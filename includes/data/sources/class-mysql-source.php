<?php
/**
 * Fonte de dados MySQL externo (tabela + mapeamento de colunas, sem SQL cru).
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Conecta a um MySQL externo via mysqli (timeout curto). Nomes de tabela/coluna
 * são validados por whitelist e conferidos contra a introspecção; só valores
 * (LIMIT) são numéricos garantidos por absint. Nunca há SQL vindo do usuário.
 */
class MySQL_Source extends Abstract_Source {

	/**
	 * {@inheritDoc}
	 */
	public function get_type() {
		return 'mysql';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate() {
		if ( ! class_exists( 'mysqli' ) ) {
			return new \WP_Error( 'skpc_no_mysqli', __( 'A extensão mysqli não está disponível no servidor.', 'sk-price-carousel' ) );
		}
		foreach ( array( 'host', 'database', 'username', 'table' ) as $field ) {
			if ( empty( $this->config[ $field ] ) ) {
				return new \WP_Error( 'skpc_mysql_config', __( 'Preencha host, banco, usuário e tabela.', 'sk-price-carousel' ) );
			}
		}
		return true;
	}

	/**
	 * Abre a conexão mysqli.
	 *
	 * @return \mysqli|\WP_Error
	 */
	private function connect() {
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// Em PHP 8.1+ o mysqli lança exceção por padrão; desligamos para tratar manualmente.
		if ( function_exists( 'mysqli_report' ) ) {
			mysqli_report( MYSQLI_REPORT_OFF );
		}

		$mysqli = mysqli_init();
		if ( ! $mysqli ) {
			return new \WP_Error( 'skpc_mysql_init', __( 'Não foi possível inicializar o mysqli.', 'sk-price-carousel' ) );
		}
		$mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, 5 );

		$flags = 0;
		if ( ! empty( $this->config['use_ssl'] ) ) {
			$mysqli->ssl_set( null, null, null, null, null );
			$flags = MYSQLI_CLIENT_SSL;
		}

		$connected = @$mysqli->real_connect( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->config['host'],
			$this->config['username'],
			isset( $this->config['password'] ) ? $this->config['password'] : '',
			$this->config['database'],
			isset( $this->config['port'] ) ? (int) $this->config['port'] : 3306,
			null,
			$flags
		);

		if ( ! $connected ) {
			return new \WP_Error(
				'skpc_mysql_connect',
				sprintf(
					/* translators: %s: mensagem de erro do MySQL. */
					__( 'Falha ao conectar no MySQL: %s', 'sk-price-carousel' ),
					mysqli_connect_error()
				)
			);
		}

		$mysqli->set_charset( 'utf8mb4' );
		return $mysqli;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function fetch_rows() {
		$mysqli = $this->connect();
		if ( is_wp_error( $mysqli ) ) {
			return $mysqli;
		}

		$table = isset( $this->config['table'] ) ? $this->config['table'] : '';
		if ( ! self::valid_ident( $table ) || ! in_array( $table, $this->query_tables( $mysqli ), true ) ) {
			$mysqli->close();
			return new \WP_Error( 'skpc_mysql_table', __( 'Tabela inválida ou inexistente.', 'sk-price-carousel' ) );
		}

		$available = $this->query_columns( $mysqli, $table );

		// Colunas a selecionar: só as mapeadas, validadas e existentes (sem duplicar).
		$select = array();
		foreach ( $this->mapping as $field => $col ) {
			if ( '' === $col ) {
				continue;
			}
			if ( self::valid_ident( $col ) && in_array( $col, $available, true ) ) {
				$select[ $col ] = true;
			}
		}
		if ( empty( $select ) ) {
			$mysqli->close();
			return new \WP_Error( 'skpc_mysql_cols', __( 'Nenhuma coluna válida foi mapeada.', 'sk-price-carousel' ) );
		}

		$cols_sql = implode( ',', array_map( array( __CLASS__, 'quote_ident' ), array_keys( $select ) ) );

		$order = '';
		$order_by = isset( $this->config['order_by'] ) ? $this->config['order_by'] : '';
		if ( '' !== $order_by && self::valid_ident( $order_by ) && in_array( $order_by, $available, true ) ) {
			$dir   = ( isset( $this->config['order_dir'] ) && 'DESC' === strtoupper( $this->config['order_dir'] ) ) ? 'DESC' : 'ASC';
			$order = ' ORDER BY ' . self::quote_ident( $order_by ) . ' ' . $dir;
		}

		$limit = isset( $this->config['limit'] ) ? absint( $this->config['limit'] ) : 100;
		if ( $limit < 1 ) {
			$limit = 100;
		}
		if ( $limit > self::MAX_ITEMS ) {
			$limit = self::MAX_ITEMS;
		}

		// Identificadores já validados por whitelist; LIMIT é inteiro garantido.
		$sql    = 'SELECT ' . $cols_sql . ' FROM ' . self::quote_ident( $table ) . $order . ' LIMIT ' . $limit;
		$result = $mysqli->query( $sql );
		if ( ! $result ) {
			$error = $mysqli->error;
			$mysqli->close();
			return new \WP_Error(
				'skpc_mysql_query',
				sprintf(
					/* translators: %s: erro do MySQL. */
					__( 'Erro ao consultar o MySQL: %s', 'sk-price-carousel' ),
					$error
				)
			);
		}

		$rows = array();
		while ( ( $row = $result->fetch_assoc() ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$rows[] = $row;
		}
		$result->free();
		$mysqli->close();

		return $rows;
	}

	/**
	 * Lista as tabelas do banco (para o admin).
	 *
	 * @return array|\WP_Error
	 */
	public function list_tables() {
		$mysqli = $this->connect();
		if ( is_wp_error( $mysqli ) ) {
			return $mysqli;
		}
		$tables = $this->query_tables( $mysqli );
		$mysqli->close();
		return $tables;
	}

	/**
	 * Lista as colunas de uma tabela (para o admin).
	 *
	 * @param string $table Tabela.
	 * @return array|\WP_Error
	 */
	public function list_columns( $table ) {
		$mysqli = $this->connect();
		if ( is_wp_error( $mysqli ) ) {
			return $mysqli;
		}
		if ( ! self::valid_ident( $table ) || ! in_array( $table, $this->query_tables( $mysqli ), true ) ) {
			$mysqli->close();
			return new \WP_Error( 'skpc_mysql_table', __( 'Tabela inválida.', 'sk-price-carousel' ) );
		}
		$cols = $this->query_columns( $mysqli, $table );
		$mysqli->close();
		return $cols;
	}

	/**
	 * SHOW TABLES.
	 *
	 * @param \mysqli $mysqli Conexão.
	 * @return array
	 */
	private function query_tables( $mysqli ) {
		$tables = array();
		$result = $mysqli->query( 'SHOW TABLES' );
		if ( $result ) {
			while ( ( $r = $result->fetch_row() ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$tables[] = $r[0];
			}
			$result->free();
		}
		return $tables;
	}

	/**
	 * SHOW COLUMNS (nome da tabela já validado antes da chamada).
	 *
	 * @param \mysqli $mysqli Conexão.
	 * @param string  $table  Tabela validada.
	 * @return array
	 */
	private function query_columns( $mysqli, $table ) {
		$cols   = array();
		$result = $mysqli->query( 'SHOW COLUMNS FROM ' . self::quote_ident( $table ) );
		if ( $result ) {
			while ( ( $r = $result->fetch_assoc() ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$cols[] = $r['Field'];
			}
			$result->free();
		}
		return $cols;
	}

	/**
	 * Valida um identificador SQL (tabela/coluna).
	 *
	 * @param mixed $name Nome.
	 * @return bool
	 */
	private static function valid_ident( $name ) {
		return is_string( $name ) && (bool) preg_match( '/^[A-Za-z0-9_]+$/', $name );
	}

	/**
	 * Envolve um identificador validado em crases.
	 *
	 * @param string $name Identificador já validado.
	 * @return string
	 */
	private static function quote_ident( $name ) {
		return '`' . $name . '`';
	}
}
