<?php
/**
 * Side cart drawer shell.
 *
 * Renders empty drawer markup. Cart contents load via AJAX so catalog pages can be cached.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aveniadp-cart-drawer" id="aveniadp-cart-drawer" hidden aria-hidden="true" data-aveniadp-cart-drawer-shell>
	<div class="aveniadp-cart-drawer-backdrop" data-aveniadp-close-cart-drawer></div>
	<aside
		class="aveniadp-cart-drawer-panel"
		role="dialog"
		aria-modal="true"
		aria-labelledby="aveniadp-cart-drawer-title"
		tabindex="-1"
	>
		<header class="aveniadp-cart-drawer-header">
			<h2 class="aveniadp-cart-drawer-title" id="aveniadp-cart-drawer-title">
				<span class="aveniadp-cart-drawer-count-label" data-aveniadp-cart-drawer-count-label>
					<?php esc_html_e( 'Your cart', 'avenia-digital-products' ); ?>
				</span>
			</h2>
			<button type="button" class="aveniadp-cart-drawer-close" data-aveniadp-close-cart-drawer aria-label="<?php esc_attr_e( 'Close cart', 'avenia-digital-products' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</header>

		<div class="aveniadp-cart-drawer-body" data-aveniadp-cart-drawer-body></div>

		<footer class="aveniadp-cart-drawer-footer" data-aveniadp-cart-drawer-footer hidden>
			<div class="aveniadp-cart-drawer-discount" data-aveniadp-cart-drawer-discount>
				<details class="aveniadp-cart-drawer-discount-details">
					<summary class="aveniadp-cart-drawer-discount-toggle">
						<?php esc_html_e( 'Have a discount code?', 'avenia-digital-products' ); ?>
					</summary>
					<div class="aveniadp-discount-form aveniadp-cart-drawer-slide">
						<div class="aveniadp-cart-drawer-slide-inner">
							<label class="screen-reader-text" for="aveniadp_drawer_discount_code"><?php esc_html_e( 'Discount Code', 'avenia-digital-products' ); ?></label>
							<div class="aveniadp-discount-form-row">
								<input type="text" id="aveniadp_drawer_discount_code" name="discount_code" autocomplete="off" />
								<button type="button" class="button aveniadp-option-btn aveniadp-apply-discount">
									<?php esc_html_e( 'Apply', 'avenia-digital-products' ); ?>
								</button>
							</div>
							<p class="aveniadp-notice aveniadp-discount-message" aria-live="polite" hidden></p>
						</div>
					</div>
				</details>
			</div>

			<div class="aveniadp-cart-drawer-totals" data-aveniadp-cart-drawer-totals>
				<div class="aveniadp-cart-drawer-totals-row">
					<span><?php esc_html_e( 'Subtotal', 'avenia-digital-products' ); ?></span>
					<span data-aveniadp-cart-drawer-subtotal><?php echo esc_html( aveniadp_format_price( 0 ) ); ?></span>
				</div>
				<div class="aveniadp-cart-drawer-totals-row aveniadp-cart-drawer-discount-row" data-aveniadp-cart-drawer-discount-row hidden>
					<span data-aveniadp-cart-drawer-discount-label><?php esc_html_e( 'Discount', 'avenia-digital-products' ); ?></span>
					<span data-aveniadp-cart-drawer-discount-amount></span>
				</div>
				<div class="aveniadp-cart-drawer-totals-row aveniadp-cart-drawer-tax-row" data-aveniadp-cart-drawer-tax-row hidden>
					<span data-aveniadp-cart-drawer-tax-label></span>
					<span data-aveniadp-cart-drawer-tax-amount></span>
				</div>
				<p class="description aveniadp-cart-drawer-tax-note" data-aveniadp-cart-drawer-tax-note hidden><?php esc_html_e( 'Prices include tax.', 'avenia-digital-products' ); ?></p>
				<div class="aveniadp-cart-drawer-totals-row aveniadp-cart-drawer-total-row">
					<strong data-aveniadp-cart-drawer-total-label><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></strong>
					<strong data-aveniadp-cart-drawer-total><?php echo esc_html( aveniadp_format_price( 0 ) ); ?></strong>
				</div>
			</div>

			<?php if ( function_exists( 'aveniadp_get_checkout_page_url' ) && aveniadp_get_checkout_page_url() ) : ?>
				<a href="<?php echo esc_url( aveniadp_get_checkout_page_url() ); ?>" class="button aveniadp-add-to-cart-btn aveniadp-cart-drawer-checkout">
					<?php esc_html_e( 'Checkout', 'avenia-digital-products' ); ?>
				</a>
			<?php endif; ?>
		</footer>

		<div class="aveniadp-cart-drawer-empty" data-aveniadp-cart-drawer-empty>
			<p><?php esc_html_e( 'Your cart is empty.', 'avenia-digital-products' ); ?></p>
		</div>
	</aside>
</div>
