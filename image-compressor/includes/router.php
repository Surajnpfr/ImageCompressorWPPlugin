<?php
/**
 * Custom /compressor route handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rewrite rule and intercepts /compressor requests.
 */
final class IC_Router {

	public const QUERY_VAR = 'compressor_page';

	private const FLUSH_OPTION = 'ic_rewrite_flush_version';

	/**
	 * Bootstrap routing hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_compressor_request' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'prevent_canonical_redirect' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ), 1 );
	}

	/**
	 * Register /compressor rewrite rule and tag.
	 */
	public static function register_rewrites(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([0-9]+)' );
		add_rewrite_rule(
			'^compressor/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Ensure rewrite rules exist after install or plugin update.
	 */
	public static function maybe_flush_rewrites(): void {
		if ( get_option( self::FLUSH_OPTION ) === IC_PLUGIN_VERSION ) {
			return;
		}

		self::register_rewrites();
		flush_rewrite_rules( false );
		update_option( self::FLUSH_OPTION, IC_PLUGIN_VERSION, false );
	}

	/**
	 * Register custom query variable.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Map /compressor URI to query var when rewrite rules are stale.
	 *
	 * @param WP $wp WordPress environment instance.
	 */
	public static function parse_compressor_request( $wp ): void {
		if ( self::matches_compressor_uri() ) {
			$wp->query_vars[ self::QUERY_VAR ] = '1';
		}
	}

	/**
	 * Prevent WordPress from redirecting /compressor away.
	 *
	 * @param string|false $redirect Canonical redirect URL.
	 * @param string       $request  Requested URL.
	 * @return string|false
	 */
	public static function prevent_canonical_redirect( $redirect, string $request ) {
		if ( self::matches_compressor_uri() ) {
			return false;
		}

		return $redirect;
	}

	/**
	 * Path segment after site home path (e.g. "compressor").
	 */
	public static function get_request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = trim( (string) parse_url( $uri, PHP_URL_PATH ), '/' );

		$home_path = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		} elseif ( $home_path === $path ) {
			$path = '';
		}

		return $path;
	}

	/**
	 * Whether the raw request URI is /compressor.
	 */
	public static function matches_compressor_uri(): bool {
		$path = self::get_request_path();

		return 'compressor' === $path;
	}

	/**
	 * Whether the current request is the compressor route.
	 */
	public static function is_compressor_request(): bool {
		if ( self::matches_compressor_uri() ) {
			return true;
		}

		$value = get_query_var( self::QUERY_VAR, '' );
		return '' !== $value && '0' !== (string) $value;
	}

	/**
	 * Enqueue assets when the compressor route is active.
	 */
	public static function maybe_enqueue_assets(): void {
		if ( self::is_compressor_request() ) {
			IC_Plugin::enqueue_assets();
		}
	}

	/**
	 * Render standalone UI and stop WordPress theme execution.
	 */
	public static function handle_request(): void {
		if ( ! self::is_compressor_request() ) {
			return;
		}

		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}

		if ( self::is_compress_post() ) {
			IC_Processor::handle_compress();
			exit;
		}

		IC_Plugin::enqueue_assets();

		status_header( 200 );
		nocache_headers();

		include IC_PLUGIN_DIR . 'templates/compressor-ui.php';
		exit;
	}

	/**
	 * Whether this is a compression POST to /compressor.
	 */
	private static function is_compress_post(): bool {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}

		return isset( $_POST['ic_action'] ) && 'compress' === sanitize_text_field( wp_unslash( $_POST['ic_action'] ) );
	}

	/**
	 * Flush rewrite rules on activation.
	 */
	public static function activate(): void {
		self::register_rewrites();
		flush_rewrite_rules();
		update_option( self::FLUSH_OPTION, IC_PLUGIN_VERSION, false );
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
		delete_option( self::FLUSH_OPTION );
	}
}
