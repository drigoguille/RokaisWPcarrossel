<?php
/**
 * Markup de um slide (SSR). Usa as MESMAS classes que o JS de refresh ao vivo,
 * garantindo estilo idêntico após re-render.
 *
 * @var array $item Item normalizado no escopo do include.
 * @package SKPriceCarousel
 */

use SKPriceCarousel\Data\Price_Parser;

defined( 'ABSPATH' ) || exit;

$skpc_has_promo = ( null !== $item['sale_price'] );
$skpc_has_link  = ( '' !== $item['url'] );
$skpc_tag       = $skpc_has_link ? 'a' : 'div';
?>
<div class="swiper-slide skpc-slide">
	<article class="skpc-card">
		<<?php echo esc_html( $skpc_tag ); ?> class="skpc-card__link"<?php echo $skpc_has_link ? ' href="' . esc_url( $item['url'] ) . '"' : ''; ?>>
			<figure class="skpc-card__media">
				<?php if ( '' !== $item['image'] ) : ?>
					<img class="skpc-card__img" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy" decoding="async">
				<?php endif; ?>
				<?php if ( '' !== $item['badge'] ) : ?>
					<span class="skpc-badge"><?php echo esc_html( $item['badge'] ); ?></span>
				<?php endif; ?>
			</figure>
			<div class="skpc-card__body">
				<?php if ( '' !== $item['title'] ) : ?>
					<h3 class="skpc-card__title"><?php echo esc_html( $item['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( '' !== $item['description'] ) : ?>
					<div class="skpc-card__desc"><?php echo wp_kses_post( $item['description'] ); ?></div>
				<?php endif; ?>
				<div class="skpc-card__prices">
					<?php if ( $skpc_has_promo ) : ?>
						<del class="skpc-price skpc-price--full"><?php echo esc_html( Price_Parser::format( $item['price'] ) ); ?></del>
						<ins class="skpc-price skpc-price--sale"><?php echo esc_html( Price_Parser::format( $item['sale_price'] ) ); ?></ins>
					<?php elseif ( null !== $item['price'] ) : ?>
						<span class="skpc-price"><?php echo esc_html( Price_Parser::format( $item['price'] ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</<?php echo esc_html( $skpc_tag ); ?>>
	</article>
</div>
