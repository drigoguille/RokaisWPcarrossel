<?php
/**
 * Atualizador automático via GitHub Releases.
 *
 * Faz o site do cliente consultar a última release do repositório configurado,
 * oferecer a atualização no painel (como um plugin do repositório oficial) e,
 * por padrão, aplicá-la automaticamente em segundo plano.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Integração com a API de releases do GitHub para updates auto-hospedados.
 */
class Updater {

	/**
	 * Caminho completo do arquivo principal do plugin.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Basename do plugin (pasta/arquivo.php).
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Slug (nome da pasta) do plugin.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Versão instalada.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Repositório no formato "owner/repo".
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Token de acesso do GitHub (opcional; para repositório privado / limite de taxa).
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Chave do transient de cache da resposta remota.
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * Se a última busca deve usar o zipball do GitHub (precisa renomear a pasta).
	 *
	 * @var bool
	 */
	private $needs_rename = false;

	/**
	 * Construtor.
	 *
	 * @param string $file    Arquivo principal do plugin.
	 * @param string $version Versão instalada.
	 * @param string $repo    "owner/repo".
	 * @param string $token   Token do GitHub (opcional).
	 */
	public function __construct( $file, $version, $repo, $token = '' ) {
		$this->file      = $file;
		$this->basename  = plugin_basename( $file );
		$this->slug      = dirname( $this->basename );
		$this->version   = $version;
		$this->repo      = trim( (string) $repo );
		$this->token     = (string) $token;
		$this->cache_key = 'skpc_update_' . md5( $this->repo );
	}

	/**
	 * Engancha os filtros do sistema de atualização.
	 *
	 * @return void
	 */
	public function init() {
		if ( '' === $this->repo || false === strpos( $this->repo, '/' ) ) {
			return; // Repositório não configurado.
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );

		if ( defined( 'SKPC_AUTO_UPDATE' ) && false === SKPC_AUTO_UPDATE ) {
			// Desligado explicitamente via constante: força OFF (sem alternador na UI).
			add_filter( 'auto_update_plugin', array( $this, 'force_disable' ), 10, 2 );
		} else {
			// Padrão: liga a atualização automática UMA vez, pela option que o próprio
			// WordPress gerencia — assim o botão "Desativar atualizações automáticas"
			// continua aparecendo na tela de Plugins e o cliente pode desligar.
			add_action( 'admin_init', array( $this, 'seed_auto_update' ) );
		}
	}

	/**
	 * Injeta a atualização no transient do WordPress quando há versão nova.
	 *
	 * @param mixed $transient Transient update_plugins.
	 * @return mixed
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		// Permite forçar a verificação em Painel → Atualizações → Verificar novamente.
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_transient( $this->cache_key );
		}

		$remote = $this->fetch_remote();
		if ( ! $remote ) {
			return $transient;
		}

		$item = (object) array(
			'id'           => $this->basename,
			'slug'         => $this->slug,
			'plugin'       => $this->basename,
			'new_version'  => $remote->version,
			'url'          => $remote->homepage,
			'package'      => $remote->download_url,
			'icons'        => (array) $remote->icons,
			'banners'      => (array) $remote->banners,
			'tested'       => $remote->tested,
			'requires'     => $remote->requires,
			'requires_php' => $remote->requires_php,
		);

		if ( version_compare( $this->version, $remote->version, '<' ) && '' !== $remote->download_url ) {
			$transient->response[ $this->basename ] = $item;
		} else {
			// Reporta "sem atualização" para o WP tratar o item nas rotinas de auto-update.
			$transient->no_update[ $this->basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Preenche a janela "Ver detalhes" do plugin.
	 *
	 * @param mixed  $result Resultado atual.
	 * @param string $action Ação da API.
	 * @param object $args   Argumentos.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$remote = $this->fetch_remote();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote->name,
			'slug'          => $this->slug,
			'version'       => $remote->version,
			'author'        => $remote->author,
			'homepage'      => $remote->homepage,
			'requires'      => $remote->requires,
			'tested'        => $remote->tested,
			'requires_php'  => $remote->requires_php,
			'last_updated'  => $remote->last_updated,
			'sections'      => array(
				'description' => $remote->description,
				'changelog'   => $remote->changelog,
			),
			'banners'       => (array) $remote->banners,
			'icons'         => (array) $remote->icons,
			'download_link' => $remote->download_url,
		);
	}

	/**
	 * Força DESLIGAR a atualização automática (usado quando SKPC_AUTO_UPDATE=false).
	 *
	 * @param bool|null $update Decisão atual.
	 * @param object    $item   Item do plugin.
	 * @return bool|null
	 */
	public function force_disable( $update, $item ) {
		if ( is_object( $item ) && isset( $item->plugin ) && $item->plugin === $this->basename ) {
			return false;
		}
		return $update;
	}

	/**
	 * Liga a atualização automática por padrão (uma única vez por site),
	 * registrando o plugin na option 'auto_update_plugins' gerida pela UI.
	 * O cliente pode desligar depois pelo botão da tela de Plugins.
	 *
	 * @return void
	 */
	public function seed_auto_update() {
		if ( get_option( 'skpc_autoupdate_seeded' ) ) {
			return;
		}
		$enabled = get_option( 'auto_update_plugins', array() );
		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}
		if ( ! in_array( $this->basename, $enabled, true ) ) {
			$enabled[] = $this->basename;
			update_option( 'auto_update_plugins', $enabled );
		}
		update_option( 'skpc_autoupdate_seeded', 1 );
	}

	/**
	 * Renomeia a pasta extraída para o slug do plugin (necessário com zipball).
	 *
	 * @param string $source        Pasta extraída.
	 * @param string $remote_source Pasta base da extração.
	 * @param object $upgrader      Instância do upgrader.
	 * @param array  $extra         Dados extras (contém 'plugin').
	 * @return string|\WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $extra = array() ) {
		if ( ! is_array( $extra ) || ! isset( $extra['plugin'] ) || $extra['plugin'] !== $this->basename ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug;
		$current = untrailingslashit( $source );

		if ( $current === untrailingslashit( $desired ) ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return trailingslashit( $desired );
		}
		return $source;
	}

	/**
	 * Limpa o cache remoto após uma atualização concluída.
	 *
	 * @param object $upgrader Upgrader.
	 * @param array  $options  Opções do processo.
	 * @return void
	 */
	public function purge_cache( $upgrader, $options ) {
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key );
		}
	}

	/**
	 * Busca (com cache) a última release do GitHub e a normaliza.
	 *
	 * @return object|false
	 */
	private function fetch_remote() {
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return is_object( $cached ) ? $cached : false;
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo );
		$args = array(
			'timeout'    => 10,
			'user-agent' => 'RokaisCarrosselWP/' . $this->version,
			'headers'    => array(
				'Accept' => 'application/vnd.github+json',
			),
		);
		if ( '' !== $this->token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}
		/**
		 * Permite ajustar os argumentos da requisição de update (ex.: headers).
		 *
		 * @param array   $args    Args do wp_remote_get.
		 * @param Updater $updater Instância.
		 */
		$args = apply_filters( 'skpc_update_request_args', $args, $this );

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache negativo curto para não martelar a API do GitHub.
			set_transient( $this->cache_key, 'invalid', 15 * MINUTE_IN_SECONDS );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $data ) || empty( $data->tag_name ) ) {
			set_transient( $this->cache_key, 'invalid', 15 * MINUTE_IN_SECONDS );
			return false;
		}

		$remote = $this->normalize_release( $data );
		set_transient( $this->cache_key, $remote, HOUR_IN_SECONDS );
		return $remote;
	}

	/**
	 * Converte a resposta da API do GitHub no formato usado internamente.
	 *
	 * @param object $data Release do GitHub.
	 * @return object
	 */
	private function normalize_release( $data ) {
		$version = ltrim( (string) $data->tag_name, 'vV' );

		// Preferir um asset .zip anexado (nome de pasta já correto); senão, zipball.
		$download = '';
		if ( ! empty( $data->assets ) && is_array( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				if ( isset( $asset->name, $asset->browser_download_url ) && preg_match( '/\.zip$/i', $asset->name ) ) {
					$download = $asset->browser_download_url;
					break;
				}
			}
		}
		if ( '' === $download && ! empty( $data->zipball_url ) ) {
			$download = $data->zipball_url;
		}

		$body      = isset( $data->body ) ? (string) $data->body : '';
		$changelog = '' !== $body ? wpautop( make_clickable( esc_html( $body ) ) ) : esc_html__( 'Sem notas de versão.', 'sk-price-carousel' );

		return (object) array(
			'version'      => $version,
			'download_url' => esc_url_raw( $download ),
			'name'         => 'Rokais Carrossel WP',
			'author'       => 'Rokais',
			'homepage'     => isset( $data->html_url ) ? esc_url_raw( $data->html_url ) : ( 'https://github.com/' . $this->repo ),
			'requires'     => '5.9',
			'tested'       => '6.6',
			'requires_php' => '7.4',
			'last_updated' => isset( $data->published_at ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data->published_at ) ) : '',
			'description'  => esc_html__( 'Carrossel inteligente para o Elementor com fontes Google Sheets, JSON e MySQL.', 'sk-price-carousel' ),
			'changelog'    => $changelog,
			'icons'        => array(
				'1x' => SKPC_ASSETS_URL . 'img/icon-128.png',
				'2x' => SKPC_ASSETS_URL . 'img/icon-256.png',
			),
			'banners'      => array(),
		);
	}
}
