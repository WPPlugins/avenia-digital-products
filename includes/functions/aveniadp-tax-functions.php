<?php
/**
 * Flat tax helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid tax mode keys.
 *
 * @return string[]
 * @since 1.0.3
 */
function aveniadp_get_tax_modes() {
	$modes = array(
		'none'      => __( 'No Tax', 'avenia-digital-products' ),
		'inclusive' => __( 'Prices include tax', 'avenia-digital-products' ),
		'exclusive' => __( 'Add tax at checkout', 'avenia-digital-products' ),
	);

	/**
	 * Filter available store tax modes.
	 *
	 * @since 1.0.3
	 *
	 * @param array $modes Mode key => label.
	 */
	return apply_filters( 'aveniadp_tax_modes', $modes );
}

/**
 * Active tax mode.
 *
 * @return string none|inclusive|exclusive
 * @since 1.0.3
 */
function aveniadp_get_tax_mode() {
	$mode = get_option( 'aveniadp_tax_mode', 'none' );
	$modes = aveniadp_get_tax_modes();

	if ( ! is_string( $mode ) || ! array_key_exists( $mode, $modes ) ) {
		$mode = 'none';
	}

	/**
	 * Filter the active tax mode.
	 *
	 * @since 1.0.3
	 *
	 * @param string $mode Active mode key.
	 */
	return apply_filters( 'aveniadp_tax_mode', $mode );
}

/**
 * Configured flat tax rate (percentage).
 *
 * @return float
 * @since 1.0.3
 */
function aveniadp_get_tax_rate() {
	$rate = get_option( 'aveniadp_tax_rate', 0 );

	return max( 0, (float) $rate );
}

/**
 * Whether exclusive tax is active with a positive rate.
 *
 * @return bool
 * @since 1.0.3
 */
function aveniadp_tax_is_exclusive() {
	return 'exclusive' === aveniadp_get_tax_mode() && aveniadp_get_tax_rate() > 0;
}

/**
 * Whether prices are tax-inclusive.
 *
 * @return bool
 * @since 1.0.3
 */
function aveniadp_tax_is_inclusive() {
	return 'inclusive' === aveniadp_get_tax_mode();
}

/**
 * Calculate tax amount for a taxable subtotal.
 *
 * @param float $taxable_amount Amount after discounts.
 * @param array $context        Optional context (cart, order, etc.).
 * @return float
 * @since 1.0.3
 */
function aveniadp_calculate_tax_amount( $taxable_amount, $context = array() ) {
	$taxable_amount = max( 0, (float) $taxable_amount );
	$amount         = 0.0;

	if ( aveniadp_tax_is_exclusive() ) {
		$rate   = aveniadp_get_tax_rate();
		$amount = round( $taxable_amount * ( $rate / 100 ), 2 );
	}

	/**
	 * Filter calculated tax for a taxable amount.
	 *
	 * @since 1.0.3
	 *
	 * @param float $amount         Tax amount.
	 * @param float $taxable_amount Subtotal after discounts.
	 * @param array $context        Calculation context.
	 */
	return (float) apply_filters( 'aveniadp_calculate_tax', $amount, $taxable_amount, $context );
}

/**
 * Human-readable tax line label.
 *
 * @return string
 * @since 1.0.3
 */
function aveniadp_get_tax_label() {
	if ( ! aveniadp_tax_is_exclusive() ) {
		return __( 'Tax', 'avenia-digital-products' );
	}

	return sprintf(
		/* translators: %s: tax rate percentage */
		__( 'Tax (%s%%)', 'avenia-digital-products' ),
		rtrim( rtrim( number_format( aveniadp_get_tax_rate(), 2, '.', '' ), '0' ), '.' )
	);
}

/**
 * Merge tax fields into cart totals.
 *
 * @param array $totals Cart totals before tax.
 * @return array
 * @since 1.0.3
 */
function aveniadp_apply_tax_to_cart_totals( $totals ) {
	$subtotal        = isset( $totals['subtotal'] ) ? (float) $totals['subtotal'] : 0.0;
	$discount_amount = isset( $totals['discount_amount'] ) ? (float) $totals['discount_amount'] : 0.0;
	$taxable_amount  = max( 0, $subtotal - $discount_amount );
	$tax_amount      = aveniadp_calculate_tax_amount(
		$taxable_amount,
		array(
			'cart_totals' => $totals,
		)
	);

	$totals['taxable_amount']         = $taxable_amount;
	$totals['tax_amount']             = $tax_amount;
	$totals['tax_rate']               = aveniadp_tax_is_exclusive() ? aveniadp_get_tax_rate() : 0;
	$totals['tax_mode']               = aveniadp_get_tax_mode();
	$totals['show_tax_line']          = aveniadp_tax_is_exclusive() && $tax_amount > 0;
	$totals['show_tax_inclusive_note'] = aveniadp_tax_is_inclusive();
	$totals['tax_label']              = aveniadp_get_tax_label();
	$totals['total']                  = max( 0, $taxable_amount + $tax_amount );

	return $totals;
}

/**
 * Render tax rows for cart/checkout totals tables.
 *
 * @param array $cart_totals Cart totals array.
 * @since 1.0.3
 */
function aveniadp_render_cart_tax_totals_rows( $cart_totals ) {
	if ( ! empty( $cart_totals['show_tax_line'] ) ) :
		?>
		<div class="aveniadp-cart-totals-row aveniadp-cart-tax-row">
			<span><?php echo esc_html( $cart_totals['tax_label'] ); ?></span>
			<span class="aveniadp-cart-tax-amount"><?php echo esc_html( aveniadp_format_price( $cart_totals['tax_amount'] ) ); ?></span>
		</div>
		<?php
	endif;

	if ( ! empty( $cart_totals['show_tax_inclusive_note'] ) ) :
		?>
		<p class="description aveniadp-cart-tax-inclusive-note"><?php esc_html_e( 'Prices include tax.', 'avenia-digital-products' ); ?></p>
		<?php
	endif;
}
