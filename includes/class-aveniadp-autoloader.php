<?php
/**
 * Autoloader class.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class.
 *
 * @since 1.0.0
 */
class AVENIADP_Autoloader {

	/**
	 * Run autoloader.
	 *
	 * @since 1.0.0
	 */
	public static function run() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name Class name.
	 * @since 1.0.0
	 */
	public static function autoload( $class_name ) {
		// Only autoload our classes.
		if ( 0 !== strpos( $class_name, 'AVENIADP_' ) ) {
			return;
		}

		// Convert class name to file name.
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Possible directories.
		$directories = array(
			AVENIADP_PLUGIN_DIR . 'includes/',
			AVENIADP_PLUGIN_DIR . 'includes/infrastructure/',
			AVENIADP_PLUGIN_DIR . 'includes/commerce/',
			AVENIADP_PLUGIN_DIR . 'includes/products/',
			AVENIADP_PLUGIN_DIR . 'includes/gateways/',
			AVENIADP_PLUGIN_DIR . 'admin/',
			AVENIADP_PLUGIN_DIR . 'public/',
		);

		// Try to find and load the file.
		foreach ( $directories as $directory ) {
			$file_path = $directory . $file_name;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return;
			}
		}
	}
}
