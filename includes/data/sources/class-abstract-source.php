<?php
/**
 * Classe base das fontes de dados.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data\Sources;

use SKPriceCarousel\Contracts\Data_Source_Interface;
use SKPriceCarousel\Data\Item_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Template method: as concretas implementam apenas fetch_rows() (linhas cruas
 * associativas) e validate(); a base cuida da normalização, do teto de itens e
 * de utilidades HTTP com guarda anti-SSRF.
 */
abstract class Abstract_Source implements Data_Source_Interface {

	const MAX_ITEMS = 200;

	/**
	 * Conexão completa.
	 *
	 * @var array
	 */
	protected $connection;

	/**
	 * Config da conexão (com segredos, quando resolvida).
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Mapa campo => coluna/caminho.
	 *
	 * @var array
	 */
	protected $mapping;

	/**
	 * Construtor.
	 *
	 * @param array $connection Conexão.
	 */
	public function __construct( array $connection ) {
		$this->connection = $connection;
		$this->config     = ( isset( $connection['config'] ) && is_array( $connection['config'] ) ) ? $connection['config'] : array();
		$this->mapping    = ( isset( $connection['mapping'] ) && is_array( $connection['mapping'] ) ) ? $connection['mapping'] : array();
	}

	/**
	 * Busca as linhas cruas (associativas por coluna/caminho).
	 *
	 * @return array|\WP_Error
	 */
	abstract protected function fetch_rows();

	/**
	 * {@inheritDoc}
	 */
	public function fetch() {
		$rows = $this->fetch_rows();
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$items = array();
		foreach ( $rows as $row ) {
			if ( count( $items ) >= self::MAX_ITEMS ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = Item_Schema::normalize_row( $row, $this->mapping );

			// Descarta linhas totalmente vazias (sem imagem, título e preço).
			if ( '' === $item['image'] && '' === $item['title'] && null === $item['price'] ) {
				continue;
			}
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * GET HTTP com timeout e guarda anti-SSRF.
	 *
	 * @param string $url  URL.
	 * @param array  $args Args extras do wp_remote_get.
	 * @return string|\WP_Error Corpo da resposta ou erro.
	 */
	protected function http_get( $url, array $args = array() ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return new \WP_Error( 'skpc_bad_url', __( 'URL inválida.', 'sk-price-carousel' ) );
		}

		$guard = self::guard_url( $url );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$defaults = array(
			'timeout'     => 10,
			'redirection' => 3,
			'user-agent'  => 'SKPriceCarousel/' . SKPC_VERSION . '; ' . home_url( '/' ),
		);
		// wp_safe_remote_get valida a URL inicial E cada redirecionamento contra
		// hosts internos/privados (resolvendo o hostname), fechando o SSRF por
		// redirect e por nome que resolve para rede interna.
		$response = wp_safe_remote_get( $url, array_merge( $defaults, $args ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'skpc_http',
				sprintf(
					/* translators: %d: código HTTP. */
					__( 'A fonte respondeu com HTTP %d.', 'sk-price-carousel' ),
					$code
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Valida esquema e bloqueia hosts internos (anti-SSRF).
	 *
	 * @param string $url URL.
	 * @return true|\WP_Error
	 */
	public static function guard_url( $url ) {
		$parts  = wp_parse_url( $url );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'skpc_scheme', __( 'Apenas URLs http/https são permitidas.', 'sk-price-carousel' ) );
		}
		$host = isset( $parts['host'] ) ? $parts['host'] : '';
		if ( '' === $host ) {
			return new \WP_Error( 'skpc_host', __( 'Host inválido na URL.', 'sk-price-carousel' ) );
		}
		if ( self::is_blocked_host( $host ) ) {
			return new \WP_Error( 'skpc_ssrf', __( 'Host não permitido (rede interna/loopback).', 'sk-price-carousel' ) );
		}
		return true;
	}

	/**
	 * Bloqueia localhost e IPs privados/reservados.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	protected static function is_blocked_host( $host ) {
		$host = strtolower( $host );
		if ( '' === $host || 'localhost' === $host || 'ip6-localhost' === $host ) {
			return true;
		}
		// Normaliza IPv6 entre colchetes (ex.: "[::1]" -> "::1").
		if ( '[' === substr( $host, 0, 1 ) && ']' === substr( $host, -1 ) ) {
			$host = substr( $host, 1, -1 );
		}
		if ( '::1' === $host ) {
			return true;
		}
		// Bloqueia quando o host já é um IP literal privado/reservado (v4 ou v6).
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$public = filter_var(
				$host,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
			return false === $public;
		}
		return false;
	}
}
