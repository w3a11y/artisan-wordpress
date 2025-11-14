/**
 * W3A11Y AltText Integration JavaScript - VANILLA JS ONLY
 * 
 * Handles single alt text generation, bulk processing, and UI interactions
 * for the WordPress admin interface.
 * 
 * CRITICAL: NO JQUERY - VANILLA JAVASCRIPT ONLY
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

(function () {
    'use strict';

    // Check if configuration object exists
    if (typeof w3a11yAltTextAjax === 'undefined') {
        console.error('W3A11Y AltText: Configuration object not found. Make sure scripts are loaded properly.');
        return;
    }

    // Global object for AltText functionality
    window.W3A11YAltText = {
        config: {
            ajaxUrl: w3a11yAltTextAjax.ajax_url,
            nonce: w3a11yAltTextAjax.nonce,
            batchSize: parseInt(w3a11yAltTextAjax.batch_size) || 8,
            batchDelay: parseInt(w3a11yAltTextAjax.batch_delay) || 2000
        },

        bulkProcessingStopped: false,
        currentSessionId: null,
        initialized: false,
        bulkProcessingInitialized: false,

        /**
         * Initialize AltText functionality
         */
        init: function () {
            // Prevent double initialization
            if (this.initialized) {
                return;
            }
            this.initialized = true;

            this.initSettingsPage();
            this.initCreditsChecker();
            this.initNotificationSystem();
        },

        /**
         * Initialize notification system
         */
        initNotificationSystem: function () {
            // Notification container is now created by PHP notification manager
            // Just verify it exists
            if (!document.getElementById('w3a11y-notifications')) {
                console.log('W3A11Y: Notification container not found - PHP notification manager may not be loaded');
            }
        },

        /**
         * Display a notification using centralized notification system
         * @param {string} message - Notification message
         * @param {string} type - Notification type (error, warning, success, info)
         * @param {number} duration - Duration in milliseconds (0 for permanent)
         */
        showNotification: function (message, type, duration) {
            // Check for duplicate notifications before showing
            const notificationContainer = document.getElementById('w3a11y-notifications');
            if (notificationContainer) {
                const existingNotifications = notificationContainer.querySelectorAll('.w3a11y-notification');
                for (let notification of existingNotifications) {
                    const notificationText = notification.textContent || notification.innerText;
                    // Check if similar message already exists (prevent duplicates)
                    if (message && notificationText.includes(message.substring(0, 50))) {
                        console.log('W3A11Y: Duplicate notification prevented:', message.substring(0, 50));
                        return null; // Don't show duplicate
                    }
                }
            }

            // Use centralized notification system if available
            if (window.W3A11YNotifications && window.W3A11YNotifications.show) {
                return window.W3A11YNotifications.show(message, type, duration);
            } else {
                // Fallback: log to console if centralized system not available
                console.log('W3A11Y Notification (' + (type || 'info') + '):', message);
                return null;
            }
        },



        /**
         * Initialize settings page functionality
         */
        initSettingsPage: function () {
            // Character counter for custom instructions
            const customInstructions = document.getElementById('alttext_custom_instructions');
            const instructionsCount = document.getElementById('alttext-instructions-count');

            if (customInstructions && instructionsCount) {
                customInstructions.addEventListener('input', function () {
                    const length = this.value.length;
                    instructionsCount.textContent = length;

                    if (length > 500) {
                        this.value = this.value.substring(0, 500);
                        instructionsCount.textContent = '500';
                    }
                });
            }

            // Max length slider interaction
            const maxLengthSlider = document.getElementById('alttext_max_length');
            const maxLengthDisplay = document.getElementById('alttext_max_length_display');

            if (maxLengthSlider && maxLengthDisplay) {
                maxLengthSlider.addEventListener('input', function () {
                    maxLengthDisplay.value = this.value;
                });

                maxLengthDisplay.addEventListener('change', function () {
                    const value = parseInt(this.value);
                    if (value >= 50 && value <= 300) {
                        maxLengthSlider.value = value;
                    } else {
                        this.value = maxLengthSlider.value;
                    }
                });
            }

            // Style selection descriptions
            const styleRadios = document.querySelectorAll('input[name="w3a11y_artisan_settings[alttext_style]"]');
            styleRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    const selectedStyle = this.value;
                    const descriptions = document.querySelectorAll('.alttext-style-description');
                    descriptions.forEach(desc => desc.style.display = 'none');

                    const selectedDesc = document.querySelector(`.alttext-style-description[data-style="${selectedStyle}"]`);
                    if (selectedDesc) {
                        selectedDesc.style.display = 'block';
                    }
                });
            });
        },

        /**
         * Initialize credits checker
         */
        initCreditsChecker: function () {
            // Only update credits on initial page load
            this.updateCreditsDisplay();

            // Only set up periodic updates on the bulk processing page where credits are actively consumed
            const isBulkPage = window.location.search.includes('page=w3a11y-bulk-alttext');
            if (isBulkPage && this.isProcessingActive()) {
                // Update credits every 30 seconds only during active processing
                this.creditUpdateInterval = setInterval(() => {
                    this.updateCreditsDisplay();
                }, 30000);
            }
        },

        /**
         * Check if bulk processing is currently active
         */
        isProcessingActive: function () {
            return this.currentSessionId !== null && !this.bulkProcessingStopped;
        },

        /**
         * Stop credits polling when processing completes
         */
        stopCreditsPolling: function () {
            if (this.creditUpdateInterval) {
                clearInterval(this.creditUpdateInterval);
                this.creditUpdateInterval = null;
            }
        },

        /**
         * Update credits display
         */
        updateCreditsDisplay: function () {
            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'w3a11y_get_credits_info',
                    nonce: this.config.nonce
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const credits = data.data;
                        const availableCredits = parseInt(credits.available) || 0;

                        const availableElements = document.querySelectorAll('#w3a11y-credits-available, #bulk-credits-available');
                        availableElements.forEach(el => {
                            if (el) el.textContent = availableCredits;
                        });

                        // Update other credit displays if they exist
                        const usedElement = document.getElementById('w3a11y-credits-used');
                        if (usedElement) {
                            usedElement.textContent = credits.used || 0;
                        }

                        const totalElement = document.getElementById('w3a11y-credits-total');
                        if (totalElement) {
                            totalElement.textContent = credits.total || 0;
                        }

                        // Check for low credits and show warning
                        this.checkLowCredits(availableCredits);
                    } else {
                        console.error('Failed to load credit information:', data);
                    }
                })
                .catch(error => {
                    console.error('AJAX error loading credits:', error);
                    // Set fallback values
                    const fallbackElements = document.querySelectorAll('#w3a11y-credits-available, #bulk-credits-available');
                    fallbackElements.forEach(el => {
                        el.innerHTML = '<span style="font-size: 0.8rem;">Error loading</span>';
                    });
                });
        },

        /**
         * Check for low credits and show appropriate warnings
         * @param {number} availableCredits - Current available credits
         */
        checkLowCredits: function (availableCredits) {
            // Clear any existing credit warnings
            const existingWarnings = document.querySelectorAll('.w3a11y-notification[data-credit-warning]');
            existingWarnings.forEach(warning => this.removeNotification(warning));

            if (availableCredits <= 0) {
                const notification = this.showNotification(
                    'You have no credits remaining. <a href="https://w3a11y.com/pricing" target="_blank">Purchase more credits</a> to continue using W3A11Y services.',
                    'error',
                    0 // Don't auto-dismiss
                );
                if (notification) {
                    notification.setAttribute('data-credit-warning', 'critical');
                }
            } else if (availableCredits <= 10) {
                const notification = this.showNotification(
                    `Low credits warning: Only ${availableCredits} credits remaining. <a href="https://w3a11y.com/pricing" target="_blank">Purchase more credits</a> to avoid service interruption.`,
                    'warning',
                    0 // Don't auto-dismiss
                );
                if (notification) {
                    notification.setAttribute('data-credit-warning', 'low');
                }
            }
        },

        /**
         * Generate alt text for a single image
         * @param {string} imageUrl - Image URL
         * @param {string} context - Context text
         * @param {number} attachmentId - Attachment ID
         * @param {function} callback - Callback function
         */
        generateAltText: function (imageUrl, context = '', attachmentId = 0, callback = null) {
            const button = document.querySelector(`.w3a11y-generate-alttext[data-attachment="${attachmentId}"]`);
            if (!button) return;

            const originalText = button.textContent;

            button.disabled = true;
            button.textContent = w3a11yAltTextAjax.generating_text;
            button.classList.add('generating');

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'w3a11y_generate_alttext',
                    nonce: this.config.nonce,
                    image_url: imageUrl,
                    context: context,
                    attachment_id: attachmentId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (callback) {
                            callback(data.data);
                        }

                        // Show success message
                        this.showNotice('Alt text generated successfully!', 'success');

                        // Update credits display
                        this.updateCreditsDisplay();

                    } else {
                        const errorMessage = data.data.message || 'Failed to generate alt text.';

                        // Check for insufficient credits message
                        if (errorMessage.toLowerCase().includes('insufficient credits') ||
                            errorMessage.toLowerCase().includes('no credits') ||
                            (data.data && data.data.status_code === 402)) {

                            // Use centralized notification manager via AJAX
                            this.triggerCentralizedNotification(
                                errorMessage,
                                'error',
                                (data.data && data.data.status_code) || 402,
                                (data.data && data.data.available_credits) || 0
                            );
                        } else {
                            this.showNotice(errorMessage, 'error');
                        }
                    }
                })
                .catch(error => {
                    this.showNotice('Network error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalText;
                    button.classList.remove('generating');
                });
        },

        /**
         * Show admin notice
         * @param {string} message - Notice message
         * @param {string} type - Notice type (success, error, warning, info)
         */
        showNotice: function (message, type = 'info') {
            // Use the new notification system
            this.showNotification(message, type, type === 'error' ? 10000 : 5000);
        },

        /**
         * Trigger centralized notification via AJAX
         * @param {string} message - Error message
         * @param {string} type - Notification type (error, warning, etc.)
         * @param {number} statusCode - HTTP status code
         * @param {number} availableCredits - Available credits count
         */
        triggerCentralizedNotification: function (message, type = 'error', statusCode = 0, availableCredits = 0) {
            // Check if we already have a notification with similar content to prevent duplicates
            const notificationContainer = document.getElementById('w3a11y-notifications');
            if (notificationContainer) {
                const existingNotifications = notificationContainer.querySelectorAll('.w3a11y-notification');
                for (let notification of existingNotifications) {
                    const notificationText = notification.textContent || notification.innerText;
                    // Prevent duplicate insufficient credits notifications
                    if (statusCode === 402 && notificationText.includes('Insufficient credits')) {
                        console.log('W3A11Y: Duplicate insufficient credits notification prevented');
                        return;
                    }
                }
            }

            const formData = new FormData();
            formData.append('action', 'w3a11y_add_notification');
            formData.append('nonce', this.config.nonce);
            formData.append('type', type);
            formData.append('message', message);
            formData.append('context', 'both');
            formData.append('status_code', statusCode);
            formData.append('available_credits', availableCredits);

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('W3A11Y: Centralized notification triggered successfully');

                        // Show immediate frontend notification if available
                        if (data.data && data.data.frontend_notification && window.W3A11YNotifications) {
                            const notif = data.data.frontend_notification;
                            window.W3A11YNotifications.show(notif.message, notif.type, notif.duration);
                        }

                        // DON'T reload page for credit errors - notification is enough
                        // Only reload for other types of admin notices
                        if (statusCode !== 402) {
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        console.error('W3A11Y: Failed to trigger centralized notification:', data);
                        // Fallback to local notification
                        this.showNotification(message, type, type === 'error' ? 10000 : 5000);
                    }
                })
                .catch(error => {
                    console.error('W3A11Y: Error triggering centralized notification:', error);
                    // Fallback to local notification
                    this.showNotification(message, type, type === 'error' ? 10000 : 5000);
                });
        },

        /**
         * Initialize bulk processing
         */
        initBulkProcessing: function () {
            // Prevent double initialization
            if (this.bulkProcessingInitialized) {
                return;
            }
            this.bulkProcessingInitialized = true;

            this.updateBulkStats();

            const startButton = document.getElementById('w3a11y-start-bulk');
            if (startButton) {
                startButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.startBulkProcessing();
                });
            }

            const cancelButton = document.getElementById('w3a11y-cancel-bulk');
            if (cancelButton) {
                cancelButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.cancelBulkProcessing();
                });
            }

            // Real-time stats updates when options change
            const optionCheckboxes = document.querySelectorAll('#overwrite-existing, #only-attached, #skip-processed');
            optionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    // Pass the checkbox that triggered the change
                    this.updateBulkStats(e.target);
                });
            });

            // Character counter for custom instructions
            const customInstructionsTextarea = document.getElementById('custom-instructions');
            const instructionCounter = document.getElementById('instruction-count');

            if (customInstructionsTextarea && instructionCounter) {
                customInstructionsTextarea.addEventListener('input', function () {
                    const length = this.value.length;
                    const maxLength = 500;
                    instructionCounter.textContent = length + ' / ' + maxLength;

                    if (length > maxLength) {
                        this.value = this.value.substring(0, maxLength);
                        instructionCounter.textContent = maxLength + ' / ' + maxLength;
                    }
                });
            }
        },

        /**
         * Update bulk processing statistics
         * @param {HTMLElement} triggerElement - The element that triggered the update (optional)
         */
        updateBulkStats: function (triggerElement) {
            const options = this.getBulkProcessingOptions();

            const formData = new URLSearchParams();
            formData.append('action', 'w3a11y_get_bulk_stats');
            formData.append('nonce', this.config.nonce);

            // Append options properly - convert booleans to strings
            Object.keys(options).forEach(key => {
                formData.append(key, options[key]);
            });

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const stats = data.data;

                        const totalElement = document.getElementById('bulk-total-images');
                        if (totalElement) {
                            totalElement.textContent = stats.total_images || 0;
                        }

                        const missingElement = document.getElementById('bulk-missing-alt');
                        if (missingElement && stats.missing_alt_text !== undefined && stats.missing_percentage !== undefined) {
                            missingElement.innerHTML = stats.missing_alt_text + ' <span style="font-size: 1rem; font-weight: normal; color: #64748b;">(' + stats.missing_percentage + '%)</span>';
                        }

                        const withAltElement = document.getElementById('bulk-with-alt');
                        if (withAltElement && stats.with_alt_text !== undefined && stats.missing_percentage !== undefined) {
                            withAltElement.innerHTML = stats.with_alt_text + ' <span style="font-size: 1rem; font-weight: normal; color: #64748b;">(' + (100 - stats.missing_percentage) + '%)</span>';
                        }

                        // Update credits display
                        if (stats.available_credits !== undefined) {
                            const creditsElement = document.getElementById('bulk-credits-available');
                            if (creditsElement) {
                                creditsElement.textContent = stats.available_credits;
                            }
                        }

                        // Scroll to statistics card if only-attached checkbox was checked
                        if (triggerElement && triggerElement.id === 'only-attached' && triggerElement.checked) {
                            const statsCard = document.querySelector('.w3a11y-stats-grid');
                            if (statsCard) {
                                statsCard.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });

                                // Add a subtle highlight animation
                                statsCard.style.transition = 'box-shadow 0.3s ease';
                                statsCard.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.3)';
                                setTimeout(() => {
                                    statsCard.style.boxShadow = '';
                                }, 1500);
                            }
                        }
                    } else {
                        console.error('Failed to load bulk statistics:', data);
                    }
                })
                .catch(error => {
                    console.error('AJAX error loading bulk statistics:', error);
                    // Don't show error to user on page load, just log it
                });
        },

        /**
         * Get bulk processing options from form
         * @returns {object} Processing options
         */
        getBulkProcessingOptions: function () {
            const getValueById = (id) => {
                const element = document.getElementById(id);
                return element ? element.value : '';
            };

            const getCheckedById = (id) => {
                const element = document.getElementById(id);
                return element ? element.checked : false;
            };

            return {
                keywords: getValueById('keywords'),
                negative_keywords: getValueById('negative-keywords'),
                custom_instructions: getValueById('custom-instructions'),
                language: getValueById('alt-text-language'),
                max_length: getValueById('max-length'),
                overwrite_existing: getCheckedById('overwrite-existing'),
                only_attached: getCheckedById('only-attached'),
                skip_processed: getCheckedById('skip-processed')
            };
        },

        /**
         * Update progress bar
         * @param {string} selector - Progress bar selector
         * @param {number} percentage - Progress percentage
         */
        updateProgressBar: function (selector, percentage) {
            const progressBar = document.querySelector(selector);
            if (progressBar) {
                progressBar.style.width = Math.max(0, Math.min(100, percentage)) + '%';
            }
        },

        /**
         * Start bulk processing
         */
        startBulkProcessing: function () {
            const options = this.getBulkProcessingOptions();

            // Check if in sync mode 
            this.isSyncMode = options.skip_processed;

            const formData = new URLSearchParams();
            formData.append('action', 'w3a11y_bulk_alttext');
            formData.append('nonce', this.config.nonce);
            formData.append('bulk_action', 'start');

            // Append options properly - convert booleans to strings
            Object.keys(options).forEach(key => {
                formData.append(key, options[key]);
            });

            const formContainer = document.getElementById('bulk-processing-form');
            const progressContainer = document.getElementById('bulk-progress-container');

            if (formContainer) formContainer.style.display = 'none';
            if (progressContainer) progressContainer.style.display = 'block';

            // Update UI based on sync mode
            const progressTitle = document.querySelector('.w3a11y-progress-title');
            const cancelButton = document.getElementById('w3a11y-cancel-bulk');

            if (this.isSyncMode) {
                if (progressTitle) progressTitle.textContent = 'Syncing Alt Text...';
                if (cancelButton) cancelButton.textContent = 'Cancel Syncing';
            } else {
                if (progressTitle) progressTitle.textContent = 'Generating Alt Text...';
                if (cancelButton) cancelButton.textContent = 'Cancel Generation';
            }

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.currentSessionId = data.data.session_id;
                        // Start credit polling when processing begins
                        if (!this.creditUpdateInterval) {
                            this.creditUpdateInterval = setInterval(() => {
                                this.updateCreditsDisplay();
                            }, 30000);
                        }
                        this.processBulkBatch(data.data.session_id, data.data.total_images);
                    } else {
                        this.showNotice(data.data.message || 'Failed to start bulk processing.', 'error');
                        this.resetBulkInterface();
                    }
                })
                .catch(error => {
                    this.showNotice('Network error occurred. Please try again.', 'error');
                    this.resetBulkInterface();
                });
        },

        /**
         * Process bulk batch with enhanced error handling
         * @param {string} sessionId - Session ID
         * @param {number} totalImages - Total number of images
         */
        processBulkBatch: function (sessionId, totalImages) {
            if (this.bulkProcessingStopped) {
                return;
            }

            // Track start time if not already set
            if (!this.batchStartTime) {
                this.batchStartTime = Date.now();
            }

            const formData = new URLSearchParams({
                action: 'w3a11y_bulk_alttext',
                nonce: this.config.nonce,
                bulk_action: 'process_batch',
                session_id: sessionId
            });

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const responseData = data.data;
                        const processed = responseData.processed + responseData.failed + (responseData.skipped || 0);
                        const percentage = Math.round((processed / responseData.total) * 100);

                        // Update progress display
                        const progressDisplay = document.getElementById('bulk-progress-display');
                        if (progressDisplay) {
                            progressDisplay.textContent = processed + ' / ' + responseData.total + ' (' + percentage + '%)';
                        }

                        // Update progress bar
                        this.updateProgressBar('#bulk-main-progress', percentage);

                        // Calculate and update time remaining estimate
                        const timeElement = document.getElementById('bulk-time-remaining');
                        if (timeElement) {
                            if (processed > 0 && processed < responseData.total) {
                                const elapsedTime = Date.now() - this.batchStartTime;
                                const avgTimePerImage = elapsedTime / processed;
                                const remainingImages = responseData.total - processed;
                                const estimatedRemainingMs = avgTimePerImage * remainingImages;

                                // Format time
                                const minutes = Math.floor(estimatedRemainingMs / 60000);
                                const seconds = Math.floor((estimatedRemainingMs % 60000) / 1000);

                                if (minutes > 0) {
                                    timeElement.textContent = `Estimated time remaining: ${minutes}m ${seconds}s`;
                                } else {
                                    timeElement.textContent = `Estimated time remaining: ${seconds}s`;
                                }
                            } else if (processed === 0) {
                                timeElement.textContent = 'Estimated time remaining: calculating...';
                            }
                        }

                        // Update credits display
                        if (responseData.available_credits !== undefined) {
                            const creditsElement = document.getElementById('bulk-credits-available');
                            if (creditsElement) {
                                creditsElement.textContent = responseData.available_credits;
                            }
                        }

                        // Update real-time stats
                        this.updateBulkStats();

                        if (responseData.status === 'completed') {
                            this.completeBulkProcessing(responseData);
                        } else {
                            // Process next batch after delay
                            setTimeout(() => {
                                this.processBulkBatch(sessionId, totalImages);
                            }, this.config.batchDelay);
                        }
                    } else {
                        // Handle errors
                        if (data.data && data.data.status_code === 402) {
                            // Extract available credits from error response
                            const availableCredits = data.data.error?.credits_available || data.data.available_credits || 0;
                            this.triggerCentralizedNotification(
                                'Insufficient credits to continue processing. Please purchase more credits.',
                                'error',
                                402,
                                availableCredits
                            );
                        } else {
                            this.triggerCentralizedNotification(
                                data.data?.message || 'Bulk processing failed.',
                                'error',
                                0
                            );
                        }
                        this.resetBulkInterface();
                    }
                })
                .catch(error => {
                    console.error('Network error during bulk processing:', error);
                    this.triggerCentralizedNotification(
                        'Network error during bulk processing. Please check your connection.',
                        'error',
                        0
                    );
                    this.resetBulkInterface();
                });
        },

        /**
         * Cancel bulk processing
         * @param {boolean} silent - If true, don't show cancellation notice
         */
        cancelBulkProcessing: function (silent = false) {
            console.log('Cancelling bulk processing.');

            this.bulkProcessingStopped = true;
            this.stopCreditsPolling();

            // Send cancel request to server to stop processing
            if (this.currentSessionId) {
                const formData = new URLSearchParams({
                    action: 'w3a11y_bulk_alttext',
                    nonce: this.config.nonce,
                    bulk_action: 'cancel',
                    session_id: this.currentSessionId
                });

                fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData
                }).catch(error => {
                    console.error('Error sending cancel request:', error);
                });
            }

            // Store sync mode state before resetting (resetBulkInterface will clear it)
            const wasSyncMode = this.isSyncMode;

            // Reset interface immediately
            this.resetBulkInterface();

            // Show appropriate cancellation message based on mode (unless silent)
            if (!silent) {
                if (wasSyncMode) {
                    this.showNotice('Bulk syncing cancelled.', 'info');
                } else {
                    this.showNotice('Bulk generation cancelled.', 'info');
                }
            }
        },








        /**
         * Complete bulk processing
         * @param {object} data - Completion data
         */
        completeBulkProcessing: function (data) {
            // Update progress to completed state
            const progressDisplay = document.getElementById('bulk-progress-display');
            if (progressDisplay) {
                progressDisplay.textContent = data.total + ' / ' + data.total + ' (100%)';
            }
            this.updateProgressBar('#bulk-main-progress', 100);

            // Update header to show completion
            const progressTitle = document.querySelector('.w3a11y-progress-title');
            const progressIcon = document.querySelector('.w3a11y-progress-icon');

            // Check if in sync mode
            const isSyncMode = this.isSyncMode;
            const syncCount = data.skipped || 0; // Skipped means synced from cache

            if (progressTitle) {
                progressTitle.textContent = isSyncMode ? 'Alt Text Sync Complete!' : 'Alt Text Generation Complete!';
            }
            if (progressIcon) {
                progressIcon.classList.remove('w3a11y-progress-icon');
                progressIcon.classList.add('dashicons-yes-alt');
            }

            // Update status message based on mode
            const statusElement = document.getElementById('bulk-progress-status');
            if (statusElement) {
                if (isSyncMode && syncCount > 0) {
                    statusElement.innerHTML = `<strong>Sync Complete!</strong><br>
                        Successfully synced: ${syncCount} images<br>
                        Failed: ${data.failed} images<br>
                        Total: ${data.total} images`;
                } else {
                    statusElement.innerHTML = `<strong>Processing Complete!</strong><br>
                        Successfully processed: ${data.processed} images<br>
                        Failed: ${data.failed} images<br>
                        Total: ${data.total} images`;
                }
            }

            // Update time remaining
            const timeElement = document.getElementById('bulk-time-remaining');
            if (timeElement) {
                timeElement.textContent = 'Completed successfully!';
            }

            // Update cancel button to restart
            const cancelButton = document.getElementById('w3a11y-cancel-bulk');
            if (cancelButton) {
                cancelButton.textContent = 'Start New Generation';
                // Remove old event listeners by cloning the button
                const newButton = cancelButton.cloneNode(true);
                cancelButton.parentNode.replaceChild(newButton, cancelButton);
                // Add new event listener for silent reset
                newButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.resetBulkInterface(true); // Pass true to indicate silent reset
                });
            }

            this.stopCreditsPolling(); // Stop credit polling when processing completes

            // Show appropriate success message
            if (isSyncMode && syncCount > 0) {
                this.showNotice(`Bulk processing completed! ${syncCount} images synced successfully.`, 'success');
            } else {
                this.showNotice(`Bulk processing completed! ${data.processed} images processed successfully.`, 'success');
            }
        },

        /**
         * Reset bulk processing interface
         * @param {boolean} silent - If true, don't show cancellation notice
         */
        resetBulkInterface: function (silent = false) {
            const formContainer = document.getElementById('bulk-processing-form');
            const progressContainer = document.getElementById('bulk-progress-container');

            if (formContainer) formContainer.style.display = 'block';
            if (progressContainer) progressContainer.style.display = 'none';

            // Reset progress elements
            const progressTitle = document.querySelector('.w3a11y-progress-title');
            const progressIcon = document.querySelector('.w3a11y-progress-icon, .w3a11y-progress-header .dashicons-yes-alt');
            const statusElement = document.getElementById('bulk-progress-status');
            const progressDisplay = document.getElementById('bulk-progress-display');
            const timeElement = document.getElementById('bulk-time-remaining');

            if (progressTitle) {
                progressTitle.textContent = 'Generating Alt Text...';
            }
            if (progressIcon) {
                progressIcon.className = 'dashicons dashicons-update w3a11y-progress-icon';
            }
            if (statusElement) {
                statusElement.textContent = 'Please keep this page open. The process may take a few minutes depending on the number of images.';
            }
            if (progressDisplay) {
                progressDisplay.textContent = '0 / 0 (0%)';
            }
            if (timeElement) {
                timeElement.textContent = 'Estimated time remaining: calculating...';
            }

            // Reset progress bar
            this.updateProgressBar('#bulk-main-progress', 0);

            // Reset cancel button - remove old listeners and add fresh one
            const cancelButton = document.getElementById('w3a11y-cancel-bulk');
            if (cancelButton) {
                cancelButton.textContent = 'Cancel Generation';
                // Remove all old event listeners by cloning the button
                const newButton = cancelButton.cloneNode(true);
                cancelButton.parentNode.replaceChild(newButton, cancelButton);
                // Add fresh cancel event listener
                newButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.cancelBulkProcessing();
                });
            }

            this.bulkProcessingStopped = false;
            this.currentSessionId = null;
            this.batchStartTime = null;
            this.isSyncMode = false;

            // Update stats
            this.updateBulkStats();
        },




    };

    // Initialize when document is ready - VANILLA JS VERSION
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            W3A11YAltText.init();

            // Initialize bulk processing if on bulk page
            if (document.querySelector('.w3a11y-bulk-container')) {
                W3A11YAltText.initBulkProcessing();
            }
        });
    } else {
        // DOM already loaded
        W3A11YAltText.init();

        // Initialize bulk processing if on bulk page
        if (document.querySelector('.w3a11y-bulk-container')) {
            W3A11YAltText.initBulkProcessing();
        }
    }

})(); // No jQuery wrapper - pure vanilla JavaScript