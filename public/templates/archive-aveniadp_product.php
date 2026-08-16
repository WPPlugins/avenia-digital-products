<?php
/**
 * Product archive template (classic themes).
 *
 * Block themes use templates/archive-aveniadp_product.html instead so the theme
 * header and menu are preserved. Override: your-theme/avenia-digital-products/archive-aveniadp_product.php
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
?>

<div class="aveniadp-products-archive">
	<?php
	echo wp_kses_post( aveniadp_render_catalog_grid() );
	?>
</div>

<?php
do_action( 'aveniadp_after_main_content' );

get_footer();
