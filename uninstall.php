<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly or uninstall was not called from WordPress.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'AVENIADP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once AVENIADP_PLUGIN_DIR . 'includes/class-aveniadp-autoloader.php';
AVENIADP_Autoloader::run();

AVENIADP_Install::uninstall();
