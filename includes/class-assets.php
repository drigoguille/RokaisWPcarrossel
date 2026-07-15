<?php
/**
 * Registro e enfileiramento de assets (frontend, preview do Elementor e admin).
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Centraliza os handles de assets para garantir carga única mesmo com várias
 * instâncias do widget na mesma página. O Swiper é empacotado localmente.
 */
class Assets {

	const SWIPER_HANDLE   = 'skpc-swiper';
	const CAROUSEL_HANDLE = 'skpc-carousel';
	const ADMIN_HANDLE    = 'skpc-admin';
	const SWIPER_VERSION  = '11.1.14';

	/**
	 * Evita localizar/registrar duas vezes.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Engancha os pontos de registro/enfileiramento.
	 *
	 * @return void
	 */
	public function init() {
		// Registro cedo no frontend para que get_script_depends()/get_style_depends()
		// do widget consigam enfileirar por dependência somente onde há carrossel.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend' ), 1 );

		// No preview do Elementor precisamos enfileirar de fato para ver o carrossel.
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_preview' ) );

		// Assets do admin somente nas telas do plugin.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin' ) );
	}

	/**
	 * Registra (sem enfileirar) os assets do carrossel no frontend.
	 *
	 * @return void
	 */
	public function register_frontend() {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		wp_register_style(
			self::SWIPER_HANDLE,
			SKPC_ASSETS_URL . 'vendor/swiper/swiper-bundle.min.css',
			array(),
			self::SWIPER_VERSION
		);
		wp_register_script(
			self::SWIPER_HANDLE,
			SKPC_ASSETS_URL . 'vendor/swiper/swiper-bundle.min.js',
			array(),
			self::SWIPER_VERSION,
			true
		);

		wp_register_style(
			self::CAROUSEL_HANDLE,
			SKPC_ASSETS_URL . 'css/skpc-carousel.css',
			array( self::SWIPER_HANDLE ),
			$this->asset_version( 'css/skpc-carousel.css' )
		);
		wp_register_script(
			self::CAROUSEL_HANDLE,
			SKPC_ASSETS_URL . 'js/skpc-carousel.js',
			array( self::SWIPER_HANDLE ),
			$this->asset_version( 'js/skpc-carousel.js' ),
			true
		);

		wp_localize_script(
			self::CAROUSEL_HANDLE,
			'SKPCConfig',
			array(
				'restBase' => esc_url_raw( rest_url( SKPC_REST_NS . '/items' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'loading' => __( 'Carregando…', 'sk-price-carousel' ),
					'empty'   => __( 'Nenhum item disponível.', 'sk-price-carousel' ),
					'error'   => __( 'Não foi possível atualizar os itens.', 'sk-price-carousel' ),
					'prev'    => __( 'Anterior', 'sk-price-carousel' ),
					'next'    => __( 'Próximo', 'sk-price-carousel' ),
					'off'     => __( 'de', 'sk-price-carousel' ),
				),
			)
		);
	}

	/**
	 * Enfileira os assets do carrossel no preview do editor Elementor.
	 *
	 * @return void
	 */
	public function enqueue_preview() {
		$this->register_frontend();
		wp_enqueue_style( self::CAROUSEL_HANDLE );
		wp_enqueue_script( self::CAROUSEL_HANDLE );
	}

	/**
	 * Registra e enfileira os assets do admin apenas nas telas do plugin.
	 *
	 * @param string $hook_suffix Hook da tela atual.
	 * @return void
	 */
	public function register_admin( $hook_suffix ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'skpc-' ) ) {
			return;
		}

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			SKPC_ASSETS_URL . 'admin/admin.css',
			array(),
			$this->asset_version( 'admin/admin.css' )
		);
		wp_enqueue_script(
			self::ADMIN_HANDLE,
			SKPC_ASSETS_URL . 'admin/admin.js',
			array( 'jquery', 'wp-i18n' ),
			$this->asset_version( 'admin/admin.js' ),
			true
		);
		wp_localize_script(
			self::ADMIN_HANDLE,
			'SKPCAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'skpc_admin' ),
				'i18n'    => array(
					'testing'    => __( 'Testando…', 'sk-price-carousel' ),
					'refreshing' => __( 'Atualizando…', 'sk-price-carousel' ),
					'loading'    => __( 'Carregando…', 'sk-price-carousel' ),
					'error'      => __( 'Ocorreu um erro. Tente novamente.', 'sk-price-carousel' ),
					'noItems'    => __( 'Nenhum item retornado pela fonte.', 'sk-price-carousel' ),
					'selectCol'  => __( '— selecionar —', 'sk-price-carousel' ),
				),
			)
		);
	}

	/**
	 * Versão do asset baseada no filemtime (cache-busting) com fallback na versão do plugin.
	 *
	 * @param string $relative_path Caminho relativo dentro de assets/.
	 * @return string|int
	 */
	private function asset_version( $relative_path ) {
		$file = SKPC_PATH . 'assets/' . $relative_path;
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}
		return SKPC_VERSION;
	}
}
