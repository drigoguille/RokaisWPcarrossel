<?php
/**
 * Integração com o Elementor: categoria e registro do widget.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Enganchada em plugins_loaded; seus hooks só disparam quando o Elementor está
 * ativo (elementor/*), de modo que nada quebra se o Elementor estiver ausente.
 */
class Elementor_Integration {

	const CATEGORY = 'skpc';

	/**
	 * Registra os hooks do Elementor.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Cria a categoria própria de widgets.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Gerenciador de elementos.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Rokais Carrossel', 'sk-price-carousel' ),
				'icon'  => 'eicon-slider-push',
			)
		);
	}

	/**
	 * Registra o widget do carrossel.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Gerenciador de widgets.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new Widget_Carousel() );
	}
}
