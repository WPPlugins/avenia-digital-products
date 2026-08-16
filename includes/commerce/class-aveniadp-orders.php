<?php
/**
 * Order management.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders class.
 *
 * @since 1.0.0
 */
class AVENIADP_Orders {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

	/**
	 * Create order.
	 *
	 * @param array $data Order data.
	 * @return int|WP_Error Order ID or error.
	 * @since 1.0.0
	 */
	public function create_order( $data ) {
		global $wpdb;

		if ( class_exists( 'AVENIADP_Install' ) ) {
			AVENIADP_Install::ensure_orders_schema();
		}

		$table = $wpdb->prefix . 'aveniadp_orders';

		$order_data = array(
			'customer_id'     => absint( $data['customer_id'] ),
			'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending',
			'total'           => floatval( $data['total'] ),
			'discount_amount' => isset( $data['discount_amount'] ) ? floatval( $data['discount_amount'] ) : 0,
			'tax_amount'      => isset( $data['tax_amount'] ) ? floatval( $data['tax_amount'] ) : 0,
			'tax_rate'        => isset( $data['tax_rate'] ) ? floatval( $data['tax_rate'] ) : 0,
			'payment_method'  => sanitize_text_field( $data['payment_method'] ),
			'created_at'      => current_time( 'mysql' ),
		);

		if ( ! empty( $data['discount_code_id'] ) ) {
			$order_data['discount_code_id'] = absint( $data['discount_code_id'] );
		}

		$format_map = array(
			'customer_id'      => '%d',
			'status'           => '%s',
			'total'            => '%f',
			'discount_amount'  => '%f',
			'tax_amount'       => '%f',
			'tax_rate'         => '%f',
			'payment_method'   => '%s',
			'created_at'       => '%s',
			'discount_code_id' => '%d',
		);

		$formats = array();
		foreach ( array_keys( $order_data ) as $field ) {
			$formats[] = $format_map[ $field ];
		}

		$result = $wpdb->insert( $table, $order_data, $formats );

		if ( false === $result ) {
			$message = __( 'Failed to create order.', 'avenia-digital-products' );
			if ( ! empty( $wpdb->last_error ) ) {
				$message .= ' ' . $wpdb->last_error;
			}
			if ( function_exists( 'aveniadp_logger' ) ) {
				aveniadp_logger()->log(
					'orders',
					'create_order failed: ' . ( $wpdb->last_error ? $wpdb->last_error : 'unknown' ),
					array(
						'table' => $table,
						'data'  => $order_data,
					)
				);
			}
			return new WP_Error( 'db_error', $message );
		}

		$order_id = $wpdb->insert_id;

		// Add order items.
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$this->add_order_item( $order_id, $item );
			}
		}

		return $order_id;
	}

	/**
	 * Add order item.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $item     Item data.
	 * @return int|WP_Error Item ID or error.
	 * @since 1.0.0
	 */
	public function add_order_item( $order_id, $item ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$table    = $wpdb->prefix . 'aveniadp_order_items';

		$item_data = array(
			'order_id'         => $order_id,
			'product_id'       => absint( $item['product_id'] ),
			'price'            => floatval( $item['price'] ),
			'quantity'         => isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1,
			'license_quantity' => isset( $item['license_quantity'] ) ? absint( $item['license_quantity'] ) : 1,
		);

		$formats = array( '%d', '%d', '%f', '%d', '%d' );

		$result = $wpdb->insert( $table, $item_data, $formats );

		if ( false === $result ) {
			$message = __( 'Failed to add order item.', 'avenia-digital-products' );
			if ( ! empty( $wpdb->last_error ) ) {
				$message .= ' ' . $wpdb->last_error;
			}
			return new WP_Error( 'db_error', $message );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_order( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$table = $wpdb->prefix . 'aveniadp_orders';

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $order;
	}

	/**
	 * Get order items.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_order_items( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$table = $wpdb->prefix . 'aveniadp_order_items';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $items;
	}

	/**
	 * Atomically mark a pending/failed order as completed.
	 *
	 * Prevents webhook + return URL races from both running completion side effects.
	 *
	 * @param int $order_id Order ID.
	 * @return bool True when this caller claimed completion.
	 * @since 1.0.0
	 */
	public function claim_order_for_completion( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}

		$table = $wpdb->prefix . 'aveniadp_orders';

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s WHERE order_id = %d AND status IN ( %s, %s )",
				'completed',
				$order_id,
				'pending',
				'failed'
			)
		);

		return 1 === (int) $updated;
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   Order status.
	 * @return bool
	 * @since 1.0.0
	 */
	public function update_order_status( $order_id, $status ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$status = sanitize_text_field( $status );

		$valid_statuses = array( 'pending', 'completed', 'failed' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'aveniadp_orders';

		$result = $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'order_id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Store the gateway transaction ID on an order (only when not already set).
	 *
	 * @param int    $order_id       Order ID.
	 * @param string $transaction_id Gateway payment reference.
	 * @return bool
	 * @since 1.0.2
	 */
	public function update_order_transaction_id( $order_id, $transaction_id ) {
		global $wpdb;

		$order_id       = absint( $order_id );
		$transaction_id = sanitize_text_field( $transaction_id );

		if ( ! $order_id || '' === $transaction_id ) {
			return false;
		}

		$order = $this->get_order( $order_id );
		if ( ! $order || ! empty( $order->transaction_id ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'aveniadp_orders';

		$result = $wpdb->update(
			$table,
			array( 'transaction_id' => $transaction_id ),
			array( 'order_id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	// phpcs:enable
}
