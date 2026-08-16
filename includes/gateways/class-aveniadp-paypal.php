<?php
/**
 * PayPal payment gateway.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPal gateway class.
 *
 * @since 1.0.0
 */
class AVENIADP_PayPal {

	/**
	 * PayPal API base URL (sandbox or live).
	 *
	 * @return string
	 */
	private function get_api_base() {
		return aveniadp_is_payment_sandbox()
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Get client ID and secret for the active payment environment.
	 *
	 * @return array{client_id: string, secret: string}
	 */
	private function get_credentials() {
		if ( aveniadp_is_payment_sandbox() ) {
			return array(
				'client_id' => get_option( 'aveniadp_paypal_test_client_id', '' ),
				'secret'    => get_option( 'aveniadp_paypal_test_secret', '' ),
			);
		}

		return array(
			'client_id' => get_option( 'aveniadp_paypal_live_client_id', '' ),
			'secret'    => get_option( 'aveniadp_paypal_live_secret', '' ),
		);
	}

	/**
	 * Initialize PayPal gateway.
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
		// Handle return_url/cancel_url redirects.
		add_action( 'template_redirect', array( $this, 'handle_payment_return' ) );

		// Admin notices for configuration issues.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_enabled() {
		return 'yes' === get_option( 'aveniadp_paypal_enabled', 'no' );
	}

	/**
	 * Output admin notices for common PayPal configuration issues.
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

		if ( 'yes' !== get_option( 'aveniadp_paypal_enabled', 'no' ) ) {
			return;
		}

		$creds = $this->get_credentials();

		if ( empty( $creds['client_id'] ) || empty( $creds['secret'] ) ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Avenia Digital Products: PayPal is enabled but API credentials are not configured for the active payment environment.', 'avenia-digital-products' );
			echo '</p></div>';
		}
	}

	/**
	 * Build a friendly payment error redirect URL.
	 *
	 * @param string $code Short error code.
	 * @param int    $order_id Optional order ID.
	 * @return string
	 */
	private function get_error_redirect_url( $code, $order_id = 0 ) {
		$args = array(
			'aveniadp_payment_error' => $code,
		);

		if ( $order_id > 0 ) {
			$args['order_id'] = $order_id;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Get gateway title.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title() {
		return __( 'PayPal', 'avenia-digital-products' );
	}

	/**
	 * Internal logging helper for PayPal events.
	 *
	 * Uses a filterable mechanism so site owners can hook a custom logger.
	 *
	 * @param string $context Short context string.
	 * @param array  $data    Additional structured data.
	 * @return void
	 */
	private function log( $context, $data = array() ) {
		$entry = array(
			'context' => $context,
			'data'    => $data,
		);

		/**
		 * Filter: aveniadp_paypal_log
		 *
		 * Allow custom logging of PayPal gateway events.
		 *
		 * @param array $entry Log entry with context and data.
		 */
		do_action( 'aveniadp_paypal_log', $entry );

		if ( function_exists( 'aveniadp_logger' ) ) {
			aveniadp_logger()->log( 'paypal', $context, $data );
		}
	}

	/**
	 * Build a user-facing message from a PayPal API error payload.
	 *
	 * @param array|null $data    Decoded response body.
	 * @param string     $fallback Fallback message.
	 * @return string
	 */
	private function format_paypal_api_error( $data, $fallback ) {
		$message = $fallback;
		if ( is_array( $data ) && ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
			$message = $data['message'];
		}

		$detail_parts = array();
		if ( is_array( $data ) && ! empty( $data['details'] ) && is_array( $data['details'] ) ) {
			foreach ( $data['details'] as $detail ) {
				if ( ! is_array( $detail ) ) {
					continue;
				}
				$piece = '';
				if ( ! empty( $detail['field'] ) ) {
					$piece .= sanitize_text_field( (string) $detail['field'] );
				}
				if ( ! empty( $detail['issue'] ) ) {
					$piece .= ( '' !== $piece ? ': ' : '' ) . sanitize_text_field( (string) $detail['issue'] );
				} elseif ( ! empty( $detail['description'] ) ) {
					$piece .= ( '' !== $piece ? ': ' : '' ) . sanitize_text_field( (string) $detail['description'] );
				}
				if ( '' !== $piece ) {
					$detail_parts[] = $piece;
				}
			}
		}

		if ( ! empty( $detail_parts ) ) {
			$message .= ' (' . implode( '; ', $detail_parts ) . ')';
		} elseif ( is_array( $data ) && ! empty( $data['debug_id'] ) ) {
			$message .= ' [debug_id: ' . sanitize_text_field( (string) $data['debug_id'] ) . ']';
		}

		$this->log(
			'api_error',
			array(
				'message'  => $message,
				'name'     => is_array( $data ) && isset( $data['name'] ) ? $data['name'] : '',
				'debug_id' => is_array( $data ) && isset( $data['debug_id'] ) ? $data['debug_id'] : '',
				'details'  => is_array( $data ) && isset( $data['details'] ) ? $data['details'] : array(),
			)
		);

		return $message;
	}

	/**
	 * Process payment.
	 *
	 * @param array $order_data Order data.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_payment( $order_data ) {
		$creds = $this->get_credentials();
		if ( empty( $creds['client_id'] ) || empty( $creds['secret'] ) ) {
			return new WP_Error( 'paypal_no_creds', __( 'PayPal credentials are not configured.', 'avenia-digital-products' ) );
		}

		$redirect_url = $this->create_order( $order_data );

		if ( is_wp_error( $redirect_url ) ) {
			return $redirect_url;
		}

		return array( 'redirect' => $redirect_url );
	}

	/**
	 * Get OAuth2 access token.
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token() {
		$creds = $this->get_credentials();

		// Cache access tokens per environment to avoid hitting PayPal on every request.
		$cache_key = 'aveniadp_paypal_access_token_' . aveniadp_get_payment_environment();

		$cached = get_transient( $cache_key );
		if ( ! empty( $cached ) && is_string( $cached ) ) {
			return $cached;
		}

		$base = $this->get_api_base();
		$url  = $base . '/v1/oauth2/token';
		$auth = base64_encode( $creds['client_id'] . ':' . $creds['secret'] );

		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			$msg = isset( $data['error_description'] ) ? $data['error_description'] : __( 'PayPal authentication failed.', 'avenia-digital-products' );
			return new WP_Error( 'paypal_auth', $msg );
		}

		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'paypal_auth', __( 'No access token in response.', 'avenia-digital-products' ) );
		}

		// Cache token for its lifetime minus a small buffer.
		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 0;
		if ( $expires_in > 0 ) {
			$ttl = max( 60, $expires_in - 60 );
			set_transient( $cache_key, $data['access_token'], $ttl );
		}

		return $data['access_token'];
	}

	/**
	 * Create PayPal order and return approval URL.
	 *
	 * @param array $order_data Order data.
	 * @return string|WP_Error Approval URL or error.
	 */
	private function create_order( $order_data ) {
		$order_id = isset( $order_data['order_id'] ) ? absint( $order_data['order_id'] ) : 0;
		$total    = isset( $order_data['total'] ) ? floatval( $order_data['total'] ) : 0;
		$currency = strtoupper( get_option( 'aveniadp_currency', 'USD' ) );

		if ( $order_id <= 0 || $total <= 0 ) {
			return new WP_Error( 'paypal_invalid_order', __( 'Invalid order data.', 'avenia-digital-products' ) );
		}

		// Cross-check against stored order to prevent tampering with the amount.
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );

		if ( ! $order || $order->total <= 0 ) {
			return new WP_Error( 'paypal_invalid_order', __( 'Invalid order reference.', 'avenia-digital-products' ) );
		}

		$stored_total = (float) $order->total;
		if ( abs( $stored_total - $total ) > 0.01 ) {
			// Always trust the server-side stored total.
			$total = $stored_total;
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$base       = $this->get_api_base();
		$return_url = add_query_arg(
			array(
				'aveniadp_payment_return' => '1',
				'gateway'          => 'paypal',
				'order_id'         => $order_id,
			),
			home_url( '/' )
		);
		$cancel_url = add_query_arg(
			array(
				'aveniadp_payment_cancelled' => '1',
				'order_id'             => $order_id,
			),
			home_url( '/' )
		);

		$body = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'amount'    => array(
						'currency_code' => $currency,
						'value'         => number_format( $total, 2, '.', '' ),
					),
					'custom_id' => (string) $order_id,
				),
			),
			'application_context' => array(
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
			),
		);

		$res = wp_remote_post(
			$base . '/v2/checkout/orders',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'                 => 'Bearer ' . $token,
					'Content-Type'                  => 'application/json',
					'PayPal-Request-Id'             => 'aveniadp_order_' . $order_id . '_' . wp_rand( 1000, 9999 ),
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'paypal_order',
				$this->format_paypal_api_error( $data, __( 'PayPal order creation failed.', 'avenia-digital-products' ) )
			);
		}

		$paypal_order_id = isset( $data['id'] ) ? $data['id'] : '';
		if ( empty( $paypal_order_id ) ) {
			return new WP_Error( 'paypal_order', __( 'PayPal did not return an order ID.', 'avenia-digital-products' ) );
		}

		// Store mapping so return handler can find our order_id from PayPal token.
		set_transient( 'aveniadp_paypal_order_' . $paypal_order_id, $order_id, 3 * HOUR_IN_SECONDS );

		$approve_url = '';
		if ( ! empty( $data['links'] ) && is_array( $data['links'] ) ) {
			foreach ( $data['links'] as $link ) {
				if ( isset( $link['rel'] ) && 'approve' === $link['rel'] && ! empty( $link['href'] ) ) {
					$approve_url = $link['href'];
					break;
				}
			}
		}

		if ( empty( $approve_url ) ) {
			return new WP_Error( 'paypal_order', __( 'PayPal did not return an approval URL.', 'avenia-digital-products' ) );
		}

		return $approve_url;
	}

	/**
	 * Capture PayPal order and return our local order ID when capture is complete.
	 *
	 * @param string $paypal_order_id PayPal order ID (token from return URL).
	 * @return array|WP_Error Array with order_id and transaction_id, or error.
	 */
	private function capture_order( $paypal_order_id ) {
		$order_id = get_transient( 'aveniadp_paypal_order_' . $paypal_order_id );
		if ( false === $order_id || ! is_numeric( $order_id ) ) {
			return new WP_Error( 'paypal_invalid_token', __( 'Invalid or expired payment session.', 'avenia-digital-products' ) );
		}

		$order_id = (int) $order_id;
		$orders   = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order    = $orders->get_order( $order_id );
		if ( ! $order || 'paypal' !== $order->payment_method ) {
			return new WP_Error( 'paypal_invalid_order', __( 'Invalid order reference.', 'avenia-digital-products' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$base = $this->get_api_base();
		$res  = wp_remote_post(
			$base . '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			return new WP_Error(
				'paypal_capture',
				$this->format_paypal_api_error( $data, __( 'PayPal capture failed.', 'avenia-digital-products' ) )
			);
		}

		$validated = $this->validate_paypal_capture_response( $data, $order );
		if ( is_wp_error( $validated ) ) {
			$this->log(
				'capture_validation_failed',
				array(
					'order_id'        => $order_id,
					'paypal_order_id' => $paypal_order_id,
					'error'           => $validated->get_error_message(),
					'status'          => isset( $data['status'] ) ? $data['status'] : '',
				)
			);
			return $validated;
		}

		delete_transient( 'aveniadp_paypal_order_' . $paypal_order_id );

		return array(
			'order_id'       => $order_id,
			'transaction_id' => $this->get_transaction_id_from_capture( $data, $paypal_order_id ),
		);
	}

	/**
	 * Pick the best PayPal reference ID to store on the order.
	 *
	 * @param array  $data            Decoded capture response.
	 * @param string $paypal_order_id PayPal order ID from the return URL.
	 * @return string
	 * @since 1.0.2
	 */
	private function get_transaction_id_from_capture( $data, $paypal_order_id ) {
		if ( is_array( $data ) && ! empty( $data['purchase_units'] ) && is_array( $data['purchase_units'] ) ) {
			foreach ( $data['purchase_units'] as $unit ) {
				if ( ! empty( $unit['payments']['captures'][0]['id'] ) && is_string( $unit['payments']['captures'][0]['id'] ) ) {
					return sanitize_text_field( $unit['payments']['captures'][0]['id'] );
				}
			}
		}

		return sanitize_text_field( $paypal_order_id );
	}

	/**
	 * Ensure a PayPal capture response is COMPLETED and matches the local order.
	 *
	 * @param array  $data  Decoded capture response.
	 * @param object $order Local order row.
	 * @return true|WP_Error
	 */
	private function validate_paypal_capture_response( $data, $order ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'paypal_capture', __( 'Invalid PayPal capture response.', 'avenia-digital-products' ) );
		}

		$status = isset( $data['status'] ) ? strtoupper( sanitize_text_field( $data['status'] ) ) : '';
		if ( 'COMPLETED' !== $status ) {
			return new WP_Error(
				'paypal_capture_incomplete',
				__( 'PayPal payment was not completed.', 'avenia-digital-products' )
			);
		}

		$purchase_units = ( isset( $data['purchase_units'] ) && is_array( $data['purchase_units'] ) )
			? $data['purchase_units']
			: array();
		$unit           = ! empty( $purchase_units[0] ) && is_array( $purchase_units[0] ) ? $purchase_units[0] : array();

		if ( ! empty( $unit['custom_id'] ) && (string) absint( $unit['custom_id'] ) !== (string) absint( $order->order_id ) ) {
			return new WP_Error( 'paypal_capture_mismatch', __( 'PayPal order does not match this store order.', 'avenia-digital-products' ) );
		}

		$capture = array();
		if ( isset( $unit['payments']['captures'][0] ) && is_array( $unit['payments']['captures'][0] ) ) {
			$capture = $unit['payments']['captures'][0];
		}

		$capture_status = isset( $capture['status'] ) ? strtoupper( sanitize_text_field( $capture['status'] ) ) : '';
		if ( '' !== $capture_status && 'COMPLETED' !== $capture_status ) {
			return new WP_Error(
				'paypal_capture_incomplete',
				__( 'PayPal payment was not completed.', 'avenia-digital-products' )
			);
		}

		$captured_value    = null;
		$captured_currency = '';
		if ( isset( $capture['amount']['value'] ) ) {
			$captured_value = (float) $capture['amount']['value'];
		}
		if ( isset( $capture['amount']['currency_code'] ) ) {
			$captured_currency = strtoupper( sanitize_text_field( $capture['amount']['currency_code'] ) );
		}

		// Fallback to purchase unit amount when capture amount is absent.
		if ( null === $captured_value && isset( $unit['amount']['value'] ) ) {
			$captured_value = (float) $unit['amount']['value'];
		}
		if ( '' === $captured_currency && isset( $unit['amount']['currency_code'] ) ) {
			$captured_currency = strtoupper( sanitize_text_field( $unit['amount']['currency_code'] ) );
		}

		$expected_total    = isset( $order->total ) ? (float) $order->total : 0.0;
		$expected_currency = strtoupper( get_option( 'aveniadp_currency', 'USD' ) );

		if ( null === $captured_value || $captured_value < 0 ) {
			return new WP_Error( 'paypal_capture_amount', __( 'PayPal did not return a valid capture amount.', 'avenia-digital-products' ) );
		}

		if ( abs( $expected_total - $captured_value ) > 0.01 || $captured_currency !== $expected_currency ) {
			return new WP_Error(
				'paypal_capture_mismatch',
				__( 'PayPal payment amount or currency does not match this order.', 'avenia-digital-products' )
			);
		}

		return true;
	}

	/**
	 * Handle return from PayPal (return_url). Capture order and complete our order.
	 *
	 * @since 1.0.0
	 */
	public function handle_payment_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Payment gateway return URL; PayPal token is verified via capture below.
		if ( empty( $_GET['aveniadp_payment_return'] ) || empty( $_GET['gateway'] ) || 'paypal' !== $_GET['gateway'] ) {
			return;
		}

		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-paypal-return' );
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( empty( $token ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$order_id = get_transient( 'aveniadp_paypal_order_' . $token );
		if ( false === $order_id || ! is_numeric( $order_id ) ) {
			wp_safe_redirect( $this->get_error_redirect_url( 'paypal_invalid_context' ) );
			exit;
		}

		$capture = $this->capture_order( $token );
		if ( is_wp_error( $capture ) ) {
			wp_safe_redirect( $this->get_error_redirect_url( 'paypal_capture_failed' ) );
			exit;
		}

		$order_id       = isset( $capture['order_id'] ) ? absint( $capture['order_id'] ) : 0;
		$transaction_id = isset( $capture['transaction_id'] ) ? sanitize_text_field( $capture['transaction_id'] ) : '';

		// Sanity-check that this order really belongs to PayPal and is pending.
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );
		if ( ! $order || 'paypal' !== $order->payment_method ) {
			wp_safe_redirect( $this->get_error_redirect_url( 'paypal_invalid_context', (int) $order_id ) );
			exit;
		}

		$payment_result = array();
		if ( '' !== $transaction_id ) {
			$payment_result['transaction_id'] = $transaction_id;
		}

		$checkout = function_exists( 'aveniadp_checkout' ) ? aveniadp_checkout() : new AVENIADP_Checkout();
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
}
