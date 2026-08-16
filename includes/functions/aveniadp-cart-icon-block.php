<?php
/**
 * Cart icon block render helpers.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed cart icon style slugs.
 *
 * @return string[]
 * @since 1.0.0
 */
function aveniadp_get_cart_icon_styles() {
	return array( 'bag', 'cart', 'basket' );
}

/**
 * Inline SVG markup for a cart icon preset.
 *
 * @param string $style Icon style slug.
 * @return string
 * @since 1.0.0
 */
function aveniadp_get_cart_icon_svg( $style ) {
	$icons = array(
		'bag'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19.26 9.696l1.385 9A2 2 0 0 1 18.67 21H5.33a2 2 0 0 1-1.977-2.304l1.385-9A2 2 0 0 1 6.716 8h10.568a2 2 0 0 1 1.977 1.696zM14 5a2 2 0 1 0-4 0"/></svg>',
		'cart'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M2 4h2l2.5 12h11l2-8H6"/></svg>',
		'basket' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 10h16l-1.5 9H5.5L4 10Z"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
	);

	$style = in_array( $style, aveniadp_get_cart_icon_styles(), true ) ? $style : 'bag';

	return $icons[ $style ];
}

/**
 * Parse cart icon block attributes.
 *
 * @param array $attributes Block attributes.
 * @return array<string, mixed>
 * @since 1.0.0
 */
function aveniadp_parse_cart_icon_block_attributes( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$icon_style = isset( $attributes['iconStyle'] ) ? sanitize_key( $attributes['iconStyle'] ) : 'bag';
	if ( ! in_array( $icon_style, aveniadp_get_cart_icon_styles(), true ) ) {
		$icon_style = 'bag';
	}

	$label = isset( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '';
	if ( '' === $label ) {
		$label = __( 'Cart', 'avenia-digital-products' );
	}

	return array(
		'iconStyle'          => $icon_style,
		'iconSize'           => max( 16, min( 48, isset( $attributes['iconSize'] ) ? absint( $attributes['iconSize'] ) : 24 ) ),
		'showCount'          => ! array_key_exists( 'showCount', $attributes ) || ! empty( $attributes['showCount'] ),
		'hideCountWhenEmpty' => ! empty( $attributes['hideCountWhenEmpty'] ),
		'label'              => $label,
	);
}

/**
 * Server-side render callback for the Cart Icon block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string
 * @since 1.0.0
 */
function aveniadp_render_cart_icon_block( $attributes, $content, $block ) {
	unset( $content, $block );

	$atts = aveniadp_parse_cart_icon_block_attributes( $attributes );
	// Count starts at zero; updated by aveniadp_bootstrap for the current visitor.
	$count  = 0;
	$svg    = aveniadp_get_cart_icon_svg( $atts['iconStyle'] );
	$hidden = $atts['showCount'] && $atts['hideCountWhenEmpty'];

	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class' => 'aveniadp-cart-icon-block',
			'style' => '--aveniadp-cart-icon-size: ' . $atts['iconSize'] . 'px;',
			'data-aveniadp-hide-count-when-empty' => $atts['hideCountWhenEmpty'] ? '1' : '0',
			'data-aveniadp-cart-icon-shell'       => '1',
		)
	);

	$allowed_svg = function_exists( 'aveniadp_get_allowed_svg_html' ) ? aveniadp_get_allowed_svg_html() : array();

	ob_start();
	?>
	<button type="button" class="aveniadp-cart-icon" data-aveniadp-open-cart-drawer aria-label="<?php echo esc_attr( $atts['label'] ); ?>">
		<span class="aveniadp-cart-icon-graphic">
			<?php echo wp_kses( $svg, $allowed_svg ); ?>
		</span>
		<?php if ( $atts['showCount'] ) : ?>
			<span class="aveniadp-cart-icon-count" data-aveniadp-cart-icon-count <?php echo $hidden ? 'hidden' : ''; ?>>
				<?php echo esc_html( (string) $count ); ?>
			</span>
		<?php endif; ?>
	</button>
	<?php
	$button_html = ob_get_clean();

	return sprintf(
		'<div %1$s>%2$s</div>',
		$wrapper_attributes,
		$button_html
	);
}
