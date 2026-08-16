<?php
/**
 * Frontend functionality.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public class.
 *
 * @since 1.0.0
 */
class AVENIADP_Public {

	/**
	 * Cached result of should_show_thank_you() for this request.
	 *
	 * @var bool|null
	 */
	private $show_thank_you = null;

	/**
	 * Initialize public.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize.
	 *
	 * Frontend hooks only; the class is always bootstrapped so admin-side or REST
	 * callers can use AVENIADP_Public methods without a separate load path.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Public session endpoint (nonce, auth, cart) — registered for AJAX in admin too.
		add_action( 'wp_ajax_aveniadp_bootstrap', array( $this, 'ajax_bootstrap' ) );
		add_action( 'wp_ajax_nopriv_aveniadp_bootstrap', array( $this, 'ajax_bootstrap' ) );

		if ( is_admin() ) {
			return;
		}

		// Enqueue public assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Thank-you message after payment.
		add_action( 'wp_footer', array( $this, 'maybe_show_thank_you' ) );
		add_action( 'wp_footer', array( $this, 'render_cart_drawer' ) );

		// Register shortcodes.
		add_shortcode( 'aveniadp_products', array( $this, 'products_shortcode' ) );
		add_shortcode( 'aveniadp_account', array( $this, 'account_shortcode' ) );
		add_shortcode( 'aveniadp_login', array( $this, 'login_shortcode' ) );

		// Single product layout inside theme content (does not replace header/footer).
		add_filter( 'the_content', array( $this, 'render_single_product_content' ), 20 );
		add_filter( 'render_block', array( $this, 'hide_single_product_blocks' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'replace_catalog_query_block' ), 9, 2 );
		add_filter( 'render_block', array( $this, 'hide_product_archive_title' ), 9, 2 );
		add_filter( 'render_block', array( $this, 'ensure_products_block_output' ), 20, 2 );
		add_filter( 'body_class', array( $this, 'single_product_body_class' ) );
		add_action( 'wp', array( $this, 'disable_single_product_post_features' ) );

		// Classic theme catalog templates; block themes use render_block hooks instead.
		add_filter( 'template_include', array( $this, 'template_loader' ) );
		add_filter( 'taxonomy_template_hierarchy', array( $this, 'add_archive_to_product_taxonomy_hierarchy' ) );
	}

	/**
	 * Enqueue public assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'aveniadp-public',
			AVENIADP_PLUGIN_URL . 'public/assets/css/public.css',
			array(),
			AVENIADP_VERSION
		);

		if ( function_exists( 'aveniadp_get_appearance_css' ) ) {
			wp_add_inline_style( 'aveniadp-public', aveniadp_get_appearance_css() );
		}

		wp_enqueue_script(
			'aveniadp-public',
			AVENIADP_PLUGIN_URL . 'public/assets/js/public.js',
			array( 'jquery' ),
			AVENIADP_VERSION,
			true
		);

		if ( $this->should_show_thank_you() ) {
			wp_add_inline_script(
				'aveniadp-public',
				'setTimeout(function(){var e=document.querySelector(".aveniadp-thank-you-notice");if(e){e.remove();}},8000);'
			);
		}

		wp_localize_script(
			'aveniadp-public',
			'aveniadpPublic',
			array(
				'ajaxUrl'                    => admin_url( 'admin-ajax.php' ),
				// Nonce, auth, and cart are provided by aveniadp_bootstrap so HTML remains cacheable.
				'nonce'                      => '',
				'needsBootstrap'             => true,
				'homeUrl'                    => home_url( '/' ),
				'thankYouBaseUrl'            => function_exists( 'aveniadp_get_thank_you_base_url' ) ? aveniadp_get_thank_you_base_url() : add_query_arg( 'aveniadp_thank_you', '1', home_url( '/' ) ),
				'cartUrl'                    => function_exists( 'aveniadp_get_cart_page_url' ) ? aveniadp_get_cart_page_url() : '',
				'checkoutUrl'                => function_exists( 'aveniadp_get_checkout_page_url' ) ? aveniadp_get_checkout_page_url() : '',
				'skipCartToCheckout'         => 'yes' === get_option( 'aveniadp_skip_cart_to_checkout', 'no' ),
				'accountUrl'                 => function_exists( 'aveniadp_get_account_page_url' ) ? aveniadp_get_account_page_url() : home_url( '/account/' ),
				'freeDownloadRequireEmail'   => function_exists( 'aveniadp_free_download_requires_email' ) && aveniadp_free_download_requires_email(),
				'isLoggedIn'                 => false,
				'userEmail'                  => '',
				'checkoutProcessing'         => __( 'Processing...', 'avenia-digital-products' ),
				'checkoutError'              => __( 'Something went wrong. Please try again.', 'avenia-digital-products' ),
				'emailRequired'              => __( 'Email is required.', 'avenia-digital-products' ),
				'emailConfirmRequired'       => __( 'Please confirm your email address.', 'avenia-digital-products' ),
				'emailMismatch'              => __( 'Email addresses do not match.', 'avenia-digital-products' ),
				'labels'                     => array(
					'subtotal'                 => __( 'Subtotal', 'avenia-digital-products' ),
					'total'                    => __( 'Total', 'avenia-digital-products' ),
					'dueToday'                 => __( 'Due today', 'avenia-digital-products' ),
					'discount'                 => __( 'Discount', 'avenia-digital-products' ),
					'tax'                      => __( 'Tax', 'avenia-digital-products' ),
					'pricesIncludeTax'         => __( 'Prices include tax.', 'avenia-digital-products' ),
					'discountCode'             => __( 'Discount Code', 'avenia-digital-products' ),
					'discountApplied'          => __( 'Discount applied:', 'avenia-digital-products' ),
					'haveDiscountCode'         => __( 'Have a discount code?', 'avenia-digital-products' ),
					'apply'                    => __( 'Apply', 'avenia-digital-products' ),
					'removeDiscount'           => __( 'Remove', 'avenia-digital-products' ),
					'selectLicense'            => __( 'Please select a license.', 'avenia-digital-products' ),
					'updateCart'               => __( 'Update Cart', 'avenia-digital-products' ),
					'addToCart'                => __( 'Add to Cart', 'avenia-digital-products' ),
					'galleryLightbox'          => __( 'Image gallery', 'avenia-digital-products' ),
					'galleryClose'             => __( 'Close', 'avenia-digital-products' ),
					'galleryPrev'              => __( 'Previous image', 'avenia-digital-products' ),
					'galleryNext'              => __( 'Next image', 'avenia-digital-products' ),
					'galleryZoom'              => __( 'View fullscreen', 'avenia-digital-products' ),
					'freeDownloadModalTitle'   => __( 'Get your free download', 'avenia-digital-products' ),
					'freeDownloadEmailLabel'   => __( 'Email address', 'avenia-digital-products' ),
					'freeDownloadSubmit'       => __( 'Send download link', 'avenia-digital-products' ),
					'freeDownloadPrivacy'      => __( 'We will email your download link. No account is required.', 'avenia-digital-products' ),
					'freeDownloadGoToAccount'  => __( 'Go to My Account', 'avenia-digital-products' ),
					'freeDownloadInvalidEmail' => __( 'Please enter a valid email address.', 'avenia-digital-products' ),
					'cartDrawerTitle'          => __( 'Your cart', 'avenia-digital-products' ),
					/* translators: %d: number of items in cart */
					'cartDrawerCountSingular'  => __( '%d item in cart', 'avenia-digital-products' ),
					/* translators: %d: number of items in cart */
					'cartDrawerCountPlural'    => __( '%d items in cart', 'avenia-digital-products' ),
					'cartDrawerEmpty'          => __( 'Your cart is empty.', 'avenia-digital-products' ),
					'cartDrawerCheckout'       => __( 'Checkout', 'avenia-digital-products' ),
					'cartDrawerChangeOptions'  => __( 'Change options', 'avenia-digital-products' ),
					'cartDrawerRemove'         => __( 'Remove item', 'avenia-digital-products' ),
					'cartDrawerClose'          => __( 'Close cart', 'avenia-digital-products' ),
				),
			)
		);
	}

	/**
	 * AJAX: return a fresh nonce, login state, and cart drawer for the current visitor.
	 *
	 * Read-only; no nonce required. The returned nonce is used for subsequent write actions.
	 *
	 * @since 1.0.0
	 */
	public function ajax_bootstrap() {
		if ( function_exists( 'aveniadp_nocache' ) ) {
			aveniadp_nocache( 'aveniadp-bootstrap' );
		}

		$payload = array(
			'nonce'      => wp_create_nonce( 'aveniadp-public' ),
			'isLoggedIn' => is_user_logged_in(),
			'userEmail'  => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'cart_count' => 0,
			'cart_items' => array(),
			'drawer'     => null,
		);

		if ( function_exists( 'aveniadp_cart' ) ) {
			$cart                  = aveniadp_cart();
			$cart_contents         = $cart->get_cart();
			$payload['cart_count'] = count( $cart_contents );
			// Cast to object so empty carts JSON-encode as {} (not []), which JS treats as a map.
			$payload['cart_items'] = (object) $this->get_bootstrap_cart_items( $cart_contents );

			if ( 'yes' !== get_option( 'aveniadp_skip_cart_to_checkout', 'no' ) ) {
				$payload['drawer'] = $cart->get_cart_drawer_data();
			}
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Build a product-ID-keyed map of cart options for purchase-form sync.
	 *
	 * Used by aveniadp_bootstrap so cached product pages can flip Add to Cart → Update Cart
	 * and restore the visitor's selected license quantity.
	 *
	 * @param array $cart_contents Cart items keyed by product ID.
	 * @return array
	 * @since 1.0.0
	 */
	private function get_bootstrap_cart_items( $cart_contents ) {
		$items = array();

		if ( empty( $cart_contents ) || ! is_array( $cart_contents ) ) {
			return $items;
		}

		foreach ( $cart_contents as $product_id => $item ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				continue;
			}

			$items[ $product_id ] = array(
				'license_quantity' => isset( $item['license_quantity'] ) ? aveniadp_sanitize_license_quantity( $item['license_quantity'], 1 ) : 1,
			);
		}

		return $items;
	}

	/**
	 * Whether the thank-you toast should display on this request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function should_show_thank_you() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Public thank-you URL uses order_id plus thank_you_token verified below.
		if ( null !== $this->show_thank_you ) {
			return $this->show_thank_you;
		}

		if ( empty( $_GET['aveniadp_thank_you'] ) || empty( $_GET['order_id'] ) ) {
			$this->show_thank_you = false;
			return false;
		}

		$order_id = absint( $_GET['order_id'] );
		if ( ! $order_id ) {
			$this->show_thank_you = false;
			return false;
		}

		$token = isset( $_GET['thank_you_token'] ) ? sanitize_text_field( wp_unslash( $_GET['thank_you_token'] ) ) : '';

		$this->show_thank_you = function_exists( 'aveniadp_verify_thank_you_access' ) && aveniadp_verify_thank_you_access( $order_id, $token );

		// phpcs:enable
		return $this->show_thank_you;
	}

	/**
	 * Show thank-you message when redirected after successful payment.
	 *
	 * @since 1.0.0
	 */
	public function maybe_show_thank_you() {
		if ( ! $this->should_show_thank_you() ) {
			return;
		}

		$order_id = absint( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated in should_show_thank_you().
		?>
		<div class="aveniadp-notice aveniadp-notice--toast is-success aveniadp-thank-you-notice" role="status" aria-live="polite">
			<?php
			/* translators: %d: order ID */
			echo esc_html( sprintf( __( 'Thank you for your order. Your order number is #%d.', 'avenia-digital-products' ), $order_id ) );
			?>
		</div>
		<?php
	}

	/**
	 * Output the side cart drawer markup in the footer.
	 *
	 * @since 1.0.0
	 */
	public function render_cart_drawer() {
		if ( is_admin() || 'yes' === get_option( 'aveniadp_skip_cart_to_checkout', 'no' ) ) {
			return;
		}

		$template = AVENIADP_PLUGIN_DIR . 'public/templates/cart-drawer.php';
		if ( ! file_exists( $template ) ) {
			return;
		}

		include $template;
	}

	/**
	 * Products shortcode — see aveniadp_render_catalog_grid() in aveniadp-catalog-functions.php.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function products_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page'     => 0,
				'category'     => '',
				'tag'          => '',
				'order_by'     => '',
				'order'        => '',
				'columns'      => 0,
				'show_image'   => '',
				'show_price'   => '',
				'show_excerpt' => '',
			),
			$atts,
			'aveniadp_products'
		);

		return aveniadp_render_catalog_grid( aveniadp_get_catalog_grid_args_from_shortcode_atts( $atts ) );
	}

	/**
	 * Account shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function account_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p class="aveniadp-notice">' . esc_html__( 'Please log in to view your account.', 'avenia-digital-products' ) . '</p>';
		}

		return aveniadp_get_template_html( 'account.php' );
	}

	/**
	 * Login shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 * @since 1.0.0
	 */
	public function login_shortcode( $atts ) {
		if ( is_user_logged_in() ) {
			return '<p class="aveniadp-notice">' . esc_html__( 'You are already logged in.', 'avenia-digital-products' ) . '</p>';
		}

		return aveniadp_get_template_html( 'login.php' );
	}

	/**
	 * Whether the plugin should render its single product layout.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function uses_custom_single_product_layout() {
		if ( ! is_singular( AVENIADP_PRODUCT_POST_TYPE ) ) {
			return false;
		}

		if ( locate_template( 'avenia-digital-products/single-aveniadp_product.php' ) || ! wp_is_block_theme() ) {
			return false;
		}

		/**
		 * Whether the plugin injects single product content into the theme layout.
		 *
		 * Classic themes use the plugin or theme single-aveniadp_product.php shell instead.
		 *
		 * @param bool $use_layout  Whether to inject via the_content.
		 * @param int  $product_id  Product post ID.
		 */
		return apply_filters( 'aveniadp_use_custom_single_product_layout', true, get_queried_object_id() );
	}

	/**
	 * Whether classic-theme PHP fallbacks are needed for single products.
	 *
	 * Block themes rely on render_block to hide duplicate post UI; classic themes
	 * also use comment/author/nav filters.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function uses_single_product_classic_fallbacks() {
		return $this->uses_custom_single_product_layout() && ! wp_is_block_theme();
	}

	/**
	 * Add body class when the plugin renders the single product layout.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function single_product_body_class( $classes ) {
		if ( $this->uses_custom_single_product_layout() ) {
			$classes[] = 'aveniadp-custom-single-product';

			if ( ! wp_is_block_theme() ) {
				$classes[] = 'aveniadp-classic-theme';
			}
		}

		return $classes;
	}

	/**
	 * Disable default post features on single product pages.
	 *
	 * @since 1.0.0
	 */
	public function disable_single_product_post_features() {
		if ( ! $this->uses_single_product_classic_fallbacks() ) {
			return;
		}

		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'previous_post_link', '__return_empty_string', 20, 2 );
		add_filter( 'next_post_link', '__return_empty_string', 20, 2 );
		add_filter( 'get_the_term_list', array( $this, 'filter_single_product_term_list' ), 20, 3 );
		add_filter( 'the_author', array( $this, 'filter_single_product_author' ), 20 );
		add_filter( 'get_the_author', array( $this, 'filter_single_product_author' ), 20 );
	}

	/**
	 * Hide taxonomy lists rendered by the theme on single product pages.
	 *
	 * @param string $term_list Term list HTML.
	 * @param string $taxonomy  Taxonomy slug.
	 * @param string $before    Before string.
	 * @return string
	 * @since 1.0.0
	 */
	public function filter_single_product_term_list( $term_list, $taxonomy, $before ) {
		unset( $taxonomy, $before );

		return '';
	}

	/**
	 * Hide author output in the theme post meta area on single product pages.
	 *
	 * @param string $author Author display name.
	 * @return string
	 * @since 1.0.0
	 */
	public function filter_single_product_author( $author ) {
		if ( is_admin() || ! is_singular( AVENIADP_PRODUCT_POST_TYPE ) || ! in_the_loop() ) {
			return $author;
		}

		return '';
	}

	/**
	 * Block names to hide when the theme still renders default post UI.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private function get_hidden_single_product_blocks() {
		return array(
			'core/post-title',
			'core/post-featured-image',
			'core/post-excerpt',
			'core/post-author-name',
			'core/post-author-biography',
			'core/post-author',
			'core/post-date',
			'core/post-terms',
			'core/post-navigation-link',
			'core/comments',
			'core/post-comments-form',
			'core/latest-posts',
			'core/query',
		);
	}

	/**
	 * Whether a block pattern slug should be hidden on single product pages.
	 *
	 * @param string $slug Pattern slug.
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_hidden_single_product_pattern( $slug ) {
		if ( ! $slug ) {
			return false;
		}

		$fragments = array(
			'written-by',
			'post-navigation',
			'comments',
			'more-post',
			'related-post',
			'author-bio',
		);

		foreach ( $fragments as $fragment ) {
			if ( false !== strpos( $slug, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hide default post blocks on single product pages.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 * @since 1.0.0
	 */
	public function hide_single_product_blocks( $block_content, $block ) {
		if ( ! $this->uses_custom_single_product_layout() ) {
			return $block_content;
		}

		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';

		if ( 'core/pattern' === $block_name ) {
			$slug = isset( $block['attrs']['slug'] ) ? $block['attrs']['slug'] : '';
			if ( $this->is_hidden_single_product_pattern( $slug ) ) {
				return '';
			}
		}

		if ( in_array( $block_name, $this->get_hidden_single_product_blocks(), true ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Replace post content with the plugin single product layout.
	 *
	 * @param string $content Post content.
	 * @return string
	 * @since 1.0.0
	 */
	public function render_single_product_content( $content ) {
		if ( ! is_singular( AVENIADP_PRODUCT_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! $this->uses_custom_single_product_layout() ) {
			return $content;
		}

		static $rendered = false;
		if ( $rendered ) {
			return '';
		}
		$rendered = true;

		remove_filter( 'the_content', array( $this, 'render_single_product_content' ), 20 );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress content filter.
		$aveniadp_product_description = apply_filters( 'the_content', get_post()->post_content );
		add_filter( 'the_content', array( $this, 'render_single_product_content' ), 20 );

		return aveniadp_get_template_html(
			'single-product-content.php',
			array(
				'aveniadp_product_description' => $aveniadp_product_description,
			)
		);
	}

	/**
	 * Whether the current request is a product catalog archive view.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_product_catalog_view() {
		return is_post_type_archive( AVENIADP_PRODUCT_POST_TYPE )
			|| is_tax( 'aveniadp_product_category' )
			|| is_tax( 'aveniadp_product_tag' )
			|| ( function_exists( 'aveniadp_is_shop_page' ) && aveniadp_is_shop_page() );
	}

	/**
	 * Hide the default archive heading on the product post type archive.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 * @since 1.0.0
	 */
	public function hide_product_archive_title( $block_content, $block ) {
		if ( ! is_post_type_archive( AVENIADP_PRODUCT_POST_TYPE ) ) {
			return $block_content;
		}

		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( 'core/query-title' !== $block_name ) {
			return $block_content;
		}

		$type = isset( $block['attrs']['type'] ) ? $block['attrs']['type'] : '';
		if ( 'archive' === $type || '' === $type ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Replace the theme post query loop with the product catalog on block theme archives.
	 *
	 * Safety net when the theme's generic archive.html is used for product archives.
	 * Block themes often ship a core/query block locked to the "post" type, which
	 * renders nothing on custom post type archives.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 * @since 1.0.0
	 */
	public function replace_catalog_query_block( $block_content, $block ) {
		if ( ! wp_is_block_theme() || ! $this->is_product_catalog_view() ) {
			return $block_content;
		}

		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( 'core/query' !== $block_name ) {
			return $block_content;
		}

		static $catalog_grid_rendered = false;
		if ( $catalog_grid_rendered ) {
			return '';
		}
		$catalog_grid_rendered = true;

		return aveniadp_render_catalog_grid();
	}

	/**
	 * Ensure the Products block outputs a catalog grid when its render callback fails.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 * @since 1.0.0
	 */
	public function ensure_products_block_output( $block_content, $block ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		if ( 'avenia-digital-products/products' !== $block_name ) {
			return $block_content;
		}

		if ( '' !== trim( $block_content ) ) {
			return $block_content;
		}

		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		return aveniadp_render_products_block( $attributes, '', null );
	}

	/**
	 * Add archive-aveniadp_product as a fallback for product category and tag templates.
	 *
	 * Keeps taxonomy views from falling through to the theme's generic archive.html.
	 *
	 * @param string[] $template_hierarchy Template hierarchy.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function add_archive_to_product_taxonomy_hierarchy( $template_hierarchy ) {
		if ( ! is_tax( array( 'aveniadp_product_category', 'aveniadp_product_tag' ) ) ) {
			return $template_hierarchy;
		}

		$template_hierarchy[] = 'archive-aveniadp_product';

		return $template_hierarchy;
	}

	/**
	 * Resolve a plugin shell template for classic theme views.
	 *
	 * Theme overrides in avenia-digital-products/ always win. On block themes the
	 * theme's block templates stay in place; UI is injected via hooks unless the
	 * theme provides a PHP shell override.
	 *
	 * @param string $template_name Template file name.
	 * @return string|null Template path, or null to keep the theme/block template.
	 * @since 1.0.0
	 */
	private function resolve_plugin_shell_template( $template_name ) {
		$theme_override = locate_template( 'avenia-digital-products/' . $template_name );
		if ( $theme_override ) {
			return $theme_override;
		}

		if ( wp_is_block_theme() ) {
			return null;
		}

		$plugin_template = AVENIADP_PLUGIN_DIR . 'public/templates/' . $template_name;
		if ( is_readable( $plugin_template ) ) {
			return $plugin_template;
		}

		return null;
	}

	/**
	 * Template loader.
	 *
	 * Classic themes use plugin PHP shell templates when the theme has no override.
	 * Block themes keep the theme template and rely on render_block/the_content hooks.
	 *
	 * @param string $template Template path.
	 * @return string
	 * @since 1.0.0
	 */
	public function template_loader( $template ) {
		if ( is_singular( AVENIADP_PRODUCT_POST_TYPE ) ) {
			$single_template = $this->resolve_plugin_shell_template( 'single-aveniadp_product.php' );
			if ( $single_template ) {
				return $single_template;
			}
		}

		if ( is_post_type_archive( AVENIADP_PRODUCT_POST_TYPE ) ) {
			$archive_template = $this->resolve_plugin_shell_template( 'archive-aveniadp_product.php' );
			if ( $archive_template ) {
				return $archive_template;
			}
		}

		if ( is_tax( 'aveniadp_product_category' ) ) {
			$taxonomy_template = $this->resolve_plugin_shell_template( 'taxonomy-aveniadp_product_category.php' );
			if ( $taxonomy_template ) {
				return $taxonomy_template;
			}
		}

		if ( is_tax( 'aveniadp_product_tag' ) ) {
			$taxonomy_template = $this->resolve_plugin_shell_template( 'taxonomy-aveniadp_product_tag.php' );
			if ( $taxonomy_template ) {
				return $taxonomy_template;
			}
		}

		return $template;
	}
}
