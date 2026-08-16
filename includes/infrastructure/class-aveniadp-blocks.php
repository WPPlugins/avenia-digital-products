<?php
/**
 * Block registration.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks class.
 *
 * @since 1.0.0
 */
class AVENIADP_Blocks {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize blocks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		$this->init();
	}

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_catalog_preview_styles' ) );
	}

	/**
	 * Register a block inserter category for plugin blocks.
	 *
	 * @param array[]                 $categories      Block categories.
	 * @param WP_Block_Editor_Context $editor_context  Editor context.
	 * @return array[]
	 * @since 1.0.0
	 */
	public function register_block_category( $categories, $editor_context ) {
		unset( $editor_context );

		$slug = 'avenia-digital-products';

		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && $slug === $category['slug'] ) {
				return $categories;
			}
		}

		return array_merge(
			array(
				array(
					'slug'  => $slug,
					'title' => __( 'Avenia Digital Products', 'avenia-digital-products' ),
					'icon'  => null,
				),
			),
			$categories
		);
	}

	/**
	 * Register blocks.
	 *
	 * @since 1.0.0
	 */
	public function register_blocks() {
		$this->register_block_type(
			'products',
			'aveniadp_render_products_block',
			/* translators: %s: asset file path */
			__( 'The Products block editor script is missing its asset file (%s). Rebuild blocks/products with npm run build.', 'avenia-digital-products' )
		);

		$this->register_block_type(
			'cart-icon',
			'aveniadp_render_cart_icon_block',
			/* translators: %s: asset file path */
			__( 'The Cart Icon block editor script is missing its asset file (%s). Rebuild blocks/cart-icon with npm run build.', 'avenia-digital-products' )
		);
	}

	/**
	 * Register a dynamic block from a blocks/{slug} directory.
	 *
	 * @param string $slug             Block directory name under blocks/.
	 * @param string $render_callback    PHP render callback function name.
	 * @param string $missing_asset_msg  _doing_it_wrong message when index.asset.php is missing.
	 * @since 1.0.0
	 */
	private function register_block_type( $slug, $render_callback, $missing_asset_msg ) {
		if ( ! function_exists( 'register_block_type' ) || ! function_exists( $render_callback ) ) {
			return;
		}

		$block_dir = AVENIADP_PLUGIN_DIR . 'blocks/' . $slug;

		if ( ! is_readable( $block_dir . '/block.json' ) ) {
			return;
		}

		$editor_script = $block_dir . '/build/index.js';
		$asset_file    = $block_dir . '/build/index.asset.php';

		if ( ! is_readable( $editor_script ) ) {
			return;
		}

		if ( ! is_readable( $asset_file ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: asset file path */
					esc_html( $missing_asset_msg ),
					esc_html( $asset_file )
				),
				'1.0.0'
			);
			return;
		}

		register_block_type(
			$block_dir,
			array(
				'render_callback' => $render_callback,
			)
		);
	}

	/**
	 * Load catalog styles in the block editor so block previews match the frontend.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_catalog_preview_styles() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'aveniadp-public',
			AVENIADP_PLUGIN_URL . 'public/assets/css/public.css',
			array(),
			AVENIADP_VERSION
		);

		if ( function_exists( 'aveniadp_get_appearance_css' ) ) {
			wp_add_inline_style( 'aveniadp-public', aveniadp_get_appearance_css() );
		}
	}
}
