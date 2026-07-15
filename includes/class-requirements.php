<?php
/**
 * Verificação de compatibilidade (PHP / Elementor) e avisos administrativos.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Gate de dependências. O PHP é bloqueante na ativação; o Elementor é soft-fail:
 * o plugin continua ativo e apenas exibe um aviso no admin.
 */
class Requirements {

	/**
	 * O PHP em execução atende à versão mínima?
	 *
	 * @return bool
	 */
	public static function is_php_compatible() {
		return version_compare( PHP_VERSION, SKPC_MIN_PHP_VERSION, '>=' );
	}

	/**
	 * O Elementor está carregado?
	 *
	 * @return bool
	 */
	public static function is_elementor_installed() {
		return defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * O Elementor carregado atende à versão mínima?
	 *
	 * @return bool
	 */
	public static function is_elementor_compatible() {
		return self::is_elementor_installed()
			&& version_compare( ELEMENTOR_VERSION, SKPC_MIN_ELEMENTOR_VERSION, '>=' );
	}

	/**
	 * A extensão OpenSSL está disponível (necessária para cifrar credenciais)?
	 *
	 * @return bool
	 */
	public static function has_openssl() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * A extensão mysqli está disponível (necessária para a fonte MySQL)?
	 *
	 * @return bool
	 */
	public static function has_mysqli() {
		return class_exists( 'mysqli' );
	}

	/**
	 * Exibe, quando aplicável, o aviso de Elementor ausente ou desatualizado.
	 *
	 * Executado no hook admin_notices, quando o Elementor já terminou de carregar.
	 *
	 * @return void
	 */
	public static function maybe_render_elementor_notice() {
		if ( self::is_elementor_compatible() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::is_elementor_installed() ) {
			$message = sprintf(
				/* translators: %s: nome do plugin exigido (Elementor). */
				esc_html__( 'O "Rokais Carrossel WP" precisa do %s ativo para funcionar.', 'sk-price-carousel' ),
				'<strong>Elementor</strong>'
			);
		} else {
			$message = sprintf(
				/* translators: 1: nome do plugin, 2: versão mínima exigida. */
				esc_html__( 'O "Rokais Carrossel WP" requer %1$s na versão %2$s ou superior.', 'sk-price-carousel' ),
				'<strong>Elementor</strong>',
				esc_html( SKPC_MIN_ELEMENTOR_VERSION )
			);
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			wp_kses( $message, array( 'strong' => array() ) )
		);
	}
}
