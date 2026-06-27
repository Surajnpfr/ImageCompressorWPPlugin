/**
 * Privacy Image Compressor — frontend logic.
 */
(function () {
	'use strict';

	if (typeof icData === 'undefined') {
		return;
	}

	var ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];

	var dropzone = document.getElementById('ic-dropzone');
	var fileInput = document.getElementById('ic-file-input');
	var fileInfo = document.getElementById('ic-file-info');
	var fileNameEl = document.getElementById('ic-file-name');
	var fileSizeEl = document.getElementById('ic-file-size');
	var compressBtn = document.getElementById('ic-compress-btn');
	var errorEl = document.getElementById('ic-error');
	var resultsEl = document.getElementById('ic-results');
	var originalSizeEl = document.getElementById('ic-original-size');
	var compressedSizeEl = document.getElementById('ic-compressed-size');
	var savedPercentEl = document.getElementById('ic-saved-percent');
	var downloadBtn = document.getElementById('ic-download-btn');
	var panelMaxSize = document.getElementById('ic-panel-max-size');
	var panelPercentage = document.getElementById('ic-panel-percentage');
	var targetValue = document.getElementById('ic-target-value');
	var targetUnit = document.getElementById('ic-target-unit');
	var qualitySlider = document.getElementById('ic-quality');
	var qualityValue = document.getElementById('ic-quality-value');
	var modeRadios = document.querySelectorAll('input[name="ic_mode"]');
	var progressEl = document.getElementById('ic-progress');
	var progressBar = document.getElementById('ic-progress-bar');
	var progressText = document.getElementById('ic-progress-text');

	var selectedFile = null;
	var downloadUrl = null;
	var downloadFilename = '';

	function formatBytes(bytes) {
		if (bytes === 0) return '0 B';
		var units = ['B', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(1024));
		var value = bytes / Math.pow(1024, i);
		return (i === 0 ? value : value.toFixed(i === 1 ? 0 : 1)) + ' ' + units[i];
	}

	function getExtension(name) {
		var parts = name.split('.');
		return parts.length > 1 ? parts.pop().toLowerCase() : '';
	}

	function showError(message) {
		errorEl.textContent = message;
		errorEl.hidden = false;
	}

	function hideError() {
		errorEl.hidden = true;
		errorEl.textContent = '';
	}

	function hideResults() {
		resultsEl.hidden = true;
		revokeDownloadUrl();
	}

	function showProgress() {
		progressEl.hidden = false;
		progressBar.classList.remove('ic-compressor__progress-bar--done');
		progressBar.setAttribute('aria-valuenow', '0');
		progressText.textContent = icData.i18n.compressing;
	}

	function hideProgress() {
		progressEl.hidden = true;
		progressBar.classList.remove('ic-compressor__progress-bar--done');
		progressBar.setAttribute('aria-valuenow', '0');
	}

	function revokeDownloadUrl() {
		if (downloadUrl) {
			URL.revokeObjectURL(downloadUrl);
			downloadUrl = null;
		}
	}

	function validateFile(file) {
		var ext = getExtension(file.name);
		if (ALLOWED_EXT.indexOf(ext) === -1) {
			return icData.i18n.invalidType;
		}
		if (file.size > icData.maxBytes) {
			return icData.i18n.fileTooLarge;
		}
		return null;
	}

	function setFile(file) {
		var error = validateFile(file);
		if (error) {
			showError(error);
			selectedFile = null;
			compressBtn.disabled = true;
			fileInfo.hidden = true;
			return;
		}

		hideError();
		hideResults();
		selectedFile = file;
		fileNameEl.textContent = file.name;
		fileSizeEl.textContent = formatBytes(file.size);
		fileInfo.hidden = false;
		compressBtn.disabled = false;
	}

	function getSelectedMode() {
		var checked = document.querySelector('input[name="ic_mode"]:checked');
		return checked ? checked.value : 'max_size';
	}

	function toggleModePanels() {
		var mode = getSelectedMode();
		if (mode === 'max_size') {
			panelMaxSize.hidden = false;
			panelPercentage.hidden = true;
		} else {
			panelMaxSize.hidden = true;
			panelPercentage.hidden = false;
		}
		hideResults();
	}

	function base64ToBlob(base64, mime) {
		var binary = atob(base64);
		var len = binary.length;
		var bytes = new Uint8Array(len);
		for (var i = 0; i < len; i++) {
			bytes[i] = binary.charCodeAt(i);
		}
		return new Blob([bytes], { type: mime });
	}

	function handleCompress() {
		if (!selectedFile) {
			showError(icData.i18n.noFile);
			return;
		}

		hideError();
		hideResults();

		var formData = new FormData();
		formData.append('ic_action', 'compress');
		formData.append('nonce', icData.nonce);
		formData.append('image', selectedFile);

		var mode = getSelectedMode();
		formData.append('mode', mode);

		if (mode === 'max_size') {
			formData.append('target_value', targetValue.value);
			formData.append('target_unit', targetUnit.value);
		} else {
			formData.append('quality', qualitySlider.value);
		}

		compressBtn.disabled = true;
		compressBtn.textContent = icData.i18n.compressing;
		showProgress();

		fetch(icData.compressUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json.success) {
					var msg = json.data && json.data.message ? json.data.message : icData.i18n.networkError;
					throw new Error(msg);
				}

				var data = json.data;
				originalSizeEl.textContent = formatBytes(data.original_size);
				compressedSizeEl.textContent = formatBytes(data.compressed_size);
				savedPercentEl.textContent = data.saved_percent + '%';

				revokeDownloadUrl();
				var blob = base64ToBlob(data.data, data.mime);
				downloadUrl = URL.createObjectURL(blob);
				downloadFilename = data.filename;

				progressBar.classList.add('ic-compressor__progress-bar--done');
				progressBar.setAttribute('aria-valuenow', '100');
				resultsEl.hidden = false;
			})
			.catch(function (err) {
				showError(err.message || icData.i18n.networkError);
			})
			.finally(function () {
				hideProgress();
				compressBtn.disabled = !selectedFile;
				compressBtn.textContent = icData.i18n.compress;
			});
	}

	function handleDownload() {
		if (!downloadUrl) return;

		var link = document.createElement('a');
		link.href = downloadUrl;
		link.download = downloadFilename;
		link.style.display = 'none';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	// Dropzone events
	dropzone.addEventListener('click', function () {
		fileInput.click();
	});

	dropzone.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			fileInput.click();
		}
	});

	dropzone.addEventListener('dragover', function (e) {
		e.preventDefault();
		dropzone.classList.add('ic-compressor__dropzone--active');
	});

	dropzone.addEventListener('dragleave', function () {
		dropzone.classList.remove('ic-compressor__dropzone--active');
	});

	dropzone.addEventListener('drop', function (e) {
		e.preventDefault();
		dropzone.classList.remove('ic-compressor__dropzone--active');
		if (e.dataTransfer.files.length > 0) {
			setFile(e.dataTransfer.files[0]);
		}
	});

	fileInput.addEventListener('change', function () {
		if (fileInput.files.length > 0) {
			setFile(fileInput.files[0]);
		}
	});

	modeRadios.forEach(function (radio) {
		radio.addEventListener('change', toggleModePanels);
	});

	qualitySlider.addEventListener('input', function () {
		qualityValue.textContent = qualitySlider.value + '%';
		qualitySlider.setAttribute('aria-valuenow', qualitySlider.value);
	});

	compressBtn.addEventListener('click', handleCompress);
	downloadBtn.addEventListener('click', handleDownload);
})();
