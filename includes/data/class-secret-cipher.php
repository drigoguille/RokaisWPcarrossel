<?php
/**
 * Cifragem/decifragem de segredos (senha MySQL, api_key, token) em repouso.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-CBC com IV aleatório. A chave vem da constante SKPC_ENCRYPTION_KEY
 * (recomendada, definível em wp-config.php) ou, como fallback, de wp_salt('auth').
 */
class Secret_Cipher {

	const METHOD = 'aes-256-cbc';

	/**
	 * A extensão OpenSSL está disponível?
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Deriva a chave binária de 32 bytes.
	 *
	 * @return string
	 */
	private static function key() {
		if ( defined( 'SKPC_ENCRYPTION_KEY' ) && SKPC_ENCRYPTION_KEY ) {
			return hash( 'sha256', (string) SKPC_ENCRYPTION_KEY, true );
		}
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Cifra um valor em texto puro.
	 *
	 * @param string $plain Texto puro.
	 * @return string Base64 de (IV . ciphertext), ou '' se vazio/indisponível.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain || ! self::available() ) {
			return '';
		}
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decifra um valor cifrado por encrypt().
	 *
	 * @param string $stored Valor armazenado.
	 * @return string Texto puro, ou '' em caso de falha (ex.: salts rotacionadas).
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored || ! self::available() ) {
			return '';
		}
		$raw = base64_decode( $stored, true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv );
		return ( false === $plain ) ? '' : $plain;
	}
}
