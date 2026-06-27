<?php
/**
 * Plugin Name:       Privacy Image Compressor
 * Description:       Privacy-first image compression at /compressor. No storage, no tracking.
 * Version:           1.1.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Hamro Niti
 * Text Domain:       image-compressor
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IC_PLUGIN_VERSION', '1.1.3' );
define( 'IC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IC_MAX_BYTES', 20 * 1024 * 1024 );

require_once IC_PLUGIN_DIR . 'includes/upload.php';
require_once IC_PLUGIN_DIR . 'includes/compressor.php';
require_once IC_PLUGIN_DIR . 'includes/processor.php';
require_once IC_PLUGIN_DIR . 'includes/router.php';

/**
 * Main plugin bootstrap.
 */
final class IC_Plugin {

	public static function init(): void {
		IC_Router::init();
	}

	/**
	 * Enqueue frontend assets for the compressor route.
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'image-compressor',
			IC_PLUGIN_URL . 'assets/css/compressor.css',
			array(),
			IC_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'image-compressor',
			IC_PLUGIN_URL . 'assets/js/compressor.js',
			array(),
			IC_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'image-compressor',
			'icData',
			array(
				'compressUrl' => home_url( '/compressor' ),
				'nonce'       => wp_create_nonce( 'ic_compress' ),
				'maxBytes' => IC_MAX_BYTES,
				'i18n'     => array(
					'noFile'       => __( 'Please select an image to compress.', 'image-compressor' ),
					'invalidType'  => __( 'Only JPG, JPEG, PNG, and WebP images are allowed.', 'image-compressor' ),
					'fileTooLarge' => __( 'File exceeds the 20 MB limit.', 'image-compressor' ),
					'compressing'  => __( 'Compressing…', 'image-compressor' ),
					'compress'     => __( 'Compress', 'image-compressor' ),
					'networkError' => __( 'Compression failed. Please try again.', 'image-compressor' ),
					'download'     => __( 'Download', 'image-compressor' ),
					'original'     => __( 'Original', 'image-compressor' ),
					'compressed'   => __( 'Compressed', 'image-compressor' ),
					'saved'        => __( 'Saved', 'image-compressor' ),
					'qualityLabel' => __( 'Quality', 'image-compressor' ),
					'dropHint'     => __( 'Drag & drop an image here, or click to browse', 'image-compressor' ),
					'formats'      => __( 'JPG, JPEG, PNG, WebP — max 20 MB', 'image-compressor' ),
				),
			)
		);
	}
}

register_activation_hook( __FILE__, array( 'IC_Router', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IC_Router', 'deactivate' ) );

IC_Plugin::init();
