<?php
/**
 * Plugin Name: Avenia Digital Products
 * Plugin URI: https://aveniadigitalproducts.com
 * Description: Sell digital downloads on WordPress with Stripe, PayPal, a cart, checkout, and secure file delivery.
 * Version: 1.0.1
 * Author: Avenia Labs
 * Author URI: https://avenialabs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: avenia-digital-products
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AVENIADP_VERSION', '1.0.1' );
define( 'AVENIADP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVENIADP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AVENIADP_PRODUCT_POST_TYPE', 'aveniadp_product' );

require_once AVENIADP_PLUGIN_DIR . 'includes/class-aveniadp-autoloader.php';
AVENIADP_Autoloader::run();

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Avenia_Digital_Products {

	/**
	 * Plugin instance.
	 *
	 * @var Avenia_Digital_Products
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Avenia_Digital_Products
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		// Activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Initialize core.
		add_action( 'plugins_loaded', array( $this, 'init_core' ), 5 );
	}

	/**
	 * Initialize core functionality.
	 *
	 * @since 1.0.0
	 */
	public function init_core() {
		new AVENIADP_Core();

		/**
		 * Fires when Avenia Digital Products core is loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aveniadp_loaded' );
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Check minimum requirements.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'Avenia Digital Products requires PHP 8.0 or higher.', 'avenia-digital-products' ),
				esc_html__( 'Plugin Activation Error', 'avenia-digital-products' ),
				array( 'back_link' => true )
			);
		}

		if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'Avenia Digital Products requires WordPress 6.5 or higher.', 'avenia-digital-products' ),
				esc_html__( 'Plugin Activation Error', 'avenia-digital-products' ),
				array( 'back_link' => true )
			);
		}

		// Include helpers and run activation.
		require_once AVENIADP_PLUGIN_DIR . 'includes/functions/aveniadp-functions.php';
		AVENIADP_Install::activate();
	}
}

/**
 * Initialize plugin.
 *
 * @return Avenia_Digital_Products
 * @since 1.0.0
 */
function aveniadp() {
	return Avenia_Digital_Products::get_instance();
}

// Initialize plugin.
aveniadp();
