<?php
/**
 * Cart template.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$cart        = function_exists( 'aveniadp_cart' ) ? aveniadp_cart() : new AVENIADP_Cart();
$cart_items  = $cart->get_cart();
$cart_totals = $cart->get_cart_totals();
$applied_code = $cart_totals['discount_code'];
?>

<section class="aveniadp-cart">
	<div class="entry-content">
	<?php if ( ! empty( $cart_items ) ) : ?>
		<div class="aveniadp-cart-summary">
			<table class="aveniadp-cart-table">
			<colgroup>
				<col class="aveniadp-cart-col-product" />
				<col class="aveniadp-cart-col-price" />
				<col class="aveniadp-cart-col-actions" />
			</colgroup>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Price', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cart_items as $product_id => $item ) : ?>
					<?php
					$product      = get_post( $product_id );
					$item_details = aveniadp_get_cart_item_details_text( $product_id, $item );
					$product_url  = get_permalink( $product_id );
					?>
					<tr>
						<td class="aveniadp-cart-item-product">
							<span class="aveniadp-cart-item-name"><?php echo esc_html( $product->post_title ); ?></span>
							<span class="aveniadp-cart-item-meta"><?php echo esc_html( $item_details ); ?></span>
							<?php if ( $product_url && aveniadp_product_has_purchase_options( $product_id ) ) : ?>
								<a href="<?php echo esc_url( aveniadp_get_cart_edit_product_url( $product_id ) ); ?>" class="aveniadp-cart-item-edit">
									<?php esc_html_e( 'Change options', 'avenia-digital-products' ); ?>
								</a>
							<?php endif; ?>
						</td>
						<td><?php echo wp_kses_post( aveniadp_get_cart_item_price_html( $product_id, $item ) ); ?></td>
						<td>
							<button type="button" class="aveniadp-text-btn aveniadp-remove-from-cart" data-product-id="<?php echo esc_attr( $product_id ); ?>">
								<?php esc_html_e( 'Remove', 'avenia-digital-products' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			</table>

			<div class="aveniadp-cart-discount" id="aveniadp-cart-discount">
			<?php if ( $applied_code ) : ?>
				<p class="aveniadp-applied-discount aveniadp-notice is-success">
					<span class="aveniadp-applied-discount-text">
						<?php
						echo esc_html__( 'Discount applied:', 'avenia-digital-products' ) . ' ';
						echo '<strong>' . esc_html( $applied_code ) . '</strong>';
						?>
					</span>
					<button
						type="button"
						class="aveniadp-remove-discount aveniadp-remove-discount-btn"
						aria-label="<?php esc_attr_e( 'Remove discount code', 'avenia-digital-products' ); ?>"
					>
						<span aria-hidden="true">&times;</span>
					</button>
				</p>
			<?php else : ?>
				<div class="aveniadp-discount-form">
					<label for="aveniadp_discount_code"><?php esc_html_e( 'Discount Code', 'avenia-digital-products' ); ?></label>
					<div class="aveniadp-discount-form-row">
						<input type="text" id="aveniadp_discount_code" name="discount_code" autocomplete="off" />
						<button type="button" class="button aveniadp-option-btn aveniadp-apply-discount">
							<?php esc_html_e( 'Apply', 'avenia-digital-products' ); ?>
						</button>
					</div>
					<p class="aveniadp-notice aveniadp-discount-message" aria-live="polite" hidden></p>
				</div>
			<?php endif; ?>
			</div>

			<div class="aveniadp-cart-totals" id="aveniadp-cart-totals">
			<div class="aveniadp-cart-totals-row">
				<span><?php esc_html_e( 'Subtotal', 'avenia-digital-products' ); ?></span>
				<span class="aveniadp-cart-subtotal"><?php echo esc_html( aveniadp_format_price( $cart_totals['subtotal'] ) ); ?></span>
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
					<span class="aveniadp-cart-discount-amount">&minus;<?php echo esc_html( aveniadp_format_price( $cart_totals['discount_amount'] ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php aveniadp_render_cart_tax_totals_rows( $cart_totals ); ?>
			<div class="aveniadp-cart-totals-row aveniadp-cart-total-row">
				<strong><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></strong>
				<strong class="aveniadp-cart-total"><?php echo esc_html( aveniadp_format_price( $cart_totals['total'] ) ); ?></strong>
			</div>
			</div>
		</div>

		<a href="<?php echo esc_url( aveniadp_get_checkout_page_url() ); ?>" class="button aveniadp-add-to-cart-btn aveniadp-proceed-checkout">
			<span class="aveniadp-add-to-cart-label"><?php esc_html_e( 'Proceed to Checkout', 'avenia-digital-products' ); ?></span>
			<span class="aveniadp-add-to-cart-price"><?php echo esc_html( aveniadp_format_price( $cart_totals['total'] ) ); ?></span>
		</a>
	<?php else : ?>
		<p class="aveniadp-notice"><?php esc_html_e( 'Your cart is empty.', 'avenia-digital-products' ); ?></p>
	<?php endif; ?>
	</div>
</section>
