<?php
/**
 * Single product template (classic themes).
 *
 * Block themes use templates/single-aveniadp_product.html and inject content via hooks.
 * Override: your-theme/avenia-digital-products/single-aveniadp_product.php
 *
 * Themes can replace the default content wrappers via aveniadp_before_main_content
 * and aveniadp_after_main_content (see aveniadp_output_content_wrapper()).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

do_action( 'aveniadp_before_main_content' );

while ( have_posts() ) :
	the_post();

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress content filter.
	$aveniadp_product_description = apply_filters( 'the_content', get_post()->post_content );
	aveniadp_get_template(
		'single-product-content.php',
		array(
			'aveniadp_product_description' => $aveniadp_product_description,
		)
	);
endwhile;

do_action( 'aveniadp_after_main_content' );

get_footer();
