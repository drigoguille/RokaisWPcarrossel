<?php
/**
 * Camada de cache stale-while-revalidate por conexão.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Cache;

use SKPriceCarousel\Plugin;
use SKPriceCarousel\Data\Data_Source_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * O dado normalizado vive em uma option durável (autoload=no) e a "frescura" é
 * controlada por um transient com o TTL. Assim, render e REST público SEMPRE
 * servem algo instantâneo (mesmo velho), e o refetch acontece em background com
 * single-flight lock.
 */
class Items_Cache {

	const DATA_PREFIX  = 'skpc_data_';
	const FRESH_PREFIX = 'skpc_fresh_';
	const LOCK_PREFIX  = 'skpc_lock_';
	const LOCK_TTL     = 30;

	/**
	 * Hash curto e estável da chave (respeita o limite de 191 chars das options).
	 *
	 * @param string $connection_id Id da conexão.
	 * @return string
	 */
	public static function key( $connection_id ) {
		return substr( md5( $connection_id . '|' . SKPC_SCHEMA_VERSION ), 0, 24 );
	}

	/**
	 * Retorna o wrapper do cache (com flag stale) ou null se nunca houve dados.
	 *
	 * @param string $connection_id Id.
	 * @return array|null
	 */
	public function get( $connection_id ) {
		$h       = self::key( $connection_id );
		$wrapper = get_option( self::DATA_PREFIX . $h );
		if ( ! is_array( $wrapper ) || ! isset( $wrapper['items'] ) ) {
			return null;
		}
		$wrapper['stale'] = ( false === get_transient( self::FRESH_PREFIX . $h ) );
		return $wrapper;
	}

	/**
	 * Retorna os itens (com corte opcional) servindo sempre do cache.
	 *
	 * @param string   $connection_id Id.
	 * @param int|null $limit         Máximo de itens (null = todos).
	 * @param int      $offset        Deslocamento.
	 * @return array
	 */
	public function get_items( $connection_id, $limit = null, $offset = 0 ) {
		$wrapper = $this->get( $connection_id );
		if ( null === $wrapper ) {
			$this->schedule_async( $connection_id );
			return array();
		}
		if ( ! empty( $wrapper['stale'] ) ) {
			$this->schedule_async( $connection_id );
		}
		$items = $wrapper['items'];
		if ( null !== $limit ) {
			$items = array_slice( $items, (int) $offset, (int) $limit );
		}
		return $items;
	}

	/**
	 * Grava os itens no cache durável e renova a frescura.
	 *
	 * @param string $connection_id Id.
	 * @param array  $items         Itens normalizados.
	 * @param int    $ttl           TTL em segundos.
	 * @return array Wrapper gravado.
	 */
	public function set( $connection_id, array $items, $ttl ) {
		$h    = self::key( $connection_id );
		$ttl  = max( 60, (int) $ttl );
		$etag = substr( md5( wp_json_encode( $items ) . '|' . SKPC_SCHEMA_VERSION ), 0, 16 );

		$wrapper = array(
			'items'     => array_values( $items ),
			'generated' => time(),
			'ttl'       => $ttl,
			'etag'      => $etag,
			'status'    => 'ok',
		);

		update_option( self::DATA_PREFIX . $h, $wrapper, false );
		set_transient( self::FRESH_PREFIX . $h, 1, $ttl );

		return $wrapper;
	}

	/**
	 * Remove todos os artefatos de cache de uma conexão.
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function delete( $connection_id ) {
		$h = self::key( $connection_id );
		delete_option( self::DATA_PREFIX . $h );
		delete_transient( self::FRESH_PREFIX . $h );
		$this->release_lock( $h );
	}

	/**
	 * Refaz o cache de uma conexão (fetch + normalize + set) com single-flight.
	 *
	 * Nunca deve ser chamado no request público do REST nem no render frontend.
	 *
	 * @param string $connection_id Id.
	 * @return array|\WP_Error Wrapper atualizado ou erro.
	 */
	public function rebuild( $connection_id ) {
		$h = self::key( $connection_id );

		if ( ! $this->acquire_lock( $h ) ) {
			// Outro processo já está buscando: devolve o que houver.
			$current = $this->get( $connection_id );
			return ( null !== $current ) ? $current : new \WP_Error( 'skpc_locked', __( 'Atualização em andamento.', 'sk-price-carousel' ) );
		}

		$repo = Plugin::instance()->connections();

		try {
			$conn = $repo->resolve( $connection_id );
			if ( null === $conn ) {
				return new \WP_Error( 'skpc_no_conn', __( 'Conexão não encontrada.', 'sk-price-carousel' ) );
			}

			$source = Data_Source_Factory::make( $conn );
			if ( is_wp_error( $source ) ) {
				$repo->update_status( $connection_id, 'error', 0, $source->get_error_message() );
				return $source;
			}

			$valid = $source->validate();
			if ( is_wp_error( $valid ) ) {
				$repo->update_status( $connection_id, 'error', 0, $valid->get_error_message() );
				return $valid;
			}

			$items = $source->fetch();
			if ( is_wp_error( $items ) ) {
				// Mantém o cache existente (stale) e marca frescura curta p/ backoff.
				$repo->update_status( $connection_id, 'error', 0, $items->get_error_message() );
				set_transient( self::FRESH_PREFIX . $h, 1, min( (int) $conn['ttl'], 300 ) );
				return $items;
			}

			$wrapper = $this->set( $connection_id, $items, (int) $conn['ttl'] );
			$repo->update_status( $connection_id, 'ok', count( $items ), '' );
			return $wrapper;
		} finally {
			$this->release_lock( $h );
		}
	}

	/**
	 * Agenda um refresh assíncrono imediato (usado quando o cache está ausente/stale).
	 *
	 * @param string $connection_id Id.
	 * @return void
	 */
	public function schedule_async( $connection_id ) {
		wp_schedule_single_event( time() - 1, 'skpc_refresh_connection', array( $connection_id ) );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Tenta obter o lock de single-flight.
	 *
	 * @param string $h Hash da chave.
	 * @return bool
	 */
	private function acquire_lock( $h ) {
		$key = self::LOCK_PREFIX . $h;
		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_add( $key, 1, 'skpc', self::LOCK_TTL );
		}
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, self::LOCK_TTL );
		return true;
	}

	/**
	 * Libera o lock.
	 *
	 * @param string $h Hash da chave.
	 * @return void
	 */
	private function release_lock( $h ) {
		$key = self::LOCK_PREFIX . $h;
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, 'skpc' );
		} else {
			delete_transient( $key );
		}
	}
}
