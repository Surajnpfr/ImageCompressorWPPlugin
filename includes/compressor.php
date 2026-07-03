<?php
/**
 * Image compression engine with Imagick and GD backends.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compresses images using Imagick (preferred) or GD (fallback).
 */
final class IC_Compressor {

	private const MAX_SCALE_ITERATIONS  = 12;
	private const MAX_QUALITY_ITERATIONS = 10;
	private const MIN_SCALE             = 0.05;

	/**
	 * Compress an image based on mode.
	 *
	 * @param array  $file_data Validated upload data from IC_Upload.
	 * @param string $mode      'max_size' or 'percentage'.
	 * @param array  $options   Mode-specific options.
	 * @return array|WP_Error   ['path' => string, 'format' => string]
	 */
	public static function compress( array $file_data, string $mode, array $options = array() ) {
		$backend = self::get_backend();
		if ( is_wp_error( $backend ) ) {
			return $backend;
		}

		if ( 'percentage' === $mode ) {
			return self::compress_by_percentage( $file_data, $backend, $options );
		}

		return self::compress_by_max_size( $file_data, $backend, $options );
	}

	/**
	 * Determine available image backend.
	 *
	 * @return string|WP_Error 'imagick' or 'gd'
	 */
	private static function get_backend() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			return 'imagick';
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			return 'gd';
		}

		return new WP_Error(
			'ic_no_backend',
			__( 'Image processing is not available on this server.', 'image-compressor' )
		);
	}

	/**
	 * Mode 2: single-pass quality reduction.
	 */
	private static function compress_by_percentage( array $file_data, string $backend, array $options ) {
		$quality = isset( $options['quality'] ) ? (int) $options['quality'] : 75;
		$quality = max( 0, min( 100, $quality ) );

		$output_path = IC_Upload::create_temp_path( $file_data['format'] === 'jpeg' ? 'jpg' : $file_data['format'] );
		$result      = self::encode_image(
			$backend,
			$file_data['path'],
			$output_path,
			$file_data['format'],
			$quality,
			1.0,
			$file_data['width'],
			$file_data['height']
		);

		if ( is_wp_error( $result ) ) {
			IC_Upload::unlink_silent( $output_path );
			return $result;
		}

		return array(
			'path'   => $output_path,
			'format' => $file_data['format'],
		);
	}

	/**
	 * Mode 1: iterative compression until target size is reached.
	 */
	private static function compress_by_max_size( array $file_data, string $backend, array $options ) {
		$target_bytes = self::parse_target_bytes( $options );
		if ( is_wp_error( $target_bytes ) ) {
			return $target_bytes;
		}

		if ( $target_bytes >= $file_data['original_size'] ) {
			return new WP_Error(
				'ic_target_too_large',
				__( 'Target size must be smaller than the original file.', 'image-compressor' )
			);
		}

		$ext    = 'jpeg' === $file_data['format'] ? 'jpg' : $file_data['format'];
		$format = $file_data['format'];

		$estimated_scale = min(
			1.0,
			sqrt( $target_bytes / max( 1, $file_data['original_size'] ) ) * 0.88
		);
		$estimated_scale = max( self::MIN_SCALE, $estimated_scale );

		$best       = null;
		$best_scale = $estimated_scale;

		for ( $i = 0; $i < self::MAX_SCALE_ITERATIONS; $i++ ) {
			$candidate = self::encode_best_under_target(
				$file_data,
				$backend,
				$target_bytes,
				$estimated_scale,
				$format,
				$ext
			);

			if ( null === $candidate ) {
				$estimated_scale *= 0.72;
				if ( $estimated_scale < self::MIN_SCALE ) {
					break;
				}
				continue;
			}

			if ( $candidate['size'] <= $target_bytes ) {
				$best       = self::keep_closer_match( $best, $candidate, $target_bytes );
				$best_scale = $estimated_scale;
				$estimated_scale = min( 1.0, $estimated_scale * 1.12 );

				if ( $estimated_scale >= 1.0 || abs( $target_bytes - $candidate['size'] ) <= max( 512, (int) ( $target_bytes * 0.03 ) ) ) {
					break;
				}
			} else {
				$estimated_scale *= 0.78;
			}

			if ( $estimated_scale < self::MIN_SCALE ) {
				break;
			}
		}

		if ( $best ) {
			$refined = self::refine_scale_to_target(
				$file_data,
				$backend,
				$target_bytes,
				$best_scale,
				$format,
				$ext,
				$best
			);
			if ( $refined ) {
				$best = $refined;
			}
		}

		if ( $best && $best['size'] <= $target_bytes ) {
			return array(
				'path'   => $best['path'],
				'format' => $format,
			);
		}

		if ( $best ) {
			IC_Upload::unlink_silent( $best['path'] );
		}

		return new WP_Error(
			'ic_target_unreachable',
			__( 'Unable to compress this image to the target size. Try a larger target or use Percentage mode.', 'image-compressor' )
		);
	}

	/**
	 * Binary-search the highest quality that stays at or under the target for a given scale.
	 *
	 * @return array{path:string,size:int}|null
	 */
	private static function encode_best_under_target(
		array $file_data,
		string $backend,
		int $target_bytes,
		float $scale,
		string $format,
		string $ext
	): ?array {
		$width  = max( 1, (int) round( $file_data['width'] * $scale ) );
		$height = max( 1, (int) round( $file_data['height'] * $scale ) );

		$low_q = 1;
		$high_q = 95;
		$best   = null;

		for ( $i = 0; $i < self::MAX_QUALITY_ITERATIONS; $i++ ) {
			if ( $low_q > $high_q ) {
				break;
			}

			$quality     = (int) round( ( $low_q + $high_q ) / 2 );
			$output_path = IC_Upload::create_temp_path( $ext );

			$result = self::encode_image(
				$backend,
				$file_data['path'],
				$output_path,
				$format,
				$quality,
				$scale,
				$width,
				$height
			);

			if ( is_wp_error( $result ) ) {
				IC_Upload::unlink_silent( $output_path );
				$high_q = $quality - 1;
				continue;
			}

			$size = filesize( $output_path );
			if ( false === $size ) {
				IC_Upload::unlink_silent( $output_path );
				$high_q = $quality - 1;
				continue;
			}

			if ( $size <= $target_bytes ) {
				$best = self::keep_closer_match(
					$best,
					array(
						'path' => $output_path,
						'size' => $size,
					),
					$target_bytes
				);
				$low_q = $quality + 1;
			} else {
				IC_Upload::unlink_silent( $output_path );
				$high_q = $quality - 1;
			}
		}

		return $best;
	}

	/**
	 * Increase scale while staying under target to get as close as possible.
	 *
	 * @param array{path:string,size:int}|null $current_best Current best match.
	 * @return array{path:string,size:int}|null
	 */
	private static function refine_scale_to_target(
		array $file_data,
		string $backend,
		int $target_bytes,
		float $best_scale,
		string $format,
		string $ext,
		?array $current_best
	): ?array {
		$low_scale  = $best_scale;
		$high_scale = 1.0;
		$best       = $current_best;

		for ( $i = 0; $i < 8; $i++ ) {
			if ( ( $high_scale - $low_scale ) < 0.02 ) {
				break;
			}

			$mid_scale = ( $low_scale + $high_scale ) / 2;
			$candidate = self::encode_best_under_target(
				$file_data,
				$backend,
				$target_bytes,
				$mid_scale,
				$format,
				$ext
			);

			if ( null === $candidate ) {
				$high_scale = $mid_scale;
				continue;
			}

			if ( $candidate['size'] <= $target_bytes ) {
				$best = self::keep_closer_match( $best, $candidate, $target_bytes );
				$low_scale = $mid_scale;
			} else {
				IC_Upload::unlink_silent( $candidate['path'] );
				$high_scale = $mid_scale;
			}
		}

		return $best;
	}

	/**
	 * Keep the candidate closest to target without exceeding it.
	 *
	 * @param array{path:string,size:int}|null $current Current best.
	 * @param array{path:string,size:int}      $candidate New candidate.
	 * @return array{path:string,size:int}
	 */
	private static function keep_closer_match( ?array $current, array $candidate, int $target_bytes ): array {
		if ( null === $current ) {
			return $candidate;
		}

		if ( $candidate['size'] > $target_bytes ) {
			IC_Upload::unlink_silent( $candidate['path'] );
			return $current;
		}

		if ( $current['size'] > $target_bytes || $candidate['size'] > $current['size'] ) {
			IC_Upload::unlink_silent( $current['path'] );
			return $candidate;
		}

		IC_Upload::unlink_silent( $candidate['path'] );
		return $current;
	}

	/**
	 * Parse target size from options into bytes.
	 *
	 * @return int|WP_Error
	 */
	private static function parse_target_bytes( array $options ) {
		$value = isset( $options['target_value'] ) ? (float) $options['target_value'] : 0;
		$unit  = isset( $options['target_unit'] ) ? strtolower( sanitize_text_field( $options['target_unit'] ) ) : 'kb';

		if ( $value <= 0 ) {
			return new WP_Error( 'ic_invalid_target', __( 'Please enter a valid target size.', 'image-compressor' ) );
		}

		$bytes = ( 'mb' === $unit ) ? (int) round( $value * 1024 * 1024 ) : (int) round( $value * 1024 );

		if ( $bytes < 1024 ) {
			return new WP_Error( 'ic_target_too_small', __( 'Target size must be at least 1 KB.', 'image-compressor' ) );
		}

		return $bytes;
	}

	/**
	 * Encode image with the selected backend.
	 *
	 * @return true|WP_Error
	 */
	private static function encode_image(
		string $backend,
		string $source_path,
		string $output_path,
		string $format,
		int $quality,
		float $scale,
		int $width,
		int $height
	) {
		if ( 'imagick' === $backend ) {
			return self::encode_with_imagick( $source_path, $output_path, $format, $quality, $width, $height );
		}

		return self::encode_with_gd( $source_path, $output_path, $format, $quality, $width, $height );
	}

	/**
	 * Imagick encoding.
	 *
	 * @return true|WP_Error
	 */
	private static function encode_with_imagick(
		string $source_path,
		string $output_path,
		string $format,
		int $quality,
		int $width,
		int $height
	) {
		try {
			$image = new Imagick( $source_path );

			if ( $width < $image->getImageWidth() || $height < $image->getImageHeight() ) {
				$image->resizeImage( $width, $height, Imagick::FILTER_LANCZOS, 1 );
			}

			$image->stripImage();

			switch ( $format ) {
				case 'jpeg':
					$image->setImageFormat( 'jpeg' );
					$image->setImageCompressionQuality( $quality );
					break;
				case 'png':
					$image->setImageFormat( 'png' );
					$image->setImageCompressionQuality( max( 0, min( 9, (int) round( ( 100 - $quality ) / 11 ) ) ) );
					break;
				case 'webp':
					$image->setImageFormat( 'webp' );
					$image->setImageCompressionQuality( $quality );
					break;
			}

			$image->writeImage( $output_path );
			$image->clear();
			$image->destroy();

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'ic_imagick_error', __( 'Image compression failed.', 'image-compressor' ) );
		}
	}

	/**
	 * GD encoding.
	 *
	 * @return true|WP_Error
	 */
	private static function encode_with_gd(
		string $source_path,
		string $output_path,
		string $format,
		int $quality,
		int $width,
		int $height
	) {
		$source = self::gd_load_image( $source_path, $format );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$src_w = imagesx( $source );
		$src_h = imagesy( $source );

		$dest = imagecreatetruecolor( $width, $height );
		if ( false === $dest ) {
			imagedestroy( $source );
			return new WP_Error( 'ic_gd_error', __( 'Image compression failed.', 'image-compressor' ) );
		}

		if ( 'png' === $format || 'webp' === $format ) {
			imagealphablending( $dest, false );
			imagesavealpha( $dest, true );
			$transparent = imagecolorallocatealpha( $dest, 0, 0, 0, 127 );
			imagefilledrectangle( $dest, 0, 0, $width, $height, $transparent );
		}

		imagecopyresampled( $dest, $source, 0, 0, 0, 0, $width, $height, $src_w, $src_h );
		imagedestroy( $source );

		$saved = false;

		switch ( $format ) {
			case 'jpeg':
				$saved = imagejpeg( $dest, $output_path, $quality );
				break;
			case 'png':
				$png_quality = max( 0, min( 9, (int) round( ( 100 - $quality ) / 11 ) ) );
				$saved       = imagepng( $dest, $output_path, $png_quality );
				break;
			case 'webp':
				if ( ! function_exists( 'imagewebp' ) ) {
					imagedestroy( $dest );
					return new WP_Error( 'ic_webp_unsupported', __( 'WebP is not supported on this server.', 'image-compressor' ) );
				}
				$saved = imagewebp( $dest, $output_path, $quality );
				break;
		}

		imagedestroy( $dest );

		if ( ! $saved ) {
			return new WP_Error( 'ic_gd_save_error', __( 'Failed to save compressed image.', 'image-compressor' ) );
		}

		return true;
	}

	/**
	 * Load image resource with GD.
	 *
	 * @return resource|GdImage|WP_Error
	 */
	private static function gd_load_image( string $path, string $format ) {
		switch ( $format ) {
			case 'jpeg':
				$img = @imagecreatefromjpeg( $path );
				break;
			case 'png':
				$img = @imagecreatefrompng( $path );
				break;
			case 'webp':
				if ( ! function_exists( 'imagecreatefromwebp' ) ) {
					return new WP_Error( 'ic_webp_unsupported', __( 'WebP is not supported on this server.', 'image-compressor' ) );
				}
				$img = @imagecreatefromwebp( $path );
				break;
			default:
				return new WP_Error( 'ic_invalid_format', __( 'Unsupported image format.', 'image-compressor' ) );
		}

		if ( false === $img ) {
			return new WP_Error( 'ic_gd_load_error', __( 'Failed to load image.', 'image-compressor' ) );
		}

		return $img;
	}
}
