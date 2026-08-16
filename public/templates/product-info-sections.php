<?php
/**
 * Product info accordion (Markdown sections).
 *
 * Override: your-theme/avenia-digital-products/product-info-sections.php
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 *
 * @var int   $product_id
 * @var array $sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
if ( empty( $sections ) || ! is_array( $sections ) ) {
	return;
}
?>

<section class="aveniadp-product-info-sections" aria-label="<?php esc_attr_e( 'Additional information', 'avenia-digital-products' ); ?>">
	<div class="aveniadp-product-accordion">
		<?php foreach ( $sections as $index => $section ) : ?>
			<?php
			$panel_id  = 'aveniadp-accordion-panel-' . $product_id . '-' . $index;
			$trigger_id = 'aveniadp-accordion-trigger-' . $product_id . '-' . $index;
			$content_html = function_exists( 'aveniadp_parse_product_markdown' ) ? aveniadp_parse_product_markdown( $section['content'] ) : '';
			?>
			<div class="aveniadp-accordion-item">
				<h3 class="aveniadp-accordion-item-heading">
					<button
						type="button"
						class="aveniadp-accordion-trigger"
						id="<?php echo esc_attr( $trigger_id ); ?>"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $panel_id ); ?>"
					>
						<span class="aveniadp-accordion-trigger-inner">
							<span class="aveniadp-accordion-title"><?php echo esc_html( $section['title'] ); ?></span>
							<span class="aveniadp-accordion-icon" aria-hidden="true">+</span>
						</span>
					</button>
				</h3>
				<div
					class="aveniadp-accordion-panel"
					id="<?php echo esc_attr( $panel_id ); ?>"
					role="region"
					aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
					aria-hidden="true"
				>
					<div class="aveniadp-accordion-panel-inner">
						<?php if ( $content_html ) : ?>
							<div class="aveniadp-accordion-content">
								<?php echo wp_kses_post( $content_html ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
