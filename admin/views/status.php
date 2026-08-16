<?php
/**
 * System status view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$stripe_enabled = get_option( 'aveniadp_stripe_enabled', 'no' );
$paypal_enabled = get_option( 'aveniadp_paypal_enabled', 'no' );
$home_url       = home_url( '/' );
$is_simulating  = aveniadp_is_simulate_payments();
$environment    = $is_simulating ? null : aveniadp_get_payment_environment();

?>

<div class="wrap aveniadp-system-status">
	<h1><?php esc_html_e( 'System Status', 'avenia-digital-products' ); ?></h1>

	<h2><?php esc_html_e( 'Environment', 'avenia-digital-products' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Plugin', 'avenia-digital-products' ); ?></th>
				<td><?php echo esc_html( AVENIADP_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Checkout mode', 'avenia-digital-products' ); ?></th>
				<td>
					<strong><?php echo esc_html( aveniadp_get_checkout_behavior_title() ); ?></strong>
					<p class="description" style="margin: 0.35em 0 0;">
						<?php echo esc_html( aveniadp_get_checkout_behavior_description() ); ?>
						<?php
						echo ' ';
						echo wp_kses(
							sprintf(
								/* translators: %s: linked Settings → Payment label */
								__( 'Change this under %s.', 'avenia-digital-products' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=aveniadp-settings&tab=payment' ) ) . '">' . esc_html__( 'Settings → Payment', 'avenia-digital-products' ) . '</a>'
							),
							array(
								'a' => array(
									'href' => true,
								),
							)
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Stripe Webhook URL', 'avenia-digital-products' ); ?></th>
				<td><code><?php echo esc_html( rest_url( 'avenia-digital-products/v1/stripe-webhook' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Page cache', 'avenia-digital-products' ); ?></th>
				<td>
					<?php esc_html_e( 'Cart, checkout, account, login, thank-you, download, and payment-return URLs are excluded from full-page caching. Catalog pages remain cacheable; cart count and drawer load via AJAX.', 'avenia-digital-products' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Protected uploads', 'avenia-digital-products' ); ?></th>
				<td>
					<?php
					$protected_dir     = AVENIADP_Protected_Uploads::ensure_directory();
					$htaccess_ok       = $protected_dir && AVENIADP_Protected_Uploads::directory_has_deny_htaccess( $protected_dir );
					$server_software   = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
					$is_nginx          = false !== stripos( $server_software, 'nginx' );
					$probe             = AVENIADP_Protected_Uploads::probe_direct_access();
					$nginx_snippet     = AVENIADP_Protected_Uploads::get_nginx_deny_snippet();
					$show_nginx_help   = $is_nginx || 'exposed' === $probe['status'];
					?>
					<?php if ( $protected_dir ) : ?>
						<code><?php echo esc_html( $protected_dir ); ?></code>
					<?php else : ?>
						<br><strong><?php esc_html_e( 'Protected uploads directory could not be created.', 'avenia-digital-products' ); ?></strong>
					<?php endif; ?>
					<?php if ( $htaccess_ok ) : ?>
						<br><?php esc_html_e( 'Apache .htaccess deny-all rules are present.', 'avenia-digital-products' ); ?>
					<?php else : ?>
						<br><strong><?php esc_html_e( 'Apache .htaccess deny-all rules are missing.', 'avenia-digital-products' ); ?></strong>
						<?php esc_html_e( 'Re-save a product file, or add deny-all rules to the protected folder. A file that only blocks .php is not enough.', 'avenia-digital-products' ); ?>
					<?php endif; ?>
					<?php if ( 'blocked' === $probe['status'] ) : ?>
						<br><?php echo esc_html( $probe['message'] ); ?>
					<?php elseif ( 'exposed' === $probe['status'] ) : ?>
						<br><strong><?php echo esc_html( $probe['message'] ); ?></strong>
					<?php else : ?>
						<br><?php echo esc_html( $probe['message'] ); ?>
					<?php endif; ?>
					<?php if ( $show_nginx_help ) : ?>
						<p style="margin: 0.75em 0 0.35em;">
							<strong><?php esc_html_e( 'Nginx', 'avenia-digital-products' ); ?></strong>
							-
							<?php esc_html_e( '.htaccess is ignored when Nginx serves static files. Add this to your server block (or ask your host to block the folder):', 'avenia-digital-products' ); ?>
						</p>
						<pre style="max-width: 48rem; overflow: auto; padding: 0.75em 1em; background: #f6f7f7; border: 1px solid #dcdcde;"><?php echo esc_html( $nginx_snippet ); ?></pre>
						<p class="description" style="margin: 0.35em 0 0;">
							<?php esc_html_e( 'Authorized downloads still work through the plugin download endpoint; only direct static URL access is blocked.', 'avenia-digital-products' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Download endpoint', 'avenia-digital-products' ); ?></th>
				<td>
					<code><?php echo esc_html( trailingslashit( $home_url ) . 'aveniadp-download/{access_key}/' ); ?></code>
					<p class="description" style="margin: 0.35em 0 0;">
						<?php esc_html_e( 'URL pattern for secure customer download links (for reference only). The plugin fills in {access_key} automatically; you do not configure this URL anywhere.', 'avenia-digital-products' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Stripe', 'avenia-digital-products' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Enabled', 'avenia-digital-products' ); ?></th>
				<td><?php echo 'yes' === $stripe_enabled ? esc_html__( 'Yes', 'avenia-digital-products' ) : esc_html__( 'No', 'avenia-digital-products' ); ?></td>
			</tr>
			<?php if ( $is_simulating ) : ?>
				<tr>
					<th><?php esc_html_e( 'In use at checkout', 'avenia-digital-products' ); ?></th>
					<td><?php esc_html_e( 'No — simulate payments is on', 'avenia-digital-products' ); ?></td>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'Credentials in use', 'avenia-digital-products' ); ?></th>
					<td>
						<?php echo esc_html( aveniadp_get_payment_environment_label() ); ?>
						—
						<?php echo aveniadp_stripe_keys_configured() ? esc_html__( 'keys configured', 'avenia-digital-products' ) : esc_html__( 'keys missing', 'avenia-digital-products' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhooks in use', 'avenia-digital-products' ); ?></th>
					<td><?php echo aveniadp_stripe_webhook_configured() ? esc_html__( 'Configured', 'avenia-digital-products' ) : esc_html__( 'Not configured', 'avenia-digital-products' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Other credentials saved', 'avenia-digital-products' ); ?></th>
					<td>
						<?php
						$inactive_env = 'live' === $environment ? 'sandbox' : 'live';
						echo aveniadp_stripe_keys_configured( $inactive_env )
							? esc_html( sprintf(
								/* translators: %s: credential environment label */
								__( 'Yes — %s (not used until you switch)', 'avenia-digital-products' ),
								aveniadp_get_payment_environment_label( $inactive_env )
							) )
							: esc_html__( 'No', 'avenia-digital-products' );
						?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'PayPal', 'avenia-digital-products' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Enabled', 'avenia-digital-products' ); ?></th>
				<td><?php echo 'yes' === $paypal_enabled ? esc_html__( 'Yes', 'avenia-digital-products' ) : esc_html__( 'No', 'avenia-digital-products' ); ?></td>
			</tr>
			<?php if ( $is_simulating ) : ?>
				<tr>
					<th><?php esc_html_e( 'In use at checkout', 'avenia-digital-products' ); ?></th>
					<td><?php esc_html_e( 'No — simulate payments is on', 'avenia-digital-products' ); ?></td>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'Credentials in use', 'avenia-digital-products' ); ?></th>
					<td>
						<?php echo esc_html( aveniadp_get_payment_environment_label() ); ?>
						—
						<?php echo aveniadp_paypal_keys_configured() ? esc_html__( 'keys configured', 'avenia-digital-products' ) : esc_html__( 'keys missing', 'avenia-digital-products' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Other credentials saved', 'avenia-digital-products' ); ?></th>
					<td>
						<?php
						$inactive_env = 'live' === $environment ? 'sandbox' : 'live';
						echo aveniadp_paypal_keys_configured( $inactive_env )
							? esc_html( sprintf(
								/* translators: %s: credential environment label */
								__( 'Yes — %s (not used until you switch)', 'avenia-digital-products' ),
								aveniadp_get_payment_environment_label( $inactive_env )
							) )
							: esc_html__( 'No', 'avenia-digital-products' );
						?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
