<?php
/**
 * Stripe payment gateway.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe gateway class.
 *
 * @since 1.0.0
 */
class AVENIADP_Stripe {

	/**
	 * Stripe API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Default webhook timestamp tolerance (seconds).
	 *
	 * @var int
	 */
	const WEBHOOK_TOLERANCE = 300;

	/**
	 * Initialize Stripe gateway.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		add_action( 'template_redirect', array( $this, 'handle_payment_return' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Currencies Stripe treats as zero-decimal (amount is the major unit).
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_zero_decimal_currency( $currency ) {
		$zero_decimal = array(
			'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
			'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
		);

		return in_array( strtolower( $currency ), $zero_decimal, true );
	}

	/**
	 * Convert a major-unit amount to Stripe's integer amount for the currency.
	 *
	 * @param float  $amount   Amount in major currency units.
	 * @param string $currency Currency code.
	 * @return int
	 * @since 1.0.0
	 */
	private function to_stripe_amount( $amount, $currency ) {
		if ( $this->is_zero_decimal_currency( $currency ) ) {
			return (int) round( floatval( $amount ) );
		}

		return (int) round( floatval( $amount ) * 100 );
	}

	/**
	 * Convert Stripe's integer amount to major currency units.
	 *
	 * @param int|float $amount   Stripe amount (cents or major units for zero-decimal).
	 * @param string    $currency Currency code.
	 * @return float
	 * @since 1.0.0
	 */
	private function from_stripe_amount( $amount, $currency ) {
		if ( $this->is_zero_decimal_currency( $currency ) ) {
			return floatval( $amount );
		}

		return floatval( $amount ) / 100;
	}

	/**
	 * Scale one-time Checkout line items so their totals equal the order total.
	 *
	 * Used when a cart discount reduces due-today below the sum of line prices.
	 *
	 * @param array  $line_items   Stripe line items (price_data based).
	 * @param float  $target_total Discounted order total in major currency units.
	 * @param string $currency     Currency code.
	 * @return array|WP_Error
	 */
	private function align_payment_line_items_to_total( $line_items, $target_total, $currency ) {
		$target_amount = $this->to_stripe_amount( $target_total, $currency );
		$indices       = array();
		$sum           = 0;

		foreach ( $line_items as $index => $line_item ) {
			if ( ! isset( $line_item['price_data']['unit_amount'] ) ) {
				continue;
			}

			$quantity = isset( $line_item['quantity'] ) ? max( 1, absint( $line_item['quantity'] ) ) : 1;
			$sum     += (int) $line_item['price_data']['unit_amount'] * $quantity;
			$indices[] = $index;
		}

		if ( empty( $indices ) ) {
			return $line_items;
		}

		if ( $target_amount < 1 ) {
			return new WP_Error(
				'stripe_invalid_amount',
				__( 'Discounted order total is too low to charge with Stripe.', 'avenia-digital-products' )
			);
		}

		if ( $sum === $target_amount ) {
			return $line_items;
		}

		if ( $sum < 1 ) {
			return new WP_Error( 'stripe_invalid_amount', __( 'Invalid order amount.', 'avenia-digital-products' ) );
		}

		$allocated = 0;
		$last      = count( $indices ) - 1;

		foreach ( $indices as $n => $index ) {
			$quantity       = isset( $line_items[ $index ]['quantity'] ) ? max( 1, absint( $line_items[ $index ]['quantity'] ) ) : 1;
			$original_total = (int) $line_items[ $index ]['price_data']['unit_amount'] * $quantity;

			if ( $n === $last ) {
				$new_total = $target_amount - $allocated;
			} else {
				$new_total  = (int) floor( ( $original_total * $target_amount ) / $sum );
				$allocated += $new_total;
			}

			if ( $new_total < 1 ) {
				return new WP_Error(
					'stripe_invalid_amount',
					__( 'Discounted order total is too low to charge with Stripe.', 'avenia-digital-products' )
				);
			}

			$line_items[ $index ]['price_data']['unit_amount'] = (int) round( $new_total / $quantity );
		}

		return $line_items;
	}

	/**
	 * Output admin notices for common Stripe configuration issues.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( aveniadp_is_simulate_payments() ) {
			return;
		}

		if ( 'yes' !== get_option( 'aveniadp_stripe_enabled', 'no' ) ) {
			return;
		}

		if ( '' === $this->get_secret_key() ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Avenia Digital Products: Stripe is enabled but the secret key is not configured for the active payment environment.', 'avenia-digital-products' );
			echo '</p></div>';
		}
	}

	/**
	 * Get secret key for the active payment environment.
	 *
	 * @return string
	 */
	private function get_secret_key() {
		if ( aveniadp_is_payment_sandbox() ) {
			return get_option( 'aveniadp_stripe_test_secret_key', '' );
		}

		return get_option( 'aveniadp_stripe_live_secret_key', '' );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_enabled() {
		return 'yes' === get_option( 'aveniadp_stripe_enabled', 'no' );
	}

	/**
	 * Get gateway title.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title() {
		return __( 'Stripe', 'avenia-digital-products' );
	}

	/**
	 * Process payment.
	 *
	 * @param array $order_data Order data.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_payment( $order_data ) {
		$secret = $this->get_secret_key();
		if ( empty( $secret ) ) {
			return new WP_Error( 'stripe_no_key', __( 'Stripe secret key is not configured.', 'avenia-digital-products' ) );
		}

		$checkout_url = $this->create_checkout_session( $order_data );
		if ( is_wp_error( $checkout_url ) ) {
			return $checkout_url;
		}

		return array( 'redirect' => $checkout_url );
	}

	/**
	 * Create Stripe Checkout session (live).
	 *
	 * @param array $order_data Order data.
	 * @return string|WP_Error Checkout URL or error.
	 * @since 1.0.0
	 */
	private function create_checkout_session( $order_data ) {
		$order_id = isset( $order_data['order_id'] ) ? absint( $order_data['order_id'] ) : 0;
		$currency = strtolower( get_option( 'aveniadp_currency', 'USD' ) );
		$items    = isset( $order_data['items'] ) && is_array( $order_data['items'] ) ? $order_data['items'] : array();

		if ( empty( $items ) ) {
			return new WP_Error( 'stripe_no_items', __( 'No items in order.', 'avenia-digital-products' ) );
		}

		$line_items = array();

		// Build line items.
		// Pricing matrix amounts are tier totals (e.g. 5 sites = $49), so Stripe quantity is always 1.
		foreach ( $items as $item ) {
			$product_id       = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$price            = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
			$license_quantity = isset( $item['license_quantity'] ) ? max( 1, absint( $item['license_quantity'] ) ) : 1;
			$name             = $product_id ? get_the_title( $product_id ) : __( 'Product', 'avenia-digital-products' );

			if ( empty( $name ) ) {
				$name = __( 'Product', 'avenia-digital-products' );
			}

			if ( $license_quantity > 1 && function_exists( 'aveniadp_format_license_quantity_label' ) ) {
				$name = sprintf(
					/* translators: 1: product name, 2: license quantity label (e.g. "5 sites") */
					__( '%1$s — %2$s', 'avenia-digital-products' ),
					$name,
					aveniadp_format_license_quantity_label( $license_quantity )
				);
			}

			$unit_amount = $this->to_stripe_amount( $price, $currency );
			if ( $unit_amount < 1 ) {
				continue;
			}

			$line_items[] = array(
				'price_data' => array(
					'currency'     => $currency,
					'product_data' => array( 'name' => $name ),
					'unit_amount'  => $unit_amount,
				),
				'quantity'   => 1,
			);
		}

		if ( empty( $line_items ) ) {
			return new WP_Error( 'stripe_invalid_amount', __( 'Invalid order amount.', 'avenia-digital-products' ) );
		}

		// Prefer the stored order total — this already includes any cart discount.
		$orders       = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order        = $order_id ? $orders->get_order( $order_id ) : null;
		$target_total = $order ? floatval( $order->total ) : ( isset( $order_data['total'] ) ? floatval( $order_data['total'] ) : 0.0 );

		// Scale line unit amounts so Stripe charges the discounted order total.
		$aligned = $this->align_payment_line_items_to_total( $line_items, $target_total, $currency );
		if ( is_wp_error( $aligned ) ) {
			return $aligned;
		}
		$line_items = $aligned;

		$success_url = add_query_arg(
			array(
				'aveniadp_payment_return' => '1',
				'gateway'           => 'stripe',
				'order_id'          => $order_id,
				'session_id'        => '{CHECKOUT_SESSION_ID}',
			),
			home_url( '/' )
		);
		$cancel_url  = add_query_arg(
			array(
				'aveniadp_payment_cancelled' => '1',
				'order_id'             => $order_id,
			),
			home_url( '/' )
		);

		$body = array(
			'mode'                => 'payment',
			'success_url'         => $success_url,
			'cancel_url'          => $cancel_url,
			'client_reference_id' => (string) $order_id,
			'line_items'          => $line_items,
		);

		// Prefill Stripe Checkout with the email from our checkout form (or logged-in user).
		$customer_id = isset( $order_data['customer_id'] ) ? absint( $order_data['customer_id'] ) : 0;
		if ( ! $customer_id && $order && ! empty( $order->customer_id ) ) {
			$customer_id = absint( $order->customer_id );
		}
		if ( $customer_id ) {
			$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
			$customer  = $customers->get_customer( $customer_id );
			if ( $customer && ! empty( $customer->email ) && is_email( $customer->email ) ) {
				$body['customer_email'] = sanitize_email( $customer->email );
			}
		}

		/**
		 * Filter Stripe Checkout Session arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array $body       Stripe session payload.
		 * @param array $order_data Order data.
		 */
		$body = apply_filters( 'aveniadp_stripe_checkout_session_args', $body, $order_data );

		/**
		 * Filter Stripe Checkout line items.
		 *
		 * @since 1.0.0
		 *
		 * @param array $line_items Line items array.
		 * @param array $order_data Order data.
		 */
		$body['line_items'] = apply_filters( 'aveniadp_checkout_line_items', $body['line_items'], $order_data );

		$response = $this->stripe_request( 'checkout/sessions', $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response['url'] ) ) {
			return new WP_Error( 'stripe_no_url', __( 'Stripe did not return a checkout URL.', 'avenia-digital-products' ) );
		}
		return $response['url'];
	}

	/**
	 * Make a request to Stripe API.
	 *
	 * @param string $endpoint Endpoint path (e.g. checkout/sessions).
	 * @param array  $body     Request parameters (sent form-encoded, as Stripe's API requires).
	 * @return array|WP_Error Decoded JSON or error.
	 */
	private function stripe_request( $endpoint, $body ) {
		$secret = $this->get_secret_key();
		$url    = self::API_BASE . '/' . $endpoint;

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => http_build_query( $body, '', '&' ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Stripe request failed.', 'avenia-digital-products' );
			return new WP_Error( 'stripe_api', $msg );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Pick the best Stripe reference ID to store on the order.
	 *
	 * @param array $session Stripe Checkout Session object.
	 * @return string
	 * @since 1.0.2
	 */
	private function get_transaction_id_from_session( $session ) {
		if ( ! is_array( $session ) ) {
			return '';
		}

		if ( ! empty( $session['payment_intent'] ) && is_string( $session['payment_intent'] ) ) {
			return sanitize_text_field( $session['payment_intent'] );
		}

		if ( ! empty( $session['id'] ) && is_string( $session['id'] ) ) {
			return sanitize_text_field( $session['id'] );
		}

		return '';
	}

	/**
	 * Retrieve a Checkout Session from Stripe.
	 *
	 * @param string $session_id Stripe session ID (e.g. cs_xxx).
	 * @return array|WP_Error Session object or error.
	 */
	private function retrieve_session( $session_id ) {
		$secret = $this->get_secret_key();
		$url    = self::API_BASE . '/checkout/sessions/' . $session_id;

		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			return new WP_Error( 'stripe_api', __( 'Failed to verify payment with Stripe.', 'avenia-digital-products' ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Handle return from Stripe Checkout (success_url). Verify session and complete order.
	 *
	 * @since 1.0.0
	 */
	public function handle_payment_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Payment gateway return URL; order and session IDs are verified with Stripe below.
		if ( empty( $_GET['aveniadp_payment_return'] ) || empty( $_GET['gateway'] ) || 'stripe' !== $_GET['gateway'] ) {
			return;
		}

		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-stripe-return' );
		}

		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

		if ( ! $order_id || ! $session_id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$session = $this->retrieve_session( $session_id );
		if ( is_wp_error( $session ) ) {
			wp_die( esc_html( $session->get_error_message() ), esc_html__( 'Payment Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		// Validate that the session matches the expected order amount and currency.
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'avenia-digital-products' ), esc_html__( 'Payment Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		$session_currency = isset( $session['currency'] ) ? strtolower( $session['currency'] ) : '';
		$session_amount   = isset( $session['amount_total'] ) ? $this->from_stripe_amount( $session['amount_total'], $session_currency ) : null;
		$expected_total   = isset( $order->total ) ? floatval( $order->total ) : 0.0;
		$expected_currency = strtolower( get_option( 'aveniadp_currency', 'USD' ) );

		if ( null === $session_amount || $session_amount < 0 ) {
			wp_die( esc_html__( 'Stripe did not return a valid amount.', 'avenia-digital-products' ), esc_html__( 'Payment Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		if ( abs( $expected_total - $session_amount ) > 0.01 || $session_currency !== $expected_currency ) {
			wp_die( esc_html__( 'Payment amount or currency mismatch.', 'avenia-digital-products' ), esc_html__( 'Payment Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		$payment_status = isset( $session['payment_status'] ) ? $session['payment_status'] : '';
		$ref_id         = isset( $session['client_reference_id'] ) ? $session['client_reference_id'] : '';
		if ( ! $this->stripe_session_payment_complete( $session ) || (string) $order_id !== $ref_id ) {
			wp_die( esc_html__( 'Invalid or unpaid session.', 'avenia-digital-products' ), esc_html__( 'Payment Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		$checkout = function_exists( 'aveniadp_checkout' ) ? aveniadp_checkout() : new AVENIADP_Checkout();
		$payment_result = array();
		$transaction_id = $this->get_transaction_id_from_session( $session );
		if ( '' !== $transaction_id ) {
			$payment_result['transaction_id'] = $transaction_id;
		}
		$checkout->complete_order( $order_id, $payment_result );

		if ( function_exists( 'aveniadp_get_thank_you_url' ) ) {
			$thank_you_url = aveniadp_get_thank_you_url( $order_id );
		} else {
			$thank_you_url = add_query_arg(
				array(
					'aveniadp_thank_you' => '1',
					'order_id'     => $order_id,
				),
				home_url( '/' )
			);
		}

		// phpcs:enable
		wp_safe_redirect( $thank_you_url );
		exit;
	}

	/**
	 * Register webhook route.
	 *
	 * @since 1.0.0
	 */
	public function register_webhook_route() {
		register_rest_route(
			'avenia-digital-products/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Stripe webhook (checkout.session.completed). Completes order idempotently.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function handle_webhook( $request ) {
		$payload   = $request->get_body();
		$sig_header = $request->get_header( 'Stripe-Signature' );
		$data      = json_decode( $payload, true );
		$livemode  = isset( $data['livemode'] ) && $data['livemode'];
		$secret    = $livemode ? get_option( 'aveniadp_stripe_webhook_secret', '' ) : get_option( 'aveniadp_stripe_webhook_secret_test', '' );

		// Enforce that webhook signing is correctly configured and valid.
		if ( empty( $secret ) || empty( $sig_header ) ) {
			$this->log( 'Stripe webhook rejected: missing secret or signature header.', array( 'livemode' => $livemode ) );
			return new WP_REST_Response( array( 'error' => 'Webhook not configured.' ), 400 );
		}

		$expects_live = aveniadp_is_payment_live();
		if ( (bool) $livemode !== (bool) $expects_live ) {
			$this->log(
				'Stripe webhook rejected: mode mismatch.',
				array(
					'livemode'    => $livemode,
					'environment' => aveniadp_get_payment_environment(),
				)
			);
			return new WP_REST_Response( array( 'error' => 'Mode mismatch.' ), 400 );
		}

		if ( ! $this->verify_webhook_signature( $payload, $sig_header, $secret, self::WEBHOOK_TOLERANCE ) ) {
			$this->log( 'Stripe webhook rejected: invalid signature.', array( 'livemode' => $livemode ) );
			return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
		}

		$event_key = '';
		if ( ! empty( $data['id'] ) && is_string( $data['id'] ) ) {
			$event_key = 'aveniadp_stripe_webhook_' . md5( sanitize_text_field( $data['id'] ) );
			if ( get_transient( $event_key ) ) {
				return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
			}
		}

		$response = $this->process_verified_webhook( $data );

		// Mark seen only after a successful (non-4xx/5xx) response so Stripe can retry failures.
		if ( $event_key && $response instanceof WP_REST_Response && (int) $response->get_status() < 400 ) {
			set_transient( $event_key, 1, HOUR_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Process a signature-verified Stripe webhook payload.
	 *
	 * @param array $data Decoded event payload.
	 * @return WP_REST_Response
	 */
	private function process_verified_webhook( $data ) {
		$type = isset( $data['type'] ) ? $data['type'] : '';

		if ( 'checkout.session.completed' === $type ) {
			$session = isset( $data['data']['object'] ) ? $data['data']['object'] : array();
			$payment_status  = isset( $session['payment_status'] ) ? $session['payment_status'] : '';
			$ref_id          = isset( $session['client_reference_id'] ) ? $session['client_reference_id'] : '';

			if ( ( 'paid' !== $payment_status && 'no_payment_required' !== $payment_status ) || ! is_numeric( $ref_id ) ) {
				return new WP_REST_Response( array( 'received' => true ), 200 );
			}

			$order_id = (int) $ref_id;

			// Validate that the session matches the expected order amount and currency.
			$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
			$order  = $orders->get_order( $order_id );
			if ( ! $order ) {
				$this->log( 'Stripe webhook checkout.session.completed: order not found.', array( 'order_id' => $order_id ) );
				return new WP_REST_Response( array( 'error' => 'Order not found.' ), 400 );
			}

			$session_currency = isset( $session['currency'] ) ? strtolower( $session['currency'] ) : '';
			$session_amount   = isset( $session['amount_total'] ) ? $this->from_stripe_amount( $session['amount_total'], $session_currency ) : null;
			$expected_total   = isset( $order->total ) ? floatval( $order->total ) : 0.0;
			$expected_currency = strtolower( get_option( 'aveniadp_currency', 'USD' ) );

			if ( null === $session_amount || $session_amount < 0 ) {
				$this->log(
					'Stripe webhook checkout.session.completed: missing or invalid amount_total.',
					array(
						'order_id' => $order_id,
					)
				);
				return new WP_REST_Response( array( 'error' => 'Invalid amount.' ), 400 );
			}

			if ( abs( $expected_total - $session_amount ) > 0.01 || $session_currency !== $expected_currency ) {
				$this->log(
					'Stripe webhook checkout.session.completed: amount or currency mismatch.',
					array(
						'order_id'          => $order_id,
						'expected_total'    => $expected_total,
						'session_amount'    => $session_amount,
						'expected_currency' => $expected_currency,
						'session_currency'  => $session_currency,
					)
				);
				return new WP_REST_Response( array( 'error' => 'Amount or currency mismatch.' ), 400 );
			}

			$checkout = function_exists( 'aveniadp_checkout' ) ? aveniadp_checkout() : new AVENIADP_Checkout();
			$payment_result = array();
			$transaction_id = $this->get_transaction_id_from_session( $session );
			if ( '' !== $transaction_id ) {
				$payment_result['transaction_id'] = $transaction_id;
			}
			$checkout->complete_order( $order_id, $payment_result );

			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Verify Stripe webhook signature (v1).
	 *
	 * @param string $payload    Raw body.
	 * @param string $sig_header Stripe-Signature header.
	 * @param string $secret     Webhook signing secret.
	 * @param int    $tolerance  Allowed timestamp skew in seconds.
	 * @return bool
	 */
	private function verify_webhook_signature( $payload, $sig_header, $secret, $tolerance = self::WEBHOOK_TOLERANCE ) {
		$parts = explode( ',', $sig_header );
		$v1    = '';
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( 0 === strpos( $part, 'v1=' ) ) {
				$v1 = substr( $part, 3 );
				break;
			}
		}
		if ( empty( $v1 ) ) {
			return false;
		}
		$timestamp = '';
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( 0 === strpos( $part, 't=' ) ) {
				$timestamp = substr( $part, 2 );
				break;
			}
		}
		if ( empty( $timestamp ) || ! ctype_digit( $timestamp ) ) {
			return false;
		}

		// Reject if timestamp is too old or too far in the future to prevent replay attacks.
		$timestamp_int = (int) $timestamp;
		if ( $timestamp_int <= 0 || abs( time() - $timestamp_int ) > absint( $tolerance ) ) {
			return false;
		}

		$signed = $timestamp . '.' . $payload;
		$expected = hash_hmac( 'sha256', $signed, $secret );
		return hash_equals( $expected, $v1 );
	}

	/**
	 * Whether a Stripe Checkout Session represents a completed authorization.
	 *
	 * @param array $session Stripe session payload.
	 * @return bool
	 * @since 1.0.4
	 */
	private function stripe_session_payment_complete( $session ) {
		$payment_status = isset( $session['payment_status'] ) ? $session['payment_status'] : '';
		return in_array( $payment_status, array( 'paid', 'no_payment_required' ), true );
	}

	/**
	 * Simple logger wrapper for Stripe-related messages.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 */
	private function log( $message, $context = array() ) {
		if ( function_exists( 'aveniadp_logger' ) ) {
			aveniadp_logger()->log( 'stripe', $message, $context );
		}
	}
}
