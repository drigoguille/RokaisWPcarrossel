<?php
/**
 * Endpoint REST que serve os itens normalizados do cache.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Rest;

use SKPriceCarousel\Plugin;
use SKPriceCarousel\Data\Price_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/skpc/v1/items?connection={id}
 *
 * Read-only e público: serve APENAS o schema normalizado do cache (sem
 * credenciais), com ETag/304, Cache-Control e um throttle leve por IP. Nunca
 * dispara fetch síncrono à fonte — apenas agenda refresh assíncrono se stale.
 */
class Items_Rest_Controller {

	/**
	 * Engancha o registro das rotas.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra a rota /items.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SKPC_REST_NS,
			'/items',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'connection' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_connection' ),
					),
					'limit'      => array(
						'default'           => 24,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $v ) {
							return $v >= 1 && $v <= 100;
						},
					),
					'offset'     => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Valida a existência da conexão.
	 *
	 * @param string $value Id.
	 * @return bool
	 */
	public function validate_connection( $value ) {
		return Plugin::instance()->connections()->exists( $value );
	}

	/**
	 * Handler da rota.
	 *
	 * @param \WP_REST_Request $request Requisição.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		if ( ! $this->allow_request() ) {
			return new \WP_REST_Response( array( 'status' => 'throttled' ), 429 );
		}

		$id     = $request['connection'];
		$limit  = (int) $request['limit'];
		$offset = (int) $request['offset'];
		$cache  = Plugin::instance()->cache();

		$wrapper = $cache->get( $id );

		// Cache ainda não populado: agenda o aquecimento e responde 202.
		if ( null === $wrapper ) {
			$cache->schedule_async( $id );
			$response = new \WP_REST_Response(
				array(
					'items'  => array(),
					'status' => 'warming',
				),
				202
			);
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		if ( ! empty( $wrapper['stale'] ) ) {
			$cache->schedule_async( $id );
		}

		$etag = '"' . $wrapper['etag'] . '"';

		// 304 quando o cliente já tem a versão atual.
		$inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		if ( '' !== $inm && $inm === $etag ) {
			$response = new \WP_REST_Response( null, 304 );
			$response->header( 'ETag', $etag );
			return $response;
		}

		$items = array_slice( $wrapper['items'], $offset, $limit );
		$items = array_map( array( $this, 'decorate' ), $items );

		$response = new \WP_REST_Response(
			array(
				'items'     => $items,
				'etag'      => $wrapper['etag'],
				'generated' => (int) $wrapper['generated'],
				'status'    => 'ok',
			),
			200
		);
		$response->header( 'ETag', $etag );
		$response->header( 'Cache-Control', 'public, max-age=30, s-maxage=' . max( 30, (int) $wrapper['ttl'] ) );

		return $response;
	}

	/**
	 * Acrescenta os campos de exibição (formatados no servidor) para casar com o SSR.
	 *
	 * @param array $item Item normalizado.
	 * @return array
	 */
	private function decorate( $item ) {
		$has_promo             = ( isset( $item['sale_price'] ) && null !== $item['sale_price'] );
		$item['has_promo']     = $has_promo;
		$item['price_display'] = Price_Parser::format( isset( $item['price'] ) ? $item['price'] : null );
		$item['sale_display']  = $has_promo ? Price_Parser::format( $item['sale_price'] ) : '';
		return $item;
	}

	/**
	 * Throttle leve por IP (janela de 10s). Ignorado com object cache persistente
	 * ausente apenas quando não há IP disponível.
	 *
	 * @return bool
	 */
	private function allow_request() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'skpc_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 40 ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 );
		return true;
	}
}
