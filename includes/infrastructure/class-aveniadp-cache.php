<?php
/**
 * Page cache compatibility.
 *
 * Opts transactional storefront responses out of full-page caching and registers
 * exclusions with common cache plugins. Catalog HTML omits cart and auth state;
 * those load via the aveniadp_bootstrap AJAX endpoint so pages remain cacheable.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache compatibility class.
 *
 * @since 1.0.0
 */
class AVENIADP_Cache {

	/**
	 * Whether nocache has already been applied for this request.
	 *
	 * @var bool
	 */
	private static $nocache_applied = false;

	/**
	 * Initialize cache hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function init() {
		add_action( 'template_redirect', array( $this, 'maybe_nocache_request' ), 0 );

		// REST requests never reach template_redirect, so opt out during REST bootstrap.
		add_action( 'rest_api_init', array( $this, 'maybe_nocache_request' ), 0 );
		add_filter( 'rest_send_nocache_headers', array( $this, 'filter_rest_send_nocache_headers' ) );

		// WP Rocket.
		add_filter( 'rocket_cache_reject_uri', array( $this, 'filter_reject_uri' ) );
		add_filter( 'rocket_cache_query_strings', array( $this, 'filter_query_strings' ) );

		// W3 Total Cache.
		add_filter( 'w3tc_can_cache', array( $this, 'filter_w3tc_can_cache' ), 10, 2 );

		// SiteGround Optimizer.
		add_filter( 'sgo_bypass_query_params', array( $this, 'filter_sg_bypass_query_params' ) );
		add_filter( 'sgo_exclude_from_cache', array( $this, 'filter_sg_exclude_uris' ) );

		// Hummingbird.
		add_filter( 'wphb_do_not_cache', array( $this, 'filter_hummingbird_do_not_cache' ) );

		// Cache Enabler.
		add_filter( 'cache_enabler_bypass_cache', array( $this, 'filter_cache_enabler_bypass' ) );

		// Cloudflare WordPress plugin.
		add_filter( 'cloudflare_bypass_cache', array( $this, 'filter_cloudflare_bypass' ) );
	}

	/**
	 * Mark the current response as not cacheable.
	 *
	 * Safe to call multiple times and before headers are sent. Sets DONOTCACHEPAGE,
	 * sends nocache headers, and notifies cache plugins that listen for opt-out hooks.
	 *
	 * @param string $reason Short reason for logs / LiteSpeed.
	 * @since 1.0.0
	 */
	public static function set_nocache( $reason = 'avenia-digital-products' ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared cache-plugin opt-out constant (WP Super Cache, LiteSpeed, etc.).
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() && function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache API for nocache opt-out.
		do_action( 'litespeed_control_set_nocache', $reason );

		/**
		 * Fires when the plugin opts the current response out of page caching.
		 *
		 * @param string $reason Reason slug.
		 * @since 1.0.0
		 */
		do_action( 'aveniadp_set_nocache', $reason );

		self::$nocache_applied = true;
	}

	/**
	 * Whether the current front-end request must bypass page cache.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function should_nocache_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( function_exists( 'aveniadp_is_serving_rest_request' ) && aveniadp_is_serving_rest_request() ) {
			return $this->is_plugin_rest_request();
		}

		$should = $this->is_excluded_store_page()
			|| $this->request_has_uncacheable_query_arg()
			|| $this->is_download_request()
			|| $this->is_plugin_rest_request();

		/**
		 * Filter whether the current request should bypass page cache.
		 *
		 * @param bool $should Whether to nocache.
		 * @since 1.0.0
		 */
		return (bool) apply_filters( 'aveniadp_should_nocache_request', $should );
	}

	/**
	 * Apply nocache when the current request is transactional.
	 *
	 * @since 1.0.0
	 */
	public function maybe_nocache_request() {
		if ( self::$nocache_applied ) {
			return;
		}

		if ( $this->should_nocache_request() ) {
			self::set_nocache( 'aveniadp-transactional' );
		}
	}

	/**
	 * Send nocache headers on plugin REST responses even for anonymous callers.
	 *
	 * Core only sends them for logged-in users, which leaves license and webhook
	 * responses cacheable by reverse proxies that key on headers alone.
	 *
	 * @param bool $send Whether core sends nocache headers.
	 * @return bool
	 * @since 1.0.0
	 */
	public function filter_rest_send_nocache_headers( $send ) {
		if ( $this->is_plugin_rest_request() ) {
			return true;
		}

		return $send;
	}

	/**
	 * Store page IDs that must never be page-cached.
	 *
	 * @return int[]
	 * @since 1.0.0
	 */
	public function get_excluded_page_ids() {
		$ids = array_filter(
			array_map(
				'absint',
				array(
					get_option( 'aveniadp_page_cart_id', 0 ),
					get_option( 'aveniadp_page_checkout_id', 0 ),
					get_option( 'aveniadp_page_account_id', 0 ),
					get_option( 'aveniadp_page_login_id', 0 ),
					get_option( 'aveniadp_thank_you_page', 0 ),
				)
			)
		);

		/**
		 * Filter page IDs excluded from full-page cache.
		 *
		 * @param int[] $ids Page IDs.
		 * @since 1.0.0
		 */
		return array_values( array_unique( array_map( 'absint', (array) apply_filters( 'aveniadp_cache_excluded_page_ids', $ids ) ) ) );
	}

	/**
	 * Query args that force a cache bypass when present.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public function get_uncacheable_query_args() {
		$args = array(
			'aveniadp_buy',
			'aveniadp_buy_error',
			'aveniadp_thank_you',
			'thank_you_token',
			'aveniadp_payment_return',
			'aveniadp_payment_cancelled',
			'aveniadp_payment_error',
			'aveniadp_download',
			'aveniadp_account_download',
		);

		/**
		 * Filter query args that prevent page caching.
		 *
		 * @param string[] $args Query arg names.
		 * @since 1.0.0
		 */
		return array_values( array_unique( (array) apply_filters( 'aveniadp_cache_uncacheable_query_args', $args ) ) );
	}

	/**
	 * URI path patterns (regex, no delimiters) for cache plugin reject lists.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public function get_excluded_uri_patterns() {
		$patterns = array(
			'/aveniadp-download/(.*)',
			'/aveniadp-account-download/(.*)',
			'/wp-json/avenia-digital-products/(.*)',
		);

		foreach ( $this->get_excluded_page_ids() as $page_id ) {
			$path = wp_parse_url( get_permalink( $page_id ), PHP_URL_PATH );
			if ( ! is_string( $path ) || '' === $path || '/' === $path ) {
				continue;
			}

			$patterns[] = preg_quote( untrailingslashit( $path ), '/' ) . '/?(.*)';
		}

		/**
		 * Filter URI patterns excluded from full-page cache.
		 *
		 * @param string[] $patterns Regex patterns without delimiters.
		 * @since 1.0.0
		 */
		return array_values( array_unique( (array) apply_filters( 'aveniadp_cache_excluded_uri_patterns', $patterns ) ) );
	}

	/**
	 * Whether the current view is a configured store page that must not be cached.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_excluded_store_page() {
		foreach ( $this->get_excluded_page_ids() as $page_id ) {
			if ( is_page( $page_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the request carries a plugin query arg that must hit PHP.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function request_has_uncacheable_query_arg() {
		foreach ( $this->get_uncacheable_query_args() as $arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Cache decision only; values unused.
			if ( isset( $_GET[ $arg ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this is a tokenized download request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_download_request() {
		global $wp_query;

		if ( ! empty( $wp_query ) && ( get_query_var( 'aveniadp_download' ) || get_query_var( 'aveniadp_account_download' ) ) ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path match only.
		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return false;
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}

		return (bool) preg_match( '#/(?:aveniadp-download|aveniadp-account-download)/#', $path );
	}

	/**
	 * Whether this is a plugin REST API request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_plugin_rest_request() {
		if ( empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path match only.
			return is_string( $request_uri ) && false !== strpos( $request_uri, '/wp-json/avenia-digital-products/' );
		}

		$route = (string) $GLOBALS['wp']->query_vars['rest_route'];

		return 0 === strpos( $route, '/avenia-digital-products/' );
	}

	/**
	 * WP Rocket: reject URI list.
	 *
	 * @param string[] $uris Existing patterns.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function filter_reject_uri( $uris ) {
		$uris = is_array( $uris ) ? $uris : array();

		return array_values( array_unique( array_merge( $uris, $this->get_excluded_uri_patterns() ) ) );
	}

	/**
	 * WP Rocket: query strings that prevent serving a cached page.
	 *
	 * @param string[] $list Query strings.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function filter_query_strings( $list ) {
		$list = is_array( $list ) ? $list : array();

		return array_values( array_unique( array_merge( $list, $this->get_uncacheable_query_args() ) ) );
	}

	/**
	 * W3 Total Cache: disallow caching when we need PHP.
	 *
	 * @param bool  $can_cache Whether W3TC may cache.
	 * @param mixed $context   Unused context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function filter_w3tc_can_cache( $can_cache, $context = null ) {
		unset( $context );

		if ( $this->should_nocache_request() ) {
			return false;
		}

		return $can_cache;
	}

	/**
	 * SiteGround Optimizer: bypass query params.
	 *
	 * @param string[] $params Params.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function filter_sg_bypass_query_params( $params ) {
		$params = is_array( $params ) ? $params : array();

		return array_values( array_unique( array_merge( $params, $this->get_uncacheable_query_args() ) ) );
	}

	/**
	 * SiteGround Optimizer: exclude URIs (plain paths when possible).
	 *
	 * @param string[] $uris URIs.
	 * @return string[]
	 * @since 1.0.0
	 */
	public function filter_sg_exclude_uris( $uris ) {
		$uris = is_array( $uris ) ? $uris : array();

		foreach ( $this->get_excluded_page_ids() as $page_id ) {
			$path = wp_parse_url( get_permalink( $page_id ), PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path && '/' !== $path ) {
				$uris[] = $path;
			}
		}

		$uris[] = '/aveniadp-download/';
		$uris[] = '/aveniadp-account-download/';
		$uris[] = '/wp-json/avenia-digital-products/';

		return array_values( array_unique( $uris ) );
	}

	/**
	 * Hummingbird: do not cache.
	 *
	 * @param bool $do_not_cache Whether to skip cache.
	 * @return bool
	 * @since 1.0.0
	 */
	public function filter_hummingbird_do_not_cache( $do_not_cache ) {
		if ( $this->should_nocache_request() ) {
			return true;
		}

		return $do_not_cache;
	}

	/**
	 * Cache Enabler: bypass.
	 *
	 * @param bool $bypass Whether to bypass.
	 * @return bool
	 * @since 1.0.0
	 */
	public function filter_cache_enabler_bypass( $bypass ) {
		if ( $this->should_nocache_request() ) {
			return true;
		}

		return $bypass;
	}

	/**
	 * Cloudflare WordPress plugin: bypass cache.
	 *
	 * @param bool $bypass Whether to bypass.
	 * @return bool
	 * @since 1.0.0
	 */
	public function filter_cloudflare_bypass( $bypass ) {
		if ( $this->should_nocache_request() ) {
			return true;
		}

		return $bypass;
	}
}
