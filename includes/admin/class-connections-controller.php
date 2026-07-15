<?php
/**
 * Persistência das conexões via admin-post (salvar/excluir).
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Admin;

use SKPriceCarousel\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handlers de admin_post que gravam/removem conexões com verificação de nonce e
 * capability e redirecionam de volta à lista com feedback.
 */
class Connections_Controller {

	const CAP = 'manage_options';

	/**
	 * Engancha os handlers.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_post_skpc_save_connection', array( $this, 'save' ) );
		add_action( 'admin_post_skpc_delete_connection', array( $this, 'delete' ) );
	}

	/**
	 * Salva (cria/atualiza) uma conexão.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'sk-price-carousel' ) );
		}
		check_admin_referer( 'skpc_save_connection' );

		$input = array(
			'id'      => isset( $_POST['connection_id'] ) ? sanitize_key( wp_unslash( $_POST['connection_id'] ) ) : '',
			'label'   => isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes.
			'type'    => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
			'ttl'     => isset( $_POST['ttl'] ) ? absint( wp_unslash( $_POST['ttl'] ) ) : 0,
			'config'  => isset( $_POST['config'] ) ? wp_unslash( (array) $_POST['config'] ) : array(),   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes per field.
			'mapping' => isset( $_POST['mapping'] ) ? wp_unslash( (array) $_POST['mapping'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes per field.
		);

		$result = Plugin::instance()->connections()->save( $input );
		$notice = is_wp_error( $result ) ? 'error' : 'saved';

		$this->redirect_to_list( $notice );
	}

	/**
	 * Exclui uma conexão.
	 *
	 * @return void
	 */
	public function delete() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'sk-price-carousel' ) );
		}
		$id = isset( $_GET['connection'] ) ? sanitize_key( wp_unslash( $_GET['connection'] ) ) : '';
		check_admin_referer( 'skpc_delete_' . $id );

		Plugin::instance()->connections()->delete( $id );
		$this->redirect_to_list( 'deleted' );
	}

	/**
	 * Redireciona para a listagem com um código de aviso.
	 *
	 * @param string $notice Código.
	 * @return void
	 */
	private function redirect_to_list( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'skpc-connections',
					'skpc_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
