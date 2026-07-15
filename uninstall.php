<?php
/**
 * Rotina de desinstalação.
 *
 * Executada pelo core do WordPress ao remover o plugin. Roda SEM o plugin
 * carregado (constantes/classes indisponíveis), portanto é autossuficiente.
 * Respeita a preferência preserve_data_on_uninstall e trata multisite.
 *
 * @package SKPriceCarousel
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Limpa todos os dados do plugin em um único site.
 *
 * @return void
 */
function skpc_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'skpc_settings', array() );
	$preserve = is_array( $settings ) && ! empty( $settings['preserve_data_on_uninstall'] );

	if ( $preserve ) {
		return;
	}

	// Options de configuração/estado.
	delete_option( 'skpc_settings' );
	delete_option( 'skpc_connections' );
	delete_option( 'skpc_credentials' );
	delete_option( 'skpc_version' );

	// Options de dado durável do cache (skpc_data_*).
	$data_options = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'skpc_data\\_%'"
	);
	foreach ( (array) $data_options as $option_name ) {
		delete_option( $option_name );
	}

	// Transients de frescura/lock (skpc_fresh_*, skpc_lock_*) e quaisquer skpc_*.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_skpc\\_%'
		    OR option_name LIKE '\\_transient\\_timeout\\_skpc\\_%'"
	);

	// Eventos de cron por conexão.
	skpc_uninstall_clear_cron();
}

/**
 * Remove todos os eventos de cron do plugin (com qualquer argumento).
 *
 * @return void
 */
function skpc_uninstall_clear_cron() {
	$crons = _get_cron_array();
	if ( empty( $crons ) ) {
		return;
	}
	foreach ( $crons as $timestamp => $hooks ) {
		if ( isset( $hooks['skpc_refresh_connection'] ) ) {
			foreach ( $hooks['skpc_refresh_connection'] as $event ) {
				wp_unschedule_event( $timestamp, 'skpc_refresh_connection', $event['args'] );
			}
		}
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		skpc_uninstall_site();
		restore_current_blog();
	}
} else {
	skpc_uninstall_site();
}
