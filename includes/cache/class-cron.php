<?php
/**
 * Agendamento WP-Cron por conexão + invalidação de cache.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Cache;

use SKPriceCarousel\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registra intervalos dinâmicos por TTL (skpc_every_{segundos}) e agenda um
 * evento por conexão (hook skpc_refresh_connection com arg [id]). O filtro de
 * schedules é registrado em TODO request — não só na ativação.
 */
class Cron {

	const HOOK = 'skpc_refresh_connection';

	/**
	 * Engancha os pontos do subsistema de cron.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );
		add_action( self::HOOK, array( $this, 'run_refresh' ), 10, 1 );
		add_action( 'skpc_connection_saved', array( $this, 'on_connection_saved' ), 10, 1 );
		add_action( 'skpc_connection_deleted', array( $this, 'on_connection_deleted' ), 10, 1 );
	}

	/**
	 * Registra um intervalo por TTL distinto encontrado nas conexões.
	 *
	 * @param array $schedules Schedules existentes.
	 * @return array
	 */
	public function register_schedules( $schedules ) {
		foreach ( $this->distinct_ttls() as $secs ) {
			$schedules[ 'skpc_every_' . $secs ] = array(
				'interval' => $secs,
				'display'  => sprintf(
					/* translators: %d: intervalo em segundos. */
					__( 'SK Carrossel: a cada %d segundos', 'sk-price-carousel' ),
					$secs
				),
			);
		}
		return $schedules;
	}

	/**
	 * Callback do evento: refaz o cache da conexão.
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function run_refresh( $connection_id ) {
		Plugin::instance()->cache()->rebuild( $connection_id );
	}

	/**
	 * Ao salvar uma conexão: invalida o cache, reagenda e aquece.
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function on_connection_saved( $connection_id ) {
		Plugin::instance()->cache()->delete( $connection_id );
		$this->schedule_connection( $connection_id );
		// Aquece o cache imediatamente (ação do admin): o wp_schedule_single_event
		// seria descartado como "duplicado" do evento recorrente dentro de 10 min.
		Plugin::instance()->cache()->rebuild( $connection_id );
	}

	/**
	 * Ao excluir uma conexão: limpa cache e desagenda.
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function on_connection_deleted( $connection_id ) {
		Plugin::instance()->cache()->delete( $connection_id );
		wp_clear_scheduled_hook( self::HOOK, array( $connection_id ) );
	}

	/**
	 * (Re)agenda o evento recorrente de uma conexão conforme seu TTL.
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function schedule_connection( $connection_id ) {
		$conn = Plugin::instance()->connections()->get( $connection_id );
		if ( null === $conn ) {
			return;
		}
		$ttl = max( 60, (int) $conn['ttl'] );

		wp_clear_scheduled_hook( self::HOOK, array( $connection_id ) );
		wp_schedule_event( time() + 60, 'skpc_every_' . $ttl, self::HOOK, array( $connection_id ) );
	}

	/**
	 * Reagenda todas as conexões (usado na ativação/reativação).
	 *
	 * @return void
	 */
	public function reschedule_all() {
		foreach ( array_keys( Plugin::instance()->connections()->all() ) as $id ) {
			$this->schedule_connection( $id );
		}
	}

	/**
	 * Limpa todos os eventos de refresh (usado na desativação).
	 *
	 * @return void
	 */
	public function clear_all() {
		foreach ( array_keys( Plugin::instance()->connections()->all() ) as $id ) {
			wp_clear_scheduled_hook( self::HOOK, array( $id ) );
		}
		// Varredura final para eventuais eventos órfãos.
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * TTLs distintos das conexões (>= 60s).
	 *
	 * @return array
	 */
	private function distinct_ttls() {
		$ttls = array();
		foreach ( Plugin::instance()->connections()->all() as $conn ) {
			$secs = max( 60, (int) $conn['ttl'] );
			$ttls[ $secs ] = $secs;
		}
		return array_values( $ttls );
	}
}
