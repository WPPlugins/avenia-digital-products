<?php
/**
 * Storefront appearance CSS variable helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Default storefront appearance colors.
 *
 * @return array<string, string>
 * @since 1.0.0
 */
function aveniadp_get_appearance_defaults() {
	return array(
		'button_bg'     => '#e8e8e8',
		'tab_active_bg' => '#111111',
		'border_color'  => '#e0e0e0',
		'panel_bg'      => '#f3f3f3',
	);
}

/**
 * Sanitize a hex color, falling back to a default when invalid.
 *
 * @param string $color   Candidate color.
 * @param string $default Default color.
 * @return string
 * @since 1.0.0
 */
function aveniadp_sanitize_hex_color_or_default( $color, $default ) {
	$sanitized = sanitize_hex_color( $color );

	return $sanitized ? $sanitized : $default;
}

/**
 * Convert a hex color to an RGB array.
 *
 * @param string $hex Hex color.
 * @return array{r:int,g:int,b:int}
 * @since 1.0.0
 */
function aveniadp_hex_to_rgb_array( $hex ) {
	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	return array(
		'r' => (int) hexdec( substr( $hex, 0, 2 ) ),
		'g' => (int) hexdec( substr( $hex, 2, 2 ) ),
		'b' => (int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Mix two hex colors.
 *
 * @param string $hex1  Base color.
 * @param string $hex2  Target color.
 * @param float  $ratio Blend ratio toward the target color (0-1).
 * @return string
 * @since 1.0.0
 */
function aveniadp_mix_hex_colors( $hex1, $hex2, $ratio ) {
	$ratio = max( 0, min( 1, (float) $ratio ) );
	$from  = aveniadp_hex_to_rgb_array( $hex1 );
	$to    = aveniadp_hex_to_rgb_array( $hex2 );

	return sprintf(
		'#%02x%02x%02x',
		(int) round( $from['r'] + ( $to['r'] - $from['r'] ) * $ratio ),
		(int) round( $from['g'] + ( $to['g'] - $from['g'] ) * $ratio ),
		(int) round( $from['b'] + ( $to['b'] - $from['b'] ) * $ratio )
	);
}

/**
 * Pick black or white text for a background color.
 *
 * @param string $hex Background color.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_contrast_color( $hex ) {
	$rgb = aveniadp_hex_to_rgb_array( $hex );
	$yiq = ( ( $rgb['r'] * 299 ) + ( $rgb['g'] * 587 ) + ( $rgb['b'] * 114 ) ) / 1000;

	return $yiq >= 128 ? '#111111' : '#ffffff';
}

/**
 * Accent colors shared by active tabs, pills, and email CTAs.
 *
 * @return array{bg: string, text: string}
 * @since 1.0.0
 */
function aveniadp_get_appearance_accent_colors() {
	$colors = aveniadp_get_appearance_colors();

	return array(
		'bg'   => $colors['tab_active_bg'],
		'text' => aveniadp_get_contrast_color( $colors['tab_active_bg'] ),
	);
}

/**
 * Saved storefront appearance colors merged with defaults.
 *
 * @return array<string, string>
 * @since 1.0.0
 */
function aveniadp_get_appearance_colors() {
	$defaults = aveniadp_get_appearance_defaults();
	$saved    = get_option( 'aveniadp_appearance', array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$colors = array();

	foreach ( $defaults as $key => $default ) {
		$colors[ $key ] = aveniadp_sanitize_hex_color_or_default(
			isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '',
			$default
		);
	}

	return $colors;
}

/**
 * CSS custom properties for storefront appearance.
 *
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_appearance_css() {
	$colors            = aveniadp_get_appearance_colors();
	$button_bg         = $colors['button_bg'];
	$tab_active_bg     = $colors['tab_active_bg'];
	$border_color      = $colors['border_color'];
	$panel_bg          = $colors['panel_bg'];
	$button_bg_hover   = aveniadp_mix_hex_colors( $button_bg, '#000000', 0.12 );
	$tab_active_hover  = aveniadp_mix_hex_colors( $tab_active_bg, '#000000', 0.12 );
	$border_strong     = aveniadp_mix_hex_colors( $border_color, '#000000', 0.35 );
	$button_text       = aveniadp_get_contrast_color( $button_bg );
	$tab_active_text   = aveniadp_get_contrast_color( $tab_active_bg );

	$properties = array(
		'--aveniadp-color-button-bg'          => $button_bg,
		'--aveniadp-color-button-bg-hover'    => $button_bg_hover,
		'--aveniadp-color-button-text'        => $button_text,
		'--aveniadp-color-tab-active-bg'      => $tab_active_bg,
		'--aveniadp-color-tab-active-bg-hover'=> $tab_active_hover,
		'--aveniadp-color-tab-active-text'    => $tab_active_text,
		'--aveniadp-color-tab-active-border'  => $tab_active_bg,
		'--aveniadp-color-border'             => $border_color,
		'--aveniadp-color-border-strong'      => $border_strong,
		'--aveniadp-color-panel-bg'           => $panel_bg,
	);

	$rules = array();

	foreach ( $properties as $property => $value ) {
		$rules[] = $property . ':' . $value;
	}

	return ':root{' . implode( ';', $rules ) . ';}';
}
