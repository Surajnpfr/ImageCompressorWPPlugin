<?php
/**
 * Upload validation and temporary file storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles secure image upload validation.
 */
final class IC_Upload {

	/** @var string[] */
	private const ALLOWED_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp' );

	/** @var string[] */
	private const ALLOWED_MIMES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

	/**
	 * Validate uploaded file and store in system temp directory.
	 *
	 * @param array $file $_FILES['image'] entry.
	 * @return array|WP_Error
	 */
	public static function validate_and_store( array $file ) {
		if ( empty( $file ) || ! isset( $file['error'] ) ) {
			return new WP_Error( 'ic_no_file', __( 'No image was uploaded.', 'image-compressor' ) );
		}

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'ic_upload_error', __( 'Upload failed. Please try again.', 'image-compressor' ) );
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'ic_invalid_upload', __( 'Invalid upload.', 'image-compressor' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > IC_MAX_BYTES ) {
			return new WP_Error( 'ic_file_too_large', __( 'File exceeds the 20 MB limit.', 'image-compressor' ) );
		}

		$original_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : 'image';
		$ext             = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
			return new WP_Error( 'ic_invalid_extension', __( 'Only JPG, JPEG, PNG, and WebP images are allowed.', 'image-compressor' ) );
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return new WP_Error( 'ic_mime_check', __( 'Unable to validate file type.', 'image-compressor' ) );
		}

		$mime = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime, self::ALLOWED_MIMES, true ) ) {
			return new WP_Error( 'ic_invalid_mime', __( 'Invalid image file type.', 'image-compressor' ) );
		}

		$format = self::mime_to_format( $mime );
		if ( null === $format ) {
			return new WP_Error( 'ic_invalid_format', __( 'Unsupported image format.', 'image-compressor' ) );
		}

		$temp_path = self::create_temp_path( $ext );
		if ( ! move_uploaded_file( $file['tmp_name'], $temp_path ) ) {
			return new WP_Error( 'ic_move_failed', __( 'Failed to process upload.', 'image-compressor' ) );
		}

		$image_info = @getimagesize( $temp_path );
		if ( false === $image_info ) {
			self::unlink_silent( $temp_path );
			return new WP_Error( 'ic_not_image', __( 'The uploaded file is not a valid image.', 'image-compressor' ) );
		}

		return array(
			'path'          => $temp_path,
			'mime'          => $mime,
			'format'        => $format,
			'original_size' => $size,
			'original_name' => $original_name,
			'width'         => (int) $image_info[0],
			'height'        => (int) $image_info[1],
		);
	}

	/**
	 * Create a unique temp file path.
	 */
	public static function create_temp_path( string $ext ): string {
		$ext = preg_replace( '/[^a-z0-9]/', '', strtolower( $ext ) );
		return trailingslashit( sys_get_temp_dir() ) . 'ic_' . wp_generate_password( 32, false ) . '.' . $ext;
	}

	/**
	 * Map MIME type to internal format key.
	 */
	private static function mime_to_format( string $mime ): ?string {
		switch ( $mime ) {
			case 'image/jpeg':
				return 'jpeg';
			case 'image/png':
				return 'png';
			case 'image/webp':
				return 'webp';
			default:
				return null;
		}
	}

	/**
	 * Safely delete a temp file.
	 */
	public static function unlink_silent( ?string $path ): void {
		if ( $path && file_exists( $path ) ) {
			@unlink( $path );
		}
	}
}
