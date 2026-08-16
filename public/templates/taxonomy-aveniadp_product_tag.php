<?php
/**
 * Product tag archive template (classic themes).
 *
 * Block themes use templates/taxonomy-aveniadp_product_tag.html instead.
 * Override: your-theme/avenia-digital-products/taxonomy-aveniadp_product_tag.php
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

<header class="page-header">
	<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
	<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
</header>

<div class="aveniadp-tag-archive">
	<?php
	echo wp_kses_post( aveniadp_render_catalog_grid() );
	?>
</div>

<?php
do_action( 'aveniadp_after_main_content' );

get_footer();
