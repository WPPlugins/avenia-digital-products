<?php
/**
 * Product management.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products class.
 *
 * @since 1.0.0
 */
class AVENIADP_Products {

	/**
	 * Initialize products.
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
		// Register Custom Post Type.
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 10 );

		// Register taxonomies.
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 10 );

		// Product featured image sizes.
		add_action( 'init', array( $this, 'register_image_sizes' ), 10 );
		add_action( 'init', array( $this, 'register_product_meta' ), 10 );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'product_featured_image_admin_hint' ), 10, 2 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Keep product meta sections enabled and expanded in the block editor; not hidden by default.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_product_block_editor_assets' ) );
		add_filter( 'default_hidden_meta_boxes', array( $this, 'ensure_product_meta_boxes_not_hidden_by_default' ), 10, 2 );

		// Save meta boxes.
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'save_post_' . AVENIADP_PRODUCT_POST_TYPE, array( $this, 'maybe_normalize_product_gallery_meta' ), 20 );
		add_action( 'rest_after_insert_' . AVENIADP_PRODUCT_POST_TYPE, array( $this, 'after_rest_insert_product' ), 10, 3 );
	}

	/**
	 * Register Custom Post Type.
	 *
	 * @since 1.0.0
	 */
	public static function register_post_type() {
		$product_base = aveniadp_get_product_base_slug();

		$labels = array(
			'name'                  => _x( 'Products', 'Post Type General Name', 'avenia-digital-products' ),
			'singular_name'         => _x( 'Product', 'Post Type Singular Name', 'avenia-digital-products' ),
			'menu_name'             => __( 'Products', 'avenia-digital-products' ),
			'name_admin_bar'        => __( 'Product', 'avenia-digital-products' ),
			'archives'              => __( 'Product Archives', 'avenia-digital-products' ),
			'attributes'            => __( 'Product Attributes', 'avenia-digital-products' ),
			'parent_item_colon'     => __( 'Parent Product:', 'avenia-digital-products' ),
			'all_items'             => __( 'All Products', 'avenia-digital-products' ),
			'add_new_item'          => __( 'Add New Product', 'avenia-digital-products' ),
			'add_new'               => __( 'Add New', 'avenia-digital-products' ),
			'new_item'              => __( 'New Product', 'avenia-digital-products' ),
			'edit_item'             => __( 'Edit Product', 'avenia-digital-products' ),
			'update_item'           => __( 'Update Product', 'avenia-digital-products' ),
			'view_item'             => __( 'View Product', 'avenia-digital-products' ),
			'view_items'            => __( 'View Products', 'avenia-digital-products' ),
			'search_items'          => __( 'Search Product', 'avenia-digital-products' ),
			'not_found'             => __( 'Not found', 'avenia-digital-products' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'avenia-digital-products' ),
			'featured_image'        => __( 'Featured Image', 'avenia-digital-products' ),
			'set_featured_image'    => __( 'Set featured image', 'avenia-digital-products' ),
			'remove_featured_image' => __( 'Remove featured image', 'avenia-digital-products' ),
			'use_featured_image'    => __( 'Use as featured image', 'avenia-digital-products' ),
			'insert_into_item'      => __( 'Insert into product', 'avenia-digital-products' ),
			'uploaded_to_this_item' => __( 'Uploaded to this product', 'avenia-digital-products' ),
			'items_list'            => __( 'Products list', 'avenia-digital-products' ),
			'items_list_navigation' => __( 'Products list navigation', 'avenia-digital-products' ),
			'filter_items_list'     => __( 'Filter products list', 'avenia-digital-products' ),
		);

		$args = array(
			'label'                 => __( 'Product', 'avenia-digital-products' ),
			'description'           => __( 'Digital products for sale', 'avenia-digital-products' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'            => array( 'aveniadp_product_category', 'aveniadp_product_tag' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'avenia-digital-products',
			'menu_position'         => 5,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rewrite'               => array(
				'slug'       => $product_base,
				'with_front' => false,
			),
		);

		register_post_type( AVENIADP_PRODUCT_POST_TYPE, $args );
	}

	/**
	 * Register cropped image sizes for product featured images.
	 *
	 * @since 1.0.0
	 */
	public function register_image_sizes() {
		add_image_size( 'aveniadp-product-card', 600, 600, true );
		add_image_size( 'aveniadp-product-single', 1200, 1200, true );
		add_image_size( 'aveniadp-product-pinned', 1920, 853, true );
		// Plugin update icons (wordpress.org convention: 128 / 256).
		add_image_size( 'aveniadp-plugin-icon', 128, 128, true );
		add_image_size( 'aveniadp-plugin-icon-2x', 256, 256, true );
	}

	/**
	 * Register product post meta for the block editor REST API.
	 *
	 * @since 1.0.0
	 */
	public function register_product_meta() {
		register_post_meta(
			AVENIADP_PRODUCT_POST_TYPE,
			'_aveniadp_product_gallery',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'sanitize_callback' => 'aveniadp_sanitize_product_gallery_meta',
				'auth_callback'     => function ( $allowed, $meta_key, $object_id ) {
					unset( $allowed, $meta_key );

					if ( ! $object_id ) {
						return current_user_can( 'edit_posts' );
					}

					return current_user_can( 'edit_post', $object_id );
				},
			)
		);

		register_post_meta(
			AVENIADP_PRODUCT_POST_TYPE,
			'_aveniadp_related_products',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'sanitize_callback' => 'aveniadp_sanitize_related_products_meta',
				'auth_callback'     => function ( $allowed, $meta_key, $object_id ) {
					unset( $allowed, $meta_key );

					if ( ! $object_id ) {
						return current_user_can( 'edit_posts' );
					}

					return current_user_can( 'edit_post', $object_id );
				},
			)
		);

		register_post_meta(
			AVENIADP_PRODUCT_POST_TYPE,
			'_aveniadp_pinned_product',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '0',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '0',
					),
				),
				'sanitize_callback' => 'aveniadp_sanitize_pinned_product_meta',
				'auth_callback'     => function ( $allowed, $meta_key, $object_id ) {
					unset( $allowed, $meta_key );

					if ( ! $object_id ) {
						return current_user_can( 'edit_posts' );
					}

					return current_user_can( 'edit_post', $object_id );
				},
			)
		);

		$demo_auth = function ( $allowed, $meta_key, $object_id ) {
			unset( $allowed, $meta_key );

			if ( ! $object_id ) {
				return current_user_can( 'edit_posts' );
			}

			return current_user_can( 'edit_post', $object_id );
		};

		register_post_meta(
			AVENIADP_PRODUCT_POST_TYPE,
			'_aveniadp_demo_button_text',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $demo_auth,
			)
		);

		register_post_meta(
			AVENIADP_PRODUCT_POST_TYPE,
			'_aveniadp_demo_button_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'sanitize_callback' => 'aveniadp_sanitize_product_external_file_url',
				'auth_callback'     => $demo_auth,
			)
		);
	}

	/**
	 * Whether products use the block editor.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function uses_block_editor_for_products() {
		if ( ! function_exists( 'use_block_editor_for_post_type' ) ) {
			return false;
		}

		return (bool) use_block_editor_for_post_type( AVENIADP_PRODUCT_POST_TYPE );
	}

	/**
	 * Show recommended featured image dimensions in the product editor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content      Featured image meta box HTML.
	 * @param int    $post_id        Post ID.
	 * @return string
	 */
	public function product_featured_image_admin_hint( $content, $post_id ) {
		if ( AVENIADP_PRODUCT_POST_TYPE !== get_post_type( $post_id ) ) {
			return $content;
		}

		$content .= '<p class="description">' . esc_html__(
			'Recommended: square image, at least 1200×1200 px. Images are automatically cropped to square for catalog and product pages.',
			'avenia-digital-products'
		) . '</p>';

		return $content;
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 */
	public static function register_taxonomies() {
		$product_base = aveniadp_get_product_base_slug();

		// Product Categories.
		$category_labels = array(
			'name'              => _x( 'Product Categories', 'taxonomy general name', 'avenia-digital-products' ),
			'singular_name'     => _x( 'Product Category', 'taxonomy singular name', 'avenia-digital-products' ),
			'search_items'      => __( 'Search Categories', 'avenia-digital-products' ),
			'all_items'         => __( 'All Categories', 'avenia-digital-products' ),
			'parent_item'       => __( 'Parent Category', 'avenia-digital-products' ),
			'parent_item_colon' => __( 'Parent Category:', 'avenia-digital-products' ),
			'edit_item'         => __( 'Edit Category', 'avenia-digital-products' ),
			'update_item'       => __( 'Update Category', 'avenia-digital-products' ),
			'add_new_item'      => __( 'Add New Category', 'avenia-digital-products' ),
			'new_item_name'     => __( 'New Category Name', 'avenia-digital-products' ),
			'menu_name'         => __( 'Categories', 'avenia-digital-products' ),
		);

		register_taxonomy(
			'aveniadp_product_category',
			array( AVENIADP_PRODUCT_POST_TYPE ),
			array(
				'hierarchical'      => true,
				'labels'            => $category_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug' => $product_base . '/category',
				),
			)
		);

		// Product Tags.
		$tag_labels = array(
			'name'                       => _x( 'Product Tags', 'taxonomy general name', 'avenia-digital-products' ),
			'singular_name'              => _x( 'Product Tag', 'taxonomy singular name', 'avenia-digital-products' ),
			'search_items'               => __( 'Search Tags', 'avenia-digital-products' ),
			'popular_items'              => __( 'Popular Tags', 'avenia-digital-products' ),
			'all_items'                  => __( 'All Tags', 'avenia-digital-products' ),
			'edit_item'                  => __( 'Edit Tag', 'avenia-digital-products' ),
			'update_item'                => __( 'Update Tag', 'avenia-digital-products' ),
			'add_new_item'               => __( 'Add New Tag', 'avenia-digital-products' ),
			'new_item_name'              => __( 'New Tag Name', 'avenia-digital-products' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'avenia-digital-products' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'avenia-digital-products' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'avenia-digital-products' ),
			'not_found'                  => __( 'No tags found', 'avenia-digital-products' ),
			'menu_name'                  => __( 'Tags', 'avenia-digital-products' ),
		);

		register_taxonomy(
			'aveniadp_product_tag',
			array( AVENIADP_PRODUCT_POST_TYPE ),
			array(
				'hierarchical'      => false,
				'labels'            => $tag_labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug' => $product_base . '/tag',
				),
			)
		);
	}

	/**
	 * Add meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'aveniadp_product_details',
			__( 'Product Details', 'avenia-digital-products' ),
			array( $this, 'product_details_meta_box' ),
			AVENIADP_PRODUCT_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'aveniadp_product_pricing',
			__( 'Pricing', 'avenia-digital-products' ),
			array( $this, 'product_pricing_meta_box' ),
			AVENIADP_PRODUCT_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'aveniadp_product_info_sections',
			__( 'Additional Info', 'avenia-digital-products' ),
			array( $this, 'product_info_sections_meta_box' ),
			AVENIADP_PRODUCT_POST_TYPE,
			'normal',
			'high'
		);

		if ( ! $this->uses_block_editor_for_products() ) {
			add_meta_box(
				'aveniadp_product_gallery',
				__( 'Product Gallery', 'avenia-digital-products' ),
				array( $this, 'product_gallery_meta_box' ),
				AVENIADP_PRODUCT_POST_TYPE,
				'side',
				'default'
			);

			add_meta_box(
				'aveniadp_product_demo',
				__( 'Demo link', 'avenia-digital-products' ),
				array( $this, 'product_demo_meta_box' ),
				AVENIADP_PRODUCT_POST_TYPE,
				'side',
				'default'
			);

			add_meta_box(
				'aveniadp_product_related',
				__( 'Related Products', 'avenia-digital-products' ),
				array( $this, 'product_related_products_meta_box' ),
				AVENIADP_PRODUCT_POST_TYPE,
				'side',
				'default'
			);

			add_meta_box(
				'aveniadp_product_catalog',
				__( 'Catalog Display', 'avenia-digital-products' ),
				array( $this, 'product_catalog_meta_box' ),
				AVENIADP_PRODUCT_POST_TYPE,
				'side',
				'default'
			);
		}
	}

	/**
	 * Product details meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_details_meta_box( $post ) {
		wp_nonce_field( 'aveniadp_save_product_meta', 'aveniadp_product_meta_nonce' );

		$file_id = get_post_meta( $post->ID, '_aveniadp_file_id', true );
		$file_source = get_post_meta( $post->ID, '_aveniadp_file_source', true );
		$file_url = get_post_meta( $post->ID, '_aveniadp_file_url', true );
		$free_download = get_post_meta( $post->ID, '_aveniadp_free_download', true );

		if ( ! in_array( $file_source, array( 'upload', 'external' ), true ) ) {
			$file_source = 'upload';
		}

		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Download Source', 'avenia-digital-products' ); ?></th>
				<td>
					<fieldset class="aveniadp-file-source-options">
						<label>
							<input type="radio" name="aveniadp_file_source" value="upload" <?php checked( $file_source, 'upload' ); ?> />
							<?php esc_html_e( 'Uploaded file', 'avenia-digital-products' ); ?>
						</label>
						<br />
						<label>
							<input type="radio" name="aveniadp_file_source" value="external" <?php checked( $file_source, 'external' ); ?> />
							<?php esc_html_e( 'External URL', 'avenia-digital-products' ); ?>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Choose whether customers receive a file stored securely by this plugin or are redirected to an external link.', 'avenia-digital-products' ); ?></p>
				</td>
			</tr>
			<tr class="aveniadp-file-source-upload">
				<th><label for="aveniadp_file_id"><?php esc_html_e( 'Download File', 'avenia-digital-products' ); ?></label></th>
				<td>
					<input type="hidden" id="aveniadp_file_id" name="aveniadp_file_id" value="<?php echo esc_attr( $file_id ); ?>" />
					<button type="button" class="button aveniadp-upload-file-button"><?php esc_html_e( 'Select File', 'avenia-digital-products' ); ?></button>
					<button type="button" class="button aveniadp-remove-file-button" style="<?php echo empty( $file_id ) ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Remove File', 'avenia-digital-products' ); ?></button>
					<p class="description"><?php esc_html_e( 'Select the file customers will download after purchase. Files are stored in a protected folder and can only be accessed through secure download links.', 'avenia-digital-products' ); ?></p>
					<div id="aveniadp-file-preview" style="margin-top:10px;">
						<?php
						if ( ! empty( $file_id ) ) {
							$uploaded_file_url = wp_get_attachment_url( $file_id );
							$file_name = $uploaded_file_url ? basename( $uploaded_file_url ) : '';
							echo '<p><strong>' . esc_html( $file_name ) . '</strong></p>';
						}
						?>
					</div>
				</td>
			</tr>
			<tr class="aveniadp-file-source-external">
				<th><label for="aveniadp_file_url"><?php esc_html_e( 'External Download URL', 'avenia-digital-products' ); ?></label></th>
				<td>
					<input
						type="url"
						id="aveniadp_file_url"
						name="aveniadp_file_url"
						class="large-text code"
						value="<?php echo esc_attr( $file_url ); ?>"
						placeholder="https://"
					/>
					<p class="description"><?php esc_html_e( 'Customers still use your secure download link, then are redirected to this URL. HTTPS is recommended.', 'avenia-digital-products' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="aveniadp_free_download"><?php esc_html_e( 'Free download (catalog)', 'avenia-digital-products' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="aveniadp_free_download" name="aveniadp_free_download" value="1" <?php checked( $free_download, '1' ); ?> />
						<?php esc_html_e( 'Offer this file for free without checkout or cart.', 'avenia-digital-products' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Visitors get a secure download link immediately. Requires an uploaded file or external URL above. The paid cart is bypassed on the product page.', 'avenia-digital-products' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Catalog display meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_catalog_meta_box( $post ) {
		$pinned = function_exists( 'aveniadp_is_product_pinned' ) && aveniadp_is_product_pinned( $post->ID );
		?>
		<div class="aveniadp-product-catalog-admin">
			<p class="aveniadp-product-catalog-panel__help description">
				<?php esc_html_e( 'Shows this product full width at the top of the shop and catalog with a 9:4 image. Only one product can be pinned at a time.', 'avenia-digital-products' ); ?>
			</p>
			<div class="aveniadp-catalog-display-list" role="group" aria-label="<?php esc_attr_e( 'Catalog display', 'avenia-digital-products' ); ?>">
				<label class="aveniadp-catalog-display-item">
					<input type="checkbox" name="aveniadp_pinned_product" class="aveniadp-catalog-display-checkbox" value="1" <?php checked( $pinned ); ?> />
					<span class="aveniadp-catalog-display-title"><?php esc_html_e( 'Pin to catalog', 'avenia-digital-products' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Product pricing meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_pricing_meta_box( $post ) {
		$pricing_matrix = get_post_meta( $post->ID, '_aveniadp_pricing_matrix', true );

		if ( empty( $pricing_matrix ) || ! is_array( $pricing_matrix ) ) {
			$pricing_matrix = array(
				'1' => array(
					'one-time' => 0,
				),
			);
		}

		?>
		<div id="aveniadp-pricing-matrix-container">
			<p class="description"><?php esc_html_e( 'Configure price tiers by sites. In Sites, use 0 for unlimited. In price fields, 0 means free.', 'avenia-digital-products' ); ?></p>
			<div id="aveniadp-pricing-matrix-builder">
				<table class="form-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sites', 'avenia-digital-products' ); ?></th>
							<th><?php esc_html_e( 'Price', 'avenia-digital-products' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
						</tr>
					</thead>
					<tbody id="aveniadp-pricing-rows">
						<?php foreach ( $pricing_matrix as $quantity => $prices ) : ?>
							<tr class="aveniadp-pricing-row">
								<td>
									<input type="number" name="pricing_quantity[]" value="<?php echo esc_attr( $quantity ); ?>" min="0" class="small-text" />
								</td>
								<td>
									<input type="number" name="pricing_one_time[]" value="<?php echo esc_attr( isset( $prices['one-time'] ) ? $prices['one-time'] : 0 ); ?>" min="0" step="0.01" class="small-text" />
								</td>
								<td>
									<button type="button" class="button aveniadp-remove-pricing-row"><?php esc_html_e( 'Remove', 'avenia-digital-products' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<button type="button" class="button aveniadp-add-pricing-row"><?php esc_html_e( 'Add Pricing Option', 'avenia-digital-products' ); ?></button>
			</div>
			<input type="hidden" id="aveniadp_pricing_matrix" name="aveniadp_pricing_matrix" value="<?php echo esc_attr( wp_json_encode( $pricing_matrix ) ); ?>" />
		</div>
		<?php
		$this->render_direct_checkout_link_generator( $post, $pricing_matrix );
	}

	/**
	 * Direct checkout link generator in the pricing meta box.
	 *
	 * @param WP_Post $post           Product post.
	 * @param array   $pricing_matrix Pricing matrix.
	 * @since 1.0.0
	 */
	private function render_direct_checkout_link_generator( $post, $pricing_matrix ) {
		$free_download = get_post_meta( $post->ID, '_aveniadp_free_download', true );
		$checkout_url  = function_exists( 'aveniadp_get_configured_checkout_page_url' ) ? aveniadp_get_configured_checkout_page_url() : '';

		if ( '1' === $free_download ) {
			return;
		}

		if ( ! $checkout_url ) {
			?>
			<div class="aveniadp-direct-checkout-link aveniadp-direct-checkout-link--disabled">
				<h3><?php esc_html_e( 'Direct checkout link', 'avenia-digital-products' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Select a Checkout Page under Avenia Digital Products → Settings → Pages to generate direct checkout links.', 'avenia-digital-products' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( ! function_exists( 'aveniadp_get_product_checkout_url' ) ) {
			return;
		}

		$default_quantity = function_exists( 'aveniadp_sanitize_license_quantity' )
			? aveniadp_sanitize_license_quantity( array_key_first( $pricing_matrix ), 1 )
			: absint( array_key_first( $pricing_matrix ) );
		$direct_link      = aveniadp_get_product_checkout_url(
			$post->ID,
			array(
				'license_quantity' => $default_quantity,
			)
		);

		if ( '' === $direct_link ) {
			?>
			<div class="aveniadp-direct-checkout-link aveniadp-direct-checkout-link--disabled">
				<h3><?php esc_html_e( 'Direct checkout link', 'avenia-digital-products' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Configure at least one paid pricing option before generating a direct checkout link.', 'avenia-digital-products' ); ?></p>
			</div>
			<?php
			return;
		}

		$has_license_options = count( $pricing_matrix ) > 1;
		?>
		<div
			id="aveniadp-direct-checkout-link"
			class="aveniadp-direct-checkout-link"
			data-product-id="<?php echo esc_attr( $post->ID ); ?>"
			data-checkout-url="<?php echo esc_url( $checkout_url ); ?>"
		>
			<h3><?php esc_html_e( 'Direct checkout link', 'avenia-digital-products' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Share this link to add this product to the cart and go straight to checkout. The checkout page URL comes from your plugin settings and updates automatically if you change the checkout page.', 'avenia-digital-products' ); ?>
			</p>

			<?php if ( $has_license_options ) : ?>
				<div class="aveniadp-direct-checkout-link-options">
					<p>
						<label for="aveniadp_direct_link_license"><?php esc_html_e( 'Sites', 'avenia-digital-products' ); ?></label>
						<select id="aveniadp_direct_link_license">
							<?php foreach ( $pricing_matrix as $quantity => $prices ) : ?>
								<option value="<?php echo esc_attr( $quantity ); ?>" <?php selected( (string) $quantity, (string) $default_quantity ); ?>>
									<?php echo esc_html( aveniadp_format_license_quantity_label( $quantity ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
				</div>
			<?php endif; ?>

			<p class="aveniadp-direct-checkout-link-field">
				<label class="screen-reader-text" for="aveniadp_direct_checkout_url"><?php esc_html_e( 'Direct checkout URL', 'avenia-digital-products' ); ?></label>
				<input
					type="url"
					id="aveniadp_direct_checkout_url"
					class="large-text code"
					readonly
					value="<?php echo esc_attr( $direct_link ); ?>"
				/>
				<button type="button" class="button aveniadp-copy-direct-checkout-url">
					<?php esc_html_e( 'Copy link', 'avenia-digital-products' ); ?>
				</button>
				<span class="aveniadp-direct-checkout-link-copied description" hidden aria-live="polite"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Additional product info accordion sections meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_info_sections_meta_box( $post ) {
		$sections = function_exists( 'aveniadp_get_product_info_sections' ) ? aveniadp_get_product_info_sections( $post->ID ) : array();

		if ( empty( $sections ) ) {
			$sections = array(
				array(
					'title'   => '',
					'content' => '',
				),
			);
		}
		?>
		<div class="aveniadp-product-info-sections-admin">
			<p class="description">
				<?php esc_html_e( 'Optional accordion sections shown on the product page (for example returns or refund policies).', 'avenia-digital-products' ); ?>
			</p>
			<p class="description aveniadp-markdown-hint">
				<?php esc_html_e( 'Content supports Markdown: **bold**, *italic*, [link text](https://example.com), and bullet lists starting with -', 'avenia-digital-products' ); ?>
			</p>
			<div id="aveniadp-info-section-rows" class="aveniadp-info-section-rows">
				<?php foreach ( $sections as $section ) : ?>
					<?php $this->render_product_info_section_row( $section ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button aveniadp-add-info-section"><?php esc_html_e( 'Add Section', 'avenia-digital-products' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render one additional info section row in the product editor.
	 *
	 * @param array $section Section data.
	 * @since 1.0.0
	 */
	private function render_product_info_section_row( $section ) {
		$title   = isset( $section['title'] ) ? $section['title'] : '';
		$content = isset( $section['content'] ) ? $section['content'] : '';
		?>
		<div class="aveniadp-info-section-row">
			<div class="aveniadp-info-section-row-toolbar">
				<input
					type="text"
					class="regular-text aveniadp-info-section-title"
					name="aveniadp_info_section_title[]"
					value="<?php echo esc_attr( $title ); ?>"
					placeholder="<?php esc_attr_e( 'Section title', 'avenia-digital-products' ); ?>"
				/>
				<button type="button" class="button-link-delete aveniadp-remove-info-section"><?php esc_html_e( 'Remove', 'avenia-digital-products' ); ?></button>
			</div>
			<label class="aveniadp-info-section-content-label"><?php esc_html_e( 'Content', 'avenia-digital-products' ); ?></label>
			<textarea
				class="large-text aveniadp-info-section-content"
				name="aveniadp_info_section_content[]"
				rows="5"
				placeholder="<?php esc_attr_e( 'Write Markdown content for this section…', 'avenia-digital-products' ); ?>"
			><?php echo esc_textarea( $content ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Product gallery meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_gallery_meta_box( $post ) {
		$gallery_ids = function_exists( 'aveniadp_get_product_gallery_ids' ) ? aveniadp_get_product_gallery_ids( $post->ID ) : array();
		?>
		<div class="aveniadp-product-gallery-admin">
			<p class="description">
				<?php esc_html_e( 'Add images for the product page gallery. The featured image is shown first; these appear as additional images.', 'avenia-digital-products' ); ?>
			</p>
			<input type="hidden" id="aveniadp_product_gallery" name="aveniadp_product_gallery" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>" />
			<ul id="aveniadp-product-gallery-images" class="aveniadp-product-gallery-images">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php
					$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
					if ( ! $thumb_url ) {
						continue;
					}
					?>
					<li class="aveniadp-product-gallery-image" data-attachment-id="<?php echo esc_attr( $attachment_id ); ?>">
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
						<button type="button" class="aveniadp-remove-gallery-image" aria-label="<?php esc_attr_e( 'Remove image', 'avenia-digital-products' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<button type="button" class="button aveniadp-add-gallery-images">
					<?php esc_html_e( 'Add to gallery', 'avenia-digital-products' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Product demo link meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_demo_meta_box( $post ) {
		$demo_text = get_post_meta( $post->ID, '_aveniadp_demo_button_text', true );
		$demo_url  = get_post_meta( $post->ID, '_aveniadp_demo_button_url', true );
		?>
		<div class="aveniadp-product-demo-admin">
			<p class="description">
				<?php esc_html_e( 'Optional demo button on the product page. Leave the URL empty to hide it. Opens in a new tab.', 'avenia-digital-products' ); ?>
			</p>
			<p>
				<label for="aveniadp_demo_button_text"><?php esc_html_e( 'Button text', 'avenia-digital-products' ); ?></label><br />
				<input
					type="text"
					id="aveniadp_demo_button_text"
					name="aveniadp_demo_button_text"
					class="widefat"
					value="<?php echo esc_attr( $demo_text ); ?>"
					placeholder="<?php esc_attr_e( 'View Demo', 'avenia-digital-products' ); ?>"
				/>
			</p>
			<p>
				<label for="aveniadp_demo_button_url"><?php esc_html_e( 'Link URL', 'avenia-digital-products' ); ?></label><br />
				<input
					type="url"
					id="aveniadp_demo_button_url"
					name="aveniadp_demo_button_url"
					class="widefat code"
					value="<?php echo esc_attr( $demo_url ); ?>"
					placeholder="https://"
				/>
			</p>
		</div>
		<?php
	}

	/**
	 * Related products meta box.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function product_related_products_meta_box( $post ) {
		$related_ids = function_exists( 'aveniadp_get_related_product_ids' ) ? aveniadp_get_related_product_ids( $post->ID ) : array();
		$products    = function_exists( 'aveniadp_get_products_for_related_picker' ) ? aveniadp_get_products_for_related_picker( $post->ID ) : array();
		$max         = function_exists( 'aveniadp_get_max_related_products' ) ? aveniadp_get_max_related_products() : 6;
		?>
		<div class="aveniadp-product-related-admin">
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: maximum number of related products */
						__( 'Choose up to %d products to show below this product. Leave empty to use products from the same category.', 'avenia-digital-products' ),
						$max
					)
				);
				?>
			</p>
			<input type="hidden" id="aveniadp_related_products" name="aveniadp_related_products" value="<?php echo esc_attr( implode( ',', $related_ids ) ); ?>" />
			<p class="aveniadp-related-products-search-wrap">
				<label class="screen-reader-text" for="aveniadp-related-products-search"><?php esc_html_e( 'Search products', 'avenia-digital-products' ); ?></label>
				<input
					type="search"
					id="aveniadp-related-products-search"
					class="aveniadp-related-products-search widefat"
					placeholder="<?php esc_attr_e( 'Search products…', 'avenia-digital-products' ); ?>"
					autocomplete="off"
				/>
			</p>
			<div id="aveniadp-related-products-list" class="aveniadp-related-products-list" role="group" aria-label="<?php esc_attr_e( 'Related products', 'avenia-digital-products' ); ?>">
				<?php if ( empty( $products ) ) : ?>
					<p class="aveniadp-related-products-empty description"><?php esc_html_e( 'No other products found.', 'avenia-digital-products' ); ?></p>
				<?php else : ?>
					<?php foreach ( $products as $product ) : ?>
						<?php
						$product_id    = (int) $product->ID;
						$product_title = get_the_title( $product );
						$is_checked    = in_array( $product_id, $related_ids, true );
						?>
						<label
							class="aveniadp-related-product-item"
							data-title="<?php echo esc_attr( strtolower( $product_title ) ); ?>"
						>
							<input
								type="checkbox"
								class="aveniadp-related-product-checkbox"
								value="<?php echo esc_attr( $product_id ); ?>"
								<?php checked( $is_checked ); ?>
							/>
							<span class="aveniadp-related-product-title"><?php echo esc_html( $product_title ); ?></span>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<p class="aveniadp-related-products-count description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: selected count, 2: maximum count */
						__( '%1$d of %2$d selected', 'avenia-digital-products' ),
						count( $related_ids ),
						$max
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Normalize pricing matrix structure and values.
	 *
	 * @param array $matrix Raw pricing matrix.
	 * @return array
	 * @since 1.0.0
	 */
	private function normalize_pricing_matrix( $matrix ) {
		if ( ! is_array( $matrix ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $matrix as $quantity => $prices ) {
			if ( ! is_array( $prices ) ) {
				continue;
			}

			if ( '0' === (string) $quantity ) {
				$key = '0';
			} else {
				$quantity = absint( $quantity );
				if ( $quantity < 1 ) {
					continue;
				}
				$key = (string) $quantity;
			}

			$normalized[ $key ] = array(
				'one-time' => isset( $prices['one-time'] ) ? floatval( $prices['one-time'] ) : 0,
			);
		}

		return $normalized;
	}

	/**
	 * Build pricing matrix from meta box table fields in the current request.
	 *
	 * @return array|null Pricing matrix or null when table fields were not submitted.
	 * @since 1.0.0
	 */
	private function parse_pricing_matrix_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only called from save_meta_boxes() after nonce verification.
		if ( ! isset( $_POST['pricing_quantity'] ) || ! is_array( $_POST['pricing_quantity'] ) ) {
			return null;
		}

		$quantities = wp_unslash( $_POST['pricing_quantity'] );
		$one_times  = ( isset( $_POST['pricing_one_time'] ) && is_array( $_POST['pricing_one_time'] ) ) ? wp_unslash( $_POST['pricing_one_time'] ) : array();

		$matrix = array();
		foreach ( $quantities as $i => $quantity ) {
			if ( '0' === trim( (string) $quantity ) ) {
				$key = '0';
			} else {
				$quantity = absint( $quantity );
				if ( $quantity < 1 ) {
					continue;
				}
				$key = (string) $quantity;
			}
			$matrix[ $key ] = array(
				'one-time' => isset( $one_times[ $i ] ) ? floatval( $one_times[ $i ] ) : 0,
			);
		}

		$matrix = $this->normalize_pricing_matrix( $matrix );

		// phpcs:enable
		return ! empty( $matrix ) ? $matrix : null;
	}

	/**
	 * Build pricing matrix from the hidden JSON field fallback.
	 *
	 * @return array|null Pricing matrix or null when JSON was not submitted or invalid.
	 * @since 1.0.0
	 */
	private function parse_pricing_matrix_json_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only called from save_meta_boxes() after nonce verification.
		if ( ! isset( $_POST['aveniadp_pricing_matrix'] ) ) {
			return null;
		}

		$decoded = json_decode( wp_unslash( $_POST['aveniadp_pricing_matrix'] ), true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$matrix = $this->normalize_pricing_matrix( $decoded );

		// phpcs:enable
		return ! empty( $matrix ) ? $matrix : null;
	}

	/**
	 * Save meta boxes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @since 1.0.0
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( AVENIADP_PRODUCT_POST_TYPE !== $post->post_type ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['aveniadp_product_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aveniadp_product_meta_nonce'] ) ), 'aveniadp_save_product_meta' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; POST values are sanitized before storage.

		if ( isset( $_POST['aveniadp_file_source'] ) ) {
			$file_source = sanitize_text_field( wp_unslash( $_POST['aveniadp_file_source'] ) );
			if ( ! in_array( $file_source, array( 'upload', 'external' ), true ) ) {
				$file_source = 'upload';
			}

			update_post_meta( $post_id, '_aveniadp_file_source', $file_source );

			if ( 'external' === $file_source ) {
				$external_url = isset( $_POST['aveniadp_file_url'] ) ? aveniadp_sanitize_product_external_file_url( wp_unslash( $_POST['aveniadp_file_url'] ) ) : '';
				update_post_meta( $post_id, '_aveniadp_file_url', $external_url );
				update_post_meta( $post_id, '_aveniadp_file_id', 0 );
			} else {
				if ( isset( $_POST['aveniadp_file_id'] ) ) {
					update_post_meta( $post_id, '_aveniadp_file_id', absint( $_POST['aveniadp_file_id'] ) );
				}
				update_post_meta( $post_id, '_aveniadp_file_url', '' );
			}
		} elseif ( isset( $_POST['aveniadp_file_id'] ) ) {
			update_post_meta( $post_id, '_aveniadp_file_id', absint( $_POST['aveniadp_file_id'] ) );
		}

		if ( 'external' !== get_post_meta( $post_id, '_aveniadp_file_source', true ) ) {
			$file_id = absint( get_post_meta( $post_id, '_aveniadp_file_id', true ) );
			if ( $file_id && function_exists( 'aveniadp_protect_product_attachment' ) ) {
				aveniadp_protect_product_attachment( $file_id );
			}
		}

		update_post_meta( $post_id, '_aveniadp_free_download', isset( $_POST['aveniadp_free_download'] ) ? '1' : '0' );

		// Classic editor only; block editor saves pinned state via REST meta.
		if ( ! $this->uses_block_editor_for_products() && function_exists( 'aveniadp_set_product_pinned_status' ) ) {
			aveniadp_set_product_pinned_status(
				$post_id,
				isset( $_POST['aveniadp_pinned_product'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['aveniadp_pinned_product'] ) )
			);
		}

		if ( isset( $_POST['aveniadp_demo_button_url'] ) ) {
			$demo_url = aveniadp_sanitize_product_external_file_url( wp_unslash( $_POST['aveniadp_demo_button_url'] ) );
			update_post_meta( $post_id, '_aveniadp_demo_button_url', $demo_url );
		}
		if ( isset( $_POST['aveniadp_demo_button_text'] ) ) {
			update_post_meta( $post_id, '_aveniadp_demo_button_text', sanitize_text_field( wp_unslash( $_POST['aveniadp_demo_button_text'] ) ) );
		}

		// Save pricing matrix (prefer visible table fields; works in block and classic editors).
		$pricing_matrix = $this->parse_pricing_matrix_from_request();
		if ( null === $pricing_matrix ) {
			$pricing_matrix = $this->parse_pricing_matrix_json_from_request();
		}
		if ( is_array( $pricing_matrix ) ) {
			update_post_meta( $post_id, '_aveniadp_pricing_matrix', $pricing_matrix );
		}

		if ( isset( $_POST['aveniadp_product_gallery'] ) && ! aveniadp_is_serving_rest_request() ) {
			update_post_meta(
				$post_id,
				'_aveniadp_product_gallery',
				aveniadp_normalize_product_gallery_meta( wp_unslash( $_POST['aveniadp_product_gallery'] ), $post_id )
			);
		}

		if ( isset( $_POST['aveniadp_related_products'] ) && ! aveniadp_is_serving_rest_request() ) {
			update_post_meta(
				$post_id,
				'_aveniadp_related_products',
				aveniadp_normalize_related_products_meta( wp_unslash( $_POST['aveniadp_related_products'] ), $post_id )
			);
		}

		$info_titles   = isset( $_POST['aveniadp_info_section_title'] ) ? wp_unslash( $_POST['aveniadp_info_section_title'] ) : array();
		$info_contents = isset( $_POST['aveniadp_info_section_content'] ) ? wp_unslash( $_POST['aveniadp_info_section_content'] ) : array();
		if ( is_array( $info_titles ) || is_array( $info_contents ) ) {
			$info_sections = aveniadp_sanitize_product_info_sections_from_request(
				is_array( $info_titles ) ? $info_titles : array(),
				is_array( $info_contents ) ? $info_contents : array()
			);
			update_post_meta( $post_id, '_aveniadp_product_info_sections', $info_sections );
		}

		// phpcs:enable
	}

	/**
	 * Normalize gallery on classic saves only (REST saves run after meta is written).
	 *
	 * @param int $post_id Post ID.
	 * @since 1.0.0
	 */
	public function maybe_normalize_product_gallery_meta( $post_id ) {
		if ( aveniadp_is_serving_rest_request() ) {
			return;
		}

		$this->normalize_product_gallery_meta( $post_id );
	}

	/**
	 * Normalize gallery meta and ensure product image sizes exist after save.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.0.0
	 */
	public function normalize_product_gallery_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$gallery = get_post_meta( $post_id, '_aveniadp_product_gallery', true );
		$had_meta  = false !== $gallery;

		if ( ! is_string( $gallery ) ) {
			$gallery = '';
		}

		$normalized = aveniadp_normalize_product_gallery_meta( $gallery, $post_id );

		if ( $gallery !== $normalized && ( $had_meta || '' !== $normalized ) ) {
			update_post_meta( $post_id, '_aveniadp_product_gallery', $normalized );
		}

		$featured_id = (int) get_post_thumbnail_id( $post_id );
		if ( $featured_id ) {
			aveniadp_regenerate_product_image_sizes( $featured_id );
		}

		foreach ( aveniadp_get_product_gallery_ids( $post_id ) as $attachment_id ) {
			aveniadp_regenerate_product_image_sizes( $attachment_id );
		}
	}

	/**
	 * Normalize gallery meta after block editor REST saves.
	 *
	 * @param WP_Post         $post     Saved post.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating Whether this is a new post.
	 * @since 1.0.0
	 */
	public function after_rest_insert_product( $post, $request, $creating ) {
		unset( $creating );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( $request instanceof WP_REST_Request ) {
			$meta = $request->get_param( 'meta' );
			if ( is_array( $meta ) && array_key_exists( '_aveniadp_product_gallery', $meta ) ) {
				update_post_meta(
					(int) $post->ID,
					'_aveniadp_product_gallery',
					aveniadp_normalize_product_gallery_meta( $meta['_aveniadp_product_gallery'], (int) $post->ID )
				);
			}

			if ( is_array( $meta ) && array_key_exists( '_aveniadp_related_products', $meta ) ) {
				update_post_meta(
					(int) $post->ID,
					'_aveniadp_related_products',
					aveniadp_normalize_related_products_meta( $meta['_aveniadp_related_products'], (int) $post->ID )
				);
			}

			if ( is_array( $meta ) && array_key_exists( '_aveniadp_pinned_product', $meta ) && function_exists( 'aveniadp_sanitize_pinned_product_meta' ) ) {
				update_post_meta(
					(int) $post->ID,
					'_aveniadp_pinned_product',
					aveniadp_sanitize_pinned_product_meta( $meta['_aveniadp_pinned_product'], (int) $post->ID )
				);
			}
		}

		$this->normalize_product_gallery_meta( (int) $post->ID );
	}

	/**
	 * Load script so product meta box panels open in the block editor (not collapsed / disabled).
	 *
	 * @since 1.0.0
	 */
	public function enqueue_product_block_editor_assets() {
		if ( ! $this->is_aveniadp_product_block_editor_context() ) {
			return;
		}

		wp_enqueue_script(
			'aveniadp-product-editor-panels',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/product-editor-panels.js',
			array( 'wp-dom-ready', 'wp-data', 'wp-editor', 'wp-preferences' ),
			AVENIADP_VERSION,
			true
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'aveniadp-product-gallery-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/product-gallery-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch' ),
			AVENIADP_VERSION,
			true
		);

		wp_localize_script(
			'aveniadp-product-gallery-panel',
			'aveniadpProductGalleryPanel',
			array(
				'postType' => AVENIADP_PRODUCT_POST_TYPE,
			)
		);

		wp_enqueue_style(
			'aveniadp-product-gallery-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/product-gallery-panel.css',
			array(),
			AVENIADP_VERSION
		);

		wp_enqueue_script(
			'aveniadp-product-demo-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/product-demo-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			AVENIADP_VERSION,
			true
		);

		wp_localize_script(
			'aveniadp-product-demo-panel',
			'aveniadpProductDemoPanel',
			array(
				'postType' => AVENIADP_PRODUCT_POST_TYPE,
			)
		);

		wp_enqueue_style(
			'aveniadp-product-demo-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/product-demo-panel.css',
			array(),
			AVENIADP_VERSION
		);

		wp_enqueue_script(
			'aveniadp-product-related-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/product-related-products-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch' ),
			AVENIADP_VERSION,
			true
		);

		wp_localize_script(
			'aveniadp-product-related-panel',
			'aveniadpProductRelatedPanel',
			array(
				'postType' => AVENIADP_PRODUCT_POST_TYPE,
				'max'      => aveniadp_get_max_related_products(),
			)
		);

		wp_enqueue_style(
			'aveniadp-product-related-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/product-related-products-panel.css',
			array(),
			AVENIADP_VERSION
		);

		wp_enqueue_script(
			'aveniadp-product-catalog-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/js/product-catalog-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			AVENIADP_VERSION,
			true
		);

		wp_localize_script(
			'aveniadp-product-catalog-panel',
			'aveniadpProductCatalogPanel',
			array(
				'postType' => AVENIADP_PRODUCT_POST_TYPE,
			)
		);

		wp_enqueue_style(
			'aveniadp-product-catalog-panel',
			AVENIADP_PLUGIN_URL . 'admin/assets/css/product-catalog-panel.css',
			array(),
			AVENIADP_VERSION
		);

		wp_localize_script(
			'aveniadp-product-editor-panels',
			'aveniadpProductEditorPanels',
			array(
				'postType'       => AVENIADP_PRODUCT_POST_TYPE,
				'metaPanelLabel' => __( 'Product Details', 'avenia-digital-products' ),
			)
		);
	}

	/**
	 * Whether the current request is the block editor for a product.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_aveniadp_product_block_editor_context() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && AVENIADP_PRODUCT_POST_TYPE === $screen->post_type ) {
				return true;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin editor screen detection when get_current_screen() is unavailable.
		if ( isset( $_GET['post_type'] ) && AVENIADP_PRODUCT_POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) {
			return true;
		}

		if ( isset( $_GET['post'] ) ) {
			return AVENIADP_PRODUCT_POST_TYPE === get_post_type( absint( $_GET['post'] ) );
		}
		// phpcs:enable

		return false;
	}

	/**
	 * Do not hide product meta boxes by default in Screen Options for new users.
	 *
	 * @param array     $hidden  Meta box ids hidden by default.
	 * @param WP_Screen $screen  Current screen.
	 * @return array
	 * @since 1.0.0
	 */
	public function ensure_product_meta_boxes_not_hidden_by_default( $hidden, $screen ) {
		if ( ! $screen || AVENIADP_PRODUCT_POST_TYPE !== $screen->post_type ) {
			return $hidden;
		}

		$ours = array( 'aveniadp_product_details', 'aveniadp_product_pricing', 'aveniadp_product_info_sections' );
		if ( ! $this->uses_block_editor_for_products() ) {
			$ours[] = 'aveniadp_product_gallery';
			$ours[] = 'aveniadp_product_demo';
			$ours[] = 'aveniadp_product_license';
			$ours[] = 'aveniadp_product_related';
			$ours[] = 'aveniadp_product_catalog';
		}
		return array_values( array_diff( (array) $hidden, $ours ) );
	}
}
