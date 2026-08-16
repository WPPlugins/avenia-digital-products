<?php
/**
 * Upgrade helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pricing page URL for the paid edition.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_pro_upgrade_url() {
	return 'https://aveniadigitalproducts.com/pricing';
}
