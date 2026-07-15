<?php
/**
 * Rotina de ativação do plugin.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

use SKPriceCarousel\Cache\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Executada no register_activation_hook. Faz o gate duro de PHP, cria as options
 * padrão (sem sobrescrever) e agenda o cron para eventuais conexões existentes.
 */
class Activator {

	/**
	 * Ativa o plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! Requirements::is_php_compatible() ) {
			deactivate_plugins( SKPC_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: versão mínima do PHP, 2: versão atual. */
						__( 'O "Rokais Carrossel WP" requer PHP %1$s ou superior. Versão atual: %2$s.', 'sk-price-carousel' ),
						SKPC_MIN_PHP_VERSION,
						PHP_VERSION
					)
				),
				esc_html__( 'Requisito não atendido', 'sk-price-carousel' ),
				array( 'back_link' => true )
			);
		}

		// Options padrão (add_option não sobrescreve valores já existentes numa reativação).
		add_option( 'skpc_settings', Plugin::default_settings() );
		add_option( 'skpc_connections', array(), '', 'no' );
		add_option( 'skpc_credentials', array(), '', 'no' );
		add_option( 'skpc_version', SKPC_VERSION );

		// Durante a ativação, plugins_loaded já disparou, então Cron::init() (que
		// registra o filtro cron_schedules) ainda não rodou. Registramos o filtro
		// aqui antes de reagendar, senão wp_schedule_event rejeita as recorrências.
		$cron = new Cron();
		$cron->init();
		$cron->reschedule_all();
	}
}
