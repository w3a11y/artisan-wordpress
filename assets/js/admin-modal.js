/**
 * W3A11Y Artisan Admin Modal JavaScript
 * 
 * Main JavaScript file for handling modal interactions, API communications,
 * image editing features, and all user interactions within the Artisan modal.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

(function () {
    'use strict';

    // Helper function for conditional logging
    function w3a11yLog(message, ...args) {
        if (window.w3a11yArtisan && window.w3a11yArtisan.enable_logging) {
            console.log(message, ...args);
        }
    }

    // Main W3A11Y Artisan object
    window.W3A11YArtisan = window.W3A11YArtisan || {};

    /**
     * Utility function to safely get w3a11yArtisan property with fallback
     */
    function getArtisanConfig(property, fallback = '') {
        if (typeof window.w3a11yArtisan !== 'undefined' && window.w3a11yArtisan[property]) {
            return window.w3a11yArtisan[property];
        }
        return fallback;
    }

    /**
     * Modal Controller Class
     * Handles all modal-related functionality
     */
    class W3A11YArtisanModal {
        constructor() {
            this.modal = null;
            this.state = {
                isOpen: false,
                mode: 'generate', // 'generate' or 'edit'
                currentImage: null,
                originalImage: null,
                referenceImages: [], // Array to store multiple reference images (max 13)
                attachmentId: null,
                history: [], // Prompt history for reuse
                originalUserPrompt: null, // Store original user input before AI refinement
                zoomLevel: 1,
                panX: 0, // Pan offset X
                panY: 0, // Pan offset Y
                selectionArea: null,
                credits: null,
                isProcessing: false,
                hasSavedImages: false, // Track if any images were saved during this session
                // Undo/Redo state management
                imageHistory: [], // Array of image states for undo/redo
                historyIndex: -1, // Current position in image history
                maxHistorySize: 20, // Maximum number of states to keep
                // Generation options
                selectedAspectRatio: '1:1',
                selectedResolution: '1K',
                selectedStyle: 'photorealistic',
                imageDimensions: { width: 1024, height: 1024 }, // Default square
                useGoogleSearch: false, // Grounding with Google Search for real-time information
                // Output format options
                selectedFormat: 'png',
                selectedQuality: 90
            };

            this.elements = {};
            this.storedGenerationOptions = null; // Store generation options container for mode switching
            this.recognition = null; // Web Speech API recognition instance
            this.isListening = false; // Voice input state
            this.init();
        }

        /**
         * Initialize the modal system
         */
        init() {
            this.cacheElements();
            this.storeGenerationOptions();
            this.initVoiceRecognition();
            this.bindEvents();

            // Check if centralized notification system is available
            if (window.W3A11YNotifications) {
                w3a11yLog('W3A11Y Artisan Modal initialized - Centralized notification system ready');
            } else {
                w3a11yLog('W3A11Y Artisan Modal initialized - Waiting for centralized notification system...');
                // Wait a bit for the notification system to load
                setTimeout(() => {
                    if (window.W3A11YNotifications) {
                        w3a11yLog('Centralized notification system now available');
                    } else {
                        console.warn('W3A11Y: Centralized notification system not available, using fallback');
                    }
                }, 1000);
            }
        }

        /**
         * Cache DOM elements for performance
         */
        cacheElements() {
            this.modal = document.getElementById('w3a11y-artisan-modal');

            // Ensure modal exists (should be added via backend)
            if (!this.modal) {
                console.error('W3A11Y: Modal HTML not found! Make sure plugin resources are loaded properly.');
                return;
            }

            this.elements = {
                // Modal controls
                closeBtn: document.getElementById('w3a11y-close-modal'),
                fullscreenBtn: document.getElementById('w3a11y-fullscreen-btn'),
                helpBtn: document.getElementById('w3a11y-help-btn'),

                // Image display
                imageDisplay: document.getElementById('w3a11y-image-display'),
                mainImage: document.getElementById('w3a11y-main-image'),
                imagePlaceholder: document.getElementById('w3a11y-image-placeholder'),
                selectionBox: document.getElementById('w3a11y-selection-box'),
                loadingOverlay: document.getElementById('w3a11y-loading-overlay'),
                loadingText: document.getElementById('w3a11y-loading-text'),

                // Image controls
                undoBtn: document.getElementById('w3a11y-undo-btn'),
                redoBtn: document.getElementById('w3a11y-redo-btn'),
                afterBtn: document.getElementById('w3a11y-after-btn'),
                beforeBtn: document.getElementById('w3a11y-before-btn'),
                editAreaBtn: document.getElementById('w3a11y-edit-area-btn'),
                zoomInBtn: document.getElementById('w3a11y-zoom-in-btn'),
                zoomOutBtn: document.getElementById('w3a11y-zoom-out-btn'),
                removeBgBtn: document.getElementById('w3a11y-remove-bg-btn'),

                // Prompt section
                promptTextarea: document.getElementById('w3a11y-prompt'),
                voiceInputBtn: document.getElementById('w3a11y-voice-input'),
                attachReferenceBtn: document.getElementById('w3a11y-attach-reference'),
                referenceFileInput: document.getElementById('w3a11y-reference-file'),
                referencePreviewContainer: document.getElementById('w3a11y-reference-preview-container'),
                referenceImages: document.getElementById('w3a11y-reference-images'),
                referenceCount: document.getElementById('w3a11y-reference-count'),
                generateBtn: document.getElementById('w3a11y-generate-btn'),

                // Format and quality options
                optimizeConvertCheckbox: document.getElementById('w3a11y-optimize-convert'),
                formatGroup: document.getElementById('w3a11y-format-group'),
                qualityGroup: document.getElementById('w3a11y-quality-group'),
                formatOptions: document.getElementById('w3a11y-format-options'),
                qualitySlider: document.getElementById('w3a11y-quality-slider'),
                qualityValue: document.getElementById('w3a11y-quality-value'),

                // Inspiration section
                inspirationContent: document.getElementById('w3a11y-inspiration-content'),
                inspirationLoading: document.getElementById('w3a11y-inspiration-loading'),
                inspirationTags: document.getElementById('w3a11y-inspiration-tags'),
                inspirationPlaceholder: document.getElementById('w3a11y-inspiration-placeholder'),

                // History section
                historyContent: document.getElementById('w3a11y-history-content'),

                // Footer
                creditsCount: document.getElementById('w3a11y-credits-count'),
                revertBtn: document.getElementById('w3a11y-revert-btn'),
                applyBtn: document.getElementById('w3a11y-apply-btn'),

                // Save modal
                saveModal: document.getElementById('w3a11y-save-options-modal'),
                closeSaveModalBtn: document.getElementById('w3a11y-close-save-modal'),
                saveFilename: document.getElementById('w3a11y-save-filename'),
                saveTitle: document.getElementById('w3a11y-save-title'),
                saveAltText: document.getElementById('w3a11y-save-alt-text'),
                replaceExisting: document.getElementById('w3a11y-replace-existing'),
                cancelSaveBtn: document.getElementById('w3a11y-cancel-save'),
                confirmSaveBtn: document.getElementById('w3a11y-confirm-save'),

                // Hidden data
                currentImageBase64: document.getElementById('w3a11y-current-image-base64'),
                originalImageBase64: document.getElementById('w3a11y-original-image-base64'),
                referenceImagesBase64: document.getElementById('w3a11y-reference-images-base64'),
                currentAttachmentId: document.getElementById('w3a11y-current-attachment-id'),
                modalMode: document.getElementById('w3a11y-modal-mode')
            };
        }

        /**
         * Store generation options container for mode switching
         */
        storeGenerationOptions() {
            const generationOptions = document.querySelector('.w3a11y-generation-options');
            if (generationOptions) {
                this.storedGenerationOptions = generationOptions.cloneNode(true);
                this.storedGenerationOptionsParent = generationOptions.parentNode;
                w3a11yLog('Generation options container stored for mode switching');
            }
        }

        /**
         * Initialize Web Speech API for voice recognition
         */
        initVoiceRecognition() {
            // Check if browser supports Web Speech API
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                w3a11yLog('Web Speech API not supported in this browser');
                // Hide voice button if not supported
                if (this.elements.voiceInputBtn) {
                    this.elements.voiceInputBtn.style.display = 'none';
                }
                return;
            }

            this.recognition = new SpeechRecognition();
            this.recognition.continuous = true; // Keep listening
            this.recognition.interimResults = true; // Show interim results
            this.recognition.lang = 'en-US'; // Default language

            // Handle recognition results
            this.recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript + ' ';
                    } else {
                        interimTranscript += transcript;
                    }
                }

                // Update textarea with transcribed text
                if (this.elements.promptTextarea) {
                    const currentText = this.elements.promptTextarea.value;
                    if (finalTranscript) {
                        // Append final transcript
                        this.elements.promptTextarea.value = currentText + finalTranscript;
                        this.autoResizeTextarea();
                    } else if (interimTranscript && !currentText.endsWith(interimTranscript)) {
                        // Show interim results (optional - you can remove this if you only want final results)
                        w3a11yLog('Listening: ' + interimTranscript);
                    }
                }
            };

            // Handle errors
            this.recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.stopVoiceInput();

                let errorMessage = 'Voice input error: ';
                switch (event.error) {
                    case 'no-speech':
                        errorMessage += 'No speech detected. Please try again.';
                        break;
                    case 'audio-capture':
                        errorMessage += 'Microphone not found or not accessible.';
                        break;
                    case 'not-allowed':
                        errorMessage += 'Microphone permission denied.';
                        break;
                    default:
                        errorMessage += event.error;
                }

                this.showError(errorMessage);
            };

            // Handle end of recognition
            this.recognition.onend = () => {
                if (this.isListening) {
                    // If manually stopped, update state
                    this.stopVoiceInput();
                }
            };

            w3a11yLog('Voice recognition initialized');
        }

        /**
         * Toggle voice input on/off
         */
        toggleVoiceInput() {
            if (!this.recognition) {
                this.showError('Voice input is not supported in your browser.');
                return;
            }

            if (this.isListening) {
                this.stopVoiceInput();
            } else {
                this.startVoiceInput();
            }
        }

        /**
         * Start voice input
         */
        startVoiceInput() {
            if (!this.recognition) return;

            try {
                this.recognition.start();
                this.isListening = true;

                // Update button state
                if (this.elements.voiceInputBtn) {
                    this.elements.voiceInputBtn.classList.add('listening');
                    this.elements.voiceInputBtn.title = 'Stop voice input';
                }

                // Show notification
                if (window.W3A11YNotifications) {
                    window.W3A11YNotifications.show('Listening... Speak your prompt', 'info', 3000);
                }

                w3a11yLog('Voice input started');
            } catch (error) {
                console.error('Error starting voice recognition:', error);
                this.showError('Could not start voice input. Please try again.');
            }
        }

        /**
         * Stop voice input
         */
        stopVoiceInput() {
            if (!this.recognition) return;

            try {
                this.recognition.stop();
                this.isListening = false;

                // Update button state
                if (this.elements.voiceInputBtn) {
                    this.elements.voiceInputBtn.classList.remove('listening');
                    this.elements.voiceInputBtn.title = 'Voice input';
                }

                w3a11yLog('Voice input stopped');
            } catch (error) {
                console.error('Error stopping voice recognition:', error);
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Modal controls
            if (this.elements.closeBtn) {
                this.elements.closeBtn.addEventListener('click', () => this.closeModal());
            }
            if (this.elements.fullscreenBtn) {
                this.elements.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
            }
            if (this.elements.helpBtn) {
                this.elements.helpBtn.addEventListener('click', () => this.showHelp());
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => this.handleKeydown(e));

            // Click outside to close
            if (this.modal) {
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) {
                        this.closeModal();
                    }
                });
            }

            // Image controls
            if (this.elements.undoBtn) {
                this.elements.undoBtn.addEventListener('click', () => this.undo());
            }
            if (this.elements.redoBtn) {
                this.elements.redoBtn.addEventListener('click', () => this.redo());
            }
            if (this.elements.afterBtn) {
                this.elements.afterBtn.addEventListener('click', () => this.showAfter());
            }
            if (this.elements.beforeBtn) {
                this.elements.beforeBtn.addEventListener('click', () => this.showBefore());
            }
            // Edit Area button (may be disabled/commented out)
            if (this.elements.editAreaBtn) {
                this.elements.editAreaBtn.addEventListener('click', () => this.toggleEditArea());
            }
            if (this.elements.zoomInBtn) {
                this.elements.zoomInBtn.addEventListener('click', () => this.zoomIn());
            }
            if (this.elements.zoomOutBtn) {
                this.elements.zoomOutBtn.addEventListener('click', () => this.zoomOut());
            }
            if (this.elements.removeBgBtn) {
                this.elements.removeBgBtn.addEventListener('click', () => this.removeBackground());
            }

            // Prompt and reference images
            if (this.elements.voiceInputBtn) {
                this.elements.voiceInputBtn.addEventListener('click', () => this.toggleVoiceInput());
            }
            if (this.elements.attachReferenceBtn) {
                this.elements.attachReferenceBtn.addEventListener('click', () => this.elements.referenceFileInput.click());
            }
            if (this.elements.referenceFileInput) {
                this.elements.referenceFileInput.addEventListener('change', (e) => this.handleReferenceUpload(e));
            }
            if (this.elements.generateBtn) {
                this.elements.generateBtn.addEventListener('click', () => this.generateImage());
            }

            // Generation options (aspect ratio and style)
            const aspectRatioOptions = document.getElementById('w3a11y-aspect-ratio-options');
            if (aspectRatioOptions) {
                aspectRatioOptions.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-option-btn')) {
                        this.selectAspectRatio(e.target);
                    }
                });
            }

            const styleOptions = document.getElementById('w3a11y-style-options');
            if (styleOptions) {
                styleOptions.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-option-btn')) {
                        // Prevent style selection if Google Search is enabled
                        if (this.state.useGoogleSearch) {
                            w3a11yLog('Style options are disabled when Google Search is enabled');
                            return;
                        }
                        this.selectStyle(e.target);
                    }
                });
            }

            // Resolution options
            const resolutionOptions = document.getElementById('w3a11y-resolution-options');
            if (resolutionOptions) {
                resolutionOptions.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-option-btn')) {
                        this.selectResolution(e.target);
                    }
                });
            }

            // Google Search grounding checkbox
            const googleSearchCheckbox = document.getElementById('w3a11y-google-search-checkbox');
            if (googleSearchCheckbox) {
                googleSearchCheckbox.addEventListener('change', (e) => {
                    this.state.useGoogleSearch = e.target.checked;
                    w3a11yLog(`Google Search grounding ${this.state.useGoogleSearch ? 'enabled' : 'disabled'}`);

                    // Disable/enable style options when Google Search is toggled
                    this.toggleStyleOptions(!e.target.checked);
                });
            }

            // Format options
            if (this.elements.formatOptions) {
                this.elements.formatOptions.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-option-btn')) {
                        this.selectFormat(e.target);
                        // Enable Apply button when format changes
                        if (this.state.currentImage && this.elements.applyBtn) {
                            this.elements.applyBtn.disabled = false;
                        }
                    }
                });
            }

            // Quality slider
            if (this.elements.qualitySlider) {
                this.elements.qualitySlider.addEventListener('input', (e) => {
                    this.state.selectedQuality = parseInt(e.target.value);
                    if (this.elements.qualityValue) {
                        this.elements.qualityValue.textContent = this.state.selectedQuality;
                    }
                    // Enable Apply button when quality changes
                    if (this.state.currentImage && this.elements.applyBtn) {
                        this.elements.applyBtn.disabled = false;
                    }
                });
            }

            // Optimize & Convert checkbox toggle
            if (this.elements.optimizeConvertCheckbox) {
                this.elements.optimizeConvertCheckbox.addEventListener('change', (e) => {
                    this.toggleOptimizationOptions(e.target.checked);
                });
            }

            // Prompt textarea auto-resize
            if (this.elements.promptTextarea) {
                this.elements.promptTextarea.addEventListener('input', () => this.autoResizeTextarea());
            }

            // Inspiration tags (delegated event for dynamic content)
            if (this.elements.inspirationTags) {
                this.elements.inspirationTags.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-inspiration-tag')) {
                        this.useInspirationSuggestion(e.target.textContent);
                    }
                });
            }

            // History items (delegated event for dynamic content)
            if (this.elements.historyContent) {
                this.elements.historyContent.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-history-item')) {
                        this.useHistoryItem(e.target.textContent);
                    }
                });
            }

            // Footer actions
            if (this.elements.revertBtn) {
                this.elements.revertBtn.addEventListener('click', () => this.revertToOriginal());
            }
            if (this.elements.applyBtn) {
                this.elements.applyBtn.addEventListener('click', () => this.showSaveModal());
            }

            // Save modal
            if (this.elements.closeSaveModalBtn) {
                this.elements.closeSaveModalBtn.addEventListener('click', () => this.closeSaveModal());
            }
            if (this.elements.cancelSaveBtn) {
                this.elements.cancelSaveBtn.addEventListener('click', () => this.closeSaveModal());
            }
            if (this.elements.confirmSaveBtn) {
                this.elements.confirmSaveBtn.addEventListener('click', () => this.saveToMediaLibrary());
            }

            // Image selection functionality
            this.bindImageSelection();

            // Pan functionality
            this.bindImagePan();
        }

        /**
         * Bind image selection/cropping functionality
         */
        bindImageSelection() {
            let isSelecting = false;
            let startX, startY;

            if (this.elements.imageDisplay) {
                this.elements.imageDisplay.addEventListener('mousedown', (e) => {
                    // Check if edit area button exists and is active
                    if (!this.elements.editAreaBtn || !this.elements.editAreaBtn.classList.contains('active')) return;

                    isSelecting = true;
                    const rect = this.elements.imageDisplay.getBoundingClientRect();
                    startX = e.clientX - rect.left;
                    startY = e.clientY - rect.top;

                    this.elements.selectionBox.style.left = startX + 'px';
                    this.elements.selectionBox.style.top = startY + 'px';
                    this.elements.selectionBox.style.width = '0px';
                    this.elements.selectionBox.style.height = '0px';
                    this.elements.selectionBox.style.display = 'block';

                    e.preventDefault();
                });
            }

            document.addEventListener('mousemove', (e) => {
                if (!isSelecting) return;

                const rect = this.elements.imageDisplay.getBoundingClientRect();
                const currentX = e.clientX - rect.left;
                const currentY = e.clientY - rect.top;

                const width = Math.abs(currentX - startX);
                const height = Math.abs(currentY - startY);
                const left = Math.min(currentX, startX);
                const top = Math.min(currentY, startY);

                this.elements.selectionBox.style.left = left + 'px';
                this.elements.selectionBox.style.top = top + 'px';
                this.elements.selectionBox.style.width = width + 'px';
                this.elements.selectionBox.style.height = height + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!isSelecting) return;

                isSelecting = false;

                // Store selection area as percentages for API
                const imageRect = this.elements.mainImage.getBoundingClientRect();
                const selectionRect = this.elements.selectionBox.getBoundingClientRect();

                // Calculate selection relative to actual image dimensions (accounting for zoom/pan)
                const relativeX = ((selectionRect.left - imageRect.left) / imageRect.width) * 100;
                const relativeY = ((selectionRect.top - imageRect.top) / imageRect.height) * 100;
                const relativeWidth = (selectionRect.width / imageRect.width) * 100;
                const relativeHeight = (selectionRect.height / imageRect.height) * 100;

                // Validate selection is within image bounds
                if (relativeWidth < 1 || relativeHeight < 1) {
                    this.showError('Selection area is too small. Please select a larger area.');
                    this.elements.selectionBox.style.display = 'none';
                    this.state.selectionArea = null;
                    return;
                }

                this.state.selectionArea = {
                    x: Math.max(0, Math.min(100, relativeX)),
                    y: Math.max(0, Math.min(100, relativeY)),
                    width: Math.min(100 - relativeX, relativeWidth),
                    height: Math.min(100 - relativeY, relativeHeight)
                };

                w3a11yLog('Selection area captured:', this.state.selectionArea);

                // Show visual feedback
                const area = this.state.selectionArea;
                this.showSuccess(`Area selected: ${area.width.toFixed(0)}% × ${area.height.toFixed(0)}% at position (${area.x.toFixed(0)}%, ${area.y.toFixed(0)}%)`);
            });
        }

        /**
         * Bind image pan/drag functionality for zoomed images
         */
        bindImagePan() {
            let isPanning = false;
            let startX, startY;
            let startPanX, startPanY;

            if (this.elements.mainImage) {
                this.elements.mainImage.addEventListener('mousedown', (e) => {
                    // Only allow panning if zoomed in and not in edit area mode
                    const isEditAreaActive = this.elements.editAreaBtn && this.elements.editAreaBtn.classList.contains('active');
                    if (this.state.zoomLevel <= 1 || isEditAreaActive) {
                        return;
                    }

                    isPanning = true;
                    startX = e.clientX;
                    startY = e.clientY;
                    startPanX = this.state.panX;
                    startPanY = this.state.panY;

                    this.elements.mainImage.classList.add('w3a11y-panning');
                    e.preventDefault();
                });
            }

            document.addEventListener('mousemove', (e) => {
                if (!isPanning) return;

                const deltaX = e.clientX - startX;
                const deltaY = e.clientY - startY;

                // Calculate new pan positions
                const newPanX = startPanX + deltaX;
                const newPanY = startPanY + deltaY;

                // Apply boundary constraints to keep image within container
                const constraints = this.calculatePanConstraints();
                this.state.panX = Math.max(constraints.minX, Math.min(constraints.maxX, newPanX));
                this.state.panY = Math.max(constraints.minY, Math.min(constraints.maxY, newPanY));

                this.applyZoom(); // Apply both zoom and pan
            });

            document.addEventListener('mouseup', () => {
                if (!isPanning) return;

                isPanning = false;
                this.elements.mainImage.classList.remove('w3a11y-panning');
            });

            // Double-click to reset zoom and pan
            if (this.elements.mainImage) {
                this.elements.mainImage.addEventListener('dblclick', (e) => {
                    const isEditAreaActive = this.elements.editAreaBtn && this.elements.editAreaBtn.classList.contains('active');
                    if (isEditAreaActive) {
                        return; // Don't interfere with area selection
                    }

                    this.state.zoomLevel = 1;
                    this.state.panX = 0;
                    this.state.panY = 0;
                    this.applyZoom();
                    e.preventDefault();
                });
            }
        }

        /**
         * Open modal in specified mode
         */
        openModal(mode = 'generate', attachmentId = null) {
            this.state.mode = mode;
            this.state.attachmentId = attachmentId;
            this.state.isOpen = true;

            // Update modal mode
            this.elements.modalMode.value = mode;

            // Reset state
            this.resetModal();

            // Initialize aspect ratio and style from pre-selected buttons (admin settings)
            this.initializeGenerationOptions();

            // Load credits immediately when modal opens
            this.loadCredits();

            // Load attachment if editing
            if (mode === 'edit' && attachmentId) {
                this.loadAttachment(attachmentId);
                this.elements.generateBtn.querySelector('.w3a11y-btn-text').textContent = 'Edit';
            }

            // Handle generation options based on mode
            // this.handleGenerationOptionsForMode(mode);

            // Show modal
            this.modal.style.display = 'flex';
            this.modal.style.opacity = '0';
            this.modal.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                this.modal.style.opacity = '1';
            }, 10);

            // Focus management
            this.elements.promptTextarea.focus();

            w3a11yLog(`Modal opened in ${mode} mode`, attachmentId);
        }

        /**
         * Close the modal
         */
        closeModal() {
            if (this.state.isProcessing) {
                if (!confirm(w3a11yArtisan.texts.confirm_close_processing)) {
                    return;
                }
            }

            // Check if any images were saved during this session
            const shouldReload = this.state.hasSavedImages;

            this.modal.style.opacity = '0';
            setTimeout(() => {
                this.modal.style.display = 'none';

                // Reload page if images were saved to show them in media library
                if (shouldReload) {
                    w3a11yLog('[MODAL CLOSE] Images were saved, reloading page to refresh media library');
                    window.location.reload();
                }
            }, 300);
            this.state.isOpen = false;
            this.resetModal();

            w3a11yLog('Modal closed');
        }

        /**
         * Initialize generation options (aspect ratio and style) from pre-selected buttons
         * This reads the admin settings that were set via PHP template
         */
        initializeGenerationOptions() {
            // Find pre-selected aspect ratio button
            const aspectRatioOptions = document.getElementById('w3a11y-aspect-ratio-options');
            if (aspectRatioOptions) {
                const activeAspectBtn = aspectRatioOptions.querySelector('.w3a11y-option-btn.active');
                if (activeAspectBtn) {
                    const aspectRatio = activeAspectBtn.getAttribute('data-aspect');
                    const width = parseInt(activeAspectBtn.getAttribute('data-width'));
                    const height = parseInt(activeAspectBtn.getAttribute('data-height'));

                    this.state.selectedAspectRatio = aspectRatio;
                    this.state.imageDimensions = { width, height };

                    w3a11yLog(`[INIT] Initialized aspect ratio from settings: ${aspectRatio} (${width}x${height})`);
                }
            }

            // Find pre-selected style button
            const styleOptions = document.getElementById('w3a11y-style-options');
            if (styleOptions) {
                const activeStyleBtn = styleOptions.querySelector('.w3a11y-option-btn.active');
                if (activeStyleBtn) {
                    const style = activeStyleBtn.getAttribute('data-style');
                    this.state.selectedStyle = style;

                    w3a11yLog(`[INIT] Initialized style from settings: ${style}`);
                }
            }

            // Initialize format selection (default PNG)
            const formatOptions = document.getElementById('w3a11y-format-options');
            if (formatOptions) {
                const pngFormatBtn = formatOptions.querySelector('.w3a11y-option-btn[data-format="png"]');
                if (pngFormatBtn) {
                    pngFormatBtn.classList.add('active');
                    this.state.selectedFormat = 'png';
                    w3a11yLog(`[INIT] Initialized format: png (default)`);
                }
            }

            // Initialize quality slider (default 90)
            if (this.elements.qualitySlider && this.elements.qualityValue) {
                this.elements.qualitySlider.value = 90;
                this.elements.qualityValue.textContent = '90';
                this.state.selectedQuality = 90;
                w3a11yLog(`[INIT] Initialized quality: 90 (default)`);
            }
        }

        /**
         * Reset modal to initial state
         */
        resetModal() {
            // Clear images
            this.elements.mainImage.style.display = 'none';
            this.elements.mainImage.src = '';
            this.elements.imagePlaceholder.style.display = 'flex';
            this.elements.selectionBox.style.display = 'none';
            this.elements.loadingOverlay.style.display = 'none';

            // Hide image controls initially (show only after image generation)
            const imageControls = document.querySelector('.w3a11y-image-controls');
            if (imageControls) {
                imageControls.classList.remove('show');
            }

            // Clear prompt and reference images
            this.elements.promptTextarea.value = '';
            this.state.referenceImages = [];
            this.elements.referencePreviewContainer.style.display = 'none';
            this.elements.referenceImagesBase64.value = '';
            this.renderReferenceImages();

            // Clear inspiration and history
            this.elements.inspirationTags.innerHTML = '';
            this.elements.inspirationPlaceholder.style.display = 'block';

            // Reset buttons
            this.elements.generateBtn.disabled = false;
            this.elements.applyBtn.style.display = 'none';
            this.elements.revertBtn.style.display = 'none';
            this.elements.undoBtn.disabled = true;
            this.elements.redoBtn.disabled = true;
            this.elements.afterBtn.classList.add('active');
            this.elements.afterBtn.disabled = true;
            this.elements.beforeBtn.classList.remove('active');
            this.elements.beforeBtn.disabled = true;
            // Remove active class from edit area button if it exists
            if (this.elements.editAreaBtn) {
                this.elements.editAreaBtn.classList.remove('active');
            }

            // Reset zoom and pan
            this.state.zoomLevel = 1;
            this.state.panX = 0;
            this.state.panY = 0;
            this.elements.mainImage.classList.remove('w3a11y-pannable', 'w3a11y-panning');
            this.updateZoomButtons();

            // Clear hidden data
            this.elements.currentImageBase64.value = '';
            this.elements.originalImageBase64.value = '';
            this.elements.currentAttachmentId.value = '';

            // Clear stored original user prompt
            this.state.originalUserPrompt = null;

            // Clear image history for undo/redo
            this.clearImageHistory();

            // Reset save tracking flag
            this.state.hasSavedImages = false;

            // Reset optimization checkbox (unchecked by default)
            if (this.elements.optimizeConvertCheckbox) {
                this.elements.optimizeConvertCheckbox.checked = false;
                this.toggleOptimizationOptions(false);
            }

            this.state.isProcessing = false;
        }

        /**
         * Load attachment for editing
         */
        loadAttachment(attachmentId) {
            // Prevent duplicate requests for the same attachment
            const requestKey = `attachment_${attachmentId}`;
            const now = Date.now();

            if (this.lastRequests && this.lastRequests[requestKey] && (now - this.lastRequests[requestKey]) < 1000) {
                w3a11yLog('W3A11Y: Preventing duplicate attachment request for', attachmentId);
                return;
            }

            if (!this.lastRequests) {
                this.lastRequests = {};
            }
            this.lastRequests[requestKey] = now;

            this.showLoading(w3a11yArtisan.texts.loading_image);

            const formData = new FormData();
            formData.append('action', 'w3a11y_get_attachment_data');
            formData.append('attachment_id', attachmentId);
            formData.append('nonce', w3a11yArtisan.nonce);

            fetch(w3a11yArtisan.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        const data = response.data;

                        // Debug logging
                        w3a11yLog('W3A11Y Debug: Attachment data received', {
                            attachment_id: data.attachment_id,
                            image_hash: data.image_hash,
                            has_base64: !!data.image_base64,
                            base64_length: data.image_base64 ? data.image_base64.length : 0
                        });

                        // Set images
                        this.elements.mainImage.src = data.image_url;
                        this.elements.mainImage.style.display = 'block';
                        this.elements.imagePlaceholder.style.display = 'none';
                        this.elements.applyBtn.style.display = 'block';
                        this.elements.applyBtn.disabled = true;
                        this.elements.revertBtn.style.display = 'flex';
                        this.elements.revertBtn.disabled = true;

                        // Show image controls since we have an image loaded
                        const imageControls = document.querySelector('.w3a11y-image-controls');
                        if (imageControls) {
                            imageControls.classList.add('show');
                        }

                        // Store data
                        this.state.currentImage = data.image_base64;
                        this.state.originalImage = data.image_base64;
                        this.state.imageHash = data.image_hash; // Store server-provided hash
                        this.elements.currentImageBase64.value = data.image_base64;
                        this.elements.originalImageBase64.value = data.image_base64;
                        this.elements.currentAttachmentId.value = attachmentId;

                        // Add initial state to image history
                        this.clearImageHistory(); // Clear any previous history
                        this.addImageToHistory(data.image_base64, 'load', `Loaded: ${data.title || 'Attachment ' + attachmentId}`);

                        // Pre-fill save form
                        const filename = data.title || `edited-image-${Date.now()}.jpg`;
                        this.elements.saveFilename.value = filename;
                        this.elements.saveTitle.value = data.title || '';
                        this.elements.saveAltText.value = data.alt_text || '';

                        // Load inspiration
                        this.loadInspiration(data.image_base64);

                        // Load prompt history for this image
                        this.loadPromptHistory(attachmentId, data.image_hash);

                        this.hideLoading();
                    } else {
                        this.showError(response.data.message);
                        this.hideLoading();
                    }
                })
                .catch(() => {
                    this.showError(w3a11yArtisan.texts.error_generic);
                    this.hideLoading();
                });
        }

        /**
         * Generate image from prompt
         */
        generateImage() {
            const prompt = this.elements.promptTextarea.value.trim();

            if (!prompt) {
                this.showError('Please enter a prompt');
                return;
            }

            if (prompt.length < 10) {
                this.showError('Please provide a more detailed prompt (minimum 10 characters)');
                return;
            }

            // Store original user prompt for history (before any AI refinement)
            this.state.originalUserPrompt = prompt;

            this.state.isProcessing = true;
            this.showLoading(w3a11yArtisan.texts.generating);
            this.elements.generateBtn.disabled = true;
            this.showButtonSpinner(this.elements.generateBtn);

            // Smart mode detection: If there's already a generated image, switch to edit mode automatically
            // This ensures consistency - once an image exists, subsequent prompts edit it rather than generate new ones
            const hasGeneratedImage = this.state.currentImage && this.state.mode === 'generate';
            const effectiveMode = hasGeneratedImage ? 'edit' : this.state.mode;

            if (hasGeneratedImage) {
                w3a11yLog('[SMART MODE] Image detected in generate mode - automatically switching to edit mode for consistency');
            }

            const requestData = {
                action: effectiveMode === 'generate' ? 'w3a11y_artisan_generate' : 'w3a11y_artisan_edit',
                prompt: prompt,
                nonce: w3a11yArtisan.nonce
            };

            // Add generation options (aspect ratio, style, resolution, and Google Search)
            requestData.aspect_ratio = this.state.selectedAspectRatio;
            requestData.style = this.state.selectedStyle;
            requestData.width = this.state.imageDimensions.width;
            requestData.height = this.state.imageDimensions.height;
            requestData.resolution = this.state.selectedResolution;
            requestData.use_google_search = this.state.useGoogleSearch;

            // Add current image for editing (either explicit edit mode or auto-detected)
            if ((effectiveMode === 'edit' || hasGeneratedImage) && this.state.currentImage) {
                requestData.image_base64 = this.state.currentImage;
                requestData.edit_type = this.determineEditType(prompt);

                // Add attachment_id if editing an existing attachment
                if (this.state.attachmentId) {
                    requestData.attachment_id = this.state.attachmentId;
                }
            }

            // Add reference images if provided (max 13)
            if (this.state.referenceImages.length > 0) {
                const base64Array = this.state.referenceImages.map(img => img.base64);
                requestData.referenceImagesBase64 = JSON.stringify(base64Array); // Convert array to JSON string
                w3a11yLog('[JS DEBUG] Sending reference images:', base64Array.length, 'images');
            } else {
                w3a11yLog('[JS DEBUG] No reference images to send');
            }

            // Add selection area if available
            if (this.state.selectionArea) {
                requestData.selection_area = this.state.selectionArea;
                w3a11yLog('[AREA EDIT] Sending selection area to API:', this.state.selectionArea);
            } else if (this.state.mode === 'edit' && this.elements.editAreaBtn && this.elements.editAreaBtn.classList.contains('active')) {
                // Warn user they activated area selection but didn't select an area
                this.showError('Please select an area on the image first, or disable "Edit an Area" mode to edit the entire image.');
                this.state.isProcessing = false;
                this.hideLoading();
                this.elements.generateBtn.disabled = false;
                this.hideButtonSpinner(this.elements.generateBtn);
                return;
            }

            // Create form data
            const formData = new FormData();
            Object.keys(requestData).forEach(key => {
                formData.append(key, requestData[key]);
            });

            // Send AJAX request
            fetch(w3a11yArtisan.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        this.handleGenerationSuccess(response.data);
                    } else {
                        const errorMessage = response.data.message || 'Unknown error occurred';

                        // Check for insufficient credits (402 error)
                        if (response.data.status_code === 402 ||
                            errorMessage.toLowerCase().includes('insufficient credits') ||
                            errorMessage.toLowerCase().includes('no credits') ||
                            errorMessage.toLowerCase().includes('not enough credits')) {

                            this.showError(errorMessage, 'error', response.data.status_code || 402, response.data.available_credits || 0);
                        } else {
                            // Use modal's own error display for other errors
                            this.showError(errorMessage);
                        }
                    }
                })
                .catch(() => {
                    this.showError(w3a11yArtisan.texts.error_generic);
                })
                .finally(() => {
                    this.state.isProcessing = false;
                    this.hideLoading();
                    this.elements.generateBtn.disabled = false;
                    this.hideButtonSpinner(this.elements.generateBtn);
                });
        }

        /**
         * Handle successful image generation
         */
        handleGenerationSuccess(data) {
            // Update image - Gemini always generates PNG format
            let imageDataUrl = data.imageBase64;
            if (!imageDataUrl.startsWith('data:image/')) {
                // Raw base64 - Gemini output is always PNG
                imageDataUrl = `data:image/png;base64,${imageDataUrl}`;
            }
            this.elements.mainImage.src = imageDataUrl;
            this.elements.mainImage.style.display = 'block';
            this.elements.imagePlaceholder.style.display = 'none';

            // Show image controls now that image is generated
            const imageControls = document.querySelector('.w3a11y-image-controls');
            if (imageControls) {
                imageControls.classList.add('show');
            }

            // Store current image
            this.state.currentImage = data.imageBase64;
            this.elements.currentImageBase64.value = data.imageBase64;

            // Store original if first generation
            if (!this.state.originalImage) {
                this.state.originalImage = data.imageBase64;
                this.elements.originalImageBase64.value = data.imageBase64;
            }

            // Add to prompt history (use original user prompt, not refined prompt)
            this.addToHistory(this.state.originalUserPrompt || this.elements.promptTextarea.value);

            // Add to image history for undo/redo (also use original prompt)
            const operation = this.state.mode === 'generate' ? 'generate' : 'edit';
            const originalPrompt = this.state.originalUserPrompt || this.elements.promptTextarea.value;
            this.addImageToHistory(data.imageBase64, operation, originalPrompt);

            // Update credits
            if (data.creditsRemaining !== undefined) {
                this.updateCredits(data.creditsRemaining);
            }

            // Enable apply button and image controls
            this.elements.applyBtn.style.display = 'block';
            this.elements.applyBtn.disabled = false;
            this.elements.revertBtn.style.display = 'flex';
            this.elements.revertBtn.disabled = false;
            this.elements.afterBtn.disabled = false;
            this.elements.beforeBtn.disabled = false;

            // Load inspiration suggestions via separate API call
            // This is faster and more reliable than combined generation
            this.loadInspiration(data.imageBase64);

            // Clear selection
            this.elements.selectionBox.style.display = 'none';
            this.state.selectionArea = null;
            if (this.elements.editAreaBtn) {
                this.elements.editAreaBtn.classList.remove('active');
            }

            this.elements.generateBtn.querySelector('.w3a11y-btn-text').textContent = 'Edit';

            w3a11yLog('Image generation successful');
        }

        /**
         * Load AI-powered inspiration suggestions
         */
        loadInspiration(imageBase64) {
            if (!imageBase64) return;

            // Prevent duplicate requests for the same image
            const imageHash = btoa(imageBase64.substring(0, 100)); // Use first 100 chars as quick hash
            const requestKey = `inspiration_${imageHash}`;
            const now = Date.now();

            if (this.lastRequests && this.lastRequests[requestKey] && (now - this.lastRequests[requestKey]) < 2000) {
                w3a11yLog('W3A11Y: Preventing duplicate inspiration request');
                return;
            }

            if (!this.lastRequests) {
                this.lastRequests = {};
            }
            this.lastRequests[requestKey] = now;

            this.elements.inspirationPlaceholder.style.display = 'none';
            this.elements.inspirationLoading.style.display = 'block';
            this.elements.inspirationTags.innerHTML = '';

            const formData = new FormData();
            formData.append('action', 'w3a11y_artisan_inspire');
            formData.append('image_base64', imageBase64);
            formData.append('nonce', w3a11yArtisan.nonce);

            fetch(w3a11yArtisan.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(response => {
                    // Handle double-nested data structure from WordPress
                    const actualData = (response.success && response.data && response.data.data) ? response.data.data : response.data;

                    if (response.success && actualData && actualData.suggestions) {
                        this.renderInspirationTags(actualData.suggestions);

                        // Show rate limit info if available (for transparency)
                        if (actualData.rateLimit && actualData.rateLimit.remaining !== null) {
                            const remaining = actualData.rateLimit.remaining;
                            const isFree = actualData.rateLimit.isFree;
                            if (!isFree && remaining <= 5) {
                                w3a11yLog(`[INSPIRE] Warning: Only ${remaining} inspire calls remaining`);
                            }
                        }
                    } else {
                        // Show placeholder if no suggestions
                        this.elements.inspirationPlaceholder.style.display = 'block';
                    }
                })
                .catch((error) => {
                    console.error('[INSPIRE ERROR]', error);
                    // Show placeholder on error
                    this.elements.inspirationPlaceholder.style.display = 'block';
                })
                .finally(() => {
                    this.elements.inspirationLoading.style.display = 'none';
                });
        }

        /**
         * Render inspiration tags
         */
        renderInspirationTags(suggestions) {
            this.elements.inspirationTags.innerHTML = '';

            suggestions.forEach(suggestion => {
                const tag = document.createElement('button');
                tag.className = 'w3a11y-inspiration-tag';
                tag.setAttribute('type', 'button');
                tag.textContent = suggestion.suggestion || suggestion;
                tag.setAttribute('data-category', suggestion.category || 'general');

                this.elements.inspirationTags.appendChild(tag);
            });

            if (suggestions.length === 0) {
                this.elements.inspirationPlaceholder.style.display = 'block';
            }
        }

        /**
         * Use inspiration suggestion
         */
        useInspirationSuggestion(suggestion) {
            const currentPrompt = this.elements.promptTextarea.value.trim();
            const newPrompt = currentPrompt ? `${currentPrompt}, ${suggestion}` : suggestion;

            this.elements.promptTextarea.value = newPrompt;
            this.autoResizeTextarea();

            // Focus on textarea
            this.elements.promptTextarea.focus();
        }

        /**
         * Load prompt history for specific image
         */
        loadPromptHistory(attachmentId = null, imageHash = null) {
            // Prevent duplicate requests
            const requestKey = `history_${attachmentId || 'null'}_${imageHash || 'null'}`;
            const now = Date.now();

            if (this.lastRequests && this.lastRequests[requestKey] && (now - this.lastRequests[requestKey]) < 1000) {
                w3a11yLog('W3A11Y: Preventing duplicate prompt history request');
                return;
            }

            if (!this.lastRequests) {
                this.lastRequests = {};
            }
            this.lastRequests[requestKey] = now;

            let requestData = {
                action: 'w3a11y_get_prompt_history',
                nonce: w3a11yArtisan.nonce,
                limit: 10
            };

            // Add image identifiers if available
            if (attachmentId) {
                requestData.attachment_id = attachmentId;
            }

            if (imageHash) {
                requestData.image_hash = imageHash;
            }

            // Debug logging
            w3a11yLog('W3A11Y Debug: Loading prompt history with', {
                attachment_id: attachmentId,
                image_hash: imageHash,
                requestData: requestData
            });

            const formData = new FormData();
            Object.keys(requestData).forEach(key => {
                formData.append(key, requestData[key]);
            });

            fetch(w3a11yArtisan.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(response => {
                    if (response.success && response.data.prompts) {
                        // Update local history with database prompts
                        this.state.history = response.data.prompts.map(item => item.prompt);
                        this.renderHistory();
                    } else {
                        // Fallback to empty history
                        this.state.history = [];
                        this.renderHistory();
                    }
                })
                .catch(() => {
                    // Fallback to empty history
                    this.state.history = [];
                    this.renderHistory();
                });
        }



        /**
         * Add prompt to history
         */
        addToHistory(prompt) {
            // Remove from history if already exists
            this.state.history = this.state.history.filter(item => item !== prompt);

            // Add to beginning
            this.state.history.unshift(prompt);

            // Keep only last 5 items
            this.state.history = this.state.history.slice(0, 5);

            // Update UI
            this.renderHistory();
        }

        /**
         * Render history items
         */
        renderHistory() {
            const historyContainer = this.elements.historyContent;
            historyContainer.innerHTML = '';

            if (this.state.history.length === 0) {
                historyContainer.innerHTML = '<div class="w3a11y-history-placeholder"><p>Your recent prompts will appear here</p></div>';
                return;
            }

            this.state.history.forEach(prompt => {
                const item = document.createElement('div');
                item.className = 'w3a11y-history-item';
                item.textContent = `"${prompt}"`;

                historyContainer.appendChild(item);
            });
        }

        /**
         * Use history item
         */
        useHistoryItem(historyText) {
            // Remove quotes from history text
            const prompt = historyText.replace(/^"|"$/g, '');
            if (this.elements.promptTextarea) {
                this.elements.promptTextarea.value = prompt;
                this.autoResizeTextarea();
                this.elements.promptTextarea.focus();
            }
        }

        /**
         * Handle reference images upload (supports multiple files, max 13)
         */
        handleReferenceUpload(e) {
            const files = Array.from(e.target.files);
            if (!files.length) return;

            // Check if adding these files would exceed the limit
            const totalFiles = this.state.referenceImages.length + files.length;
            if (totalFiles > 13) {
                const allowedCount = 13 - this.state.referenceImages.length;
                this.showError(`You can only upload up to 13 reference images. You can add ${allowedCount} more.`);
                return;
            }

            // Process each file
            files.forEach((file, index) => {
                // Validate file type
                if (!file.type.match(/^image\/(jpeg|jpg|png|webp)$/)) {
                    this.showError(`File "${file.name}" is not a valid image format. Please select JPEG, PNG or WebP files.`);
                    return;
                }

                // Validate file size (max 10MB before resizing)
                if (file.size > 10 * 1024 * 1024) {
                    this.showError(`File "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(1)}MB). Please select files smaller than 10MB.`);
                    return;
                }

                // Process the image with resizing
                this.processReferenceImage(file);
            });

            // Clear the file input for future selections
            e.target.value = '';
        }

        /**
         * Process a single reference image with client-side resizing
         */
        async processReferenceImage(file) {
            try {
                // Resize the image to fit within 1024x1024
                const resizedBase64 = await this.resizeImageToBase64(file, 1024, 1024);

                // Create reference image object
                const referenceImage = {
                    id: Date.now() + Math.random(), // Unique ID
                    name: file.name,
                    base64: resizedBase64,
                    originalSize: file.size,
                    type: file.type
                };

                // Add to state
                this.state.referenceImages.push(referenceImage);

                // Update UI
                this.renderReferenceImages();
                this.updateReferenceImagesData();

            } catch (error) {
                console.error('Error processing reference image:', error);
                this.showError(`Failed to process image "${file.name}". Please try again.`);
            }
        }

        /**
         * Client-side image resizing utility
         * Resizes image to fit within maxWidth x maxHeight while maintaining aspect ratio
         */
        async resizeImageToBase64(file, maxWidth = 1024, maxHeight = 1024) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                img.onload = () => {
                    try {
                        // Calculate new dimensions while maintaining aspect ratio
                        let { width, height } = img;

                        if (width > maxWidth || height > maxHeight) {
                            const aspectRatio = width / height;

                            if (width > height) {
                                width = maxWidth;
                                height = maxWidth / aspectRatio;
                            } else {
                                height = maxHeight;
                                width = maxHeight * aspectRatio;
                            }

                            // Ensure we don't exceed the other dimension
                            if (height > maxHeight) {
                                height = maxHeight;
                                width = maxHeight * aspectRatio;
                            }
                            if (width > maxWidth) {
                                width = maxWidth;
                                height = maxWidth / aspectRatio;
                            }
                        }

                        // Set canvas dimensions
                        canvas.width = Math.round(width);
                        canvas.height = Math.round(height);

                        // Draw and resize image
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                        // Get base64 with good quality
                        const quality = 0.8; // 80% quality for good balance
                        const base64 = canvas.toDataURL('image/jpeg', quality);

                        // Return just the base64 data without the data:image prefix
                        resolve(base64.split(',')[1]);
                    } catch (error) {
                        reject(error);
                    }
                };

                img.onerror = () => {
                    reject(new Error('Failed to load image'));
                };

                // Load the image
                img.src = URL.createObjectURL(file);
            });
        }

        /**
         * Render reference images in the UI
         */
        renderReferenceImages() {
            if (!this.elements.referenceImages) return;

            const container = this.elements.referenceImages;
            container.innerHTML = '';

            // Update count
            if (this.elements.referenceCount) {
                this.elements.referenceCount.textContent = `${this.state.referenceImages.length}/13`;
            }

            if (this.state.referenceImages.length === 0) {
                this.elements.referencePreviewContainer.style.display = 'none';
                return;
            }

            this.elements.referencePreviewContainer.style.display = 'block';

            this.state.referenceImages.forEach((refImage, index) => {
                const imageContainer = document.createElement('div');
                imageContainer.className = 'w3a11y-reference-item';
                imageContainer.innerHTML = `
                    <img src="data:image/jpeg;base64,${refImage.base64}" 
                         alt="Reference image ${index + 1}" 
                         class="w3a11y-reference-thumbnail" />
                    <div class="w3a11y-reference-overlay">
                        <button type="button" class="w3a11y-reference-remove" 
                                data-ref-id="${refImage.id}" 
                                title="Remove this reference image">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="w3a11y-reference-info">
                        <span class="w3a11y-reference-name">${refImage.name}</span>
                    </div>
                `;

                // Add remove event listener
                const removeBtn = imageContainer.querySelector('.w3a11y-reference-remove');
                removeBtn.addEventListener('click', () => {
                    this.removeReferenceImage(refImage.id);
                });

                container.appendChild(imageContainer);
            });
        }

        /**
         * Remove a specific reference image
         */
        removeReferenceImage(imageId) {
            this.state.referenceImages = this.state.referenceImages.filter(img => img.id !== imageId);
            this.renderReferenceImages();
            this.updateReferenceImagesData();
        }

        /**
         * Update hidden form data with reference images
         */
        updateReferenceImagesData() {
            if (this.elements.referenceImagesBase64) {
                const base64Array = this.state.referenceImages.map(img => img.base64);
                this.elements.referenceImagesBase64.value = JSON.stringify(base64Array);
            }
        }

        /**
         * Clear all reference images
         */
        clearAllReferenceImages() {
            this.state.referenceImages = [];
            this.renderReferenceImages();
            this.updateReferenceImagesData();
            this.elements.referenceFileInput.value = '';
        }

        /**
         * Select aspect ratio and calculate dimensions
         */
        selectAspectRatio(button) {
            // Remove active class from all aspect ratio buttons
            const aspectRatioButtons = document.querySelectorAll('#w3a11y-aspect-ratio-options .w3a11y-option-btn');
            aspectRatioButtons.forEach(btn => btn.classList.remove('active'));

            // Add active class to selected button
            button.classList.add('active');

            // Get aspect ratio and dimensions from data attributes
            this.state.selectedAspectRatio = button.getAttribute('data-aspect');
            this.state.imageDimensions = {
                width: parseInt(button.getAttribute('data-width')),
                height: parseInt(button.getAttribute('data-height'))
            };

            w3a11yLog(`Selected aspect ratio: ${this.state.selectedAspectRatio} (${this.state.imageDimensions.width}x${this.state.imageDimensions.height})`);
        }

        /**
         * Calculate dimensions for aspect ratio as per Gemini API official specs
         */
        calculateDimensions(aspectRatio) {
            // Official Gemini API aspect ratio dimensions
            const dimensions = {
                '1:1': { width: 1024, height: 1024 },
                '2:3': { width: 832, height: 1248 },
                '3:2': { width: 1248, height: 832 },
                '3:4': { width: 864, height: 1184 },
                '4:3': { width: 1184, height: 864 },
                '4:5': { width: 896, height: 1152 },
                '5:4': { width: 1152, height: 896 },
                '9:16': { width: 768, height: 1344 },
                '16:9': { width: 1344, height: 768 },
                '21:9': { width: 1536, height: 672 }
            };

            return dimensions[aspectRatio] || { width: 1024, height: 1024 };
        }

        /**
         * Select style
         */
        selectStyle(button) {
            // Remove active class from all style buttons
            const styleButtons = document.querySelectorAll('#w3a11y-style-options .w3a11y-option-btn');
            styleButtons.forEach(btn => btn.classList.remove('active'));

            // Add active class to selected button
            button.classList.add('active');

            // Get selected style
            this.state.selectedStyle = button.getAttribute('data-style');

            w3a11yLog(`Selected style: ${this.state.selectedStyle}`);

            // If logo style is selected, auto-select PNG format with transparency
            if (this.state.selectedStyle === 'logo') {
                const pngButton = document.querySelector('#w3a11y-format-options .w3a11y-option-btn[data-format="png"]');
                if (pngButton && !pngButton.classList.contains('active')) {
                    this.selectFormat(pngButton);
                }
            }
        }

        /**
         * Toggle style options (enable/disable)
         * @param {boolean} enabled - Whether style options should be enabled
         */
        toggleStyleOptions(enabled) {
            const styleButtons = document.querySelectorAll('#w3a11y-style-options .w3a11y-option-btn');

            if (enabled) {
                // Enable style options
                styleButtons.forEach(btn => {
                    btn.classList.remove('disabled');
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    btn.style.pointerEvents = 'auto';
                });
            } else {
                // Disable style options and clear selection
                styleButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.classList.add('disabled');
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                    btn.style.pointerEvents = 'none';
                });

                // Clear selected style
                this.state.selectedStyle = null;
                w3a11yLog('Style options disabled - Google Search requires original prompt');
            }
        }

        /**
         * Select resolution (1K, 2K, 4K)
         */
        selectResolution(button) {
            // Remove active class from all resolution buttons
            const resolutionButtons = document.querySelectorAll('#w3a11y-resolution-options .w3a11y-option-btn');
            resolutionButtons.forEach(btn => btn.classList.remove('active'));

            // Add active class to selected button
            button.classList.add('active');

            // Get selected resolution
            this.state.selectedResolution = button.getAttribute('data-resolution');

            w3a11yLog(`Selected resolution: ${this.state.selectedResolution}`);
        }

        /**
         * Toggle visibility of optimization options (format, quality, background removal)
         */
        toggleOptimizationOptions(show) {
            if (this.elements.formatGroup) {
                this.elements.formatGroup.style.display = show ? 'block' : 'none';
            }
            if (this.elements.qualityGroup) {
                this.elements.qualityGroup.style.display = show ? 'block' : 'none';
            }

            w3a11yLog(`[OPTIMIZE] Optimization options ${show ? 'shown' : 'hidden'}`);
        }

        /**
         * Select output format
         */
        selectFormat(button) {
            // Remove active class from all format buttons
            const formatButtons = document.querySelectorAll('#w3a11y-format-options .w3a11y-option-btn');
            formatButtons.forEach(btn => btn.classList.remove('active'));

            // Add active class to selected button
            button.classList.add('active');

            // Update state
            this.state.selectedFormat = button.getAttribute('data-format');

            // Update filename extension in save modal if it's open
            if (this.elements.saveModal && this.elements.saveModal.style.display === 'block') {
                const currentFilename = this.elements.saveFilename.value;
                if (currentFilename) {
                    // Replace extension with new format
                    const nameWithoutExt = currentFilename.replace(/\.(jpg|jpeg|png|webp)$/i, '');
                    const ext = this.state.selectedFormat === 'jpeg' ? 'jpg' : this.state.selectedFormat;
                    this.elements.saveFilename.value = `${nameWithoutExt}.${ext}`;
                }
            }

            w3a11yLog('Selected format:', this.state.selectedFormat);
        }

        /**
         * Show save modal
         */
        showSaveModal() {
            if (!this.state.currentImage) {
                this.showError('No image to save');
                return;
            }

            // Always regenerate filename with correct extension based on current selected format
            // This ensures format changes in sidebar are reflected in the filename
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const ext = this.state.selectedFormat === 'jpeg' ? 'jpg' : this.state.selectedFormat;
            this.elements.saveFilename.value = `w3a11y-artisan-${timestamp}.${ext}`;
            // Add blur event listener to auto-fill filename if left empty
            if (!this.elements.saveFilename.hasBlurListener) {
                this.elements.saveFilename.addEventListener('blur', () => {
                    if (!this.elements.saveFilename.value.trim()) {
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                        const ext = this.state.selectedFormat === 'jpeg' ? 'jpg' : this.state.selectedFormat;
                        this.elements.saveFilename.value = `w3a11y-artisan-${timestamp}.${ext}`;
                    }
                });
                this.elements.saveFilename.hasBlurListener = true; // Prevent duplicate listeners
            }

            // Show replace option for edit mode
            const replaceOption = document.getElementById('w3a11y-replace-option');
            if (this.state.mode === 'edit' && this.state.attachmentId) {
                if (replaceOption) {
                    replaceOption.style.display = 'block';
                }
            } else {
                if (replaceOption) {
                    replaceOption.style.display = 'none';
                }
            }

            this.elements.saveModal.style.display = 'block';
            this.elements.saveModal.style.opacity = '0';
            this.elements.saveModal.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                this.elements.saveModal.style.opacity = '1';
            }, 10);
            this.elements.saveFilename.focus();
        }

        /**
         * Close save modal
         */
        closeSaveModal() {
            this.elements.saveModal.style.opacity = '0';
            setTimeout(() => {
                this.elements.saveModal.style.display = 'none';
            }, 300);
        }

        /**
         * Save image to media library
         */
        async saveToMediaLibrary() {
            const filename = this.elements.saveFilename.value.trim();
            const title = this.elements.saveTitle.value.trim();
            const altText = this.elements.saveAltText.value.trim();
            const replaceExisting = this.elements.replaceExisting.checked;
            const shouldOptimize = this.elements.optimizeConvertCheckbox && this.elements.optimizeConvertCheckbox.checked;

            if (!filename) {
                this.showError('Please enter a filename');
                return;
            }

            this.showButtonSpinner(this.elements.confirmSaveBtn);
            this.elements.confirmSaveBtn.disabled = true;

            try {
                // Step 1: Convert/optimize only if user has enabled the checkbox
                let finalImageBase64 = this.state.currentImage;

                if (shouldOptimize) {
                    w3a11yLog(`[SAVE] Optimizing: Converting to ${this.state.selectedFormat.toUpperCase()} with quality ${this.state.selectedQuality}`);
                    finalImageBase64 = await this.convertImageFormat(
                        this.state.currentImage,
                        this.state.selectedFormat,
                        this.state.selectedQuality,
                        this.state.selectedStyle === 'logo' // Remove background for logo
                    );
                } else {
                    w3a11yLog('[SAVE] Saving original PNG without optimization (checkbox unchecked)');
                }

                // Strip data URI prefix before sending to server (server expects raw base64)
                const rawBase64 = finalImageBase64.replace(/^data:image\/\w+;base64,/, '');

                // Step 2: Save to media library
                const requestData = {
                    action: 'w3a11y_artisan_save_image',
                    image_base64: rawBase64,
                    filename: filename,
                    title: title,
                    alt_text: altText,
                    nonce: w3a11yArtisan.nonce
                };

                if (replaceExisting && this.state.attachmentId) {
                    requestData.replace_existing = true;
                    requestData.existing_attachment_id = this.state.attachmentId;
                }

                const formData = new FormData();
                Object.keys(requestData).forEach(key => {
                    formData.append(key, requestData[key]);
                });

                const response = await fetch(w3a11yArtisan.ajax_url, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Show success notification without closing modal or reloading page
                    // This allows users to continue generating and saving multiple images
                    const savedImageInfo = result.data;
                    const successMessage = savedImageInfo.attachment_id
                        ? `Image saved successfully! (ID: ${savedImageInfo.attachment_id})`
                        : 'Image saved successfully to media library!';

                    this.showSuccess(successMessage);
                    this.closeSaveModal();

                    // Mark that images have been saved during this session
                    // Page will reload when modal closes to show saved images in media library
                    this.state.hasSavedImages = true;
                    w3a11yLog('[SAVE FLAG] Set hasSavedImages flag - page will reload on modal close');

                    // Reset save form for next save operation
                    setTimeout(() => {
                        // Generate new filename for next potential save
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                        const ext = this.state.selectedFormat === 'jpeg' ? 'jpg' : this.state.selectedFormat;
                        this.elements.saveFilename.value = `w3a11y-artisan-${timestamp}.${ext}`;
                        this.elements.replaceExisting.checked = false;
                    }, 500);

                    // Call callback if it exists (for media modal integration)
                    if (window.W3A11YArtisan && typeof window.W3A11YArtisan.onImageSaved === 'function') {
                        window.W3A11YArtisan.onImageSaved(savedImageInfo);
                    }

                    w3a11yLog('[SAVE SUCCESS] Image saved, modal remains open for continued work');
                } else {
                    this.showError(result.data.message);
                }
            } catch (error) {
                console.error('Error saving image:', error);
                this.showError('Failed to save image to media library');
            } finally {
                this.hideButtonSpinner(this.elements.confirmSaveBtn);
                this.elements.confirmSaveBtn.disabled = false;
            }
        }

        /**
         * Convert image format using the /convert API endpoint
         * @param {string} imageBase64 - Base64 image data (PNG from Gemini)
         * @param {string} outputFormat - Desired format (jpeg, png, webp)
         * @param {number} quality - Quality setting (50-100)
         * @param {boolean} removeBackground - Whether to remove background for transparency
         * @returns {Promise<string>} Converted image as base64
         */
        async convertImageFormat(imageBase64, outputFormat, quality, removeBackground = false) {
            try {
                w3a11yLog(`[CONVERT] Converting to ${outputFormat}, quality: ${quality}, removeBackground: ${removeBackground}`);

                // Use WordPress AJAX (same pattern as all other API calls in this plugin)
                const formData = new FormData();
                formData.append('action', 'w3a11y_artisan_convert');
                formData.append('nonce', w3a11yArtisan.nonce);
                formData.append('image_base64', imageBase64.replace(/^data:image\/\w+;base64,/, '')); // Remove data URI prefix if present
                formData.append('output_format', outputFormat);
                formData.append('quality', quality);
                formData.append('remove_background', removeBackground ? '1' : '0');

                const response = await fetch(w3a11yArtisan.ajax_url, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Handle double-nested data structure from WordPress wp_send_json_success
                // WordPress wraps the Node.js API response, creating: { success: true, data: { success: true, data: {...} } }
                const actualData = (result.success && result.data && result.data.data) ? result.data.data : result.data;

                if (result.success && actualData && actualData.imageBase64) {
                    w3a11yLog(`[CONVERT] Conversion successful: ${actualData.originalSize} → ${actualData.convertedSize} (${actualData.sizeReduction} reduction)`);

                    // Show rate limit info if available (for transparency)
                    if (actualData.rateLimit && actualData.rateLimit.remaining !== null) {
                        const remaining = actualData.rateLimit.remaining;
                        const isFree = actualData.rateLimit.isFree;
                        if (!isFree && remaining <= 5) {
                            w3a11yLog(`[CONVERT] Warning: Only ${remaining} convert calls remaining`);
                        }
                    }

                    // API returns full data URI
                    return actualData.imageBase64;
                } else {
                    const errorMsg = actualData && actualData.message ? actualData.message : 'Conversion API returned no image data';
                    throw new Error(errorMsg);
                }
            } catch (error) {
                if (!removeBackground) {
                    this.showError(`Format conversion failed: ${error.message}`, 'error', 7000);
                }

                // Throw error so calling function knows conversion failed
                throw error;
            }
        }

        /**
         * Remove background from current image using convert API
         */
        async removeBackground() {
            if (!this.state.currentImage) {
                this.showError('No image to remove background from');
                return;
            }

            try {
                w3a11yLog('[REMOVE BG] Starting background removal');

                // Show processing state
                if (this.elements.removeBgBtn) {
                    this.elements.removeBgBtn.disabled = true;
                    this.elements.removeBgBtn.classList.add('processing');
                }

                this.showLoading('Removing background...');

                // Store current image in case we need to revert
                const originalImage = this.state.currentImage;

                // Call convert API with removeBackground flag
                const transparentImage = await this.convertImageFormat(
                    this.state.currentImage,
                    'png', // Must be PNG for transparency
                    100, // Full quality for transparency
                    true // Remove background
                );

                // Only update if we got a valid image back
                if (transparentImage && transparentImage !== originalImage) {
                    // Update current image
                    this.state.currentImage = transparentImage;
                    this.elements.mainImage.src = transparentImage;
                    this.elements.currentImageBase64.value = transparentImage;

                    // Add to history (this properly integrates with undo/redo system)
                    this.addImageToHistory(transparentImage, 'remove_background', 'Background removed');

                    // Enable all control buttons (revert, apply, before/after, undo/redo)
                    if (this.elements.revertBtn) this.elements.revertBtn.disabled = false;
                    if (this.elements.applyBtn) this.elements.applyBtn.disabled = false;
                    if (this.elements.afterBtn) this.elements.afterBtn.disabled = false;
                    if (this.elements.beforeBtn) this.elements.beforeBtn.disabled = false;

                    // Update undo/redo button states based on history
                    this.updateUndoRedoButtons();

                    this.hideLoading();

                    // Use centralized notification
                    this.showSuccess('Background removed successfully!', 'success', 4000);

                    w3a11yLog('[REMOVE BG SUCCESS] Background removed, controls enabled');
                } else {
                    // If conversion failed or returned same image, just hide loading
                    this.hideLoading();
                    w3a11yLog('[REMOVE BG] Conversion returned no new image, keeping original');
                }

            } catch (error) {
                this.hideLoading();

                this.showError(`Background removal failed: ${error.message}`, 'error', 7000);

                w3a11yLog('[REMOVE BG] Failed, keeping original image');
            } finally {
                // Reset button state
                if (this.elements.removeBgBtn) {
                    this.elements.removeBgBtn.disabled = false;
                    this.elements.removeBgBtn.classList.remove('processing');
                }
            }
        }

        /**
         * Load user credits
         */
        loadCredits() {
            w3a11yLog('Loading credits...');

            // Show loading state
            this.elements.creditsCount.textContent = 'Loading credits...';

            const formData = new FormData();
            formData.append('action', 'w3a11y_artisan_credits');
            formData.append('nonce', w3a11yArtisan.nonce);

            fetch(w3a11yArtisan.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(response => {
                    w3a11yLog('Credits response:', response);
                    if (response.success && response.data.credits !== undefined) {
                        this.updateCredits(response.data.credits);
                    } else {
                        console.error('Invalid credits response:', response);
                        this.elements.creditsCount.textContent = 'Unable to load credits';
                    }
                })
                .catch((error) => {
                    console.error('Credits loading failed:', error);
                    this.elements.creditsCount.textContent = 'Unable to load credits';
                });
        }

        /**
         * Update credits display
         */
        updateCredits(credits) {
            this.state.credits = credits;
            this.elements.creditsCount.textContent = `${credits} credits remaining.`;
        }

        /**
         * Utility methods
         */

        showLoading(text = 'Processing...') {
            this.elements.loadingText.textContent = text;
            this.elements.loadingOverlay.style.display = 'flex';
        }

        hideLoading() {
            this.elements.loadingOverlay.style.display = 'none';
        }

        showButtonSpinner(button) {
            const btnText = button.querySelector('.w3a11y-btn-text');
            const btnSpinner = button.querySelector('.w3a11y-btn-spinner');
            if (btnText) btnText.style.display = 'none';
            if (btnSpinner) btnSpinner.style.display = 'inline-block';
        }

        hideButtonSpinner(button) {
            const btnText = button.querySelector('.w3a11y-btn-text');
            const btnSpinner = button.querySelector('.w3a11y-btn-spinner');
            if (btnText) btnText.style.display = 'inline-block';
            if (btnSpinner) btnSpinner.style.display = 'none';
        }

        showError(message) {
            w3a11yLog('[SHOW ERROR]', message);
            console.error('W3A11Y Artisan Error:', message);

            // Use ONLY centralized notification system
            if (window.W3A11YNotifications && typeof window.W3A11YNotifications.show === 'function') {
                window.W3A11YNotifications.show(message, 'error', 7000);
            } else {
                console.error('[W3A11Y] Centralized notification system not available:', message);
            }
        }

        showSuccess(message) {
            w3a11yLog('[SHOW SUCCESS]', message);

            // Use ONLY centralized notification system
            if (window.W3A11YNotifications && typeof window.W3A11YNotifications.show === 'function') {
                window.W3A11YNotifications.show(message, 'success', 4000);
            } else {
                console.error('[W3A11Y] Centralized notification system not available:', message);
            }
        }

        autoResizeTextarea() {
            if (!this.elements.promptTextarea) {
                return;
            }
            const textarea = this.elements.promptTextarea;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
        }

        determineEditType(prompt) {
            const lowerPrompt = prompt.toLowerCase();

            if (lowerPrompt.includes('add') || lowerPrompt.includes('insert')) {
                return 'add_element';
            } else if (lowerPrompt.includes('remove') || lowerPrompt.includes('delete')) {
                return 'remove_element';
            } else if (lowerPrompt.includes('style') || lowerPrompt.includes('artistic')) {
                return 'style_transfer';
            } else if (lowerPrompt.includes('enhance') || lowerPrompt.includes('improve')) {
                return 'enhance';
            } else {
                return 'modify';
            }
        }

        /**
         * Additional modal functions
         */

        toggleFullscreen() {
            this.modal.classList.toggle('w3a11y-fullscreen');
        }

        showHelp() {
            window.open('https://w3a11y.com/docs/artisan', '_blank');
        }

        handleKeydown(e) {
            if (!this.state.isOpen) return;

            // Escape to close
            if (e.key === 'Escape' && !this.state.isProcessing) {
                this.closeModal();
            }

            // Ctrl/Cmd + Enter to generate
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.generateImage();
            }

            // Ctrl/Cmd + Z for undo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            }

            // Ctrl/Cmd + Shift + Z (or Ctrl/Cmd + Y) for redo  
            if ((e.ctrlKey || e.metaKey) && ((e.key === 'z' && e.shiftKey) || e.key === 'y')) {
                e.preventDefault();
                this.redo();
            }
        }

        toggleEditArea() {
            // Check if edit area button exists (may be disabled)
            if (!this.elements.editAreaBtn) {
                w3a11yLog('[EDIT AREA] Button not available (feature disabled)');
                return;
            }

            this.elements.editAreaBtn.classList.toggle('active');

            if (this.elements.editAreaBtn.classList.contains('active')) {
                this.elements.imageDisplay.classList.add('selection-mode');
                this.showSuccess('Selection mode activated. Click and drag on the image to select the area you want to edit.');
            } else {
                this.elements.imageDisplay.classList.remove('selection-mode');
                this.elements.selectionBox.style.display = 'none';
                this.state.selectionArea = null;
                w3a11yLog('Selection area cleared');
            }
        }

        zoomIn() {
            this.state.zoomLevel = Math.min(this.state.zoomLevel * 1.2, 3);
            this.applyZoom();
        }

        zoomOut() {
            this.state.zoomLevel = Math.max(this.state.zoomLevel / 1.2, 0.5);

            // Reset pan position when zooming out to 1x or lower
            if (this.state.zoomLevel <= 1) {
                this.state.panX = 0;
                this.state.panY = 0;
            }

            this.applyZoom();
        }

        /**
         * Calculate pan constraints to keep image within container bounds
         */
        calculatePanConstraints() {
            const container = this.elements.imageDisplay;
            const image = this.elements.mainImage;

            if (!container || !image || this.state.zoomLevel <= 1) {
                return { minX: 0, maxX: 0, minY: 0, maxY: 0 };
            }

            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;

            // Get the natural image dimensions
            const naturalWidth = image.naturalWidth || image.clientWidth;
            const naturalHeight = image.naturalHeight || image.clientHeight;

            // Calculate aspect ratios to determine how the image fits in the container
            const containerAspect = containerWidth / containerHeight;
            const imageAspect = naturalWidth / naturalHeight;

            let displayedWidth, displayedHeight;

            // Determine actual displayed dimensions (object-fit: contain behavior)
            if (imageAspect > containerAspect) {
                // Image is wider - limited by container width
                displayedWidth = containerWidth;
                displayedHeight = containerWidth / imageAspect;
            } else {
                // Image is taller - limited by container height
                displayedHeight = containerHeight;
                displayedWidth = containerHeight * imageAspect;
            }

            // Calculate zoomed dimensions
            const zoomedWidth = displayedWidth * this.state.zoomLevel;
            const zoomedHeight = displayedHeight * this.state.zoomLevel;

            // Calculate maximum pan distances
            const maxPanX = Math.max(0, (zoomedWidth - containerWidth) / 2);
            const maxPanY = Math.max(0, (zoomedHeight - containerHeight) / 2);

            return {
                minX: -maxPanX,
                maxX: maxPanX,
                minY: -maxPanY,
                maxY: maxPanY
            };
        }

        applyZoom() {
            // Apply boundary constraints before applying zoom
            const constraints = this.calculatePanConstraints();
            this.state.panX = Math.max(constraints.minX, Math.min(constraints.maxX, this.state.panX));
            this.state.panY = Math.max(constraints.minY, Math.min(constraints.maxY, this.state.panY));

            // When using scale() translate(), the translate values need to be adjusted for the scale
            // Divide by zoom level to get correct positioning after scale is applied
            const adjustedPanX = this.state.panX / this.state.zoomLevel;
            const adjustedPanY = this.state.panY / this.state.zoomLevel;

            const transform = `scale(${this.state.zoomLevel}) translate(${adjustedPanX}px, ${adjustedPanY}px)`;
            this.elements.mainImage.style.transform = transform;

            // Update cursor classes based on zoom level
            const isEditAreaActive = this.elements.editAreaBtn && this.elements.editAreaBtn.classList.contains('active');
            if (this.state.zoomLevel > 1 && !isEditAreaActive) {
                this.elements.mainImage.classList.add('w3a11y-pannable');
            } else {
                this.elements.mainImage.classList.remove('w3a11y-pannable', 'w3a11y-panning');
            }

            this.updateZoomButtons();
        }

        updateZoomButtons() {
            this.elements.zoomInBtn.disabled = this.state.zoomLevel >= 3;
            this.elements.zoomOutBtn.disabled = this.state.zoomLevel <= 0.5;
        }

        showAfter() {
            this.elements.afterBtn.classList.add('active');
            this.elements.beforeBtn.classList.remove('active');

            if (this.state.currentImage) {
                // Properly handle both PNG and JPEG formats
                let imageUrl = this.state.currentImage;
                if (!imageUrl.startsWith('data:image/')) {
                    // Raw base64 - detect format from signature
                    const isPNG = imageUrl.startsWith('iVBOR');
                    const mimeType = isPNG ? 'image/png' : 'image/jpeg';
                    imageUrl = `data:${mimeType};base64,${imageUrl}`;
                }
                this.elements.mainImage.src = imageUrl;
                w3a11yLog('[SHOW AFTER] Displaying current image');
            }
        }

        showBefore() {
            this.elements.beforeBtn.classList.add('active');
            this.elements.afterBtn.classList.remove('active');

            if (this.state.originalImage) {
                // Properly handle both PNG and JPEG formats
                let imageUrl = this.state.originalImage;
                if (!imageUrl.startsWith('data:image/')) {
                    // Raw base64 - detect format from signature
                    const isPNG = imageUrl.startsWith('iVBOR');
                    const mimeType = isPNG ? 'image/png' : 'image/jpeg';
                    imageUrl = `data:${mimeType};base64,${imageUrl}`;
                }
                this.elements.mainImage.src = imageUrl;
                w3a11yLog('[SHOW BEFORE] Displaying original image');
            }
        }

        revertToOriginal() {
            if (!this.state.originalImage) return;

            if (confirm(w3a11yArtisan.texts.confirm_revert)) {
                this.state.currentImage = this.state.originalImage;
                this.elements.currentImageBase64.value = this.state.originalImage;

                const imageUrl = `data:image/jpeg;base64,${this.state.originalImage}`;
                this.elements.mainImage.src = imageUrl;

                // Reset to after view
                this.showAfter();

                // Clear history
                this.state.history = [];
                this.renderHistory();
            }
        }

        /**
         * Add current image state to history
         * 
         * @param {string} imageBase64 Base64 image data
         * @param {string} operation Operation type ('generate', 'edit', 'load')
         * @param {string} prompt The prompt used (optional)
         */
        addImageToHistory(imageBase64, operation = 'unknown', prompt = '') {
            // Remove any future history if we're not at the end (when user makes changes after undo)
            if (this.state.historyIndex < this.state.imageHistory.length - 1) {
                this.state.imageHistory = this.state.imageHistory.slice(0, this.state.historyIndex + 1);
            }

            const historyEntry = {
                imageBase64: imageBase64,
                operation: operation,
                prompt: prompt,
                timestamp: new Date().toISOString()
            };

            this.state.imageHistory.push(historyEntry);
            this.state.historyIndex = this.state.imageHistory.length - 1;

            // Keep history within size limit
            if (this.state.imageHistory.length > this.state.maxHistorySize) {
                this.state.imageHistory.shift();
                this.state.historyIndex--;
            }

            this.updateUndoRedoButtons();

            w3a11yLog(`Added to image history: ${operation} (${this.state.historyIndex + 1}/${this.state.imageHistory.length})`);
        }

        /**
         * Navigate to previous image in history
         */
        undo() {
            if (this.state.historyIndex > 0) {
                this.state.historyIndex--;
                this.restoreImageFromHistory();
                w3a11yLog(`Undo: Navigate to step ${this.state.historyIndex + 1}/${this.state.imageHistory.length}`);
            }
        }

        /**
         * Navigate to next image in history
         */
        redo() {
            if (this.state.historyIndex < this.state.imageHistory.length - 1) {
                this.state.historyIndex++;
                this.restoreImageFromHistory();
                w3a11yLog(`Redo: Navigate to step ${this.state.historyIndex + 1}/${this.state.imageHistory.length}`);
            }
        }

        /**
         * Restore image from history at current index
         */
        restoreImageFromHistory() {
            if (this.state.historyIndex >= 0 && this.state.historyIndex < this.state.imageHistory.length) {
                const historyEntry = this.state.imageHistory[this.state.historyIndex];

                // Update current image (preserve full data URI if already present)
                this.state.currentImage = historyEntry.imageBase64;
                this.elements.currentImageBase64.value = historyEntry.imageBase64;

                // Update display - handle both data URI and raw base64
                let imageDataUrl = historyEntry.imageBase64;
                if (!imageDataUrl.startsWith('data:image/')) {
                    // Raw base64 - detect format from first bytes (PNG starts with iVBOR, JPEG with /9j/)
                    const isPNG = imageDataUrl.startsWith('iVBOR');
                    const mimeType = isPNG ? 'image/png' : 'image/jpeg';
                    imageDataUrl = `data:${mimeType};base64,${imageDataUrl}`;
                }
                this.elements.mainImage.src = imageDataUrl;

                // Update prompt if available
                if (historyEntry.prompt) {
                    this.elements.promptTextarea.value = historyEntry.prompt;
                    this.autoResizeTextarea();
                }

                // Update UI buttons
                this.updateUndoRedoButtons();

                // Show which step we're on
                this.showHistoryStatus();

                w3a11yLog(`[HISTORY RESTORE] Restored image from step ${this.state.historyIndex + 1}/${this.state.imageHistory.length}, operation: ${historyEntry.operation}`);
            }
        }

        /**
         * Update undo/redo button states
         */
        updateUndoRedoButtons() {
            const canUndo = this.state.historyIndex > 0;
            const canRedo = this.state.historyIndex < this.state.imageHistory.length - 1;

            this.elements.undoBtn.disabled = !canUndo;
            this.elements.redoBtn.disabled = !canRedo;

            // Update button tooltips
            this.elements.undoBtn.setAttribute('aria-label', canUndo ?
                `Undo (Step ${this.state.historyIndex}/${this.state.imageHistory.length})` :
                'No previous steps to undo');
            this.elements.redoBtn.setAttribute('aria-label', canRedo ?
                `Redo (Step ${this.state.historyIndex + 2}/${this.state.imageHistory.length})` :
                'No next steps to redo');
        }

        /**
         * Show current history status
         */
        showHistoryStatus() {
            if (this.state.imageHistory.length > 1) {
                const current = this.state.historyIndex + 1;
                const total = this.state.imageHistory.length;
                const operation = this.state.imageHistory[this.state.historyIndex].operation;
                const operationLabel = this.getOperationLabel(operation);

                w3a11yLog(`History: Step ${current}/${total} (${operation})`);
            }
        }

        /**
         * Get user-friendly operation label
         */
        getOperationLabel(operation) {
            const labels = {
                'generate': 'Generated Image',
                'edit': 'Edited Image',
                'load': 'Loaded Image',
                'unknown': 'Image State'
            };
            return labels[operation] || labels['unknown'];
        }

        /**
         * Clear image history (when modal closes or resets)
         */
        clearImageHistory() {
            this.state.imageHistory = [];
            this.state.historyIndex = -1;
            this.updateUndoRedoButtons();
        }
    }

    /**
     * Initialize when document is ready
     */
    function initializeW3A11YArtisan() {
        // Check if we're on a relevant admin page
        // Handle case where w3a11yArtisan might not be available (dynamic loading)
        if (typeof w3a11yArtisan === 'undefined') {
            w3a11yLog('W3A11Y Artisan: Localized data not available, creating fallback');

            // Create minimal fallback configuration for dynamic loading scenarios
            window.w3a11yArtisan = {
                api_configured: true, // Assume configured if modal is being loaded
                ajax_url: window.ajaxurl || '', // Use WordPress provided ajaxurl only
                nonce: '', // Will be handled by WordPress nonce system
                texts: {
                    generating: 'Generating image...',
                    editing: 'Editing image...',
                    saving: 'Saving to Media Library...',
                    error_generic: 'An error occurred. Please try again.',
                    success_saved: 'Image saved successfully to Media Library!'
                },
                plugin_url: '', // Will be determined from script src
                version: '1.0.0'
            };

            // Try to get plugin URL from current script
            const scripts = document.querySelectorAll('script[src*="admin-modal"]');
            if (scripts.length > 0) {
                const scriptSrc = scripts[0].src;
                const match = scriptSrc.match(/(.+\/wp-content\/plugins\/[^\/]+\/)/);
                if (match) {
                    window.w3a11yArtisan.plugin_url = match[1];
                }
            }

            // Try to get AJAX URL from global ajaxurl if not set via localized script
            if (!window.w3a11yArtisan.ajax_url) {
                if (typeof window.ajaxurl !== 'undefined') {
                    window.w3a11yArtisan.ajax_url = window.ajaxurl;
                }
            }
        }

        // For now, allow the modal to work even if API is not configured
        // This will let users see the modal and configure the plugin
        if (!w3a11yArtisan.api_configured) {
            w3a11yLog('W3A11Y Artisan: API not configured - modal will show but generation will not work');
            // Don't return, continue to initialize the modal
        }

        // Initialize modal
        const modalController = new W3A11YArtisanModal();

        // Expose modal controller globally
        window.W3A11YArtisan.modal = modalController;
        window.W3A11YArtisan.openModal = (mode, attachmentId) => {
            modalController.openModal(mode, attachmentId);
        };

        console.log('W3A11Y Artisan: JavaScript initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeW3A11YArtisan);
    } else {
        initializeW3A11YArtisan();
    }

})();