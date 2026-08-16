<?php
/**
 * Email notification system.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emails class.
 *
 * @since 1.0.0
 */
class AVENIADP_Emails {

	/**
	 * Send email.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message (HTML).
	 * @param string $type    Email type (for filtering).
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_email( $to, $subject, $message, $type = '' ) {
		// Check if email type is enabled.
		if ( ! empty( $type ) && ! $this->is_email_enabled( $type ) ) {
			return false;
		}

		// Get sender info.
		$from_name  = get_option( 'aveniadp_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'aveniadp_email_from_email', get_option( 'admin_email' ) );

		// Send HTML and let PHPMailer build multipart/alternative.
		$plain_text = $this->html_to_plain_text( $message );
		$headers    = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		$alt_body_callback = static function ( $phpmailer ) use ( $plain_text ) {
			$phpmailer->AltBody = $plain_text;
		};
		add_action( 'phpmailer_init', $alt_body_callback );

		$sent = wp_mail( $to, $subject, $message, $headers );

		remove_action( 'phpmailer_init', $alt_body_callback );

		/**
		 * Fires after a transactional email is sent or attempted.
		 *
		 * @since 1.0.0
		 *
		 * @param string $type    Email type (order_confirmation, admin_order_notification, download_link, account_confirmation).
		 * @param string $to      Recipient email address.
		 * @param string $subject Email subject.
		 * @param bool   $sent    Whether wp_mail() reported success.
		 */
		do_action( 'aveniadp_email_sent', $type, $to, $subject, $sent );

		return $sent;
	}

	/**
	 * Convert HTML email content to a readable plain-text alternative.
	 *
	 * @param string $html HTML message.
	 * @return string
	 * @since 1.0.0
	 */
	private function html_to_plain_text( $html ) {
		$text = preg_replace( '/<(br|\/p|\/div|\/tr|\/h[1-6])[^>]*>/i', "\n", $html );
		$text = preg_replace( '/<\/td>/i', "\t", $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n[ \t]+/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Check if email type is enabled.
	 *
	 * @param string $type Email type.
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_email_enabled( $type ) {
		$enabled = get_option( 'aveniadp_email_' . $type . '_enabled', true );
		return (bool) $enabled;
	}

	/**
	 * Wrap email body content in the shared branded template.
	 *
	 * @param string $content Inner HTML content.
	 * @return string
	 * @since 1.0.0
	 */
	public function wrap_email( $content ) {
		$email_content = $content;

		ob_start();
		include AVENIADP_PLUGIN_DIR . 'includes/emails/wrapper.php';
		return ob_get_clean();
	}

	/**
	 * Get stored email content with fallback defaults.
	 *
	 * @param string $option_name Option key for message content.
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_stored_email_content( $option_name ) {
		$content = get_option( $option_name, '' );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$content = self::get_default_email_content( $option_name );
		}

		return $content;
	}

	/**
	 * Default editable email content by option name.
	 *
	 * @param string $option_name Option key.
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_default_email_content( $option_name ) {
		$defaults = array(
			'aveniadp_email_order_confirmation_message' => implode(
				"\n",
				array(
					'<p>' . sprintf(
						/* translators: %s: product title(s) placeholder */
						esc_html__( 'Thanks for buying %s!', 'avenia-digital-products' ),
						'<strong>{product_titles}</strong>'
					) . '</p>',
					'<p>' . esc_html__( 'Your order has been confirmed.', 'avenia-digital-products' ) . '</p>',
					'<p><strong>' . esc_html__( 'Order', 'avenia-digital-products' ) . ':</strong> #{order_id}</p>',
					'{order_items}',
					'<p><strong>' . esc_html__( 'Total', 'avenia-digital-products' ) . ':</strong> {order_total}</p>',
				)
			),
			'aveniadp_email_admin_order_notification_message' => implode(
				"\n",
				array(
					'<p>' . esc_html__( 'You have received a new order.', 'avenia-digital-products' ) . '</p>',
					'<p><strong>' . esc_html__( 'Order', 'avenia-digital-products' ) . ':</strong> #{order_id}</p>',
					'<p><strong>' . esc_html__( 'Customer', 'avenia-digital-products' ) . ':</strong> {customer_name} ({customer_email})</p>',
					'{order_items}',
					'<p><strong>' . esc_html__( 'Total', 'avenia-digital-products' ) . ':</strong> {order_total}</p>',
					'{view_order_button}',
				)
			),
			'aveniadp_email_account_confirmation_message' => implode(
				"\n",
				array(
					'<p>' . esc_html__( 'Hello,', 'avenia-digital-products' ) . '</p>',
					'<p>' . esc_html__( 'An account has been created for you.', 'avenia-digital-products' ) . '</p>',
					'<p><strong>' . esc_html__( 'Username', 'avenia-digital-products' ) . ':</strong> {username}</p>',
					'<p>' . esc_html__( 'To set your password, please click the button below:', 'avenia-digital-products' ) . '</p>',
					'{set_password_button}',
				)
			),
			'aveniadp_email_download_link_message'      => implode(
				"\n",
				array(
					'<p>' . esc_html__( 'Thank you for your purchase!', 'avenia-digital-products' ) . '</p>',
					'<p>' . sprintf(
						/* translators: %s: product title placeholder */
						esc_html__( 'You can download %s using the link below:', 'avenia-digital-products' ),
						'<strong>{product_title}</strong>'
					) . '</p>',
					'{download_button}',
					'<p>' . sprintf(
						/* translators: %s: number of days */
						esc_html__( 'This download link expires in %s days.', 'avenia-digital-products' ),
						'<strong>{download_expiration_days}</strong>'
					) . '</p>',
					'<p>' . esc_html__( 'You can always download this product anytime from your account:', 'avenia-digital-products' ) . '</p>',
					'{account_button}',
				)
			),
		);

		return isset( $defaults[ $option_name ] ) ? $defaults[ $option_name ] : '';
	}

	/**
	 * Replace placeholders and wrap content for sending.
	 *
	 * @param string $option_name  Option key for message content.
	 * @param array  $replacements Placeholder => value map (without braces).
	 * @param string $email_type   Email type for filtering replacements.
	 * @param array  $context      Context data (order ID, customer, etc.).
	 * @return string
	 * @since 1.0.0
	 */
	private function build_email_from_option( $option_name, $replacements, $email_type = '', $context = array() ) {
		$content = self::get_stored_email_content( $option_name );

		return $this->build_email_content( $content, $replacements, $email_type, $context );
	}

	/**
	 * Replace placeholders and wrap raw email content for sending.
	 *
	 * @param string $content      Email body before wrapping.
	 * @param array  $replacements Placeholder => value map (without braces).
	 * @param string $email_type   Email type for filtering replacements.
	 * @param array  $context      Context data (order ID, customer, etc.).
	 * @return string
	 * @since 1.0.0
	 */
	private function build_email_content( $content, $replacements, $email_type = '', $context = array() ) {
		/**
		 * Filter email placeholder replacements before send.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $replacements Placeholder => value map (without braces).
		 * @param string $email_type   Email type (order_confirmation, download_link, account_confirmation).
		 * @param array  $context      Context data (order ID, customer, etc.).
		 */
		$replacements = apply_filters( 'aveniadp_email_replacements', $replacements, $email_type, $context );

		foreach ( $replacements as $placeholder => $value ) {
			$content = str_replace( '{' . $placeholder . '}', $value, $content );
		}

		return $this->wrap_email( $this->prepare_email_html( $content ) );
	}

	/**
	 * Normalize body HTML so paragraphs keep spacing in email clients.
	 *
	 * @param string $content Email body HTML before wrapping.
	 * @return string
	 * @since 1.0.0
	 */
	private function prepare_email_html( $content ) {
		$content = (string) $content;

		// Convert bare newlines to paragraphs/breaks after placeholders are expanded.
		if ( '' !== trim( $content ) ) {
			$content = wpautop( $content );
		}

		$content = preg_replace(
			'/<p(?![^>]*\bstyle=)(\s[^>]*)?>/i',
			'<p style="margin:0 0 16px;"$1>',
			$content
		);

		return $content;
	}

	/**
	 * Render order items table for email content.
	 *
	 * @param array $items Order items.
	 * @return string
	 * @since 1.0.0
	 */
	private function render_order_items_table( $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$border = '#dcdcde';
		$html   = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:16px 0;font-size:14px;">';
		$html  .= '<thead><tr>';
		$html  .= '<th align="left" style="padding:10px 12px;border-bottom:2px solid ' . esc_attr( $border ) . ';color:#646970;">' . esc_html__( 'Product', 'avenia-digital-products' ) . '</th>';
		$html  .= '<th align="right" style="padding:10px 12px;border-bottom:2px solid ' . esc_attr( $border ) . ';color:#646970;">' . esc_html__( 'Price', 'avenia-digital-products' ) . '</th>';
		$html  .= '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$product = get_post( $item->product_id );
			$title   = $product ? $product->post_title : __( 'Product', 'avenia-digital-products' );
			$html   .= '<tr>';
			$html   .= '<td style="padding:10px 12px;border-bottom:1px solid ' . esc_attr( $border ) . ';">' . esc_html( $title );
			if ( (int) $item->quantity > 1 ) {
				$html .= ' <span style="color:#646970;">&times; ' . esc_html( (string) $item->quantity ) . '</span>';
			}
			$html .= '</td>';
			$html .= '<td align="right" style="padding:10px 12px;border-bottom:1px solid ' . esc_attr( $border ) . ';">' . esc_html( aveniadp_format_price( $item->price ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Render a primary email button.
	 *
	 * @param string $url  Button URL.
	 * @param string $text Button label.
	 * @return string
	 * @since 1.0.0
	 */
	private function render_email_button( $url, $text ) {
		$accent = function_exists( 'aveniadp_get_appearance_accent_colors' )
			? aveniadp_get_appearance_accent_colors()
			: array(
				'bg'   => '#111111',
				'text' => '#ffffff',
			);

		return '<p style="margin:24px 0;"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 24px;background:' . esc_attr( $accent['bg'] ) . ';color:' . esc_attr( $accent['text'] ) . ';text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">' . esc_html( $text ) . '</a></p>';
	}

	/**
	 * Recipients for the admin new-order notification.
	 *
	 * Comma, semicolon, or whitespace separated. Falls back to the site admin email.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public function get_admin_order_notification_recipients() {
		$raw = get_option( 'aveniadp_email_admin_order_recipients', '' );
		if ( '' === trim( (string) $raw ) ) {
			$raw = get_option( 'admin_email' );
		}

		$parts  = preg_split( '/[\s,;]+/', (string) $raw );
		$emails = array();

		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( $part );
			if ( is_email( $email ) ) {
				$emails[] = strtolower( $email );
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Build shared order email placeholders for customer and admin notifications.
	 *
	 * @param object $order    Order row.
	 * @param object $customer Customer row.
	 * @param array  $items    Order items.
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	private function get_order_email_replacements( $order, $customer, $items ) {
		$view_order_url = admin_url( 'admin.php?page=aveniadp-orders&view=' . absint( $order->order_id ) );

		return array_merge(
			function_exists( 'aveniadp_get_customer_email_placeholders' ) ? aveniadp_get_customer_email_placeholders( $customer ) : array(),
			array(
				'order_id'          => (string) $order->order_id,
				'order_total'       => aveniadp_format_price( $order->total ),
				'order_items'       => $this->render_order_items_table( $items ),
				'product_titles'    => $this->get_order_product_titles( $items ),
				'customer_email'    => esc_html( $customer->email ),
				'payment_method'    => esc_html( (string) $order->payment_method ),
				'view_order_url'    => esc_url( $view_order_url ),
				'view_order_button' => $this->render_email_button( $view_order_url, __( 'View Order', 'avenia-digital-products' ) ),
			)
		);
	}

	/**
	 * Comma-separated product titles for an order (escaped).
	 *
	 * @param array $items Order items.
	 * @return string
	 * @since 1.0.0
	 */
	private function get_order_product_titles( $items ) {
		$titles = array();

		foreach ( (array) $items as $item ) {
			$product_id = isset( $item->product_id ) ? absint( $item->product_id ) : 0;
			$product    = $product_id ? get_post( $product_id ) : null;
			$titles[]   = $product ? $product->post_title : __( 'Product', 'avenia-digital-products' );
		}

		$titles = array_values( array_unique( array_filter( $titles ) ) );

		if ( empty( $titles ) ) {
			return esc_html__( 'your product', 'avenia-digital-products' );
		}

		return esc_html( implode( ', ', $titles ) );
	}

	/**
	 * Send order confirmation email.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_order_confirmation( $order_id ) {
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer( $order->customer_id );

		if ( ! $customer ) {
			return false;
		}

		$items           = $orders->get_order_items( $order->order_id );
		/* translators: %d: order ID */
		$default_subject = sprintf( __( 'Order Confirmation #%d', 'avenia-digital-products' ), $order_id );
		$subject         = get_option( 'aveniadp_email_order_confirmation_subject', $default_subject );
		$subject         = str_replace( '{order_id}', (string) $order_id, $subject );

		$message = $this->build_email_from_option(
			'aveniadp_email_order_confirmation_message',
			$this->get_order_email_replacements( $order, $customer, $items ),
			'order_confirmation',
			array(
				'order_id' => $order_id,
				'order'    => $order,
				'customer' => $customer,
			)
		);

		return $this->send_email( $customer->email, $subject, $message, 'order_confirmation' );
	}

	/**
	 * Send admin new-order notification email.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_admin_order_notification( $order_id ) {
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer( $order->customer_id );

		if ( ! $customer ) {
			return false;
		}

		$recipients = $this->get_admin_order_notification_recipients();
		if ( empty( $recipients ) ) {
			return false;
		}

		$items           = $orders->get_order_items( $order->order_id );
		/* translators: %d: order ID */
		$default_subject = sprintf( __( 'New Order #%d', 'avenia-digital-products' ), $order_id );
		$subject         = get_option( 'aveniadp_email_admin_order_notification_subject', $default_subject );
		$subject         = str_replace( '{order_id}', (string) $order_id, $subject );

		$message = $this->build_email_from_option(
			'aveniadp_email_admin_order_notification_message',
			$this->get_order_email_replacements( $order, $customer, $items ),
			'admin_order_notification',
			array(
				'order_id' => $order_id,
				'order'    => $order,
				'customer' => $customer,
			)
		);

		return $this->send_email( implode( ', ', $recipients ), $subject, $message, 'admin_order_notification' );
	}

	/**
	 * Send account confirmation email.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   Email address.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_account_confirmation( $user_id, $email ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$set_password_url = $this->get_set_password_url( $user );
		if ( '' === $set_password_url ) {
			return false;
		}

		$subject   = get_option( 'aveniadp_email_account_confirmation_subject', __( 'Welcome! Your account has been created', 'avenia-digital-products' ) );
		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer_by_email( $email );

		$message = $this->build_email_from_option(
			'aveniadp_email_account_confirmation_message',
			array_merge(
				$customer && function_exists( 'aveniadp_get_customer_email_placeholders' ) ? aveniadp_get_customer_email_placeholders( $customer ) : array(),
				array(
					'username'            => esc_html( $user->user_login ),
					'user_email'          => esc_html( $user->user_email ),
					'set_password_url'    => esc_url( $set_password_url ),
					'set_password_button' => $this->render_email_button( $set_password_url, __( 'Set Password', 'avenia-digital-products' ) ),
				)
			),
			'account_confirmation',
			array(
				'user_id'  => $user_id,
				'email'    => $email,
				'customer' => $customer,
			)
		);

		return $this->send_email( $email, $subject, $message, 'account_confirmation' );
	}

	/**
	 * Build a one-time set-password URL for a user (reset key + login).
	 *
	 * @param WP_User $user User object.
	 * @return string Empty string on failure.
	 * @since 1.0.0
	 */
	private function get_set_password_url( $user ) {
		if ( ! $user instanceof WP_User ) {
			return '';
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return '';
		}

		return network_site_url(
			'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);
	}

	/**
	 * Send download link email.
	 *
	 * @param int    $order_id     Order ID.
	 * @param int    $product_id   Product ID.
	 * @param string $download_url Download URL.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_download_link( $order_id, $product_id, $download_url ) {
		$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
		$order  = $orders->get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
		$customer  = $customers->get_customer( $order->customer_id );

		if ( ! $customer ) {
			return false;
		}

		$product         = get_post( $product_id );
		/* translators: %s: product title */
		$default_subject = sprintf( __( 'Download: %s', 'avenia-digital-products' ), $product->post_title );
		$subject         = get_option( 'aveniadp_email_download_link_subject', __( 'Download: {product_title}', 'avenia-digital-products' ) );
		$subject         = str_replace( '{product_title}', $product->post_title, $subject );

		$account_url = function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' );
		$expiration_days = function_exists( 'aveniadp_get_email_download_link_expiration_days' ) ? aveniadp_get_email_download_link_expiration_days() : 2;

		$message = $this->build_email_from_option(
			'aveniadp_email_download_link_message',
			array_merge(
				function_exists( 'aveniadp_get_customer_email_placeholders' ) ? aveniadp_get_customer_email_placeholders( $customer ) : array(),
				array(
					'product_title'            => esc_html( $product->post_title ),
					'download_url'             => esc_url( $download_url ),
					'download_button'          => $this->render_email_button( $download_url, __( 'Download Now', 'avenia-digital-products' ) ),
					'download_expiration_days' => esc_html( (string) $expiration_days ),
					'account_url'              => esc_url( $account_url ),
					'account_button'           => $this->render_email_button( $account_url, __( 'Go to My Account', 'avenia-digital-products' ) ),
				)
			),
			'download_link',
			array(
				'order_id'     => $order_id,
				'product_id'   => $product_id,
				'order'        => $order,
				'customer'     => $customer,
				'download_url' => $download_url,
			)
		);

		return $this->send_email( $customer->email, $subject, $message, 'download_link' );
	}

	/**
	 * Send a test email using sample placeholder data.
	 *
	 * @param string $type       Email type slug.
	 * @param string $to         Recipient email address.
	 * @param array  $overrides  Optional subject and message overrides from the settings form.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_test_email( $type, $to, $overrides = array() ) {
		$config = self::get_email_type_configs();

		if ( ! isset( $config[ $type ] ) ) {
			return false;
		}

		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$type_config  = $config[ $type ];
		$replacements = $this->get_test_email_replacements( $type );

		$content = isset( $overrides['message'] ) ? wp_kses_post( $overrides['message'] ) : '';
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$content = self::get_stored_email_content( $type_config['message_option'] );
		}

		$subject = isset( $overrides['subject'] ) ? sanitize_text_field( $overrides['subject'] ) : '';
		if ( '' === $subject ) {
			$subject = get_option( $type_config['subject_option'], $type_config['default_subject'] );
		}

		$subject = $this->replace_email_subject_placeholders( $subject, $replacements );
		/* translators: %s: email subject line */
		$subject = sprintf( __( '[Test] %s', 'avenia-digital-products' ), $subject );

		$message = $this->build_email_content(
			$content,
			$replacements,
			$type,
			array(
				'test' => true,
				'type' => $type,
			)
		);

		return $this->send_email( $to, $subject, $message, '' );
	}

	/**
	 * Email type configuration for test sends and defaults.
	 *
	 * @return array<string, array{message_option: string, subject_option: string, default_subject: string}>
	 * @since 1.0.0
	 */
	public static function get_email_type_configs() {
		return array(
			'order_confirmation'    => array(
				'message_option'  => 'aveniadp_email_order_confirmation_message',
				'subject_option'  => 'aveniadp_email_order_confirmation_subject',
				'default_subject' => __( 'Order Confirmation #{order_id}', 'avenia-digital-products' ),
			),
			'admin_order_notification' => array(
				'message_option'  => 'aveniadp_email_admin_order_notification_message',
				'subject_option'  => 'aveniadp_email_admin_order_notification_subject',
				'default_subject' => __( 'New Order #{order_id}', 'avenia-digital-products' ),
			),
			'account_confirmation'    => array(
				'message_option'  => 'aveniadp_email_account_confirmation_message',
				'subject_option'  => 'aveniadp_email_account_confirmation_subject',
				'default_subject' => __( 'Welcome! Your account has been created', 'avenia-digital-products' ),
			),
			'download_link'           => array(
				'message_option'  => 'aveniadp_email_download_link_message',
				'subject_option'  => 'aveniadp_email_download_link_subject',
				'default_subject' => __( 'Download: {product_title}', 'avenia-digital-products' ),
			),
		);
	}

	/**
	 * Replace placeholders in an email subject line.
	 *
	 * @param string $subject      Subject template.
	 * @param array  $replacements Placeholder => value map.
	 * @return string
	 * @since 1.0.0
	 */
	private function replace_email_subject_placeholders( $subject, $replacements ) {
		foreach ( $replacements as $placeholder => $value ) {
			$subject = str_replace( '{' . $placeholder . '}', wp_strip_all_tags( $value ), $subject );
		}

		return $subject;
	}

	/**
	 * Sample placeholder values for test emails.
	 *
	 * @param string $type Email type slug.
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	private function get_test_email_replacements( $type ) {
		$user         = wp_get_current_user();
		$display_name = $user->exists() ? $user->display_name : __( 'Customer', 'avenia-digital-products' );
		$replacements = array(
			'customer_name'     => $display_name,
			'customer_greeting' => $display_name
				? sprintf(
					/* translators: %s: customer name */
					__( 'Hi %s,', 'avenia-digital-products' ),
					$display_name
				)
				: __( 'Hi,', 'avenia-digital-products' ),
		);

		$sample_product_title = __( 'Sample Product', 'avenia-digital-products' );
		$product_ids          = get_posts(
			array(
				'post_type'              => AVENIADP_PRODUCT_POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! empty( $product_ids ) ) {
			$sample_post = get_post( $product_ids[0] );
			if ( $sample_post ) {
				$sample_product_title = $sample_post->post_title;
			}
		}

		$account_url = function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' );
		$sample_total = function_exists( 'aveniadp_format_price' ) ? aveniadp_format_price( 29.99 ) : '$29.99';

		if ( in_array( $type, array( 'order_confirmation', 'admin_order_notification' ), true ) ) {
			$sample_items   = array(
				(object) array(
					'product_id' => ! empty( $product_ids ) ? (int) $product_ids[0] : 0,
					'quantity'   => 1,
					'price'      => 29.99,
				),
			);
			$view_order_url = admin_url( 'admin.php?page=aveniadp-orders&view=1001' );
			$replacements['order_id']          = '1001';
			$replacements['order_total']       = $sample_total;
			$replacements['order_items']       = $this->render_order_items_table( $sample_items );
			$replacements['product_titles']    = esc_html( $sample_product_title );
			$replacements['customer_email']    = $user->exists() && is_email( $user->user_email )
				? esc_html( $user->user_email )
				: 'customer@example.com';
			$replacements['payment_method']    = 'stripe';
			$replacements['view_order_url']    = esc_url( $view_order_url );
			$replacements['view_order_button'] = $this->render_email_button( $view_order_url, __( 'View Order', 'avenia-digital-products' ) );
		}

		if ( 'account_confirmation' === $type ) {
			$sample_login = $user->exists() ? $user->user_login : 'customer@example.com';
			$sample_url   = network_site_url(
				'wp-login.php?action=rp&key=samplekey&login=' . rawurlencode( $sample_login ),
				'login'
			);
			$replacements['username']            = esc_html( $sample_login );
			$replacements['user_email']          = esc_html( $user->exists() && is_email( $user->user_email ) ? $user->user_email : 'customer@example.com' );
			$replacements['set_password_url']    = esc_url( $sample_url );
			$replacements['set_password_button'] = $this->render_email_button( $sample_url, __( 'Set Password', 'avenia-digital-products' ) );
		}

		if ( 'download_link' === $type ) {
			$download_url      = home_url( '/aveniadp-download/sample-access-key/' );
			$expiration_days   = function_exists( 'aveniadp_get_email_download_link_expiration_days' ) ? aveniadp_get_email_download_link_expiration_days() : 2;
			$replacements['product_title']            = esc_html( $sample_product_title );
			$replacements['download_url']             = esc_url( $download_url );
			$replacements['download_button']          = $this->render_email_button( $download_url, __( 'Download Now', 'avenia-digital-products' ) );
			$replacements['download_expiration_days'] = esc_html( (string) $expiration_days );
			$replacements['account_url']              = esc_url( $account_url );
			$replacements['account_button']           = $this->render_email_button( $account_url, __( 'Go to My Account', 'avenia-digital-products' ) );
		}

		return $replacements;
	}
}
