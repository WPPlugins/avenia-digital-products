<?php
/**
 * Admin interface.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 *
 * @since 1.0.0
 */
class AVENIADP_Admin {

	/**
	 * Initialize admin.
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
		// Register before default priority so CPT submenus (show_in_menu) find this parent.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 5 );

		// After core adds the product list submenu (_add_post_type_submenus, priority 10).
		add_action( 'admin_menu', array( $this, 'register_product_catalog_submenus' ), 15 );

		// After all submenus exist.
		add_action( 'admin_menu', array( $this, 'reorder_product_admin_submenu' ), 99 );

		// Upgrade to Full menu styles and new-tab script.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_upgrade_menu_assets' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Handle admin form submissions before page output.
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'admin_init', array( AVENIADP_Admin_Discounts::class, 'maybe_handle_request' ) );
		add_action( 'wp_ajax_aveniadp_send_test_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Avenia Digital Products', 'avenia-digital-products' ),
			__( 'Avenia Digital Products', 'avenia-digital-products' ),
			'manage_options',
			'avenia-digital-products',
			array( $this, 'render_dashboard' ),
			'dashicons-cart',
			30
		);

		add_submenu_page(
			'avenia-digital-products',
			__( 'Orders', 'avenia-digital-products' ),
			__( 'Orders', 'avenia-digital-products' ),
			'manage_options',
			'aveniadp-orders',
			array( $this, 'render_orders' )
		);

		add_submenu_page(
			'avenia-digital-products',
			__( 'Customers', 'avenia-digital-products' ),
			__( 'Customers', 'avenia-digital-products' ),
			'manage_options',
			'aveniadp-customers',
			array( $this, 'render_customers' )
		);

		add_submenu_page(
			'avenia-digital-products',
			__( 'Discount Codes', 'avenia-digital-products' ),
			__( 'Discount Codes', 'avenia-digital-products' ),
			'manage_options',
			'aveniadp-discounts',
			array( $this, 'render_discounts' )
		);

		add_submenu_page(
			'avenia-digital-products',
			__( 'System Status', 'avenia-digital-products' ),
			__( 'System Status', 'avenia-digital-products' ),
			'manage_options',
			'aveniadp-status',
			array( $this, 'render_status' )
		);

		add_submenu_page(
			'avenia-digital-products',
			__( 'Settings', 'avenia-digital-products' ),
			__( 'Settings', 'avenia-digital-products' ),
			'manage_options',
			'aveniadp-settings',
			array( $this, 'render_settings' )
		);

		// Upgrade to Full link below Settings (URL set in reorder_product_admin_submenu).
		add_submenu_page(
			'avenia-digital-products',
			__( 'Upgrade to Full', 'avenia-digital-products' ),
			'<span class="aveniadp-upgrade-menu-item">' . esc_html__( 'Upgrade to Full', 'avenia-digital-products' ) . '</span>',
			'manage_options',
			'aveniadp-upgrade-to-full',
			array( $this, 'redirect_upgrade_to_full' )
		);
	}

	/**
	 * Add Product, categories, and tags under the plugin menu.
	 *
	 * When a post type uses show_in_menu => a parent slug, core only registers the
	 * "All items" list — not Add New or taxonomy screens (unlike top-level post types).
	 *
	 * @since 1.0.0
	 */
	public function register_product_catalog_submenus() {
		$ptype = get_post_type_object( AVENIADP_PRODUCT_POST_TYPE );
		if ( ! $ptype || 'avenia-digital-products' !== $ptype->show_in_menu ) {
			return;
		}

		$parent = 'avenia-digital-products';

		$slug_new = 'post-new.php?post_type=' . AVENIADP_PRODUCT_POST_TYPE;
		if ( ! $this->admin_submenu_has_slug( $parent, $slug_new ) ) {
			add_submenu_page(
				$parent,
				$ptype->labels->add_new_item,
				__( 'Add Product', 'avenia-digital-products' ),
				$ptype->cap->create_posts,
				$slug_new
			);
		}

		$cat_tax = get_taxonomy( 'aveniadp_product_category' );
		if ( $cat_tax && $cat_tax->show_ui && $cat_tax->show_in_menu ) {
			$slug_cat = 'edit-tags.php?taxonomy=aveniadp_product_category&post_type=' . AVENIADP_PRODUCT_POST_TYPE;
			if ( ! $this->admin_submenu_has_slug( $parent, $slug_cat ) ) {
				add_submenu_page(
					$parent,
					$cat_tax->labels->name,
					__( 'Product Categories', 'avenia-digital-products' ),
					$cat_tax->cap->manage_terms,
					$slug_cat
				);
			}
		}

		$tag_tax = get_taxonomy( 'aveniadp_product_tag' );
		if ( $tag_tax && $tag_tax->show_ui && $tag_tax->show_in_menu ) {
			$slug_tag = 'edit-tags.php?taxonomy=aveniadp_product_tag&post_type=' . AVENIADP_PRODUCT_POST_TYPE;
			if ( ! $this->admin_submenu_has_slug( $parent, $slug_tag ) ) {
				add_submenu_page(
					$parent,
					$tag_tax->labels->name,
					__( 'Product Tags', 'avenia-digital-products' ),
					$tag_tax->cap->manage_terms,
					$slug_tag
				);
			}
		}
	}

	/**
	 * Whether the parent submenu already contains a given slug.
	 *
	 * @param string $parent Parent file/slug.
	 * @param string $slug   Submenu file/slug.
	 * @return bool
	 */
	private function admin_submenu_has_slug( $parent, $slug ) {
		global $submenu;

		if ( empty( $submenu[ $parent ] ) ) {
			return false;
		}

		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Order plugin submenus and clarify labels.
	 *
	 * Core adds the product list after this class registers store pages, so submenu
	 * order must be normalized. Dashboard (parent duplicate) is first, then catalog
	 * items, then operations.
	 *
	 * @since 1.0.0
	 */
	public function reorder_product_admin_submenu() {
		global $submenu;

		if ( empty( $submenu['avenia-digital-products'] ) || ! is_array( $submenu['avenia-digital-products'] ) ) {
			return;
		}

		$items = $submenu['avenia-digital-products'];

		$desired_order = array(
			'avenia-digital-products',
			'edit.php?post_type=' . AVENIADP_PRODUCT_POST_TYPE,
			'post-new.php?post_type=' . AVENIADP_PRODUCT_POST_TYPE,
			'edit-tags.php?taxonomy=aveniadp_product_category&post_type=' . AVENIADP_PRODUCT_POST_TYPE,
			'edit-tags.php?taxonomy=aveniadp_product_tag&post_type=' . AVENIADP_PRODUCT_POST_TYPE,
			'aveniadp-orders',
			'aveniadp-customers',
			'aveniadp-discounts',
			'aveniadp-status',
			'aveniadp-settings',
			'aveniadp-upgrade-to-full',
		);

		$by_slug = array();
		foreach ( $items as $item ) {
			if ( isset( $item[2] ) ) {
				$by_slug[ $item[2] ] = $item;
			}
		}

		$ordered = array();
		foreach ( $desired_order as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[] = $by_slug[ $slug ];
				unset( $by_slug[ $slug ] );
			}
		}
		foreach ( $by_slug as $item ) {
			$ordered[] = $item;
		}

		foreach ( $ordered as &$item ) {
			if ( ! isset( $item[2] ) ) {
				continue;
			}
			switch ( $item[2] ) {
				case 'avenia-digital-products':
					$item[0] = __( 'Dashboard', 'avenia-digital-products' );
					break;
				case 'post-new.php?post_type=' . AVENIADP_PRODUCT_POST_TYPE:
					$item[0] = __( 'Add Product', 'avenia-digital-products' );
					break;
				case 'edit-tags.php?taxonomy=aveniadp_product_category&post_type=' . AVENIADP_PRODUCT_POST_TYPE:
					$item[0] = __( 'Product Categories', 'avenia-digital-products' );
					break;
				case 'edit-tags.php?taxonomy=aveniadp_product_tag&post_type=' . AVENIADP_PRODUCT_POST_TYPE:
					$item[0] = __( 'Product Tags', 'avenia-digital-products' );
					break;
				case 'aveniadp-upgrade-to-full':
					// External pricing URL; target=_blank is applied via upgrade-menu.js.
					if ( function_exists( 'aveniadp_get_pro_upgrade_url' ) ) {
						$item[2] = aveniadp_get_pro_upgrade_url();
					}
					break;
			}
		}
		unset( $item );

		$submenu['avenia-digital-products'] = $ordered;
	}

	/**
	 * Redirect to the Full pricing page when the upgrade menu slug is opened directly.
	 *
	 * @since 1.0.0
	 */
	public function redirect_upgrade_to_full() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'avenia-digital-products' ) );
		}

		$url = function_exists( 'aveniadp_get_pro_upgrade_url' ) ? aveniadp_get_pro_upgrade_url() : 'https://aveniadigitalproducts.com/pricing';

		// External host; wp_safe_redirect would block this by default.
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Enqueue Upgrade to Full menu styles and new-tab script.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_upgrade_menu_assets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'aveniadp-admin-upgrade-menu',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/upgrade-menu.css',
			array(),
			AVENIADP_VERSION
		);

		wp_enqueue_script(
			'aveniadp-admin-upgrade-menu',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/upgrade-menu.js',
			array(),
			AVENIADP_VERSION,
			true
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @since 1.0.0
	 */
	public function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product_screen = $screen && isset( $screen->post_type ) && AVENIADP_PRODUCT_POST_TYPE === $screen->post_type;
		$is_settings_page  = 'avenia-digital-products_page_aveniadp-settings' === $hook;
		$settings_tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab navigation in admin.
		$is_emails_tab     = $is_settings_page && 'emails' === $settings_tab;
		$is_appearance_tab = $is_settings_page && 'appearance' === $settings_tab;

		if ( false === strpos( $hook, 'avenia-digital-products' ) && ! $is_product_screen ) {
			return;
		}

		if ( $is_emails_tab ) {
			wp_enqueue_editor();
			wp_enqueue_media();
		}

		if ( $is_appearance_tab ) {
			wp_enqueue_style( 'wp-color-picker' );
		}

		wp_enqueue_style(
			'aveniadp-admin',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			AVENIADP_VERSION
		);

		$script_deps = array( 'jquery' );
		if ( $is_product_screen ) {
			$script_deps[] = 'postbox';
			$script_deps[] = 'jquery-ui-sortable';
			wp_enqueue_media();
		}
		if ( $is_emails_tab ) {
			$script_deps[] = 'editor';
		}
		if ( $is_appearance_tab ) {
			$script_deps[] = 'wp-color-picker';
		}

		wp_enqueue_script(
			'aveniadp-admin',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/admin.js',
			$script_deps,
			AVENIADP_VERSION,
			true
		);

		wp_localize_script(
			'aveniadp-admin',
			'aveniadpAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'aveniadp-admin' ),
				'isProductEdit' => $is_product_screen,
				'relatedMax'    => function_exists( 'aveniadp_get_max_related_products' ) ? aveniadp_get_max_related_products() : 6,
				'checkoutUrl'   => function_exists( 'aveniadp_get_configured_checkout_page_url' ) ? aveniadp_get_configured_checkout_page_url() : '',
				'i18n'          => array(
					'galleryTitle'  => __( 'Add images to gallery', 'avenia-digital-products' ),
					'galleryButton' => __( 'Add to gallery', 'avenia-digital-products' ),
					'removeImage'   => __( 'Remove image', 'avenia-digital-products' ),
					/* translators: 1: selected related product count, 2: maximum allowed */
					'relatedCount'  => __( '%1$d of %2$d selected', 'avenia-digital-products' ),
					'revokeAccessConfirm'          => __( 'Revoke product access for this order? Downloads will stop working immediately. Stripe or PayPal billing will not be changed.', 'avenia-digital-products' ),
					'revokeAllAccessConfirm'       => __( 'Revoke all product access for this customer? This cannot be undone from the customer account. Stripe or PayPal billing will not be changed.', 'avenia-digital-products' ),
					'directCheckoutLinkCopied'     => __( 'Link copied to clipboard.', 'avenia-digital-products' ),
					'directCheckoutUnlimitedSites' => __( 'Unlimited sites', 'avenia-digital-products' ),
					/* translators: %s: number of sites (singular) */
					'directCheckoutSite'           => __( '%s site', 'avenia-digital-products' ),
					/* translators: %s: number of sites (plural) */
					'directCheckoutSites'          => __( '%s sites', 'avenia-digital-products' ),
					'sendTestEmailSending'         => __( 'Sending…', 'avenia-digital-products' ),
					'sendTestEmailSuccess'         => __( 'Test email sent.', 'avenia-digital-products' ),
					'sendTestEmailFailed'          => __( 'Failed to send test email. Check that wp_mail is configured.', 'avenia-digital-products' ),
				),
			)
		);

		if ( $is_settings_page ) {
			wp_enqueue_script(
				'aveniadp-admin-settings',
				AVENIADP_PLUGIN_URL . 'admin/assets/js/settings-page.js',
				array( 'jquery', 'aveniadp-admin' ),
				AVENIADP_VERSION,
				true
			);
		}

	}

	/**
	 * Render dashboard.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render orders.
	 *
	 * @since 1.0.0
	 */
	public function render_orders() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/orders.php';
	}

	/**
	 * Render customers.
	 *
	 * @since 1.0.0
	 */
	public function render_customers() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/customers.php';
	}

	/**
	 * Render discounts.
	 *
	 * @since 1.0.0
	 */
	public function render_discounts() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/discounts.php';
	}

	/**
	 * Render system status.
	 *
	 * @since 1.0.0
	 */
	public function render_status() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/status.php';
	}

	/**
	 * Save settings form submission on admin_init.
	 *
	 * @since 1.0.0
	 */
	public function save_settings() {
		AVENIADP_Admin_Settings::maybe_save();
	}

	/**
	 * Render settings.
	 *
	 * @since 1.0.0
	 */
	public function render_settings() {
		include AVENIADP_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * AJAX: send a test transactional email from settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'aveniadp-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to send test emails.', 'avenia-digital-products' ) ) );
		}

		$type = isset( $_POST['email_type'] ) ? sanitize_key( wp_unslash( $_POST['email_type'] ) ) : '';
		if ( '' === $type || ! isset( AVENIADP_Emails::get_email_type_configs()[ $type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email type.', 'avenia-digital-products' ) ) );
		}

		$user = wp_get_current_user();
		if ( ! $user->exists() || ! is_email( $user->user_email ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid email address found for your user account.', 'avenia-digital-products' ) ) );
		}

		$overrides = array();
		if ( isset( $_POST['subject'] ) ) {
			$overrides['subject'] = sanitize_text_field( wp_unslash( $_POST['subject'] ) );
		}
		if ( isset( $_POST['message'] ) ) {
			$overrides['message'] = wp_kses_post( wp_unslash( $_POST['message'] ) );
		}

		$emails = function_exists( 'aveniadp_emails' ) ? aveniadp_emails() : new AVENIADP_Emails();
		$sent   = $emails->send_test_email( $type, $user->user_email, $overrides );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send test email. Check that wp_mail is configured.', 'avenia-digital-products' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: recipient email address */
					__( 'Test email sent to %s.', 'avenia-digital-products' ),
					$user->user_email
				),
			)
		);
	}
}
