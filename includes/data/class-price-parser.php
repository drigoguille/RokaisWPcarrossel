<?php
/**
 * Conversão determinística de strings de preço (inclusive pt-BR) para float.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Interpreta valores de preço em formatos variados: "R$ 1.234,56", "1234.56",
 * "1.234,56", "R$ 99", etc., distinguindo separador de milhar de decimal.
 */
class Price_Parser {

	/**
	 * Converte um valor arbitrário em float (ou null quando não há preço).
	 *
	 * @param mixed $value Valor cru vindo da fonte.
	 * @return float|null
	 */
	public static function parse( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		$s = trim( (string) $value );
		if ( '' === $s ) {
			return null;
		}

		// Mantém apenas dígitos, vírgula, ponto e sinal negativo.
		$s = preg_replace( '/[^0-9,.\-]/', '', $s );
		if ( '' === $s || '-' === $s ) {
			return null;
		}

		$last_comma = strrpos( $s, ',' );
		$last_dot   = strrpos( $s, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
			if ( $last_comma > $last_dot ) {
				// Vírgula é o separador decimal (pt-BR): remove pontos de milhar.
				$s = str_replace( '.', '', $s );
				$s = str_replace( ',', '.', $s );
			} else {
				// Ponto é o separador decimal (en): remove vírgulas de milhar.
				$s = str_replace( ',', '', $s );
			}
		} elseif ( false !== $last_comma ) {
			// Só vírgula: decimal se houver uma única vírgula com até 2 casas.
			$parts = explode( ',', $s );
			$frac  = end( $parts );
			if ( 2 === count( $parts ) && strlen( $frac ) <= 2 ) {
				$s = str_replace( ',', '.', $s );
			} else {
				$s = str_replace( ',', '', $s );
			}
		} else {
			// Só ponto(s): múltiplos pontos são milhar; um ponto seguido de
			// exatamente 3 dígitos (ex.: "1.500") também é milhar em pt-BR.
			if ( substr_count( $s, '.' ) > 1 || ( preg_match( '/\.\d{3}$/', $s ) && ! preg_match( '/\.\d{1,2}$/', $s ) ) ) {
				$s = str_replace( '.', '', $s );
			}
		}

		if ( ! is_numeric( $s ) ) {
			return null;
		}

		return (float) $s;
	}

	/**
	 * Formata um float para exibição em pt-BR (ex.: "R$ 1.234,56").
	 *
	 * Usa NBSP (U+00A0) entre o símbolo e o número para casar com o
	 * Intl.NumberFormat('pt-BR', {currency:'BRL'}) usado no JavaScript.
	 *
	 * @param float|null $value Valor numérico.
	 * @return string
	 */
	public static function format( $value ) {
		if ( null === $value ) {
			return '';
		}
		return 'R$' . "\xC2\xA0" . number_format_i18n( (float) $value, 2 );
	}
}
