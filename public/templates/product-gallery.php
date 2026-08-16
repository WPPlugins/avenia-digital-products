<?php
/**
 * Single product image gallery.
 *
 * Override: your-theme/avenia-digital-products/product-gallery.php
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 *
 * @var int   $product_id Product post ID.
 * @var int[] $image_ids  Attachment IDs (featured image first).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$product_id = isset( $product_id ) ? absint( $product_id ) : get_the_ID();
$image_ids  = isset( $image_ids ) && is_array( $image_ids ) ? array_map( 'absint', $image_ids ) : array();
$image_ids  = array_values( array_filter( $image_ids ) );

if ( empty( $image_ids ) ) {
	return;
}

$primary_id      = $image_ids[0];
$lightbox_images = array();

foreach ( $image_ids as $attachment_id ) {
	$full_src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $full_src ) {
		continue;
	}

	$lightbox_images[] = array(
		'id'  => $attachment_id,
		'src' => $full_src[0],
		'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}
?>

<div
	class="aveniadp-product-gallery"
	data-product-id="<?php echo esc_attr( $product_id ); ?>"
	data-aveniadp-lightbox-images="<?php echo esc_attr( wp_json_encode( $lightbox_images ) ); ?>"
>
	<div class="aveniadp-product-gallery-main">
		<div class="aveniadp-product-image-wrap">
			<button
				type="button"
				class="aveniadp-product-gallery-lightbox-trigger"
				aria-label="<?php esc_attr_e( 'View fullscreen', 'avenia-digital-products' ); ?>"
			>
				<div class="aveniadp-product-image">
					<?php
					echo wp_get_attachment_image(
						$primary_id,
						'aveniadp-product-single',
						false,
						array(
							'class'         => 'aveniadp-product-gallery-main-image',
							'loading'       => 'eager',
							'data-image-id' => $primary_id,
						)
					);
					?>
				</div>
			</button>
		</div>
	</div>

	<?php if ( count( $image_ids ) > 1 ) : ?>
		<ul class="aveniadp-product-gallery-thumbs" role="list">
			<?php foreach ( $image_ids as $index => $attachment_id ) : ?>
				<?php
				$full_src    = wp_get_attachment_image_src( $attachment_id, 'aveniadp-product-single' );
				$full_srcset = wp_get_attachment_image_srcset( $attachment_id, 'aveniadp-product-single' );
				$full_sizes  = wp_get_attachment_image_sizes( $attachment_id, 'aveniadp-product-single' );
				if ( ! $full_src ) {
					continue;
				}
				?>
				<li>
					<button
						type="button"
						class="aveniadp-product-gallery-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
						data-image-id="<?php echo esc_attr( $attachment_id ); ?>"
						data-src="<?php echo esc_url( $full_src[0] ); ?>"
						<?php if ( $full_srcset ) : ?>
							data-srcset="<?php echo esc_attr( $full_srcset ); ?>"
						<?php endif; ?>
						<?php if ( $full_sizes ) : ?>
							data-sizes="<?php echo esc_attr( $full_sizes ); ?>"
						<?php endif; ?>
						aria-label="<?php
						/* translators: %d: image number in the product gallery */
						echo esc_attr( sprintf( __( 'Show image %d', 'avenia-digital-products' ), $index + 1 ) );
						?>"
						aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					>
						<?php
						echo wp_get_attachment_image(
							$attachment_id,
							'aveniadp-product-card',
							false,
							array(
								'class' => 'aveniadp-product-gallery-thumb-image',
							)
						);
						?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
