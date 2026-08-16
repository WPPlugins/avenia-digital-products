<?php
/**
 * Product access control (grant/revoke).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Access class.
 *
 * @since 1.0.0
 */
class AVENIADP_Access {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

	/**
	 * Whether a completed order still grants product access.
	 *
	 * @param object|null $order Order row.
	 * @return bool
	 * @since 1.0.0
	 */
	public function order_grants_access( $order ) {
		if ( ! $order || 'completed' !== $order->status ) {
			return false;
		}

		return empty( $order->access_revoked_at );
	}

	/**
	 * Revoke product access for a completed order (local only; does not contact gateways).
	 *
	 * @param int $order_id Order ID.
	 * @return true|WP_Error
	 * @since 1.0.0
	 */
	public function revoke_order_access( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$orders   = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order    = $orders->get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Order not found.', 'avenia-digital-products' ) );
		}

		if ( 'completed' !== $order->status ) {
			return new WP_Error( 'invalid_status', __( 'Only completed orders can have access revoked.', 'avenia-digital-products' ) );
		}

		if ( ! empty( $order->access_revoked_at ) ) {
			return new WP_Error( 'already_revoked', __( 'Access for this order has already been revoked.', 'avenia-digital-products' ) );
		}

		$downloads = function_exists( 'aveniadp_downloads' ) ? aveniadp_downloads() : new AVENIADP_Downloads();
		$downloads->revoke_downloads_for_order( $order_id );

		$table  = $wpdb->prefix . 'aveniadp_orders';
		$result = $wpdb->update(
			$table,
			array( 'access_revoked_at' => current_time( 'mysql' ) ),
			array( 'order_id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to revoke order access.', 'avenia-digital-products' ) );
		}

		$logger = function_exists( 'aveniadp_logger' ) ? aveniadp_logger() : new AVENIADP_Logger();
		$logger->log(
			'access',
			'Order access revoked locally.',
			array(
				'order_id'    => $order_id,
				'customer_id' => (int) $order->customer_id,
				'admin_id'    => get_current_user_id(),
			)
		);

		return true;
	}

	/**
	 * Revoke access for every completed, non-revoked order belonging to a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array{revoked:int,errors:array}|WP_Error
	 * @since 1.0.0
	 */
	public function revoke_all_customer_access( $customer_id ) {
		$customer_id = absint( $customer_id );
		$customers   = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer    = $customers->get_customer( $customer_id );

		if ( ! $customer ) {
			return new WP_Error( 'invalid_customer', __( 'Customer not found.', 'avenia-digital-products' ) );
		}

		$orders  = $customers->get_customer_orders( $customer_id );
		$revoked = 0;
		$errors  = array();

		foreach ( $orders as $order ) {
			if ( 'completed' !== $order->status || ! empty( $order->access_revoked_at ) ) {
				continue;
			}

			$result = $this->revoke_order_access( (int) $order->order_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf(
					/* translators: 1: order ID, 2: error message */
					__( 'Order #%1$d: %2$s', 'avenia-digital-products' ),
					(int) $order->order_id,
					$result->get_error_message()
				);
				continue;
			}

			++$revoked;
		}

		$logger = function_exists( 'aveniadp_logger' ) ? aveniadp_logger() : new AVENIADP_Logger();
		$logger->log(
			'access',
			'All customer access revoked locally.',
			array(
				'customer_id' => $customer_id,
				'orders'      => $revoked,
				'admin_id'    => get_current_user_id(),
			)
		);

		return array(
			'revoked' => $revoked,
			'errors'  => $errors,
		);
	}

	// phpcs:enable
}
