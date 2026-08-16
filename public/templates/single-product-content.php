<?php
/**
 * Single product layout (rendered inside theme content area).
 *
 * Override: your-theme/avenia-digital-products/single-product-content.php
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$product_id           = get_the_ID();
$free_download        = get_post_meta( $product_id, '_aveniadp_free_download', true );
$has_download_file    = function_exists( 'aveniadp_product_has_download_file' ) && aveniadp_product_has_download_file( $product_id );
$pricing_matrix       = get_post_meta( $product_id, '_aveniadp_pricing_matrix', true );
$categories           = get_the_terms( $product_id, 'aveniadp_product_category' );
$has_pricing_matrix   = ! empty( $pricing_matrix ) && is_array( $pricing_matrix );
$has_license_options  = $has_pricing_matrix && count( $pricing_matrix ) > 1;
$only_quantity        = $has_pricing_matrix ? aveniadp_sanitize_license_quantity( array_key_first( $pricing_matrix ), 1 ) : 0;

$selected_quantity = $only_quantity;
$is_updating_cart  = false;

if ( function_exists( 'aveniadp_cart' ) ) {
	$cart = aveniadp_cart()->get_cart();
	if ( isset( $cart[ $product_id ] ) ) {
		$is_updating_cart = true;
		$cart_item        = $cart[ $product_id ];

		if ( isset( $cart_item['license_quantity'] ) ) {
			$selected_quantity = aveniadp_sanitize_license_quantity( $cart_item['license_quantity'], $only_quantity );
		}
	}
}

if ( $has_pricing_matrix && ! isset( $pricing_matrix[ (string) $selected_quantity ] ) ) {
	$selected_quantity = $only_quantity;
}

$initial_price_label = '';
if ( '1' === $free_download ) {
	$initial_price_label = esc_html__( 'Free', 'avenia-digital-products' );
} elseif ( $has_pricing_matrix ) {
	$initial_price_label = aveniadp_get_product_price_display_html( $product_id, $selected_quantity );
}

$description = isset( $aveniadp_product_description ) ? $aveniadp_product_description : '';
$free_download_requires_email = function_exists( 'aveniadp_free_download_requires_email' ) && aveniadp_free_download_requires_email();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'aveniadp-single-product' ); ?>>
	<div class="aveniadp-single-product-layout">
		<div class="aveniadp-single-product-media">
			<?php
			$gallery_html = function_exists( 'aveniadp_get_product_gallery_html' ) ? aveniadp_get_product_gallery_html( $product_id ) : '';
			if ( $gallery_html ) {
				echo wp_kses_post( $gallery_html );
			}
			?>
		</div>

		<div class="aveniadp-single-product-summary">
			<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
				<nav class="aveniadp-product-breadcrumb" aria-label="<?php esc_attr_e( 'Product category', 'avenia-digital-products' ); ?>">
					<?php
					$category = $categories[0];
					printf(
						'<a href="%1$s">/ %2$s</a>',
						esc_url( get_term_link( $category ) ),
						esc_html( strtoupper( $category->name ) )
					);
					?>
				</nav>
			<?php endif; ?>

			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<?php if ( $initial_price_label ) : ?>
				<div class="aveniadp-single-product-pricing">
					<div class="aveniadp-single-product-price" id="aveniadp-selected-price">
						<?php echo wp_kses_post( $initial_price_label ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<div class="entry-content aveniadp-product-content">
					<h2 class="aveniadp-product-description-heading"><?php esc_html_e( 'Product Description', 'avenia-digital-products' ); ?></h2>
					<?php echo wp_kses_post( $description ); ?>
				</div>
			<?php endif; ?>

			<div id="aveniadp-product-purchase" class="aveniadp-product-purchase">
				<?php
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public buy-now redirect error display; code is sanitized before output.
				if ( ! empty( $_GET['aveniadp_buy_error'] ) && function_exists( 'aveniadp_get_direct_checkout_error_message' ) ) {
					$buy_error_code = sanitize_key( wp_unslash( $_GET['aveniadp_buy_error'] ) );
					if ( $buy_error_code ) {
						?>
						<p class="aveniadp-notice is-error" role="alert">
							<?php echo esc_html( aveniadp_get_direct_checkout_error_message( $buy_error_code ) ); ?>
						</p>
						<?php
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				?>
				<?php if ( '1' === $free_download ) : ?>
					<?php if ( $has_download_file ) : ?>
						<?php if ( function_exists( 'aveniadp_render_product_demo_button' ) ) : ?>
							<?php aveniadp_render_product_demo_button( $product_id ); ?>
						<?php endif; ?>
						<button type="button" class="button aveniadp-add-to-cart-btn aveniadp-free-download" data-product-id="<?php echo esc_attr( $product_id ); ?>">
							<?php esc_html_e( 'Download', 'avenia-digital-products' ); ?>
						</button>
						<p class="aveniadp-notice aveniadp-free-download-message" role="alert" aria-live="polite" hidden></p>
					<?php else : ?>
						<p class="aveniadp-notice is-error"><?php esc_html_e( 'Free download is enabled but no download file or URL has been configured yet.', 'avenia-digital-products' ); ?></p>
					<?php endif; ?>
				<?php elseif ( $has_pricing_matrix ) : ?>
					<?php if ( $has_license_options ) : ?>
						<div class="aveniadp-product-options">
							<?php if ( $has_license_options ) : ?>
								<div class="aveniadp-option-group aveniadp-license-quantity" data-target="aveniadp_license_quantity">
									<span class="aveniadp-option-label" id="aveniadp_license_quantity_label"><?php esc_html_e( 'Sites', 'avenia-digital-products' ); ?></span>
									<div class="aveniadp-option-buttons" role="group" aria-labelledby="aveniadp_license_quantity_label">
										<?php
										foreach ( $pricing_matrix as $quantity => $prices ) :
											$label         = aveniadp_format_license_quantity_label( $quantity );
											$is_selected   = ( (string) $quantity === (string) $selected_quantity );
											?>
											<button
												type="button"
												class="aveniadp-option-btn<?php echo $is_selected ? ' is-selected' : ''; ?>"
												data-value="<?php echo esc_attr( $quantity ); ?>"
												aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
											><?php echo esc_html( $label ); ?></button>
											<?php
										endforeach;
										?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<form id="aveniadp-purchase-form" class="aveniadp-purchase-form" method="post"<?php echo $is_updating_cart ? ' data-update-cart="1"' : ''; ?>>
						<?php wp_nonce_field( 'aveniadp-add-to-cart', 'aveniadp_add_to_cart_nonce' ); ?>
						<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />

						<select
							id="aveniadp_license_quantity"
							name="license_quantity"
							class="aveniadp-visually-hidden"
							required
						>
							<?php foreach ( $pricing_matrix as $quantity => $prices ) : ?>
								<option
									value="<?php echo esc_attr( $quantity ); ?>"
									<?php selected( (string) $quantity, (string) $selected_quantity ); ?>
								>
									<?php echo esc_html( aveniadp_format_license_quantity_label( $quantity ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<div class="aveniadp-add-to-cart-actions">
							<?php if ( function_exists( 'aveniadp_render_product_demo_button' ) ) : ?>
								<?php aveniadp_render_product_demo_button( $product_id ); ?>
							<?php endif; ?>
							<button type="submit" class="button aveniadp-add-to-cart-btn aveniadp-add-to-cart" data-product-id="<?php echo esc_attr( $product_id ); ?>">
								<span class="aveniadp-add-to-cart-label"><?php echo $is_updating_cart ? esc_html__( 'Update Cart', 'avenia-digital-products' ) : esc_html__( 'Add to Cart', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-add-to-cart-price"><?php echo wp_kses_post( $initial_price_label ); ?></span>
							</button>
							<span class="aveniadp-notice aveniadp-add-to-cart-message" role="status" aria-live="polite" hidden></span>
						</div>
					</form>
				<?php else : ?>
					<p class="aveniadp-notice is-error"><?php esc_html_e( 'Product pricing not configured.', 'avenia-digital-products' ); ?></p>
				<?php endif; ?>
			</div>

			<?php
			$info_sections_html = function_exists( 'aveniadp_get_product_info_sections_html' ) ? aveniadp_get_product_info_sections_html( $product_id ) : '';
			if ( $info_sections_html ) {
				echo wp_kses_post( $info_sections_html );
			}
			?>
		</div>
	</div>

	<?php
	$related_html = function_exists( 'aveniadp_get_related_products_html' ) ? aveniadp_get_related_products_html( $product_id ) : '';
	if ( $related_html ) {
		echo wp_kses_post( $related_html );
	}
	?>
</article>

<?php if ( '1' === $free_download && $has_download_file && $free_download_requires_email ) : ?>
	<div class="aveniadp-free-download-modal" id="aveniadp-free-download-modal" hidden aria-hidden="true">
		<div class="aveniadp-free-download-modal-backdrop" data-aveniadp-close-free-download-modal></div>
		<div
			class="aveniadp-free-download-modal-dialog"
			role="dialog"
			aria-modal="true"
			aria-labelledby="aveniadp-free-download-modal-title"
		>
			<button type="button" class="aveniadp-free-download-modal-close" data-aveniadp-close-free-download-modal aria-label="<?php esc_attr_e( 'Close', 'avenia-digital-products' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
			<div class="aveniadp-free-download-modal-form-wrap">
				<h2 class="aveniadp-free-download-modal-title" id="aveniadp-free-download-modal-title"><?php esc_html_e( 'Get your free download', 'avenia-digital-products' ); ?></h2>
				<form id="aveniadp-free-download-form" class="aveniadp-free-download-form" novalidate>
					<label class="aveniadp-free-download-email-label" for="aveniadp-free-download-email"><?php esc_html_e( 'Email address', 'avenia-digital-products' ); ?></label>
					<input
						type="email"
						id="aveniadp-free-download-email"
						name="email"
						class="aveniadp-free-download-email-input"
						required
						autocomplete="email"
					/>
					<p class="aveniadp-free-download-modal-privacy"><?php esc_html_e( 'We will email your download link. No account is required.', 'avenia-digital-products' ); ?></p>
					<button type="submit" class="button aveniadp-add-to-cart-btn aveniadp-free-download-submit">
						<?php esc_html_e( 'Send download link', 'avenia-digital-products' ); ?>
					</button>
					<p class="aveniadp-notice aveniadp-free-download-modal-message" role="alert" aria-live="polite" hidden></p>
				</form>
			</div>
			<div class="aveniadp-free-download-modal-success" hidden>
				<h2 class="aveniadp-free-download-modal-title"><?php esc_html_e( 'Check your email', 'avenia-digital-products' ); ?></h2>
				<p class="aveniadp-free-download-modal-success-message"></p>
				<?php if ( function_exists( 'aveniadp_get_account_page_url' ) && aveniadp_get_account_page_url() ) : ?>
					<a class="button aveniadp-add-to-cart-btn aveniadp-free-download-account-link" href="<?php echo esc_url( aveniadp_get_account_page_url() ); ?>" hidden>
						<?php esc_html_e( 'Go to My Account', 'avenia-digital-products' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php endif; ?>
