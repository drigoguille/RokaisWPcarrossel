<?php
/**
 * Resolvedor de "dot-paths" sobre estruturas decodificadas de JSON.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Navega caminhos como "data.items" ou "images.0.src" sem depender de uma lib
 * completa de JSONPath.
 */
class Path_Resolver {

	/**
	 * Retorna o valor no caminho informado, ou null se ausente.
	 *
	 * @param mixed  $data Estrutura (array/objeto) já decodificada.
	 * @param string $path Caminho em dot-notation. Vazio retorna $data.
	 * @return mixed
	 */
	public static function get( $data, $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return $data;
		}

		$current = $data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( is_array( $current ) ) {
				if ( array_key_exists( $segment, $current ) ) {
					$current = $current[ $segment ];
				} elseif ( is_numeric( $segment ) && array_key_exists( (int) $segment, $current ) ) {
					$current = $current[ (int) $segment ];
				} else {
					return null;
				}
			} elseif ( is_object( $current ) ) {
				if ( isset( $current->$segment ) ) {
					$current = $current->$segment;
				} else {
					return null;
				}
			} else {
				return null;
			}
		}

		return $current;
	}
}
