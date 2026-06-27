<?php
/**
 * AJAX request orchestration and response handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes compression requests.
 */
final class IC_Processor {

	/**
	 * Handle compress request (custom route or legacy admin-ajax).
	 */
	public static function handle_compress(): void {
		if ( ! self::verify_nonce() ) {
			self::send_error( __( 'Security check failed. Please refresh and try again.', 'image-compressor' ), 403 );
		}

		$output_path   = null;
		$original_path = null;

		try {
			if ( empty( $_FILES['image'] ) ) {
				self::send_error( __( 'No image was uploaded.', 'image-compressor' ) );
			}

			$file_data = IC_Upload::validate_and_store( $_FILES['image'] );
			if ( is_wp_error( $file_data ) ) {
				self::send_error( $file_data->get_error_message() );
			}

			$original_path = $file_data['path'];

			$mode    = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
			$options = self::parse_options( $mode );

			if ( is_wp_error( $options ) ) {
				self::send_error( $options->get_error_message() );
			}

			$result = IC_Compressor::compress( $file_data, $mode, $options );
			if ( is_wp_error( $result ) ) {
				self::send_error( $result->get_error_message() );
			}

			$output_path = $result['path'];

			$compressed_size = filesize( $output_path );
			if ( false === $compressed_size ) {
				self::send_error( __( 'Failed to read compressed image.', 'image-compressor' ) );
			}

			$original_size = $file_data['original_size'];
			$saved_percent = $original_size > 0
				? (int) round( ( 1 - ( $compressed_size / $original_size ) ) * 100 )
				: 0;

			$file_contents = file_get_contents( $output_path );
			if ( false === $file_contents ) {
				self::send_error( __( 'Failed to read compressed image.', 'image-compressor' ) );
			}

			$filename = self::build_output_filename( $file_data['original_name'], $result['format'] );

			wp_send_json_success(
				array(
					'original_size'   => $original_size,
					'compressed_size' => $compressed_size,
					'saved_percent'   => max( 0, $saved_percent ),
					'filename'        => $filename,
					'mime'            => $file_data['mime'],
					'data'            => base64_encode( $file_contents ),
				)
			);
		} finally {
			IC_Upload::unlink_silent( $original_path );
			IC_Upload::unlink_silent( $output_path );
		}
	}

	/**
	 * Parse and validate mode-specific POST options.
	 *
	 * @return array|WP_Error
	 */
	private static function parse_options( string $mode ) {
		if ( 'percentage' === $mode ) {
			$quality = isset( $_POST['quality'] ) ? (int) $_POST['quality'] : 75;
			return array(
				'quality' => max( 0, min( 100, $quality ) ),
			);
		}

		if ( 'max_size' === $mode ) {
			$target_value = isset( $_POST['target_value'] ) ? floatval( $_POST['target_value'] ) : 0;
			$target_unit  = isset( $_POST['target_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['target_unit'] ) ) : 'kb';

			return array(
				'target_value' => $target_value,
				'target_unit'  => in_array( $target_unit, array( 'kb', 'mb' ), true ) ? $target_unit : 'kb',
			);
		}

		return new WP_Error( 'ic_invalid_mode', __( 'Invalid compression mode.', 'image-compressor' ) );
	}

	/**
	 * Build download filename preserving original base name.
	 */
	private static function build_output_filename( string $original_name, string $format ): string {
		$base = pathinfo( $original_name, PATHINFO_FILENAME );
		$base = sanitize_file_name( $base );
		if ( '' === $base ) {
			$base = 'compressed-image';
		}

		$ext = ( 'jpeg' === $format ) ? 'jpg' : $format;
		return $base . '-compressed.' . $ext;
	}

	/**
	 * Verify request nonce.
	 */
	private static function verify_nonce(): bool {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'ic_compress' );
	}

	/**
	 * Send JSON error and exit.
	 */
	private static function send_error( string $message, int $status = 400 ): void {
		wp_send_json_error( array( 'message' => $message ), $status );
	}
}
