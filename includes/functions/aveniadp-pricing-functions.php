<?php
/**
 * Pricing matrix and formatting helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function aveniadp_format_price( $amount, $currency = '' ) {
	if ( empty( $currency ) ) {
		$currency = get_option( 'aveniadp_currency', 'USD' );
	}

	$position = get_option( 'aveniadp_currency_position', 'left' );
	$symbol = aveniadp_get_currency_symbol( $currency );

	$formatted = number_format( floatval( $amount ), 2 );

	if ( 'left' === $position ) {
		return $symbol . $formatted;
	}

	return $formatted . ' ' . $symbol;
}

/**
 * Get currency symbol.
 *
 * @param string $currency Currency code.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_currency_symbol( $currency ) {
	$symbols = array(
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'JPY' => '¥',
		'AUD' => 'A$',
		'CAD' => 'C$',
	);

	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency;
}

/**
 * Get product price.
 *
 * @param int $product_id       Product ID.
 * @param int $license_quantity License quantity.
 * @return float|WP_Error
 * @since 1.0.0
 */
function aveniadp_get_product_price( $product_id, $license_quantity = 1 ) {
	return aveniadp_cart()->calculate_price( $product_id, $license_quantity );
}

/**
 * Get product pricing matrix.
 *
 * @param int $product_id Product ID.
 * @return array
 * @since 1.0.0
 */
function aveniadp_get_product_pricing_matrix( $product_id ) {
	return get_post_meta( $product_id, '_aveniadp_pricing_matrix', true );
}

/**
 * Get lowest catalog listing price for a product across license tiers (and billing periods).
 *
 * @param int $product_id Product ID.
 * @return float|null Null when no price is configured.
 * @since 1.0.0
 */
function aveniadp_get_product_listing_price( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return null;
	}

	$pricing_matrix = aveniadp_get_product_pricing_matrix( $product_id );
	if ( empty( $pricing_matrix ) || ! is_array( $pricing_matrix ) ) {
		return null;
	}

	$lowest_price = null;

	foreach ( array_keys( $pricing_matrix ) as $quantity ) {
		$price = aveniadp_get_product_price( $product_id, aveniadp_sanitize_license_quantity( $quantity, 1 ) );
		if ( is_wp_error( $price ) ) {
			continue;
		}

		if ( null === $lowest_price || $price < $lowest_price ) {
			$lowest_price = floatval( $price );
		}
	}

	return $lowest_price;
}

/**
 * Get formatted listing price HTML for catalog views (blocks, archives).
 *
 * @param int $product_id Product ID.
 * @return string Empty string when no price is configured.
 * @since 1.0.0
 */
function aveniadp_get_product_listing_price_html( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return '';
	}

	if ( '1' === get_post_meta( $product_id, '_aveniadp_free_download', true ) ) {
		return '<span class="aveniadp-price-free">' . esc_html__( 'Free', 'avenia-digital-products' ) . '</span>';
	}

	$price = aveniadp_get_product_listing_price( $product_id );
	if ( null === $price ) {
		return '';
	}

	return esc_html( aveniadp_format_price( $price ) );
}

/**
 * Get formatted price HTML for a product selection (single product, AJAX updates).
 *
 * @param int $product_id       Product ID.
 * @param int $license_quantity License quantity.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_product_price_display_html( $product_id, $license_quantity = 1 ) {
	$product_id = absint( $product_id );

	if ( ! $product_id ) {
		return '-';
	}

	$license_quantity = aveniadp_sanitize_license_quantity( $license_quantity, 1 );
	if ( ! aveniadp_is_unlimited_license_quantity( $license_quantity ) && $license_quantity < 1 ) {
		return '-';
	}

	$price = aveniadp_get_product_price( $product_id, $license_quantity );
	if ( is_wp_error( $price ) ) {
		return '-';
	}

	return esc_html( aveniadp_format_price( $price ) );
}

/**
 * Whether a license quantity represents unlimited site activations.
 *
 * @param int|string $license_quantity License quantity.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_unlimited_license_quantity( $license_quantity ) {
	return 0 === absint( $license_quantity );
}

/**
 * Sanitize license quantity from request input (0 = unlimited sites).
 *
 * @param mixed $value   Raw value.
 * @param int   $default Default when value is missing or invalid.
 * @return int
 * @since 1.0.0
 */
function aveniadp_sanitize_license_quantity( $value, $default = 1 ) {
	if ( ! isset( $value ) || '' === $value ) {
		return $default;
	}

	$quantity = absint( $value );
	if ( 0 === $quantity ) {
		return 0;
	}

	return $quantity >= 1 ? $quantity : $default;
}

/**
 * Format license quantity for display (e.g. "1 site", "5 sites", "Unlimited sites").
 *
 * @param int $license_quantity License quantity.
 * @return string
 * @since 1.0.0
 */
function aveniadp_format_license_quantity_label( $license_quantity ) {
	if ( aveniadp_is_unlimited_license_quantity( $license_quantity ) ) {
		return __( 'Unlimited sites', 'avenia-digital-products' );
	}

	$license_quantity = absint( $license_quantity );
	if ( $license_quantity < 1 ) {
		$license_quantity = 1;
	}

	if ( 1 === $license_quantity ) {
		return sprintf(
			/* translators: %s: number of sites */
			__( '%s site', 'avenia-digital-products' ),
			number_format_i18n( $license_quantity )
		);
	}

	return sprintf(
		/* translators: %s: number of sites */
		__( '%s sites', 'avenia-digital-products' ),
		number_format_i18n( $license_quantity )
	);
}

/**
 * Get cart line item detail parts for display.
 *
 * @param int   $product_id Product ID.
 * @param array $item       Cart item data.
 * @return array
 * @since 1.0.0
 */
function aveniadp_get_cart_item_detail_parts( $product_id, $item ) {
	$license_quantity = isset( $item['license_quantity'] ) ? aveniadp_sanitize_license_quantity( $item['license_quantity'], 1 ) : 1;

	return array(
		aveniadp_format_license_quantity_label( $license_quantity ),
		__( 'One-time purchase', 'avenia-digital-products' ),
	);
}

/**
 * Get cart line item details as a single string.
 *
 * @param int   $product_id Product ID.
 * @param array $item       Cart item data.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_cart_item_details_text( $product_id, $item ) {
	return implode( ' · ', aveniadp_get_cart_item_detail_parts( $product_id, $item ) );
}

/**
 * Whether a product offers more than one pricing tier to choose from.
 *
 * @param int $product_id Product ID.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_product_has_purchase_options( $product_id ) {
	$pricing_matrix = get_post_meta( absint( $product_id ), '_aveniadp_pricing_matrix', true );

	if ( empty( $pricing_matrix ) || ! is_array( $pricing_matrix ) ) {
		return false;
	}

	return count( $pricing_matrix ) > 1;
}

/**
 * Product page URL for editing cart item options.
 *
 * @param int $product_id Product ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_cart_edit_product_url( $product_id ) {
	$url = get_permalink( absint( $product_id ) );

	if ( ! $url ) {
		return '';
	}

	return $url . '#aveniadp-product-purchase';
}

/**
 * Get formatted cart line item price HTML.
 *
 * @param int   $product_id Product ID.
 * @param array $item       Cart item data.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_cart_item_price_html( $product_id, $item ) {
	$price = isset( $item['price'] ) ? max( 0, floatval( $item['price'] ) ) : 0.0;

	return esc_html( aveniadp_format_price( $price ) );
}
