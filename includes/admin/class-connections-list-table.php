<?php
/**
 * Tabela de listagem das conexões (WP_List_Table).
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Admin;

use SKPriceCarousel\Plugin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renderiza as conexões cadastradas com status, contagem e ações de linha.
 */
class Connections_List_Table extends \WP_List_Table {

	/**
	 * Construtor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'skpc_connection',
				'plural'   => 'skpc_connections',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Colunas.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'label'        => __( 'Nome', 'sk-price-carousel' ),
			'type'         => __( 'Tipo', 'sk-price-carousel' ),
			'ttl'          => __( 'Atualização', 'sk-price-carousel' ),
			'status'       => __( 'Status', 'sk-price-carousel' ),
			'item_count'   => __( 'Itens', 'sk-price-carousel' ),
			'last_refresh' => __( 'Última atualização', 'sk-price-carousel' ),
		);
	}

	/**
	 * Ações em lote.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'delete' => __( 'Remover', 'sk-price-carousel' ) );
	}

	/**
	 * Prepara os itens e processa ações em lote.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = array_values( Plugin::instance()->connections()->all() );
	}

	/**
	 * Processa a exclusão em lote.
	 *
	 * @return void
	 */
	private function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ids = isset( $_REQUEST['connection'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_REQUEST['connection'] ) ) : array();
		$repo = Plugin::instance()->connections();
		foreach ( $ids as $id ) {
			$repo->delete( $id );
		}
	}

	/**
	 * Coluna de checkbox.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="connection[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * Coluna Nome + ações de linha.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_label( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'       => 'skpc-connections',
				'action'     => 'edit',
				'connection' => $item['id'],
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'skpc_delete_connection',
					'connection' => $item['id'],
				),
				admin_url( 'admin-post.php' )
			),
			'skpc_delete_' . $item['id']
		);

		$actions = array(
			'edit'    => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Editar', 'sk-price-carousel' ) ),
			'refresh' => sprintf( '<a href="#" class="skpc-row-refresh" data-id="%s">%s</a>', esc_attr( $item['id'] ), esc_html__( 'Atualizar agora', 'sk-price-carousel' ) ),
			'delete'  => sprintf(
				'<a href="%s" class="skpc-row-delete" onclick="return confirm(&#39;%s&#39;)">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Remover esta conexão?', 'sk-price-carousel' ) ),
				esc_html__( 'Remover', 'sk-price-carousel' )
			),
		);

		return sprintf(
			'<strong>%1$s</strong><br><code class="skpc-id">%2$s</code>%3$s',
			esc_html( $item['label'] ),
			esc_html( $item['id'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Coluna Tipo.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_type( $item ) {
		$types = \SKPriceCarousel\Data\Connection_Repository::types();
		return isset( $types[ $item['type'] ] ) ? esc_html( $types[ $item['type'] ] ) : esc_html( $item['type'] );
	}

	/**
	 * Coluna TTL.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_ttl( $item ) {
		return sprintf(
			/* translators: %d: segundos. */
			esc_html__( 'a cada %ds', 'sk-price-carousel' ),
			(int) $item['ttl']
		);
	}

	/**
	 * Coluna Status.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_status( $item ) {
		$status = isset( $item['last_status'] ) ? $item['last_status'] : 'never';
		$map    = array(
			'ok'    => array( 'skpc-badge--ok', __( 'OK', 'sk-price-carousel' ) ),
			'error' => array( 'skpc-badge--err', __( 'Erro', 'sk-price-carousel' ) ),
			'never' => array( 'skpc-badge--neutral', __( 'Nunca', 'sk-price-carousel' ) ),
		);
		$badge = isset( $map[ $status ] ) ? $map[ $status ] : $map['never'];

		$html = sprintf(
			'<span class="skpc-badge %s skpc-status-%s" data-id="%s">%s</span>',
			esc_attr( $badge[0] ),
			esc_attr( $item['id'] ),
			esc_attr( $item['id'] ),
			esc_html( $badge[1] )
		);
		if ( 'error' === $status && ! empty( $item['last_error'] ) ) {
			$html .= '<br><small class="skpc-error-msg">' . esc_html( $item['last_error'] ) . '</small>';
		}
		return $html;
	}

	/**
	 * Coluna contagem de itens.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_item_count( $item ) {
		return '<span class="skpc-count-' . esc_attr( $item['id'] ) . '">' . (int) $item['item_count'] . '</span>';
	}

	/**
	 * Coluna última atualização.
	 *
	 * @param array $item Conexão.
	 * @return string
	 */
	public function column_last_refresh( $item ) {
		if ( empty( $item['last_refresh'] ) ) {
			return '<span class="skpc-when-' . esc_attr( $item['id'] ) . '">' . esc_html__( '—', 'sk-price-carousel' ) . '</span>';
		}
		return sprintf(
			'<span class="skpc-when-%1$s">%2$s</span>',
			esc_attr( $item['id'] ),
			sprintf(
				/* translators: %s: tempo relativo. */
				esc_html__( 'há %s', 'sk-price-carousel' ),
				esc_html( human_time_diff( (int) $item['last_refresh'], time() ) )
			)
		);
	}

	/**
	 * Mensagem de lista vazia.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Nenhuma conexão cadastrada. Clique em "Adicionar conexão" para começar.', 'sk-price-carousel' );
	}
}
