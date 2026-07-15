<?php
/**
 * Carregamento do textdomain de tradução.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Carrega o catálogo de traduções. O WP 6.7 exige que isso ocorra a partir do
 * hook init (chamar antes dispara _doing_it_wrong nas traduções JIT).
 */
class I18n {

	/**
	 * Registra o carregamento do textdomain.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Carrega o textdomain do plugin.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'sk-price-carousel',
			false,
			dirname( SKPC_BASENAME ) . '/languages'
		);
	}
}
