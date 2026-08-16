<?php
/**
 * Lightweight logging helper.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 *
 * @since 1.0.0
 */
class AVENIADP_Logger {

	/**
	 * Log an entry.
	 *
	 * @param string $channel Short channel name (e.g. stripe, paypal, license).
	 * @param string $message Message.
	 * @param array  $context Optional structured context.
	 * @return void
	 * @since 1.0.0
	 */
	public function log( $channel, $message, $context = array() ) {
		$channel = sanitize_key( $channel );

		/**
		 * Filter: aveniadp_log_entry
		 *
		 * Allow custom logging of Avenia Digital Products events.
		 *
		 * @param array $entry {
		 *     @type string $channel Log channel.
		 *     @type string $message Message.
		 *     @type array  $context Additional context.
		 * }
		 */
		do_action(
			'aveniadp_log_entry',
			array(
				'channel' => $channel,
				'message' => $message,
				'context' => $context,
			)
		);

		// Fallback to error_log in debug environments.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$entry = '[SD ' . strtoupper( $channel ) . '] ' . $message;
			if ( ! empty( $context ) && is_array( $context ) ) {
				$entry .= ' ' . wp_json_encode( $context );
			}
			error_log( $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

