<?php
/**
 * Plugin Name:       Rokais Carrossel WP
 * Plugin URI:        https://skgrupo.com/
 * Description:       Carrossel inteligente para o Elementor com imagem, descrição, preço cheio e preço promocional. Fontes de dados: Google Sheets, JSON e MySQL, com cache automático e atualização ao vivo.
 * Version:           1.0.4
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Rokais
 * Author URI:        https://skgrupo.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sk-price-carousel
 * Domain Path:       /languages
 * Elementor tested up to: 3.23.0
 *
 * @package SKPriceCarousel
 */

defined( 'ABSPATH' ) || exit;

/*
 * -----------------------------------------------------------------------------
 * Constantes globais do plugin.
 * -----------------------------------------------------------------------------
 */
define( 'SKPC_VERSION', '1.0.4' );
define( 'SKPC_FILE', __FILE__ );
define( 'SKPC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SKPC_URL', plugin_dir_url( __FILE__ ) );
define( 'SKPC_BASENAME', plugin_basename( __FILE__ ) );
define( 'SKPC_ASSETS_URL', SKPC_URL . 'assets/' );
define( 'SKPC_MIN_PHP_VERSION', '7.4' );
define( 'SKPC_MIN_ELEMENTOR_VERSION', '3.5.0' );

// Versão do schema normalizado. Incrementar sempre que o formato do item mudar
// para invalidar automaticamente o cache durável em atualizações do plugin.
define( 'SKPC_SCHEMA_VERSION', 1 );

// Namespace da REST API do plugin.
define( 'SKPC_REST_NS', 'skpc/v1' );

/*
 * -----------------------------------------------------------------------------
 * Atualização automática via GitHub Releases.
 * -----------------------------------------------------------------------------
 * ALTERE 'SKPC_GITHUB_REPO' para o seu repositório no formato "usuario/repositorio".
 * Para repositório privado ou para elevar o limite da API, defina no wp-config.php:
 *   define( 'SKPC_GITHUB_TOKEN', 'ghp_seu_token' );
 * A atualização automática vem LIGADA por padrão e pode ser desligada em cada
 * site pelo botão "Desativar atualizações automáticas" na tela de Plugins, ou
 * de forma forçada (sem alternador na UI) definindo no wp-config.php:
 *   define( 'SKPC_AUTO_UPDATE', false );
 */
if ( ! defined( 'SKPC_GITHUB_REPO' ) ) {
	define( 'SKPC_GITHUB_REPO', 'drigoguille/RokaisWPcarrossel' );
}
if ( ! defined( 'SKPC_AUTO_UPDATE' ) ) {
	define( 'SKPC_AUTO_UPDATE', true );
}

/*
 * -----------------------------------------------------------------------------
 * Autoloader próprio (sem depender de Composer em runtime).
 * -----------------------------------------------------------------------------
 */
require_once SKPC_PATH . 'includes/class-autoloader.php';
SKPriceCarousel\Autoloader::register();

/*
 * -----------------------------------------------------------------------------
 * Hooks de ciclo de vida.
 * -----------------------------------------------------------------------------
 */
register_activation_hook( SKPC_FILE, array( 'SKPriceCarousel\\Activator', 'activate' ) );
register_deactivation_hook( SKPC_FILE, array( 'SKPriceCarousel\\Deactivator', 'deactivate' ) );

/*
 * -----------------------------------------------------------------------------
 * Bootstrap.
 * -----------------------------------------------------------------------------
 */
SKPriceCarousel\Plugin::instance();
