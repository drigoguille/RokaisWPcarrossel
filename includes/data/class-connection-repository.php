<?php
/**
 * Repositório das conexões globais — porta única de acesso.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

use SKPriceCarousel\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Persiste as conexões em duas options (autoload=no): skpc_connections (dados
 * não sensíveis + metadados) e skpc_credentials (segredos cifrados). Consumido
 * por admin, cron, REST e widget.
 */
class Connection_Repository {

	const OPTION_CONNECTIONS = 'skpc_connections';
	const OPTION_CREDENTIALS = 'skpc_credentials';

	/**
	 * Tipos de fonte suportados.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'google_sheets' => __( 'Google Sheets', 'sk-price-carousel' ),
			'json'          => __( 'Link JSON', 'sk-price-carousel' ),
			'mysql'         => __( 'MySQL (externo)', 'sk-price-carousel' ),
		);
	}

	/**
	 * Campos secretos por tipo (armazenados cifrados, fora de skpc_connections).
	 *
	 * @param string $type Tipo da fonte.
	 * @return array
	 */
	public static function secret_fields( $type ) {
		switch ( $type ) {
			case 'mysql':
				return array( 'password' );
			case 'json':
				return array( 'auth_token' );
			case 'google_sheets':
				return array( 'api_key' );
			default:
				return array();
		}
	}

	/**
	 * Todas as conexões (sem segredos), indexadas por id.
	 *
	 * @return array
	 */
	public function all() {
		$stored = get_option( self::OPTION_CONNECTIONS, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Uma conexão (sem segredos) ou null.
	 *
	 * @param string $id Id da conexão.
	 * @return array|null
	 */
	public function get( $id ) {
		$all = $this->all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * A conexão existe?
	 *
	 * @param string $id Id da conexão.
	 * @return bool
	 */
	public function exists( $id ) {
		$all = $this->all();
		return isset( $all[ $id ] );
	}

	/**
	 * Conexão pronta para uso interno: config com os segredos decifrados.
	 *
	 * NUNCA deve ser exposta ao cliente (AJAX/REST). Uso exclusivo de cron/teste.
	 *
	 * @param string $id Id da conexão.
	 * @return array|null
	 */
	public function resolve( $id ) {
		$conn = $this->get( $id );
		if ( null === $conn ) {
			return null;
		}
		$conn['config'] = $this->merge_secrets( $id, $conn['type'], isset( $conn['config'] ) ? $conn['config'] : array() );
		return $conn;
	}

	/**
	 * Junta os segredos decifrados à config de uma conexão.
	 *
	 * @param string $id     Id.
	 * @param string $type   Tipo.
	 * @param array  $config Config sem segredos.
	 * @return array
	 */
	private function merge_secrets( $id, $type, array $config ) {
		$creds = get_option( self::OPTION_CREDENTIALS, array() );
		$creds = is_array( $creds ) ? $creds : array();
		foreach ( self::secret_fields( $type ) as $field ) {
			$config[ $field ] = ( isset( $creds[ $id ][ $field ] ) )
				? Secret_Cipher::decrypt( $creds[ $id ][ $field ] )
				: '';
		}
		return $config;
	}

	/**
	 * Sanitiza e persiste uma conexão. Preserva segredos quando o campo vem vazio.
	 *
	 * @param array $input Dados crus (tipicamente de $_POST já com unslash).
	 * @return string|\WP_Error Id salvo ou erro.
	 */
	public function save( array $input ) {
		$types = self::types();
		$type  = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		if ( ! isset( $types[ $type ] ) ) {
			return new \WP_Error( 'skpc_bad_type', __( 'Tipo de fonte inválido.', 'sk-price-carousel' ) );
		}

		$label = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '';
		if ( '' === $label ) {
			return new \WP_Error( 'skpc_no_label', __( 'Informe um nome para a conexão.', 'sk-price-carousel' ) );
		}

		$settings    = Plugin::instance()->settings();
		$default_ttl = isset( $settings['default_ttl'] ) ? (int) $settings['default_ttl'] : 900;
		$ttl         = isset( $input['ttl'] ) ? absint( $input['ttl'] ) : $default_ttl;
		if ( $ttl < 60 ) {
			$ttl = 60;
		}

		$id       = $this->resolve_id( isset( $input['id'] ) ? $input['id'] : '', $label );
		$existing = $this->get( $id );

		$config  = $this->sanitize_config( $type, isset( $input['config'] ) ? (array) $input['config'] : array() );
		$mapping = $this->sanitize_mapping( isset( $input['mapping'] ) ? (array) $input['mapping'] : array() );

		$connection = array(
			'id'           => $id,
			'label'        => $label,
			'type'         => $type,
			'ttl'          => $ttl,
			'config'       => $config,
			'mapping'      => $mapping,
			'last_status'  => $existing ? $existing['last_status'] : 'never',
			'last_refresh' => $existing ? (int) $existing['last_refresh'] : 0,
			'last_error'   => $existing ? $existing['last_error'] : '',
			'item_count'   => $existing ? (int) $existing['item_count'] : 0,
		);

		$this->store_secrets( $id, $type, isset( $input['config'] ) ? (array) $input['config'] : array() );

		$all        = $this->all();
		$all[ $id ] = $connection;
		update_option( self::OPTION_CONNECTIONS, $all, false );

		/**
		 * Disparado após salvar uma conexão. Cache e Cron reagem para invalidar
		 * e reagendar.
		 *
		 * @param string $id Id da conexão.
		 */
		do_action( 'skpc_connection_saved', $id );

		return $id;
	}

	/**
	 * Remove uma conexão e seus segredos.
	 *
	 * @param string $id Id.
	 * @return bool
	 */
	public function delete( $id ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPTION_CONNECTIONS, $all, false );

		$creds = get_option( self::OPTION_CREDENTIALS, array() );
		if ( is_array( $creds ) && isset( $creds[ $id ] ) ) {
			unset( $creds[ $id ] );
			update_option( self::OPTION_CREDENTIALS, $creds, false );
		}

		/**
		 * Disparado após excluir uma conexão.
		 *
		 * @param string $id Id da conexão.
		 */
		do_action( 'skpc_connection_deleted', $id );

		return true;
	}

	/**
	 * Atualiza os metadados de status após uma tentativa de refresh.
	 *
	 * @param string $id     Id.
	 * @param string $status 'ok'|'error'.
	 * @param int    $count  Quantidade de itens.
	 * @param string $error  Mensagem de erro (se houver).
	 * @return void
	 */
	public function update_status( $id, $status, $count = 0, $error = '' ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return;
		}
		$all[ $id ]['last_status']  = ( 'ok' === $status ) ? 'ok' : 'error';
		$all[ $id ]['last_refresh'] = time();
		$all[ $id ]['last_error']   = sanitize_text_field( $error );
		if ( 'ok' === $status ) {
			$all[ $id ]['item_count'] = (int) $count;
		}
		update_option( self::OPTION_CONNECTIONS, $all, false );
	}

	/**
	 * Gera/normaliza o id da conexão garantindo unicidade.
	 *
	 * @param string $requested Id solicitado (edição) ou vazio (novo).
	 * @param string $label     Rótulo para derivar o slug.
	 * @return string
	 */
	private function resolve_id( $requested, $label ) {
		$requested = sanitize_key( $requested );
		if ( '' !== $requested && $this->exists( $requested ) ) {
			return $requested; // Edição de conexão existente.
		}

		$base = sanitize_key( $requested ? $requested : sanitize_title( $label ) );
		if ( '' === $base ) {
			$base = 'conexao';
		}
		$id      = $base;
		$all     = $this->all();
		$counter = 2;
		while ( isset( $all[ $id ] ) ) {
			$id = $base . '-' . $counter;
			++$counter;
		}
		return $id;
	}

	/**
	 * Monta uma conexão efêmera (não persistida) para teste/introspecção.
	 *
	 * Sanitiza config/mapping e resolve os segredos: usa o valor informado quando
	 * presente ou, se vazio e houver id, o segredo já salvo.
	 *
	 * @param array $input Dados crus.
	 * @return array|\WP_Error
	 */
	public function prepare_for_test( array $input ) {
		$types = self::types();
		$type  = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
		if ( ! isset( $types[ $type ] ) ) {
			return new \WP_Error( 'skpc_bad_type', __( 'Tipo de fonte inválido.', 'sk-price-carousel' ) );
		}

		$raw_config = isset( $input['config'] ) ? (array) $input['config'] : array();
		$config     = $this->sanitize_config( $type, $raw_config );
		$mapping    = $this->sanitize_mapping( isset( $input['mapping'] ) ? (array) $input['mapping'] : array() );
		$id         = isset( $input['id'] ) ? sanitize_key( $input['id'] ) : '';

		foreach ( self::secret_fields( $type ) as $field ) {
			$value = isset( $raw_config[ $field ] ) ? (string) $raw_config[ $field ] : '';
			if ( '' === $value && '' !== $id ) {
				$config[ $field ] = $this->get_secret( $id, $field );
			} else {
				$config[ $field ] = $value;
			}
		}

		return array(
			'type'    => $type,
			'config'  => $config,
			'mapping' => $mapping,
		);
	}

	/**
	 * Indica se há um segredo salvo para o campo (para exibir placeholder no form).
	 *
	 * @param string $id    Id.
	 * @param string $field Campo.
	 * @return bool
	 */
	public function has_secret( $id, $field ) {
		$creds = get_option( self::OPTION_CREDENTIALS, array() );
		return is_array( $creds ) && ! empty( $creds[ $id ][ $field ] );
	}

	/**
	 * Recupera e decifra um segredo salvo.
	 *
	 * @param string $id    Id.
	 * @param string $field Campo.
	 * @return string
	 */
	private function get_secret( $id, $field ) {
		$creds = get_option( self::OPTION_CREDENTIALS, array() );
		if ( is_array( $creds ) && isset( $creds[ $id ][ $field ] ) ) {
			return Secret_Cipher::decrypt( $creds[ $id ][ $field ] );
		}
		return '';
	}

	/**
	 * Sanitiza a config não sensível conforme o tipo.
	 *
	 * @param string $type   Tipo.
	 * @param array  $config Config crua.
	 * @return array
	 */
	private function sanitize_config( $type, array $config ) {
		$out = array();
		switch ( $type ) {
			case 'google_sheets':
				$mode           = isset( $config['mode'] ) ? sanitize_key( $config['mode'] ) : 'csv';
				$out['mode']    = in_array( $mode, array( 'csv', 'gviz', 'api' ), true ) ? $mode : 'csv';
				$out['sheet_id'] = isset( $config['sheet_id'] ) ? sanitize_text_field( $config['sheet_id'] ) : '';
				$out['gid']     = isset( $config['gid'] ) ? sanitize_text_field( $config['gid'] ) : '0';
				$out['range']   = isset( $config['range'] ) ? sanitize_text_field( $config['range'] ) : 'A1:Z1000';
				break;

			case 'json':
				$out['url']       = isset( $config['url'] ) ? esc_url_raw( trim( $config['url'] ) ) : '';
				$out['root_path'] = isset( $config['root_path'] ) ? sanitize_text_field( $config['root_path'] ) : '';
				break;

			case 'mysql':
				$out['host']      = isset( $config['host'] ) ? sanitize_text_field( $config['host'] ) : '';
				$out['port']      = isset( $config['port'] ) ? absint( $config['port'] ) : 3306;
				if ( ! $out['port'] ) {
					$out['port'] = 3306;
				}
				$out['database']  = isset( $config['database'] ) ? sanitize_text_field( $config['database'] ) : '';
				$out['username']  = isset( $config['username'] ) ? sanitize_text_field( $config['username'] ) : '';
				$out['table']     = isset( $config['table'] ) ? sanitize_text_field( $config['table'] ) : '';
				$out['use_ssl']   = ! empty( $config['use_ssl'] ) ? 1 : 0;
				$out['limit']     = isset( $config['limit'] ) ? absint( $config['limit'] ) : 100;
				$out['order_by']  = isset( $config['order_by'] ) ? sanitize_text_field( $config['order_by'] ) : '';
				$dir              = isset( $config['order_dir'] ) ? strtoupper( $config['order_dir'] ) : 'ASC';
				$out['order_dir'] = ( 'DESC' === $dir ) ? 'DESC' : 'ASC';
				break;
		}
		return $out;
	}

	/**
	 * Sanitiza o mapa de colunas.
	 *
	 * @param array $mapping Mapa cru.
	 * @return array
	 */
	private function sanitize_mapping( array $mapping ) {
		$out = array();
		foreach ( Item_Schema::mappable_fields() as $field ) {
			$out[ $field ] = isset( $mapping[ $field ] ) ? sanitize_text_field( $mapping[ $field ] ) : '';
		}
		return $out;
	}

	/**
	 * Cifra e persiste os segredos; preserva o valor existente quando o novo é vazio.
	 *
	 * @param string $id     Id.
	 * @param string $type   Tipo.
	 * @param array  $config Config crua (pode conter os segredos).
	 * @return void
	 */
	private function store_secrets( $id, $type, array $config ) {
		$secret_fields = self::secret_fields( $type );
		if ( empty( $secret_fields ) ) {
			return;
		}

		$creds = get_option( self::OPTION_CREDENTIALS, array() );
		$creds = is_array( $creds ) ? $creds : array();
		if ( ! isset( $creds[ $id ] ) || ! is_array( $creds[ $id ] ) ) {
			$creds[ $id ] = array();
		}

		foreach ( $secret_fields as $field ) {
			$value = isset( $config[ $field ] ) ? (string) $config[ $field ] : '';
			if ( '' !== $value ) {
				$creds[ $id ][ $field ] = Secret_Cipher::encrypt( $value );
			}
			// Campo vazio: mantém o segredo já existente (não sobrescreve).
		}

		update_option( self::OPTION_CREDENTIALS, $creds, false );
	}
}
