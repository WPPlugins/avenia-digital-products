<?php
/**
 * Checkout template.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$cart         = function_exists( 'aveniadp_cart' ) ? aveniadp_cart() : new AVENIADP_Cart();
$cart_items   = $cart->get_cart();
$cart_totals  = $cart->get_cart_totals();
$applied_code = $cart_totals['discount_code'];
$payments     = function_exists( 'aveniadp_payments' ) ? aveniadp_payments() : new AVENIADP_Payments();
$gateways     = $payments->get_enabled_gateways();
$simulate     = function_exists( 'aveniadp_is_simulate_payments' ) && aveniadp_is_simulate_payments();
$can_checkout = $simulate || ! empty( $gateways );
$cart_url     = function_exists( 'aveniadp_get_cart_page_url' ) ? aveniadp_get_cart_page_url() : '';
$prefill      = function_exists( 'aveniadp_get_checkout_prefill' ) ? aveniadp_get_checkout_prefill() : array(
	'email' => '',
	'name'  => '',
);
$is_logged_in = is_user_logged_in();
?>

<section class="aveniadp-checkout">
	<div class="entry-content">
	<?php if ( ! empty( $cart_items ) ) : ?>
		<div class="aveniadp-checkout-summary">
			<h2 class="aveniadp-section-heading"><?php esc_html_e( 'Order Summary', 'avenia-digital-products' ); ?></h2>

			<ul class="aveniadp-checkout-items">
				<?php foreach ( $cart_items as $product_id => $item ) : ?>
					<?php
					$product      = get_post( $product_id );
					$item_details = aveniadp_get_cart_item_details_text( $product_id, $item );
					?>
					<li>
						<div class="aveniadp-checkout-item-info">
							<span class="aveniadp-checkout-item-name"><?php echo esc_html( $product->post_title ); ?></span>
							<span class="aveniadp-checkout-item-meta"><?php echo esc_html( $item_details ); ?></span>
						</div>
						<span class="aveniadp-checkout-item-price"><?php echo wp_kses_post( aveniadp_get_cart_item_price_html( $product_id, $item ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="aveniadp-cart-totals">
				<div class="aveniadp-cart-totals-row">
					<span><?php esc_html_e( 'Subtotal', 'avenia-digital-products' ); ?></span>
					<span><?php echo esc_html( aveniadp_format_price( $cart_totals['subtotal'] ) ); ?></span>
				</div>
				<?php if ( $cart_totals['discount_amount'] > 0 ) : ?>
					<div class="aveniadp-cart-totals-row aveniadp-cart-discount-row">
						<span>
							<?php
							printf(
								/* translators: %s: discount code */
								esc_html__( 'Discount (%s)', 'avenia-digital-products' ),
								esc_html( $applied_code )
							);
							?>
						</span>
						<span>&minus;<?php echo esc_html( aveniadp_format_price( $cart_totals['discount_amount'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php aveniadp_render_cart_tax_totals_rows( $cart_totals ); ?>
				<div class="aveniadp-cart-totals-row aveniadp-cart-total-row">
					<strong><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></strong>
					<strong><?php echo esc_html( aveniadp_format_price( $cart_totals['total'] ) ); ?></strong>
				</div>
			</div>

			<?php if ( $cart_url ) : ?>
				<p class="aveniadp-checkout-edit-cart">
					<a href="<?php echo esc_url( $cart_url ); ?>">
						<?php esc_html_e( 'Edit cart or change discount code', 'avenia-digital-products' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<form id="aveniadp-checkout-form" method="post">
			<?php wp_nonce_field( 'aveniadp-checkout', 'aveniadp_checkout_nonce' ); ?>

			<p class="aveniadp-notice aveniadp-checkout-notice" id="aveniadp-checkout-notice" role="alert" aria-live="polite" hidden></p>

			<div class="aveniadp-checkout-fields">
				<?php if ( ! $is_logged_in ) : ?>
					<p>
						<label for="aveniadp_name"><?php esc_html_e( 'Name', 'avenia-digital-products' ); ?></label>
						<input type="text" id="aveniadp_name" name="name" autocomplete="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" />
					</p>
					<p>
						<label for="aveniadp_email"><?php esc_html_e( 'Email', 'avenia-digital-products' ); ?> <span class="required">*</span></label>
						<input type="email" id="aveniadp_email" name="email" required autocomplete="email" value="<?php echo esc_attr( $prefill['email'] ); ?>" />
						<span class="aveniadp-field-error" id="aveniadp-email-error" role="alert" hidden></span>
					</p>
					<p>
						<label for="aveniadp_email_confirm"><?php esc_html_e( 'Confirm email', 'avenia-digital-products' ); ?> <span class="required">*</span></label>
						<input type="email" id="aveniadp_email_confirm" name="email_confirm" required autocomplete="off" value="<?php echo esc_attr( $prefill['email'] ); ?>" />
						<span class="aveniadp-field-error" id="aveniadp-email-confirm-error" role="alert" hidden></span>
					</p>
				<?php else : ?>
					<p class="aveniadp-checkout-logged-in-as">
						<?php
						printf(
							/* translators: %s: customer email address */
							esc_html__( 'Purchasing as %s', 'avenia-digital-products' ),
							esc_html( $prefill['email'] )
						);
						?>
					</p>
					<input type="hidden" id="aveniadp_name" name="name" value="<?php echo esc_attr( $prefill['name'] ); ?>" />
					<input type="hidden" id="aveniadp_email" name="email" value="<?php echo esc_attr( $prefill['email'] ); ?>" />
				<?php endif; ?>

				<?php if ( $simulate ) : ?>
					<div class="aveniadp-notice aveniadp-checkout-simulate-notice" role="status">
						<?php esc_html_e( 'Test mode: your order will complete without a real charge. No payment provider is used.', 'avenia-digital-products' ); ?>
					</div>
					<input type="hidden" name="payment_method" value="simulate" />
				<?php elseif ( count( $gateways ) > 1 ) : ?>
					<div class="aveniadp-option-group aveniadp-payment-methods" data-target="aveniadp_payment_method">
						<span class="aveniadp-option-label" id="aveniadp_payment_method_label"><?php esc_html_e( 'Payment Method', 'avenia-digital-products' ); ?></span>
						<div class="aveniadp-option-buttons aveniadp-payment-method-buttons" role="group" aria-labelledby="aveniadp_payment_method_label">
							<?php
							$gateway_index = 0;
							foreach ( $gateways as $id => $gateway ) :
								$is_selected = ( 0 === $gateway_index );
								$logo_html   = function_exists( 'aveniadp_get_payment_gateway_logo_html' ) ? aveniadp_get_payment_gateway_logo_html( $id ) : '';
								?>
								<button
									type="button"
									class="aveniadp-option-btn aveniadp-payment-method-btn<?php echo $is_selected ? ' is-selected' : ''; ?>"
									data-value="<?php echo esc_attr( $id ); ?>"
									aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
								>
									<span class="aveniadp-payment-method-name"><?php echo esc_html( $gateway->get_title() ); ?></span>
									<?php if ( $logo_html ) : ?>
										<span class="aveniadp-payment-method-logo" aria-hidden="true"><?php echo wp_kses( $logo_html, aveniadp_get_allowed_svg_html() ); ?></span>
									<?php endif; ?>
								</button>
								<?php
								++$gateway_index;
							endforeach;
							?>
						</div>
						<select id="aveniadp_payment_method" name="payment_method" class="aveniadp-visually-hidden" required>
							<?php
							$gateway_index = 0;
							foreach ( $gateways as $id => $gateway ) :
								?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( 0 === $gateway_index ); ?>>
									<?php echo esc_html( $gateway->get_title() ); ?>
								</option>
								<?php
								++$gateway_index;
							endforeach;
							?>
						</select>
					</div>
				<?php elseif ( ! empty( $gateways ) ) : ?>
					<input type="hidden" name="payment_method" value="<?php echo esc_attr( array_key_first( $gateways ) ); ?>" />
				<?php endif; ?>
			</div>

			<?php if ( $can_checkout ) : ?>
			<button type="submit" class="button aveniadp-add-to-cart-btn aveniadp-checkout-submit">
				<span class="aveniadp-add-to-cart-label">
					<?php
					echo $simulate
						? esc_html__( 'Complete Order', 'avenia-digital-products' )
						: esc_html__( 'Complete Purchase', 'avenia-digital-products' );
					?>
				</span>
				<span class="aveniadp-add-to-cart-price"><?php echo esc_html( aveniadp_format_price( $cart_totals['total'] ) ); ?></span>
			</button>
			<?php else : ?>
				<p class="aveniadp-notice is-error"><?php esc_html_e( 'No payment methods are available. Please contact the store owner.', 'avenia-digital-products' ); ?></p>
			<?php endif; ?>
		</form>
	<?php else : ?>
		<p class="aveniadp-notice"><?php esc_html_e( 'Your cart is empty.', 'avenia-digital-products' ); ?></p>
	<?php endif; ?>
	</div>
</section>
