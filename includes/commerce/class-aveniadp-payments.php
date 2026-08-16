<?php
/**
 * Payment gateway abstraction.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payments class.
 *
 * @since 1.0.0
 */
class AVENIADP_Payments {

	/**
	 * Available gateways.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $gateways = array();

	/**
	 * Initialize payments.
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
		// Register gateways.
		$this->register_gateways();
	}

	/**
	 * Register payment gateways.
	 *
	 * @since 1.0.0
	 */
	private function register_gateways() {
		$this->gateways['stripe'] = new AVENIADP_Stripe();
		$this->gateways['paypal'] = new AVENIADP_PayPal();
	}

	/**
	 * Get gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return object|null
	 * @since 1.0.0
	 */
	public function get_gateway( $gateway_id ) {
		return isset( $this->gateways[ $gateway_id ] ) ? $this->gateways[ $gateway_id ] : null;
	}

	/**
	 * Get enabled gateways.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_enabled_gateways() {
		$enabled = array();

		foreach ( $this->gateways as $id => $gateway ) {
			if ( $gateway->is_enabled() ) {
				$enabled[ $id ] = $gateway;
			}
		}

		return $enabled;
	}

	/**
	 * Process a simulated payment (no gateway calls).
	 *
	 * @param array $order_data Order data.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_simulated_payment( $order_data ) {
		if ( ! aveniadp_is_simulate_payments() ) {
			return new WP_Error( 'simulate_disabled', __( 'Simulated payments are not enabled.', 'avenia-digital-products' ) );
		}

		return array(
			'success' => true,
			'mode'    => 'simulated',
			'gateway' => 'simulate',
		);
	}

	/**
	 * Process payment.
	 *
	 * @param array  $order_data Order data.
	 * @param string $gateway_id Gateway ID.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_payment( $order_data, $gateway_id ) {
		$gateway = $this->get_gateway( $gateway_id );

		if ( ! $gateway ) {
			return new WP_Error( 'invalid_gateway', __( 'Invalid payment gateway.', 'avenia-digital-products' ) );
		}

		if ( ! $gateway->is_enabled() ) {
			return new WP_Error( 'gateway_disabled', __( 'Payment gateway is disabled.', 'avenia-digital-products' ) );
		}

		return $gateway->process_payment( $order_data );
	}

}

/**
 * Inline logo markup for a payment gateway button.
 *
 * @param string $gateway_id Gateway ID (stripe|paypal).
 * @return string SVG HTML or empty string.
 * @since 1.0.0
 */
function aveniadp_get_payment_gateway_logo_html( $gateway_id ) {
	$gateway_id = sanitize_key( $gateway_id );

	switch ( $gateway_id ) {
		case 'stripe':
			return '<svg class="aveniadp-payment-logo aveniadp-payment-logo--stripe" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 214" width="56" height="23" focusable="false"><path fill="#635BFF" d="M512 110.08c0-36.409-17.636-65.138-51.342-65.138-33.85 0-54.33 28.73-54.33 64.854 0 42.808 24.179 64.426 58.88 64.426 16.925 0 29.725-3.84 39.396-9.244v-28.445c-9.67 4.836-20.764 7.823-34.844 7.823-13.796 0-26.027-4.836-27.591-21.618h69.547c0-1.85.284-9.245.284-12.658m-70.258-13.511c0-16.071 9.814-22.756 18.774-22.756 8.675 0 17.92 6.685 17.92 22.756zm-90.31-51.627c-13.939 0-22.899 6.542-27.876 11.094l-1.85-8.818h-31.288v165.83l35.555-7.537.143-40.249c5.12 3.698 12.657 8.96 25.173 8.96 25.458 0 48.64-20.48 48.64-65.564-.142-41.245-23.609-63.716-48.498-63.716m-8.534 97.991c-8.391 0-13.37-2.986-16.782-6.684l-.143-52.765c3.698-4.124 8.818-6.968 16.925-6.968 12.942 0 21.902 14.506 21.902 33.137 0 19.058-8.818 33.28-21.902 33.28M241.493 36.551l35.698-7.68V0l-35.698 7.538zm0 10.809h35.698v124.444h-35.698zm-38.257 10.524L200.96 47.36h-30.72v124.444h35.556V87.467c8.39-10.951 22.613-8.96 27.022-7.396V47.36c-4.551-1.707-21.191-4.836-29.582 10.524m-71.112-41.386-34.702 7.395-.142 113.92c0 21.05 15.787 36.551 36.836 36.551 11.662 0 20.195-2.133 24.888-4.693V140.8c-4.55 1.849-27.022 8.391-27.022-12.658V77.653h27.022V47.36h-27.022zM35.982 83.484c0-5.546 4.551-7.68 12.09-7.68 10.808 0 24.461 3.272 35.27 9.103V51.484c-11.804-4.693-23.466-6.542-35.27-6.542C19.2 44.942 0 60.018 0 85.192c0 39.252 54.044 32.995 54.044 49.92 0 6.541-5.688 8.675-13.653 8.675-11.804 0-26.88-4.836-38.827-11.378v33.849c13.227 5.689 26.596 8.106 38.827 8.106 29.582 0 49.92-14.648 49.92-40.106-.142-42.382-54.329-34.845-54.329-50.774"/></svg>';

		case 'paypal':
			return '<svg class="aveniadp-payment-logo aveniadp-payment-logo--paypal" xmlns="http://www.w3.org/2000/svg" viewBox="39 5 82 26" width="70" height="18" focusable="false"><path fill="#003087" d="M46.211 6.749h-6.839a.95.95 0 0 0-.939.802l-2.766 17.537a.57.57 0 0 0 .564.658h3.265a.95.95 0 0 0 .939-.803l.746-4.73a.95.95 0 0 1 .938-.803h2.165c4.505 0 7.105-2.18 7.784-6.5.306-1.89.013-3.375-.872-4.415-.972-1.142-2.696-1.746-4.985-1.746zM47 13.154c-.374 2.454-2.249 2.454-4.062 2.454h-1.032l.724-4.583a.57.57 0 0 1 .563-.481h.473c1.235 0 2.4 0 3.002.704.359.42.469 1.044.332 1.906zM66.654 13.075h-3.275a.57.57 0 0 0-.563.481l-.145.916-.229-.332c-.709-1.029-2.29-1.373-3.868-1.373-3.619 0-6.71 2.741-7.312 6.586-.313 1.918.132 3.752 1.22 5.031.998 1.176 2.426 1.666 4.125 1.666 2.916 0 4.533-1.875 4.533-1.875l-.146.91a.57.57 0 0 0 .562.66h2.95a.95.95 0 0 0 .939-.803l1.77-11.209a.568.568 0 0 0-.561-.658zm-4.565 6.374c-.316 1.871-1.801 3.127-3.695 3.127-.951 0-1.711-.305-2.199-.883-.484-.574-.668-1.391-.514-2.301.295-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.499.589.697 1.411.554 2.317zM84.096 13.075h-3.291a.954.954 0 0 0-.787.417l-4.539 6.686-1.924-6.425a.953.953 0 0 0-.912-.678h-3.234a.57.57 0 0 0-.541.754l3.625 10.638-3.408 4.811a.57.57 0 0 0 .465.9h3.287a.949.949 0 0 0 .781-.408l10.946-15.8a.57.57 0 0 0-.468-.895z"/><path fill="#009CDE" d="M94.992 6.749h-6.84a.95.95 0 0 0-.938.802l-2.766 17.537a.569.569 0 0 0 .562.658h3.51a.665.665 0 0 0 .656-.562l.785-4.971a.95.95 0 0 1 .938-.803h2.164c4.506 0 7.105-2.18 7.785-6.5.307-1.89.012-3.375-.873-4.415-.971-1.142-2.694-1.746-4.983-1.746zm.789 6.405c-.373 2.454-2.248 2.454-4.062 2.454h-1.031l.725-4.583a.568.568 0 0 1 .562-.481h.473c1.234 0 2.4 0 3.002.704.359.42.468 1.044.331 1.906zM114.436 13.075h-3.273a.567.567 0 0 0-.562.481l-.145.916-.23-.332c-.709-1.029-2.289-1.373-3.867-1.373-3.619 0-6.709 2.741-7.311 6.586-.312 1.918.131 3.752 1.219 5.031 1 1.176 2.426 1.666 4.125 1.666 2.916 0 4.533-1.875 4.533-1.875l-.146.91a.57.57 0 0 0 .564.66h2.949a.95.95 0 0 0 .938-.803l1.771-11.209a.571.571 0 0 0-.565-.658zm-4.565 6.374c-.314 1.871-1.801 3.127-3.695 3.127-.949 0-1.711-.305-2.199-.883-.484-.574-.666-1.391-.514-2.301.297-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.501.589.699 1.411.554 2.317zM119.295 7.23l-2.807 17.858a.569.569 0 0 0 .562.658h2.822c.469 0 .867-.34.939-.803l2.768-17.536a.57.57 0 0 0-.562-.659h-3.16a.571.571 0 0 0-.562.482z"/></svg>';

		default:
			return '';
	}
}
