<?php
/**
 * Login template.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$account_url = function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' );
if ( ! $account_url ) {
	$account_url = home_url( '/' );
}

// Prefer an explicit redirect_to (e.g. account download links); otherwise send users to their account page.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public login page; redirect target is sanitized and validated below.
$redirect_to = $account_url;
if ( ! empty( $_GET['redirect_to'] ) ) {
	$redirect_to = wp_validate_redirect( sanitize_url( wp_unslash( $_GET['redirect_to'] ) ), $account_url );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( is_user_logged_in() ) {
	wp_safe_redirect( $redirect_to );
	exit;
}
?>

<section class="aveniadp-login">
	<div class="entry-content">
		<form method="post" action="<?php echo esc_url( wp_login_url() ); ?>" class="aveniadp-login-form">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />

			<div class="aveniadp-login-fields">
				<p>
					<label for="aveniadp_user_login"><?php esc_html_e( 'Email', 'avenia-digital-products' ); ?></label>
					<input type="email" id="aveniadp_user_login" name="log" autocomplete="username" required />
				</p>

				<p>
					<label for="aveniadp_user_pass"><?php esc_html_e( 'Password', 'avenia-digital-products' ); ?></label>
					<input type="password" id="aveniadp_user_pass" name="pwd" autocomplete="current-password" required />
				</p>

				<p class="aveniadp-login-remember">
					<label class="aveniadp-login-remember-label">
						<input type="checkbox" name="rememberme" value="forever" />
						<span><?php esc_html_e( 'Remember me', 'avenia-digital-products' ); ?></span>
					</label>
				</p>
			</div>

			<button type="submit" class="button aveniadp-add-to-cart-btn aveniadp-login-submit">
				<span class="aveniadp-add-to-cart-label"><?php esc_html_e( 'Log In', 'avenia-digital-products' ); ?></span>
			</button>

			<p class="aveniadp-login-meta">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
					<?php esc_html_e( 'Forgot password?', 'avenia-digital-products' ); ?>
				</a>
			</p>
		</form>
	</div>
</section>
