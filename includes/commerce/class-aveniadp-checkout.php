<?php
/**
 * Checkout process.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout class.
 *
 * @since 1.0.0
 */
class AVENIADP_Checkout {

	/**
	 * Initialize checkout.
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
		// Register shortcode.
		add_shortcode( 'aveniadp_checkout', array( $this, 'checkout_shortcode' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_aveniadp_process_checkout', array( $this, 'ajax_process_checkout' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_process_checkout', array( $this, 'ajax_process_checkout' ) );
	}

	/**
	 * Process checkout.
	 *
	 * @param array $data Checkout data.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_checkout( $data ) {
		$cart = function_exists( 'aveniadp_cart' ) ? aveniadp_cart() : new AVENIADP_Cart();
		$cart_items = $cart->get_cart();

		if ( empty( $cart_items ) ) {
			return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'avenia-digital-products' ) );
		}

		// Validate email.
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$email = $user && $user->exists() ? sanitize_email( $user->user_email ) : '';
		} else {
			$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
			$email_confirm = isset( $data['email_confirm'] ) ? sanitize_email( $data['email_confirm'] ) : '';

			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'avenia-digital-products' ) );
			}

			if ( ! is_email( $email_confirm ) ) {
				return new WP_Error( 'invalid_email_confirm', __( 'Please confirm your email address.', 'avenia-digital-products' ) );
			}

			if ( strtolower( $email ) !== strtolower( $email_confirm ) ) {
				return new WP_Error( 'email_mismatch', __( 'Email addresses do not match.', 'avenia-digital-products' ) );
			}
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'avenia-digital-products' ) );
		}

		$name = isset( $data['name'] ) && function_exists( 'aveniadp_sanitize_customer_name' )
			? aveniadp_sanitize_customer_name( $data['name'] )
			: ( isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '' );

		// Get or create customer.
		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer = $customers->get_customer_by_email( $email );
		if ( ! $customer ) {
			$customer_id = $customers->create_customer( $email, null, $name );
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}
		} else {
			$customer_id = $customer->customer_id;
			if ( '' !== $name ) {
				$customers->maybe_update_customer_name( $customer_id, $name );
			}
		}

		// Calculate total using cart totals (discount + tax).
		$cart_totals      = $cart->get_cart_totals( $customer_id );
		$subtotal         = $cart_totals['subtotal'];
		$discount_amount  = $cart_totals['discount_amount'];
		$tax_amount       = isset( $cart_totals['tax_amount'] ) ? (float) $cart_totals['tax_amount'] : 0;
		$tax_rate         = isset( $cart_totals['tax_rate'] ) ? (float) $cart_totals['tax_rate'] : 0;
		$total            = $cart_totals['total'];
		$discount_code_id = null;
		$applied_code     = $cart_totals['discount_code'];

		if ( $applied_code ) {
			$discounts = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();
			$product_ids = array_keys( $cart_items );
			$discount    = $discounts->validate_discount_code( $applied_code, $subtotal, $product_ids, $customer_id );

			if ( is_wp_error( $discount ) ) {
				return $discount;
			}

			$discount_code_id = $discount->code_id;
		}

		// Get payment gateway.
		$payments = function_exists( 'aveniadp_payments' ) ? aveniadp_payments() : new AVENIADP_Payments();

		if ( aveniadp_is_simulate_payments() ) {
			$gateway_id = 'simulate';
		} else {
			$gateway_id = isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : '';
			$gateway    = $payments->get_gateway( $gateway_id );

			if ( ! $gateway || ! $gateway->is_enabled() ) {
				return new WP_Error( 'invalid_gateway', __( 'Invalid payment gateway.', 'avenia-digital-products' ) );
			}
		}

		// Create order first (pending status).
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order_data = array(
			'customer_id'      => $customer_id,
			'total'            => $total,
			'discount_code_id' => $discount_code_id,
			'discount_amount'  => $discount_amount,
			'tax_amount'       => $tax_amount,
			'tax_rate'         => $tax_rate,
			'payment_method'   => $gateway_id,
			'status'           => 'pending',
			'items'            => $cart_items,
		);

		/**
		 * Filter order data before the pending order is created.
		 *
		 * @since 1.0.0
		 *
		 * @param array $order_data   Order data passed to AVENIADP_Orders::create_order().
		 * @param array $cart_items   Cart items keyed by product ID.
		 * @param int   $customer_id  Customer ID.
		 */
		$order_data = apply_filters( 'aveniadp_checkout_order_data', $order_data, $cart_items, $customer_id );

		$order_id = $orders->create_order( $order_data );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		// Process payment.
		$order_data['order_id'] = $order_id;
		if ( 'simulate' === $gateway_id ) {
			$result = $payments->process_simulated_payment( $order_data );
		} else {
			$result = $payments->process_payment( $order_data, $gateway_id );
		}

		if ( is_wp_error( $result ) ) {
			// Update order status to failed.
			$orders->update_order_status( $order_id, 'failed' );
			return $result;
		}

		// Payment successful - order will be updated via webhook.
		// For redirect-based payments (Stripe Checkout, PayPal Standard), complete order on return.
		// For immediate payments, complete now.
		if ( isset( $result['redirect'] ) ) {
			// Redirect-based payment - order will be completed via webhook/return URL.
			set_transient( 'aveniadp_pending_order_' . $order_id, 1, 15 * MINUTE_IN_SECONDS );
		} elseif ( isset( $result['success'] ) && $result['success'] ) {
			// Immediate payment success (e.g. simulated checkout).
			$this->complete_order( $order_id, $result );
			$result['order_id'] = $order_id;
			if ( function_exists( 'aveniadp_get_thank_you_url' ) ) {
				$result['thank_you_url'] = aveniadp_get_thank_you_url( $order_id );
			}
		}

		return $result;
	}

	/**
	 * Complete order after successful payment.
	 *
	 * @param int   $order_id       Order ID.
	 * @param array $payment_result Optional payment result data.
	 * @param array $options        Completion options (send_emails, create_account).
	 * @since 1.0.0
	 */
	public function complete_order( $order_id, $payment_result = array(), $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'send_emails'    => true,
				'create_account' => null,
			)
		);

		if ( class_exists( 'AVENIADP_Install' ) ) {
			AVENIADP_Install::ensure_orders_schema();
		}

		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );

		// Idempotency: avoid duplicate downloads/licenses if webhook and return URL both fire.
		if ( ! $order ) {
			return;
		}

		if ( ! empty( $payment_result['transaction_id'] ) ) {
			$orders->update_order_transaction_id( $order_id, $payment_result['transaction_id'] );
		}

		if ( 'completed' === $order->status || ! $orders->claim_order_for_completion( $order_id ) ) {
			// Already completed, or another request claimed completion.
			if ( function_exists( 'aveniadp_cart' ) ) {
				aveniadp_cart()->clear_cart();
			}
			return;
		}

		/**
		 * Fires before order completion side effects (downloads, emails).
		 *
		 * @since 1.0.0
		 *
		 * @param int   $order_id       Order ID.
		 * @param array $payment_result Payment gateway result data.
		 * @param array $options        Completion options (send_emails, create_account).
		 */
		do_action( 'aveniadp_before_order_complete', $order_id, $payment_result, $options );

		$order = $orders->get_order( $order_id );
		$order_items = $orders->get_order_items( $order_id );

		if ( ! empty( $order->discount_code_id ) ) {
			$discounts = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();
			$discounts->increment_usage( (int) $order->discount_code_id );
		}

		if ( function_exists( 'aveniadp_cart' ) ) {
			aveniadp_cart()->clear_cart();
		}

		// Create downloads.
		$downloads = function_exists( 'aveniadp_downloads' ) ? aveniadp_downloads() : new AVENIADP_Downloads();
		foreach ( $order_items as $item ) {
			$downloads->create_download( $order_id, $item->product_id, $options['send_emails'] );
		}

		if ( $options['send_emails'] ) {
			$emails = function_exists( 'aveniadp_emails' ) ? aveniadp_emails() : new AVENIADP_Emails();
			$emails->send_order_confirmation( $order_id );
			$emails->send_admin_order_notification( $order_id );
		}

		$should_create_account = null === $options['create_account']
			? ( 'auto-create' === get_option( 'aveniadp_account_creation', 'auto-create' ) )
			: (bool) $options['create_account'];

		if ( $should_create_account ) {
			$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
			$customers->maybe_create_wp_account( $order->customer_id, $options['send_emails'] );
		}

		$order = $orders->get_order( $order_id );

		/**
		 * Fires once when an order is fully completed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $order_id       Order ID.
		 * @param object $order          Order object.
		 * @param array  $payment_result Payment gateway result data.
		 */
		do_action( 'aveniadp_order_completed', $order_id, $order, $payment_result );
	}

	/**
	 * Checkout shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function checkout_shortcode( $atts ) {
		return aveniadp_get_template_html( 'checkout.php' );
	}

	/**
	 * AJAX process checkout.
	 *
	 * @since 1.0.0
	 */
	public function ajax_process_checkout() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$data = array(
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'email_confirm'  => isset( $_POST['email_confirm'] ) ? sanitize_email( wp_unslash( $_POST['email_confirm'] ) ) : '',
			'payment_method' => isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '',
		);

		$result = $this->process_checkout( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}
}
