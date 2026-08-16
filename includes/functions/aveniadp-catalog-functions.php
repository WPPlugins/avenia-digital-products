<?php
/**
 * Catalog grid, pagination, and pinned product helpers.
 *
 * ## Catalog grid render paths
 *
 * All paths output the same markup via `aveniadp_render_catalog_grid()` → `catalog-grid.php`.
 *
 * 1. **Products block** — `aveniadp_render_products_block()` (block.json render_callback).
 * 2. **`[aveniadp_products]` shortcode** — `AVENIADP_Public::products_shortcode()`.
 * 3. **Block theme query replacement** — `AVENIADP_Public::replace_catalog_query_block()` swaps the
 *    first `core/query` on product archive/taxonomy views when the theme has no custom template.
 * 4. **Classic PHP archive templates** — `archive-aveniadp_product.php`, taxonomy templates, or theme
 *    overrides under `avenia-digital-products/`.
 *
 * Pass display options through `aveniadp_get_catalog_grid_args_from_shortcode_atts()` (shortcode attrs)
 * or `aveniadp_get_catalog_grid_args_from_block_attributes()` (block attrs). Archive and query-replacement
 * paths use `aveniadp_get_default_catalog_grid_args()` (Reading settings per-page, 3 columns, card defaults).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function aveniadp_get_catalog_page_number() {
	$paged = get_query_var( 'paged' );
	if ( $paged ) {
		return max( 1, absint( $paged ) );
	}

	$page = get_query_var( 'page' );
	if ( $page ) {
		return max( 1, absint( $page ) );
	}

	return 1;
}

/**
 * Products per page for catalog views.
 *
 * Uses the Reading settings value by default; pass a positive override to fix the count.
 *
 * @param int $override Explicit per-page count from block or shortcode attributes.
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_products_per_page( $override = 0 ) {
	$override = absint( $override );

	if ( $override > 0 ) {
		return $override;
	}

	return max( 1, absint( get_option( 'posts_per_page', 12 ) ) );
}

/**
 * Render pagination links for a product catalog query.
 *
 * @param int      $max_num_pages Total pages.
 * @param int|null $current       Current page (default: detected from query vars).
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_catalog_pagination_html( $max_num_pages, $current = null ) {
	$max_num_pages = absint( $max_num_pages );
	if ( $max_num_pages < 2 ) {
		return '';
	}

	$current = null === $current ? aveniadp_get_catalog_page_number() : max( 1, absint( $current ) );

	$pagination = paginate_links(
		array(
			'total'     => $max_num_pages,
			'current'   => $current,
			'type'      => 'list',
			'prev_text' => '&larr; ' . __( 'Previous', 'avenia-digital-products' ),
			'next_text' => __( 'Next', 'avenia-digital-products' ) . ' &rarr;',
		)
	);

	if ( ! $pagination ) {
		return '';
	}

	return sprintf(
		'<nav class="aveniadp-catalog-pagination navigation pagination" aria-label="%1$s">%2$s</nav>',
		esc_attr__( 'Products', 'avenia-digital-products' ),
		$pagination
	);
}

/**
 * Whether a product is pinned to the top of the catalog.
 *
 * @param int $product_id Product post ID.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_product_pinned( $product_id ) {
	$value = get_post_meta( absint( $product_id ), '_aveniadp_pinned_product', true );

	if ( is_bool( $value ) ) {
		return $value;
	}

	return in_array( (string) $value, array( '1', 'true', 'yes' ), true );
}

/**
 * Build taxonomy filters for catalog queries.
 *
 * @param string $category Category slug.
 * @param string $tag      Tag slug.
 * @return array<int, array<string, mixed>>
 * @since 1.0.0
 */
function aveniadp_get_catalog_tax_query( $category = '', $tag = '' ) {
	$tax_query = array();
	$category  = sanitize_text_field( $category );
	$tag       = sanitize_text_field( $tag );

	if ( $category ) {
		$tax_query[] = array(
			'taxonomy' => 'aveniadp_product_category',
			'field'    => 'slug',
			'terms'    => $category,
		);
	}

	if ( $tag ) {
		$tax_query[] = array(
			'taxonomy' => 'aveniadp_product_tag',
			'field'    => 'slug',
			'terms'    => $tag,
		);
	}

	if ( empty( $tax_query ) && is_tax() ) {
		$queried_term = get_queried_object();
		if ( $queried_term instanceof WP_Term ) {
			if ( 'aveniadp_product_category' === $queried_term->taxonomy ) {
				$tax_query[] = array(
					'taxonomy' => 'aveniadp_product_category',
					'field'    => 'slug',
					'terms'    => $queried_term->slug,
				);
			} elseif ( 'aveniadp_product_tag' === $queried_term->taxonomy ) {
				$tax_query[] = array(
					'taxonomy' => 'aveniadp_product_tag',
					'field'    => 'slug',
					'terms'    => $queried_term->slug,
				);
			}
		}
	}

	return $tax_query;
}

/**
 * Get the pinned product ID for the current catalog context.
 *
 * @param string $category Category slug.
 * @param string $tag      Tag slug.
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_pinned_product_id_for_catalog( $category = '', $tag = '' ) {
	$tax_query = aveniadp_get_catalog_tax_query( $category, $tag );
	$args      = array(
		'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Pinned product ID must not be altered by third-party query filters.
		'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Pinned product lookup uses a single indexed meta flag.
			array(
				'key'     => '_aveniadp_pinned_product',
				'value'   => array( '1', 'yes', 'true' ),
				'compare' => 'IN',
			),
		),
	);

	if ( ! empty( $tax_query ) ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Catalog pinned product respects active category or tag archive.
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}
	}

	$pinned_ids = get_posts( $args );

	return ! empty( $pinned_ids ) ? absint( $pinned_ids[0] ) : 0;
}

/**
 * Set whether a product is pinned to the catalog.
 *
 * Only one product can be pinned at a time.
 *
 * @param int  $product_id Product post ID.
 * @param bool $pinned     Whether the product should be pinned.
 * @since 1.0.0
 */
function aveniadp_set_product_pinned_status( $product_id, $pinned ) {
	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return;
	}

	update_post_meta( $product_id, '_aveniadp_pinned_product', $pinned ? '1' : '0' );
}

/**
 * Unpin all catalog products except one.
 *
 * @param int $except_product_id Product ID to keep pinned.
 * @since 1.0.0
 */
function aveniadp_unpin_other_catalog_products( $except_product_id ) {
	$except_product_id = absint( $except_product_id );

	$existing = get_posts(
		array(
			'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ensures only one product remains pinned in the catalog.
				array(
					'key'   => '_aveniadp_pinned_product',
					'value' => '1',
				),
			),
		)
	);

	foreach ( $existing as $other_id ) {
		if ( absint( $other_id ) !== $except_product_id ) {
			update_post_meta( absint( $other_id ), '_aveniadp_pinned_product', '0' );
		}
	}
}

/**
 * Sanitize pinned product meta.
 *
 * @param mixed  $value         Meta value.
 * @param int    $object_id     Post ID.
 * @param string $meta_key      Meta key.
 * @param string $object_type   Object type.
 * @param string $object_subtype Object subtype.
 * @return string
 * @since 1.0.0
 */
function aveniadp_sanitize_pinned_product_meta( $value, $object_id = 0, $meta_key = '', $object_type = '', $object_subtype = '' ) {
	unset( $meta_key, $object_type, $object_subtype );

	$pinned    = ( '1' === (string) $value || true === $value || 1 === $value );
	$object_id = absint( $object_id );

	if ( $pinned && $object_id ) {
		aveniadp_unpin_other_catalog_products( $object_id );
	}

	return $pinned ? '1' : '0';
}

/**
 * Parse a shortcode catalog display boolean attribute.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $key     Attribute key.
 * @param bool   $default Default when the attribute is omitted or empty.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_parse_catalog_show_attr( $atts, $key, $default ) {
	if ( ! is_array( $atts ) || ! isset( $atts[ $key ] ) || '' === (string) $atts[ $key ] ) {
		return $default;
	}

	return in_array( strtolower( (string) $atts[ $key ] ), array( '1', 'true', 'yes', 'on' ), true );
}

/**
 * Default catalog grid template arguments (archives and block-theme fallbacks).
 *
 * @return array<string, mixed>
 * @since 1.0.0
 */
function aveniadp_get_default_catalog_grid_args() {
	return array(
		'per_page'             => aveniadp_get_products_per_page( 0 ),
		'category'             => '',
		'tag'                  => '',
		'order_by'             => 'date',
		'order'                => 'DESC',
		'columns'              => 3,
		'aveniadp_card_show_image'   => true,
		'aveniadp_card_show_price'   => true,
		'aveniadp_card_show_excerpt' => false,
	);
}

/**
 * Merge and sanitize catalog grid template arguments.
 *
 * @param array<string, mixed> $args Partial or full args (catalog-grid.php variable names).
 * @return array<string, mixed>
 * @since 1.0.0
 */
function aveniadp_parse_catalog_grid_args( $args = array() ) {
	$args   = is_array( $args ) ? $args : array();
	$merged = wp_parse_args( $args, aveniadp_get_default_catalog_grid_args() );

	return array(
		'per_page'             => aveniadp_get_products_per_page( absint( $merged['per_page'] ) ),
		'category'             => sanitize_text_field( $merged['category'] ),
		'tag'                  => sanitize_text_field( $merged['tag'] ),
		'order_by'             => sanitize_text_field( $merged['order_by'] ),
		'order'                => sanitize_text_field( $merged['order'] ),
		'columns'              => max( 1, min( 6, absint( $merged['columns'] ) ) ),
		'aveniadp_card_show_image'   => (bool) $merged['aveniadp_card_show_image'],
		'aveniadp_card_show_price'   => (bool) $merged['aveniadp_card_show_price'],
		'aveniadp_card_show_excerpt' => (bool) $merged['aveniadp_card_show_excerpt'],
	);
}

/**
 * Render the product catalog grid (shared entry point for all catalog paths).
 *
 * @param array<string, mixed> $args Optional catalog-grid.php arguments.
 * @return string
 * @since 1.0.0
 */
function aveniadp_render_catalog_grid( $args = array() ) {
	return aveniadp_get_template_html( 'catalog-grid.php', aveniadp_parse_catalog_grid_args( $args ) );
}

/**
 * Build catalog grid template arguments from shortcode attributes.
 *
 * @param array $atts Shortcode attributes.
 * @return array<string, mixed>
 * @since 1.0.0
 */
function aveniadp_get_catalog_grid_args_from_shortcode_atts( $atts ) {
	$atts = is_array( $atts ) ? $atts : array();

	return aveniadp_parse_catalog_grid_args(
		array(
			'per_page'             => ! empty( $atts['per_page'] ) ? absint( $atts['per_page'] ) : 0,
			'category'             => ! empty( $atts['category'] ) ? sanitize_text_field( $atts['category'] ) : '',
			'tag'                  => ! empty( $atts['tag'] ) ? sanitize_text_field( $atts['tag'] ) : '',
			'order_by'             => ! empty( $atts['order_by'] ) ? sanitize_text_field( $atts['order_by'] ) : 'date',
			'order'                => ! empty( $atts['order'] ) ? sanitize_text_field( $atts['order'] ) : 'DESC',
			'columns'              => ! empty( $atts['columns'] ) ? absint( $atts['columns'] ) : 3,
			'aveniadp_card_show_image'   => aveniadp_parse_catalog_show_attr( $atts, 'show_image', true ),
			'aveniadp_card_show_price'   => aveniadp_parse_catalog_show_attr( $atts, 'show_price', true ),
			'aveniadp_card_show_excerpt' => aveniadp_parse_catalog_show_attr( $atts, 'show_excerpt', false ),
		)
	);
}

/**
 * Build catalog grid template arguments from Products block attributes.
 *
 * @param array $attributes Block attributes.
 * @return array
 * @since 1.0.0
 */
function aveniadp_get_catalog_grid_args_from_block_attributes( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$mapped = array(
		'per_page' => isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 0,
		'category' => isset( $attributes['category'] ) ? (string) $attributes['category'] : '',
		'tag'      => isset( $attributes['tag'] ) ? (string) $attributes['tag'] : '',
		'order_by' => isset( $attributes['orderBy'] ) ? (string) $attributes['orderBy'] : '',
		'order'    => isset( $attributes['order'] ) ? (string) $attributes['order'] : '',
		'columns'  => isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 0,
	);

	if ( isset( $attributes['showImage'] ) ) {
		$mapped['show_image'] = $attributes['showImage'] ? '1' : '0';
	}
	if ( isset( $attributes['showPrice'] ) ) {
		$mapped['show_price'] = $attributes['showPrice'] ? '1' : '0';
	}
	if ( isset( $attributes['showExcerpt'] ) ) {
		$mapped['show_excerpt'] = $attributes['showExcerpt'] ? '1' : '0';
	}

	return aveniadp_get_catalog_grid_args_from_shortcode_atts( $mapped );
}

/**
 * Server-side render callback for the Products block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string
 * @since 1.0.0
 */
function aveniadp_render_products_block( $attributes, $content, $block ) {
	unset( $content, $block );

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => 'aveniadp-products-block',
		)
	);

	return sprintf(
		'<div %1$s>%2$s</div>',
		$wrapper_attributes,
		aveniadp_render_catalog_grid( aveniadp_get_catalog_grid_args_from_block_attributes( $attributes ) )
	);
}
