<?php
/**
 * Installation and database setup.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installation class.
 *
 * @since 1.0.0
 */
class AVENIADP_Install {

	/**
	 * Database version.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Activate plugin.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Create database tables.
		self::create_tables();
		self::run_schema_upgrades();
		update_option( 'aveniadp_db_version', self::DB_VERSION );

		AVENIADP_Protected_Uploads::ensure_directory();

		// Set default options.
		self::set_default_options();

		// Create core pages (cart, checkout, account, login, thank you).
		self::maybe_create_core_pages();

		if ( class_exists( 'AVENIADP_Products' ) ) {
			AVENIADP_Products::register_post_type();
			AVENIADP_Products::register_taxonomies();
		}

		// Register download endpoint before flushing rewrite rules.
		AVENIADP_Downloads::register_rewrite_rules();

		// Flush rewrite rules for custom post types and download endpoint.
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Orders table.
		$table_orders = $wpdb->prefix . 'aveniadp_orders';
		$sql_orders   = "CREATE TABLE IF NOT EXISTS {$table_orders} (
			order_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			total decimal(10,2) NOT NULL,
			discount_code_id bigint(20) UNSIGNED NULL,
			discount_amount decimal(10,2) NULL DEFAULT 0,
			tax_amount decimal(10,2) NULL DEFAULT 0,
			tax_rate decimal(5,2) NULL DEFAULT 0,
			payment_method varchar(50) NOT NULL,
			transaction_id varchar(255) NULL,
			access_revoked_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (order_id),
			KEY customer_id (customer_id),
			KEY status (status)
		) $charset_collate;";

		// Order items table.
		$table_order_items = $wpdb->prefix . 'aveniadp_order_items';
		$sql_order_items   = "CREATE TABLE IF NOT EXISTS {$table_order_items} (
			item_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED NOT NULL,
			price decimal(10,2) NOT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			license_quantity int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY (item_id),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) $charset_collate;";

		// Customers table.
		$table_customers = $wpdb->prefix . 'aveniadp_customers';
		$sql_customers   = "CREATE TABLE IF NOT EXISTS {$table_customers} (
			customer_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NULL,
			email varchar(100) NOT NULL,
			name varchar(255) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (customer_id),
			UNIQUE KEY email (email),
			KEY user_id (user_id)
		) $charset_collate;";

		// Downloads table.
		$table_downloads = $wpdb->prefix . 'aveniadp_downloads';
		$sql_downloads   = "CREATE TABLE IF NOT EXISTS {$table_downloads} (
			download_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED NOT NULL,
			download_count int(11) NOT NULL DEFAULT 0,
			expires_at datetime NULL,
			access_key varchar(255) NOT NULL,
			PRIMARY KEY (download_id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY access_key (access_key)
		) $charset_collate;";

		// Discount codes table.
		$table_discounts = $wpdb->prefix . 'aveniadp_discount_codes';
		$sql_discounts   = "CREATE TABLE IF NOT EXISTS {$table_discounts} (
			code_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			discount_type varchar(20) NOT NULL,
			discount_value decimal(10,2) NOT NULL,
			usage_limit int(11) NULL,
			usage_limit_per_customer int(11) NULL,
			usage_count int(11) NOT NULL DEFAULT 0,
			expires_at datetime NULL,
			minimum_amount decimal(10,2) NULL,
			applicable_products text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (code_id),
			UNIQUE KEY code (code)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_orders );
		dbDelta( $sql_order_items );
		dbDelta( $sql_customers );
		dbDelta( $sql_downloads );
		dbDelta( $sql_discounts );
	}

	/**
	 * Run database upgrades on existing installs.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_upgrade_db() {
		$installed     = get_option( 'aveniadp_db_version', '0' );
		$needs_upgrade = version_compare( $installed, self::DB_VERSION, '<' );

		// Retry when version was stamped before tables/columns were fully created.
		if ( ! $needs_upgrade && self::schema_is_current() ) {
			return;
		}

		self::create_tables();
		self::run_schema_upgrades();

		if ( class_exists( 'AVENIADP_Protected_Uploads' ) ) {
			AVENIADP_Protected_Uploads::ensure_directory();
		}

		update_option( 'aveniadp_db_version', self::DB_VERSION );
	}

	/**
	 * Whether required plugin tables and order columns exist.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function schema_is_current() {
		if ( ! self::orders_schema_is_current() ) {
			return false;
		}

		$tables = array(
			'aveniadp_orders',
			'aveniadp_order_items',
			'aveniadp_customers',
			'aveniadp_downloads',
			'aveniadp_discount_codes',
		);

		foreach ( $tables as $table ) {
			if ( ! self::table_exists( $table ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the orders table has required columns for inserts.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function orders_schema_is_current() {
		global $wpdb;

		$table = $wpdb->prefix . 'aveniadp_orders';

		if ( ! self::table_exists( 'aveniadp_orders' ) ) {
			return false;
		}

		return self::table_has_column( $table, 'tax_amount' )
			&& self::table_has_column( $table, 'tax_rate' )
			&& self::table_has_column( $table, 'transaction_id' );
	}

	/**
	 * Whether a plugin table exists.
	 *
	 * @param string $table Table name without prefix.
	 * @return bool
	 * @since 1.0.0
	 */
	private static function table_exists( $table ) {
		global $wpdb;

		$full = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check on plugin-owned table.
		return $full === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) );
	}

	/**
	 * Apply additive column upgrades.
	 *
	 * @since 1.0.0
	 */
	private static function run_schema_upgrades() {
		self::upgrade_orders_transaction_id_column();
		self::upgrade_orders_tax_columns();
		self::upgrade_discount_usage_limit_per_customer_column();
	}

	/**
	 * Ensure commerce tables/columns exist before writes.
	 *
	 * @since 1.0.0
	 */
	public static function ensure_orders_schema() {
		if ( self::schema_is_current() ) {
			return;
		}

		self::create_tables();
		self::run_schema_upgrades();
		update_option( 'aveniadp_db_version', self::DB_VERSION );
	}

	/**
	 * Check whether a plugin table has a column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table       Full table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	private static function table_has_column( $table, $column_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema upgrade check on plugin-owned table.
		$column = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table,
				$column_name
			)
		);

		return ! empty( $column );
	}

	/**
	 * Ensure orders table has transaction_id (existing installs).
	 *
	 * @since 1.0.2
	 */
	private static function upgrade_orders_transaction_id_column() {
		global $wpdb;

		$table = $wpdb->prefix . 'aveniadp_orders';

		if ( self::table_has_column( $table, 'transaction_id' ) ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN transaction_id varchar(255) NULL AFTER payment_method', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Additive schema upgrade for plugin-owned table.
	}

	/**
	 * Ensure orders table has tax_amount and tax_rate (existing installs).
	 *
	 * @since 1.0.3
	 */
	private static function upgrade_orders_tax_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'aveniadp_orders';

		if ( ! self::table_has_column( $table, 'tax_amount' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN tax_amount decimal(10,2) NULL DEFAULT 0 AFTER discount_amount', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Additive schema upgrade for plugin-owned table.
		}

		if ( ! self::table_has_column( $table, 'tax_rate' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN tax_rate decimal(5,2) NULL DEFAULT 0 AFTER tax_amount', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Additive schema upgrade for plugin-owned table.
		}
	}

	/**
	 * Ensure discount codes table has usage_limit_per_customer (existing installs).
	 *
	 * @since 1.0.4
	 */
	private static function upgrade_discount_usage_limit_per_customer_column() {
		global $wpdb;

		$table = $wpdb->prefix . 'aveniadp_discount_codes';

		if ( self::table_has_column( $table, 'usage_limit_per_customer' ) ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN usage_limit_per_customer int(11) NULL AFTER usage_limit', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Additive schema upgrade for plugin-owned table.
	}

	/**
	 * Set default options.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$defaults = array(
			'aveniadp_version'              => AVENIADP_VERSION,
			'aveniadp_currency'             => 'USD',
			'aveniadp_currency_position'   => 'left',
			'aveniadp_account_creation'    => 'auto-create',
			'aveniadp_simulate_payments'   => 'yes',
			'aveniadp_payment_environment' => 'sandbox',
			'aveniadp_delete_data_on_uninstall' => false,
			'aveniadp_skip_cart_to_checkout'         => 'no',
			'aveniadp_free_download_require_email'   => false,
			'aveniadp_email_download_link_expiration' => 2,
			'aveniadp_product_base'                  => aveniadp_get_default_product_base_slug(),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Create core pages when missing (activation only).
	 *
	 * Runtime uses {@see sync_core_page_options()} so intentionally unassigned
	 * or deleted pages are not recreated on every request.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_create_core_pages() {
		// Products catalog page.
		self::maybe_create_shop_page();

		// Cart page.
		self::maybe_create_page(
			'aveniadp_page_cart_id',
			__( 'Cart', 'avenia-digital-products' ),
			self::block_content_shortcode( '[aveniadp_cart]' )
		);

		// Checkout page: store shortcode as block markup for the block editor.
		self::maybe_create_page(
			'aveniadp_page_checkout_id',
			__( 'Checkout', 'avenia-digital-products' ),
			self::block_content_shortcode( '[aveniadp_checkout]' )
		);

		// Account page.
		self::maybe_create_page(
			'aveniadp_page_account_id',
			__( 'My Account', 'avenia-digital-products' ),
			self::block_content_shortcode( '[aveniadp_account]' )
		);

		// Login page.
		self::maybe_create_page(
			'aveniadp_page_login_id',
			__( 'Login', 'avenia-digital-products' ),
			self::block_content_shortcode( '[aveniadp_login]' )
		);

		// Thank you page (used by redirects).
		self::maybe_create_page(
			'aveniadp_thank_you_page',
			__( 'Order Received', 'avenia-digital-products' ),
			self::block_content_paragraph( __( 'Thank you for your order. You will receive an email with your download links shortly.', 'avenia-digital-products' ) )
		);
	}

	/**
	 * Clear page options that point at missing or non-page posts.
	 *
	 * Does not create pages — admins may leave Cart/Products unassigned.
	 *
	 * @since 1.0.0
	 */
	public static function sync_core_page_options() {
		$options = array(
			'aveniadp_page_shop_id',
			'aveniadp_page_cart_id',
			'aveniadp_page_checkout_id',
			'aveniadp_page_account_id',
			'aveniadp_page_login_id',
			'aveniadp_thank_you_page',
		);

		foreach ( $options as $option_name ) {
			$page_id = absint( get_option( $option_name, 0 ) );
			if ( $page_id && ! self::is_valid_store_page( $page_id ) ) {
				update_option( $option_name, 0 );
			}
		}
	}

	/**
	 * Whether a stored page ID points to a usable published page.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_valid_store_page( $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id ) {
			return false;
		}

		$post = get_post( $page_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return false;
		}

		return in_array( $post->post_status, array( 'publish', 'private' ), true );
	}

	/**
	 * Create the shop page if it does not exist yet.
	 *
	 * Safe to call on existing installs (idempotent). Activation only.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_create_shop_page() {
		self::maybe_create_page(
			'aveniadp_page_shop_id',
			__( 'Products', 'avenia-digital-products' ),
			self::block_content_products_grid(),
			'products'
		);
	}

	/**
	 * Products block markup for the shop page.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function block_content_products_grid() {
		return "<!-- wp:avenia-digital-products/products /-->";
	}

	/**
	 * Wrap a shortcode in core Shortcode block markup for the block editor.
	 *
	 * Plain `[tag]` in post_content is treated as classic content; this avoids the “convert to blocks” step.
	 *
	 * @param string $shortcode Full shortcode including brackets, e.g. `[aveniadp_checkout]`.
	 * @return string
	 * @since 1.0.0
	 */
	private static function block_content_shortcode( $shortcode ) {
		$shortcode = trim( $shortcode );
		return "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
	}

	/**
	 * Single paragraph as block markup.
	 *
	 * @param string $text Plain text (will be escaped for HTML).
	 * @return string
	 * @since 1.0.0
	 */
	private static function block_content_paragraph( $text ) {
		return sprintf(
			"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
			esc_html( $text )
		);
	}

	/**
	 * Create a page when the stored option is empty or points to a missing post.
	 *
	 * Call from activation only. Runtime must not recreate unassigned pages.
	 *
	 * @param string $option_name Option name storing the page ID.
	 * @param string $title       Page title.
	 * @param string $content     Page content (block HTML preferred for new pages).
	 * @param string $slug        Optional page slug (post_name).
	 * @since 1.0.0
	 */
	private static function maybe_create_page( $option_name, $title, $content, $slug = '' ) {
		$page_id = absint( get_option( $option_name, 0 ) );

		if ( self::is_valid_store_page( $page_id ) ) {
			return;
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $slug ) {
			$post_data['post_name'] = $slug;
		}

		$page_id = wp_insert_post( $post_data );

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( $option_name, $page_id );
		}
	}

	/**
	 * Uninstall plugin data when the admin opted in.
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() {
		$delete_data = get_option( 'aveniadp_delete_data_on_uninstall', 'no' );

		if ( 'yes' !== $delete_data ) {
			return;
		}

		global $wpdb;

		foreach ( self::get_table_names() as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops plugin-owned tables built from $wpdb->prefix.
		}

		foreach ( self::get_option_names() as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Plugin-owned database table names.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private static function get_table_names() {
		global $wpdb;

		return array(
			$wpdb->prefix . 'aveniadp_downloads',
			$wpdb->prefix . 'aveniadp_order_items',
			$wpdb->prefix . 'aveniadp_orders',
			$wpdb->prefix . 'aveniadp_customers',
			$wpdb->prefix . 'aveniadp_discount_codes',
		);
	}

	/**
	 * Plugin option names removed on uninstall.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private static function get_option_names() {
		return array(
			'aveniadp_version',
			'aveniadp_db_version',
			'aveniadp_download_rewrite_version',
			'aveniadp_delete_data_on_uninstall',
			'aveniadp_currency',
			'aveniadp_currency_position',
			'aveniadp_tax_mode',
			'aveniadp_tax_rate',
			'aveniadp_free_download_require_email',
			'aveniadp_account_creation',
			'aveniadp_skip_cart_to_checkout',
			'aveniadp_product_base',
			'aveniadp_page_shop_id',
			'aveniadp_page_cart_id',
			'aveniadp_page_checkout_id',
			'aveniadp_page_account_id',
			'aveniadp_page_login_id',
			'aveniadp_thank_you_page',
			'aveniadp_simulate_payments',
			'aveniadp_payment_environment',
			'aveniadp_stripe_enabled',
			'aveniadp_stripe_test_secret_key',
			'aveniadp_stripe_live_secret_key',
			'aveniadp_stripe_webhook_secret_test',
			'aveniadp_stripe_webhook_secret',
			'aveniadp_paypal_enabled',
			'aveniadp_paypal_test_client_id',
			'aveniadp_paypal_test_secret',
			'aveniadp_paypal_live_client_id',
			'aveniadp_paypal_live_secret',
			'aveniadp_email_from_name',
			'aveniadp_email_from_email',
			'aveniadp_email_logo',
			'aveniadp_email_download_link_expiration',
			'aveniadp_email_order_confirmation_enabled',
			'aveniadp_email_order_confirmation_subject',
			'aveniadp_email_order_confirmation_message',
			'aveniadp_email_admin_order_notification_enabled',
			'aveniadp_email_admin_order_notification_subject',
			'aveniadp_email_admin_order_notification_message',
			'aveniadp_email_admin_order_recipients',
			'aveniadp_email_account_confirmation_enabled',
			'aveniadp_email_account_confirmation_subject',
			'aveniadp_email_account_confirmation_message',
			'aveniadp_email_download_link_enabled',
			'aveniadp_email_download_link_subject',
			'aveniadp_email_download_link_message',
			'aveniadp_appearance',
		);
	}
}
