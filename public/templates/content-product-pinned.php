<?php
/**
 * Pinned product card template (full-width catalog hero).
 *
 * Override: your-theme/avenia-digital-products/content-product-pinned.php
 *
 * Optional variables before include:
 * - bool $aveniadp_card_show_image  Show featured image (default true).
 * - bool $aveniadp_card_show_price   Show listing price (default true).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$aveniadp_card_show_image = isset( $aveniadp_card_show_image ) ? (bool) $aveniadp_card_show_image : true;
$aveniadp_card_show_price = isset( $aveniadp_card_show_price ) ? (bool) $aveniadp_card_show_price : true;

$product_id = get_the_ID();
$price_html = $aveniadp_card_show_price && function_exists( 'aveniadp_get_product_listing_price_html' )
	? aveniadp_get_product_listing_price_html( $product_id )
	: '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'aveniadp-product-card aveniadp-product-pinned' ); ?>>
	<?php if ( $aveniadp_card_show_image && has_post_thumbnail() ) : ?>
		<div class="aveniadp-product-image-wrap">
			<a class="aveniadp-product-image" href="<?php the_permalink(); ?>">
				<?php the_post_thumbnail( 'aveniadp-product-pinned' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<div class="aveniadp-product-card-details">
		<header class="entry-header">
			<?php
			the_title(
				'<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">',
				'</a></h2>'
			);
			?>
		</header>

		<?php if ( $price_html ) : ?>
			<div class="aveniadp-product-price">
				<?php echo wp_kses_post( $price_html ); ?>
			</div>
		<?php endif; ?>
	</div>
</article>
