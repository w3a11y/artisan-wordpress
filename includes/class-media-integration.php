<?php
/**
 * W3A11Y Artisan Media Integration Class
 * 
 * Handles integration with WordPress Media Library including adding buttons
 * and hooking into media upload workflows.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Artisan Media Integration Class
 * 
 * @since 1.0.0
 */
class W3A11Y_Artisan_Media_Integration {
    
    /**
     * Media integration instance
     * 
     * @var W3A11Y_Artisan_Media_Integration
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Get media integration instance (Singleton pattern)
     * 
     * @return W3A11Y_Artisan_Media_Integration
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize media integration hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Add buttons to media library page
        add_action('admin_footer-upload.php', array($this, 'add_media_library_button'));
        
        // Add custom media buttons via media_buttons hook
        // add_action('media_buttons', array($this, 'add_media_buttons'), 15);
        
        // Enqueue scripts on media pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        
        // Add Artisan modal to admin pages
        add_action('admin_footer', array($this, 'add_artisan_modal'));
        
        // Handle media library AJAX actions
        add_action('wp_ajax_w3a11y_get_attachment_data', array($this, 'ajax_get_attachment_data'));
    }
    
    /**
     * Add Generate button to Media Library page
     * 
     * @since 1.0.0
     */
    public function add_media_library_button() {
        // Only add if user has required capabilities
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Check if plugin is configured
        if (!w3a11y_artisan_is_configured()) {
            return;
        }
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Find the "Add New" button and add our button after it
            var addNewButton = document.querySelector('.page-title-action');
            if (addNewButton) {
                var artisanButton = document.createElement('button');
                artisanButton.type = 'button';
                artisanButton.className = 'w3a11y-artisan-generate-btn';
                artisanButton.id = 'w3a11y-generate-new-image';
                artisanButton.innerHTML = '<svg width="16px" height="16px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="none"><g fill="#000000"><path d="M4.083.183a.5.5 0 00-.65.65l.393.981a.5.5 0 010 .371l-.393.982a.5.5 0 00.65.65l.981-.393a.5.5 0 01.372 0l.98.392a.5.5 0 00.65-.65l-.392-.98a.5.5 0 010-.372l.393-.981a.5.5 0 00-.65-.65l-.981.392a.5.5 0 01-.372 0l-.98-.392z"/><path fill-rule="evenodd" d="M11.414 4.104a2 2 0 00-2.828 0L.808 11.882a2 2 0 002.828 2.828l7.778-7.778a2 2 0 000-2.828zm-1.768 1.06a.5.5 0 01.708.707l-.884.884-.707-.707.883-.884zM7.702 7.11l.707.707-5.834 5.834a.5.5 0 11-.707-.707l5.834-5.834z" clip-rule="evenodd"/><path d="M10.572 11.21a.5.5 0 010-.92l1.22-.522a.5.5 0 00.262-.262l.522-1.22a.5.5 0 01.92 0l.521 1.22a.5.5 0 00.263.262l1.219.522a.5.5 0 010 .92l-1.219.522a.5.5 0 00-.263.263l-.522 1.218a.5.5 0 01-.919 0l-.522-1.218a.5.5 0 00-.263-.263l-1.219-.522zM12.833.183a.5.5 0 00-.65.65l.293.731a.5.5 0 010 .371l-.293.732a.5.5 0 00.65.65l.731-.293a.5.5 0 01.372 0l.73.292a.5.5 0 00.65-.65l-.292-.73a.5.5 0 010-.372l.293-.731a.5.5 0 00-.65-.65l-.731.292a.5.5 0 01-.372 0l-.73-.292z"/></g></svg>' +
                    '<?php echo esc_js(__('Generate Image With W3A11Y Artisan', 'w3a11y-artisan')); ?>';
                
                // Insert after the Add New button
                addNewButton.parentNode.insertBefore(artisanButton, addNewButton.nextSibling);
                
                // Handle click
                artisanButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.W3A11YArtisan && window.W3A11YArtisan.openModal) {
                        window.W3A11YArtisan.openModal('generate');
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Add media buttons to post/page editor
     * 
     * @since 1.0.0
     */
    public function add_media_buttons() {
        // Only show on post edit screens
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('post', 'page'))) {
            return;
        }
        
        // Only if user has required capabilities
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Check if plugin is configured
        if (!w3a11y_artisan_is_configured()) {
            return;
        }
        
        // Add Generate button to media buttons
        printf(
            '<button type="button" class="button w3a11y-media-button" id="w3a11y-editor-generate-btn" title="%s">' .
            '<svg width="16px" height="16px" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="none"><g fill="#000000"><path d="M4.083.183a.5.5 0 00-.65.65l.393.981a.5.5 0 010 .371l-.393.982a.5.5 0 00.65.65l.981-.393a.5.5 0 01.372 0l.98.392a.5.5 0 00.65-.65l-.392-.98a.5.5 0 010-.372l.393-.981a.5.5 0 00-.65-.65l-.981.392a.5.5 0 01-.372 0l-.98-.392z"/><path fill-rule="evenodd" d="M11.414 4.104a2 2 0 00-2.828 0L.808 11.882a2 2 0 002.828 2.828l7.778-7.778a2 2 0 000-2.828zm-1.768 1.06a.5.5 0 01.708.707l-.884.884-.707-.707.883-.884zM7.702 7.11l.707.707-5.834 5.834a.5.5 0 11-.707-.707l5.834-5.834z" clip-rule="evenodd"/><path d="M10.572 11.21a.5.5 0 010-.92l1.22-.522a.5.5 0 00.262-.262l.522-1.22a.5.5 0 01.92 0l.521 1.22a.5.5 0 00.263.262l1.219.522a.5.5 0 010 .92l-1.219.522a.5.5 0 00-.263.263l-.522 1.218a.5.5 0 01-.919 0l-.522-1.218a.5.5 0 00-.263-.263l-1.219-.522zM12.833.183a.5.5 0 00-.65.65l.293.731a.5.5 0 010 .371l-.293.732a.5.5 0 00.65.65l.731-.293a.5.5 0 01.372 0l.73.292a.5.5 0 00.65-.65l-.292-.73a.5.5 0 010-.372l.293-.731a.5.5 0 00-.65-.65l-.731.292a.5.5 0 01-.372 0l-.73-.292z"/></g></svg>' .
            '%s</button>',
            esc_attr__('Generate images with AI', 'w3a11y-artisan'),
            esc_html__('Generate With AI', 'w3a11y-artisan')
        );
    }
    
    /**
     * Enqueue media-specific scripts
     * 
     * @param string $hook_suffix Current admin page hook.
     * @since 1.0.0
     */
    public function enqueue_media_scripts($hook_suffix) {
        // Check if we're on post.php (covers both block editor and Elementor)
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for context only, not processing form data
        $is_post_edit = ($pagenow === 'post.php' && isset($_GET['post']));
        $is_post_new = ($pagenow === 'post-new.php');
        
        // Define media-related pages
        $media_pages = array(
            'upload.php',    // Media library page (restored)
            'media.php',
            'post.php',
            'post-new.php',
            'media-upload.php'
        );
        
        // Allow if it's a media page OR if it's post editing (any action)
        if (!in_array($hook_suffix, $media_pages) && !$is_post_edit && !$is_post_new) {
            return;
        }
        
        // Only if user has required capabilities
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Check if plugin is configured
        if (!w3a11y_artisan_is_configured()) {
            return;
        }
        
        // Enqueue modal styles
        wp_enqueue_style(
            'w3a11y-artisan-modal',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/css/modal-style.css',
            array(),
            W3A11Y_ARTISAN_VERSION
        );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'w3a11y-artisan-admin',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            W3A11Y_ARTISAN_VERSION
        );
        
        // Enqueue media scripts (WordPress native)
        wp_enqueue_media();
        
        // Enqueue our modal script (pure vanilla JS - no jQuery)
        wp_enqueue_script(
            'w3a11y-artisan-modal',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/js/admin-modal.js',
            array(),
            W3A11Y_ARTISAN_VERSION,
            true
        );
        
        // Enqueue NEW media modal extensions (pure vanilla JS - no jQuery)
        wp_enqueue_script(
            'w3a11y-artisan-media-extensions',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/js/media-modal-extensions.js',
            array('media-editor', 'media-views', 'wp-i18n', 'underscore'),
            W3A11Y_ARTISAN_VERSION,
            true
        );
        
        // Localize AltText AJAX data for media modal extensions
        wp_localize_script('w3a11y-artisan-media-extensions', 'w3a11yAltTextAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('w3a11y_artisan_nonce'),
            'batch_size' => 8,
            'batch_delay' => 2000,
            'generating_text' => __('Generating...', 'w3a11y-artisan')
        ));
        
        // Keep legacy media integration for media library page and ensure modal compatibility
        wp_enqueue_script(
            'w3a11y-artisan-media',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/js/media-integration.js',
            array('media-editor'),
            W3A11Y_ARTISAN_VERSION,
            true
        );
        
        // Localize script with necessary data
        $settings = W3A11Y_Artisan::get_settings();
        wp_localize_script('w3a11y-artisan-modal', 'w3a11yArtisan', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('w3a11y_artisan_nonce'),
            'plugin_url' => W3A11Y_ARTISAN_PLUGIN_URL,
            'api_configured' => w3a11y_artisan_is_configured(),
            'api_key' => isset($settings['api_key']) ? $settings['api_key'] : '',
            'convert_url' => w3a11y_artisan_get_api_url('artisan', 'convert'),
            'texts' => array(
                'generateImage' => __('Generate with W3A11Y Artisan', 'w3a11y-artisan'),
                'editImage' => __('Edit with W3A11Y Artisan', 'w3a11y-artisan'),
                'loading' => __('Loading...', 'w3a11y-artisan'),
                'loading_image' => __('Loading image...', 'w3a11y-artisan'),
                'error' => __('An error occurred', 'w3a11y-artisan'),
                'error_generic' => __('An error occurred. Please try again.', 'w3a11y-artisan'),
                'success' => __('Success!', 'w3a11y-artisan'),
                'success_saved' => __('Image saved successfully to Media Library!', 'w3a11y-artisan'),
                'generating' => __('Generating image...', 'w3a11y-artisan'),
                'generatingImage' => __('Generating image...', 'w3a11y-artisan'),
                'editing' => __('Editing image...', 'w3a11y-artisan'),
                'savingImage' => __('Saving image...', 'w3a11y-artisan'),
                'loadingCredits' => __('Loading credits...', 'w3a11y-artisan'),
                'noCredits' => __('No credits available', 'w3a11y-artisan'),
                'buyCredits' => __('Buy More Credits', 'w3a11y-artisan'),
                'selectImage' => __('Please select an image first', 'w3a11y-artisan'),
                'invalidImage' => __('Please select a valid image file', 'w3a11y-artisan'),
                'promptRequired' => __('Please enter a description for the image you want to generate', 'w3a11y-artisan'),
                'savingToLibrary' => __('Saving to Media Library...', 'w3a11y-artisan'),
                'imageGenerated' => __('Image generated successfully!', 'w3a11y-artisan'),
                'imageSaved' => __('Image saved to Media Library!', 'w3a11y-artisan'),
                'confirm_close_processing' => __('Are you sure you want to close? Your image generation is still in progress.', 'w3a11y-artisan'),
                'confirm_revert' => __('Are you sure you want to revert to the original image? This cannot be undone.', 'w3a11y-artisan'),
            ),
            'environment' => array(
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checking URL parameters for context only, not processing form data
                'isElementor' => (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'elementor'),
                'isBlockEditor' => (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'edit'),
                'isMediaLibrary' => ($hook_suffix === 'upload.php'),
                'postId' => isset($_GET['post']) ? intval($_GET['post']) : 0,
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
            )
        ));
        
        // Debug info
        W3A11Y_Artisan::log('Enqueuing scripts on ' . $pagenow . ' with hook_suffix: ' . $hook_suffix . ' - api_configured: ' . (w3a11y_artisan_is_configured() ? 'true' : 'false'), 'debug');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for logging only, not processing form data
        if (isset($_GET['action'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for logging only, not processing form data
            W3A11Y_Artisan::log('Action parameter: ' . sanitize_text_field(wp_unslash($_GET['action'])), 'debug');
        }
    }
    
    /**
     * Add Artisan modal to admin pages
     * 
     * @since 1.0.0
     */
    public function add_artisan_modal() {
        // Check for post editing pages (covers Elementor and Block Editor)
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for context only, not processing form data
        $is_post_edit = ($pagenow === 'post.php' && isset($_GET['post']));
        $is_post_new = ($pagenow === 'post-new.php');
        
        // Get current screen
        $screen = get_current_screen();
        $relevant_pages = array(
            'upload',      // Media Library
            'media',       // Media pages
            'post',        // Edit Post (includes both block editor and Elementor)
            'page'         // Edit Page
        );
        
        // Allow if it's a relevant screen OR if we're editing a post (any action)
        $is_relevant_screen = $screen && in_array($screen->base, $relevant_pages);
        
        if (!$is_relevant_screen && !$is_post_edit && !$is_post_new) {
            return;
        }
        
        // Only if user has required capabilities
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Check if plugin is configured
        if (!w3a11y_artisan_is_configured()) {
            return;
        }
        
        // Debug info
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for logging only, not processing form data
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'none';
        W3A11Y_Artisan::log('Adding modal on ' . $pagenow . ' with action: ' . $action, 'debug');
        
        // Include the modal template
        include W3A11Y_ARTISAN_PLUGIN_DIR . 'admin/partials/artisan-modal.php';
    }
    
    /**
     * AJAX handler to get attachment data
     * 
     * @since 1.0.0
     */
    public function ajax_get_attachment_data() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated with intval() below
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID.', 'w3a11y-artisan')));
        }
        
        // Get attachment data
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array('message' => __('Attachment not found.', 'w3a11y-artisan')));
        }
        
        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(array('message' => __('Selected file is not an image.', 'w3a11y-artisan')));
        }
        
        // Get image data
        $image_url = wp_get_attachment_url($attachment_id);
        $image_meta = wp_get_attachment_metadata($attachment_id);
        
        // Convert image to base64 for API
        $image_base64 = $this->get_image_base64($image_url);
        
        if (!$image_base64) {
            wp_send_json_error(array('message' => __('Failed to process image data.', 'w3a11y-artisan')));
        }
        
        // Generate image hash for prompt history association
        $image_hash = md5($image_base64);
        
        // Debug logging
        W3A11Y_Artisan::log("Media integration - attachment_id=$attachment_id, image_hash=$image_hash, base64_length=" . strlen($image_base64), 'debug');
        
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'image_url' => $image_url,
            'image_base64' => $image_base64,
            'image_hash' => $image_hash, // Add image hash for prompt history
            'dimensions' => array(
                'width' => isset($image_meta['width']) ? $image_meta['width'] : null,
                'height' => isset($image_meta['height']) ? $image_meta['height'] : null
            ),
            'file_size' => size_format(filesize(get_attached_file($attachment_id))),
            'mime_type' => get_post_mime_type($attachment_id)
        ));
    }
    
    /**
     * Convert image URL to base64
     * 
     * @param string $image_url Image URL to convert.
     * @return string|false Base64 encoded image data or false on failure.
     * @since 1.0.0
     */
    private function get_image_base64($image_url) {
        // Get image data
        $response = wp_remote_get($image_url, array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            W3A11Y_Artisan::log('Failed to fetch image: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            W3A11Y_Artisan::log('Empty image data received', 'error');
            return false;
        }
        
        // Get mime type
        $mime_type = wp_remote_retrieve_header($response, 'content-type');
        if (empty($mime_type)) {
            // Fallback: detect from image data
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($image_data);
        }
        
        // Validate mime type
        $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
        if (!in_array($mime_type, $allowed_types)) {
            W3A11Y_Artisan::log('Unsupported image type: ' . $mime_type, 'error');
            return false;
        }
        
        // Convert to base64
        $base64 = base64_encode($image_data);
        
        // Optional: Compress if image is too large
        $max_size = W3A11Y_Artisan::get_settings('max_image_size') ?: 2048;
        if (strlen($base64) > ($max_size * 1024)) { // Convert to bytes roughly
            $compressed = $this->compress_image_base64($base64, $mime_type, $max_size);
            if ($compressed) {
                $base64 = $compressed;
            }
        }
        
        return $base64;
    }
    
    /**
     * Compress base64 image if needed
     * 
     * @param string $base64 Base64 image data.
     * @param string $mime_type Image MIME type.
     * @param int $max_dimension Maximum width/height.
     * @return string|false Compressed base64 or false on failure.
     * @since 1.0.0
     */
    private function compress_image_base64($base64, $mime_type, $max_dimension) {
        // Decode base64
        $image_data = base64_decode($base64);
        if (!$image_data) {
            return false;
        }
        
        // Create image resource
        $image = imagecreatefromstring($image_data);
        if (!$image) {
            return false;
        }
        
        // Get current dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate new dimensions
        if ($width > $max_dimension || $height > $max_dimension) {
            $ratio = min($max_dimension / $width, $max_dimension / $height);
            $new_width = round($width * $ratio);
            $new_height = round($height * $ratio);
            
            // Create new image
            $new_image = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG
            if ($mime_type === 'image/png') {
                imagealphablending($new_image, false);
                imagesavealpha($new_image, true);
            }
            
            // Resize
            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Output to buffer
            ob_start();
            switch ($mime_type) {
                case 'image/jpeg':
                    imagejpeg($new_image, null, 85);
                    break;
                case 'image/png':
                    imagepng($new_image, null, 6);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        imagewebp($new_image, null, 85);
                    } else {
                        // Fallback to JPEG
                        imagejpeg($new_image, null, 85);
                    }
                    break;
            }
            $compressed_data = ob_get_clean();
            
            // Clean up
            imagedestroy($image);
            imagedestroy($new_image);
            
            if ($compressed_data) {
                return base64_encode($compressed_data);
            }
        }
        
        // Clean up
        imagedestroy($image);
        
        return false;
    }
    
}