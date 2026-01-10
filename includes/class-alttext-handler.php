<?php
/**
 * W3A11Y AltText API Handler Class
 * 
 * Handles all API communications with W3A11Y AltText services
 * including generation, bulk processing, and settings management.
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y AltText API Handler Class
 * 
 * @since 1.1.0
 */
class W3A11Y_AltText_Handler {
    
    /**
     * AltText handler instance
     * 
     * @var W3A11Y_AltText_Handler
     * @since 1.1.0
     */
    private static $instance = null;

    /**
     * Batch processing configuration
     * 
     * @var array
     * @since 1.1.0
     */
    public $batch_config = array(
        'batch_size' => 8,
        'batch_delay_ms' => 2000,
        'batch_timeout_ms' => 60000, // Increased from 30s to 60s for alt text generation
        'max_retries' => 5
    );

    /**
     * Get AltText handler instance (Singleton pattern)
     * 
     * @return W3A11Y_AltText_Handler
     * @since 1.1.0
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
     * @since 1.1.0
     */
    private function __construct() {
        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     * 
     * @since 1.1.0
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_w3a11y_generate_alttext', array($this, 'handle_generate_alttext'));
        add_action('wp_ajax_w3a11y_bulk_alttext', array($this, 'handle_bulk_alttext'));
        add_action('wp_ajax_w3a11y_get_bulk_stats', array($this, 'handle_get_bulk_stats'));
        add_action('wp_ajax_w3a11y_get_credits_info', array($this, 'handle_get_credits_info'));
        add_action('wp_ajax_w3a11y_get_session_status', array($this, 'handle_get_session_status'));
        add_action('wp_ajax_w3a11y_resume_bulk_processing', array($this, 'handle_resume_bulk_processing'));
        
        // Auto-generate alt text on image upload
        add_action('add_attachment', array($this, 'auto_generate_alttext_on_upload'));
    }

    /**
     * Handle single alt text generation request
     * 
     * @since 1.1.0
     */
    public function handle_generate_alttext() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }

        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }

        // Validate and sanitize input
        $image_url = isset($_POST['image_url']) ? sanitize_url(wp_unslash($_POST['image_url'])) : '';
        $context = isset($_POST['context']) ? sanitize_textarea_field(wp_unslash($_POST['context'])) : '';
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (empty($image_url)) {
            wp_send_json_error(array('message' => __('Image URL is required.', 'w3a11y-artisan')));
        }

        try {
            // Get user settings
            $settings = $this->get_alttext_settings();
            
            // Prepare API request
            $api_response = $this->call_alttext_api($image_url, $context, $settings);

            if ($api_response && isset($api_response['success']) && $api_response['success']) {
                // Debug log the API response
                W3A11Y_Artisan::log('API Response received: ' . wp_json_encode($api_response), 'debug');
                
                // Check if altText exists in the response (server returns 'altText' at root level)
                if (isset($api_response['altText']) && !empty($api_response['altText'])) {
                    $alt_text = sanitize_text_field($api_response['altText']);
                    
                    // Update attachment alt text if attachment ID provided
                    if ($attachment_id > 0) {
                        $update_result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                        W3A11Y_Artisan::log('Alt text update result for attachment ' . $attachment_id . ': ' . ($update_result ? 'SUCCESS' : 'FAILED'), 'debug');
                    }

                    wp_send_json_success(array(
                        'alt_text' => $alt_text,
                        'confidence' => $api_response['confidence'] ?? 0.8,
                        'credits_used' => $api_response['creditsUsed'] ?? 1,
                        'available_credits' => $api_response['creditsRemaining'] ?? 0
                    ));
                } else {
                    // API succeeded but no alt_text in response
                    W3A11Y_Artisan::log('API returned success but no alt_text field. Response: ' . wp_json_encode($api_response), 'error');
                    wp_send_json_error(array('message' => __('Alt text generation succeeded but no text was returned.', 'w3a11y-artisan')));
                }

            } else {
                $error_message = isset($api_response['error']) ? $api_response['error'] : __('Failed to generate alt text.', 'w3a11y-artisan');
                W3A11Y_Artisan::log('API call failed: ' . $error_message, 'error');
                wp_send_json_error(array('message' => $error_message));
            }

        } catch (Exception $e) {
            W3A11Y_Artisan::log('AltText Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => __('An error occurred while generating alt text.', 'w3a11y-artisan')));
        }
    }

    /**
     * Handle bulk alt text processing request
     * 
     * @since 1.1.0
     */
    public function handle_bulk_alttext() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : 'start';

        switch ($action) {
            case 'start':
                $this->start_bulk_processing();
                break;
            case 'process_batch':
                $this->process_bulk_batch();
                break;
            case 'cancel':
                $this->cancel_bulk_processing();
                break;
            case 'resume':
                $this->resume_bulk_processing();
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid bulk action.', 'w3a11y-artisan')));
        }
    }

    /**
     * Start bulk processing using new batch processor
     * 
     * @since 1.1.0
     */
    private function start_bulk_processing() {
        // Get processing options from form with proper boolean handling
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_bulk_alttext_ajax() before calling this method
        $processing_options = array(
            'custom_instructions' => isset($_POST['custom_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['custom_instructions'])) : '',
            'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en',
            'max_length' => isset($_POST['max_length']) ? intval($_POST['max_length']) : 125,
            'overwrite_existing' => isset($_POST['overwrite_existing']) ? filter_var(wp_unslash($_POST['overwrite_existing']), FILTER_VALIDATE_BOOLEAN) : false,
            'only_attached' => isset($_POST['only_attached']) ? filter_var(wp_unslash($_POST['only_attached']), FILTER_VALIDATE_BOOLEAN) : false,
            'skip_processed' => isset($_POST['skip_processed']) ? filter_var(wp_unslash($_POST['skip_processed']), FILTER_VALIDATE_BOOLEAN) : true
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Use new batch processor
        $batch_processor = W3A11Y_Batch_Processor::get_instance();
        $result = $batch_processor->start_bulk_processing($processing_options);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Process next batch using new batch processor
     * 
     * @since 1.1.0
     */
    private function process_bulk_batch() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_bulk_alttext_ajax() before calling this method
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        // Use new batch processor
        $batch_processor = W3A11Y_Batch_Processor::get_instance();
        $result = $batch_processor->process_next_batch($session_id);

        // Handle insufficient credits error specially
        if (!$result['success'] && isset($result['status']) && $result['status'] === 'insufficient_credits') {
            // Return error with special 402 status for frontend handling
            wp_send_json_error(array(
                'message' => $result['error']['message'],
                'status_code' => 402,
                'credits_required' => $result['error']['credits_required'] ?? 0,
                'credits_available' => $result['error']['credits_available'] ?? 0,
                'processed' => $result['processed'],
                'total' => $result['total'],
                'can_resume' => $result['can_resume'] ?? false,
                'session_id' => $result['session_id'] ?? $session_id
            ), 402);
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => $result['error']['message'] ?? __('Processing failed.', 'w3a11y-artisan'),
                'processed' => $result['processed'] ?? 0,
                'total' => $result['total'] ?? 0
            ));
        }
    }

    /**
     * Process a batch of images with retry logic
     * 
     * @param array $images Array of image data
     * @param array $alttext_settings Override settings for bulk processing
     * @return array Processing results
     * @since 1.1.0
     */
    private function process_image_batch($images, $alttext_settings = array()) {
        $processed = 0;
        $failed = 0;
        $details = array();
        $settings = !empty($alttext_settings) ? $alttext_settings : $this->get_alttext_settings();

        foreach ($images as $image) {
            $retries = 0;
            $success = false;
            $last_error = '';

            while ($retries <= $this->batch_config['max_retries'] && !$success) {
                try {
                    $api_response = $this->call_alttext_api($image['url'], $image['context'], $settings);

                    if ($api_response && isset($api_response['success']) && $api_response['success']) {
                        // Check if altText exists in the response (server returns 'altText' at root level)
                        if (isset($api_response['altText']) && !empty($api_response['altText'])) {
                            $alt_text = sanitize_text_field($api_response['altText']);
                            
                            // Update attachment alt text
                            update_post_meta($image['attachment_id'], '_wp_attachment_image_alt', $alt_text);
                            
                            $processed++;
                            $success = true;
                            $details[] = array(
                                'id' => $image['attachment_id'],
                                'status' => 'success',
                                'alt_text' => $alt_text
                            );
                        } else {
                            $last_error = 'Alt text not found in API response';
                            $retries++;
                        }

                    } else {
                        $last_error = isset($api_response['error']) ? $api_response['error'] : 'Unknown API error';
                        $retries++;
                        
                        if ($retries <= $this->batch_config['max_retries']) {
                            // Wait before retry
                            usleep($this->batch_config['batch_delay_ms'] * 1000);
                        }
                    }

                } catch (Exception $e) {
                    $last_error = $e->getMessage();
                    $retries++;
                    
                    if ($retries <= $this->batch_config['max_retries']) {
                        usleep($this->batch_config['batch_delay_ms'] * 1000);
                    }
                }
            }

            if (!$success) {
                $failed++;
                $details[] = array(
                    'id' => $image['attachment_id'],
                    'status' => 'failed',
                    'error' => $last_error,
                    'retries' => $retries - 1
                );
            }
        }

        return array(
            'processed' => $processed,
            'failed' => $failed,
            'details' => $details
        );
    }

    /**
     * Cancel bulk processing using batch processor
     * 
     * @since 1.1.0
     */
    private function cancel_bulk_processing() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_bulk_alttext_ajax() before calling this method
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        // Use new batch processor
        $batch_processor = W3A11Y_Batch_Processor::get_instance();
        $result = $batch_processor->cancel_bulk_processing($session_id);

        wp_send_json_success($result);
    }

    /**
     * Resume paused bulk processing
     * 
     * @since 1.1.0
     */
    private function resume_bulk_processing() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_bulk_alttext_ajax() before calling this method
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        // Use new batch processor
        $batch_processor = W3A11Y_Batch_Processor::get_instance();
        $result = $batch_processor->resume_bulk_processing($session_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle resume bulk processing AJAX request
     * 
     * @since 1.1.0
     */
    public function handle_resume_bulk_processing() {
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $this->resume_bulk_processing();
    }

    /**
     * Handle get session status AJAX request
     * 
     * @since 1.1.0
     */
    public function handle_get_session_status() {
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => 'Session ID required'));
        }

        // Get session data from transient
        $session_data = get_transient('w3a11y_bulk_session_' . $session_id);
        
        if (!$session_data) {
            wp_send_json_error(array('message' => 'Session not found or expired'));
        }

        wp_send_json_success(array('session' => $session_data));
    }

    /**
     * Handle bulk stats request
     * 
     * @since 1.1.0
     */
    public function handle_get_bulk_stats() {
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }

        // Get processing options from the new form structure
        $processing_options = array(
            'keywords' => isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '',
            'negative_keywords' => isset($_POST['negative_keywords']) ? sanitize_text_field(wp_unslash($_POST['negative_keywords'])) : '',
            'custom_instructions' => isset($_POST['custom_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['custom_instructions'])) : '',
            'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en',
            'max_length' => isset($_POST['max_length']) ? intval(wp_unslash($_POST['max_length'])) : 125,
            'overwrite_existing' => isset($_POST['overwrite_existing']) ? filter_var(wp_unslash($_POST['overwrite_existing']), FILTER_VALIDATE_BOOLEAN) : false,
            'only_attached' => isset($_POST['only_attached']) ? filter_var(wp_unslash($_POST['only_attached']), FILTER_VALIDATE_BOOLEAN) : false,
            'skip_processed' => isset($_POST['skip_processed']) ? filter_var(wp_unslash($_POST['skip_processed']), FILTER_VALIDATE_BOOLEAN) : true
        );

        $stats = $this->get_image_statistics($processing_options);
        
        // Also get user credits
        $credits = $this->get_user_credits();
        $stats['available_credits'] = $credits['available'] ?? 0;

        wp_send_json_success($stats);
    }

    /**
     * Handle credits info request
     * 
     * @since 1.1.0
     */
    public function handle_get_credits_info() {
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }

        $credits = $this->get_user_credits();
        wp_send_json_success($credits);
    }

    /**
     * Call W3A11Y AltText API
     * 
     * @param string $image_url Image URL
     * @param string $context Context text
     * @param array $settings User settings
     * @return array API response
     * @since 1.1.0
     */
    private function call_alttext_api($image_url, $context = '', $settings = array()) {
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            throw new Exception(esc_html(__('API key not configured.', 'w3a11y-artisan')));
        }

        // Use centralized API configuration
        $config = w3a11y_get_api_config();
        $endpoint = w3a11y_artisan_get_api_url('alttext', 'generate');
        
        $body = array(
            'imageUrl' => $image_url,
            'context' => $context,
            'domain' => wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost', // Required for hash generation
            'enhancementOptions' => array(
                'style' => $settings['alttext_style'] ?? 'detailed',
                'language' => $settings['alttext_language'] ?? 'auto-detect',
                'maxLength' => intval($settings['alttext_max_length'] ?? 125),
                'customInstructions' => $settings['alttext_custom_instructions'] ?? ''
            )
        );

        $args = array(
            'method' => 'POST',
            'timeout' => intval($this->batch_config['batch_timeout_ms'] / 1000),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => $config['user_agent']
            ),
            'body' => wp_json_encode($body)
        );

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception(esc_html($response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? $error_data['error'] : 'API request failed';
            
            // Handle specific status codes
            if ($status_code === 402) {
                $error_message = __('Insufficient credits. Please purchase more credits to continue.', 'w3a11y-artisan');
                
                // Add notification for insufficient credits
                if (class_exists('W3A11Y_Notification_Manager')) {
                    $notification_manager = W3A11Y_Notification_Manager::get_instance();
                    $notification_manager->add_insufficient_credits_notice(0, 'both');
                }
            } elseif ($status_code === 401) {
                $error_message = __('Invalid API key. Please check your settings.', 'w3a11y-artisan');
            } elseif ($status_code === 429) {
                $error_message = __('Rate limit exceeded. Please try again later.', 'w3a11y-artisan');
            }
            
            // Add general API error notification
            if (class_exists('W3A11Y_Notification_Manager')) {
                $notification_manager = W3A11Y_Notification_Manager::get_instance();
                $notification_manager->add_api_error_notice($error_message, $status_code, $endpoint, 'both');
            }
            
            throw new Exception(esc_html($error_message));
        }

        return json_decode($body, true);
    }



    /**
     * Get image statistics
     * 
     * @param array $options Processing options
     * @return array Statistics
     * @since 1.1.0
     */
    public function get_image_statistics($options) {
        global $wpdb;
        
        // Create cache key based on options
        $cache_key = 'w3a11y_image_stats_' . md5(serialize($options));
        $cached_stats = wp_cache_get($cache_key, 'w3a11y_artisan');
        
        if (false !== $cached_stats) {
            return $cached_stats;
        }
        
        // Apply only_attached filter if requested
        if (!empty($options['only_attached'])) {
            // Get total images count with parent filter
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query with caching implemented
            $total_images = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_mime_type LIKE 'image/%' 
                AND post_status = 'inherit'
                AND post_parent > 0
            ");
            
            // Get missing alt text count with parent filter
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query with caching implemented
            $missing_alt_images = $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image/%' 
                AND p.post_status = 'inherit'
                AND p.post_parent > 0
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ");
        } else {
            // Get total images count without parent filter
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query with caching implemented
            $total_images = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_mime_type LIKE 'image/%' 
                AND post_status = 'inherit'
            ");
            
            // Get missing alt text count without parent filter
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query with caching implemented
            $missing_alt_images = $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image/%' 
                AND p.post_status = 'inherit'
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ");
        }
        
        $with_alt_images = $total_images - $missing_alt_images;

        $stats = array(
            'total_images' => intval($total_images),
            'missing_alt_text' => intval($missing_alt_images),
            'with_alt_text' => intval($with_alt_images),
            'missing_percentage' => $total_images > 0 ? round(($missing_alt_images / $total_images) * 100, 1) : 0
        );
        
        // Cache for 5 minutes
        wp_cache_set($cache_key, $stats, 'w3a11y_artisan', 300);
        
        return $stats;
    }

    /**
     * Get image context for alt text generation
     * 
     * @param WP_Post $attachment Attachment post object
     * @return string Context text
     * @since 1.1.0
     */
    private function get_image_context($attachment) {
        $context_parts = array();

        // Add title
        if (!empty($attachment->post_title)) {
            $context_parts[] = $attachment->post_title;
        }

        // Add caption
        if (!empty($attachment->post_excerpt)) {
            $context_parts[] = $attachment->post_excerpt;
        }

        // Add description
        if (!empty($attachment->post_content)) {
            $context_parts[] = $attachment->post_content;
        }

        return implode('. ', $context_parts);
    }

    /**
     * Get user credits information
     * 
     * @return array Credits information
     * @since 1.1.0
     */
    private function get_user_credits() {
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return array('available' => 0, 'used' => 0);
        }

        try {
            // Use centralized API configuration
            $config = w3a11y_get_api_config();
            $endpoint = w3a11y_artisan_get_api_url('alttext', 'credits');
            
            $args = array(
                'method' => 'GET',
                'timeout' => 10,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'User-Agent' => $config['user_agent']
                )
            );

            $response = wp_remote_request($endpoint, $args);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                return array(
                    'available' => $data['available_credits'] ?? 0,  // Server returns 'available_credits'
                    'used' => $data['used_credits'] ?? 0,           // Match server field names
                    'total' => $data['total_credits'] ?? ($data['available_credits'] ?? 0)  // Fallback to available if total not provided
                );
            }

        } catch (Exception $e) {
            W3A11Y_Artisan::log('Credits check error: ' . $e->getMessage(), 'error');
        }

        return array('available' => 0, 'used' => 0, 'total' => 0);
    }

    /**
     * Get AltText settings
     * 
     * @return array Settings array
     * @since 1.1.0
     */
    private function get_alttext_settings() {
        $settings = get_option('w3a11y_artisan_settings', array());
        
        return array(
            'alttext_custom_instructions' => $settings['alttext_custom_instructions'] ?? '',
            'alttext_language' => $settings['alttext_language'] ?? 'en',
            'alttext_max_length' => intval($settings['alttext_max_length'] ?? 125),
            'alttext_style' => $settings['alttext_style'] ?? 'detailed'
        );
    }

    /**
     * Get API key
     * 
     * @return string API key
     * @since 1.1.0
     */
    private function get_api_key() {
        $settings = get_option('w3a11y_artisan_settings', array());
        return $settings['api_key'] ?? '';
    }

    /**
     * Auto-generate alt text when new images are uploaded
     * 
     * @param int $attachment_id Attachment ID of uploaded image.
     * @since 1.1.0
     */
    public function auto_generate_alttext_on_upload($attachment_id) {
        // Check if auto-generation is enabled
        $settings = get_option('w3a11y_artisan_settings', array());
        if (empty($settings['auto_generate_alttext'])) {
            return;
        }
        
        // Check if API key is configured
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return;
        }
        
        // Check if the attachment is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        // Check if alt text already exists
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            // Skip if alt text already exists
            return;
        }
        
        // Schedule alt text generation to run after upload is complete
        // Using wp_schedule_single_event to avoid blocking the upload process
        wp_schedule_single_event(time() + 5, 'w3a11y_scheduled_alttext_generation', array($attachment_id));
        
        // Also try to generate immediately if scheduling fails
        if (!wp_next_scheduled('w3a11y_scheduled_alttext_generation', array($attachment_id))) {
            $this->generate_alttext_for_attachment($attachment_id);
        }
    }

    /**
     * Generate alt text for a specific attachment
     * 
     * @param int $attachment_id Attachment ID.
     * @return bool Success status.
     * @since 1.1.0
     */
    private function generate_alttext_for_attachment($attachment_id) {
        try {
            // Get image URL
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                return false;
            }
            
            // Get settings
            $alttext_settings = $this->get_alttext_settings();
            $api_key = $this->get_api_key();
            
            // Prepare API request
            $config = w3a11y_get_api_config();
            $endpoint = w3a11y_artisan_get_api_url('alttext', 'generate');
            
            $body = array(
                'imageUrl' => $image_url,
                'customInstructions' => $alttext_settings['alttext_custom_instructions'],
                'language' => $alttext_settings['alttext_language'],
                'maxLength' => $alttext_settings['alttext_max_length'],
                'style' => $alttext_settings['alttext_style']
            );
            
            $args = array(
                'method' => 'POST',
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'User-Agent' => $config['user_agent']
                ),
                'body' => wp_json_encode($body)
            );
            
            $response = wp_remote_request($endpoint, $args);
            
            if (is_wp_error($response)) {
                W3A11Y_Artisan::log('Auto alt text generation error: ' . $response->get_error_message(), 'error');
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            
            if ($response_code === 200 && isset($data['altText'])) {
                // Update alt text
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['altText']));
                W3A11Y_Artisan::log('Auto-generated alt text for attachment ' . $attachment_id, 'info');
                return true;
            } else {
                W3A11Y_Artisan::log('Auto alt text generation failed for attachment ' . $attachment_id . ': ' . $response_body, 'error');
                return false;
            }
            
        } catch (Exception $e) {
            W3A11Y_Artisan::log('Auto alt text generation exception: ' . $e->getMessage(), 'error');
            return false;
        }
    }
}

// Hook for scheduled alt text generation
add_action('w3a11y_scheduled_alttext_generation', function($attachment_id) {
    $handler = W3A11Y_AltText_Handler::get_instance();
    // Use reflection to call private method
    $method = new ReflectionMethod($handler, 'generate_alttext_for_attachment');
    $method->setAccessible(true);
    $method->invoke($handler, $attachment_id);
});
