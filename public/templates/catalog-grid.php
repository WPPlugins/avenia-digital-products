<?php
/**
 * Product catalog grid (archives, shortcode, block theme fallbacks).
 *
 * Loaded via aveniadp_render_catalog_grid() in aveniadp-catalog-functions.php. Do not include
 * directly unless you pass the same args shape (see aveniadp_parse_catalog_grid_args()).
 *
 * Optional variables before include:
 * - int    $per_page             Products per page (default: Settings → Reading).
 * - string $category             Category slug filter.
 * - string $tag                  Tag slug filter.
 * - string $order_by             Query orderby (default: date).
 * - string $order                Query order (default: DESC).
 * - bool   $aveniadp_card_show_image   Show featured image on cards (default: true).
 * - bool   $aveniadp_card_show_price    Show price on cards (default: true).
 * - bool   $aveniadp_card_show_excerpt  Show excerpt on cards (default: false).
 * - int    $columns              Grid columns 1–6 (default: 3).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$per_page = isset( $per_page ) ? aveniadp_get_products_per_page( $per_page ) : aveniadp_get_products_per_page();
$category = isset( $category ) ? sanitize_text_field( $category ) : '';
$tag      = isset( $tag ) ? sanitize_text_field( $tag ) : '';
$order_by = isset( $order_by ) ? sanitize_text_field( $order_by ) : 'date';
$order    = isset( $order ) ? sanitize_text_field( $order ) : 'DESC';

$aveniadp_card_show_image   = isset( $aveniadp_card_show_image ) ? (bool) $aveniadp_card_show_image : true;
$aveniadp_card_show_price   = isset( $aveniadp_card_show_price ) ? (bool) $aveniadp_card_show_price : true;
$aveniadp_card_show_excerpt = isset( $aveniadp_card_show_excerpt ) ? (bool) $aveniadp_card_show_excerpt : false;
$columns              = isset( $columns ) ? absint( $columns ) : 3;
$columns              = max( 1, min( 6, $columns ) );

$grid_classes    = 'aveniadp-products-grid aveniadp-products-columns-' . $columns;
$tax_query       = function_exists( 'aveniadp_get_catalog_tax_query' ) ? aveniadp_get_catalog_tax_query( $category, $tag ) : array();
$current_page    = aveniadp_get_catalog_page_number();
$pinned_id       = function_exists( 'aveniadp_get_pinned_product_id_for_catalog' ) ? aveniadp_get_pinned_product_id_for_catalog( $category, $tag ) : 0;
$show_pinned     = $pinned_id && 1 === $current_page;
$template_args   = array(
	'aveniadp_card_show_image'   => $aveniadp_card_show_image,
	'aveniadp_card_show_price'   => $aveniadp_card_show_price,
	'aveniadp_card_show_excerpt' => $aveniadp_card_show_excerpt,
);

$query_args = array(
	'post_type'      => AVENIADP_PRODUCT_POST_TYPE,
	'posts_per_page' => $per_page,
	'post_status'    => 'publish',
	'orderby'        => $order_by,
	'order'          => $order,
	'paged'          => $current_page,
);

if ( ! empty( $tax_query ) ) {
	$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Catalog archive filtering by product category or tag.
	if ( count( $tax_query ) > 1 ) {
		$query_args['tax_query']['relation'] = 'AND';
	}
}

if ( $pinned_id ) {
	$query_args['post__not_in'] = array( $pinned_id ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Excludes the pinned hero product from the paginated grid only.
}

$products = new WP_Query( $query_args );
?>

<section class="aveniadp-products-catalog">
	<?php
	$has_pinned = $show_pinned && get_post( $pinned_id );
	$has_grid   = $products->have_posts();

	if ( $has_pinned || $has_grid ) :
		if ( $has_pinned ) :
			$pinned_post = get_post( $pinned_id );
			?>
			<div class="aveniadp-product-pinned-wrap">
				<?php
				global $post;
				$post = $pinned_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				aveniadp_get_template( 'content-product-pinned.php', $template_args );
				wp_reset_postdata();
				?>
			</div>
			<?php
		endif;

		if ( $has_grid ) :
			?>
			<div class="<?php echo esc_attr( $grid_classes ); ?>">
				<?php
				while ( $products->have_posts() ) :
					$products->the_post();
					aveniadp_get_template( 'content-product.php', $template_args );
				endwhile;
				?>
			</div>
			<?php
			$pagination = function_exists( 'aveniadp_get_catalog_pagination_html' )
				? aveniadp_get_catalog_pagination_html( $products->max_num_pages )
				: '';
			if ( $pagination ) {
				echo wp_kses_post( $pagination );
			}
			wp_reset_postdata();
		endif;
	else :
		?>
		<p class="aveniadp-notice"><?php esc_html_e( 'No products found.', 'avenia-digital-products' ); ?></p>
		<?php
	endif;
	?>
</section>
