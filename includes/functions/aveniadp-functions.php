<?php
/**
 * Helper functions.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed HTML tags/attributes for inline SVG icons and logos.
 *
 * @return array<string, array<string, bool>>
 * @since 1.0.0
 */
function aveniadp_get_allowed_svg_html() {
	return array(
		'svg'    => array(
			'class'           => true,
			'xmlns'           => true,
			'viewbox'         => true,
			'viewBox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'focusable'       => true,
		),
		'path'   => array(
			'd'    => true,
			'fill' => true,
		),
		'circle' => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
	);
}

/**
 * Whether WordPress is handling a REST API request.
 *
 * Uses the REST_REQUEST constant (available since WordPress 4.4) so we do not
 * depend on wp_is_serving_rest_request(), which requires WordPress 6.5+.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_serving_rest_request() {
	return defined( 'REST_REQUEST' ) && REST_REQUEST;
}

/**
 * Whether checkout completes without calling a payment provider.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_simulate_payments() {
	return 'yes' === get_option( 'aveniadp_simulate_payments', 'yes' );
}

/**
 * Active payment environment when simulate mode is off.
 *
 * @return string sandbox|live
 * @since 1.0.0
 */
function aveniadp_get_payment_environment() {
	$environment = get_option( 'aveniadp_payment_environment', 'sandbox' );

	if ( ! in_array( $environment, array( 'sandbox', 'live' ), true ) ) {
		return 'sandbox';
	}

	return $environment;
}

/**
 * Whether the active payment environment is sandbox.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_payment_sandbox() {
	return 'sandbox' === aveniadp_get_payment_environment();
}

/**
 * Whether the active payment environment is live.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_payment_live() {
	return 'live' === aveniadp_get_payment_environment();
}

/**
 * Short title describing what customers experience at checkout.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_checkout_behavior_title() {
	if ( aveniadp_is_simulate_payments() ) {
		return __( 'Simulated checkout', 'avenia-digital-products' );
	}

	if ( aveniadp_is_payment_live() ) {
		return __( 'Live checkout', 'avenia-digital-products' );
	}

	return __( 'Test checkout (sandbox)', 'avenia-digital-products' );
}

/**
 * One-sentence explanation of current checkout behavior.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_checkout_behavior_description() {
	if ( aveniadp_is_simulate_payments() ) {
		return __( 'Orders complete instantly. Nothing is charged and no payment provider is contacted.', 'avenia-digital-products' );
	}

	if ( aveniadp_is_payment_live() ) {
		return __( 'Customers pay through your enabled payment providers using live credentials. Real money is charged.', 'avenia-digital-products' );
	}

	return __( 'Customers pay through your enabled payment providers using test credentials. No real money is charged.', 'avenia-digital-products' );
}

/**
 * Plain-language label for a credential environment.
 *
 * @param string|null $environment sandbox|live|null for active environment.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_payment_environment_label( $environment = null ) {
	$environment = $environment ? $environment : aveniadp_get_payment_environment();

	if ( 'live' === $environment ) {
		return __( 'Live credentials', 'avenia-digital-products' );
	}

	return __( 'Test credentials (sandbox)', 'avenia-digital-products' );
}

/**
 * Whether Stripe secret key is configured for an environment.
 *
 * @param string|null $environment sandbox|live|null for active environment.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_stripe_keys_configured( $environment = null ) {
	$environment = $environment ? $environment : aveniadp_get_payment_environment();

	if ( 'live' === $environment ) {
		return '' !== get_option( 'aveniadp_stripe_live_secret_key', '' );
	}

	return '' !== get_option( 'aveniadp_stripe_test_secret_key', '' );
}

/**
 * Whether Stripe webhook secret is configured for an environment.
 *
 * @param string|null $environment sandbox|live|null for active environment.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_stripe_webhook_configured( $environment = null ) {
	$environment = $environment ? $environment : aveniadp_get_payment_environment();

	if ( 'live' === $environment ) {
		return '' !== get_option( 'aveniadp_stripe_webhook_secret', '' );
	}

	return '' !== get_option( 'aveniadp_stripe_webhook_secret_test', '' );
}

/**
 * Whether PayPal credentials are configured for an environment.
 *
 * @param string|null $environment sandbox|live|null for active environment.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_paypal_keys_configured( $environment = null ) {
	$environment = $environment ? $environment : aveniadp_get_payment_environment();

	if ( 'live' === $environment ) {
		return '' !== get_option( 'aveniadp_paypal_live_client_id', '' ) && '' !== get_option( 'aveniadp_paypal_live_secret', '' );
	}

	return '' !== get_option( 'aveniadp_paypal_test_client_id', '' ) && '' !== get_option( 'aveniadp_paypal_test_secret', '' );
}

/**
 * Build a dashboard URL for an order's gateway transaction ID.
 *
 * @param object $order Order row from aveniadp_orders.
 * @return string Empty when no link is available.
 * @since 1.0.2
 */
function aveniadp_get_order_transaction_url( $order ) {
	if ( empty( $order->transaction_id ) || empty( $order->payment_method ) ) {
		return '';
	}

	$transaction_id = sanitize_text_field( $order->transaction_id );
	$payment_method = sanitize_text_field( $order->payment_method );

	if ( 'stripe' === $payment_method ) {
		$base = aveniadp_is_payment_sandbox() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com';

		if ( 0 === strpos( $transaction_id, 'pi_' ) ) {
			return $base . '/payments/' . rawurlencode( $transaction_id );
		}

		if ( 0 === strpos( $transaction_id, 'cs_' ) ) {
			return $base . '/checkout/sessions/' . rawurlencode( $transaction_id );
		}

		return $base . '/search?query=' . rawurlencode( $transaction_id );
	}

	if ( 'paypal' === $payment_method ) {
		$base = aveniadp_is_payment_sandbox() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';

		return $base . '/activity/payment/' . rawurlencode( $transaction_id );
	}

	return '';
}

require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-tax-functions.php';
require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-template-functions.php';
require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-appearance-functions.php';
require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-upgrade-functions.php';

/**
 * Get the protected uploads service instance.
 *
 * @return AVENIADP_Protected_Uploads
 * @since 1.0.0
 */
function aveniadp_protected_uploads() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new AVENIADP_Protected_Uploads();
	}

	return $instance;
}

/**
 * Move a product download attachment into the protected directory.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_protect_product_attachment( $attachment_id ) {
	return aveniadp_protected_uploads()->protect_attachment( $attachment_id );
}


/**
 * Get product download source.
 *
 * @param int $product_id Product ID.
 * @return string upload|external
 * @since 1.0.0
 */
function aveniadp_get_product_file_source( $product_id ) {
	$source = get_post_meta( $product_id, '_aveniadp_file_source', true );
	if ( in_array( $source, array( 'upload', 'external' ), true ) ) {
		return $source;
	}

	return 'upload';
}

/**
 * Sanitize an external product download URL.
 *
 * @param string $url Raw URL.
 * @return string
 * @since 1.0.0
 */
function aveniadp_sanitize_product_external_file_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	return esc_url_raw( $url, array( 'https', 'http' ) );
}

/**
 * Get the configured external download URL for a product.
 *
 * @param int $product_id Product ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_product_external_file_url( $product_id ) {
	return aveniadp_sanitize_product_external_file_url( get_post_meta( $product_id, '_aveniadp_file_url', true ) );
}

/**
 * Whether a product has a valid download configured.
 *
 * @param int $product_id Product ID.
 * @return bool
 * @since 1.0.0
 */
function aveniadp_product_has_download_file( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return false;
	}

	if ( 'external' === aveniadp_get_product_file_source( $product_id ) ) {
		return '' !== aveniadp_get_product_external_file_url( $product_id );
	}

	$file_id = absint( get_post_meta( $product_id, '_aveniadp_file_id', true ) );
	if ( ! $file_id ) {
		return false;
	}

	$file_path = get_attached_file( $file_id );

	return $file_path && file_exists( $file_path );
}

/**
 * Get configured demo button data for a product.
 *
 * @param int $product_id Product ID.
 * @return array|null Array with text and url keys, or null when not configured.
 * @since 1.0.0
 */
function aveniadp_get_product_demo_button( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return null;
	}

	$url = aveniadp_sanitize_product_external_file_url( get_post_meta( $product_id, '_aveniadp_demo_button_url', true ) );
	if ( '' === $url ) {
		return null;
	}

	$text = get_post_meta( $product_id, '_aveniadp_demo_button_text', true );
	$text = is_string( $text ) ? trim( $text ) : '';
	if ( '' === $text ) {
		$text = __( 'View Demo', 'avenia-digital-products' );
	}

	return array(
		'text' => sanitize_text_field( $text ),
		'url'  => $url,
	);
}

/**
 * Render the product demo button when configured.
 *
 * @param int $product_id Product ID.
 * @since 1.0.0
 */
function aveniadp_render_product_demo_button( $product_id ) {
	$demo_button = aveniadp_get_product_demo_button( $product_id );
	if ( ! $demo_button ) {
		return;
	}

	printf(
		'<a href="%1$s" class="button aveniadp-demo-btn" target="_blank" rel="noopener noreferrer">%2$s</a>',
		esc_url( $demo_button['url'] ),
		esc_html( $demo_button['text'] )
	);
}

/**
 * Plugin service singletons.
 *
 * Bootstrapped in AVENIADP_Core::init_components() when the class registers WordPress hooks on
 * construction (see that method for the full list, including aveniadp_cache()). All other aveniadp_*()
 * accessors below are lazy: the instance is created on first call and has no constructor
 * side effects.
 */

/**
 * Get the page cache compatibility instance.
 *
 * Bootstrapped on `init` via AVENIADP_Core so exclusion filters are registered. Call
 * aveniadp_nocache() / AVENIADP_Cache::set_nocache() from handlers that may run earlier.
 *
 * @return AVENIADP_Cache
 * @since 1.0.0
 */
function aveniadp_cache() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Cache();
	}
	return $instance;
}

/**
 * Mark the current response as not page-cacheable.
 *
 * @param string $reason Short reason slug for logs / cache plugins.
 * @since 1.0.0
 */
function aveniadp_nocache( $reason = 'avenia-digital-products' ) {
	AVENIADP_Cache::set_nocache( $reason );
}

/**
 * Get the cart service instance.
 *
 * @return AVENIADP_Cart
 * @since 1.0.0
 */
function aveniadp_cart() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Cart();
	}
	return $instance;
}

/**
 * Get the checkout service instance.
 *
 * @return AVENIADP_Checkout
 * @since 1.0.0
 */
function aveniadp_checkout() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Checkout();
	}
	return $instance;
}

/**
 * Get the orders service instance.
 *
 * @return AVENIADP_Orders
 * @since 1.0.0
 */
function aveniadp_orders() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Orders();
	}
	return $instance;
}

/**
 * Get the access control service instance.
 *
 * @return AVENIADP_Access
 * @since 1.0.0
 */
function aveniadp_access() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Access();
	}
	return $instance;
}

/**
 * Get the customers service instance.
 *
 * @return AVENIADP_Customers
 * @since 1.0.0
 */
function aveniadp_customers() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Customers();
	}
	return $instance;
}

/**
 * Sanitize a customer display name.
 *
 * @param string $name Raw name input.
 * @return string
 * @since 1.0.2
 */
function aveniadp_sanitize_customer_name( $name ) {
	return sanitize_text_field( $name );
}

/**
 * Get a customer's stored display name.
 *
 * @param object|null $customer Customer row.
 * @return string
 * @since 1.0.2
 */
function aveniadp_get_customer_display_name( $customer ) {
	if ( ! $customer || empty( $customer->name ) ) {
		return '';
	}

	return aveniadp_sanitize_customer_name( $customer->name );
}

/**
 * Build common customer placeholders for transactional emails.
 *
 * @param object|null $customer Customer row.
 * @return array<string, string>
 * @since 1.0.2
 */
function aveniadp_get_customer_email_placeholders( $customer ) {
	$name = aveniadp_get_customer_display_name( $customer );

	return array(
		'customer_name'     => $name,
		'customer_greeting' => $name
			? sprintf(
				/* translators: %s: customer name */
				__( 'Hi %s,', 'avenia-digital-products' ),
				$name
			)
			: __( 'Hi,', 'avenia-digital-products' ),
	);
}

/**
 * Format a customer label for admin lists.
 *
 * @param object|null $customer Customer row.
 * @return string
 * @since 1.0.2
 */
function aveniadp_format_customer_label( $customer ) {
	if ( ! $customer ) {
		return '';
	}

	$name = aveniadp_get_customer_display_name( $customer );
	if ( $name ) {
		return sprintf(
			/* translators: 1: customer name, 2: email address */
			__( '%1$s (%2$s)', 'avenia-digital-products' ),
			$name,
			$customer->email
		);
	}

	return $customer->email;
}

/**
 * Format an order status slug for display.
 *
 * @param string $status Order status slug.
 * @return string
 * @since 1.0.0
 */
function aveniadp_format_order_status( $status ) {
	$status = sanitize_key( (string) $status );
	$labels = array(
		'pending'   => __( 'Pending', 'avenia-digital-products' ),
		'completed' => __( 'Completed', 'avenia-digital-products' ),
		'failed'    => __( 'Failed', 'avenia-digital-products' ),
	);

	if ( isset( $labels[ $status ] ) ) {
		return $labels[ $status ];
	}

	return $status ? ucfirst( $status ) : '';
}

/**
 * Format a payment method slug for display.
 *
 * @param string $payment_method Payment method slug.
 * @return string
 * @since 1.0.0
 */
function aveniadp_format_payment_method( $payment_method ) {
	$payment_method = sanitize_key( (string) $payment_method );
	$labels         = array(
		'manual'   => __( 'Manual', 'avenia-digital-products' ),
		'free'     => __( 'Free download', 'avenia-digital-products' ),
		'stripe'   => __( 'Stripe', 'avenia-digital-products' ),
		'paypal'   => __( 'PayPal', 'avenia-digital-products' ),
		'simulate' => __( 'Simulated', 'avenia-digital-products' ),
	);

	if ( isset( $labels[ $payment_method ] ) ) {
		return $labels[ $payment_method ];
	}

	return $payment_method ? ucfirst( $payment_method ) : '';
}

/**
 * Prefill checkout fields for logged-in users.
 *
 * @return array{email: string, name: string}
 * @since 1.0.2
 */
function aveniadp_get_checkout_prefill() {
	$prefill = array(
		'email' => '',
		'name'  => '',
	);

	if ( ! is_user_logged_in() ) {
		return $prefill;
	}

	$user = wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return $prefill;
	}

	$prefill['email'] = $user->user_email;

	$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
	$customer  = $customers->get_customer_by_email( $user->user_email );
	if ( $customer ) {
		$prefill['name'] = aveniadp_get_customer_display_name( $customer );
	}

	if ( '' === $prefill['name'] ) {
		$prefill['name'] = trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $prefill['name'] ) {
			$prefill['name'] = $user->display_name;
		}
	}

	return $prefill;
}

/**
 * Get the downloads service instance.
 *
 * @return AVENIADP_Downloads
 * @since 1.0.0
 */
function aveniadp_downloads() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Downloads();
	}
	return $instance;
}

/**
 * Get the payments service instance.
 *
 * @return AVENIADP_Payments
 * @since 1.0.0
 */
function aveniadp_payments() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Payments();
	}
	return $instance;
}

/**
 * Get the discounts service instance.
 *
 * @return AVENIADP_Discounts
 * @since 1.0.0
 */
function aveniadp_discounts() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Discounts();
	}
	return $instance;
}

/**
 * Get the emails service instance.
 *
 * @return AVENIADP_Emails
 * @since 1.0.0
 */
function aveniadp_emails() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Emails();
	}
	return $instance;
}

/**
 * Get the logo URL used in transactional emails.
 *
 * Uses the original attachment file (not a resized intermediate) so WebP/PNG
 * uploads are not replaced by WordPress-generated JPEGs.
 * Falls back to the theme custom logo when no plugin logo is set.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_email_logo_url() {
	$logo_id = absint( get_option( 'aveniadp_email_logo', 0 ) );
	if ( $logo_id ) {
		$url = wp_get_attachment_url( $logo_id );
		if ( $url ) {
			return $url;
		}
	}

	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$url = wp_get_attachment_url( $custom_logo_id );
		if ( $url ) {
			return $url;
		}
	}

	return '';
}

/**
 * Render a WYSIWYG editor for email content settings.
 *
 * @param string $option_name Stored option key.
 * @param string $editor_id   Unique editor element ID.
 * @since 1.0.0
 */
function aveniadp_render_email_settings_editor( $option_name, $editor_id ) {
	$content = AVENIADP_Emails::get_stored_email_content( $option_name );

	wp_editor(
		$content,
		$editor_id,
		array(
			'textarea_name' => $option_name,
			'textarea_rows' => 12,
			'media_buttons' => false,
			'teeny'         => false,
			'quicktags'     => true,
			'wpautop'       => true,
			'tinymce'       => array(
				'toolbar1' => 'formatselect,bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
			),
		)
	);
}

/**
 * Render a send-test-email button for an email settings section.
 *
 * @param string $email_type     Email type slug.
 * @param string $subject_field  Subject input ID/name.
 * @param string $message_field  Message editor field name.
 * @since 1.0.0
 */
function aveniadp_render_email_test_send_button( $email_type, $subject_field, $message_field ) {
	$user = wp_get_current_user();
	?>
	<p class="aveniadp-email-test-send">
		<button
			type="button"
			class="button aveniadp-send-test-email"
			data-email-type="<?php echo esc_attr( $email_type ); ?>"
			data-subject-field="<?php echo esc_attr( $subject_field ); ?>"
			data-message-field="<?php echo esc_attr( $message_field ); ?>"
		>
			<?php esc_html_e( 'Send test email', 'avenia-digital-products' ); ?>
		</button>
		<span class="aveniadp-send-test-email-status" aria-live="polite"></span>
		<?php if ( $user->exists() && is_email( $user->user_email ) ) : ?>
			<span class="description aveniadp-email-test-send-note">
				<?php
				printf(
					/* translators: %s: admin user email address */
					esc_html__( 'Sends to %s using sample placeholder data.', 'avenia-digital-products' ),
					esc_html( $user->user_email )
				);
				?>
			</span>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Get the logger instance.
 *
 * @return AVENIADP_Logger
 * @since 1.0.0
 */
function aveniadp_logger() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new AVENIADP_Logger();
	}
	return $instance;
}

/**
 * Default slug for single product and archive URLs.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_default_product_base_slug() {
	return 'digital-product';
}

/**
 * Get the configured product permalink base slug.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_product_base_slug() {
	$base = get_option( 'aveniadp_product_base', aveniadp_get_default_product_base_slug() );
	$base = sanitize_title( is_string( $base ) ? $base : '' );

	if ( '' === $base ) {
		$base = aveniadp_get_default_product_base_slug();
	}

	return $base;
}

/**
 * Get product gallery attachment IDs (excluding the featured image).
 *
 * @param int $product_id Product post ID.
 * @return int[]
 * @since 1.0.0
 */
function aveniadp_get_product_gallery_ids( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	if ( ! $product_id ) {
		return array();
	}

	$gallery = get_post_meta( $product_id, '_aveniadp_product_gallery', true );
	if ( empty( $gallery ) || ! is_string( $gallery ) ) {
		return array();
	}

	$ids = array();
	foreach ( explode( ',', $gallery ) as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
			$ids[] = $attachment_id;
		}
	}

	return $ids;
}

/**
 * Sanitize product gallery meta (comma-separated attachment IDs).
 *
 * @param mixed $value          Raw meta value.
 * @param string $meta_key      Meta key.
 * @param string $object_type   Object type.
 * @param string $object_subtype Object subtype.
 * @return string
 * @since 1.0.0
 */
function aveniadp_sanitize_product_gallery_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
	unset( $meta_key, $object_type, $object_subtype );

	return aveniadp_parse_product_gallery_ids_string( $value );
}

/**
 * Parse gallery meta into a comma-separated list of valid image attachment IDs.
 *
 * @param mixed $value Raw meta value.
 * @return string
 * @since 1.0.0
 */
function aveniadp_parse_product_gallery_ids_string( $value ) {
	$gallery_ids = array();

	if ( is_array( $value ) ) {
		$value = implode( ',', $value );
	}

	$value = is_string( $value ) ? $value : '';

	if ( '' !== $value ) {
		foreach ( explode( ',', $value ) as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) && ! in_array( $attachment_id, $gallery_ids, true ) ) {
				$gallery_ids[] = $attachment_id;
			}
		}
	}

	return implode( ',', $gallery_ids );
}

/**
 * Normalize gallery meta for a product (exclude featured image duplicate).
 *
 * @param mixed $value     Raw meta value.
 * @param int   $product_id Product post ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_normalize_product_gallery_meta( $value, $product_id ) {
	$gallery_ids = array_filter(
		array_map(
			'absint',
			explode( ',', aveniadp_parse_product_gallery_ids_string( $value ) )
		)
	);

	$product_id = absint( $product_id );
	if ( $product_id ) {
		$featured_id = (int) get_post_thumbnail_id( $product_id );
		if ( $featured_id ) {
			$gallery_ids = array_values( array_diff( $gallery_ids, array( $featured_id ) ) );
		}
	}

	return implode( ',', $gallery_ids );
}

/**
 * Ensure plugin product image sizes exist for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @since 1.0.0
 */
function aveniadp_regenerate_product_image_sizes( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	$needs    = ! is_array( $metadata )
		|| empty( $metadata['sizes']['aveniadp-product-card'] )
		|| empty( $metadata['sizes']['aveniadp-product-single'] )
		|| empty( $metadata['sizes']['aveniadp-product-pinned'] )
		|| empty( $metadata['sizes']['aveniadp-plugin-icon'] )
		|| empty( $metadata['sizes']['aveniadp-plugin-icon-2x'] );

	if ( ! $needs ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
	if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
}

/**
 * Get all product image IDs for display (featured image first, then gallery).
 *
 * @param int $product_id Product post ID.
 * @return int[]
 * @since 1.0.0
 */
function aveniadp_get_product_image_ids( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	if ( ! $product_id ) {
		return array();
	}

	$image_ids = array();

	if ( has_post_thumbnail( $product_id ) ) {
		$image_ids[] = (int) get_post_thumbnail_id( $product_id );
	}

	foreach ( aveniadp_get_product_gallery_ids( $product_id ) as $attachment_id ) {
		if ( ! in_array( $attachment_id, $image_ids, true ) ) {
			$image_ids[] = $attachment_id;
		}
	}

	return $image_ids;
}

/**
 * Render the single product image gallery.
 *
 * @param int $product_id Product post ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_product_gallery_html( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	$image_ids  = aveniadp_get_product_image_ids( $product_id );

	if ( empty( $image_ids ) ) {
		return '';
	}

	return aveniadp_get_template_html(
		'product-gallery.php',
		array(
			'product_id' => $product_id,
			'image_ids'  => $image_ids,
		)
	);
}

/**
 * Get permalink for a configured core page option.
 *
 * @param string $option_name   Option storing the page ID.
 * @param string $fallback_path Optional path appended to home URL when unset.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_page_url( $option_name, $fallback_path = '' ) {
	$page_id = absint( get_option( $option_name, 0 ) );

	if ( $page_id && get_post( $page_id ) ) {
		return get_permalink( $page_id );
	}

	if ( $fallback_path ) {
		return home_url( $fallback_path );
	}

	return '';
}

/**
 * Get cart page URL.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_cart_page_url() {
	return aveniadp_get_page_url( 'aveniadp_page_cart_id', '/cart/' );
}

/**
 * Get shop page ID.
 *
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_shop_page_id() {
	return absint( get_option( 'aveniadp_page_shop_id', 0 ) );
}

/**
 * Whether the current request is the configured shop page.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_is_shop_page() {
	$shop_id = aveniadp_get_shop_page_id();

	return $shop_id && is_page( $shop_id );
}

/**
 * Get checkout page URL.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_checkout_page_url() {
	return aveniadp_get_page_url( 'aveniadp_page_checkout_id', '/checkout/' );
}

/**
 * Get the checkout page ID from plugin settings.
 *
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_checkout_page_id() {
	return absint( get_option( 'aveniadp_page_checkout_id', 0 ) );
}

/**
 * Get the checkout page URL only when a page is selected in plugin settings.
 *
 * @return string Empty when no checkout page is configured or the page no longer exists.
 * @since 1.0.0
 */
function aveniadp_get_configured_checkout_page_url() {
	$page_id = aveniadp_get_checkout_page_id();
	if ( ! $page_id ) {
		return '';
	}

	$page = get_post( $page_id );
	if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
		return '';
	}

	$permalink = get_permalink( $page_id );

	return ( $permalink && ! is_wp_error( $permalink ) ) ? $permalink : '';
}

/**
 * Whether a product can use a direct checkout link.
 *
 * @param int   $product_id Product ID.
 * @param array $args {
 *     Optional. Link options to validate.
 *
 *     @type int|null $license_quantity License quantity from the pricing matrix.
 * }
 * @return bool
 * @since 1.0.0
 */
function aveniadp_product_supports_direct_checkout_link( $product_id, $args = array() ) {
	$product_id = absint( $product_id );
	if ( ! $product_id || AVENIADP_PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
		return false;
	}

	if ( '1' === get_post_meta( $product_id, '_aveniadp_free_download', true ) ) {
		return false;
	}

	if ( ! aveniadp_get_configured_checkout_page_url() ) {
		return false;
	}

	$pricing_matrix = function_exists( 'aveniadp_get_product_pricing_matrix' ) ? aveniadp_get_product_pricing_matrix( $product_id ) : array();
	if ( empty( $pricing_matrix ) || ! is_array( $pricing_matrix ) ) {
		return false;
	}

	$args = wp_parse_args(
		$args,
		array(
			'license_quantity' => null,
		)
	);

	if ( null === $args['license_quantity'] ) {
		$license_quantity = aveniadp_sanitize_license_quantity( array_key_first( $pricing_matrix ), 1 );
	} else {
		$license_quantity = aveniadp_sanitize_license_quantity( $args['license_quantity'], 1 );
	}

	if ( ! isset( $pricing_matrix[ (string) $license_quantity ] ) ) {
		return false;
	}

	$price = aveniadp_get_product_price( $product_id, $license_quantity );

	return ! is_wp_error( $price ) && $price > 0;
}

/**
 * Build a direct checkout URL that adds this product to the cart and redirects to checkout.
 *
 * The base URL is resolved from Settings → Pages → Checkout Page (not a hardcoded page ID).
 *
 * @param int   $product_id Product ID.
 * @param array $args {
 *     Optional. Link options.
 *
 *     @type int|null $license_quantity License quantity from the pricing matrix. Default first tier.
 * }
 * @return string Empty string when the link cannot be built.
 * @since 1.0.0
 */
function aveniadp_get_product_checkout_url( $product_id, $args = array() ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return '';
	}

	$checkout_url = aveniadp_get_configured_checkout_page_url();
	if ( ! $checkout_url ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'license_quantity' => null,
		)
	);

	if ( null === $args['license_quantity'] ) {
		$pricing_matrix = aveniadp_get_product_pricing_matrix( $product_id );
		if ( ! empty( $pricing_matrix ) && is_array( $pricing_matrix ) ) {
			$args['license_quantity'] = aveniadp_sanitize_license_quantity( array_key_first( $pricing_matrix ), 1 );
		} else {
			$args['license_quantity'] = 1;
		}
	} else {
		$args['license_quantity'] = aveniadp_sanitize_license_quantity( $args['license_quantity'], 1 );
	}

	if ( ! aveniadp_product_supports_direct_checkout_link( $product_id, $args ) ) {
		return '';
	}

	return add_query_arg(
		array(
			'aveniadp_buy'      => $product_id,
			'license_quantity' => $args['license_quantity'],
		),
		$checkout_url
	);
}

/**
 * Map direct checkout link error codes to user-facing messages.
 *
 * @param string $error_code Error code from aveniadp_buy handling.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_direct_checkout_error_message( $error_code ) {
	$messages = array(
		'invalid_product'          => __( 'This product is not available for purchase.', 'avenia-digital-products' ),
		'free_product'             => __( 'This product is offered as a free download.', 'avenia-digital-products' ),
		'already_in_cart'          => __( 'This product is already in your cart.', 'avenia-digital-products' ),
		'invalid_license_quantity' => __( 'The selected number of sites is not available for this product.', 'avenia-digital-products' ),
		'invalid_price'            => __( 'This product does not have a valid price configured.', 'avenia-digital-products' ),
	);

	$error_code = sanitize_key( $error_code );

	return isset( $messages[ $error_code ] )
		? $messages[ $error_code ]
		: __( 'Unable to add this product to the cart.', 'avenia-digital-products' );
}

/**
 * Get account page URL.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_account_page_url() {
	return aveniadp_get_page_url( 'aveniadp_page_account_id', '/account/' );
}

/**
 * Get login page URL.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_login_page_url() {
	return aveniadp_get_page_url( 'aveniadp_page_login_id', '/login/' );
}

/**
 * Whether free downloads require an email address before delivery.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_free_download_requires_email() {
	return (bool) get_option( 'aveniadp_free_download_require_email', false );
}

/**
 * Build a secure download URL for an access key.
 *
 * Uses pretty permalinks when available, otherwise a query arg fallback.
 *
 * @param string $access_key Download access key.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_download_url( $access_key ) {
	$access_key = sanitize_text_field( $access_key );
	if ( '' === $access_key ) {
		return '';
	}

	if ( get_option( 'permalink_structure' ) && aveniadp_download_pretty_urls_available() ) {
		return home_url( '/aveniadp-download/' . rawurlencode( $access_key ) . '/' );
	}

	return add_query_arg( 'aveniadp_download', $access_key, home_url( '/' ) );
}

/**
 * Whether stored rewrite rules include the download endpoint.
 *
 * @return bool
 * @since 1.0.0
 */
function aveniadp_download_pretty_urls_available() {
	$rules = get_option( 'rewrite_rules' );
	if ( ! is_array( $rules ) ) {
		return false;
	}

	foreach ( $rules as $pattern => $rewrite ) {
		if ( false !== strpos( $pattern, 'aveniadp-download' ) || false !== strpos( $rewrite, 'aveniadp_download' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Days before emailed download links expire.
 *
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_email_download_link_expiration_days() {
	return max( 0, absint( get_option( 'aveniadp_email_download_link_expiration', 2 ) ) );
}

/**
 * Build an authenticated account download URL.
 *
 * @param int $download_id Download ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_account_download_url( $download_id ) {
	$download_id = absint( $download_id );
	if ( ! $download_id ) {
		return '';
	}

	$nonce = wp_create_nonce( 'aveniadp-account-download-' . $download_id );

	if ( get_option( 'permalink_structure' ) ) {
		return add_query_arg(
			'nonce',
			$nonce,
			home_url( '/aveniadp-account-download/' . $download_id . '/' )
		);
	}

	return add_query_arg(
		array(
			'aveniadp_account_download' => $download_id,
			'nonce'                 => $nonce,
		),
		home_url( '/' )
	);
}

/**
 * Get base thank-you URL (without order ID).
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_thank_you_base_url() {
	$page_id = absint( get_option( 'aveniadp_thank_you_page', 0 ) );

	if ( $page_id && get_post( $page_id ) ) {
		$base = get_permalink( $page_id );
	} else {
		$base = home_url( '/' );
	}

	return add_query_arg(
		array(
			'aveniadp_thank_you' => '1',
		),
		$base
	);
}

/**
 * Create a one-time thank-you token for an order.
 *
 * @param int $order_id Order ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_create_thank_you_token( $order_id ) {
	$order_id = absint( $order_id );
	if ( ! $order_id ) {
		return '';
	}

	$token = wp_generate_password( 32, false );
	set_transient( 'aveniadp_thank_you_' . $token, $order_id, 30 * MINUTE_IN_SECONDS );

	return $token;
}

/**
 * Verify access to display the thank-you message for an order.
 *
 * @param int    $order_id Order ID.
 * @param string $token    Optional checkout redirect token (valid until expiry).
 * @return bool
 * @since 1.0.0
 */
function aveniadp_verify_thank_you_access( $order_id, $token = '' ) {
	$order_id = absint( $order_id );
	if ( ! $order_id ) {
		return false;
	}

	$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
	$order  = $orders->get_order( $order_id );

	if ( ! $order || 'completed' !== $order->status ) {
		return false;
	}

	$token = sanitize_text_field( $token );
	if ( '' !== $token ) {
		$stored_order_id = get_transient( 'aveniadp_thank_you_' . $token );
		if ( false !== $stored_order_id && (int) $stored_order_id === $order_id ) {
			return true;
		}
	}

	if ( is_user_logged_in() ) {
		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer( (int) $order->customer_id );
		if ( $customer && ! empty( $customer->user_id ) && (int) $customer->user_id === get_current_user_id() ) {
			return true;
		}
	}

	return false;
}

/**
 * Get thank-you URL for a specific order.
 *
 * @param int $order_id Order ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_thank_you_url( $order_id ) {
	$order_id = absint( $order_id );
	$url      = aveniadp_get_thank_you_base_url();

	if ( $order_id ) {
		$token = aveniadp_create_thank_you_token( $order_id );
		$url   = add_query_arg(
			array(
				'order_id'        => $order_id,
				'thank_you_token' => $token,
			),
			$url
		);
	}

	return $url;
}

/**
 * Get sanitized product info accordion sections.
 *
 * @param int $product_id Product post ID.
 * @return array<int, array{title: string, content: string}>
 * @since 1.0.0
 */
function aveniadp_get_product_info_sections( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	if ( ! $product_id ) {
		return array();
	}

	$sections = get_post_meta( $product_id, '_aveniadp_product_info_sections', true );
	if ( ! is_array( $sections ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		$title   = isset( $section['title'] ) ? sanitize_text_field( $section['title'] ) : '';
		$content = isset( $section['content'] ) ? (string) $section['content'] : '';

		if ( '' === $title && '' === trim( $content ) ) {
			continue;
		}

		$normalized[] = array(
			'title'   => $title,
			'content' => $content,
		);
	}

	return $normalized;
}

/**
 * Sanitize product info sections from admin request arrays.
 *
 * @param array $titles   Section titles.
 * @param array $contents Section markdown content.
 * @return array<int, array{title: string, content: string}>
 * @since 1.0.0
 */
function aveniadp_sanitize_product_info_sections_from_request( $titles, $contents ) {
	$sections = array();

	if ( ! is_array( $titles ) || ! is_array( $contents ) ) {
		return $sections;
	}

	$count = max( count( $titles ), count( $contents ) );
	for ( $i = 0; $i < $count; $i++ ) {
		$title   = isset( $titles[ $i ] ) ? sanitize_text_field( wp_unslash( $titles[ $i ] ) ) : '';
		$content = isset( $contents[ $i ] ) ? sanitize_textarea_field( wp_unslash( $contents[ $i ] ) ) : '';

		if ( '' === $title && '' === trim( $content ) ) {
			continue;
		}

		$sections[] = array(
			'title'   => $title,
			'content' => $content,
		);
	}

	return $sections;
}

/**
 * Parse inline Markdown (bold, italic, links).
 *
 * @param string $text Markdown text.
 * @return string Safe HTML fragment.
 * @since 1.0.0
 */
function aveniadp_parse_inline_product_markdown( $text ) {
	$text = esc_html( (string) $text );

	$text = preg_replace_callback(
		'/\[([^\]]+)\]\(([^)]+)\)/',
		static function ( $matches ) {
			$label = $matches[1];
			$url   = esc_url( $matches[2], array( 'http', 'https', 'mailto' ) );

			if ( ! $url ) {
				return $matches[0];
			}

			return '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
		},
		$text
	);

	$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text );

	return $text;
}

/**
 * Parse a limited Markdown subset for product info accordion content.
 *
 * Supported: paragraphs, **bold**, *italic*, [links](url), and - bullet lists.
 *
 * @param string $markdown Markdown source.
 * @return string Safe HTML.
 * @since 1.0.0
 */
function aveniadp_parse_product_markdown( $markdown ) {
	$markdown = trim( (string) $markdown );
	if ( '' === $markdown ) {
		return '';
	}

	$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
	$blocks   = preg_split( '/\n\s*\n/', $markdown );
	$output   = array();

	foreach ( $blocks as $block ) {
		$block = trim( $block );
		if ( '' === $block ) {
			continue;
		}

		$lines   = explode( "\n", $block );
		$is_list = true;

		foreach ( $lines as $line ) {
			if ( ! preg_match( '/^[-*]\s+/', $line ) ) {
				$is_list = false;
				break;
			}
		}

		if ( $is_list ) {
			$items = '';
			foreach ( $lines as $line ) {
				$line   = preg_replace( '/^[-*]\s+/', '', $line );
				$items .= '<li>' . aveniadp_parse_inline_product_markdown( $line ) . '</li>';
			}
			$output[] = '<ul>' . $items . '</ul>';
			continue;
		}

		$paragraph_lines = array();
		foreach ( $lines as $line ) {
			$paragraph_lines[] = aveniadp_parse_inline_product_markdown( $line );
		}

		$output[] = '<p>' . implode( '<br>', $paragraph_lines ) . '</p>';
	}

	return wp_kses_post( implode( "\n", $output ) );
}

/**
 * Render the single product info accordion.
 *
 * @param int $product_id Product post ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_product_info_sections_html( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	$sections   = aveniadp_get_product_info_sections( $product_id );

	if ( empty( $sections ) ) {
		return '';
	}

	return aveniadp_get_template_html(
		'product-info-sections.php',
		array(
			'product_id' => $product_id,
			'sections'   => $sections,
		)
	);
}

/**
 * Maximum related products shown on the single product page.
 *
 * @return int
 * @since 1.0.0
 */
function aveniadp_get_max_related_products() {
	return 6;
}

/**
 * Get manually selected related product IDs for a product.
 *
 * @param int $product_id Product post ID.
 * @return int[]
 * @since 1.0.0
 */
function aveniadp_get_related_product_ids( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	if ( ! $product_id ) {
		return array();
	}

	$related = get_post_meta( $product_id, '_aveniadp_related_products', true );
	if ( empty( $related ) || ! is_string( $related ) ) {
		return array();
	}

	$ids = array();
	foreach ( explode( ',', $related ) as $related_id ) {
		$related_id = absint( $related_id );
		if ( $related_id && AVENIADP_PRODUCT_POST_TYPE === get_post_type( $related_id ) ) {
			$ids[] = $related_id;
		}
	}

	return $ids;
}

/**
 * Sanitize related products meta (comma-separated product IDs).
 *
 * @param mixed  $value          Raw meta value.
 * @param string $meta_key       Meta key.
 * @param string $object_type    Object type.
 * @param string $object_subtype Object subtype.
 * @return string
 * @since 1.0.0
 */
function aveniadp_sanitize_related_products_meta( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
	unset( $meta_key, $object_type, $object_subtype );

	return aveniadp_parse_related_products_ids_string( $value );
}

/**
 * Parse related products meta into a comma-separated list of valid product IDs.
 *
 * @param mixed $value Raw meta value.
 * @return string
 * @since 1.0.0
 */
function aveniadp_parse_related_products_ids_string( $value ) {
	$product_ids = array();

	if ( is_array( $value ) ) {
		$value = implode( ',', $value );
	}

	$value = is_string( $value ) ? $value : '';

	if ( '' !== $value ) {
		foreach ( explode( ',', $value ) as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id && AVENIADP_PRODUCT_POST_TYPE === get_post_type( $product_id ) && ! in_array( $product_id, $product_ids, true ) ) {
				$product_ids[] = $product_id;
			}
		}
	}

	return implode( ',', $product_ids );
}

/**
 * Normalize related products meta for a product (exclude self, cap count).
 *
 * @param mixed $value      Raw meta value.
 * @param int   $product_id Product post ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_normalize_related_products_meta( $value, $product_id ) {
	$product_ids = array_filter(
		array_map(
			'absint',
			explode( ',', aveniadp_parse_related_products_ids_string( $value ) )
		)
	);

	$product_id = absint( $product_id );
	$max        = aveniadp_get_max_related_products();

	if ( $product_id ) {
		$product_ids = array_values(
			array_filter(
				$product_ids,
				function ( $related_id ) use ( $product_id ) {
					return $related_id !== $product_id;
				}
			)
		);
	}

	if ( count( $product_ids ) > $max ) {
		$product_ids = array_slice( $product_ids, 0, $max );
	}

	return implode( ',', $product_ids );
}

/**
 * Get products for the related-products admin picker.
 *
 * @param int $exclude_id Product ID to exclude (usually the current product).
 * @return WP_Post[]
 * @since 1.0.0
 */
function aveniadp_get_products_for_related_picker( $exclude_id = 0 ) {
	$exclude_id = absint( $exclude_id );

	$args = array(
		'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
		'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page'         => -1,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	if ( $exclude_id ) {
		$args['post__not_in'] = array( $exclude_id ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Small admin picker list; excluding current product only.
	}

	return get_posts( $args );
}

/**
 * Get related product IDs to display on the single product page.
 *
 * Uses manual selections when set; otherwise falls back to same-category products.
 *
 * @param int $product_id Product post ID.
 * @return int[]
 * @since 1.0.0
 */
function aveniadp_get_related_product_ids_for_display( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	if ( ! $product_id ) {
		return array();
	}

	$max   = aveniadp_get_max_related_products();
	$manual = aveniadp_get_related_product_ids( $product_id );

	if ( ! empty( $manual ) ) {
		$ids = array();
		foreach ( $manual as $related_id ) {
			if ( 'publish' !== get_post_status( $related_id ) ) {
				continue;
			}
			$ids[] = $related_id;
			if ( count( $ids ) >= $max ) {
				break;
			}
		}
		return $ids;
	}

	$categories = get_the_terms( $product_id, 'aveniadp_product_category' );
	if ( empty( $categories ) || is_wp_error( $categories ) ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $max,
			'post__not_in'           => array( $product_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Related products fallback excludes the current product only.
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Related products matched by shared category on single product pages.
				array(
					'taxonomy' => 'aveniadp_product_category',
					'field'    => 'term_id',
					'terms'    => array( (int) $categories[0]->term_id ),
				),
			),
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Render the related products section for a single product.
 *
 * @param int $product_id Product post ID.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_related_products_html( $product_id = 0 ) {
	$product_id = $product_id ? absint( $product_id ) : get_the_ID();
	$ids        = aveniadp_get_related_product_ids_for_display( $product_id );

	if ( empty( $ids ) ) {
		return '';
	}

	$query = new WP_Query(
		array(
			'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => aveniadp_get_max_related_products(),
			'post__in'               => $ids,
			'orderby'                => 'post__in',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	if ( ! $query->have_posts() ) {
		return '';
	}

	return aveniadp_get_template_html(
		'related-products.php',
		array(
			'product_id' => $product_id,
			'query'      => $query,
		)
	);
}

require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-pricing-functions.php';
require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-catalog-functions.php';
require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-cart-icon-block.php';

