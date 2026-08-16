<?php
/**
 * Shopping cart functionality.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart class.
 *
 * Uses WordPress-native storage: cookie + transient (no PHP sessions).
 *
 * @since 1.0.0
 */
class AVENIADP_Cart {

	/**
	 * Cookie name for cart key.
	 *
	 * @var string
	 */
	const CART_COOKIE = 'aveniadp_cart_key';

	/**
	 * Transient key prefix (suffix is cart key).
	 *
	 * @var string
	 */
	const CART_TRANSIENT_PREFIX = 'aveniadp_cart_';

	/**
	 * Transient key prefix for cart meta (suffix is cart key).
	 *
	 * @var string
	 */
	const CART_META_TRANSIENT_PREFIX = 'aveniadp_cart_meta_';

	/**
	 * Cart data TTL in seconds (2 days).
	 *
	 * @var int
	 */
	const CART_TTL = 172800;

	/**
	 * Initialize cart.
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
		add_shortcode( 'aveniadp_cart', array( $this, 'cart_shortcode' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_aveniadp_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_aveniadp_remove_from_cart', array( $this, 'ajax_remove_from_cart' ) );
		add_action( 'wp_ajax_aveniadp_update_cart', array( $this, 'ajax_update_cart' ) );
		add_action( 'wp_ajax_aveniadp_get_price', array( $this, 'ajax_get_price' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_remove_from_cart', array( $this, 'ajax_remove_from_cart' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_update_cart', array( $this, 'ajax_update_cart' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_get_price', array( $this, 'ajax_get_price' ) );
		add_action( 'wp_ajax_aveniadp_get_cart_drawer', array( $this, 'ajax_get_cart_drawer' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_get_cart_drawer', array( $this, 'ajax_get_cart_drawer' ) );
		add_action( 'wp_ajax_aveniadp_apply_discount', array( $this, 'ajax_apply_discount' ) );
		add_action( 'wp_ajax_aveniadp_remove_discount', array( $this, 'ajax_remove_discount' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_apply_discount', array( $this, 'ajax_apply_discount' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_remove_discount', array( $this, 'ajax_remove_discount' ) );

		add_action( 'init', array( $this, 'maybe_handle_direct_checkout_link' ), 20 );
	}

	/**
	 * Get or create cart key for current request.
	 *
	 * @param bool $create_if_missing If true, create and set cookie when missing.
	 * @return string Empty string if no cart key (and not creating).
	 * @since 1.0.0
	 */
	private function get_cart_key( $create_if_missing = false ) {
		static $request_key = null;

		// Allow a later create_if_missing call to run after an earlier empty lookup.
		if ( null !== $request_key && ( '' !== $request_key || ! $create_if_missing ) ) {
			return $request_key;
		}

		$key = isset( $_COOKIE[ self::CART_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::CART_COOKIE ] ) ) : '';

		if ( '' === $key && $create_if_missing ) {
			$key = bin2hex( random_bytes( 16 ) );
			$expire = time() + self::CART_TTL;
			if ( ! headers_sent() ) {
				setcookie(
					self::CART_COOKIE,
					$key,
					array(
						'expires'  => $expire,
						'path'     => COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			}
		}

		$request_key = $key;
		return $key;
	}

	/**
	 * Get cart contents from transient (keyed by cookie).
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_cart() {
		$key = $this->get_cart_key( false );
		if ( '' === $key ) {
			return array();
		}
		$cart = get_transient( self::CART_TRANSIENT_PREFIX . $key );
		return is_array( $cart ) ? $cart : array();
	}

	/**
	 * Persist cart to transient.
	 *
	 * @param array $cart Cart data.
	 * @since 1.0.0
	 */
	private function save_cart( $cart ) {
		$key = $this->get_cart_key( true );
		if ( '' === $key ) {
			return;
		}
		set_transient( self::CART_TRANSIENT_PREFIX . $key, $cart, self::CART_TTL );
	}

	/**
	 * Add item to cart.
	 *
	 * @param int $product_id       Product ID.
	 * @param int $license_quantity License quantity.
	 * @return bool|WP_Error
	 * @since 1.0.0
	 */
	public function add_to_cart( $product_id, $license_quantity = 1 ) {
		$product_id       = absint( $product_id );
		$license_quantity = aveniadp_sanitize_license_quantity( $license_quantity, 1 );

		// Validate product.
		if ( AVENIADP_PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product.', 'avenia-digital-products' ) );
		}

		if ( '1' === get_post_meta( $product_id, '_aveniadp_free_download', true ) ) {
			return new WP_Error( 'free_product', __( 'This product is free—use the download button on the product page.', 'avenia-digital-products' ) );
		}

		// Check if product already in cart (limit 1 per product).
		$cart = $this->get_cart();
		if ( isset( $cart[ $product_id ] ) ) {
			return new WP_Error( 'already_in_cart', __( 'Product already in cart.', 'avenia-digital-products' ) );
		}

		// Get price.
		$line_item = $this->build_line_item( $product_id, $license_quantity );
		if ( is_wp_error( $line_item ) ) {
			return $line_item;
		}

		if ( $line_item['price'] <= 0 ) {
			return new WP_Error( 'invalid_price', __( 'Product price must be greater than 0.', 'avenia-digital-products' ) );
		}

		// Ensure we have a cart key (creates cookie on first add).
		$this->get_cart_key( true );

		$cart = $this->get_cart();
		$cart[ $product_id ] = $line_item;
		$this->save_cart( $cart );

		return true;
	}

	/**
	 * Build cart line item data for a product selection.
	 *
	 * @param int $product_id       Product ID.
	 * @param int $license_quantity License quantity.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	private function build_line_item( $product_id, $license_quantity = 1 ) {
		$product_id       = absint( $product_id );
		$license_quantity = aveniadp_sanitize_license_quantity( $license_quantity, 1 );

		$price = $this->calculate_price( $product_id, $license_quantity );
		if ( is_wp_error( $price ) ) {
			return $price;
		}

		$item = array(
			'product_id'       => $product_id,
			'license_quantity' => $license_quantity,
			'price'            => floatval( $price ),
		);

		/**
		 * Filter a cart line item before it is stored.
		 *
		 * @since 1.0.0
		 *
		 * @param array $item             Cart line item.
		 * @param int   $product_id       Product ID.
		 * @param int   $license_quantity License quantity from the item.
		 */
		return apply_filters( 'aveniadp_cart_line_item', $item, $product_id, $license_quantity );
	}

	/**
	 * Add a product to the cart or update it when already present.
	 *
	 * @param int $product_id       Product ID.
	 * @param int $license_quantity License quantity.
	 * @return bool|WP_Error
	 * @since 1.0.0
	 */
	public function add_or_update_cart_item( $product_id, $license_quantity = 1 ) {
		$product_id = absint( $product_id );
		$cart       = $this->get_cart();

		if ( isset( $cart[ $product_id ] ) ) {
			return $this->update_cart_item( $product_id, $license_quantity );
		}

		return $this->add_to_cart( $product_id, $license_quantity );
	}

	/**
	 * Handle direct checkout links (?aveniadp_buy=123).
	 *
	 * @since 1.0.0
	 */
	public function maybe_handle_direct_checkout_link() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public buy-now URL; product ID and options are sanitized below.
		if ( ! isset( $_GET['aveniadp_buy'] ) ) {
			return;
		}

		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-buy' );
		}

		$product_id = absint( wp_unslash( $_GET['aveniadp_buy'] ) );
		if ( ! $product_id ) {
			return;
		}

		$license_quantity = isset( $_GET['license_quantity'] )
			? aveniadp_sanitize_license_quantity( wp_unslash( $_GET['license_quantity'] ), 1 )
			: 1;

		$result = $this->add_or_update_cart_item( $product_id, $license_quantity );

		if ( is_wp_error( $result ) ) {
			$redirect = get_permalink( $product_id );
			if ( ! $redirect ) {
				$redirect = home_url( '/' );
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'aveniadp_buy_error' => rawurlencode( $result->get_error_code() ),
					),
					$redirect
				)
			);
			exit;
		}

		$checkout_url = aveniadp_get_configured_checkout_page_url();
		if ( ! $checkout_url ) {
			$checkout_url = aveniadp_get_checkout_page_url();
		}

		// phpcs:enable
		wp_safe_redirect( $checkout_url ? $checkout_url : home_url( '/' ) );
		exit;
	}

	/**
	 * Update an existing cart item's license and billing options.
	 *
	 * @param int $product_id       Product ID.
	 * @param int $license_quantity License quantity.
	 * @return bool|WP_Error
	 * @since 1.0.0
	 */
	public function update_cart_item( $product_id, $license_quantity = 1 ) {
		$product_id = absint( $product_id );
		$cart       = $this->get_cart();

		if ( ! isset( $cart[ $product_id ] ) ) {
			return new WP_Error( 'not_in_cart', __( 'Product not found in cart.', 'avenia-digital-products' ) );
		}

		if ( AVENIADP_PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product.', 'avenia-digital-products' ) );
		}

		$line_item = $this->build_line_item( $product_id, $license_quantity );
		if ( is_wp_error( $line_item ) ) {
			return $line_item;
		}

		if ( $line_item['price'] <= 0 ) {
			return new WP_Error( 'invalid_price', __( 'Product price must be greater than 0.', 'avenia-digital-products' ) );
		}

		$cart[ $product_id ] = $line_item;
		$this->save_cart( $cart );

		return true;
	}

	/**
	 * Remove item from cart.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function remove_from_cart( $product_id ) {
		$product_id = absint( $product_id );
		$cart = $this->get_cart();

		if ( isset( $cart[ $product_id ] ) ) {
			unset( $cart[ $product_id ] );
			$this->save_cart( $cart );

			if ( empty( $cart ) ) {
				$this->clear_cart_meta();
			}

			return true;
		}

		return false;
	}

	/**
	 * Calculate price.
	 *
	 * @param int $product_id       Product ID.
	 * @param int $license_quantity License quantity.
	 * @return float|WP_Error
	 * @since 1.0.0
	 */
	public function calculate_price( $product_id, $license_quantity ) {
		$pricing_matrix = get_post_meta( $product_id, '_aveniadp_pricing_matrix', true );

		if ( empty( $pricing_matrix ) || ! is_array( $pricing_matrix ) ) {
			return new WP_Error( 'no_pricing', __( 'Product pricing not configured.', 'avenia-digital-products' ) );
		}

		$license_key = (string) $license_quantity;

		if ( ! isset( $pricing_matrix[ $license_key ] ) ) {
			return new WP_Error( 'invalid_license_quantity', __( 'Invalid number of sites selected.', 'avenia-digital-products' ) );
		}

		$license_pricing = $pricing_matrix[ $license_key ];

		if ( isset( $license_pricing['one-time'] ) ) {
			$price = floatval( $license_pricing['one-time'] );

			/**
			 * Filter calculated product price.
			 *
			 * @since 1.0.0
			 *
			 * @param float $price            Calculated price.
			 * @param int   $product_id       Product ID.
			 * @param int   $license_quantity License quantity.
			 */
			return apply_filters( 'aveniadp_product_price', $price, $product_id, $license_quantity );
		}

		if ( isset( $license_pricing['price'] ) ) {
			return apply_filters( 'aveniadp_product_price', floatval( $license_pricing['price'] ), $product_id, $license_quantity );
		}

		$prices = array_filter(
			array_map( 'floatval', array_values( $license_pricing ) ),
			function ( $amount ) {
				return $amount > 0;
			}
		);
		if ( empty( $prices ) ) {
			return new WP_Error( 'no_price', __( 'No price configured for this number of sites.', 'avenia-digital-products' ) );
		}

		return apply_filters( 'aveniadp_product_price', floatval( reset( $prices ) ), $product_id, $license_quantity );
	}

	/**
	 * Get cart total.
	 *
	 * @return float
	 * @since 1.0.0
	 */
	public function get_cart_total() {
		$cart  = $this->get_cart();
		$total = 0.0;

		foreach ( $cart as $item ) {
			$total += isset( $item['price'] ) ? max( 0, floatval( $item['price'] ) ) : 0.0;
		}

		/**
		 * Filter the cart total before discounts and tax.
		 *
		 * @since 1.0.4
		 *
		 * @param float $total Amount due at checkout.
		 * @param array $cart  Cart items.
		 */
		return apply_filters( 'aveniadp_cart_due_today', $total, $cart );
	}

	/**
	 * Get cart meta (e.g. applied discount code).
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function get_cart_meta() {
		$key = $this->get_cart_key( false );
		if ( '' === $key ) {
			return array();
		}

		$meta = get_transient( self::CART_META_TRANSIENT_PREFIX . $key );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Persist cart meta.
	 *
	 * @param array $meta Cart meta.
	 * @since 1.0.0
	 */
	private function save_cart_meta( $meta ) {
		$key = $this->get_cart_key( true );
		if ( '' === $key ) {
			return;
		}

		set_transient( self::CART_META_TRANSIENT_PREFIX . $key, $meta, self::CART_TTL );
	}

	/**
	 * Clear cart meta.
	 *
	 * @since 1.0.0
	 */
	private function clear_cart_meta() {
		$key = $this->get_cart_key( false );
		if ( '' !== $key ) {
			delete_transient( self::CART_META_TRANSIENT_PREFIX . $key );
		}
	}

	/**
	 * Get applied discount code from cart session.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_applied_discount_code() {
		$meta = $this->get_cart_meta();
		return ! empty( $meta['discount_code'] ) ? sanitize_text_field( $meta['discount_code'] ) : '';
	}

	/**
	 * Apply a discount code to the cart session.
	 *
	 * @param string $code        Discount code.
	 * @param int    $customer_id Customer ID (optional).
	 * @return true|WP_Error
	 * @since 1.0.0
	 */
	public function apply_discount_code( $code, $customer_id = 0 ) {
		$code = sanitize_text_field( $code );
		if ( '' === $code ) {
			return new WP_Error( 'empty_code', __( 'Please enter a discount code.', 'avenia-digital-products' ) );
		}

		if ( empty( $this->get_cart() ) ) {
			return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'avenia-digital-products' ) );
		}

		$customer_id = $this->resolve_customer_id( $customer_id );
		$subtotal    = $this->get_cart_total();
		$discounts   = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();
		$product_ids = array_keys( $this->get_cart() );
		$discount    = $discounts->validate_discount_code( $code, $subtotal, $product_ids, $customer_id );

		if ( is_wp_error( $discount ) ) {
			return $discount;
		}

		$this->save_cart_meta(
			array(
				'discount_code' => $code,
			)
		);

		return true;
	}

	/**
	 * Remove applied discount code from cart session.
	 *
	 * @since 1.0.0
	 */
	public function remove_discount_code() {
		$this->clear_cart_meta();
	}

	/**
	 * Get cart totals including any applied discount.
	 *
	 * @param int $customer_id Customer ID (optional).
	 * @return array
	 * @since 1.0.0
	 */
	public function get_cart_totals( $customer_id = 0 ) {
		$customer_id     = $this->resolve_customer_id( $customer_id );
		$subtotal        = $this->get_cart_total();
		$discount_code   = $this->get_applied_discount_code();
		$discount_amount = 0;

		if ( $discount_code ) {
			$discounts   = function_exists( 'aveniadp_discounts' ) ? aveniadp_discounts() : new AVENIADP_Discounts();
			$product_ids = array_keys( $this->get_cart() );
			$discount    = $discounts->validate_discount_code( $discount_code, $subtotal, $product_ids, $customer_id );

			if ( is_wp_error( $discount ) ) {
				$this->remove_discount_code();
				$discount_code = '';
			} else {
				$discount_amount = $discounts->calculate_discount( $discount, $subtotal );
			}
		}

		return aveniadp_apply_tax_to_cart_totals(
			array(
				'subtotal'        => $subtotal,
				'discount_amount' => $discount_amount,
				'total'           => max( 0, $subtotal - $discount_amount ),
				'discount_code'   => $discount_code,
			)
		);
	}

	/**
	 * Format cart totals for AJAX responses.
	 *
	 * @param array $totals Cart totals from get_cart_totals().
	 * @return array
	 * @since 1.0.0
	 */
	private function format_cart_totals_response( $totals ) {
		return array(
			'subtotal'                => aveniadp_format_price( $totals['subtotal'] ),
			'discount_amount'         => aveniadp_format_price( $totals['discount_amount'] ),
			'tax_amount'              => ! empty( $totals['show_tax_line'] ) ? aveniadp_format_price( $totals['tax_amount'] ) : '',
			'tax_label'               => ! empty( $totals['show_tax_line'] ) ? $totals['tax_label'] : '',
			'total'                   => aveniadp_format_price( $totals['total'] ),
			'discount_code'           => $totals['discount_code'],
			'has_discount'            => ! empty( $totals['discount_code'] ) && $totals['discount_amount'] > 0,
			'has_tax'                 => ! empty( $totals['show_tax_line'] ),
			'show_tax_inclusive_note' => ! empty( $totals['show_tax_inclusive_note'] ),
		);
	}

	/**
	 * Build cart drawer payload for AJAX responses.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_cart_drawer_data() {
		$items = array();

		foreach ( $this->get_cart() as $product_id => $item ) {
			$product = get_post( $product_id );
			if ( ! $product ) {
				continue;
			}

			$thumbnail = '';
			if ( has_post_thumbnail( $product_id ) ) {
				$thumbnail = get_the_post_thumbnail_url( $product_id, 'aveniadp-product-card' );
			}

			$items[] = array(
				'product_id'       => $product_id,
				'title'            => $product->post_title,
				'price_html'       => aveniadp_get_cart_item_price_html( $product_id, $item ),
				'details'          => aveniadp_get_cart_item_details_text( $product_id, $item ),
				'license_quantity' => isset( $item['license_quantity'] ) ? aveniadp_sanitize_license_quantity( $item['license_quantity'], 1 ) : 1,
				'edit_url'         => function_exists( 'aveniadp_product_has_purchase_options' ) && aveniadp_product_has_purchase_options( $product_id )
					? aveniadp_get_cart_edit_product_url( $product_id )
					: '',
				'thumbnail'        => $thumbnail ? $thumbnail : '',
				'product_url'      => get_permalink( $product_id ),
			);
		}

		$totals = $this->get_cart_totals();

		return array(
			'items'  => $items,
			'count'  => count( $items ),
			'totals' => $this->format_cart_totals_response( $totals ),
		);
	}

	/**
	 * Whether the side cart drawer should open after cart changes.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function should_use_cart_drawer() {
		return 'yes' !== get_option( 'aveniadp_skip_cart_to_checkout', 'no' );
	}

	/**
	 * Append drawer payload to an AJAX success response when appropriate.
	 *
	 * @param array $response Response data.
	 * @return array
	 * @since 1.0.0
	 */
	private function maybe_add_drawer_to_response( $response ) {
		if ( ! $this->should_use_cart_drawer() ) {
			return $response;
		}

		$response['drawer'] = $this->get_cart_drawer_data();
		$response['open_drawer'] = true;

		return $response;
	}

	/**
	 * Clear cart.
	 *
	 * @since 1.0.0
	 */
	public function clear_cart() {
		$key = $this->get_cart_key( false );
		if ( '' !== $key ) {
			delete_transient( self::CART_TRANSIENT_PREFIX . $key );
			delete_transient( self::CART_META_TRANSIENT_PREFIX . $key );
		}
	}

	/**
	 * Cart shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function cart_shortcode( $atts ) {
		return aveniadp_get_template_html( 'cart.php' );
	}

	/**
	 * AJAX get cart drawer contents.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_cart_drawer() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		wp_send_json_success(
			array(
				'drawer' => $this->get_cart_drawer_data(),
			)
		);
	}

	/**
	 * AJAX add to cart.
	 *
	 * @since 1.0.0
	 */
	public function ajax_add_to_cart() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$license_quantity = isset( $_POST['license_quantity'] ) ? aveniadp_sanitize_license_quantity( wp_unslash( $_POST['license_quantity'] ), 1 ) : 1; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via aveniadp_sanitize_license_quantity() after AJAX nonce check.

		$result = $this->add_to_cart( $product_id, $license_quantity );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$response = array(
			'message'              => __( 'Product added to cart.', 'avenia-digital-products' ),
			'cart_count'           => count( $this->get_cart() ),
			'cart_total'           => $this->get_cart_total(),
			'cart_total_formatted' => aveniadp_format_price( $this->get_cart_total() ),
		);

		if ( 'yes' === get_option( 'aveniadp_skip_cart_to_checkout', 'no' ) ) {
			$checkout_url = aveniadp_get_checkout_page_url();
			if ( $checkout_url ) {
				$response['redirect'] = $checkout_url;
			}
		} else {
			$response = $this->maybe_add_drawer_to_response( $response );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX remove from cart.
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_from_cart() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		$result = $this->remove_from_cart( $product_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Product not found in cart.', 'avenia-digital-products' ) ) );
		}

		$response = array(
			'message'    => __( 'Product removed from cart.', 'avenia-digital-products' ),
			'cart_count' => count( $this->get_cart() ),
			'cart_total' => $this->get_cart_total(),
		);

		if ( $this->should_use_cart_drawer() ) {
			$response['drawer'] = $this->get_cart_drawer_data();
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX update cart.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_cart() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$product_id       = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$license_quantity = isset( $_POST['license_quantity'] ) ? aveniadp_sanitize_license_quantity( wp_unslash( $_POST['license_quantity'] ), 1 ) : 1; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via aveniadp_sanitize_license_quantity() after AJAX nonce check.

		$result = $this->update_cart_item( $product_id, $license_quantity );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$response = array(
			'message'    => __( 'Cart updated.', 'avenia-digital-products' ),
			'cart_count' => count( $this->get_cart() ),
			'cart_total' => $this->get_cart_total(),
		);

		if ( $this->should_use_cart_drawer() ) {
			$response = $this->maybe_add_drawer_to_response( $response );
		} else {
			$cart_url = function_exists( 'aveniadp_get_cart_page_url' ) ? aveniadp_get_cart_page_url() : '';
			if ( $cart_url ) {
				$response['redirect'] = $cart_url;
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX get price.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_price() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$product_id       = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$license_quantity = isset( $_POST['license_quantity'] ) ? aveniadp_sanitize_license_quantity( wp_unslash( $_POST['license_quantity'] ), 1 ) : 1; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via aveniadp_sanitize_license_quantity() after AJAX nonce check.

		$price = $this->calculate_price( $product_id, $license_quantity );

		if ( is_wp_error( $price ) ) {
			wp_send_json_error( array( 'message' => $price->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'price' => aveniadp_get_product_price_display_html( $product_id, $license_quantity ),
			)
		);
	}

	/**
	 * AJAX apply discount code.
	 *
	 * @since 1.0.0
	 */
	public function ajax_apply_discount() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$code = isset( $_POST['discount_code'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_code'] ) ) : '';
		$result = $this->apply_discount_code( $code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$response = array(
			'message' => __( 'Discount code applied.', 'avenia-digital-products' ),
			'totals'  => $this->format_cart_totals_response( $this->get_cart_totals() ),
		);

		if ( $this->should_use_cart_drawer() ) {
			$response['drawer'] = $this->get_cart_drawer_data();
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX remove discount code.
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_discount() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$this->remove_discount_code();

		$response = array(
			'message' => __( 'Discount code removed.', 'avenia-digital-products' ),
			'totals'  => $this->format_cart_totals_response( $this->get_cart_totals() ),
		);

		if ( $this->should_use_cart_drawer() ) {
			$response['drawer'] = $this->get_cart_drawer_data();
		}

		wp_send_json_success( $response );
	}

	/**
	 * Resolve customer ID for discount validation.
	 *
	 * @param int $customer_id Explicit customer ID when known.
	 * @return int
	 * @since 1.0.4
	 */
	private function resolve_customer_id( $customer_id = 0 ) {
		$customer_id = absint( $customer_id );
		if ( $customer_id > 0 ) {
			return $customer_id;
		}

		if ( ! is_user_logged_in() ) {
			return 0;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() || ! is_email( $user->user_email ) ) {
			return 0;
		}

		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer_by_email( $user->user_email );

		return $customer ? absint( $customer->customer_id ) : 0;
	}
}
