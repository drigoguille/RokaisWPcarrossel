<?php
/**
 * Fonte de dados via link JSON.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data\Sources;

use SKPriceCarousel\Data\Path_Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Busca um JSON remoto, navega até o array de itens (root_path em dot-notation)
 * e resolve cada campo mapeado por caminho relativo (suporta chaves aninhadas).
 */
class Json_Source extends Abstract_Source {

	/**
	 * {@inheritDoc}
	 */
	public function get_type() {
		return 'json';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate() {
		if ( empty( $this->config['url'] ) ) {
			return new \WP_Error( 'skpc_no_url', __( 'Informe a URL do JSON.', 'sk-price-carousel' ) );
		}
		return true;
	}

	/**
	 * Decodifica e retorna o array de elementos-fonte, ou WP_Error.
	 *
	 * @return array|\WP_Error
	 */
	private function fetch_elements() {
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$args = array();
		if ( ! empty( $this->config['auth_token'] ) ) {
			$args['headers'] = array( 'Authorization' => 'Bearer ' . $this->config['auth_token'] );
		}

		$body = $this->http_get( $this->config['url'], $args );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		// Remove eventual BOM UTF-8 antes de decodificar.
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
		$data = json_decode( $body, true );
		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'skpc_json', __( 'O conteúdo retornado não é um JSON válido.', 'sk-price-carousel' ) );
		}

		$root_path = isset( $this->config['root_path'] ) ? $this->config['root_path'] : '';
		$array     = Path_Resolver::get( $data, $root_path );

		if ( ! is_array( $array ) ) {
			return new \WP_Error( 'skpc_json_path', __( 'O caminho informado não aponta para uma lista de itens.', 'sk-price-carousel' ) );
		}
		return $array;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function fetch_rows() {
		$elements = $this->fetch_elements();
		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		$rows = array();
		foreach ( $elements as $element ) {
			$row = array();
			foreach ( $this->mapping as $field => $path ) {
				if ( '' === $path ) {
					continue;
				}
				$value = Path_Resolver::get( $element, $path );
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = '';
				}
				// A chave da linha é o próprio caminho, casando com o mapping.
				$row[ $path ] = $value;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Sugere chaves de mapeamento a partir do primeiro elemento (para o admin).
	 *
	 * @return array|\WP_Error
	 */
	public function get_columns() {
		$elements = $this->fetch_elements();
		if ( is_wp_error( $elements ) ) {
			return $elements;
		}
		if ( empty( $elements ) || ! is_array( $elements[0] ) ) {
			return array();
		}
		return array_keys( $elements[0] );
	}
}
