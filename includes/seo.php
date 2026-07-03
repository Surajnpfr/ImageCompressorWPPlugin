<?php
/**
 * SEO controls for the /compressor utility route.
 *
 * @package Image_Compressor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compressor page SEO (noindex utility, structured data for AI).
 */
final class IC_SEO {

	/**
	 * Bootstrap SEO hooks.
	 */
	public static function init(): void {
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_head_meta' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_json_ld' ), 20 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );

		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'rank_math_robots' ) );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'rank_math_canonical' ) );
		add_filter( 'wpseo_robots', array( __CLASS__, 'yoast_robots' ) );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'yoast_canonical' ) );
	}

	/**
	 * Whether the current GET request is the compressor UI.
	 */
	private static function is_compressor_get(): bool {
		if ( ! class_exists( 'IC_Router' ) || ! IC_Router::is_compressor_request() ) {
			return false;
		}

		return ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Public compressor URL.
	 */
	public static function get_page_url(): string {
		return home_url( '/compressor' );
	}

	/**
	 * Meta description for the compressor page.
	 */
	public static function get_description(): string {
		return __(
			'Free online image compressor. Reduce JPG, PNG, and WebP file size by target KB or percentage. Private, fast, nothing stored.',
			'image-compressor'
		);
	}

	/**
	 * noindex utility tool; allow follow for crawl path discovery.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function filter_robots( array $robots ): array {
		if ( ! self::is_compressor_get() ) {
			return $robots;
		}

		$robots['noindex'] = true;
		$robots['follow']  = true;

		return $robots;
	}

	/**
	 * Document title for compressor route.
	 *
	 * @param string $title Current title.
	 */
	public static function filter_document_title( string $title ): string {
		if ( ! self::is_compressor_get() ) {
			return $title;
		}

		return __( 'Image Compressor', 'image-compressor' ) . ' — ' . get_bloginfo( 'name' );
	}

	/**
	 * Meta, canonical, and social tags for the compressor page.
	 */
	public static function output_head_meta(): void {
		if ( ! self::is_compressor_get() ) {
			return;
		}

		$url         = self::get_page_url();
		$description = self::get_description();
		$title       = __( 'Image Compressor', 'image-compressor' ) . ' — ' . get_bloginfo( 'name' );

		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );

		printf( '<meta property="og:type" content="website">' . "\n" );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $title ) ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );

		printf( '<meta name="twitter:card" content="summary">' . "\n" );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $title ) ) );
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
	}

	/**
	 * WebApplication schema for AI/search discoverability without indexing UI.
	 */
	public static function output_json_ld(): void {
		if ( ! self::is_compressor_get() ) {
			return;
		}

		$payload = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'WebApplication',
			'name'        => __( 'Image Compressor', 'image-compressor' ),
			'description' => self::get_description(),
			'url'         => self::get_page_url(),
			'applicationCategory' => 'MultimediaApplication',
			'operatingSystem'     => 'Web',
			'offers'              => array(
				'@type' => 'Offer',
				'price' => '0',
				'priceCurrency' => 'USD',
			),
			'provider' => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Discourage indexing compressor POST endpoint via robots.txt.
	 *
	 * @param string $output Robots.txt output.
	 * @param bool   $public Whether site is public.
	 */
	public static function robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$output .= "\n# Privacy Image Compressor\n";
		$output .= "Disallow: /compressor\n";

		return $output;
	}

	/**
	 * Rank Math robots override on compressor route.
	 *
	 * @param array|string $robots Robots value.
	 * @return array|string
	 */
	public static function rank_math_robots( $robots ) {
		if ( ! self::is_compressor_get() ) {
			return $robots;
		}

		return 'noindex, follow';
	}

	/**
	 * Rank Math canonical override.
	 *
	 * @param string $canonical Canonical URL.
	 */
	public static function rank_math_canonical( string $canonical ): string {
		if ( ! self::is_compressor_get() ) {
			return $canonical;
		}

		return self::get_page_url();
	}

	/**
	 * Yoast robots override.
	 *
	 * @param string $robots Robots string.
	 */
	public static function yoast_robots( string $robots ): string {
		if ( ! self::is_compressor_get() ) {
			return $robots;
		}

		return 'noindex, follow';
	}

	/**
	 * Yoast canonical override.
	 *
	 * @param string $canonical Canonical URL.
	 */
	public static function yoast_canonical( string $canonical ): string {
		if ( ! self::is_compressor_get() ) {
			return $canonical;
		}

		return self::get_page_url();
	}

	/**
	 * Send noindex header for compression POST responses.
	 */
	public static function send_post_robots_header(): void {
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}
}
