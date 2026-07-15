<?php
/**
 * Handlers AJAX do admin: testar, atualizar agora, detectar colunas, listar tabelas.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Admin;

use SKPriceCarousel\Plugin;
use SKPriceCarousel\Data\Data_Source_Factory;
use SKPriceCarousel\Data\Price_Parser;
use SKPriceCarousel\Data\Sources\MySQL_Source;

defined( 'ABSPATH' ) || exit;

/**
 * Todos os handlers exigem nonce skpc_admin + capability manage_options e nunca
 * são registrados como nopriv.
 */
class Admin_Ajax {

	const CAP = 'manage_options';

	/**
	 * Engancha os handlers.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_skpc_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_skpc_refresh_now', array( $this, 'refresh_now' ) );
		add_action( 'wp_ajax_skpc_detect_columns', array( $this, 'detect_columns' ) );
		add_action( 'wp_ajax_skpc_list_tables', array( $this, 'list_tables' ) );
	}

	/**
	 * Verifica nonce e capability comuns a todos os handlers.
	 *
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'skpc_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'sk-price-carousel' ) ), 403 );
		}
	}

	/**
	 * Monta o input cru a partir do POST.
	 *
	 * @return array
	 */
	private function read_input() {
		return array(
			'id'      => isset( $_POST['connection_id'] ) ? sanitize_key( wp_unslash( $_POST['connection_id'] ) ) : '',
			'type'    => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
			'config'  => isset( $_POST['config'] ) ? wp_unslash( (array) $_POST['config'] ) : array(),   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in repository.
			'mapping' => isset( $_POST['mapping'] ) ? wp_unslash( (array) $_POST['mapping'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in repository.
		);
	}

	/**
	 * Cria a fonte a partir do input do formulário (não persistido).
	 *
	 * @return \SKPriceCarousel\Contracts\Data_Source_Interface|\WP_Error
	 */
	private function make_source() {
		$conn = Plugin::instance()->connections()->prepare_for_test( $this->read_input() );
		if ( is_wp_error( $conn ) ) {
			return $conn;
		}
		return Data_Source_Factory::make( $conn );
	}

	/**
	 * Testa a conexão e devolve amostra + contagem.
	 *
	 * @return void
	 */
	public function test_connection() {
		$this->guard();

		$source = $this->make_source();
		if ( is_wp_error( $source ) ) {
			wp_send_json_error( array( 'message' => $source->get_error_message() ) );
		}

		$valid = $source->validate();
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ) );
		}

		$items = $source->fetch();
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ) );
		}

		$preview = array();
		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$has_promo = ( null !== $item['sale_price'] );
			$preview[] = array(
				'image'         => $item['image'],
				'title'         => $item['title'],
				'price_display' => Price_Parser::format( $item['price'] ),
				'sale_display'  => $has_promo ? Price_Parser::format( $item['sale_price'] ) : '',
				'badge'         => $item['badge'],
			);
		}

		wp_send_json_success(
			array(
				'count'   => count( $items ),
				'items'   => $preview,
				'message' => sprintf(
					/* translators: %d: quantidade de itens. */
					_n( '%d item encontrado.', '%d itens encontrados.', count( $items ), 'sk-price-carousel' ),
					count( $items )
				),
			)
		);
	}

	/**
	 * Força um refresh do cache de uma conexão salva.
	 *
	 * @return void
	 */
	public function refresh_now() {
		$this->guard();

		$id   = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$repo = Plugin::instance()->connections();
		if ( '' === $id || ! $repo->exists( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Conexão inválida.', 'sk-price-carousel' ) ) );
		}

		$result = Plugin::instance()->cache()->rebuild( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$conn = $repo->get( $id );
		wp_send_json_success(
			array(
				'status' => $conn['last_status'],
				'count'  => (int) $conn['item_count'],
				'when'   => sprintf(
					/* translators: %s: tempo relativo. */
					__( 'há %s', 'sk-price-carousel' ),
					human_time_diff( (int) $conn['last_refresh'], time() )
				),
			)
		);
	}

	/**
	 * Detecta colunas/chaves da fonte para o mapeamento.
	 *
	 * @return void
	 */
	public function detect_columns() {
		$this->guard();

		$source = $this->make_source();
		if ( is_wp_error( $source ) ) {
			wp_send_json_error( array( 'message' => $source->get_error_message() ) );
		}

		if ( $source instanceof MySQL_Source ) {
			$input = $this->read_input();
			$table = isset( $input['config']['table'] ) ? $input['config']['table'] : '';
			if ( '' === $table ) {
				wp_send_json_error( array( 'message' => __( 'Escolha a tabela antes de detectar as colunas.', 'sk-price-carousel' ) ) );
			}
			$columns = $source->list_columns( $table );
		} else {
			$columns = $source->get_columns();
		}

		if ( is_wp_error( $columns ) ) {
			wp_send_json_error( array( 'message' => $columns->get_error_message() ) );
		}

		wp_send_json_success( array( 'columns' => array_values( array_map( 'strval', (array) $columns ) ) ) );
	}

	/**
	 * Lista as tabelas de um MySQL externo.
	 *
	 * @return void
	 */
	public function list_tables() {
		$this->guard();

		$source = $this->make_source();
		if ( is_wp_error( $source ) ) {
			wp_send_json_error( array( 'message' => $source->get_error_message() ) );
		}
		if ( ! $source instanceof MySQL_Source ) {
			wp_send_json_error( array( 'message' => __( 'Disponível apenas para MySQL.', 'sk-price-carousel' ) ) );
		}

		$tables = $source->list_tables();
		if ( is_wp_error( $tables ) ) {
			wp_send_json_error( array( 'message' => $tables->get_error_message() ) );
		}

		wp_send_json_success( array( 'tables' => array_values( array_map( 'strval', (array) $tables ) ) ) );
	}
}
