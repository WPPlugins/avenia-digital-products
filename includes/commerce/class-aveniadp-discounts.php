<?php
/**
 * Discount codes/coupons management.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discounts class.
 *
 * @since 1.0.0
 */
class AVENIADP_Discounts {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

	/**
	 * Get discount code by ID.
	 *
	 * @param int $code_id Code ID.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_discount_by_id( $code_id ) {
		global $wpdb;

		$code_id = absint( $code_id );
		$table   = $wpdb->prefix . 'aveniadp_discount_codes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code_id = %d",
				$code_id
			)
		);
	}

	/**
	 * Get discount code.
	 *
	 * @param string $code Discount code.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_discount_code( $code ) {
		global $wpdb;

		$code = sanitize_text_field( $code );
		$table = $wpdb->prefix . 'aveniadp_discount_codes';

		$discount = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s",
				$code
			)
		);

		return $discount;
	}

	/**
	 * Validate discount code.
	 *
	 * @param string $code            Discount code.
	 * @param float  $cart_total      Cart total.
	 * @param array  $product_ids      Product IDs in cart.
	 * @param int    $customer_id      Customer ID (optional).
	 * @return object|WP_Error
	 * @since 1.0.0
	 */
	public function validate_discount_code( $code, $cart_total, $product_ids = array(), $customer_id = 0 ) {
		$discount = $this->get_discount_code( $code );

		if ( ! $discount ) {
			return new WP_Error( 'invalid_code', __( 'Invalid discount code.', 'avenia-digital-products' ) );
		}

		// Check expiration.
		if ( ! empty( $discount->expires_at ) && strtotime( $discount->expires_at ) < time() ) {
			return new WP_Error( 'expired_code', __( 'Discount code has expired.', 'avenia-digital-products' ) );
		}

		// Check usage limit.
		if ( ! empty( $discount->usage_limit ) && $discount->usage_count >= $discount->usage_limit ) {
			return new WP_Error( 'usage_limit_reached', __( 'Discount code usage limit reached.', 'avenia-digital-products' ) );
		}

		// Check per-customer usage limit.
		if ( ! empty( $discount->usage_limit_per_customer ) && $customer_id > 0 ) {
			$customer_usage = $this->get_customer_usage_count( $discount->code_id, $customer_id );
			if ( $customer_usage >= (int) $discount->usage_limit_per_customer ) {
				return new WP_Error( 'customer_usage_limit_reached', __( 'You have already used this discount code.', 'avenia-digital-products' ) );
			}
		}

		// Check minimum amount.
		if ( ! empty( $discount->minimum_amount ) && $cart_total < floatval( $discount->minimum_amount ) ) {
			/* translators: %s: minimum order amount */
			return new WP_Error( 'minimum_amount', sprintf( __( 'Minimum order amount: %s', 'avenia-digital-products' ), aveniadp_format_price( $discount->minimum_amount ) ) );
		}

		// Check applicable products.
		if ( ! empty( $discount->applicable_products ) ) {
			$applicable = json_decode( $discount->applicable_products, true );
			if ( is_array( $applicable ) && ! empty( $applicable ) ) {
				$has_applicable = false;
				foreach ( $product_ids as $product_id ) {
					if ( in_array( $product_id, $applicable, true ) ) {
						$has_applicable = true;
						break;
					}
				}
				if ( ! $has_applicable ) {
					return new WP_Error( 'not_applicable', __( 'Discount code not applicable to products in cart.', 'avenia-digital-products' ) );
				}
			}
		}

		return $discount;
	}

	/**
	 * Count completed orders using a discount code for a customer.
	 *
	 * @param int $code_id     Discount code ID.
	 * @param int $customer_id Customer ID.
	 * @return int
	 * @since 1.0.4
	 */
	public function get_customer_usage_count( $code_id, $customer_id ) {
		global $wpdb;

		$code_id     = absint( $code_id );
		$customer_id = absint( $customer_id );

		if ( ! $code_id || ! $customer_id ) {
			return 0;
		}

		$table = $wpdb->prefix . 'aveniadp_orders';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE discount_code_id = %d AND customer_id = %d AND status = 'completed'",
				$code_id,
				$customer_id
			)
		);
	}

	/**
	 * Calculate discount amount.
	 *
	 * @param object $discount   Discount object.
	 * @param float  $cart_total Cart total.
	 * @return float
	 * @since 1.0.0
	 */
	public function calculate_discount( $discount, $cart_total ) {
		$cart_total = max( 0, floatval( $cart_total ) );

		if ( 'percentage' === $discount->discount_type ) {
			$amount = ( $cart_total * floatval( $discount->discount_value ) ) / 100;
		} else {
			$amount = floatval( $discount->discount_value );
		}

		// Never discount more than the amount being discounted.
		return max( 0, min( $amount, $cart_total ) );
	}

	/**
	 * Create a discount code.
	 *
	 * @param array $data Discount data.
	 * @return int|WP_Error
	 * @since 1.0.0
	 */
	public function create_discount( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aveniadp_discount_codes';
		$row   = $this->prepare_discount_row( $data );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		if ( $this->get_discount_code( $row['code'] ) ) {
			return new WP_Error( 'duplicate_code', __( 'A discount code with that name already exists.', 'avenia-digital-products' ) );
		}

		$inserted = $wpdb->insert( $table, $row, $this->get_discount_formats_for_row( $row ) );

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', __( 'Could not create discount code.', 'avenia-digital-products' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a discount code.
	 *
	 * @param int   $code_id Code ID.
	 * @param array $data    Discount data.
	 * @return true|WP_Error
	 * @since 1.0.0
	 */
	public function update_discount( $code_id, $data ) {
		global $wpdb;

		$code_id  = absint( $code_id );
		$existing = $this->get_discount_by_id( $code_id );

		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Discount code not found.', 'avenia-digital-products' ) );
		}

		$table = $wpdb->prefix . 'aveniadp_discount_codes';
		$row   = $this->prepare_discount_row( $data, $existing );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code_id FROM {$table} WHERE code = %s AND code_id != %d",
				$row['code'],
				$code_id
			)
		);

		if ( $duplicate ) {
			return new WP_Error( 'duplicate_code', __( 'A discount code with that name already exists.', 'avenia-digital-products' ) );
		}

		$updated = $wpdb->update(
			$table,
			$row,
			array( 'code_id' => $code_id ),
			$this->get_discount_formats_for_row( $row ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'db_error', __( 'Could not update discount code.', 'avenia-digital-products' ) );
		}

		return true;
	}

	/**
	 * Sanitize discount data for database storage.
	 *
	 * @param array       $data     Raw discount data.
	 * @param object|null $existing Existing discount when updating.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	private function prepare_discount_row( $data, $existing = null ) {
		$code = isset( $data['code'] ) ? strtoupper( sanitize_text_field( $data['code'] ) ) : '';

		if ( '' === $code ) {
			return new WP_Error( 'invalid_code', __( 'Discount code is required.', 'avenia-digital-products' ) );
		}

		$discount_type = isset( $data['discount_type'] ) ? sanitize_text_field( $data['discount_type'] ) : 'percentage';
		if ( ! in_array( $discount_type, array( 'percentage', 'fixed' ), true ) ) {
			$discount_type = 'percentage';
		}

		$discount_value = isset( $data['discount_value'] ) ? floatval( $data['discount_value'] ) : 0;
		if ( $discount_value <= 0 ) {
			return new WP_Error( 'invalid_value', __( 'Discount value must be greater than zero.', 'avenia-digital-products' ) );
		}

		if ( 'percentage' === $discount_type && $discount_value > 100 ) {
			return new WP_Error( 'invalid_value', __( 'Percentage discount cannot exceed 100%.', 'avenia-digital-products' ) );
		}

		$usage_limit = null;
		if ( isset( $data['usage_limit'] ) && '' !== $data['usage_limit'] ) {
			$usage_limit = absint( $data['usage_limit'] );
		}

		$usage_limit_per_customer = null;
		if ( isset( $data['usage_limit_per_customer'] ) && '' !== $data['usage_limit_per_customer'] ) {
			$usage_limit_per_customer = absint( $data['usage_limit_per_customer'] );
		}

		$minimum_amount = null;
		if ( isset( $data['minimum_amount'] ) && '' !== $data['minimum_amount'] ) {
			$minimum_amount = floatval( $data['minimum_amount'] );
		}

		$expires_at = null;
		if ( ! empty( $data['expires_at'] ) ) {
			$expires_at = sanitize_text_field( $data['expires_at'] ) . ' 23:59:59';
		}

		$applicable_products = null;
		if ( ! empty( $data['applicable_products'] ) && is_array( $data['applicable_products'] ) ) {
			$product_ids = array_map( 'absint', $data['applicable_products'] );
			$product_ids = array_values( array_filter( $product_ids ) );
			if ( ! empty( $product_ids ) ) {
				$applicable_products = wp_json_encode( $product_ids );
			}
		}

		$row = array(
			'code'                => $code,
			'discount_type'       => $discount_type,
			'discount_value'      => $discount_value,
			'usage_limit'              => $usage_limit,
			'usage_limit_per_customer' => $usage_limit_per_customer,
			'expires_at'                 => $expires_at,
			'minimum_amount'      => $minimum_amount,
			'applicable_products' => $applicable_products,
		);

		if ( ! $existing ) {
			$row['usage_count'] = 0;
			$row['created_at']  = current_time( 'mysql' );
		}

		return $row;
	}

	/**
	 * Database format strings for a discount row.
	 *
	 * @param array $row Discount row data.
	 * @return array
	 * @since 1.0.0
	 */
	private function get_discount_formats_for_row( $row ) {
		$formats = array();

		foreach ( array_keys( $row ) as $key ) {
			switch ( $key ) {
				case 'discount_value':
				case 'minimum_amount':
					$formats[] = '%f';
					break;
				case 'usage_limit':
				case 'usage_limit_per_customer':
				case 'usage_count':
					$formats[] = '%d';
					break;
				default:
					$formats[] = '%s';
					break;
			}
		}

		return $formats;
	}

	/**
	 * Increment usage count.
	 *
	 * @param int $code_id Code ID.
	 * @since 1.0.0
	 */
	public function increment_usage( $code_id ) {
		global $wpdb;

		$code_id = absint( $code_id );
		$table = $wpdb->prefix . 'aveniadp_discount_codes';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET usage_count = usage_count + 1 WHERE code_id = %d",
				$code_id
			)
		);
	}

	// phpcs:enable
}
