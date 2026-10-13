/**
 * WP Artwork Analyzer - Public Frontend Controller
 *
 * Implements the slide-in Artwork Analyzer sidebar, explicit "Analyze Artwork" action,
 * drag-and-drop media upload, WebRTC camera capture, visual reverse search results,
 * encyclopedic web research sources, and progressive SSE AI streaming.
 *
 * @package WP_Artwork_Analyzer
 */

(function () {
    'use strict';

    const config = window.wpaArtworkAnalyzerConfig || {};
    const i18n   = config.i18n || {};

    // State variables
    let currentAbortController = null;
    let accumulatedMarkdown    = '';
    let selectedFile           = null;
    let fileDimensions         = { width: 0, height: 0 };
    let previousActiveElement  = null;
    let cameraStream           = null;
    let isAnalyzing            = false;

    /**
     * Unified console logger with visual formatting for development and audit.
     */
    function logEvent(title, detail = null) {
        const badge = '%c[WPA Artwork Analyzer]';
        const badgeStyle = 'background: #0f172a; color: #38bdf8; font-weight: 700; padding: 2px 6px; border-radius: 4px; font-size: 11px;';
        const titleStyle = 'font-weight: 600; color: #0284c7; font-size: 11px;';

        if (detail && typeof detail === 'object') {
            console.groupCollapsed(`${badge}%c ${title}`, badgeStyle, titleStyle);
            console.dir(detail);
            console.groupEnd();
        } else if (detail !== null) {
            console.log(`${badge}%c ${title}:`, badgeStyle, titleStyle, detail);
        } else {
            console.log(`${badge}%c ${title}`, badgeStyle, titleStyle);
        }
    }

    // DOM Elements cache
    let el = {};

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cacheElements();
        bindDrawerEvents();
        bindUploadAndDropzone();
        bindAnalysisActions();
        bindFormEvents();
    }

    /**
     * Cache all primary DOM elements.
     */
    function cacheElements() {
        el = {
            // Drawer elements
            drawer:           document.getElementById('wpa-drawer'),
            backdrop:         document.getElementById('wpa-drawer-backdrop'),
            drawerCloseBtn:   document.getElementById('wpa-drawer-close'),
            launcherBtns:     document.querySelectorAll('.wpa-drawer-launcher-btn, .wpa-drawer-open-trigger, .wpa-open-drawer-btn'),
            
            // Drawer upload & preview
            dropzone:         document.getElementById('wpa-drawer-dropzone'),
            drawerFileInput:  document.getElementById('wpa-drawer-file-input'),
            drawerMobileCam:  document.getElementById('wpa-drawer-mobile-camera'),
            browseBtn:        document.getElementById('wpa-drawer-browse-btn'),
            cameraBtn:        document.getElementById('wpa-drawer-camera-btn'),
            previewCard:      document.getElementById('wpa-drawer-preview-card'),
            previewImg:       document.getElementById('wpa-drawer-preview-img'),
            filenameEl:       document.getElementById('wpa-drawer-filename'),
            dimensionsEl:     document.getElementById('wpa-drawer-dimensions'),
            filesizeEl:       document.getElementById('wpa-drawer-filesize'),
            replaceBtn:       document.getElementById('wpa-drawer-replace-btn'),
            removeBtn:        document.getElementById('wpa-drawer-remove-btn'),
            analyzeBtn:       document.getElementById('wpa-drawer-analyze-btn'),

            // Pipeline progress
            pipelineStatus:   document.getElementById('wpa-drawer-pipeline-status'),
            stageLabel:       document.getElementById('wpa-pipeline-stage-label'),
            progressBar:      document.getElementById('wpa-pipeline-progress-bar'),
            pipelineSteps:    document.querySelectorAll('#wpa-pipeline-steps li'),
            stopBtn:          document.getElementById('wpa-drawer-stop-btn'),

            // Results & Tabs
            resultsArea:      document.getElementById('wpa-drawer-results'),
            tabCritiqueBtn:   document.getElementById('wpa-tab-critique-btn'),
            tabMatchesBtn:    document.getElementById('wpa-tab-matches-btn'),
            tabResearchBtn:   document.getElementById('wpa-tab-research-btn'),
            badgeCritique:    document.getElementById('wpa-badge-critique'),
            badgeMatches:     document.getElementById('wpa-badge-matches'),
            badgeResearch:    document.getElementById('wpa-badge-research'),
            panelCritique:    document.getElementById('wpa-panel-critique'),
            panelMatches:     document.getElementById('wpa-panel-matches'),
            panelResearch:    document.getElementById('wpa-panel-research'),
            streamContent:    document.getElementById('wpa-drawer-stream-content'),

            // Visual Matches & Research Elements
            bestGuessCard:    document.getElementById('wpa-best-guess-card'),
            bestGuessText:    document.getElementById('wpa-best-guess-text'),
            entitiesWrapper:  document.getElementById('wpa-entities-wrapper'),
            entitiesTags:     document.getElementById('wpa-entities-tags'),
            matchesList:      document.getElementById('wpa-matches-list'),
            matchesEmpty:     document.getElementById('wpa-matches-empty'),
            researchList:     document.getElementById('wpa-research-list'),
            researchEmpty:    document.getElementById('wpa-research-empty'),

            // Error & Start Over
            errorBox:         document.getElementById('wpa-drawer-error-box'),
            errorMessage:     document.getElementById('wpa-drawer-error-message'),
            retryBtn:         document.getElementById('wpa-drawer-retry-btn'),
            startOverBtn:     document.getElementById('wpa-drawer-startover-btn'),

            // Inline legacy wrapper
            inlineWrapper:    document.getElementById('wpa-analyzer-wrapper'),
            inlineStopBtn:    document.getElementById('wpa-btn-stop'),
            inlineResetBtn:   document.getElementById('wpa-btn-reset'),
            inlineRetryBtn:   document.getElementById('wpa-btn-retry'),
            inlineStream:     document.getElementById('wpa-stream-content'),
        };
    }

    /**
     * Bind drawer open/close, backdrop, and Escape key events.
     */
    function bindDrawerEvents() {
        // Open drawer from any launcher button
        document.addEventListener('click', function (e) {
            const launcher = e.target.closest('.wpa-drawer-launcher-btn, .wpa-drawer-open-trigger, .wpa-open-drawer-btn, #wpa-drawer-launcher-btn');
            if (launcher) {
                e.preventDefault();
                openDrawer();
            }
        });

        // Close drawer from close button
        if (el.drawerCloseBtn) {
            el.drawerCloseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeDrawer();
            });
        }

        // Close drawer by clicking backdrop
        if (el.backdrop) {
            el.backdrop.addEventListener('click', function (e) {
                e.preventDefault();
                closeDrawer();
            });
        }

        // Escape key support
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (el.drawer && el.drawer.classList.contains('wpa-drawer-open')) {
                    closeDrawer();
                }
            }
        });
    }

    /**
     * Open Slide-In Drawer.
     */
    function openDrawer() {
        if (!el.drawer) cacheElements();
        if (!el.drawer) return;

        previousActiveElement = document.activeElement;

        el.drawer.classList.add('wpa-drawer-open');
        el.drawer.setAttribute('aria-hidden', 'false');

        if (el.backdrop) {
            el.backdrop.classList.add('wpa-backdrop-visible');
            el.backdrop.setAttribute('aria-hidden', 'false');
        }

        document.body.classList.add('wpa-drawer-active');

        // Focus close button or dropzone for a11y
        setTimeout(function () {
            if (el.drawerCloseBtn) {
                el.drawerCloseBtn.focus();
            } else if (el.drawer) {
                el.drawer.focus();
            }
        }, 200);
    }

    /**
     * Close Slide-In Drawer with safety confirmation if operation is active.
     */
    function closeDrawer(force = false) {
        if (!el.drawer || !el.drawer.classList.contains('wpa-drawer-open')) {
            return;
        }

        if (isAnalyzing && !force) {
            const confirmMsg = i18n.confirmClose || 'An artwork analysis is currently in progress. Do you want to cancel and close?';
            if (!window.confirm(confirmMsg)) {
                return;
            }
            if (currentAbortController) {
                currentAbortController.abort();
            }
        }

        el.drawer.classList.remove('wpa-drawer-open');
        el.drawer.setAttribute('aria-hidden', 'true');

        if (el.backdrop) {
            el.backdrop.classList.remove('wpa-backdrop-visible');
            el.backdrop.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('wpa-drawer-active');

        // Restore focus
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
    }

    /**
     * Bind drag-and-drop, file browsing, and camera triggers.
     */
    function bindUploadAndDropzone() {
        if (el.browseBtn && el.drawerFileInput) {
            el.browseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                el.drawerFileInput.click();
            });
        }

        if (el.drawerFileInput) {
            el.drawerFileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    processSelectedFile(e.target.files[0]);
                }
            });
        }

        if (el.cameraBtn) {
            el.cameraBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                                 (window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

                if (isMobile && el.drawerMobileCam) {
                    el.drawerMobileCam.click();
                } else if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    openDesktopCameraModal(function (capturedFile) {
                        processSelectedFile(capturedFile);
                    });
                } else if (el.drawerMobileCam) {
                    el.drawerMobileCam.click();
                }
            });
        }

        if (el.drawerMobileCam) {
            el.drawerMobileCam.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    processSelectedFile(e.target.files[0]);
                }
            });
        }

        // Drag and drop handling
        if (el.dropzone) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                el.dropzone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.dropzone.classList.add('wpa-dropzone-dragover');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
                el.dropzone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.dropzone.classList.remove('wpa-dropzone-dragover');
                });
            });

            el.dropzone.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    processSelectedFile(e.dataTransfer.files[0]);
                }
            });

            // Keyboard trigger on dropzone
            el.dropzone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (el.drawerFileInput) el.drawerFileInput.click();
                }
            });
        }

        // Replace and Remove Image buttons
        if (el.replaceBtn && el.drawerFileInput) {
            el.replaceBtn.addEventListener('click', function (e) {
                e.preventDefault();
                el.drawerFileInput.click();
            });
        }

        if (el.removeBtn) {
            el.removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                clearSelectedFile();
            });
        }

        if (el.startOverBtn) {
            el.startOverBtn.addEventListener('click', function (e) {
                e.preventDefault();
                clearSelectedFile();
            });
        }
    }

    /**
     * Process selected image file with client-side validation.
     */
    function processSelectedFile(file) {
        if (!file) return;

        // Check format
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showError(i18n.invalidFormat || 'Only JPG, PNG, and WEBP files are supported.');
            return;
        }

        // Check file size
        const maxBytes = (config.maxFileSize || 10) * 1024 * 1024;
        if (file.size > maxBytes) {
            showError(i18n.fileTooLarge || 'File exceeds maximum allowed size.');
            return;
        }

        selectedFile = file;

        // Hide any previous error
        if (el.errorBox) el.errorBox.style.display = 'none';

        // Read image for preview and dimensions
        const reader = new FileReader();
        reader.onload = function (e) {
            const dataUrl = e.target.result;
            if (el.previewImg) el.previewImg.src = dataUrl;

            // Determine image dimensions
            const img = new Image();
            img.onload = function () {
                fileDimensions = { width: img.naturalWidth, height: img.naturalHeight };
                if (el.dimensionsEl) {
                    el.dimensionsEl.textContent = `${fileDimensions.width} × ${fileDimensions.height} px`;
                }

                logEvent('📁 Image Selected & Inspected', {
                    filename: file.name || 'artwork.jpg',
                    size: formatBytes(file.size),
                    type: file.type,
                    dimensions: `${fileDimensions.width} × ${fileDimensions.height} px`,
                });
            };
            img.src = dataUrl;

            // Update preview details
            if (el.filenameEl) el.filenameEl.textContent = file.name || 'artwork.jpg';
            if (el.filesizeEl) el.filesizeEl.textContent = formatBytes(file.size);

            // Toggle dropzone vs preview card
            if (el.dropzone) el.dropzone.style.display = 'none';
            if (el.previewCard) el.previewCard.style.display = 'flex';

            // Enable Analyze Artwork button!
            if (el.analyzeBtn) {
                el.analyzeBtn.disabled = false;
                el.analyzeBtn.focus();
            }

            // Sync with Contact Form 7 input if present
            syncFileWithCf7(file);
        };
        reader.readAsDataURL(file);
    }

    /**
     * Reset selected file and return to upload state.
     */
    function clearSelectedFile() {
        selectedFile = null;
        fileDimensions = { width: 0, height: 0 };

        if (el.drawerFileInput) el.drawerFileInput.value = '';
        if (el.drawerMobileCam) el.drawerMobileCam.value = '';

        if (el.previewImg) el.previewImg.src = '';
        if (el.previewCard) el.previewCard.style.display = 'none';
        if (el.dropzone) el.dropzone.style.display = 'flex';

        if (el.analyzeBtn) el.analyzeBtn.disabled = true;

        if (el.pipelineStatus) el.pipelineStatus.style.display = 'none';
        if (el.resultsArea) el.resultsArea.style.display = 'none';
        if (el.errorBox) el.errorBox.style.display = 'none';

        // Clear CF7 input
        const cf7Input = document.querySelector('form.wpcf7-form input[type="file"][name="artwork"]');
        if (cf7Input) cf7Input.value = '';
    }

    /**
     * Synchronize File object with Contact Form 7 input via DataTransfer API.
     */
    function syncFileWithCf7(file) {
        const cf7Input = document.querySelector('form.wpcf7-form input[type="file"][name="artwork"]') ||
                         document.querySelector('form.wpcf7-form input[type="file"]');
        if (!cf7Input) return;

        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            cf7Input.files = dt.files;
        } catch (e) {
            // Fallback: selectedFile is passed directly in FormData
        }
    }

    /**
     * Bind primary analysis actions (Analyze button, Stop, Retry, Tabs).
     */
    function bindAnalysisActions() {
        // Explicit Analyze Artwork button in drawer
        if (el.analyzeBtn) {
            el.analyzeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (selectedFile && !isAnalyzing) {
                    startPipelineAnalysis(selectedFile);
                }
            });
        }

        // Stop analysis button
        if (el.stopBtn) {
            el.stopBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (currentAbortController) {
                    currentAbortController.abort();
                }
            });
        }

        // Retry button
        if (el.retryBtn) {
            el.retryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (selectedFile) {
                    startPipelineAnalysis(selectedFile);
                }
            });
        }

        // Tab switching
        if (el.tabCritiqueBtn) {
            el.tabCritiqueBtn.addEventListener('click', function () {
                switchTab('critique');
            });
        }
        if (el.tabMatchesBtn) {
            el.tabMatchesBtn.addEventListener('click', function () {
                switchTab('matches');
            });
        }
        if (el.tabResearchBtn) {
            el.tabResearchBtn.addEventListener('click', function () {
                switchTab('research');
            });
        }
    }

    /**
     * Switch active tab in results section.
     */
    function switchTab(tabName) {
        const tabs = [
            { name: 'critique', btn: el.tabCritiqueBtn, panel: el.panelCritique },
            { name: 'matches',  btn: el.tabMatchesBtn,  panel: el.panelMatches },
            { name: 'research', btn: el.tabResearchBtn, panel: el.panelResearch },
        ];

        tabs.forEach(function (t) {
            if (t.btn && t.panel) {
                if (t.name === tabName) {
                    t.btn.classList.add('wpa-tab-active');
                    t.btn.setAttribute('aria-selected', 'true');
                    t.panel.classList.add('wpa-panel-active');
                    t.panel.style.display = 'block';
                } else {
                    t.btn.classList.remove('wpa-tab-active');
                    t.btn.setAttribute('aria-selected', 'false');
                    t.panel.classList.remove('wpa-panel-active');
                    t.panel.style.display = 'none';
                }
            }
        });
    }

    /**
     * Execute the full sequential multi-stage analysis pipeline via SSE.
     */
    async function startPipelineAnalysis(file) {
        if (isAnalyzing) return;
        isAnalyzing = true;

        // UI state updates
        if (el.analyzeBtn) el.analyzeBtn.disabled = true;
        if (el.replaceBtn) el.replaceBtn.disabled = true;
        if (el.removeBtn) el.removeBtn.disabled = true;

        if (el.pipelineStatus) el.pipelineStatus.style.display = 'block';
        if (el.resultsArea) el.resultsArea.style.display = 'block';
        if (el.errorBox) el.errorBox.style.display = 'none';

        // Reset progress and result containers
        updateStage('preparing', i18n.preparing || 'Preparing & optimizing image...', 15);
        accumulatedMarkdown = '';
        renderMarkdown('');
        resetMatchesArea();
        resetResearchArea();
        switchTab('critique');

        // Prepare FormData
        const formData = new FormData();
        formData.append('artwork', file);

        // Turnstile
        if (config.turnstileEnabled && window.turnstile) {
            const token = window.turnstile.getResponse();
            if (token) formData.append('cf_turnstile_response', token);
        }

        // CF7 Form ID or Email if available
        const cf7Form = document.querySelector('form.wpcf7-form');
        if (cf7Form) {
            const emailInput = cf7Form.querySelector('input[type="email"]');
            if (emailInput && emailInput.value) {
                formData.append('your-email', emailInput.value);
            }
            const formIdInput = cf7Form.querySelector('input[name="_wpcf7"]');
            if (formIdInput) {
                formData.append('form_id', formIdInput.value);
            }
        }

        currentAbortController = new AbortController();

        try {
            const endpoint = (config.restUrl || '/wp-json/art-analyzer/v1/') + 'analyze';

            logEvent('🚀 [REQUEST] Initiating artwork analysis pipeline', {
                endpoint: endpoint,
                file: {
                    name: file.name,
                    size: formatBytes(file.size),
                    type: file.type,
                    dimensions: `${fileDimensions.width} × ${fileDimensions.height} px`,
                },
                turnstile: config.turnstileEnabled ? 'active' : 'disabled',
            });

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                signal: currentAbortController.signal,
            });

            logEvent('⚡ [RESPONSE] SSE Connection Established', {
                status: response.status,
                statusText: response.statusText,
                contentType: response.headers.get('content-type'),
            });

            if (!response.ok && response.status !== 200) {
                const errData = await response.json().catch(() => ({}));
                logEvent('❌ Analysis request HTTP error', errData);
                showError(errData.message || 'Analysis request failed.');
                return;
            }

            if (!response.body) {
                showError('Streaming is not supported by your browser.');
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const chunks = buffer.split('\n\n');
                buffer = chunks.pop(); // Keep unparsed fragment

                for (const chunk of chunks) {
                    processStreamPacket(chunk);
                }
            }

            if (buffer.trim()) {
                processStreamPacket(buffer);
            }

            finishPipeline();

        } catch (err) {
            if (err.name === 'AbortError') {
                logEvent('🛑 Analysis aborted by user');
                updateStage('aborted', i18n.aborted || 'Analysis was stopped.', 0);
            } else {
                logEvent('❌ Unexpected exception in analysis pipeline', { error: err.message });
                showError(err.message || 'An unexpected error occurred during analysis.');
            }
        } finally {
            isAnalyzing = false;
            currentAbortController = null;
            if (el.analyzeBtn) el.analyzeBtn.disabled = false;
            if (el.replaceBtn) el.replaceBtn.disabled = false;
            if (el.removeBtn) el.removeBtn.disabled = false;
        }
    }

    /**
     * Process an SSE event packet.
     */
    function processStreamPacket(packet) {
        if (!packet || !packet.trim()) return;

        const lines = packet.split('\n');
        let eventType = 'message';
        let dataStr   = '';

        for (const line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('event:')) {
                eventType = trimmed.substring(6).trim();
            } else if (trimmed.startsWith('data:')) {
                dataStr = trimmed.substring(5).trim();
            }
        }

        if (!dataStr) return;

        let data = {};
        try {
            data = JSON.parse(dataStr);
        } catch (e) {
            return;
        }

        switch (eventType) {
            case 'start':
                logEvent('🏁 Stream Session Initialized', { uuid: data.uuid });
                break;

            case 'stage':
                logEvent(`📍 Stage: ${data.stage} (${data.progress}%)`, {
                    stage: data.stage,
                    label: data.label,
                    progress: data.progress,
                });
                updateStage(data.stage, data.label, data.progress);
                break;

            case 'matches':
                logEvent('🔎 [RESPONSE] Visual Reverse Search Matches', {
                    provider: data.provider,
                    configured: data.configured,
                    best_guess: data.best_guess,
                    entities: data.entities,
                    matches_count: data.matches ? data.matches.length : 0,
                    matches: data.matches,
                });
                if (data.matches && data.matches.length && typeof console.table === 'function') {
                    console.table(data.matches);
                }
                renderMatches(data);
                break;

            case 'research':
                logEvent('📚 [RESPONSE] Encyclopedic Web Research Findings', {
                    provider: data.provider,
                    configured: data.configured,
                    query: data.query,
                    findings_count: data.findings ? data.findings.length : 0,
                    findings: data.findings,
                });
                if (data.findings && data.findings.length && typeof console.table === 'function') {
                    console.table(data.findings);
                }
                renderResearch(data);
                break;

            case 'text':
                if (data.text) {
                    accumulatedMarkdown += data.text;
                    renderMarkdown(accumulatedMarkdown);
                }
                break;

            case 'done':
                logEvent('✅ [COMPLETE] Artwork Analysis Finished', {
                    status: data.status,
                    uuid: data.uuid,
                    total_critique_length: accumulatedMarkdown.length,
                });
                finishPipeline();
                break;

            case 'error':
                logEvent('❌ [ERROR] Analysis Stream Error', data);
                showError(data.message || 'An error occurred during analysis.');
                break;
        }
    }

    /**
     * Update pipeline stage indicators.
     */
    function updateStage(stage, label, progress) {
        if (el.stageLabel && label) {
            el.stageLabel.textContent = label;
        }

        if (el.progressBar && typeof progress === 'number') {
            el.progressBar.style.width = `${progress}%`;
        }

        if (el.pipelineSteps) {
            const stepOrder = ['preparing', 'reverse_search', 'web_research', 'analyzing'];
            const currentIndex = stepOrder.indexOf(stage);

            el.pipelineSteps.forEach(function (li, idx) {
                const s = li.getAttribute('data-step');
                if (s === stage) {
                    li.classList.add('wpa-step-active');
                    li.classList.remove('wpa-step-done');
                } else if (currentIndex > -1 && idx < currentIndex) {
                    li.classList.remove('wpa-step-active');
                    li.classList.add('wpa-step-done');
                } else if (stage === 'completed') {
                    li.classList.remove('wpa-step-active');
                    li.classList.add('wpa-step-done');
                } else {
                    li.classList.remove('wpa-step-active', 'wpa-step-done');
                }
            });
        }
    }

    /**
     * Render Reverse Image Search results in Tab 2.
     */
    function renderMatches(data) {
        const matches = data.matches || [];
        const count = matches.length;

        if (el.badgeMatches) el.badgeMatches.textContent = count;

        // Best Guess Candidate
        if (data.best_guess && el.bestGuessCard && el.bestGuessText) {
            el.bestGuessText.textContent = data.best_guess;
            el.bestGuessCard.style.display = 'block';
        }

        // Web Entities
        if (data.entities && data.entities.length && el.entitiesWrapper && el.entitiesTags) {
            el.entitiesTags.innerHTML = '';
            data.entities.forEach(function (ent) {
                const chip = document.createElement('span');
                chip.className = 'wpa-entity-tag';
                chip.textContent = ent.description;
                el.entitiesTags.appendChild(chip);
            });
            el.entitiesWrapper.style.display = 'block';
        }

        // Matches Cards
        if (el.matchesList) {
            el.matchesList.innerHTML = '';

            if (count === 0) {
                el.matchesList.innerHTML = `
                    <div class="wpa-empty-state">
                        <div class="wpa-empty-icon">🔎</div>
                        <div class="wpa-empty-text">${i18n.noMatchesFound || 'No direct visual matches found online. Proceeding with independent visual analysis.'}</div>
                    </div>
                `;
                return;
            }

            matches.forEach(function (m) {
                const card = document.createElement('div');
                card.className = 'wpa-match-card';

                const matchTypeClass = m.match_type === 'exact' ? 'wpa-badge-exact' : (m.match_type === 'partial' ? 'wpa-badge-partial' : 'wpa-badge-similar');
                const matchTypeLabel = m.match_type === 'exact' ? (i18n.exactMatch || 'Exact Match') : (m.match_type === 'partial' ? (i18n.partialMatch || 'Partial Match') : (i18n.similarMatch || 'Similar'));

                const thumbHtml = m.thumbnail_url ? `<div class="wpa-match-thumb"><img src="${escapeHtml(m.thumbnail_url)}" alt="Match" loading="lazy" /></div>` : '';

                card.innerHTML = `
                    ${thumbHtml}
                    <div class="wpa-match-info">
                        <div class="wpa-match-header">
                            <span class="wpa-match-type ${matchTypeClass}">${matchTypeLabel}</span>
                            <span class="wpa-match-domain">${escapeHtml(m.domain || '')}</span>
                        </div>
                        <a href="${escapeHtml(m.url)}" target="_blank" rel="noopener noreferrer" class="wpa-match-title">
                            ${escapeHtml(m.title || 'Source Page')} ↗
                        </a>
                    </div>
                `;
                el.matchesList.appendChild(card);
            });
        }
    }

    /**
     * Render Web Research findings in Tab 3.
     */
    function renderResearch(data) {
        const findings = data.findings || [];
        const count = findings.length;

        if (el.badgeResearch) el.badgeResearch.textContent = count;

        if (el.researchList) {
            el.researchList.innerHTML = '';

            if (count === 0) {
                el.researchList.innerHTML = `
                    <div class="wpa-empty-state">
                        <div class="wpa-empty-icon">📚</div>
                        <div class="wpa-empty-text">${i18n.noResearchFound || 'No encyclopedic background records found for this specific work.'}</div>
                    </div>
                `;
                return;
            }

            findings.forEach(function (f) {
                const card = document.createElement('div');
                card.className = 'wpa-research-card';

                card.innerHTML = `
                    <div class="wpa-research-header">
                        <span class="wpa-research-topic">${escapeHtml(f.topic || 'General')}</span>
                        <span class="wpa-research-domain">${escapeHtml(f.domain || '')}</span>
                    </div>
                    <a href="${escapeHtml(f.url)}" target="_blank" rel="noopener noreferrer" class="wpa-research-title">
                        ${escapeHtml(f.title)} ↗
                    </a>
                    ${f.snippet ? `<div class="wpa-research-snippet">${escapeHtml(f.snippet)}</div>` : ''}
                `;
                el.researchList.appendChild(card);
            });
        }
    }

    function resetMatchesArea() {
        if (el.badgeMatches) el.badgeMatches.textContent = '0';
        if (el.bestGuessCard) el.bestGuessCard.style.display = 'none';
        if (el.entitiesWrapper) el.entitiesWrapper.style.display = 'none';
        if (el.matchesList) {
            el.matchesList.innerHTML = `
                <div class="wpa-empty-state">
                    <div class="wpa-empty-icon">🔎</div>
                    <div class="wpa-empty-text">${i18n.reverseSearching || 'Searching for visual matches online...'}</div>
                </div>
            `;
        }
    }

    function resetResearchArea() {
        if (el.badgeResearch) el.badgeResearch.textContent = '0';
        if (el.researchList) {
            el.researchList.innerHTML = `
                <div class="wpa-empty-state">
                    <div class="wpa-empty-icon">📚</div>
                    <div class="wpa-empty-text">${i18n.webResearching || 'Researching artist, history, & collection records...'}</div>
                </div>
            `;
        }
    }

    function finishPipeline() {
        isAnalyzing = false;
        updateStage('completed', i18n.complete || 'Artwork analysis complete.', 100);

        if (el.badgeCritique) {
            el.badgeCritique.textContent = 'Complete';
            el.badgeCritique.classList.remove('wpa-badge-streaming');
            el.badgeCritique.classList.add('wpa-badge-done');
        }

        // Update hidden CF7 form status
        const statusField = document.getElementById('analysis-status');
        if (statusField) statusField.value = 'completed';
    }

    function showError(msg) {
        isAnalyzing = false;
        if (el.errorBox) el.errorBox.style.display = 'flex';
        if (el.errorMessage) el.errorMessage.textContent = msg;
        if (el.pipelineStatus) el.pipelineStatus.style.display = 'none';
    }

    /**
     * Markdown renderer that supports headers, bold, italics, lists, blockquotes,
     * and clickable external research source links [Title](URL).
     */
    function renderMarkdown(md) {
        const container = el.streamContent || el.inlineStream;
        if (!container) return;

        if (!md || !md.trim()) {
            container.innerHTML = `
                <div class="wpa-stream-placeholder">
                    <div class="wpa-pulse-bar"></div>
                    <div class="wpa-pulse-bar short"></div>
                    <div class="wpa-pulse-bar medium"></div>
                </div>
            `;
            return;
        }

        let html = escapeHtml(md);

        // Markdown Links [Text](URL) -> Safe Clickable Link
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="wpa-citation-link">$1 ↗</a>');

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

        // Lists
        html = html.replace(/^\s*[-*]\s+(.*$)/gim, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/gms, '<ul>$1</ul>');
        html = html.replace(/<\/ul>\s*<ul>/g, '');

        // Paragraphs
        const paragraphs = html.split(/\n\s*\n/);
        html = paragraphs.map(function (p) {
            p = p.trim();
            if (p.startsWith('<h2') || p.startsWith('<h3') || p.startsWith('<ul') || p.startsWith('<blockquote')) {
                return p;
            }
            return p.length > 0 ? '<p>' + p + '</p>' : '';
        }).join('\n');

        html = html.replace(/(?<!>)\n(?!<)/g, '<br />');

        container.innerHTML = html;
    }

    /**
     * Contact Form 7 integration: Enhance native file upload with
     * Choose File & Camera buttons, plus explicit Analyze Artwork trigger.
     */
    function bindFormEvents() {
        const forms = document.querySelectorAll('form.wpcf7-form');

        forms.forEach(function (form) {
            const formIdInput = form.querySelector('input[name="_wpcf7"]');
            const formId = formIdInput ? parseInt(formIdInput.value, 10) : 0;
            const targetId = config.configuredFormId ? parseInt(config.configuredFormId, 10) : 0;

            if (targetId > 0 && formId > 0 && formId !== targetId) {
                return;
            }

            const fileInput = form.querySelector('input[type="file"][name="artwork"]') ||
                              form.querySelector('input[type="file"]');
            if (!fileInput || fileInput.dataset.wpaEnhanced) return;
            fileInput.dataset.wpaEnhanced = 'true';

            fileInput.setAttribute('accept', 'image/jpeg,image/png,image/webp,image/*');

            // Source picker wrapper
            const controlsWrapper = document.createElement('div');
            controlsWrapper.className = 'wpa-source-picker-wrapper';

            const btnFile = document.createElement('button');
            btnFile.type = 'button';
            btnFile.className = 'wpa-btn-source wpa-btn-file-select';
            btnFile.innerHTML = '📁 ' + (i18n.chooseFile || 'Choose Image File');

            const btnCamera = document.createElement('button');
            btnCamera.type = 'button';
            btnCamera.className = 'wpa-btn-source wpa-btn-camera-trigger';
            btnCamera.innerHTML = '📷 ' + (i18n.takePhoto || 'Take Photo');

            const btnAnalyzeExplicit = document.createElement('button');
            btnAnalyzeExplicit.type = 'button';
            btnAnalyzeExplicit.className = 'wpa-btn wpa-btn-primary wpa-btn-explicit-analyze';
            btnAnalyzeExplicit.innerHTML = '🔍 ' + (i18n.analyzeArtwork || 'Analyze Artwork');
            btnAnalyzeExplicit.style.display = 'none';

            controlsWrapper.appendChild(btnFile);
            controlsWrapper.appendChild(btnCamera);
            controlsWrapper.appendChild(btnAnalyzeExplicit);

            // Selected media preview card
            const selectedBadge = document.createElement('div');
            selectedBadge.className = 'wpa-selected-media-card';
            selectedBadge.style.display = 'none';
            selectedBadge.innerHTML = `
                <div class="wpa-selected-media-thumb">
                    <img src="" alt="Thumbnail" class="wpa-media-thumb-img" />
                </div>
                <div class="wpa-selected-media-details">
                    <span class="wpa-selected-media-title"></span>
                    <span class="wpa-selected-media-meta"></span>
                </div>
                <button type="button" class="wpa-selected-media-clear" title="${i18n.remove || 'Remove'}">&times;</button>
            `;

            const parent = fileInput.closest('.wpcf7-form-control-wrap') || fileInput.parentElement;
            if (parent) {
                parent.appendChild(controlsWrapper);
                parent.appendChild(selectedBadge);
            }

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
                    if (el.drawerMobileCam) el.drawerMobileCam.click();
                } else if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    openDesktopCameraModal(function (captured) {
                        applyFileToCf7(captured, fileInput, selectedBadge, btnAnalyzeExplicit);
                    });
                }
            });

            fileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    applyFileToCf7(e.target.files[0], fileInput, selectedBadge, btnAnalyzeExplicit);
                }
            });

            // Explicit Analyze Artwork Click Handler: Opens Sidebar Drawer and runs Analysis!
            btnAnalyzeExplicit.addEventListener('click', function (e) {
                e.preventDefault();
                const file = selectedFile || (fileInput.files && fileInput.files[0]);
                if (file) {
                    openDrawer();
                    processSelectedFile(file);
                    startPipelineAnalysis(file);
                }
            });

            const clearBtn = selectedBadge.querySelector('.wpa-selected-media-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    fileInput.value = '';
                    selectedFile = null;
                    selectedBadge.style.display = 'none';
                    btnAnalyzeExplicit.style.display = 'none';
                });
            }
        });
    }

    function applyFileToCf7(file, fileInput, selectedBadge, btnAnalyze) {
        selectedFile = file;

        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
        } catch (e) {}

        if (selectedBadge) {
            const thumbImg = selectedBadge.querySelector('.wpa-media-thumb-img');
            const titleEl  = selectedBadge.querySelector('.wpa-selected-media-title');
            const metaEl   = selectedBadge.querySelector('.wpa-selected-media-meta');

            if (titleEl) titleEl.textContent = file.name || 'artwork.jpg';
            if (metaEl)  metaEl.textContent = formatBytes(file.size);

            const reader = new FileReader();
            reader.onload = function (e) {
                if (thumbImg) thumbImg.src = e.target.result;
                selectedBadge.style.display = 'flex';
                if (btnAnalyze) btnAnalyze.style.display = 'inline-flex';
            };
            reader.readAsDataURL(file);
        }

        // Also update drawer if open
        if (el.previewImg) {
            processSelectedFile(file);
        }
    }

    /**
     * Desktop WebRTC Camera Modal.
     */
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
            audio: false,
        }).then(function (stream) {
            cameraStream = stream;
            video.srcObject = stream;
            video.play();
        }).catch(function (err) {
            closeCameraModal();
            alert(i18n.cameraUnavailable || 'Camera not available or access denied.');
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
        if (modal) modal.style.display = 'none';

        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
    }

    /**
     * Helpers.
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatBytes(bytes, decimals = 1) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
})();
