<?php
/**
 * Core functionality.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class.
 *
 * @since 1.0.0
 */
class AVENIADP_Core {

	/**
	 * Initialize core functionality.
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
		// Load dependencies.
		$this->load_dependencies();

		// Initialize components.
		add_action( 'init', array( $this, 'init_components' ), 10 );
		add_action( 'init', array( $this, 'maybe_sync_core_pages' ), 5 );
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-functions.php';

		// Register CPT/taxonomies on init — must construct before init runs (not inside init_components at priority 10,
		// or register_post_type may never run in the same request).
		new AVENIADP_Products();
		new AVENIADP_Blocks();
	}

	/**
	 * Bootstrap plugin services on init.
	 *
	 * Service instances are retrieved via aveniadp_*() accessors in aveniadp-functions.php (singletons).
	 *
	 * Hooked services — constructors register actions, filters, cron, or REST routes — must be
	 * bootstrapped here so WordPress hooks are wired on every request:
	 *   aveniadp_cache(), aveniadp_cart(), aveniadp_checkout(), aveniadp_downloads(), aveniadp_payments()
	 *   plus AVENIADP_Public and the AVENIADP_Admin facades (no aveniadp_*() accessor).
	 *   AVENIADP_Public is always constructed; frontend hooks are gated inside the class.
	 *
	 * Lazy services — stateless data/logic helpers with no constructor side effects — are created
	 * on first use only: aveniadp_orders(), aveniadp_customers(), aveniadp_discounts(), aveniadp_access(), aveniadp_emails(),
	 * aveniadp_logger(). Do not bootstrap those here.
	 *
	 * @since 1.0.0
	 */
	public function init_components() {
		aveniadp_protected_uploads();
		aveniadp_cache();
		aveniadp_cart();
		aveniadp_checkout();
		aveniadp_downloads();
		aveniadp_payments();

		if ( is_admin() ) {
			new AVENIADP_Admin();
			new AVENIADP_Admin_Access();
		}

		new AVENIADP_Public();

		/**
		 * Fires after core plugin services are initialized on `init`.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aveniadp_init' );
	}

	/**
	 * Sync core storefront page options (clear stale IDs; do not recreate pages).
	 *
	 * @since 1.0.0
	 */
	public function maybe_sync_core_pages() {
		AVENIADP_Install::maybe_upgrade_db();
		AVENIADP_Install::sync_core_page_options();
	}
}
