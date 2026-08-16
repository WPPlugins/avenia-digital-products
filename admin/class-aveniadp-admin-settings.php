<?php
/**
 * Admin settings save handler.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings class.
 *
 * @since 1.0.0
 */
class AVENIADP_Admin_Settings {

	/**
	 * Whether settings were saved during this request.
	 *
	 * @var bool
	 */
	private static $saved = false;

	/**
	 * Handle settings form submission on admin_init.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_save() {
		if ( ! isset( $_GET['page'] ) || 'aveniadp-settings' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_POST['aveniadp_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'aveniadp_settings', 'aveniadp_settings_nonce' );

		self::save_from_request( wp_unslash( $_POST ) );
		self::$saved = true;
	}

	/**
	 * Whether settings were saved during this request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function was_saved() {
		return self::$saved;
	}

	/**
	 * Persist settings from POST data.
	 *
	 * @param array $post Raw POST data (already unslashed).
	 * @since 1.0.0
	 */
	public static function save_from_request( $post ) {
		$tab = isset( $post['aveniadp_settings_tab'] ) ? sanitize_key( $post['aveniadp_settings_tab'] ) : '';

		// General settings.
		if ( 'general' === $tab ) {
			if ( isset( $post['aveniadp_currency'] ) ) {
				update_option( 'aveniadp_currency', sanitize_text_field( $post['aveniadp_currency'] ) );
			}
			if ( isset( $post['aveniadp_currency_position'] ) ) {
				update_option( 'aveniadp_currency_position', sanitize_text_field( $post['aveniadp_currency_position'] ) );
			}

			if ( isset( $post['aveniadp_tax_mode'] ) ) {
				$tax_modes = aveniadp_get_tax_modes();
				$tax_mode  = sanitize_key( $post['aveniadp_tax_mode'] );
				update_option( 'aveniadp_tax_mode', array_key_exists( $tax_mode, $tax_modes ) ? $tax_mode : 'none' );
			}
			if ( isset( $post['aveniadp_tax_rate'] ) ) {
				$tax_rate = max( 0, min( 100, (float) $post['aveniadp_tax_rate'] ) );
				update_option( 'aveniadp_tax_rate', $tax_rate );
			}

			update_option( 'aveniadp_skip_cart_to_checkout', isset( $post['aveniadp_skip_cart_to_checkout'] ) ? 'yes' : 'no' );
			update_option( 'aveniadp_free_download_require_email', isset( $post['aveniadp_free_download_require_email'] ) );

			if ( isset( $post['aveniadp_product_base'] ) ) {
				$old_base = get_option( 'aveniadp_product_base', aveniadp_get_default_product_base_slug() );
				$new_base = sanitize_title( $post['aveniadp_product_base'] );
				if ( '' === $new_base ) {
					$new_base = aveniadp_get_default_product_base_slug();
				}
				if ( $old_base !== $new_base ) {
					update_option( 'aveniadp_product_base', $new_base );
					flush_rewrite_rules();
				}
			}

			if ( isset( $post['aveniadp_account_creation'] ) ) {
				update_option( 'aveniadp_account_creation', sanitize_text_field( $post['aveniadp_account_creation'] ) );
			}

			update_option( 'aveniadp_delete_data_on_uninstall', isset( $post['aveniadp_delete_data_on_uninstall'] ) ? 'yes' : 'no' );

			if ( isset( $post['aveniadp_thank_you_page'] ) ) {
				update_option( 'aveniadp_thank_you_page', absint( $post['aveniadp_thank_you_page'] ) );
			}

			if ( isset( $post['aveniadp_page_cart_id'] ) ) {
				update_option( 'aveniadp_page_cart_id', absint( $post['aveniadp_page_cart_id'] ) );
			}
			if ( isset( $post['aveniadp_page_checkout_id'] ) ) {
				update_option( 'aveniadp_page_checkout_id', absint( $post['aveniadp_page_checkout_id'] ) );
			}
			if ( isset( $post['aveniadp_page_account_id'] ) ) {
				update_option( 'aveniadp_page_account_id', absint( $post['aveniadp_page_account_id'] ) );
			}
			if ( isset( $post['aveniadp_page_login_id'] ) ) {
				update_option( 'aveniadp_page_login_id', absint( $post['aveniadp_page_login_id'] ) );
			}
			if ( isset( $post['aveniadp_page_shop_id'] ) ) {
				update_option( 'aveniadp_page_shop_id', absint( $post['aveniadp_page_shop_id'] ) );
			}
		}

		// Appearance settings.
		if ( 'appearance' === $tab && isset( $post['aveniadp_appearance_button_bg'] ) ) {
			$appearance_defaults = function_exists( 'aveniadp_get_appearance_defaults' ) ? aveniadp_get_appearance_defaults() : array();
			$appearance          = array(
				'button_bg'     => function_exists( 'aveniadp_sanitize_hex_color_or_default' )
					? aveniadp_sanitize_hex_color_or_default( $post['aveniadp_appearance_button_bg'], $appearance_defaults['button_bg'] ?? '#e8e8e8' )
					: sanitize_hex_color( $post['aveniadp_appearance_button_bg'] ),
				'tab_active_bg' => function_exists( 'aveniadp_sanitize_hex_color_or_default' )
					? aveniadp_sanitize_hex_color_or_default( $post['aveniadp_appearance_tab_active_bg'], $appearance_defaults['tab_active_bg'] ?? '#111111' )
					: sanitize_hex_color( $post['aveniadp_appearance_tab_active_bg'] ),
				'border_color'  => function_exists( 'aveniadp_sanitize_hex_color_or_default' )
					? aveniadp_sanitize_hex_color_or_default( $post['aveniadp_appearance_border_color'], $appearance_defaults['border_color'] ?? '#e0e0e0' )
					: sanitize_hex_color( $post['aveniadp_appearance_border_color'] ),
				'panel_bg'      => function_exists( 'aveniadp_sanitize_hex_color_or_default' )
					? aveniadp_sanitize_hex_color_or_default( $post['aveniadp_appearance_panel_bg'], $appearance_defaults['panel_bg'] ?? '#f3f3f3' )
					: sanitize_hex_color( $post['aveniadp_appearance_panel_bg'] ),
			);

			update_option( 'aveniadp_appearance', $appearance );
		}

		// Email settings — only when Emails tab is saved (avoids wiping toggles from other tabs).
		if ( 'emails' === $tab ) {
			if ( isset( $post['aveniadp_email_from_name'] ) ) {
				update_option( 'aveniadp_email_from_name', sanitize_text_field( $post['aveniadp_email_from_name'] ) );
			}
			if ( isset( $post['aveniadp_email_from_email'] ) ) {
				$email = sanitize_email( $post['aveniadp_email_from_email'] );
				if ( is_email( $email ) ) {
					update_option( 'aveniadp_email_from_email', $email );
				}
			}
			if ( isset( $post['aveniadp_email_logo'] ) ) {
				update_option( 'aveniadp_email_logo', absint( $post['aveniadp_email_logo'] ) );
			}

			update_option( 'aveniadp_email_order_confirmation_enabled', isset( $post['aveniadp_email_order_confirmation_enabled'] ) );
			update_option( 'aveniadp_email_admin_order_notification_enabled', isset( $post['aveniadp_email_admin_order_notification_enabled'] ) );
			update_option( 'aveniadp_email_account_confirmation_enabled', isset( $post['aveniadp_email_account_confirmation_enabled'] ) );
			update_option( 'aveniadp_email_download_link_enabled', isset( $post['aveniadp_email_download_link_enabled'] ) );
			if ( isset( $post['aveniadp_email_download_link_expiration'] ) ) {
				update_option( 'aveniadp_email_download_link_expiration', absint( $post['aveniadp_email_download_link_expiration'] ) );
			}

			if ( isset( $post['aveniadp_email_order_confirmation_subject'] ) ) {
				update_option( 'aveniadp_email_order_confirmation_subject', sanitize_text_field( $post['aveniadp_email_order_confirmation_subject'] ) );
			}
			if ( isset( $post['aveniadp_email_order_confirmation_message'] ) ) {
				update_option( 'aveniadp_email_order_confirmation_message', wp_kses_post( $post['aveniadp_email_order_confirmation_message'] ) );
			}

			if ( isset( $post['aveniadp_email_admin_order_recipients'] ) ) {
				$recipient_parts = preg_split( '/[\s,;]+/', (string) $post['aveniadp_email_admin_order_recipients'] );
				$recipients      = array();
				foreach ( (array) $recipient_parts as $part ) {
					$email = sanitize_email( $part );
					if ( is_email( $email ) ) {
						$recipients[] = $email;
					}
				}
				update_option( 'aveniadp_email_admin_order_recipients', implode( ', ', array_unique( $recipients ) ) );
			}
			if ( isset( $post['aveniadp_email_admin_order_notification_subject'] ) ) {
				update_option( 'aveniadp_email_admin_order_notification_subject', sanitize_text_field( $post['aveniadp_email_admin_order_notification_subject'] ) );
			}
			if ( isset( $post['aveniadp_email_admin_order_notification_message'] ) ) {
				update_option( 'aveniadp_email_admin_order_notification_message', wp_kses_post( $post['aveniadp_email_admin_order_notification_message'] ) );
			}

			if ( isset( $post['aveniadp_email_account_confirmation_subject'] ) ) {
				update_option( 'aveniadp_email_account_confirmation_subject', sanitize_text_field( $post['aveniadp_email_account_confirmation_subject'] ) );
			}
			if ( isset( $post['aveniadp_email_account_confirmation_message'] ) ) {
				update_option( 'aveniadp_email_account_confirmation_message', wp_kses_post( $post['aveniadp_email_account_confirmation_message'] ) );
			}

			if ( isset( $post['aveniadp_email_download_link_subject'] ) ) {
				update_option( 'aveniadp_email_download_link_subject', sanitize_text_field( $post['aveniadp_email_download_link_subject'] ) );
			}
			if ( isset( $post['aveniadp_email_download_link_message'] ) ) {
				update_option( 'aveniadp_email_download_link_message', wp_kses_post( $post['aveniadp_email_download_link_message'] ) );
			}

		}

		// Payment settings — only when Payment tab is saved.
		if ( 'payment' === $tab ) {
			update_option( 'aveniadp_simulate_payments', isset( $post['aveniadp_simulate_payments'] ) ? 'yes' : 'no' );

			$payment_environment = isset( $post['aveniadp_payment_environment'] ) ? sanitize_text_field( $post['aveniadp_payment_environment'] ) : 'sandbox';
			if ( ! in_array( $payment_environment, array( 'sandbox', 'live' ), true ) ) {
				$payment_environment = 'sandbox';
			}
			update_option( 'aveniadp_payment_environment', $payment_environment );

			update_option( 'aveniadp_stripe_enabled', isset( $post['aveniadp_stripe_enabled'] ) ? 'yes' : 'no' );
			self::maybe_update_secret_option( 'aveniadp_stripe_test_secret_key', $post );
			self::maybe_update_secret_option( 'aveniadp_stripe_live_secret_key', $post );
			self::maybe_update_secret_option( 'aveniadp_stripe_webhook_secret_test', $post );
			self::maybe_update_secret_option( 'aveniadp_stripe_webhook_secret', $post );

			update_option( 'aveniadp_paypal_enabled', isset( $post['aveniadp_paypal_enabled'] ) ? 'yes' : 'no' );
			if ( isset( $post['aveniadp_paypal_test_client_id'] ) ) {
				update_option( 'aveniadp_paypal_test_client_id', sanitize_text_field( $post['aveniadp_paypal_test_client_id'] ) );
			}
			self::maybe_update_secret_option( 'aveniadp_paypal_test_secret', $post );
			if ( isset( $post['aveniadp_paypal_live_client_id'] ) ) {
				update_option( 'aveniadp_paypal_live_client_id', sanitize_text_field( $post['aveniadp_paypal_live_client_id'] ) );
			}
			self::maybe_update_secret_option( 'aveniadp_paypal_live_secret', $post );
		}
	}

	/**
	 * Update a secret option only when a non-empty value was submitted.
	 *
	 * Empty password fields mean "keep the existing secret".
	 *
	 * @param string $option Option name.
	 * @param array  $post   Unslashed POST data.
	 * @since 1.0.0
	 */
	private static function maybe_update_secret_option( $option, $post ) {
		if ( ! isset( $post[ $option ] ) ) {
			return;
		}

		$value = sanitize_text_field( $post[ $option ] );
		if ( '' === $value ) {
			return;
		}

		update_option( $option, $value );
	}

	/**
	 * Render a password input for a stored secret (value never echoed back).
	 *
	 * @param string $option      Option name / input name.
	 * @param string $placeholder Placeholder hint (e.g. sk_live_...).
	 * @since 1.0.0
	 */
	public static function render_secret_input( $option, $placeholder = '' ) {
		$option      = sanitize_key( $option );
		$has_value   = '' !== (string) get_option( $option, '' );
		$placeholder = $has_value
			? __( '••••••••  (leave blank to keep current)', 'avenia-digital-products' )
			: $placeholder;
		?>
		<input
			type="password"
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			value=""
			class="regular-text"
			autocomplete="new-password"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
		/>
		<?php
	}
}
