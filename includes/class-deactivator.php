<?php
/**
 * Rotina de desativação do plugin.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

use SKPriceCarousel\Cache\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Executada no register_deactivation_hook. Limpa apenas os eventos de cron
 * agendados; as options e credenciais são preservadas (limpeza só no uninstall).
 */
class Deactivator {

	/**
	 * Desativa o plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		( new Cron() )->clear_all();
	}
}
