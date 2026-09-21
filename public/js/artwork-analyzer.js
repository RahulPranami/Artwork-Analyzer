/**
 * WP Artwork Analyzer - Public Frontend Script
 */

(function () {
    'use strict';

    const config = window.wpaArtworkAnalyzerConfig || {};
    const i18n = config.i18n || {};

    let currentAbortController = null;
    let accumulatedMarkdown = '';
    let selectedFile = null;

    /**
     * DOM Elements Cache
     */
    let elements = {};

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cacheElements();
        bindFormEvents();
        bindActionButtons();
    }

    function cacheElements() {
        elements = {
            wrapper: document.getElementById('wpa-analyzer-wrapper'),
            statusText: document.querySelector('.wpa-status-text'),
            stopBtn: document.getElementById('wpa-btn-stop'),
            resetBtn: document.getElementById('wpa-btn-reset'),
            retryBtn: document.getElementById('wpa-btn-retry'),
            artworkPreview: document.getElementById('wpa-artwork-preview'),
            filename: document.getElementById('wpa-preview-filename'),
            filesize: document.getElementById('wpa-preview-filesize'),
            streamContent: document.getElementById('wpa-stream-content'),
            errorBox: document.getElementById('wpa-error-container'),
            errorMessage: document.getElementById('wpa-error-message'),
            trackerList: document.querySelectorAll('.wpa-tracker-list li'),
        };
    }

    /**
     * Find targeted CF7 form and bind submit and file change listeners.
     */
    function bindFormEvents() {
        const forms = document.querySelectorAll('form.wpcf7-form');

        forms.forEach(function (form) {
            const formIdInput = form.querySelector('input[name="_wpcf7"]');
            const formId = formIdInput ? parseInt(formIdInput.value, 10) : 0;
            const targetId = config.configuredFormId ? parseInt(config.configuredFormId, 10) : 0;

            // Check if form matches target configuration
            if (targetId > 0 && formId > 0 && formId !== targetId) {
                return;
            }

            const fileInput = form.querySelector('input[type="file"][name="artwork"]') ||
                              form.querySelector('input[type="file"]');

            if (!fileInput) {
                return;
            }

            // Setup camera or file selection controls
            setupMediaInputEnhancements(fileInput, form);

            const submitBtn = form.querySelector('input[type="submit"]') ||
                              form.querySelector('button[type="submit"]') ||
                              form.querySelector('.wpcf7-submit');

            let analysisStarted = false;

            function doStart(e) {
                const file = (fileInput.files && fileInput.files[0]) ? fileInput.files[0] : selectedFile;
                if (!file) {
                    return;
                }

                if (analysisStarted) {
                    return;
                }

                // Check file format
                const validMimes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!validMimes.includes(file.type)) {
                    showError(i18n.invalidFormat || 'Only JPG, PNG, and WEBP files are supported.');
                    return;
                }

                // Check file size
                const maxBytes = (config.maxFileSize || 10) * 1024 * 1024;
                if (file.size > maxBytes) {
                    showError(i18n.fileTooLarge || 'File exceeds maximum allowed size.');
                    return;
                }

                analysisStarted = true;
                setTimeout(function () {
                    analysisStarted = false;
                }, 3000);

                startAnalysis(file, form);
            }

            if (submitBtn) {
                submitBtn.addEventListener('click', doStart);
            }

            form.addEventListener('submit', doStart);

            // Also listen to CF7 custom DOM events
            document.addEventListener('wpcf7beforesubmit', function (evt) {
                if (evt.detail && evt.detail.form === form) {
                    doStart(evt);
                }
            });
        });
    }

    /**
     * Enhanced Media Input: Choose File or Use Camera
     */
    let cameraStream = null;

    function setupMediaInputEnhancements(fileInput, form) {
        if (fileInput.dataset.wpaEnhanced) {
            return;
        }
        fileInput.dataset.wpaEnhanced = 'true';

        fileInput.setAttribute('accept', 'image/jpeg,image/png,image/webp,image/*');

        // Create container for dual source buttons
        const controlsContainer = document.createElement('div');
        controlsContainer.className = 'wpa-source-picker-wrapper';

        // Choose file button
        const btnFile = document.createElement('button');
        btnFile.type = 'button';
        btnFile.className = 'wpa-btn-source wpa-btn-file-select';
        btnFile.innerHTML = '📁 ' + (i18n.chooseFile || 'Choose Image File');

        // Take photo button
        const btnCamera = document.createElement('button');
        btnCamera.type = 'button';
        btnCamera.className = 'wpa-btn-source wpa-btn-camera-trigger';
        btnCamera.innerHTML = '📷 ' + (i18n.takePhoto || 'Take Photo');

        // Hidden input for mobile camera capture
        const mobileCameraInput = document.createElement('input');
        mobileCameraInput.type = 'file';
        mobileCameraInput.accept = 'image/*';
        mobileCameraInput.setAttribute('capture', 'environment');
        mobileCameraInput.style.display = 'none';

        controlsContainer.appendChild(btnFile);
        controlsContainer.appendChild(btnCamera);
        controlsContainer.appendChild(mobileCameraInput);

        // Selected media card (thumbnail + details)
        const selectedBadge = document.createElement('div');
        selectedBadge.className = 'wpa-selected-media-card';
        selectedBadge.style.display = 'none';
        selectedBadge.innerHTML = `
            <div class="wpa-selected-media-thumb">
                <img src="" alt="Artwork Thumbnail" class="wpa-media-thumb-img" />
            </div>
            <div class="wpa-selected-media-details">
                <span class="wpa-selected-media-title"></span>
                <span class="wpa-selected-media-meta"></span>
            </div>
            <button type="button" class="wpa-selected-media-clear" title="${i18n.remove || 'Remove'}">&times;</button>
        `;

        const parent = fileInput.closest('.wpcf7-form-control-wrap') || fileInput.parentElement;
        if (parent) {
            parent.appendChild(controlsContainer);
            parent.appendChild(selectedBadge);
        }

        // Hide native input for a clean UI
        fileInput.style.display = 'none';

        btnFile.addEventListener('click', function (e) {
            e.preventDefault();
            fileInput.click();
        });

        btnCamera.addEventListener('click', function (e) {
            e.preventDefault();
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                             (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

            if (isMobile) {
                mobileCameraInput.click();
            } else if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                openDesktopCameraModal(function (capturedFile) {
                    applyFileToInput(capturedFile, fileInput, selectedBadge);
                });
            } else {
                mobileCameraInput.click();
            }
        });

        mobileCameraInput.addEventListener('change', function (e) {
            if (e.target.files && e.target.files[0]) {
                applyFileToInput(e.target.files[0], fileInput, selectedBadge);
            }
        });

        fileInput.addEventListener('change', function (e) {
            if (e.target.files && e.target.files[0]) {
                applyFileToInput(e.target.files[0], fileInput, selectedBadge);
            }
        });

        const clearBtn = selectedBadge.querySelector('.wpa-selected-media-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                fileInput.value = '';
                mobileCameraInput.value = '';
                selectedFile = null;
                selectedBadge.style.display = 'none';
                if (elements.artworkPreview) {
                    elements.artworkPreview.src = '';
                }
                if (elements.filename) {
                    elements.filename.textContent = '';
                }
                if (elements.filesize) {
                    elements.filesize.textContent = '';
                }
            });
        }
    }

    function applyFileToInput(file, fileInput, selectedBadge) {
        selectedFile = file;

        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
        } catch (err) {
            // DataTransfer fallback is handled by selectedFile in startAnalysis
        }

        if (selectedBadge) {
            const thumbImg = selectedBadge.querySelector('.wpa-media-thumb-img');
            const titleEl = selectedBadge.querySelector('.wpa-selected-media-title');
            const metaEl = selectedBadge.querySelector('.wpa-selected-media-meta');

            if (titleEl) titleEl.textContent = file.name || 'artwork.jpg';
            if (metaEl) metaEl.textContent = formatBytes(file.size);

            const reader = new FileReader();
            reader.onload = function (e) {
                if (thumbImg) thumbImg.src = e.target.result;
                selectedBadge.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }

        handleFileSelection(file);
    }

    function openDesktopCameraModal(onCaptured) {
        let modal = document.getElementById('wpa-camera-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'wpa-camera-modal';
            modal.className = 'wpa-camera-modal';
            modal.innerHTML = `
                <div class="wpa-camera-overlay"></div>
                <div class="wpa-camera-box">
                    <div class="wpa-camera-header">
                        <h4>${i18n.captureArtwork || 'Capture Artwork with Camera'}</h4>
                        <button type="button" class="wpa-camera-close-btn">&times;</button>
                    </div>
                    <div class="wpa-camera-body">
                        <video id="wpa-camera-feed" autoplay playsinline muted></video>
                        <canvas id="wpa-camera-canvas" style="display:none;"></canvas>
                        <div class="wpa-camera-hint">Hold your artwork up to the camera and click Snap</div>
                    </div>
                    <div class="wpa-camera-footer">
                        <button type="button" class="wpa-btn wpa-btn-primary wpa-camera-snap-btn">📸 ${i18n.snapPhoto || 'Snap Photo'}</button>
                        <button type="button" class="wpa-btn wpa-btn-secondary wpa-camera-cancel-btn">${i18n.cancel || 'Cancel'}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            modal.querySelector('.wpa-camera-overlay').addEventListener('click', closeCameraModal);
            modal.querySelector('.wpa-camera-close-btn').addEventListener('click', closeCameraModal);
            modal.querySelector('.wpa-camera-cancel-btn').addEventListener('click', closeCameraModal);
        }

        const video = modal.querySelector('#wpa-camera-feed');
        const snapBtn = modal.querySelector('.wpa-camera-snap-btn');

        modal.style.display = 'flex';

        navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1920 }, height: { ideal: 1080 }, facingMode: 'environment' },
            audio: false
        }).then(function (stream) {
            cameraStream = stream;
            video.srcObject = stream;
            video.play();
        }).catch(function (err) {
            console.warn('Camera access failed:', err);
            closeCameraModal();
            alert(i18n.cameraUnavailable || 'Camera not available or access denied. Please choose a file instead.');
        });

        snapBtn.onclick = function () {
            const canvas = modal.querySelector('#wpa-camera-canvas');
            canvas.width = video.videoWidth || 1280;
            canvas.height = video.videoHeight || 720;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(function (blob) {
                if (blob) {
                    const capturedFile = new File([blob], 'camera-artwork-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    closeCameraModal();
                    if (typeof onCaptured === 'function') {
                        onCaptured(capturedFile);
                    }
                }
            }, 'image/jpeg', 0.92);
        };
    }

    function closeCameraModal() {
        const modal = document.getElementById('wpa-camera-modal');
        if (modal) {
            modal.style.display = 'none';
        }
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            cameraStream = null;
        }
    }

    /**
     * Bind Stop, Reset, and Retry buttons.
     */

    function bindActionButtons() {
        if (elements.stopBtn) {
            elements.stopBtn.addEventListener('click', function () {
                if (currentAbortController) {
                    currentAbortController.abort();
                    setState('cancelled');
                    updateStatus(i18n.aborted || 'Analysis was stopped.');
                }
            });
        }

        if (elements.resetBtn) {
            elements.resetBtn.addEventListener('click', resetAnalyzer);
        }

        if (elements.retryBtn) {
            elements.retryBtn.addEventListener('click', function () {
                if (selectedFile) {
                    const form = document.querySelector('form.wpcf7-form');
                    startAnalysis(selectedFile, form);
                }
            });
        }
    }

    /**
     * File selection & instant image preview.
     */
    function handleFileSelection(file) {
        selectedFile = file;

        if (elements.filename) {
            elements.filename.textContent = file.name;
        }

        if (elements.filesize) {
            elements.filesize.textContent = formatBytes(file.size);
        }

        if (elements.artworkPreview) {
            const reader = new FileReader();
            reader.onload = function (e) {
                elements.artworkPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    /**
     * Start progressive streaming analysis.
     */
    async function startAnalysis(file, form) {
        if (!elements.wrapper) {
            cacheElements();
        }

        handleFileSelection(file);

        // Show wrapper and set state
        elements.wrapper.style.display = 'block';
        setState('uploading');
        updateStatus(i18n.uploading || 'Optimizing & uploading image...');

        // Hide error box
        if (elements.errorBox) {
            elements.errorBox.style.display = 'none';
        }

        // Reset text stream and tracker
        accumulatedMarkdown = '';
        renderMarkdown('');
        resetTracker();

        // Scroll to analyzer
        elements.wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Prepare FormData
        const formData = new FormData();
        formData.append('artwork', file);

        if (form) {
            const emailInput = form.querySelector('input[type="email"]');
            if (emailInput && emailInput.value) {
                formData.append('your-email', emailInput.value);
            }
            const formIdInput = form.querySelector('input[name="_wpcf7"]');
            if (formIdInput) {
                formData.append('form_id', formIdInput.value);
            }
        }

        // Add Cloudflare Turnstile token if enabled
        if (config.turnstileEnabled && window.turnstile) {
            const turnstileToken = window.turnstile.getResponse();
            if (turnstileToken) {
                formData.append('cf_turnstile_response', turnstileToken);
            }
        }

        currentAbortController = new AbortController();

        try {
            const endpoint = (config.restUrl || '/wp-json/art-analyzer/v1/') + 'analyze';
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                signal: currentAbortController.signal,
            });

            if (!response.ok && response.status !== 200) {
                const errorData = await response.json().catch(() => ({}));
                const msg = errorData.message || (i18n.analyzingError || 'Analysis request failed.');
                showError(msg);
                return;
            }

            if (!response.body) {
                showError('ReadableStream not supported by browser or response was empty.');
                return;
            }

            setState('streaming');
            updateStatus(i18n.streaming || 'Generating live critique...');

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                // Process SSE events separated by double newlines
                const chunks = buffer.split('\n\n');
                buffer = chunks.pop(); // Keep incomplete fragment

                for (const chunk of chunks) {
                    processSseChunk(chunk);
                }
            }

            // Process any remaining bytes in buffer
            if (buffer.trim()) {
                processSseChunk(buffer);
            }

            if (elements.wrapper.getAttribute('data-state') !== 'error') {
                setState('completed');
                updateStatus(i18n.complete || 'Artwork analysis complete.');
            }

        } catch (err) {
            if (err.name === 'AbortError') {
                setState('cancelled');
                updateStatus(i18n.aborted || 'Analysis was stopped.');
            } else {
                showError(err.message || 'An unexpected error occurred during analysis.');
            }
        } finally {
            currentAbortController = null;
        }
    }

    /**
     * Process an individual SSE chunk.
     */
    function processSseChunk(chunkStr) {
        if (!chunkStr || !chunkStr.trim()) {
            return;
        }

        const lines = chunkStr.split('\n');
        let eventType = 'message';
        let dataStr = '';

        for (const line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('event:')) {
                eventType = trimmed.substring(6).trim();
            } else if (trimmed.startsWith('data:')) {
                dataStr = trimmed.substring(5).trim();
            }
        }

        if (!dataStr) {
            return;
        }

        let parsedData = {};
        try {
            parsedData = JSON.parse(dataStr);
        } catch (e) {
            return;
        }

        switch (eventType) {
            case 'start':
                // Record analysis ID in hidden form field if available
                if (parsedData.uuid) {
                    const idField = document.getElementById('analysis-id');
                    if (idField) idField.value = parsedData.uuid;
                }
                break;

            case 'text':
                if (parsedData.text) {
                    accumulatedMarkdown += parsedData.text;
                    renderMarkdown(accumulatedMarkdown);
                    updateTracker(accumulatedMarkdown);
                }
                break;

            case 'done':
                setState('completed');
                updateStatus(i18n.complete || 'Artwork analysis complete.');
                const statusField = document.getElementById('analysis-status');
                if (statusField) statusField.value = 'completed';
                break;

            case 'error':
                showError(parsedData.message || 'An error occurred during analysis.');
                break;
        }
    }

    /**
     * Progressive Markdown to safe HTML renderer.
     */
    function renderMarkdown(md) {
        if (!elements.streamContent) return;

        if (!md || !md.trim()) {
            elements.streamContent.innerHTML =
                '<div class="wpa-stream-placeholder">' +
                '<div class="wpa-pulse-bar"></div>' +
                '<div class="wpa-pulse-bar short"></div>' +
                '<div class="wpa-pulse-bar medium"></div>' +
                '</div>';
            return;
        }

        let html = escapeHtml(md);

        // Headers
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h2>$1</h2>');

        // Bold and Italic
        html = html.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>');
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Blockquotes
        html = html.replace(/^\> (.*$)/gim, '<blockquote>$1</blockquote>');

        // Unordered lists (- or *)
        html = html.replace(/^\s*[-*]\s+(.*$)/gim, '<li>$1</li>');

        // Wrap consecutive <li> into <ul>
        html = html.replace(/(<li>.*<\/li>)/gms, '<ul>$1</ul>');
        html = html.replace(/<\/ul>\s*<ul>/g, '');

        // Paragraphs (double newlines)
        const paragraphs = html.split(/\n\s*\n/);
        html = paragraphs.map(function (p) {
            p = p.trim();
            if (p.startsWith('<h2') || p.startsWith('<h3') || p.startsWith('<ul') || p.startsWith('<blockquote')) {
                return p;
            }
            return p.length > 0 ? '<p>' + p + '</p>' : '';
        }).join('\n');

        // Single newlines within paragraph to <br>
        html = html.replace(/(?<!>)\n(?!<)/g, '<br />');

        elements.streamContent.innerHTML = html;
    }

    /**
     * Update progress tracker list items as corresponding sections are detected in markdown.
     */
    function updateTracker(md) {
        if (!elements.trackerList) return;

        const lower = md.toLowerCase();

        const sectionMap = {
            'overview': lower.includes('artwork overview') || lower.includes('overview'),
            'identification': lower.includes('identification') || lower.includes('possible identification'),
            'subject': lower.includes('subject matter') || lower.includes('depicts'),
            'composition': lower.includes('composition'),
            'color': lower.includes('color analysis') || lower.includes('dominant colors'),
            'technique': lower.includes('technique') || lower.includes('style'),
            'mood': lower.includes('mood') || lower.includes('emotional qualities'),
            'meaning': lower.includes('meaning') || lower.includes('interpretation') || lower.includes('context'),
            'summary': lower.includes('summary') || lower.includes('conclusion'),
        };

        let lastActive = null;

        elements.trackerList.forEach(function (li) {
            const section = li.getAttribute('data-section');
            if (sectionMap[section]) {
                li.classList.remove('wpa-active');
                li.classList.add('wpa-done');
                lastActive = li;
            } else {
                li.classList.remove('wpa-done', 'wpa-active');
            }
        });

        // Highlight the latest section currently being populated
        if (lastActive && elements.wrapper.getAttribute('data-state') === 'streaming') {
            lastActive.classList.remove('wpa-done');
            lastActive.classList.add('wpa-active');
        }
    }

    function resetTracker() {
        if (elements.trackerList) {
            elements.trackerList.forEach(function (li) {
                li.classList.remove('wpa-done', 'wpa-active');
            });
        }
    }

    /**
     * UI State Machine.
     */
    function setState(state) {
        if (!elements.wrapper) return;

        elements.wrapper.setAttribute('data-state', state);

        if (state === 'streaming' || state === 'uploading') {
            if (elements.stopBtn) elements.stopBtn.style.display = 'inline-flex';
            if (elements.resetBtn) elements.resetBtn.style.display = 'none';
        } else if (state === 'completed' || state === 'cancelled' || state === 'error') {
            if (elements.stopBtn) elements.stopBtn.style.display = 'none';
            if (elements.resetBtn) elements.resetBtn.style.display = 'inline-flex';
        }
    }

    function updateStatus(text) {
        if (elements.statusText) {
            elements.statusText.textContent = text;
        }
    }

    function showError(msg) {
        setState('error');
        updateStatus('Analysis encountered an issue');

        if (elements.errorBox) {
            elements.errorBox.style.display = 'flex';
        }
        if (elements.errorMessage) {
            elements.errorMessage.textContent = msg;
        }
    }

    function resetAnalyzer() {
        setState('idle');
        if (elements.wrapper) {
            elements.wrapper.style.display = 'none';
        }
        accumulatedMarkdown = '';
        selectedFile = null;

        const fileInput = document.querySelector('form.wpcf7-form input[type="file"][name="artwork"]');
        if (fileInput) {
            fileInput.value = '';
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatBytes(bytes, decimals = 1) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
})();
