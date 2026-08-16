<?php
/**
 * Admin actions for revoking product access.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin access class.
 *
 * @since 1.0.0
 */
class AVENIADP_Admin_Access {

	/**
	 * Initialize admin access handlers.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_post_aveniadp_revoke_order_access', array( $this, 'handle_revoke_order_access' ) );
		add_action( 'admin_post_aveniadp_revoke_all_customer_access', array( $this, 'handle_revoke_all_customer_access' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Revoke access for a single order.
	 *
	 * @since 1.0.0
	 */
	public function handle_revoke_order_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'avenia-digital-products' ) );
		}

		check_admin_referer( 'aveniadp_revoke_order_access' );

		$order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'admin.php?page=aveniadp-orders&view=' . $order_id );

		$access = function_exists( 'aveniadp_access' ) ? aveniadp_access() : new AVENIADP_Access();
		$result = $access->revoke_order_access( $order_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $redirect_to, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice(
			$redirect_to,
			'success',
			__( 'Product access has been revoked for this order. Billing at Stripe or PayPal was not changed.', 'avenia-digital-products' )
		);
	}

	/**
	 * Revoke access for all orders belonging to a customer.
	 *
	 * @since 1.0.0
	 */
	public function handle_revoke_all_customer_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'avenia-digital-products' ) );
		}

		check_admin_referer( 'aveniadp_revoke_all_customer_access' );

		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'admin.php?page=aveniadp-customers&view=' . $customer_id );

		$access = function_exists( 'aveniadp_access' ) ? aveniadp_access() : new AVENIADP_Access();
		$result = $access->revoke_all_customer_access( $customer_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( $redirect_to, 'error', $result->get_error_message() );
		}

		if ( 0 === (int) $result['revoked'] && empty( $result['errors'] ) ) {
			$this->redirect_with_notice( $redirect_to, 'info', __( 'No active access found to revoke for this customer.', 'avenia-digital-products' ) );
		}

		$message = sprintf(
			/* translators: %d: number of orders */
			_n(
				'Access revoked locally for %d order. Billing at Stripe or PayPal was not changed.',
				'Access revoked locally for %d orders. Billing at Stripe or PayPal was not changed.',
				(int) $result['revoked'],
				'avenia-digital-products'
			),
			(int) $result['revoked']
		);

		if ( ! empty( $result['errors'] ) ) {
			$message .= ' ' . implode( ' ', $result['errors'] );
			$this->redirect_with_notice( $redirect_to, 'error', $message );
		}

		$this->redirect_with_notice( $redirect_to, 'success', $message );
	}

	/**
	 * Show admin notices after revoke actions.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- One-time admin flash message; values are sanitized before output.
		if ( ! isset( $_GET['aveniadp_notice'], $_GET['aveniadp_message'] ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( false === strpos( $screen->id, 'avenia-digital-products' ) && false === strpos( $screen->id, '_page_aveniadp-' ) ) ) {
			return;
		}

		$type    = sanitize_key( wp_unslash( $_GET['aveniadp_notice'] ) );
		$message = sanitize_text_field( wp_unslash( rawurldecode( $_GET['aveniadp_message'] ) ) );

		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-info is-dismissible';
		if ( 'success' === $type ) {
			$class = 'notice notice-success is-dismissible';
		} elseif ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
		// phpcs:enable
	}

	/**
	 * Redirect back to admin with a flash-style notice in the query string.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $type        Notice type.
	 * @param string $message     Notice message.
	 * @since 1.0.0
	 */
	private function redirect_with_notice( $redirect_to, $type, $message ) {
		$url = add_query_arg(
			array(
				'aveniadp_notice'  => sanitize_key( $type ),
				'aveniadp_message' => rawurlencode( $message ),
			),
			$redirect_to
		);

		wp_safe_redirect( $url );
		exit;
	}
}
