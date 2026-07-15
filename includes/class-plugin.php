<?php
/**
 * Classe bootstrap (singleton) do plugin.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

use SKPriceCarousel\Data\Connection_Repository;
use SKPriceCarousel\Cache\Items_Cache;
use SKPriceCarousel\Cache\Cron;
use SKPriceCarousel\Rest\Items_Rest_Controller;
use SKPriceCarousel\Admin\Admin_Menu;
use SKPriceCarousel\Admin\Connections_Controller;
use SKPriceCarousel\Admin\Admin_Ajax;
use SKPriceCarousel\Elementor\Elementor_Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Orquestra a inicialização de todos os subsistemas e expõe um registry leve
 * de serviços compartilhados por toda a execução (uma única instância cada).
 */
final class Plugin {

	/**
	 * Instância única.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Repositório de conexões (lazy).
	 *
	 * @var Connection_Repository|null
	 */
	private $connections = null;

	/**
	 * Camada de cache (lazy).
	 *
	 * @var Items_Cache|null
	 */
	private $cache = null;

	/**
	 * Gerenciador de cron (lazy).
	 *
	 * @var Cron|null
	 */
	private $cron = null;

	/**
	 * Objeto de assets (lazy).
	 *
	 * @var Assets|null
	 */
	private $assets = null;

	/**
	 * Retorna (criando se necessário) a instância única.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado: engancha a inicialização em plugins_loaded.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Inicializa os subsistemas assim que todos os plugins carregaram.
	 *
	 * @return void
	 */
	public function on_plugins_loaded() {
		// i18n (carrega o textdomain no hook init).
		( new I18n() )->init();

		// Cron + agenda de refresh de cache (roda em background, independe do Elementor).
		$this->cron()->init();

		// Endpoint REST que serve o cache normalizado.
		( new Items_Rest_Controller() )->init();

		// Registro/enfileiramento de assets do frontend e do admin.
		$this->assets()->init();

		// Telas administrativas.
		if ( is_admin() ) {
			( new Admin_Menu() )->init();
			( new Connections_Controller() )->init();
			( new Admin_Ajax() )->init();
		}

		// Atualização automática via GitHub Releases.
		$token = defined( 'SKPC_GITHUB_TOKEN' ) ? SKPC_GITHUB_TOKEN : '';
		( new Updater( SKPC_FILE, SKPC_VERSION, SKPC_GITHUB_REPO, $token ) )->init();

		// Integração com o Elementor (os hooks só disparam se o Elementor estiver ativo).
		( new Elementor_Integration() )->init();

		// Aviso administrativo caso o Elementor esteja ausente/desatualizado.
		add_action( 'admin_notices', array( Requirements::class, 'maybe_render_elementor_notice' ) );
	}

	/**
	 * Repositório de conexões globais.
	 *
	 * @return Connection_Repository
	 */
	public function connections() {
		if ( null === $this->connections ) {
			$this->connections = new Connection_Repository();
		}
		return $this->connections;
	}

	/**
	 * Camada de cache stale-while-revalidate.
	 *
	 * @return Items_Cache
	 */
	public function cache() {
		if ( null === $this->cache ) {
			$this->cache = new Items_Cache();
		}
		return $this->cache;
	}

	/**
	 * Gerenciador de WP-Cron.
	 *
	 * @return Cron
	 */
	public function cron() {
		if ( null === $this->cron ) {
			$this->cron = new Cron();
		}
		return $this->cron;
	}

	/**
	 * Objeto de assets.
	 *
	 * @return Assets
	 */
	public function assets() {
		if ( null === $this->assets ) {
			$this->assets = new Assets();
		}
		return $this->assets;
	}

	/**
	 * Configurações globais do plugin com defaults garantidos.
	 *
	 * @return array
	 */
	public function settings() {
		$defaults = self::default_settings();
		$saved    = get_option( 'skpc_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Valores padrão das configurações globais.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'default_ttl'                 => 900, // 15 minutos.
			'default_live_interval'       => 60,  // segundos.
			'preserve_data_on_uninstall'  => false,
		);
	}
}
