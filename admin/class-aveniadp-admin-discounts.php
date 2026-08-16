<?php
/**
 * Admin discount code form handler.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin discounts class.
 *
 * @since 1.0.0
 */
class AVENIADP_Admin_Discounts {

	/**
	 * View context prepared during admin_init.
	 *
	 * @var array|null
	 */
	private static $context = null;

	/**
	 * Handle discount delete/save on admin_init.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_handle_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin discounts screen routing; destructive actions verify nonces below.
		if ( ! isset( $_GET['page'] ) || 'aveniadp-discounts' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::$context = self::build_default_context();

		if ( isset( $_GET['delete'] ) && check_admin_referer( 'delete_discount_' . absint( $_GET['delete'] ) ) ) {
			self::handle_delete( absint( $_GET['delete'] ) );
		}

		if ( isset( $_POST['aveniadp_save_discount'] ) && check_admin_referer( 'aveniadp_save_discount', 'aveniadp_discount_nonce' ) ) {
			self::handle_save( wp_unslash( $_POST ) );
		}

		if ( null === self::$context['discount'] && 'edit' === self::$context['action'] && self::$context['code_id'] ) {
			self::load_discount_for_edit();
		}
	}

	/**
	 * Get view context for the discounts admin screen.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_view_context() {
		if ( null === self::$context ) {
			self::$context = self::build_default_context();

			if ( null === self::$context['discount'] && 'edit' === self::$context['action'] && self::$context['code_id'] ) {
				self::load_discount_for_edit();
			}
		}

		return self::$context;
	}

	/**
	 * Build default view context from GET parameters.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function build_default_context() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin discounts list navigation; mutations verify nonces separately.
		return array(
			'action'   => isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list',
			'code_id'  => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'discount' => null,
			'notices'  => array(),
		);
		// phpcs:enable
	}

	/**
	 * Delete a discount code.
	 *
	 * @param int $discount_id Discount ID.
	 * @since 1.0.0
	 */
	private static function handle_delete( $discount_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-row delete on plugin-owned discount table after nonce check.
		$wpdb->delete(
			$wpdb->prefix . 'aveniadp_discount_codes',
			array( 'code_id' => $discount_id ),
			array( '%d' )
		);

		self::$context['notices'][] = array(
			'type'    => 'success',
			'message' => __( 'Discount code deleted.', 'avenia-digital-products' ),
		);
		self::$context['action']  = 'list';
		self::$context['code_id']   = 0;
	}

	/**
	 * Save a discount code from POST data.
	 *
	 * @param array $post Raw POST data (already unslashed).
	 * @since 1.0.0
	 */
	private static function handle_save( $post ) {
		$discounts_obj = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();

		$save_data = array(
			'code'                => isset( $post['code'] ) ? $post['code'] : '',
			'discount_type'       => isset( $post['discount_type'] ) ? $post['discount_type'] : 'percentage',
			'discount_value'      => isset( $post['discount_value'] ) ? $post['discount_value'] : '',
			'usage_limit'              => isset( $post['usage_limit'] ) ? $post['usage_limit'] : '',
			'usage_limit_per_customer' => isset( $post['usage_limit_per_customer'] ) ? $post['usage_limit_per_customer'] : '',
			'expires_at'                 => isset( $post['expires_at'] ) ? $post['expires_at'] : '',
			'minimum_amount'      => isset( $post['minimum_amount'] ) ? $post['minimum_amount'] : '',
			'applicable_products' => isset( $post['applicable_products'] ) ? array_map( 'absint', (array) $post['applicable_products'] ) : array(),
		);

		$edit_id = isset( $post['code_id'] ) ? absint( $post['code_id'] ) : 0;

		if ( $edit_id ) {
			$result = $discounts_obj->update_discount( $edit_id, $save_data );
		} else {
			$result = $discounts_obj->create_discount( $save_data );
		}

		if ( is_wp_error( $result ) ) {
			self::$context['notices'][] = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			self::$context['action']  = $edit_id ? 'edit' : 'add';
			self::$context['code_id']   = $edit_id;
			self::$context['discount']  = (object) array(
				'code_id'             => $edit_id,
				'code'                => $save_data['code'],
				'discount_type'       => $save_data['discount_type'],
				'discount_value'      => $save_data['discount_value'],
				'usage_limit'              => '' !== $save_data['usage_limit'] ? absint( $save_data['usage_limit'] ) : null,
				'usage_limit_per_customer' => '' !== $save_data['usage_limit_per_customer'] ? absint( $save_data['usage_limit_per_customer'] ) : null,
				'minimum_amount'           => '' !== $save_data['minimum_amount'] ? floatval( $save_data['minimum_amount'] ) : null,
				'expires_at'          => '' !== $save_data['expires_at'] ? $save_data['expires_at'] . ' 23:59:59' : null,
				'applicable_products' => ! empty( $save_data['applicable_products'] )
					? wp_json_encode( array_values( array_filter( array_map( 'absint', $save_data['applicable_products'] ) ) ) )
					: null,
			);
			return;
		}

		self::$context['notices'][] = array(
			'type'    => 'success',
			'message' => __( 'Discount code saved.', 'avenia-digital-products' ),
		);
		self::$context['action']  = 'list';
		self::$context['code_id']   = 0;
		self::$context['discount']  = null;
	}

	/**
	 * Load discount for edit action.
	 *
	 * @since 1.0.0
	 */
	private static function load_discount_for_edit() {
		$discounts_obj = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();
		$discount      = $discounts_obj->get_discount_by_id( self::$context['code_id'] );

		if ( ! $discount ) {
			self::$context['notices'][] = array(
				'type'    => 'error',
				'message' => __( 'Discount code not found.', 'avenia-digital-products' ),
			);
			self::$context['action'] = 'list';
			return;
		}

		self::$context['discount'] = $discount;
	}
}
