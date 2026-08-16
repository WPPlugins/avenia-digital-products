<?php
/**
 * Template loading helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function aveniadp_get_main_classes( $extra = '' ) {
	$classes = array(
		'site-main',
		'wp-block-group',
		'has-global-padding',
		'is-layout-constrained',
		'wp-block-group-is-layout-constrained',
	);

	if ( $extra ) {
		$classes[] = $extra;
	}

	/**
	 * Filter main content wrapper classes on classic theme shell templates.
	 *
	 * @param string[] $classes Main element class names.
	 */
	$classes = apply_filters( 'aveniadp_main_content_classes', array_filter( $classes ) );

	return implode( ' ', $classes );
}

/**
 * Output opening content wrappers before plugin shell templates.
 *
 * Themes can replace this markup to match page.php:
 *
 * remove_action( 'aveniadp_before_main_content', 'aveniadp_output_content_wrapper', 10 );
 * add_action( 'aveniadp_before_main_content', 'my_theme_wrapper_start', 10 );
 *
 * @since 1.0.0
 */
function aveniadp_output_content_wrapper() {
	echo '<div id="primary" class="content-area">';
	echo '<main id="main" class="' . esc_attr( aveniadp_get_main_classes() ) . '">';
}

/**
 * Output closing content wrappers after plugin shell templates.
 *
 * @since 1.0.0
 */
function aveniadp_output_content_wrapper_end() {
	echo '</main></div>';
}

add_action( 'aveniadp_before_main_content', 'aveniadp_output_content_wrapper', 10 );
add_action( 'aveniadp_after_main_content', 'aveniadp_output_content_wrapper_end', 10 );

/**
 * Locate a template file, preferring the active theme override.
 *
 * Copy templates to your-theme/avenia-digital-products/{template-name}.php to override.
 *
 * @param string $template_name Template file name.
 * @param string $template_path Theme subdirectory. Default avenia-digital-products.
 * @param string $default_path  Plugin templates directory. Default public/templates/.
 * @return string
 * @since 1.0.0
 */
function aveniadp_locate_template( $template_name, $template_path = 'avenia-digital-products', $default_path = '' ) {
	if ( ! $default_path ) {
		$default_path = AVENIADP_PLUGIN_DIR . 'public/templates/';
	} else {
		$default_path = trailingslashit( $default_path );
	}

	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		)
	);

	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	/**
	 * Filter the located template path.
	 *
	 * @param string $template      Located template path.
	 * @param string $template_name Template file name.
	 * @param string $template_path Theme subdirectory.
	 * @param string $default_path  Plugin templates directory.
	 */
	return apply_filters( 'aveniadp_locate_template', $template, $template_name, $template_path, $default_path );
}

/**
 * Load a template file with optional arguments.
 *
 * @param string $template_name Template file name.
 * @param array  $args          Arguments available in the template.
 * @param string $template_path Theme subdirectory.
 * @param string $default_path  Plugin templates directory.
 * @since 1.0.0
 */
function aveniadp_get_template( $template_name, $args = array(), $template_path = 'avenia-digital-products', $default_path = '' ) {
	$located = aveniadp_locate_template( $template_name, $template_path, $default_path );

	if ( ! $located || ! is_readable( $located ) ) {
		return;
	}

	/**
	 * Filter template arguments before loading.
	 *
	 * @param array  $args          Template arguments.
	 * @param string $template_name Template file name.
	 * @param string $template_path Theme subdirectory.
	 * @param string $default_path  Plugin templates directory.
	 */
	$args = apply_filters( 'aveniadp_get_template_args', $args, $template_name, $template_path, $default_path );

	if ( ! empty( $args ) && is_array( $args ) ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template vars extracted into local scope.
		extract( $args, EXTR_SKIP );
	}

	/**
	 * Fires before a template is loaded.
	 *
	 * @param string $template_name Template file name.
	 * @param string $template_path Theme subdirectory.
	 * @param string $located       Located template path.
	 * @param array  $args          Template arguments.
	 */
	do_action( 'aveniadp_before_get_template', $template_name, $template_path, $located, $args );

	include $located;

	/**
	 * Fires after a template is loaded.
	 *
	 * @param string $template_name Template file name.
	 * @param string $template_path Theme subdirectory.
	 * @param string $located       Located template path.
	 * @param array  $args          Template arguments.
	 */
	do_action( 'aveniadp_after_get_template', $template_name, $template_path, $located, $args );
}

/**
 * Load a template and return its HTML.
 *
 * @param string $template_name Template file name.
 * @param array  $args          Arguments available in the template.
 * @param string $template_path Theme subdirectory.
 * @param string $default_path  Plugin templates directory.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_template_html( $template_name, $args = array(), $template_path = 'avenia-digital-products', $default_path = '' ) {
	ob_start();
	aveniadp_get_template( $template_name, $args, $template_path, $default_path );
	return ob_get_clean();
}
