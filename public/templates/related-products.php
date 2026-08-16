<?php
/**
 * Related products section on the single product page.
 *
 * Override: your-theme/avenia-digital-products/related-products.php
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 *
 * @var int       $product_id
 * @var WP_Query  $query
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $query ) || ! $query instanceof WP_Query || ! $query->have_posts() ) {
	return;
}
?>

<section class="aveniadp-related-products" aria-label="<?php esc_attr_e( 'Related products', 'avenia-digital-products' ); ?>">
	<h2 class="aveniadp-related-products-heading"><?php esc_html_e( 'Related Products', 'avenia-digital-products' ); ?></h2>
	<div class="aveniadp-products-grid aveniadp-products-columns-3">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			aveniadp_get_template( 'content-product.php' );
		endwhile;
		wp_reset_postdata();
		?>
	</div>
</section>
