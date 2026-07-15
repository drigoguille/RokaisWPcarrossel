<?php
/**
 * Autoloader PSR-4-like que segue a convenção de nomes do WordPress.
 *
 * Mapeia o namespace raiz "SKPriceCarousel\" para a pasta includes/, usando
 * sub-namespaces como sub-pastas e nomes de arquivo class-*.php / interface-*.php.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Registra e resolve o carregamento das classes do plugin.
 */
class Autoloader {

	/**
	 * Namespace raiz do plugin.
	 *
	 * @var string
	 */
	const ROOT_NAMESPACE = 'SKPriceCarousel\\';

	/**
	 * Registra o autoloader na pilha do SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolve o nome totalmente qualificado da classe para um arquivo e o carrega.
	 *
	 * @param string $class Nome totalmente qualificado da classe/interface.
	 * @return void
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::ROOT_NAMESPACE ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::ROOT_NAMESPACE ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );

		// Sub-namespaces viram sub-pastas em minúsculas (ex.: Data\Sources -> data/sources).
		$sub_path = '';
		if ( ! empty( $parts ) ) {
			$sub_path = strtolower( implode( '/', $parts ) ) . '/';
		}

		// Interfaces usam o prefixo interface-*, classes usam class-*.
		if ( '_Interface' === substr( $name, -10 ) ) {
			$base     = substr( $name, 0, -10 );
			$filename = 'interface-' . self::to_kebab( $base ) . '.php';
		} else {
			$filename = 'class-' . self::to_kebab( $name ) . '.php';
		}

		$file = SKPC_PATH . 'includes/' . $sub_path . $filename;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Converte um nome de classe (Foo_Bar) em kebab-case (foo-bar).
	 *
	 * @param string $name Nome da classe.
	 * @return string
	 */
	private static function to_kebab( $name ) {
		return str_replace( '_', '-', strtolower( $name ) );
	}
}
