<?php
/**
 * Admin settings view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'avenia-digital-products' ) );
}

if ( AVENIADP_Admin_Settings::was_saved() ) {
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'avenia-digital-products' ) . '</p></div>';
}

// Get current tab.
$current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab navigation in admin.
$valid_tabs   = array( 'general', 'payment', 'emails', 'appearance' );
if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
	$current_tab = 'general';
}
$appearance          = function_exists( 'aveniadp_get_appearance_colors' ) ? aveniadp_get_appearance_colors() : array(
	'button_bg'     => '#e8e8e8',
	'tab_active_bg' => '#111111',
	'border_color'  => '#e0e0e0',
	'panel_bg'      => '#f3f3f3',
);
$appearance_defaults = function_exists( 'aveniadp_get_appearance_defaults' ) ? aveniadp_get_appearance_defaults() : $appearance;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Avenia Digital Products Settings', 'avenia-digital-products' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="?page=aveniadp-settings&tab=general" class="nav-tab <?php echo 'general' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'avenia-digital-products' ); ?>
		</a>
		<a href="?page=aveniadp-settings&tab=payment" class="nav-tab <?php echo 'payment' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Payment', 'avenia-digital-products' ); ?>
		</a>
		<a href="?page=aveniadp-settings&tab=emails" class="nav-tab <?php echo 'emails' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Emails', 'avenia-digital-products' ); ?>
		</a>
		<a href="?page=aveniadp-settings&tab=appearance" class="nav-tab <?php echo 'appearance' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Appearance', 'avenia-digital-products' ); ?>
		</a>
	</nav>

	<form method="post" action="">
		<?php wp_nonce_field( 'aveniadp_settings', 'aveniadp_settings_nonce' ); ?>
		<input type="hidden" name="aveniadp_settings_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

		<?php if ( 'general' === $current_tab ) : ?>
			<h2><?php esc_html_e( 'General Settings', 'avenia-digital-products' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'More features', 'avenia-digital-products' ); ?></th>
					<td>
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: linked domain name (aveniadigitalproducts.com) */
									__( 'Subscriptions, license keys, and sales reports are available in the paid plugin at %s', 'avenia-digital-products' ),
									'<a href="' . esc_url( aveniadp_get_pro_upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">aveniadigitalproducts.com</a>'
								),
								array(
									'a' => array(
										'href'   => true,
										'target' => true,
										'rel'    => true,
									),
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_currency"><?php esc_html_e( 'Currency', 'avenia-digital-products' ); ?></label></th>
					<td>
						<select id="aveniadp_currency" name="aveniadp_currency">
							<option value="USD" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'USD' ); ?>>USD</option>
							<option value="EUR" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'EUR' ); ?>>EUR</option>
							<option value="GBP" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'GBP' ); ?>>GBP</option>
							<option value="JPY" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'JPY' ); ?>>JPY</option>
							<option value="AUD" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'AUD' ); ?>>AUD</option>
							<option value="CAD" <?php selected( get_option( 'aveniadp_currency', 'USD' ), 'CAD' ); ?>>CAD</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_currency_position"><?php esc_html_e( 'Currency Position', 'avenia-digital-products' ); ?></label></th>
					<td>
						<select id="aveniadp_currency_position" name="aveniadp_currency_position">
							<option value="left" <?php selected( get_option( 'aveniadp_currency_position', 'left' ), 'left' ); ?>><?php esc_html_e( 'Left', 'avenia-digital-products' ); ?></option>
							<option value="right" <?php selected( get_option( 'aveniadp_currency_position', 'left' ), 'right' ); ?>><?php esc_html_e( 'Right', 'avenia-digital-products' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_tax_mode"><?php esc_html_e( 'Tax', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php $tax_mode = get_option( 'aveniadp_tax_mode', 'none' ); ?>
						<select id="aveniadp_tax_mode" name="aveniadp_tax_mode">
							<?php foreach ( aveniadp_get_tax_modes() as $mode_key => $mode_label ) : ?>
								<option value="<?php echo esc_attr( $mode_key ); ?>" <?php selected( $tax_mode, $mode_key ); ?>>
									<?php echo esc_html( $mode_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description aveniadp-tax-mode-desc" data-tax-mode="none" <?php echo 'none' === $tax_mode ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'No tax is calculated or mentioned at checkout.', 'avenia-digital-products' ); ?>
						</p>
						<p class="description aveniadp-tax-mode-desc" data-tax-mode="inclusive" <?php echo 'inclusive' === $tax_mode ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'Your product prices already include tax. Customers see a "Prices include tax" note. No rate is entered here; bake tax into the prices you set.', 'avenia-digital-products' ); ?>
						</p>
						<p class="description aveniadp-tax-mode-desc" data-tax-mode="exclusive" <?php echo 'exclusive' === $tax_mode ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'Tax is added on top of product prices at checkout. Enter the store-wide rate below.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr class="aveniadp-tax-rate-row" <?php echo 'exclusive' === $tax_mode ? '' : 'style="display:none;"'; ?>>
					<th><label for="aveniadp_tax_rate"><?php esc_html_e( 'Tax rate (%)', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="number" id="aveniadp_tax_rate" name="aveniadp_tax_rate" value="<?php echo esc_attr( get_option( 'aveniadp_tax_rate', 0 ) ); ?>" min="0" max="100" step="0.01" class="small-text" />
						<p class="description"><?php esc_html_e( 'Added to the cart subtotal at checkout (for example 20 for 20%).', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_free_download_require_email"><?php esc_html_e( 'Require Email for Free Downloads', 'avenia-digital-products' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="aveniadp_free_download_require_email" name="aveniadp_free_download_require_email" value="1" <?php checked( get_option( 'aveniadp_free_download_require_email', false ), true ); ?> />
							<?php esc_html_e( 'Collect email before free downloads', 'avenia-digital-products' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, visitors enter their email to receive a download link, get a customer account, and can re-download from My Account. When disabled, free products download instantly without email.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_account_creation"><?php esc_html_e( 'Account Creation', 'avenia-digital-products' ); ?></label></th>
					<td>
						<select id="aveniadp_account_creation" name="aveniadp_account_creation">
							<option value="auto-create" <?php selected( get_option( 'aveniadp_account_creation', 'auto-create' ), 'auto-create' ); ?>><?php esc_html_e( 'Auto-Create Account After Purchase', 'avenia-digital-products' ); ?></option>
							<option value="guest" <?php selected( get_option( 'aveniadp_account_creation', 'auto-create' ), 'guest' ); ?>><?php esc_html_e( 'Guest Checkout', 'avenia-digital-products' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Auto-create is the default. It silently registers a WordPress account after a successful purchase and sends the account confirmation email. Guest checkout is email-only with no login.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_delete_data_on_uninstall"><?php esc_html_e( 'Remove Data on Uninstall', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="checkbox" id="aveniadp_delete_data_on_uninstall" name="aveniadp_delete_data_on_uninstall" value="1" <?php checked( get_option( 'aveniadp_delete_data_on_uninstall', 'no' ), 'yes' ); ?> />
						<p class="description">
							<?php esc_html_e( 'If enabled, all Avenia Digital Products tables and settings will be permanently deleted when the plugin is uninstalled from the Plugins screen. Leave unchecked to keep data for future reinstalls.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Permalinks', 'avenia-digital-products' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="aveniadp_product_base"><?php esc_html_e( 'Product base', 'avenia-digital-products' ); ?></label></th>
					<td>
						<code><?php echo esc_html( user_trailingslashit( home_url() ) ); ?></code>
						<input
							type="text"
							id="aveniadp_product_base"
							name="aveniadp_product_base"
							value="<?php echo esc_attr( aveniadp_get_product_base_slug() ); ?>"
							class="regular-text code"
						/>
						<code>/<?php esc_html_e( 'product-name', 'avenia-digital-products' ); ?>/</code>
						<p class="description">
							<?php
							printf(
								/* translators: %s: example archive URL path */
								esc_html__( 'URL slug for single products and the product archive (e.g. %s). Category and tag URLs use this base too. Save settings after changing.', 'avenia-digital-products' ),
								'<code>/' . esc_html( aveniadp_get_product_base_slug() ) . '/</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Core Pages', 'avenia-digital-products' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="aveniadp_page_shop_id"><?php esc_html_e( 'Products Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_page_shop_id',
								'id'                => 'aveniadp_page_shop_id',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Select a page', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_page_shop_id', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php
							printf(
								/* translators: 1: customizable products page path, 2: product archive path */
								esc_html__( 'A customizable product catalog page (e.g. %1$s). Add headings, images, or other blocks above the Products block in the page editor. The %2$s archive remains available separately.', 'avenia-digital-products' ),
								'<code>/products/</code>',
								'<code>/' . esc_html( aveniadp_get_product_base_slug() ) . '/</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_page_cart_id"><?php esc_html_e( 'Cart Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_page_cart_id',
								'id'                => 'aveniadp_page_cart_id',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Select a page', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_page_cart_id', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'This page should contain the [aveniadp_cart] shortcode.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_page_checkout_id"><?php esc_html_e( 'Checkout Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_page_checkout_id',
								'id'                => 'aveniadp_page_checkout_id',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Select a page', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_page_checkout_id', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'This page should contain the [aveniadp_checkout] shortcode.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_thank_you_page"><?php esc_html_e( 'Thank You Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_thank_you_page',
								'id'                => 'aveniadp_thank_you_page',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Use homepage with inline notice', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_thank_you_page', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Optional. After a successful purchase, customers will be redirected to this page with order details. If not set, the homepage with an inline thank-you notice is used.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Skip cart', 'avenia-digital-products' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="aveniadp_skip_cart_to_checkout" value="1" <?php checked( get_option( 'aveniadp_skip_cart_to_checkout', 'no' ), 'yes' ); ?> />
							<?php esc_html_e( 'After adding a product to the cart, redirect customers straight to the checkout page (bypassing the cart page).', 'avenia-digital-products' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Requires a valid Checkout Page selected above. The cart still stores items in the background.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_page_account_id"><?php esc_html_e( 'Account Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_page_account_id',
								'id'                => 'aveniadp_page_account_id',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Select a page', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_page_account_id', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'This page should contain the [aveniadp_account] shortcode to show customer account details.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_page_login_id"><?php esc_html_e( 'Login Page', 'avenia-digital-products' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'aveniadp_page_login_id',
								'id'                => 'aveniadp_page_login_id',
								'echo'              => 1,
								'show_option_none'  => esc_html__( 'Select a page', 'avenia-digital-products' ),
								'option_none_value' => '0',
								'selected'          => absint( get_option( 'aveniadp_page_login_id', 0 ) ),
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'This page should contain the [aveniadp_login] shortcode to provide a front-end login form.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php if ( 'payment' === $current_tab ) : ?>
			<?php
			$simulate_payments   = get_option( 'aveniadp_simulate_payments', 'yes' );
			$payment_environment = get_option( 'aveniadp_payment_environment', 'sandbox' );
			$stripe_enabled      = get_option( 'aveniadp_stripe_enabled', 'no' );
			$paypal_enabled      = get_option( 'aveniadp_paypal_enabled', 'no' );
			$is_simulating       = 'yes' === $simulate_payments;
			$is_live             = ! $is_simulating && 'live' === $payment_environment;
			$env_group_class     = static function( $env ) use ( $is_simulating, $payment_environment ) {
				if ( $is_simulating ) {
					return '';
				}

				return $env === $payment_environment ? ' is-active' : ' is-inactive';
			};
			?>
			<h2><?php esc_html_e( 'Payment Settings', 'avenia-digital-products' ); ?></h2>

			<div
				class="aveniadp-payment-status-card<?php echo $is_live ? ' aveniadp-payment-status-card--live' : ''; ?><?php echo $is_simulating ? ' aveniadp-payment-status-card--simulate' : ''; ?>"
				id="aveniadp-payment-status-card"
				data-title-simulate="<?php echo esc_attr( __( 'Simulated checkout', 'avenia-digital-products' ) ); ?>"
				data-desc-simulate="<?php echo esc_attr( __( 'Orders complete instantly. Nothing is charged and no payment provider is contacted.', 'avenia-digital-products' ) ); ?>"
				data-title-sandbox="<?php echo esc_attr( __( 'Test checkout (sandbox)', 'avenia-digital-products' ) ); ?>"
				data-desc-sandbox="<?php echo esc_attr( __( 'Customers pay through your enabled payment providers using test credentials. No real money is charged.', 'avenia-digital-products' ) ); ?>"
				data-title-live="<?php echo esc_attr( __( 'Live checkout', 'avenia-digital-products' ) ); ?>"
				data-desc-live="<?php echo esc_attr( __( 'Customers pay through your enabled payment providers using live credentials. Real money is charged.', 'avenia-digital-products' ) ); ?>"
			>
				<p class="aveniadp-payment-status-card__eyebrow"><?php esc_html_e( 'What customers experience at checkout right now', 'avenia-digital-products' ); ?></p>
				<p class="aveniadp-payment-status-card__title" id="aveniadp-payment-status-title"><?php echo esc_html( aveniadp_get_checkout_behavior_title() ); ?></p>
				<p class="aveniadp-payment-status-card__desc" id="aveniadp-payment-status-desc"><?php echo esc_html( aveniadp_get_checkout_behavior_description() ); ?></p>
			</div>

			<?php if ( ! $is_simulating && $is_live && ( aveniadp_stripe_keys_configured( 'live' ) || aveniadp_paypal_keys_configured( 'live' ) ) ) : ?>
				<div class="notice notice-warning inline aveniadp-payment-live-warning">
					<p>
						<strong><?php esc_html_e( 'You are accepting real payments.', 'avenia-digital-products' ); ?></strong>
						<?php esc_html_e( 'Customers can be charged real money. Confirm your live credentials and webhooks before promoting your store.', 'avenia-digital-products' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h3 class="aveniadp-payment-section-heading"><?php esc_html_e( 'Checkout mode', 'avenia-digital-products' ); ?></h3>
			<table class="form-table aveniadp-payment-settings">
				<tr>
					<th><label for="aveniadp_simulate_payments"><?php esc_html_e( 'Simulate payments', 'avenia-digital-products' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="aveniadp_simulate_payments" name="aveniadp_simulate_payments" value="1" <?php checked( $simulate_payments, 'yes' ); ?> />
							<?php esc_html_e( 'Skip payment processing (for testing)', 'avenia-digital-products' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Turn this on while building your store. Checkout completes without contacting a payment provider.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
				<tr class="aveniadp-payment-environment-row" <?php echo $is_simulating ? 'style="display:none;"' : ''; ?>>
					<th><?php esc_html_e( 'Real payments use', 'avenia-digital-products' ); ?></th>
					<td>
						<fieldset class="aveniadp-payment-environment-options">
							<label class="aveniadp-payment-environment-option">
								<input type="radio" name="aveniadp_payment_environment" value="sandbox" <?php checked( $payment_environment, 'sandbox' ); ?> />
								<span class="aveniadp-payment-environment-option__label"><?php esc_html_e( 'Test mode (sandbox)', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-environment-option__hint"><?php esc_html_e( 'Practice checkouts with test cards. No real charges.', 'avenia-digital-products' ); ?></span>
							</label>
							<label class="aveniadp-payment-environment-option">
								<input type="radio" name="aveniadp_payment_environment" value="live" <?php checked( $payment_environment, 'live' ); ?> />
								<span class="aveniadp-payment-environment-option__label"><?php esc_html_e( 'Live mode', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-environment-option__hint"><?php esc_html_e( 'Real customer payments. Money is actually charged.', 'avenia-digital-products' ); ?></span>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Only one credential set is used at a time. You can save both test and live keys below and switch here when you are ready.', 'avenia-digital-products' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3 class="aveniadp-payment-section-heading"><?php esc_html_e( 'Payment providers', 'avenia-digital-products' ); ?></h3>

			<div class="aveniadp-payment-providers<?php echo $is_simulating ? ' is-paused' : ''; ?>" id="aveniadp-payment-providers-body">
				<p class="aveniadp-payment-providers-paused-notice" id="aveniadp-payment-providers-paused-notice" <?php echo $is_simulating ? '' : 'hidden'; ?>>
					<?php esc_html_e( 'These settings are saved for later. Checkout ignores them until you turn off simulate payments.', 'avenia-digital-products' ); ?>
				</p>

				<h3 class="aveniadp-payment-gateway-heading"><?php esc_html_e( 'Stripe', 'avenia-digital-products' ); ?></h3>
					<table class="form-table aveniadp-payment-settings">
						<tr>
							<th><label for="aveniadp_stripe_enabled"><?php esc_html_e( 'Enable Stripe', 'avenia-digital-products' ); ?></label></th>
							<td>
								<input type="checkbox" id="aveniadp_stripe_enabled" name="aveniadp_stripe_enabled" value="1" <?php checked( $stripe_enabled, 'yes' ); ?> />
							</td>
						</tr>
						<tr class="aveniadp-stripe-settings aveniadp-payment-env-group<?php echo esc_attr( $env_group_class( 'sandbox' ) ); ?>" data-env="sandbox" style="<?php echo 'yes' === $stripe_enabled ? '' : 'display:none;'; ?>">
							<th>
								<?php esc_html_e( 'Test credentials', 'avenia-digital-products' ); ?>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--active" data-env-badge="sandbox"><?php esc_html_e( 'In use', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--stored" data-env-badge="sandbox"><?php esc_html_e( 'Saved', 'avenia-digital-products' ); ?></span>
							</th>
							<td class="aveniadp-payment-credential-group">
								<p class="aveniadp-payment-env-stored-note" data-env-note="sandbox"><?php esc_html_e( 'Saved for later. Switch to test mode above to use these credentials.', 'avenia-digital-products' ); ?></p>
								<p>
									<label for="aveniadp_stripe_test_secret_key"><?php esc_html_e( 'Secret key', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_stripe_test_secret_key', 'sk_test_...' ); ?>
								</p>
								<p>
									<label for="aveniadp_stripe_webhook_secret_test"><?php esc_html_e( 'Webhook signing secret', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_stripe_webhook_secret_test', 'whsec_...' ); ?>
								</p>
								<p class="description aveniadp-payment-webhook-help">
									<strong><?php esc_html_e( 'When do I need this?', 'avenia-digital-products' ); ?></strong>
									<?php esc_html_e( 'Optional if customers return to your site after checkout. Recommended as a backup if they close the browser early. If you register a webhook in Stripe, paste the matching signing secret here, otherwise Stripe events will be rejected. Use the webhook URL on the System Status page.', 'avenia-digital-products' ); ?>
								</p>
							</td>
						</tr>
						<tr class="aveniadp-stripe-settings aveniadp-payment-env-group<?php echo esc_attr( $env_group_class( 'live' ) ); ?>" data-env="live" style="<?php echo 'yes' === $stripe_enabled ? '' : 'display:none;'; ?>">
							<th>
								<?php esc_html_e( 'Live credentials', 'avenia-digital-products' ); ?>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--active" data-env-badge="live"><?php esc_html_e( 'In use', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--stored" data-env-badge="live"><?php esc_html_e( 'Saved', 'avenia-digital-products' ); ?></span>
							</th>
							<td class="aveniadp-payment-credential-group">
								<p class="aveniadp-payment-env-stored-note" data-env-note="live"><?php esc_html_e( 'Saved for later. Switch to live mode above to use these credentials.', 'avenia-digital-products' ); ?></p>
								<p>
									<label for="aveniadp_stripe_live_secret_key"><?php esc_html_e( 'Secret key', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_stripe_live_secret_key', 'sk_live_...' ); ?>
								</p>
								<p>
									<label for="aveniadp_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_stripe_webhook_secret', 'whsec_...' ); ?>
								</p>
								<p class="description aveniadp-payment-webhook-help">
									<strong><?php esc_html_e( 'When do I need this?', 'avenia-digital-products' ); ?></strong>
									<?php esc_html_e( 'Optional if customers return to your site after checkout. Recommended as a backup if they close the browser early. If you register a webhook in Stripe, paste the matching signing secret here, otherwise Stripe events will be rejected. Use the webhook URL on the System Status page.', 'avenia-digital-products' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<h3 class="aveniadp-payment-gateway-heading"><?php esc_html_e( 'PayPal', 'avenia-digital-products' ); ?></h3>
					<table class="form-table aveniadp-payment-settings">
						<tr>
							<th><label for="aveniadp_paypal_enabled"><?php esc_html_e( 'Enable PayPal', 'avenia-digital-products' ); ?></label></th>
							<td>
								<input type="checkbox" id="aveniadp_paypal_enabled" name="aveniadp_paypal_enabled" value="1" <?php checked( $paypal_enabled, 'yes' ); ?> />
							</td>
						</tr>
						<tr class="aveniadp-paypal-settings aveniadp-payment-env-group<?php echo esc_attr( $env_group_class( 'sandbox' ) ); ?>" data-env="sandbox" style="<?php echo 'yes' === $paypal_enabled ? '' : 'display:none;'; ?>">
							<th>
								<?php esc_html_e( 'Test credentials', 'avenia-digital-products' ); ?>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--active" data-env-badge="sandbox"><?php esc_html_e( 'In use', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--stored" data-env-badge="sandbox"><?php esc_html_e( 'Saved', 'avenia-digital-products' ); ?></span>
							</th>
							<td class="aveniadp-payment-credential-group">
								<p class="aveniadp-payment-env-stored-note" data-env-note="sandbox"><?php esc_html_e( 'Saved for later. Switch to test mode above to use these credentials.', 'avenia-digital-products' ); ?></p>
								<p>
									<label for="aveniadp_paypal_test_client_id"><?php esc_html_e( 'Client ID', 'avenia-digital-products' ); ?></label><br />
									<input type="text" id="aveniadp_paypal_test_client_id" name="aveniadp_paypal_test_client_id" value="<?php echo esc_attr( get_option( 'aveniadp_paypal_test_client_id', '' ) ); ?>" class="regular-text" />
								</p>
								<p>
									<label for="aveniadp_paypal_test_secret"><?php esc_html_e( 'Secret', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_paypal_test_secret' ); ?>
								</p>
							</td>
						</tr>
						<tr class="aveniadp-paypal-settings aveniadp-payment-env-group<?php echo esc_attr( $env_group_class( 'live' ) ); ?>" data-env="live" style="<?php echo 'yes' === $paypal_enabled ? '' : 'display:none;'; ?>">
							<th>
								<?php esc_html_e( 'Live credentials', 'avenia-digital-products' ); ?>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--active" data-env-badge="live"><?php esc_html_e( 'In use', 'avenia-digital-products' ); ?></span>
								<span class="aveniadp-payment-env-badge aveniadp-payment-env-badge--stored" data-env-badge="live"><?php esc_html_e( 'Saved', 'avenia-digital-products' ); ?></span>
							</th>
							<td class="aveniadp-payment-credential-group">
								<p class="aveniadp-payment-env-stored-note" data-env-note="live"><?php esc_html_e( 'Saved for later. Switch to live mode above to use these credentials.', 'avenia-digital-products' ); ?></p>
								<p>
									<label for="aveniadp_paypal_live_client_id"><?php esc_html_e( 'Client ID', 'avenia-digital-products' ); ?></label><br />
									<input type="text" id="aveniadp_paypal_live_client_id" name="aveniadp_paypal_live_client_id" value="<?php echo esc_attr( get_option( 'aveniadp_paypal_live_client_id', '' ) ); ?>" class="regular-text" />
								</p>
								<p>
									<label for="aveniadp_paypal_live_secret"><?php esc_html_e( 'Secret', 'avenia-digital-products' ); ?></label><br />
									<?php AVENIADP_Admin_Settings::render_secret_input( 'aveniadp_paypal_live_secret' ); ?>
								</p>
							</td>
						</tr>
					</table>
			</div>
		<?php endif; ?>

		<?php if ( 'emails' === $current_tab ) : ?>
			<?php
			$email_logo_id  = absint( get_option( 'aveniadp_email_logo', 0 ) );
			$email_logo_url = $email_logo_id ? wp_get_attachment_url( $email_logo_id ) : '';
			?>
			<h2><?php esc_html_e( 'Email Settings', 'avenia-digital-products' ); ?></h2>
			<p class="description"><?php esc_html_e( 'All emails use a shared layout with your logo and footer. Edit only the message body below. The logo and styling are applied automatically.', 'avenia-digital-products' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="aveniadp_email_from_name"><?php esc_html_e( 'From Name', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="text" id="aveniadp_email_from_name" name="aveniadp_email_from_name" value="<?php echo esc_attr( get_option( 'aveniadp_email_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_email_from_email"><?php esc_html_e( 'From Email', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="email" id="aveniadp_email_from_email" name="aveniadp_email_from_email" value="<?php echo esc_attr( get_option( 'aveniadp_email_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email Logo', 'avenia-digital-products' ); ?></th>
					<td>
						<input type="hidden" id="aveniadp_email_logo" name="aveniadp_email_logo" value="<?php echo esc_attr( $email_logo_id ); ?>" />
						<div id="aveniadp-email-logo-preview" class="aveniadp-email-logo-preview">
							<?php if ( $email_logo_url ) : ?>
								<img src="<?php echo esc_url( $email_logo_url ); ?>" alt="" style="max-width:160px;height:auto;" />
							<?php endif; ?>
						</div>
						<p>
							<button type="button" class="button aveniadp-upload-email-logo-button"><?php esc_html_e( 'Select Logo', 'avenia-digital-products' ); ?></button>
							<button type="button" class="button aveniadp-remove-email-logo-button" <?php echo $email_logo_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Logo', 'avenia-digital-products' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'Shown at the top of every email. If empty, your theme custom logo or site name is used.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Order Confirmation Email', 'avenia-digital-products' ); ?></th>
					<td>
						<p>
							<label>
								<input type="checkbox" name="aveniadp_email_order_confirmation_enabled" value="1" <?php checked( get_option( 'aveniadp_email_order_confirmation_enabled', true ), true ); ?> />
								<?php esc_html_e( 'Enable order confirmation email', 'avenia-digital-products' ); ?>
							</label>
						</p>
						<p>
							<label for="aveniadp_email_order_confirmation_subject"><?php esc_html_e( 'Subject', 'avenia-digital-products' ); ?></label><br />
							<input type="text" id="aveniadp_email_order_confirmation_subject" name="aveniadp_email_order_confirmation_subject" value="<?php echo esc_attr( get_option( 'aveniadp_email_order_confirmation_subject', __( 'Order Confirmation #{order_id}', 'avenia-digital-products' ) ) ); ?>" class="regular-text" />
							<span class="description"><?php esc_html_e( 'Placeholders: {order_id}', 'avenia-digital-products' ); ?></span>
						</p>
						<p>
							<label for="aveniadp_email_order_confirmation_message"><?php esc_html_e( 'Email Content', 'avenia-digital-products' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Placeholders: {order_id}, {order_total}, {order_items}, {product_titles}', 'avenia-digital-products' ); ?></span>
						</p>
						<?php aveniadp_render_email_settings_editor( 'aveniadp_email_order_confirmation_message', 'aveniadp_email_order_confirmation_message' ); ?>
						<?php aveniadp_render_email_test_send_button( 'order_confirmation', 'aveniadp_email_order_confirmation_subject', 'aveniadp_email_order_confirmation_message' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Admin New Order Email', 'avenia-digital-products' ); ?></th>
					<td>
						<p>
							<label>
								<input type="checkbox" name="aveniadp_email_admin_order_notification_enabled" value="1" <?php checked( get_option( 'aveniadp_email_admin_order_notification_enabled', true ), true ); ?> />
								<?php esc_html_e( 'Enable admin new order email', 'avenia-digital-products' ); ?>
							</label>
						</p>
						<p>
							<label for="aveniadp_email_admin_order_recipients"><?php esc_html_e( 'Recipient(s)', 'avenia-digital-products' ); ?></label><br />
							<input type="text" id="aveniadp_email_admin_order_recipients" name="aveniadp_email_admin_order_recipients" value="<?php echo esc_attr( get_option( 'aveniadp_email_admin_order_recipients', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
							<span class="description"><?php esc_html_e( 'Comma-separated email addresses. Defaults to the site admin email if empty.', 'avenia-digital-products' ); ?></span>
						</p>
						<p>
							<label for="aveniadp_email_admin_order_notification_subject"><?php esc_html_e( 'Subject', 'avenia-digital-products' ); ?></label><br />
							<input type="text" id="aveniadp_email_admin_order_notification_subject" name="aveniadp_email_admin_order_notification_subject" value="<?php echo esc_attr( get_option( 'aveniadp_email_admin_order_notification_subject', __( 'New Order #{order_id}', 'avenia-digital-products' ) ) ); ?>" class="regular-text" />
							<span class="description"><?php esc_html_e( 'Placeholders: {order_id}', 'avenia-digital-products' ); ?></span>
						</p>
						<p>
							<label for="aveniadp_email_admin_order_notification_message"><?php esc_html_e( 'Email Content', 'avenia-digital-products' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Placeholders: {order_id}, {order_total}, {order_items}, {product_titles}, {customer_name}, {customer_email}, {payment_method}, {view_order_button}, {view_order_url}', 'avenia-digital-products' ); ?></span>
						</p>
						<?php aveniadp_render_email_settings_editor( 'aveniadp_email_admin_order_notification_message', 'aveniadp_email_admin_order_notification_message' ); ?>
						<?php aveniadp_render_email_test_send_button( 'admin_order_notification', 'aveniadp_email_admin_order_notification_subject', 'aveniadp_email_admin_order_notification_message' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Account Confirmation Email', 'avenia-digital-products' ); ?></th>
					<td>
						<p>
							<label>
								<input type="checkbox" name="aveniadp_email_account_confirmation_enabled" value="1" <?php checked( get_option( 'aveniadp_email_account_confirmation_enabled', true ), true ); ?> />
								<?php esc_html_e( 'Enable account confirmation email', 'avenia-digital-products' ); ?>
							</label>
						</p>
						<p>
							<label for="aveniadp_email_account_confirmation_subject"><?php esc_html_e( 'Subject', 'avenia-digital-products' ); ?></label><br />
							<input type="text" id="aveniadp_email_account_confirmation_subject" name="aveniadp_email_account_confirmation_subject" value="<?php echo esc_attr( get_option( 'aveniadp_email_account_confirmation_subject', __( 'Welcome! Your account has been created', 'avenia-digital-products' ) ) ); ?>" class="regular-text" />
						</p>
						<p>
							<label for="aveniadp_email_account_confirmation_message"><?php esc_html_e( 'Email Content', 'avenia-digital-products' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Placeholders: {username}, {user_email}, {set_password_button}, {set_password_url}', 'avenia-digital-products' ); ?></span>
						</p>
						<?php aveniadp_render_email_settings_editor( 'aveniadp_email_account_confirmation_message', 'aveniadp_email_account_confirmation_message' ); ?>
						<?php aveniadp_render_email_test_send_button( 'account_confirmation', 'aveniadp_email_account_confirmation_subject', 'aveniadp_email_account_confirmation_message' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Download Link Email', 'avenia-digital-products' ); ?></th>
					<td>
						<p>
							<label>
								<input type="checkbox" name="aveniadp_email_download_link_enabled" value="1" <?php checked( get_option( 'aveniadp_email_download_link_enabled', true ), true ); ?> />
								<?php esc_html_e( 'Enable download link email', 'avenia-digital-products' ); ?>
							</label>
						</p>
						<p>
							<label for="aveniadp_email_download_link_expiration"><?php esc_html_e( 'Email Link Expiration (days)', 'avenia-digital-products' ); ?></label><br />
							<input type="number" id="aveniadp_email_download_link_expiration" name="aveniadp_email_download_link_expiration" value="<?php echo esc_attr( get_option( 'aveniadp_email_download_link_expiration', 2 ) ); ?>" min="1" step="1" class="small-text" />
							<span class="description"><?php esc_html_e( 'Emailed download links expire after this many days. Account downloads are not affected.', 'avenia-digital-products' ); ?></span>
						</p>
						<p>
							<label for="aveniadp_email_download_link_subject"><?php esc_html_e( 'Subject', 'avenia-digital-products' ); ?></label><br />
							<input type="text" id="aveniadp_email_download_link_subject" name="aveniadp_email_download_link_subject" value="<?php echo esc_attr( get_option( 'aveniadp_email_download_link_subject', __( 'Download: {product_title}', 'avenia-digital-products' ) ) ); ?>" class="regular-text" />
							<span class="description"><?php esc_html_e( 'Placeholder: {product_title}', 'avenia-digital-products' ); ?></span>
						</p>
						<p>
							<label for="aveniadp_email_download_link_message"><?php esc_html_e( 'Email Content', 'avenia-digital-products' ); ?></label><br />
							<span class="description"><?php esc_html_e( 'Placeholders: {product_title}, {download_url}, {download_button}, {download_expiration_days}, {account_url}, {account_button}', 'avenia-digital-products' ); ?></span>
						</p>
						<?php aveniadp_render_email_settings_editor( 'aveniadp_email_download_link_message', 'aveniadp_email_download_link_message' ); ?>
						<?php aveniadp_render_email_test_send_button( 'download_link', 'aveniadp_email_download_link_subject', 'aveniadp_email_download_link_message' ); ?>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php if ( 'appearance' === $current_tab ) : ?>
			<h2><?php esc_html_e( 'Appearance', 'avenia-digital-products' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Customize storefront buttons, account tabs, and borders. Text colors adjust automatically for contrast.', 'avenia-digital-products' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="aveniadp_appearance_button_bg"><?php esc_html_e( 'Button Background', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input
							type="text"
							id="aveniadp_appearance_button_bg"
							name="aveniadp_appearance_button_bg"
							class="aveniadp-color-picker"
							value="<?php echo esc_attr( $appearance['button_bg'] ); ?>"
							data-default-color="<?php echo esc_attr( $appearance_defaults['button_bg'] ); ?>"
						/>
						<p class="description"><?php esc_html_e( 'Primary action buttons on product, cart, checkout, and account pages.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_appearance_tab_active_bg"><?php esc_html_e( 'Active Tab Background', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input
							type="text"
							id="aveniadp_appearance_tab_active_bg"
							name="aveniadp_appearance_tab_active_bg"
							class="aveniadp-color-picker"
							value="<?php echo esc_attr( $appearance['tab_active_bg'] ); ?>"
							data-default-color="<?php echo esc_attr( $appearance_defaults['tab_active_bg'] ); ?>"
						/>
						<p class="description"><?php esc_html_e( 'Selected account tab, option pills, pagination highlight, and email CTA buttons.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_appearance_border_color"><?php esc_html_e( 'Border Color', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input
							type="text"
							id="aveniadp_appearance_border_color"
							name="aveniadp_appearance_border_color"
							class="aveniadp-color-picker"
							value="<?php echo esc_attr( $appearance['border_color'] ); ?>"
							data-default-color="<?php echo esc_attr( $appearance_defaults['border_color'] ); ?>"
						/>
						<p class="description"><?php esc_html_e( 'Borders on cards, tabs, form fields, and tables.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aveniadp_appearance_panel_bg"><?php esc_html_e( 'Cart & Checkout Background', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input
							type="text"
							id="aveniadp_appearance_panel_bg"
							name="aveniadp_appearance_panel_bg"
							class="aveniadp-color-picker"
							value="<?php echo esc_attr( $appearance['panel_bg'] ); ?>"
							data-default-color="<?php echo esc_attr( $appearance_defaults['panel_bg'] ); ?>"
						/>
						<p class="description"><?php esc_html_e( 'Background for the cart and checkout summary panels.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php submit_button( __( 'Save Settings', 'avenia-digital-products' ), 'primary', 'aveniadp_save_settings' ); ?>
	</form>
</div>
