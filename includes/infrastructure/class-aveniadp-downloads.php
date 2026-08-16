<?php
/**
 * Download delivery system.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads class.
 *
 * @since 1.0.0
 */
class AVENIADP_Downloads {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

	/**
	 * Initialize downloads.
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
		// Download endpoint.
		add_action( 'init', array( $this, 'add_download_endpoint' ), 10 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_account_download' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_download' ) );

		add_action( 'wp_ajax_aveniadp_request_free_download', array( $this, 'ajax_request_free_download' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_request_free_download', array( $this, 'ajax_request_free_download' ) );
	}

	/**
	 * Register download rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule( '^aveniadp-download/([^/]+)/?', 'index.php?aveniadp_download=$matches[1]', 'top' );
		add_rewrite_rule( '^aveniadp-account-download/([0-9]+)/?', 'index.php?aveniadp_account_download=$matches[1]', 'top' );
		add_rewrite_tag( '%aveniadp_download%', '([^&]+)' );
		add_rewrite_tag( '%aveniadp_account_download%', '([0-9]+)' );
	}

	/**
	 * Flush rewrite rules after plugin updates when needed.
	 *
	 * @since 1.0.0
	 */
	public function maybe_flush_rewrite_rules() {
		$version_flushed = get_option( 'aveniadp_download_rewrite_version' ) === '1.0.1';
		if ( $version_flushed && $this->rewrite_rules_registered() ) {
			return;
		}

		self::register_rewrite_rules();
		flush_rewrite_rules( false );
		update_option( 'aveniadp_download_rewrite_version', '1.0.1' );
	}

	/**
	 * Allow aveniadp_download in public query vars.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'aveniadp_download';
		$vars[] = 'aveniadp_account_download';
		return $vars;
	}

	/**
	 * Add download endpoint.
	 *
	 * @since 1.0.0
	 */
	public function add_download_endpoint() {
		self::register_rewrite_rules();
	}

	/**
	 * Resolve the requested download access key from query vars or the URL path.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function get_requested_download_access_key() {
		global $wp_query;

		if ( isset( $wp_query->query_vars['aveniadp_download'] ) && '' !== $wp_query->query_vars['aveniadp_download'] ) {
			return sanitize_text_field( $wp_query->query_vars['aveniadp_download'] );
		}

		$query_var = get_query_var( 'aveniadp_download', '' );
		if ( '' !== $query_var ) {
			return sanitize_text_field( $query_var );
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			if ( is_string( $path ) && preg_match( '#/aveniadp-download/([^/]+)/?$#', $path, $matches ) ) {
				return sanitize_text_field( $matches[1] );
			}
		}

		return '';
	}

	/**
	 * Whether pretty download rewrite rules are stored.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function rewrite_rules_registered() {
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) ) {
			return false;
		}

		$found_download = false;
		$found_account  = false;

		foreach ( $rules as $pattern => $rewrite ) {
			if ( false !== strpos( $pattern, 'aveniadp-download' ) || false !== strpos( $rewrite, 'aveniadp_download' ) ) {
				$found_download = true;
			}
			if ( false !== strpos( $pattern, 'aveniadp-account-download' ) || false !== strpos( $rewrite, 'aveniadp_account_download' ) ) {
				$found_account = true;
			}
		}

		return $found_download && $found_account;
	}

	/**
	 * Handle authenticated account downloads (not subject to email link expiry).
	 *
	 * @since 1.0.0
	 */
	public function handle_account_download() {
		$download_id = $this->get_requested_account_download_id();

		if ( ! $download_id ) {
			return;
		}

		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-account-download' );
		}

		if ( ! is_user_logged_in() ) {
			$login_url = function_exists( 'aveniadp_get_login_page_url' ) ? aveniadp_get_login_page_url() : wp_login_url();
			$redirect  = function_exists( 'aveniadp_get_account_download_url' ) ? aveniadp_get_account_download_url( $download_id ) : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login_url ) );
			exit;
		}

		if ( ! $this->verify_account_download_nonce( $download_id ) ) {
			wp_die( esc_html__( 'Invalid download request.', 'avenia-digital-products' ), esc_html__( 'Download Error', 'avenia-digital-products' ), array( 'response' => 403 ) );
		}

		$download = $this->get_download_by_id( $download_id );
		if ( ! $download ) {
			wp_die( esc_html__( 'Invalid download link.', 'avenia-digital-products' ), esc_html__( 'Download Error', 'avenia-digital-products' ), array( 'response' => 404 ) );
		}

		$user = wp_get_current_user();
		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer_by_email( $user->user_email );

		if ( ! $customer || ! $this->account_allows_download( $download, (int) $customer->customer_id ) ) {
			wp_die( esc_html__( 'You do not have access to this download.', 'avenia-digital-products' ), esc_html__( 'Download Unavailable', 'avenia-digital-products' ), array( 'response' => 403 ) );
		}

		$this->deliver_product_download( $download );
	}

	/**
	 * Resolve account download ID from query vars or the URL path.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private function get_requested_account_download_id() {
		global $wp_query;

		if ( isset( $wp_query->query_vars['aveniadp_account_download'] ) && '' !== $wp_query->query_vars['aveniadp_account_download'] ) {
			return absint( $wp_query->query_vars['aveniadp_account_download'] );
		}

		$query_var = get_query_var( 'aveniadp_account_download', '' );
		if ( '' !== $query_var ) {
			return absint( $query_var );
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			if ( is_string( $path ) && preg_match( '#/aveniadp-account-download/([0-9]+)/?$#', $path, $matches ) ) {
				return absint( $matches[1] );
			}
		}

		return 0;
	}

	/**
	 * Verify the account download nonce for a resolved download ID.
	 *
	 * @param int $download_id Download ID.
	 * @return bool
	 * @since 1.0.0
	 */
	private function verify_account_download_nonce( $download_id ) {
		$download_id = absint( $download_id );
		if ( ! $download_id ) {
			return false;
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'aveniadp-account-download-' . $download_id );
	}

	/**
	 * Handle email download links (expire after a short period).
	 *
	 * @since 1.0.0
	 */
	public function handle_download() {
		global $wp_query;

		$access_key = $this->get_requested_download_access_key();

		if ( '' === $access_key ) {
			return;
		}

		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-download' );
		}

		$download = $this->get_download_by_key( $access_key );

		if ( ! $download ) {
			wp_die( esc_html__( 'Invalid download link.', 'avenia-digital-products' ), esc_html__( 'Download Error', 'avenia-digital-products' ), array( 'response' => 404 ) );
		}

		$product_id = absint( $download->product_id );

		// Free catalog downloads use order_id = 0; revoke if product is no longer marked free.
		if ( 0 === (int) $download->order_id ) {
			if ( '1' !== get_post_meta( $product_id, '_aveniadp_free_download', true ) ) {
				wp_die( esc_html__( 'This free download is no longer available.', 'avenia-digital-products' ), esc_html__( 'Download Error', 'avenia-digital-products' ), array( 'response' => 410 ) );
			}
		} elseif ( ! $this->order_allows_download( (int) $download->order_id, $product_id ) ) {
			wp_die( esc_html__( 'Download access for this order has been revoked.', 'avenia-digital-products' ), esc_html__( 'Download Unavailable', 'avenia-digital-products' ), array( 'response' => 403 ) );
		}

		// Email links expire after a short period; account downloads use a separate route.
		if ( ! empty( $download->expires_at ) && strtotime( $download->expires_at ) < time() ) {
			$account_url = function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' );
			$message     = sprintf(
				/* translators: %s: account page URL */
				__( 'This download link has expired. <a href="%s">Log in to your account</a> to download anytime.', 'avenia-digital-products' ),
				esc_url( $account_url )
			);
			wp_die( wp_kses_post( $message ), esc_html__( 'Download Expired', 'avenia-digital-products' ), array( 'response' => 403 ) );
		}

		$this->deliver_product_download( $download );
	}

	/**
	 * Deliver a product file after validation.
	 *
	 * @param object $download Download row.
	 * @since 1.0.0
	 */
	private function deliver_product_download( $download ) {
		$product_id = absint( $download->product_id );

		if ( ! function_exists( 'aveniadp_product_has_download_file' ) || ! aveniadp_product_has_download_file( $product_id ) ) {
			wp_die( esc_html__( 'File not found.', 'avenia-digital-products' ), esc_html__( 'File Not Found', 'avenia-digital-products' ), array( 'response' => 404 ) );
		}

		if ( 0 !== (int) $download->order_id ) {
			$this->increment_download_count( $download->download_id );
		}

		if ( 'external' === aveniadp_get_product_file_source( $product_id ) ) {
			$external_url = aveniadp_get_product_external_file_url( $product_id );
			if ( '' === $external_url ) {
				wp_die( esc_html__( 'File not found.', 'avenia-digital-products' ), esc_html__( 'File Not Found', 'avenia-digital-products' ), array( 'response' => 404 ) );
			}

			$this->redirect_to_external_file( $external_url );
		}

		$file_id   = absint( get_post_meta( $product_id, '_aveniadp_file_id', true ) );
		$file_path = get_attached_file( $file_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'avenia-digital-products' ), esc_html__( 'File Not Found', 'avenia-digital-products' ), array( 'response' => 404 ) );
		}

		$this->serve_file( $file_path );
	}

	/**
	 * Get download by access key.
	 *
	 * @param string $access_key Access key.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_download_by_key( $access_key ) {
		global $wpdb;

		$access_key = sanitize_text_field( $access_key );
		$table = $wpdb->prefix . 'aveniadp_downloads';

		$download = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE access_key = %s",
				$access_key
			)
		);

		return $download;
	}

	/**
	 * Get download by ID.
	 *
	 * @param int $download_id Download ID.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_download_by_id( $download_id ) {
		global $wpdb;

		$download_id = absint( $download_id );
		if ( ! $download_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aveniadp_downloads WHERE download_id = %d",
				$download_id
			)
		);
	}

	/**
	 * Get downloads by order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_downloads_by_order( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$table = $wpdb->prefix . 'aveniadp_downloads';

		$downloads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $downloads;
	}

	/**
	 * Calculate when an emailed download link should expire.
	 *
	 * @return string|null MySQL datetime or null when email links do not expire.
	 * @since 1.0.0
	 */
	public function calculate_email_link_expires_at() {
		$days = function_exists( 'aveniadp_get_email_download_link_expiration_days' ) ? aveniadp_get_email_download_link_expiration_days() : 2;
		if ( $days < 1 ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( $days ) . ' days' ) );
	}

	/**
	 * Refresh the email-link expiration for a download row.
	 *
	 * @param int $download_id Download ID.
	 * @since 1.0.0
	 */
	public function refresh_email_link_expiration( $download_id ) {
		$this->update_download_expiration( absint( $download_id ), $this->calculate_email_link_expires_at() );
	}

	/**
	 * Whether a logged-in customer can download from their account.
	 *
	 * @param object $download    Download row.
	 * @param int    $customer_id Customer ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function account_allows_download( $download, $customer_id ) {
		if ( ! $download || 0 === (int) $download->order_id ) {
			return false;
		}

		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( (int) $download->order_id );

		if ( ! $order || (int) $order->customer_id !== absint( $customer_id ) ) {
			return false;
		}

		return $this->order_allows_download( (int) $order->order_id, absint( $download->product_id ) );
	}

	/**
	 * Whether an order still allows downloading its products.
	 *
	 * @param int $order_id   Order ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function order_allows_download( $order_id, $product_id ) {
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( absint( $order_id ) );

		if ( ! $order || 'completed' !== $order->status ) {
			return false;
		}

		if ( function_exists( 'aveniadp_access' ) && ! aveniadp_access()->order_grants_access( $order ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Revoke download links for an order by expiring them immediately.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function revoke_downloads_for_order( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'aveniadp_downloads',
			array( 'expires_at' => current_time( 'mysql' ) ),
			array( 'order_id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update a download expiration timestamp.
	 *
	 * @param int         $download_id Download ID.
	 * @param string|null $expires_at  Expiration datetime or null.
	 * @since 1.0.0
	 */
	private function update_download_expiration( $download_id, $expires_at ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'aveniadp_downloads',
			array( 'expires_at' => $expires_at ),
			array( 'download_id' => absint( $download_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Create download.
	 *
	 * @param int    $order_id   Order ID.
	 * @param int    $product_id Product ID.
	 * @return int|WP_Error Download ID or error.
	 * @since 1.0.0
	 */
	public function create_download( $order_id, $product_id, $send_email = true ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$product_id = absint( $product_id );

		// Check if download already exists for this order/product.
		$table = $wpdb->prefix . 'aveniadp_downloads';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT download_id FROM {$table} WHERE order_id = %d AND product_id = %d",
				$order_id,
				$product_id
			)
		);

		if ( $existing ) {
			return absint( $existing );
		}

		$expires_at = $this->calculate_email_link_expires_at();

		// Generate access key.
		$access_key = $this->generate_access_key();

		$result = $wpdb->insert(
			$table,
			array(
				'order_id'       => $order_id,
				'product_id'     => $product_id,
				'download_count' => 0,
				'expires_at'     => $expires_at,
				'access_key'     => $access_key,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create download.', 'avenia-digital-products' ) );
		}

		$download_id = $wpdb->insert_id;

		if ( $send_email ) {
			$download_url = function_exists( 'aveniadp_get_download_url' ) ? aveniadp_get_download_url( $access_key ) : home_url( '/aveniadp-download/' . $access_key . '/' );
			$emails       = function_exists( 'aveniadp_emails' ) ? aveniadp_emails() : new AVENIADP_Emails();
			$emails->send_download_link( $order_id, $product_id, $download_url );
		}

		return $download_id;
	}

	/**
	 * Resend the download link email for an existing order/product.
	 *
	 * @param int $order_id   Order ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function resend_download_link( $order_id, $product_id ) {
		$order_id   = absint( $order_id );
		$product_id = absint( $product_id );

		$downloads = $this->get_downloads_by_order( $order_id );
		foreach ( $downloads as $download ) {
			if ( (int) $download->product_id !== $product_id ) {
				continue;
			}

			$this->refresh_email_link_expiration( (int) $download->download_id );

			$download_url = function_exists( 'aveniadp_get_download_url' ) ? aveniadp_get_download_url( $download->access_key ) : home_url( '/aveniadp-download/' . $download->access_key . '/' );
			$emails       = function_exists( 'aveniadp_emails' ) ? aveniadp_emails() : new AVENIADP_Emails();

			return (bool) $emails->send_download_link( $order_id, $product_id, $download_url );
		}

		return false;
	}

	/**
	 * Find a completed free order for a customer/product pair.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $product_id  Product ID.
	 * @return int Order ID or 0.
	 * @since 1.0.0
	 */
	public function get_free_order_for_customer_product( $customer_id, $product_id ) {
		global $wpdb;

		$customer_id = absint( $customer_id );
		$product_id  = absint( $product_id );

		if ( ! $customer_id || ! $product_id ) {
			return 0;
		}

		$orders_table = $wpdb->prefix . 'aveniadp_orders';
		$items_table  = $wpdb->prefix . 'aveniadp_order_items';

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT o.order_id
				FROM {$orders_table} o
				INNER JOIN {$items_table} oi ON o.order_id = oi.order_id
				WHERE o.customer_id = %d
					AND oi.product_id = %d
					AND o.payment_method = 'free'
					AND o.status = 'completed'
				ORDER BY o.created_at DESC
				LIMIT 1",
				$customer_id,
				$product_id
			)
		);

		return $order_id ? absint( $order_id ) : 0;
	}

	/**
	 * Process a gated free download: customer, order, and download email.
	 *
	 * Does not create WordPress users. Existing logged-in or email-matching users
	 * are linked to the customer record when present.
	 *
	 * @param int    $product_id Product post ID.
	 * @param string $email      Customer email.
	 * @return array|WP_Error Result data or error.
	 * @since 1.0.0
	 */
	public function process_gated_free_download( $product_id, $email ) {
		$product_id = absint( $product_id );
		$email      = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'avenia-digital-products' ) );
		}

		if ( ! $this->check_free_download_rate_limit() ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many download requests. Please try again later.', 'avenia-digital-products' )
			);
		}

		$customers   = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$user_id     = is_user_logged_in() ? get_current_user_id() : null;
		$customer_id = $customers->create_customer( $email, $user_id );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$this->link_existing_customer_account( $customer_id, $email );

		$existing_order_id = $this->get_free_order_for_customer_product( $customer_id, $product_id );
		if ( $existing_order_id ) {
			$has_download = false;
			$downloads      = $this->get_downloads_by_order( $existing_order_id );

			foreach ( $downloads as $download ) {
				if ( (int) $download->product_id === $product_id ) {
					$has_download = true;
					break;
				}
			}

			if ( $has_download ) {
				$this->resend_download_link( $existing_order_id, $product_id );
			} else {
				$download_id = $this->create_download( $existing_order_id, $product_id );
				if ( is_wp_error( $download_id ) ) {
					return $download_id;
				}
			}

			return array(
				'message' => __( 'We sent the download link to your email again.', 'avenia-digital-products' ),
				'resent'  => true,
			);
		}

		$orders  = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order_id = $orders->create_order(
			array(
				'customer_id'    => $customer_id,
				'status'         => 'completed',
				'total'          => 0,
				'payment_method' => 'free',
				'items'          => array(
					array(
						'product_id'       => $product_id,
						'price'            => 0,
						'quantity'         => 1,
						'license_quantity' => 1,
					),
				),
			)
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$download_id = $this->create_download( $order_id, $product_id );
		if ( is_wp_error( $download_id ) ) {
			return $download_id;
		}

		return array(
			'message' => __( 'Check your email for the download link.', 'avenia-digital-products' ),
			'resent'  => false,
		);
	}

	/**
	 * Rate-limit gated free download requests by client IP.
	 *
	 * Default: 5 requests per IP per hour. Override with the
	 * `aveniadp_free_download_rate_limit` filter.
	 *
	 * @return bool True when the request is allowed.
	 * @since 1.0.0
	 */
	private function check_free_download_rate_limit() {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = 'unknown';
		}

		/**
		 * Filter the max gated free-download requests allowed per IP per hour.
		 *
		 * @param int $limit Requests per hour. Default 5.
		 */
		$limit = absint( apply_filters( 'aveniadp_free_download_rate_limit', 5 ) );
		if ( $limit < 1 ) {
			return true;
		}

		$transient_key = 'aveniadp_free_dl_rl_' . md5( $ip );
		$requests      = get_transient( $transient_key );
		if ( false === $requests ) {
			$requests = 0;
		}

		if ( (int) $requests >= $limit ) {
			return false;
		}

		set_transient( $transient_key, (int) $requests + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Link a customer to an existing WordPress account when one is already available.
	 *
	 * Never creates a new WordPress user.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $email       Customer email.
	 * @since 1.0.0
	 */
	private function link_existing_customer_account( $customer_id, $email ) {
		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer( $customer_id );

		if ( ! $customer ) {
			return;
		}

		if ( ! empty( $customer->user_id ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$customers->update_customer( $customer_id, array( 'user_id' => get_current_user_id() ) );
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$customers->update_customer( $customer_id, array( 'user_id' => $user->ID ) );
		}
	}

	/**
	 * Get or create a shared download row for a free catalog product (order_id = 0). No email is sent.
	 *
	 * @param int $product_id Product post ID.
	 * @return string|WP_Error Access key or error.
	 * @since 1.0.0
	 */
	public function get_or_create_free_catalog_access_key( $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );
		$table      = $wpdb->prefix . 'aveniadp_downloads';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT access_key FROM {$table} WHERE order_id = 0 AND product_id = %d LIMIT 1",
				$product_id
			)
		);

		if ( ! empty( $existing ) ) {
			return $existing;
		}

		$expires_at = null;

		$access_key = $this->generate_access_key();

		$result = $wpdb->insert(
			$table,
			array(
				'order_id'       => 0,
				'product_id'     => $product_id,
				'download_count' => 0,
				'expires_at'     => $expires_at,
				'access_key'     => $access_key,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Could not prepare download.', 'avenia-digital-products' ) );
		}

		return $access_key;
	}

	/**
	 * AJAX: issue redirect URL for a free catalog product.
	 *
	 * @since 1.0.0
	 */
	public function ajax_request_free_download() {
		check_ajax_referer( 'aveniadp-public', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id || AVENIADP_PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'avenia-digital-products' ) ) );
		}

		if ( 'publish' !== get_post_status( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Product is not available.', 'avenia-digital-products' ) ) );
		}

		if ( '1' !== get_post_meta( $product_id, '_aveniadp_free_download', true ) ) {
			wp_send_json_error( array( 'message' => __( 'This product is not offered as a free download.', 'avenia-digital-products' ) ) );
		}

		if ( ! function_exists( 'aveniadp_product_has_download_file' ) || ! aveniadp_product_has_download_file( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No file is configured for this product.', 'avenia-digital-products' ) ) );
		}

		if ( function_exists( 'aveniadp_free_download_requires_email' ) && aveniadp_free_download_requires_email() ) {
			$email = '';

			if ( is_user_logged_in() ) {
				$user  = wp_get_current_user();
				$email = $user->user_email;
			} elseif ( isset( $_POST['email'] ) ) {
				$email = sanitize_email( wp_unslash( $_POST['email'] ) );
			}

			if ( ! $email ) {
				wp_send_json_error( array( 'message' => __( 'Please enter your email address.', 'avenia-digital-products' ) ) );
			}

			$result = $this->process_gated_free_download( $product_id, $email );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'gated'      => true,
					'message'    => $result['message'],
					'accountUrl' => function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' ),
				)
			);
		}

		$key = $this->get_or_create_free_catalog_access_key( $product_id );
		if ( is_wp_error( $key ) ) {
			wp_send_json_error( array( 'message' => $key->get_error_message() ) );
		}

		$url = function_exists( 'aveniadp_get_download_url' ) ? aveniadp_get_download_url( $key ) : home_url( '/aveniadp-download/' . $key . '/' );

		wp_send_json_success( array( 'redirect' => $url ) );
	}

	/**
	 * Generate access key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function generate_access_key() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Increment download count.
	 *
	 * @param int $download_id Download ID.
	 * @since 1.0.0
	 */
	private function increment_download_count( $download_id ) {
		global $wpdb;

		$download_id = absint( $download_id );
		$table = $wpdb->prefix . 'aveniadp_downloads';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET download_count = download_count + 1 WHERE download_id = %d",
				$download_id
			)
		);
	}

	/**
	 * Redirect to an external download URL.
	 *
	 * @param string $url External URL.
	 * @since 1.0.0
	 */
	private function redirect_to_external_file( $url ) {
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		$url = esc_url_raw( $url );
		if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
			wp_die( esc_html__( 'Invalid download URL.', 'avenia-digital-products' ), esc_html__( 'Download Error', 'avenia-digital-products' ), array( 'response' => 400 ) );
		}

		// External file hosts are allowed; validate before redirecting.
		wp_redirect( $url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Off-site CDN/S3 download URLs are intentional after esc_url_raw + wp_http_validate_url.
		exit;
	}

	/**
	 * Serve file.
	 *
	 * @param string $file_path File path.
	 * @since 1.0.0
	 */
	private function serve_file( $file_path ) {
		$file_name = sanitize_file_name( basename( $file_path ) );
		if ( '' === $file_name ) {
			$file_name = 'download';
		}
		$mime_type = wp_check_filetype( $file_name );

		// Clear any output.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Send no-cache headers via WordPress helper.
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		// Force download (ASCII fallback + RFC 5987 filename*).
		$ascii_name = preg_replace( '/[^\x20-\x7E]/', '_', $file_name );
		$ascii_name = str_replace( array( '"', '\\', "\r", "\n" ), '_', (string) $ascii_name );
		if ( '' === $ascii_name ) {
			$ascii_name = 'download';
		}

		header( 'Content-Type: ' . ( ! empty( $mime_type['type'] ) ? $mime_type['type'] : 'application/octet-stream' ) );
		header(
			'Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode( $file_name )
		);
		$filesize = @filesize( $file_path );
		if ( false !== $filesize ) {
			header( 'Content-Length: ' . $filesize );
		}
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Disable compression.
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Disable output compression so binary download Content-Length remains accurate.

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Binary file streaming for authorized downloads.
		exit;
	}

	// phpcs:enable
}
