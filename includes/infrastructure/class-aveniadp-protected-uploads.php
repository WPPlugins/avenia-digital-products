<?php
/**
 * Protected product file uploads.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores product download files outside public access and blocks direct URLs.
 *
 * @since 1.0.0
 */
class AVENIADP_Protected_Uploads {

	/**
	 * Upload subdirectory name (under wp-content/uploads).
	 *
	 * @var string
	 */
	const UPLOAD_SUBDIR = 'aveniadp-protected';

	/**
	 * Attachment meta key marking protected files.
	 *
	 * @var string
	 */
	const PROTECTED_META_KEY = '_aveniadp_protected_file';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		add_action( 'add_attachment', array( $this, 'mark_uploaded_attachment' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'block_direct_access' ), 1 );
	}

	/**
	 * Whether an attachment is stored as a protected product file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_protected_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		return $attachment_id && '1' === get_post_meta( $attachment_id, self::PROTECTED_META_KEY, true );
	}

	/**
	 * Ensure the protected upload directory exists with deny rules.
	 *
	 * @return string|false Absolute path to protected base directory.
	 * @since 1.0.0
	 */
	public static function ensure_directory() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$protected_dir = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! wp_mkdir_p( $protected_dir ) ) {
			return false;
		}

		self::write_security_files( $protected_dir );

		return $protected_dir;
	}

	/**
	 * Move an attachment into the protected directory if needed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function protect_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( $this->is_protected_attachment( $attachment_id ) ) {
			return true;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$protected_base = trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! wp_mkdir_p( $protected_base ) ) {
			return false;
		}

		self::write_security_files( $protected_base );

		$relative_path = $this->get_upload_relative_path( $file_path );
		if ( $relative_path && 0 === strpos( $relative_path, self::UPLOAD_SUBDIR . '/' ) ) {
			update_post_meta( $attachment_id, self::PROTECTED_META_KEY, '1' );
			return true;
		}

		$time     = filemtime( $file_path );
		$subdir   = '/' . gmdate( 'Y/m', $time ? $time : time() );
		$dest_dir = $protected_base . $subdir;
		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return false;
		}

		self::write_security_files( $dest_dir );

		$filename  = wp_unique_filename( $dest_dir, basename( $file_path ) );
		$dest_path = trailingslashit( $dest_dir ) . $filename;

		if ( ! $this->move_file( $file_path, $dest_path ) ) {
			return false;
		}

		$this->delete_attachment_derivatives( $attachment_id, $file_path );

		$new_relative = self::UPLOAD_SUBDIR . $subdir . '/' . $filename;
		update_attached_file( $attachment_id, $new_relative );
		update_post_meta( $attachment_id, self::PROTECTED_META_KEY, '1' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) ) {
			unset( $metadata['file'], $metadata['sizes'], $metadata['original_image'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => trailingslashit( $upload_dir['baseurl'] ) . $new_relative,
			)
		);

		return true;
	}

	/**
	 * Route protected uploads into the secure directory.
	 *
	 * @param array $dirs Upload directory data.
	 * @return array
	 * @since 1.0.0
	 */
	public function filter_upload_dir( $dirs ) {
		if ( ! $this->is_protected_upload_request() ) {
			return $dirs;
		}

		$subdir        = '/' . self::UPLOAD_SUBDIR . $dirs['subdir'];
		$dirs['path']  = $dirs['basedir'] . $subdir;
		$dirs['url']   = $dirs['baseurl'] . $subdir;
		$dirs['subdir'] = $subdir;

		if ( ! wp_mkdir_p( $dirs['path'] ) ) {
			return $dirs;
		}

		self::write_security_files( trailingslashit( $dirs['basedir'] ) . self::UPLOAD_SUBDIR );
		self::write_security_files( $dirs['path'] );

		return $dirs;
	}

	/**
	 * Mark attachments uploaded through the product file picker.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @since 1.0.0
	 */
	public function mark_uploaded_attachment( $attachment_id ) {
		if ( ! $this->is_protected_upload_request() ) {
			return;
		}

		update_post_meta( $attachment_id, self::PROTECTED_META_KEY, '1' );
	}

	/**
	 * Hide protected attachment URLs on the front end.
	 *
	 * @param string $url           Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 * @since 1.0.0
	 */
	public function filter_attachment_url( $url, $attachment_id ) {
		if ( is_admin() || ! $this->is_protected_attachment( $attachment_id ) ) {
			return $url;
		}

		return '';
	}

	/**
	 * Block direct HTTP access to protected files and attachment pages.
	 *
	 * @since 1.0.0
	 */
	public function block_direct_access() {
		if ( is_admin() ) {
			return;
		}

		if ( is_attachment() ) {
			$attachment_id = get_queried_object_id();
			if ( $attachment_id && $this->is_protected_attachment( $attachment_id ) ) {
				wp_die( esc_html__( 'Direct access to this file is not allowed.', 'avenia-digital-products' ), esc_html__( 'Forbidden', 'avenia-digital-products' ), array( 'response' => 403 ) );
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_uri = is_string( $request_uri ) ? $request_uri : '';

		if ( '' === $request_uri || false === strpos( $request_uri, self::UPLOAD_SUBDIR ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['baseurl'] ) ) {
			return;
		}

		$protected_path = wp_parse_url( trailingslashit( $upload_dir['baseurl'] ) . self::UPLOAD_SUBDIR, PHP_URL_PATH );
		if ( ! $protected_path || false === strpos( $request_uri, $protected_path ) ) {
			return;
		}

		wp_die( esc_html__( 'Direct access to this file is not allowed.', 'avenia-digital-products' ), esc_html__( 'Forbidden', 'avenia-digital-products' ), array( 'response' => 403 ) );
	}

	/**
	 * Whether the current upload request is for a product download file.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_protected_upload_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Flag is set by the authenticated product file uploader; WordPress media upload nonce is verified separately.
		if ( empty( $_REQUEST['aveniadp_protected_upload'] ) ) {
			return false;
		}

		$is_protected = '1' === sanitize_text_field( wp_unslash( $_REQUEST['aveniadp_protected_upload'] ) );
		// phpcs:enable
		return $is_protected;
	}

	/**
	 * Public URL path (pathname) for the protected uploads directory.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_url_path() {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['baseurl'] ) ) {
			return '/wp-content/uploads/' . self::UPLOAD_SUBDIR;
		}

		$path = wp_parse_url( trailingslashit( $upload_dir['baseurl'] ) . self::UPLOAD_SUBDIR, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? untrailingslashit( $path ) : '/wp-content/uploads/' . self::UPLOAD_SUBDIR;
	}

	/**
	 * Nginx location snippet that denies direct URL access.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_nginx_deny_snippet() {
		$path = self::get_url_path();

		return "location ^~ {$path}/ {\n    deny all;\n    return 403;\n}";
	}

	/**
	 * Whether .htaccess in a directory contains directory-wide deny rules.
	 *
	 * Ignores Files / FilesMatch blocks that only restrict certain extensions
	 * (for example host "directory hardening" that only blocks .php).
	 *
	 * @param string $directory Directory path.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function directory_has_deny_htaccess( $directory ) {
		$htaccess_file = trailingslashit( $directory ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) || ! is_readable( $htaccess_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local security file read.
		$contents = file_get_contents( $htaccess_file );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return false;
		}

		if ( false !== strpos( $contents, '# BEGIN Avenia Digital Products' ) ) {
			return true;
		}

		$stripped = preg_replace( '/<FilesMatch\b[^>]*>.*?<\/FilesMatch>/is', '', $contents );
		$stripped = is_string( $stripped ) ? preg_replace( '/<Files\b[^>]*>.*?<\/Files>/is', '', $stripped ) : '';
		if ( ! is_string( $stripped ) ) {
			return false;
		}

		return (bool) preg_match( '/^\s*Require\s+all\s+denied\s*$/mi', $stripped )
			|| (bool) preg_match( '/^\s*Deny\s+from\s+all\s*$/mi', $stripped );
	}

	/**
	 * Probe whether a file under the protected directory is reachable by HTTP.
	 *
	 * @return array{
	 *     status: string,
	 *     code: int,
	 *     message: string
	 * } status is one of blocked, exposed, unknown.
	 * @since 1.0.0
	 */
	public static function probe_direct_access() {
		$directory = self::ensure_directory();
		if ( ! $directory ) {
			return array(
				'status'  => 'unknown',
				'code'    => 0,
				'message' => __( 'Could not create the protected uploads directory.', 'avenia-digital-products' ),
			);
		}

		$probe_name = 'aveniadp-access-probe-' . wp_generate_password( 12, false, false ) . '.txt';
		$probe_path = trailingslashit( $directory ) . $probe_name;
		$probe_body = 'aveniadp-probe-' . wp_generate_password( 16, false, false );

		if ( false === file_put_contents( $probe_path, $probe_body ) ) {
			return array(
				'status'  => 'unknown',
				'code'    => 0,
				'message' => __( 'Could not write a probe file to test direct URL access.', 'avenia-digital-products' ),
			);
		}

		$upload_dir = wp_upload_dir();
		$probe_url  = trailingslashit( $upload_dir['baseurl'] ) . self::UPLOAD_SUBDIR . '/' . $probe_name;
		$response   = wp_remote_get(
			$probe_url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
			)
		);

		wp_delete_file( $probe_path );

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'unknown',
				'code'    => 0,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Could not reach the probe URL (%s). Check the protected folder manually in a private browser window.', 'avenia-digital-products' ),
					$response->get_error_message()
				),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 && false !== strpos( $body, $probe_body ) ) {
			return array(
				'status'  => 'exposed',
				'code'    => $code,
				'message' => __( 'Direct URL access is not blocked. On Nginx (and hosts that serve static files through Nginx), .htaccess is ignored — add the Nginx deny location below, or ask your host to block this folder.', 'avenia-digital-products' ),
			);
		}

		if ( in_array( $code, array( 401, 403, 404 ), true ) || $code >= 400 ) {
			return array(
				'status'  => 'blocked',
				'code'    => $code,
				'message' => __( 'Direct URL access appears blocked.', 'avenia-digital-products' ),
			);
		}

		return array(
			'status'  => 'unknown',
			'code'    => $code,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Unexpected probe response (HTTP %d). Verify a protected file URL returns 403 in a private browser window.', 'avenia-digital-products' ),
				$code
			),
		);
	}

	/**
	 * Write index.php and .htaccess deny rules to a directory.
	 *
	 * @param string $directory Directory path.
	 * @since 1.0.0
	 */
	private static function write_security_files( $directory ) {
		$directory = trailingslashit( $directory );

		$index_file = $directory . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = $directory . '.htaccess';
		$block         = self::get_deny_htaccess_block();

		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, $block );
			return;
		}

		if ( self::directory_has_deny_htaccess( $directory ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local security file read.
		$existing = file_get_contents( $htaccess_file );
		$existing = is_string( $existing ) ? rtrim( $existing ) : '';
		$combined = ( '' === $existing ? '' : $existing . "\n\n" ) . $block;
		file_put_contents( $htaccess_file, $combined );
	}

	/**
	 * Apache deny-all rules wrapped in markers for safe merge/update.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function get_deny_htaccess_block() {
		$rules  = "# BEGIN Avenia Digital Products\n";
		$rules .= "Options -Indexes\n";
		$rules .= "<IfModule mod_authz_core.c>\n";
		$rules .= "Require all denied\n";
		$rules .= "</IfModule>\n";
		$rules .= "<IfModule !mod_authz_core.c>\n";
		$rules .= "Order allow,deny\n";
		$rules .= "Deny from all\n";
		$rules .= "</IfModule>\n";
		$rules .= "# END Avenia Digital Products\n";

		return $rules;
	}

	/**
	 * Move a file, falling back to copy + delete.
	 *
	 * @param string $source      Source path.
	 * @param string $destination Destination path.
	 * @return bool
	 * @since 1.0.0
	 */
	private function move_file( $source, $destination ) {
		if ( @rename( $source, $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic move on local disk; WP_Filesystem is not used for protected upload temp files.
			return true;
		}

		if ( ! @copy( $source, $destination ) ) {
			return false;
		}

		wp_delete_file( $source );

		return file_exists( $destination );
	}

	/**
	 * Remove generated attachment sizes for relocated files.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $original_path Original absolute file path.
	 * @since 1.0.0
	 */
	private function delete_attachment_derivatives( $attachment_id, $original_path ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return;
		}

		$base_dir = trailingslashit( dirname( $original_path ) );
		foreach ( $metadata['sizes'] as $size ) {
			if ( empty( $size['file'] ) ) {
				continue;
			}

			$derivative = $base_dir . $size['file'];
			if ( file_exists( $derivative ) ) {
				wp_delete_file( $derivative );
			}
		}
	}

	/**
	 * Get a file path relative to the uploads basedir.
	 *
	 * @param string $file_path Absolute file path.
	 * @return string
	 * @since 1.0.0
	 */
	private function get_upload_relative_path( $file_path ) {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return '';
		}

		$basedir         = trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );
		$normalized_file = wp_normalize_path( $file_path );

		if ( 0 !== strpos( $normalized_file, $basedir ) ) {
			return '';
		}

		return ltrim( substr( $normalized_file, strlen( $basedir ) ), '/' );
	}
}
