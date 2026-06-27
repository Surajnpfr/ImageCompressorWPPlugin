<?php
/**
 * Standalone compressor page at /compressor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ic_page_title = __( 'Image Compressor', 'image-compressor' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="index, follow">
	<title><?php echo esc_html( $ic_page_title ); ?> — <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="ic-compressor-page">
	<main class="ic-compressor" id="ic-compressor">
		<div class="ic-compressor__card">
			<header class="ic-compressor__header">
				<h1 class="ic-compressor__title"><?php echo esc_html( $ic_page_title ); ?></h1>
				<p class="ic-compressor__subtitle"><?php esc_html_e( 'Compress images instantly. Nothing is stored.', 'image-compressor' ); ?></p>
			</header>

			<div
				class="ic-compressor__dropzone"
				id="ic-dropzone"
				role="button"
				tabindex="0"
				aria-label="<?php esc_attr_e( 'Drop image here or click to browse', 'image-compressor' ); ?>"
			>
				<input
					type="file"
					id="ic-file-input"
					class="ic-compressor__file-input"
					accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
					aria-hidden="true"
				/>
				<div class="ic-compressor__dropzone-icon" aria-hidden="true">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
					</svg>
				</div>
				<p class="ic-compressor__dropzone-text"><?php esc_html_e( 'Drag & drop an image here, or click to browse', 'image-compressor' ); ?></p>
				<p class="ic-compressor__dropzone-hint"><?php esc_html_e( 'JPG, JPEG, PNG, WebP — max 20 MB', 'image-compressor' ); ?></p>
			</div>

			<div class="ic-compressor__file-info" id="ic-file-info" hidden>
				<span class="ic-compressor__file-name" id="ic-file-name"></span>
				<span class="ic-compressor__file-size" id="ic-file-size"></span>
			</div>

			<fieldset class="ic-compressor__modes">
				<legend class="ic-compressor__legend"><?php esc_html_e( 'Compression mode', 'image-compressor' ); ?></legend>
				<label class="ic-compressor__radio">
					<input type="radio" name="ic_mode" value="max_size" checked />
					<span><?php esc_html_e( 'Max Size', 'image-compressor' ); ?></span>
				</label>
				<label class="ic-compressor__radio">
					<input type="radio" name="ic_mode" value="percentage" />
					<span><?php esc_html_e( 'Percentage', 'image-compressor' ); ?></span>
				</label>
			</fieldset>

			<div class="ic-compressor__panel" id="ic-panel-max-size">
				<label class="ic-compressor__label" for="ic-target-value">
					<?php esc_html_e( 'Target size', 'image-compressor' ); ?>
				</label>
				<div class="ic-compressor__input-group">
					<input
						type="number"
						id="ic-target-value"
						class="ic-compressor__input"
						min="1"
						step="1"
						value="500"
						inputmode="numeric"
					/>
					<select id="ic-target-unit" class="ic-compressor__select" aria-label="<?php esc_attr_e( 'Size unit', 'image-compressor' ); ?>">
						<option value="kb"><?php esc_html_e( 'KB', 'image-compressor' ); ?></option>
						<option value="mb"><?php esc_html_e( 'MB', 'image-compressor' ); ?></option>
					</select>
				</div>
			</div>

			<div class="ic-compressor__panel" id="ic-panel-percentage" hidden>
				<label class="ic-compressor__label" for="ic-quality">
					<?php esc_html_e( 'Quality', 'image-compressor' ); ?>
					<span class="ic-compressor__quality-value" id="ic-quality-value">75%</span>
				</label>
				<input
					type="range"
					id="ic-quality"
					class="ic-compressor__slider"
					min="0"
					max="100"
					value="75"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="75"
				/>
			</div>

			<div class="ic-compressor__progress" id="ic-progress" hidden>
				<div class="ic-compressor__progress-bar" id="ic-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
				<p class="ic-compressor__progress-text" id="ic-progress-text"><?php esc_html_e( 'Compressing…', 'image-compressor' ); ?></p>
			</div>

			<div class="ic-compressor__alert ic-compressor__alert--error" id="ic-error" role="alert" aria-live="assertive" hidden></div>

			<button type="button" class="ic-compressor__btn" id="ic-compress-btn" disabled>
				<?php esc_html_e( 'Compress', 'image-compressor' ); ?>
			</button>

			<div class="ic-compressor__results" id="ic-results" aria-live="polite" hidden>
				<h2 class="ic-compressor__results-title"><?php esc_html_e( 'Result', 'image-compressor' ); ?></h2>
				<dl class="ic-compressor__stats">
					<div class="ic-compressor__stat">
						<dt><?php esc_html_e( 'Original', 'image-compressor' ); ?></dt>
						<dd id="ic-original-size">—</dd>
					</div>
					<div class="ic-compressor__stat">
						<dt><?php esc_html_e( 'Compressed', 'image-compressor' ); ?></dt>
						<dd id="ic-compressed-size">—</dd>
					</div>
					<div class="ic-compressor__stat ic-compressor__stat--highlight">
						<dt><?php esc_html_e( 'Saved', 'image-compressor' ); ?></dt>
						<dd id="ic-saved-percent">—</dd>
					</div>
				</dl>
				<button type="button" class="ic-compressor__btn ic-compressor__btn--download" id="ic-download-btn">
					<?php esc_html_e( 'Download', 'image-compressor' ); ?>
				</button>
			</div>
		</div>

		<footer class="ic-compressor__footer">
			<p><?php esc_html_e( 'Images are processed in memory and deleted immediately. Nothing is stored.', 'image-compressor' ); ?></p>
		</footer>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
