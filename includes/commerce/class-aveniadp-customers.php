<?php
/**
 * Customer management.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customers class.
 *
 * @since 1.0.0
 */
class AVENIADP_Customers {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

	/**
	 * Get customer by email.
	 *
	 * @param string $email Customer email.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_customer_by_email( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$table = $wpdb->prefix . 'aveniadp_customers';

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s",
				$email
			)
		);

		return $customer;
	}

	/**
	 * Get customer by ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_customer( $customer_id ) {
		global $wpdb;

		$customer_id = absint( $customer_id );
		$table = $wpdb->prefix . 'aveniadp_customers';

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d",
				$customer_id
			)
		);

		return $customer;
	}

	/**
	 * Create customer.
	 *
	 * @param string   $email   Customer email.
	 * @param int|null $user_id WordPress user ID (optional).
	 * @param string   $name    Customer display name (optional).
	 * @return int|WP_Error Customer ID or error.
	 * @since 1.0.0
	 */
	public function create_customer( $email, $user_id = null, $name = '' ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$name  = function_exists( 'aveniadp_sanitize_customer_name' ) ? aveniadp_sanitize_customer_name( $name ) : sanitize_text_field( $name );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'avenia-digital-products' ) );
		}

		// Check if customer already exists.
		$existing = $this->get_customer_by_email( $email );
		if ( $existing ) {
			if ( '' !== $name ) {
				$this->maybe_update_customer_name( $existing->customer_id, $name );
			}
			return $existing->customer_id;
		}

		$table = $wpdb->prefix . 'aveniadp_customers';
		$data  = array(
			'user_id'    => $user_id ? absint( $user_id ) : null,
			'email'      => $email,
			'created_at' => current_time( 'mysql' ),
		);
		$format = array( '%d', '%s', '%s' );

		if ( '' !== $name ) {
			$data['name'] = $name;
			$format[]     = '%s';
		}

		$result = $wpdb->insert( $table, $data, $format );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create customer.', 'avenia-digital-products' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a customer's name when provided and not already set.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $name        Display name.
	 * @return void
	 * @since 1.0.2
	 */
	public function maybe_update_customer_name( $customer_id, $name ) {
		global $wpdb;

		$name = function_exists( 'aveniadp_sanitize_customer_name' ) ? aveniadp_sanitize_customer_name( $name ) : sanitize_text_field( $name );
		if ( '' === $name ) {
			return;
		}

		$customer = $this->get_customer( $customer_id );
		if ( ! $customer || ! empty( $customer->name ) ) {
			return;
		}

		$table = $wpdb->prefix . 'aveniadp_customers';
		$wpdb->update(
			$table,
			array( 'name' => $name ),
			array( 'customer_id' => absint( $customer_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Sync customer name to linked WordPress user profile.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Display name.
	 * @return void
	 * @since 1.0.2
	 */
	private function sync_name_to_wp_user( $user_id, $name ) {
		$user_id = absint( $user_id );
		$name    = function_exists( 'aveniadp_sanitize_customer_name' ) ? aveniadp_sanitize_customer_name( $name ) : sanitize_text_field( $name );

		if ( ! $user_id || '' === $name ) {
			return;
		}

		$parts = preg_split( '/\s+/', $name, 2 );
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
				'first_name'   => $parts[0],
				'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			)
		);
	}

	/**
	 * Update customer.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param array  $data        Customer data.
	 * @return bool|WP_Error
	 * @since 1.0.0
	 */
	public function update_customer( $customer_id, $data ) {
		global $wpdb;

		$customer_id = absint( $customer_id );
		$table = $wpdb->prefix . 'aveniadp_customers';

		$update_data = array();
		$format = array();

		if ( isset( $data['user_id'] ) ) {
			$update_data['user_id'] = absint( $data['user_id'] );
			$format[] = '%d';
		}

		if ( isset( $data['email'] ) ) {
			$email = sanitize_email( $data['email'] );
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'avenia-digital-products' ) );
			}
			$update_data['email'] = $email;
			$format[] = '%s';
		}

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = function_exists( 'aveniadp_sanitize_customer_name' )
				? aveniadp_sanitize_customer_name( $data['name'] )
				: sanitize_text_field( $data['name'] );
			$format[] = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'customer_id' => $customer_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get customer orders.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_customer_orders( $customer_id ) {
		global $wpdb;

		$customer_id = absint( $customer_id );
		$table = $wpdb->prefix . 'aveniadp_orders';

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
				$customer_id
			)
		);

		return $orders;
	}

	/**
	 * Create a WordPress user account for a customer when one does not exist.
	 *
	 * @param int  $customer_id Customer ID.
	 * @param bool $send_email  Whether to send the account confirmation email.
	 * @return int|WP_Error User ID, 0 when skipped, or error.
	 * @since 1.0.0
	 */
	public function maybe_create_wp_account( $customer_id, $send_email = true ) {
		$customer = $this->get_customer( $customer_id );
		if ( ! $customer ) {
			return 0;
		}

		if ( ! empty( $customer->user_id ) ) {
			return absint( $customer->user_id );
		}

		$email = $customer->email;
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			$this->update_customer( $customer_id, array( 'user_id' => $existing_user->ID ) );
			return $existing_user->ID;
		}

		$user_id = wp_create_user( $email, wp_generate_password(), $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$this->update_customer( $customer_id, array( 'user_id' => $user_id ) );

		if ( ! empty( $customer->name ) ) {
			$this->sync_name_to_wp_user( $user_id, $customer->name );
		}

		if ( $send_email ) {
			$emails = function_exists( 'aveniadp_emails' ) ? aveniadp_emails() : new AVENIADP_Emails();
			$emails->send_account_confirmation( $user_id, $email );
		}

		return $user_id;
	}

	// phpcs:enable
}
