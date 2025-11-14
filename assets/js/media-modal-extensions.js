/**
 * W3A11Y Artisan Media Modal Extensions
 * 
 * Pure vanilla JavaScript WordPress wp.media.view extensions for seamless integration
 * with the WordPress Media Modal system. No jQuery dependencies.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

(function () {
    'use strict';

    // Ensure wp.media is available and wait for it to be fully loaded
    function initializeWhenReady() {
        if (typeof wp === 'undefined' || !wp.media || !wp.media.view) {
            if (window.w3a11yArtisan && window.w3a11yArtisan.enable_logging) {
                console.log('W3A11Y: wp.media not ready yet, waiting...');
            }
            setTimeout(initializeWhenReady, 100);
            return;
        }

        // Check if required components are available
        if (!wp.media.view.Toolbar || !wp.media.view.Attachment) {
            console.log('W3A11Y: wp.media.view components not ready yet, waiting...');
            setTimeout(initializeWhenReady, 100);
            return;
        }

        console.log('W3A11Y: Initializing media modal extensions');
        initializeMediaExtensions();
    }

    // Helper functions for common functionality
    const W3A11YHelpers = {
        /**
         * Creates a standardized generate button configuration
         */
        createGenerateButtonConfig: () => ({
            text: wp.i18n.__('Generate with W3A11Y Artisan', 'w3a11y'),
            style: 'secondary',
            priority: 25,
            requires: false,
            className: 'w3a11y-generate-new-image',
            click: function () {
                if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                    window.W3A11YArtisan.openModal('generate');
                } else {
                    console.error('W3A11Y: Modal system not available');
                }
            }
        }),

        /**
         * Adds styled icon to generate button
         */
        styleGenerateButton: function (element) {
            const generateBtn = element.querySelector('.w3a11y-generate-new-image');
            if (!generateBtn || generateBtn.querySelector('.dashicons')) return;

            const iconSpan = document.createElement('span');
            iconSpan.className = 'dashicons dashicons-art';
            Object.assign(iconSpan.style, {
                marginTop: '5px',
                marginRight: '5px'
            });
            generateBtn.insertBefore(iconSpan, generateBtn.firstChild);

            Object.assign(generateBtn.style, {
                background: 'linear-gradient(135deg, #137fec 0%, #1056d9 100%)',
                borderColor: '#137fec',
                color: '#ffffff',
                textShadow: 'none',
                boxShadow: '0 2px 4px rgba(19, 127, 236, 0.2)',
                marginTop: '15px',
                marginLeft: '10px',
                float: 'left'
            });
        },

        /**
         * Updates WordPress Backbone model for attachment
         */
        updateWordPressModel: function (attachmentId, altText) {
            let modelUpdated = false;

            // Method 1: Use wp.media.attachment(id) - most reliable
            if (attachmentId && typeof wp !== 'undefined' && wp.media && wp.media.attachment) {
                try {
                    const attachment = wp.media.attachment(attachmentId);
                    if (attachment && attachment.set) {
                        attachment.set('alt', altText);
                        attachment.save();
                        console.log('W3A11Y: WordPress model updated and saved');
                        modelUpdated = true;
                    }
                } catch (error) {
                    console.log('W3A11Y: Attachment model update failed:', error);
                }
            }

            // Method 2: Try frame selection fallback
            if (!modelUpdated && typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                try {
                    const currentState = wp.media.frame.state();
                    const selection = currentState && currentState.get && currentState.get('selection');
                    const attachment = selection && selection.first && selection.first();

                    if (attachment && attachment.set) {
                        attachment.set('alt', altText);
                        console.log('W3A11Y: WordPress model updated via frame selection');
                        modelUpdated = true;
                    }
                } catch (error) {
                    console.log('W3A11Y: Frame selection update failed:', error);
                }
            }

            return modelUpdated;
        },

        /**
         * Creates toolbar extension with generate button
         */
        createToolbarExtension: function (originalToolbar) {
            return originalToolbar.extend({
                initialize: function () {
                    originalToolbar.prototype.initialize.apply(this, arguments);
                    this.set('w3a11y_generate', W3A11YHelpers.createGenerateButtonConfig());
                    setTimeout(() => W3A11YHelpers.styleGenerateButton(this.el), 50);
                }
            });
        }
    };

    function initializeMediaExtensions() {
        // Extend both toolbar types with the same logic
        if (wp.media.view.Toolbar.Select) {
            wp.media.view.Toolbar.Select = W3A11YHelpers.createToolbarExtension(wp.media.view.Toolbar.Select);
        }

        if (wp.media.view.Toolbar.Insert) {
            wp.media.view.Toolbar.Insert = W3A11YHelpers.createToolbarExtension(wp.media.view.Toolbar.Insert);
        }

        /**
         * Extend Attachment Details view to add Edit button
         * Using vanilla JavaScript approach with DOM manipulation
         */
        if (wp.media.view.Attachment && wp.media.view.Attachment.Details) {
            const originalAttachmentDetails = wp.media.view.Attachment.Details;
            wp.media.view.Attachment.Details = originalAttachmentDetails.extend({
                initialize: function () {
                    originalAttachmentDetails.prototype.initialize.apply(this, arguments);
                    // Re-render when model changes to ensure button appears
                    this.listenTo(this.model, 'change:sizes change:uploading', this.render);
                },

                render: function () {
                    // Call original render first
                    originalAttachmentDetails.prototype.render.apply(this, arguments);

                    // Add our button after render
                    setTimeout(() => {
                        this.addW3A11YEditButton();
                    }, 50); // Small delay to ensure DOM is ready

                    return this;
                },

                addW3A11YEditButton: function () {
                    // Only add button for images
                    if (!this.model || this.model.get('type') !== 'image') {
                        return;
                    }

                    // Check if plugin is configured
                    if (!window.w3a11yArtisan || !window.w3a11yArtisan.api_configured) {
                        return;
                    }

                    // Check if button already exists
                    if (this.el.querySelector('.w3a11y-artisan-editBtn')) {
                        return;
                    }

                    // Find the .edit-attachment element to insert after
                    const editAttachmentElement = this.el.querySelector('.edit-attachment');
                    if (!editAttachmentElement) {
                        // Fallback: append to the end if edit-attachment not found
                        this.addW3A11YEditButtonFallback();
                        return;
                    }

                    // Create button element using vanilla JS
                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'w3a11y-artisan-editBtn';
                    editButton.setAttribute('data-attachment-id', this.model.get('id'));
                    editButton.style.display = 'block';
                    editButton.style.background = 'none';
                    editButton.style.border = 'none';
                    editButton.style.padding = '4px 0';
                    editButton.style.color = '#2271b1';
                    editButton.style.cursor = 'pointer';
                    editButton.appendChild(document.createTextNode(wp.i18n.__('Edit with W3A11Y Artisan', 'w3a11y')));

                    // Add click event listener - FIXED: Pass attachmentId directly, not as object
                    editButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        const attachmentId = this.model.get('id');

                        if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                            window.W3A11YArtisan.openModal('edit', attachmentId);
                        } else {
                            console.error('W3A11Y: Modal system not available');
                        }
                    });

                    // Insert after the edit-attachment element
                    editAttachmentElement.parentNode.insertBefore(editButton, editAttachmentElement.nextSibling);
                },

                addW3A11YEditButtonFallback: function () {
                    // Fallback method - create button that appears after WordPress .edit-attachment button
                    const editAttachmentElement = this.el.querySelector('.edit-attachment');

                    if (editAttachmentElement) {
                        // Create edit button element using vanilla JS
                        const editButton = document.createElement('button');
                        editButton.type = 'button';
                        editButton.className = 'w3a11y-artisan-editBtn';
                        editButton.setAttribute('data-attachment-id', this.model.get('id'));
                        editButton.style.display = 'block';
                        editButton.style.background = 'none';
                        editButton.style.border = 'none';
                        editButton.style.padding = '4px 0';
                        editButton.style.color = '#2271b1';
                        editButton.style.cursor = 'pointer';
                        editButton.appendChild(document.createTextNode(wp.i18n.__('Edit with W3A11Y Artisan', 'w3a11y')));

                        // Add click event listener
                        editButton.addEventListener('click', (e) => {
                            e.preventDefault();
                            const attachmentId = this.model.get('id');

                            if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                                window.W3A11YArtisan.openModal('edit', attachmentId);
                            } else {
                                console.error('W3A11Y: Modal system not available');
                            }
                        });

                        // Insert after the edit-attachment element
                        editAttachmentElement.parentNode.insertBefore(editButton, editAttachmentElement.nextSibling);
                    } else {
                        // Last resort fallback - create button container at the end if .edit-attachment not found
                        const buttonContainer = document.createElement('div');
                        buttonContainer.className = 'w3a11y-attachment-actions';

                        const editButton = document.createElement('button');
                        editButton.type = 'button';
                        editButton.className = 'button button-primary w3a11y-artisan-edit-btn';
                        editButton.setAttribute('data-attachment-id', this.model.get('id'));
                        editButton.style.width = '100%';

                        const iconSpan = document.createElement('span');
                        iconSpan.className = 'dashicons dashicons-art';
                        iconSpan.style.marginTop = '3px';
                        iconSpan.style.marginRight = '5px';

                        editButton.appendChild(iconSpan);
                        editButton.appendChild(document.createTextNode(wp.i18n.__('Edit with W3A11Y Artisan', 'w3a11y')));

                        // Add click event listener
                        editButton.addEventListener('click', (e) => {
                            e.preventDefault();
                            const attachmentId = this.model.get('id');

                            if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                                window.W3A11YArtisan.openModal('edit', attachmentId);
                            } else {
                                console.error('W3A11Y: Modal system not available');
                            }
                        });

                        buttonContainer.appendChild(editButton);
                        this.el.appendChild(buttonContainer);
                    }
                }
            });
        }

        /**
         * Extend Attachment Details Two Column view for Grid mode
         */
        if (wp.media.view.Attachment && wp.media.view.Attachment.Details.TwoColumn) {
            const originalTwoColumn = wp.media.view.Attachment.Details.TwoColumn;
            wp.media.view.Attachment.Details.TwoColumn = originalTwoColumn.extend({
                initialize: function () {
                    originalTwoColumn.prototype.initialize.apply(this, arguments);
                    // Re-render when model changes to ensure button appears
                    this.listenTo(this.model, 'change:sizes change:uploading', this.render);
                },

                render: function () {
                    // Call original render first
                    originalTwoColumn.prototype.render.apply(this, arguments);

                    // Add our button after render
                    setTimeout(() => {
                        this.addW3A11YEditButton();
                    }, 50); // Small delay to ensure DOM is ready

                    return this;
                },

                addW3A11YEditButton: function () {
                    // Only add button for images
                    if (!this.model || this.model.get('type') !== 'image') {
                        return;
                    }

                    // Check if plugin is configured
                    if (!window.w3a11yArtisan || !window.w3a11yArtisan.api_configured) {
                        return;
                    }

                    // Check if button already exists
                    if (this.el.querySelector('.w3a11y-artisan-edit-btn')) {
                        return;
                    }

                    // Find the .edit-attachment element to insert after
                    const editAttachmentElement = this.el.querySelector('.edit-attachment');
                    if (!editAttachmentElement) {
                        // Fallback: append to the end if edit-attachment not found
                        this.addW3A11YEditButtonFallback();
                        return;
                    }

                    // Create button element using vanilla JS
                    const editButton = document.createElement('button');
                    editButton.className = 'w3a11y-artisan-edit-btn';
                    editButton.setAttribute('data-attachment-id', this.model.get('id'));
                    editButton.style.marginLeft = '0.6em';

                    const iconSpan = document.createElement('span');
                    iconSpan.className = 'dashicons dashicons-art';

                    editButton.appendChild(iconSpan);
                    editButton.appendChild(document.createTextNode(wp.i18n.__('Edit with W3A11Y Artisan', 'w3a11y')));

                    // Add click event listener - FIXED: Pass attachmentId directly, not as object
                    editButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        const attachmentId = this.model.get('id');

                        if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                            window.W3A11YArtisan.openModal('edit', attachmentId);
                        } else {
                            console.error('W3A11Y: Modal system not available');
                        }
                    });

                    // Insert after the edit-attachment element
                    editAttachmentElement.parentNode.insertBefore(editButton, editAttachmentElement.nextSibling);
                }
            });
        }

        /**
         * Centralized AltText functionality
         */
        const AltTextFunctionality = {
            /**
             * Validates if alt text button should be added
             */
            shouldAddButton: function (viewInstance) {
                return (
                    viewInstance.model &&
                    viewInstance.model.get('type') === 'image' &&
                    window.w3a11yArtisan &&
                    window.w3a11yArtisan.api_configured &&
                    !viewInstance.el.querySelector('.w3a11y-alttext-btn')
                );
            },

            /**
             * Creates alt text button with proper styling and events
             */
            createAltTextButton: function (viewInstance, altTextField) {
                const currentAltText = altTextField.value || viewInstance.model.get('alt') || '';
                const buttonText = currentAltText.trim()
                    ? wp.i18n.__('Update Alt Text', 'w3a11y')
                    : wp.i18n.__('Generate Alt Text', 'w3a11y');

                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'w3a11y-alttext-container';
                Object.assign(buttonContainer.style, {
                    width: '65%',
                    float: 'right',
                    marginTop: '10px'
                });

                const altTextButton = document.createElement('button');
                Object.assign(altTextButton, {
                    type: 'button',
                    className: 'button w3a11y-alttext-btn',
                    textContent: buttonText
                });
                altTextButton.setAttribute('data-attachment-id', viewInstance.model.get('id'));

                // Add icon
                const iconSpan = document.createElement('i');
                iconSpan.className = 'dashicons dashicons-translation';
                altTextButton.insertBefore(iconSpan, altTextButton.firstChild);

                // Add event listener
                altTextButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    AltTextFunctionality.handleAltTextGeneration(viewInstance, altTextButton, altTextField);
                });

                buttonContainer.appendChild(altTextButton);
                return buttonContainer;
            },

            /**
             * Adds alt text button to attachment details
             */
            addW3A11YAltTextButton: function (viewInstance) {
                if (!AltTextFunctionality.shouldAddButton(viewInstance)) return;

                const altTextField = viewInstance.el.querySelector('[data-setting="alt"]');
                if (!altTextField) return;

                const buttonContainer = AltTextFunctionality.createAltTextButton(viewInstance, altTextField);
                const altFieldContainer = altTextField.closest('.setting');
                if (altFieldContainer) {
                    altFieldContainer.appendChild(buttonContainer);
                }
            },

            /**
             * Handles the alt text generation process
             */
            handleAltTextGeneration: function (viewInstance, button, altTextField) {
                const attachmentData = AltTextFunctionality.getAttachmentData(viewInstance);
                if (!attachmentData.imageUrl) {
                    console.error('W3A11Y: No image URL available');
                    return;
                }

                AltTextFunctionality.setButtonState(button, 'generating');

                // Try primary alt text generation service
                if (window.W3A11YAltText && window.W3A11YAltText.generateAltText) {
                    AltTextFunctionality.callPrimaryAltTextService(viewInstance, attachmentData, button, altTextField);
                } else {
                    // Fallback to direct AJAX call
                    console.log('W3A11Y: Using direct AJAX fallback');
                    AltTextFunctionality.callFallbackAltTextService(viewInstance, attachmentData, button, altTextField);
                }

                // Safety timeout to reset button state
                AltTextFunctionality.setButtonTimeout(button, 30000);
            },

            /**
             * Extracts attachment data for alt text generation
             */
            getAttachmentData: function (viewInstance) {
                return {
                    attachmentId: viewInstance.model.get('id'),
                    imageUrl: viewInstance.model.get('url'),
                    context: [
                        viewInstance.model.get('title') || '',
                        viewInstance.model.get('caption') || '',
                        viewInstance.model.get('description') || ''
                    ].filter(Boolean).join('. ')
                };
            },

            /**
             * Manages button visual state during generation
             */
            setButtonState: function (button, state) {
                const iconElement = button.querySelector('.dashicons');

                if (state === 'generating') {
                    button.disabled = true;
                    button.classList.add('generating');
                    button.textContent = wp.i18n.__('Generating...', 'w3a11y');
                } else if (state === 'success') {
                    button.disabled = false;
                    button.classList.remove('generating');
                    button.textContent = wp.i18n.__('Update Alt Text', 'w3a11y');
                } else {
                    button.disabled = false;
                    button.classList.remove('generating');
                }

                if (iconElement) {
                    button.insertBefore(iconElement, button.firstChild);
                }
            },

            /**
             * Calls primary alt text generation service
             */
            callPrimaryAltTextService: function (viewInstance, attachmentData, button, altTextField) {
                // Create a custom callback that handles both success and error cases
                const customCallback = (result) => {
                    if (result && result.alt_text) {
                        AltTextFunctionality.processAltTextResult(result.alt_text, attachmentData.attachmentId, button);
                    } else {
                        // Handle error case - the generateAltText method should have already shown notifications
                        // but we'll reset the button state just in case
                        AltTextFunctionality.setButtonState(button, 'reset');
                    }
                };

                // Call the primary service - it already has 402 error handling with centralized notifications
                window.W3A11YAltText.generateAltText(
                    attachmentData.imageUrl,
                    attachmentData.context,
                    attachmentData.attachmentId,
                    customCallback
                );
            },

            /**
             * Processes successful alt text generation result
             */
            processAltTextResult: function (altText, attachmentId, button) {
                // Update WordPress model
                W3A11YHelpers.updateWordPressModel(attachmentId, altText);

                // Update all alt text fields
                AltTextFunctionality.updateAltTextFields(altText);

                // Update button state
                AltTextFunctionality.setButtonState(button, 'success');
            },

            /**
             * Sets safety timeout for button reset
             */
            setButtonTimeout: function (button, timeout) {
                setTimeout(() => {
                    if (button.classList.contains('generating')) {
                        this.setButtonState(button, 'reset');
                    }
                }, timeout);
            },

            /**
             * Updates all alt text fields across the page
             */
            updateAltTextFields: function (altText) {
                console.log('W3A11Y: Updating alt text fields with:', altText);

                const altTextContainers = document.querySelectorAll('.w3a11y-alttext-container');
                altTextContainers.forEach((container, index) => {
                    const settingParent = container.closest('.setting');
                    const textarea = settingParent && settingParent.querySelector('textarea');

                    if (textarea) {
                        textarea.value = altText;
                        console.log(`W3A11Y: Updated alt text field ${index + 1}:`, textarea.value);

                        // Trigger WordPress change events
                        ['change', 'input'].forEach(eventType => {
                            textarea.dispatchEvent(new Event(eventType, { bubbles: true }));
                        });
                    }
                });

                // Update all button texts to "Update"
                document.querySelectorAll('.w3a11y-alttext-btn').forEach(button => {
                    const iconElement = button.querySelector('.dashicons');
                    button.textContent = wp.i18n.__('Update Alt Text', 'w3a11y');
                    if (iconElement) {
                        button.insertBefore(iconElement, button.firstChild);
                    }
                });

                console.log('W3A11Y: Alt text fields updated successfully');
            },

            /**
             * Fallback AJAX call for alt text generation
             */
            callFallbackAltTextService: function (viewInstance, attachmentData, button, altTextField) {
                const formData = new FormData();
                Object.entries({
                    action: 'w3a11y_generate_alttext',
                    nonce: window.w3a11yAltTextAjax?.nonce || '',
                    image_url: attachmentData.imageUrl,
                    context: attachmentData.context,
                    attachment_id: attachmentData.attachmentId
                }).forEach(([key, value]) => formData.append(key, value));

                fetch(window.w3a11yAltTextAjax?.ajax_url || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('W3A11Y: Received AJAX response:', data);

                        if (data.success && data.data && data.data.alt_text) {
                            console.log('W3A11Y: Alt text generated successfully:', data.data.alt_text);
                            AltTextFunctionality.processAltTextResult(data.data.alt_text, attachmentData.attachmentId, button);
                        } else {
                            console.error('W3A11Y: Alt text generation failed', data);

                            const errorMessage = (data.data && data.data.message) || 'Failed to generate alt text';
                            const statusCode = (data.data && data.data.status_code) || 0;

                            // Debug logging
                            console.log('W3A11Y Debug: Error details:', {
                                message: errorMessage,
                                statusCode: statusCode,
                                fullData: data
                            });

                            // Check for insufficient credits (402 error or credit-related messages)
                            const isCreditError = (
                                statusCode === 402 ||
                                errorMessage.toLowerCase().includes('insufficient credits') ||
                                errorMessage.toLowerCase().includes('no credits') ||
                                errorMessage.toLowerCase().includes('not enough credits') ||
                                errorMessage.toLowerCase().includes('credit') ||
                                // Also check if it's a generic error that might be credit-related
                                (errorMessage.includes('error occurred') && statusCode === 0)
                            );

                            if (isCreditError) {
                                console.log('W3A11Y Debug: Detected potential credit error, triggering centralized notification');

                                // Use centralized notification manager via AJAX
                                AltTextFunctionality.triggerCentralizedNotification(
                                    errorMessage,
                                    'error',
                                    statusCode || 402,
                                    (data.data && data.data.available_credits) || 0
                                );
                            } else {
                                // For other errors, just log them (no intrusive notifications in media modal)
                                console.error('W3A11Y: Alt text generation error:', errorMessage);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('W3A11Y: AJAX error', error);
                    })
                    .finally(() => {
                        AltTextFunctionality.setButtonState(button, 'reset');
                    });
            },

            /**
             * Trigger centralized notification via AJAX
             * @param {string} message - Error message
             * @param {string} type - Notification type (error, warning, etc.)
             * @param {number} statusCode - HTTP status code
             * @param {number} availableCredits - Available credits count
             */
            triggerCentralizedNotification: function (message, type = 'error', statusCode = 0, availableCredits = 0) {
                // Use WordPress AJAX (ensure w3a11yArtisan is available)
                const ajaxUrl = (window.w3a11yArtisan && window.w3a11yArtisan.ajax_url) ||
                    (window.ajaxurl) ||
                    '/wp-admin/admin-ajax.php';

                // Try to get nonce from multiple sources
                const nonce = (window.w3a11yArtisan && window.w3a11yArtisan.nonce) ||
                    (window.w3a11yAltTextAjax && window.w3a11yAltTextAjax.nonce) ||
                    '';

                console.log('W3A11Y Debug: Triggering centralized notification', {
                    message: message,
                    type: type,
                    statusCode: statusCode,
                    availableCredits: availableCredits,
                    ajaxUrl: ajaxUrl,
                    nonce: nonce ? 'available' : 'missing'
                });

                const formData = new FormData();
                formData.append('action', 'w3a11y_add_notification');
                formData.append('nonce', nonce);
                formData.append('type', type);
                formData.append('message', message);
                formData.append('context', 'both');
                formData.append('status_code', statusCode);
                formData.append('available_credits', availableCredits);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('W3A11Y Debug: Centralized notification response:', data);

                        if (data.success) {
                            console.log('W3A11Y: Centralized notification triggered successfully');

                            // Show immediate frontend notification if available
                            if (data.data && data.data.frontend_notification && window.W3A11YNotifications) {
                                const notif = data.data.frontend_notification;
                                console.log('W3A11Y Debug: Showing frontend notification:', notif);
                                window.W3A11YNotifications.show(notif.message, notif.type, notif.duration);
                            } else {
                                console.log('W3A11Y Debug: No frontend notification data or W3A11YNotifications not available');
                                // Fallback: show a basic notification
                                if (window.W3A11YNotifications) {
                                    window.W3A11YNotifications.show(
                                        message + ' <a href="https://w3a11y.com/pricing" target="_blank">Purchase more credits</a>',
                                        type,
                                        0
                                    );
                                }
                            }

                            // Also refresh page after a delay to show admin notice
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            console.error('W3A11Y: Failed to trigger centralized notification:', data);
                            // Fallback: show notification directly
                            if (window.W3A11YNotifications) {
                                window.W3A11YNotifications.show(
                                    message + ' <a href="https://w3a11y.com/pricing" target="_blank">Purchase more credits</a>',
                                    type,
                                    0
                                );
                            }
                        }
                    })
                    .catch(error => {
                        console.error('W3A11Y: Error triggering centralized notification:', error);
                        // Fallback: show notification directly
                        if (window.W3A11YNotifications) {
                            window.W3A11YNotifications.show(
                                message + ' <a href="https://w3a11y.com/pricing" target="_blank">Purchase more credits</a>',
                                type,
                                0
                            );
                        }
                    });
            }
        };

        // Add AltText functionality to both detail views
        if (wp.media.view.Attachment && wp.media.view.Attachment.Details) {
            const originalDetailsRender = wp.media.view.Attachment.Details.prototype.render;
            wp.media.view.Attachment.Details.prototype.render = function () {
                const result = originalDetailsRender.apply(this, arguments);
                const viewInstance = this;
                setTimeout(() => {
                    AltTextFunctionality.addW3A11YAltTextButton(viewInstance);
                }, 100);
                return result;
            };
        }

        if (wp.media.view.Attachment && wp.media.view.Attachment.Details.TwoColumn) {
            const originalTwoColumnRender = wp.media.view.Attachment.Details.TwoColumn.prototype.render;
            wp.media.view.Attachment.Details.TwoColumn.prototype.render = function () {
                const result = originalTwoColumnRender.apply(this, arguments);
                const viewInstance = this;
                setTimeout(() => {
                    AltTextFunctionality.addW3A11YAltTextButton(viewInstance);
                }, 100);
                return result;
            };
        }

        console.log('W3A11Y: Media modal extensions loaded successfully');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeWhenReady);
    } else {
        initializeWhenReady();
    }

})();