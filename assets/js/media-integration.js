/**
 * W3A11Y Artisan Media Integration JavaScript
 * 
 * Simplified version that works with backend-injected buttons via print_media_templates
 * No MutationObserver - relies on WordPress PHP backend for button injection
 * Pure vanilla JavaScript - no jQuery dependencies
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Use vanilla JavaScript initialization
(function () {
    'use strict';

    // Helper function for conditional logging
    function w3a11yLog(message, ...args) {
        if (window.w3a11yArtisan && window.w3a11yArtisan.enable_logging) {
            console.log(message, ...args);
        }
    }

    function initW3A11YMediaIntegration() {
        /**
         * Media Integration Controller
         */
        class W3A11YMediaIntegration {
            constructor() {
                this.init();
            }

            init() {
                this.addMediaLibraryButton();
                // this.addEditorButton(); // Disabled - button removed from editor
                this.bindAttachmentEditButtons();
                this.bindMediaModalEvents();

                w3a11yLog('W3A11Y Media Integration initialized (backend-injection mode)');
            }

            /**
             * Add Generate button to Media Library page
             */
            addMediaLibraryButton() {
                // Only on upload.php page
                if (!window.location.href.includes('upload.php')) {
                    return;
                }

                // Wait for page to be fully loaded
                window.addEventListener('load', () => {
                    this.insertMediaLibraryButton();
                });

                // Also try immediately in case page is already loaded
                setTimeout(() => {
                    this.insertMediaLibraryButton();
                }, 100);
            }

            /**
             * Insert the button into media library page
             */
            insertMediaLibraryButton() {
                const addNewButton = document.querySelector('.page-title-action');

                if (addNewButton && !document.getElementById('w3a11y-generate-new-image')) {
                    const artisanButton = document.createElement('button');
                    artisanButton.type = 'button';
                    artisanButton.className = 'w3a11y-artisan-generate-btn';
                    artisanButton.id = 'w3a11y-generate-new-image';

                    const svgIcon = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px; vertical-align: text-bottom;">
                            <path d="M7 21L12 16L17 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 16V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M3 7L6 4L9 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    `;

                    const buttonText = (window.w3a11yArtisan && window.w3a11yArtisan.texts && window.w3a11yArtisan.texts.generate_new_image)
                        ? window.w3a11yArtisan.texts.generate_new_image
                        : 'Generate Image With W3A11Y Artisan';

                    artisanButton.innerHTML = svgIcon + buttonText;

                    // Insert after the existing "Add New" button
                    addNewButton.parentNode.insertBefore(artisanButton, addNewButton.nextSibling);

                    // Bind click event
                    artisanButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.openGenerateModal();
                    });

                    w3a11yLog('Media Library button added');
                }
            }

            /**
             * Add Generate button to post/page editor
             */
            addEditorButton() {
                // Check if we're on post edit page
                const screen = window.pagenow;
                if (!['post', 'page'].includes(screen)) {
                    return;
                }

                // Wait for media buttons to be available
                setTimeout(() => {
                    this.insertEditorButton();
                }, 500);
            }

            /**
             * Insert button into post editor media buttons
             */
            insertEditorButton() {
                const mediaButtons = document.querySelector('#wp-content-media-buttons, .wp-media-buttons');

                if (mediaButtons && !document.getElementById('w3a11y-editor-generate-btn')) {
                    const editorButton = document.createElement('button');
                    editorButton.type = 'button';
                    editorButton.className = 'button w3a11y-media-button';
                    editorButton.id = 'w3a11y-editor-generate-btn';

                    const generateTooltip = (window.w3a11yArtisan && window.w3a11yArtisan.texts && window.w3a11yArtisan.texts.generate_tooltip)
                        ? window.w3a11yArtisan.texts.generate_tooltip
                        : 'Generate images with AI';

                    const generateWithAI = (window.w3a11yArtisan && window.w3a11yArtisan.texts && window.w3a11yArtisan.texts.generate_with_ai)
                        ? window.w3a11yArtisan.texts.generate_with_ai
                        : 'Generate With AI';

                    editorButton.title = generateTooltip;
                    editorButton.innerHTML = `
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px; vertical-align: text-bottom;">
                            <path d="M7 21L12 16L17 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 16V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M3 7L6 4L9 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        ${generateWithAI}
                    `;

                    mediaButtons.appendChild(editorButton);

                    // Bind click event
                    editorButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.openGenerateModal();
                    });

                    w3a11yLog('Editor button added');
                }
            }

            /**
             * Bind events to attachment edit buttons
             */
            bindAttachmentEditButtons() {
                // Handle dynamically added edit buttons in media modal
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-artisan-edit-btn')) {
                        e.preventDefault();
                        const attachmentId = e.target.getAttribute('data-attachment-id');
                        this.openEditModal(attachmentId);
                    }
                });

                // Handle edit buttons in media library grid view
                this.bindGridViewButtons();

                // Handle edit buttons in media library list view  
                this.bindListViewButtons();
            }

            /**
             * Bind buttons in grid view
             */
            bindGridViewButtons() {
                // Use MutationObserver to monitor for new attachments loaded via AJAX
                const attachmentsContainer = document.querySelector('.attachments');
                if (attachmentsContainer) {
                    const observer = new MutationObserver(() => {
                        setTimeout(() => {
                            this.addGridViewButtons();
                        }, 100);
                    });

                    observer.observe(attachmentsContainer, {
                        childList: true,
                        subtree: true
                    });
                }

                // Initial button addition
                setTimeout(() => {
                    this.addGridViewButtons();
                }, 1000);
            }

            /**
             * Add edit buttons to grid view items
             */
            addGridViewButtons() {
                const attachments = document.querySelectorAll('.attachment');
                attachments.forEach((attachment) => {
                    const attachmentId = attachment.getAttribute('data-id');

                    // Skip if not an image or button already exists
                    if (!attachment.classList.contains('image') || attachment.querySelector('.w3a11y-grid-edit-btn')) {
                        return;
                    }

                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'button w3a11y-grid-edit-btn w3a11y-artisan-edit-btn';
                    editButton.setAttribute('data-attachment-id', attachmentId);
                    editButton.title = 'Edit with W3A11Y Artisan';
                    editButton.innerHTML = '<span class="dashicons dashicons-edit"></span>';

                    // Add to attachment actions
                    const actions = attachment.querySelector('.actions');
                    if (actions) {
                        actions.appendChild(editButton);
                    }
                });
            }

            /**
             * Bind buttons in list view
             */
            bindListViewButtons() {
                // Monitor for AJAX updates in list view using MutationObserver
                const theList = document.getElementById('the-list');
                if (theList) {
                    const observer = new MutationObserver(() => {
                        setTimeout(() => {
                            this.addListViewButtons();
                        }, 100);
                    });

                    observer.observe(theList, {
                        childList: true,
                        subtree: true
                    });
                }

                // Initial button addition
                setTimeout(() => {
                    this.addListViewButtons();
                }, 1000);
            }

            /**
             * Add edit buttons to list view items
             */
            addListViewButtons() {
                const rows = document.querySelectorAll('#the-list tr');
                rows.forEach((row) => {
                    const idAttribute = row.getAttribute('id');
                    const attachmentId = idAttribute ? idAttribute.replace('post-', '') : null;

                    // Skip if not an image row or button already exists
                    const hasImage = row.querySelector('.media-icon img');
                    const hasButton = row.querySelector('.w3a11y-list-edit-btn');

                    if (!hasImage || hasButton || !attachmentId) {
                        return;
                    }

                    const editButton = document.createElement('a');
                    editButton.href = '#';
                    editButton.className = 'w3a11y-list-edit-btn w3a11y-artisan-edit-btn';
                    editButton.setAttribute('data-attachment-id', attachmentId);
                    editButton.textContent = 'Edit with W3A11Y Artisan';

                    // Add to row actions
                    const rowActions = row.querySelector('.row-actions');
                    if (rowActions) {
                        const spans = rowActions.querySelectorAll('span');
                        const lastAction = spans[spans.length - 1];
                        if (lastAction) {
                            const newSpan = document.createElement('span');
                            newSpan.className = 'w3a11y-edit';
                            newSpan.appendChild(editButton);

                            const separator = document.createTextNode(' | ');
                            lastAction.parentNode.insertBefore(separator, lastAction.nextSibling);
                            lastAction.parentNode.insertBefore(newSpan, separator.nextSibling);
                        }
                    }
                });
            }

            /**
             * Bind media modal events (simplified - backend handles button injection)
             */
            bindMediaModalEvents() {
                // Handle clicks on backend-injected generate buttons
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-generate-new-image') ||
                        e.target.closest('.w3a11y-generate-new-image')) {
                        e.preventDefault();
                        e.stopPropagation();
                        w3a11yLog('W3A11Y: Generate button clicked (backend-injected)');
                        this.openGenerateModal();
                    }
                });

                // Handle clicks on edit buttons (already handled in bindAttachmentEditButtons)
                // This is redundant but keeping for safety
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('w3a11y-artisan-edit-btn') ||
                        e.target.closest('.w3a11y-artisan-edit-btn')) {
                        e.preventDefault();
                        const button = e.target.classList.contains('w3a11y-artisan-edit-btn') ?
                            e.target : e.target.closest('.w3a11y-artisan-edit-btn');
                        const attachmentId = button.getAttribute('data-attachment-id');
                        if (attachmentId) {
                            this.openEditModal(attachmentId);
                        }
                    }
                });

                // Set up callback for image save refresh
                this.setupImageSaveCallback();

                w3a11yLog('W3A11Y: Media modal events bound (backend injection mode)');
            }

            /**
             * Setup callback for when images are saved to refresh buttons
             */
            setupImageSaveCallback() {
                // Set up a global callback that can be called from admin-modal.js
                window.W3A11YArtisan = window.W3A11YArtisan || {};
                window.W3A11YArtisan.onImageSaved = (imageData) => {
                    w3a11yLog('W3A11Y: Image saved, refreshing media library...', imageData);

                    // Refresh the media library content
                    setTimeout(() => {
                        if (wp && wp.media && wp.media.frame) {
                            try {
                                const frame = wp.media.frame;
                                if (frame.content && frame.content.get()) {
                                    const view = frame.content.get();
                                    if (view.collection) {
                                        view.collection.more().done(() => {
                                            w3a11yLog('W3A11Y: Media library refreshed successfully');
                                        });
                                    }
                                }
                            } catch (e) {
                                w3a11yLog('W3A11Y: Could not refresh media library programmatically');
                            }
                        }
                    }, 500);
                };
            }

            // ========================================
            // REMOVED COMPLEX METHODS:
            // - observeMediaModalChanges() - No longer needed, backend handles injection
            // - enhanceMediaModal() - No longer needed, backend handles injection  
            // - addAttachmentDetailsButtons() - Handled by backend attachment_fields_to_edit
            // ========================================

            /**
             * Open generate modal with retry logic for page builders
             */
            openGenerateModal() {
                if (!w3a11yArtisan.api_configured) {
                    this.showNotConfiguredMessage();
                    return;
                }

                // Enhanced modal system check with retry logic
                const tryOpenModal = (attempts = 0) => {
                    if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                        window.W3A11YArtisan.openModal('generate');
                        return;
                    }

                    // Try to ensure modal scripts are loaded
                    this.ensureModalScriptsLoaded();

                    if (attempts < 10) {
                        w3a11yLog(`W3A11Y: Modal system not ready, waiting... (attempt ${attempts + 1})`);
                        setTimeout(() => {
                            tryOpenModal(attempts + 1);
                        }, 500);
                        return;
                    }

                    // Final fallback: redirect to media library
                    w3a11yLog('W3A11Y: Modal system unavailable, using fallback redirect');
                    const currentUrl = window.location.href;
                    const mediaUrl = w3a11yArtisan.media_library_url || '/wp-admin/upload.php?w3a11y_generate=1';

                    // Open in new tab for page builders
                    if (currentUrl.includes('elementor') || currentUrl.includes('builder') ||
                        window.parent !== window) {
                        window.open(mediaUrl, '_blank');
                    } else {
                        window.location.href = mediaUrl;
                    }
                };

                tryOpenModal();
            }

            /**
             * Ensure modal scripts are loaded dynamically
             */
            ensureModalScriptsLoaded() {
                if (!window.W3A11YArtisan && !this.loadingModalScript) {
                    this.loadingModalScript = true;

                    // Try to load the modal system dynamically
                    const modalScript = document.querySelector('script[src*="admin-modal"]');
                    if (!modalScript && w3a11yArtisan.plugin_url) {
                        w3a11yLog('W3A11Y: Loading modal system dynamically');
                        const script = document.createElement('script');
                        script.src = `${w3a11yArtisan.plugin_url}assets/js/admin-modal.js?ver=${w3a11yArtisan.version || '1.0.0'}`;
                        script.onload = () => {
                            w3a11yLog('W3A11Y: Modal system loaded dynamically');
                            this.loadingModalScript = false;
                        };
                        script.onerror = () => {
                            console.error('W3A11Y: Failed to load modal system');
                            this.loadingModalScript = false;
                        };
                        document.head.appendChild(script);
                    }
                }
            }

            /**
             * Open edit modal for specific attachment
             */
            openEditModal(attachmentId) {
                if (!attachmentId) {
                    console.error('No attachment ID provided');
                    return;
                }

                if (!w3a11yArtisan.api_configured) {
                    this.showNotConfiguredMessage();
                    return;
                }

                if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                    window.W3A11YArtisan.openModal('edit', attachmentId);
                } else {
                    console.error('W3A11Y Artisan modal not available');
                    this.showErrorMessage('Modal system not loaded. Please refresh the page.');
                }
            }

            /**
             * Show not configured message
             */
            showNotConfiguredMessage() {
                const message = (window.w3a11yArtisan && window.w3a11yArtisan.texts && window.w3a11yArtisan.texts.error_no_api_key)
                    ? window.w3a11yArtisan.texts.error_no_api_key
                    : 'Please configure your W3A11Y API key in Settings.';
                const settingsUrl = window.w3a11yArtisan ? window.w3a11yArtisan.settings_url : '#';

                const notice = document.createElement('div');
                notice.className = 'notice notice-warning is-dismissible w3a11y-temp-notice';
                notice.innerHTML = `
                    <p><strong>W3A11Y Artisan:</strong> ${message} 
                       <a href="${settingsUrl}" class="button button-small">Go to Settings</a>
                    </p>
                `;

                document.body.appendChild(notice);

                setTimeout(() => {
                    notice.style.opacity = '0';
                    notice.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        if (notice.parentNode) {
                            notice.parentNode.removeChild(notice);
                        }
                    }, 300);
                }, 8000);
            }

            /**
             * Show error message
             */
            showErrorMessage(message) {
                const notice = document.createElement('div');
                notice.className = 'notice notice-error is-dismissible w3a11y-temp-notice';
                notice.innerHTML = `
                    <p><strong>W3A11Y Artisan:</strong> ${message}</p>
                `;

                document.body.appendChild(notice);

                setTimeout(() => {
                    notice.style.opacity = '0';
                    notice.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        if (notice.parentNode) {
                            notice.parentNode.removeChild(notice);
                        }
                    }, 300);
                }, 5000);
            }

            /**
             * Check if current page supports media integration
             */
            isMediaPage() {
                const currentPage = window.pagenow;
                const supportedPages = ['upload', 'post', 'page', 'edit-post', 'edit-page'];

                return supportedPages.includes(currentPage) ||
                    window.location.href.includes('upload.php') ||
                    window.location.href.includes('post.php') ||
                    window.location.href.includes('post-new.php');
            }
        }

        /**
         * Initialize when document is ready
         */
        function initMediaIntegration() {
            // Always initialize for media modal detection
            const mediaIntegration = new W3A11YMediaIntegration();

            // Expose globally for access from WordPress media frame events
            window.W3A11YArtisan = window.W3A11YArtisan || {};
            window.W3A11YArtisan.mediaIntegration = mediaIntegration;
            window.w3a11yMediaIntegration = mediaIntegration; // Also expose directly

            w3a11yLog('W3A11Y Media Integration: Initialized globally for media modal detection (safe mode)');
        }

        // Initialize the media integration
        initMediaIntegration();
    }

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initW3A11YMediaIntegration);
    } else {
        initW3A11YMediaIntegration();
    }
})();