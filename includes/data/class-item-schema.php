<?php
/**
 * Normalizador central para o schema comum de item.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Converte linhas cruas (de qualquer fonte) e itens manuais do repeater no
 * schema comum { id, image, title, description, price, sale_price, url, badge },
 * aplicando sanitização por campo e a regra única de promoção.
 */
class Item_Schema {

	/**
	 * Campos mapeáveis (na ordem exibida no admin).
	 *
	 * @return array
	 */
	public static function mappable_fields() {
		return array( 'image', 'title', 'description', 'price', 'sale_price', 'url', 'badge' );
	}

	/**
	 * Rótulos legíveis dos campos (para a UI de mapeamento).
	 *
	 * @return array
	 */
	public static function field_labels() {
		return array(
			'image'       => __( 'Imagem', 'sk-price-carousel' ),
			'title'       => __( 'Descrição/Título', 'sk-price-carousel' ),
			'description' => __( 'Descrição longa', 'sk-price-carousel' ),
			'price'       => __( 'Preço cheio', 'sk-price-carousel' ),
			'sale_price'  => __( 'Preço promocional', 'sk-price-carousel' ),
			'url'         => __( 'Link (URL)', 'sk-price-carousel' ),
			'badge'       => __( 'Selo/Badge', 'sk-price-carousel' ),
		);
	}

	/**
	 * Normaliza uma linha crua associativa usando o mapa campo => coluna/caminho.
	 *
	 * @param array $raw     Linha bruta (chaves = colunas/caminhos).
	 * @param array $mapping Mapa campo => coluna.
	 * @return array Item normalizado.
	 */
	public static function normalize_row( array $raw, array $mapping ) {
		$pick = static function ( $field ) use ( $raw, $mapping ) {
			if ( empty( $mapping[ $field ] ) ) {
				return '';
			}
			$col = $mapping[ $field ];
			return array_key_exists( $col, $raw ) ? $raw[ $col ] : '';
		};

		return self::build(
			array(
				'image'       => $pick( 'image' ),
				'title'       => $pick( 'title' ),
				'description' => $pick( 'description' ),
				'price'       => $pick( 'price' ),
				'sale_price'  => $pick( 'sale_price' ),
				'url'         => $pick( 'url' ),
				'badge'       => $pick( 'badge' ),
			)
		);
	}

	/**
	 * Normaliza um item do repeater manual do widget Elementor.
	 *
	 * @param array $row Linha do repeater.
	 * @return array Item normalizado.
	 */
	public static function normalize_manual( array $row ) {
		$image = '';
		if ( ! empty( $row['item_image']['url'] ) ) {
			$image = $row['item_image']['url'];
		}

		$url = '';
		if ( ! empty( $row['item_url']['url'] ) ) {
			$url = $row['item_url']['url'];
		} elseif ( ! empty( $row['item_url'] ) && is_string( $row['item_url'] ) ) {
			$url = $row['item_url'];
		}

		return self::build(
			array(
				'image'       => $image,
				'title'       => isset( $row['item_title'] ) ? $row['item_title'] : '',
				'description' => isset( $row['item_description'] ) ? $row['item_description'] : '',
				'price'       => isset( $row['item_price'] ) ? $row['item_price'] : '',
				'sale_price'  => isset( $row['item_sale_price'] ) ? $row['item_sale_price'] : '',
				'url'         => $url,
				'badge'       => isset( $row['item_badge'] ) ? $row['item_badge'] : '',
			)
		);
	}

	/**
	 * Constrói e sanitiza o item normalizado a partir de valores crus.
	 *
	 * A descrição passa por wp_kses_post (HTML limitado seguro) — o mesmo valor
	 * é servido pelo REST e usado tal e qual no SSR e no JS (paridade garantida).
	 *
	 * @param array $data Valores crus por campo.
	 * @return array Item normalizado.
	 */
	public static function build( array $data ) {
		$image       = esc_url_raw( trim( (string) self::val( $data, 'image' ) ) );
		$title       = sanitize_text_field( (string) self::val( $data, 'title' ) );
		$description = wp_kses_post( (string) self::val( $data, 'description' ) );
		$url         = esc_url_raw( trim( (string) self::val( $data, 'url' ) ) );
		$price       = Price_Parser::parse( self::val( $data, 'price' ) );
		$sale        = Price_Parser::parse( self::val( $data, 'sale_price' ) );

		// Regra única de promoção: só é promo quando 0 < sale < price.
		$has_promo = ( null !== $price && null !== $sale && $sale > 0 && $sale < $price );
		if ( ! $has_promo ) {
			$sale = null;
		}

		$badge = sanitize_text_field( (string) self::val( $data, 'badge' ) );
		if ( '' === $badge && $has_promo ) {
			$percent = (int) round( ( 1 - ( $sale / $price ) ) * 100 );
			if ( $percent > 0 ) {
				/* translators: %d: percentual de desconto. */
				$badge = sprintf( __( '-%d%%', 'sk-price-carousel' ), $percent );
			}
		}

		$id = substr( md5( $image . '|' . $title . '|' . $url ), 0, 12 );

		return array(
			'id'          => $id,
			'image'       => $image,
			'title'       => $title,
			'description' => $description,
			'price'       => $price,
			'sale_price'  => $sale,
			'url'         => $url,
			'badge'       => $badge,
		);
	}

	/**
	 * Acesso seguro a uma chave de array.
	 *
	 * @param array  $data Array.
	 * @param string $key  Chave.
	 * @return mixed
	 */
	private static function val( $data, $key ) {
		return isset( $data[ $key ] ) ? $data[ $key ] : '';
	}
}
