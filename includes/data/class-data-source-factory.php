<?php
/**
 * Fábrica de fontes de dados.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

use SKPriceCarousel\Data\Sources\Google_Sheets_Source;
use SKPriceCarousel\Data\Sources\Json_Source;
use SKPriceCarousel\Data\Sources\MySQL_Source;

defined( 'ABSPATH' ) || exit;

/**
 * Instancia a implementação de fonte correta a partir do tipo da conexão. O
 * array de conexão deve conter a config já com os segredos (via resolve()).
 */
class Data_Source_Factory {

	/**
	 * Cria a fonte adequada.
	 *
	 * @param array $connection Conexão resolvida (config com segredos).
	 * @return \SKPriceCarousel\Contracts\Data_Source_Interface|\WP_Error
	 */
	public static function make( array $connection ) {
		$type = isset( $connection['type'] ) ? $connection['type'] : '';
		switch ( $type ) {
			case 'google_sheets':
				return new Google_Sheets_Source( $connection );
			case 'json':
				return new Json_Source( $connection );
			case 'mysql':
				return new MySQL_Source( $connection );
			default:
				return new \WP_Error( 'skpc_unknown_type', __( 'Tipo de fonte desconhecido.', 'sk-price-carousel' ) );
		}
	}
}
