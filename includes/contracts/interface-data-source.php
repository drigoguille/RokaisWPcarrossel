<?php
/**
 * Contrato comum das fontes de dados.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Toda fonte (Google Sheets, JSON, MySQL) implementa esta interface e devolve
 * itens já normalizados no schema comum, isolando o resto do plugin dos detalhes
 * de cada origem.
 */
interface Data_Source_Interface {

	/**
	 * Identificador do tipo de fonte.
	 *
	 * @return string
	 */
	public function get_type();

	/**
	 * Valida a configuração da fonte antes de buscar dados.
	 *
	 * @return true|\WP_Error
	 */
	public function validate();

	/**
	 * Busca os dados na origem e retorna itens normalizados.
	 *
	 * @return array|\WP_Error Array de itens normalizados ou erro.
	 */
	public function fetch();
}
