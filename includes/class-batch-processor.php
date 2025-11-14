<?php
/**
 * W3A11Y Batch Processor Class
 * 
 * Handles bulk image processing with proper filtering, batching, and progress management.
 * Works with the new extension API for efficient batch processing.
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Batch Processor Class
 * 
 * @since 1.1.0
 */
class W3A11Y_Batch_Processor {
    
    /**
     * Batch processor instance
     * 
     * @var W3A11Y_Batch_Processor
     * @since 1.1.0
     */
    private static $instance = null;

    /**
     * Server batch configuration
     * 
     * @var array
     * @since 1.1.0
     */
    private $server_config = null;

    /**
     * Flag to track if attached filter is being applied
     * 
     * @var bool
     * @since 1.1.0
     */
    private $applying_attached_filter = false;

    /**
     * Get batch processor instance (Singleton pattern)
     * 
     * @return W3A11Y_Batch_Processor
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
        // Initialize hooks if needed
    }

    /**
     * Get server batch configuration from API
     * 
     * @return array Server configuration
     * @since 1.1.0
     */
    public function get_server_config() {
        if ($this->server_config !== null) {
            return $this->server_config;
        }

        try {
            $api_key = $this->get_api_key();
            if (empty($api_key)) {
                throw new Exception('API key not configured');
            }

            $config = w3a11y_get_api_config();
            $endpoint = w3a11y_artisan_get_api_url('alttext', 'config');

            $response = wp_remote_get($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'User-Agent' => $config['user_agent']
                ),
                'timeout' => 15
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['success']) && $data['success'] && isset($data['config'])) {
                    $this->server_config = $data['config'];
                    return $this->server_config;
                }
            }

        } catch (Exception $e) {
            W3A11Y_Artisan::log('Failed to get server config: ' . $e->getMessage(), 'error');
        }

        return $this->server_config;
    }

    /**
     * Get filtered images for bulk processing based on options
     * 
     * @param array $processing_options Processing options from form
     * @return array Array of filtered image data
     * @since 1.1.0
     */
    public function get_filtered_images($processing_options) {
        
        $query_args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        // Apply only_attached filter
        if (!empty($processing_options['only_attached'])) {
            // Only include images that have a parent post (attached to posts/pages)
            // Use posts_where filter to add SQL condition for post_parent > 0
            add_filter('posts_where', array($this, 'filter_attached_only'), 10, 2);
            $this->applying_attached_filter = true;
        }

        // Apply overwrite_existing filter (default: only missing alt text)
        if (empty($processing_options['overwrite_existing'])) {
            if (!isset($query_args['meta_query'])) {
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for bulk alt text generation to filter images without alt text in user-initiated operations
                $query_args['meta_query'] = array();
            }
            $query_args['meta_query']['relation'] = 'AND';
            $query_args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            );
        }



        $attachment_ids = get_posts($query_args);
        
        // Remove filter if it was applied
        if (!empty($this->applying_attached_filter)) {
            remove_filter('posts_where', array($this, 'filter_attached_only'), 10);
            $this->applying_attached_filter = false;
        }
        
        $images = array();

        foreach ($attachment_ids as $attachment_id) {
            $image_url = wp_get_attachment_url($attachment_id);
            if (!$image_url) {
                continue;
            }

            // Get image context
            $attachment = get_post($attachment_id);
            $context = $this->get_image_context($attachment);
            $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            $images[] = array(
                'attachmentId' => $attachment_id,
                'imageUrl' => $image_url,
                'context' => $context,
                'title' => $attachment->post_title,
                'alt' => $current_alt
            );
        }

        return $images;
    }

    /**
     * Filter to only include attached images (post_parent > 0)
     * 
     * @param string $where SQL WHERE clause
     * @param WP_Query $query The query object
     * @return string Modified WHERE clause
     * @since 1.1.0
     */
    public function filter_attached_only($where, $query) {
        global $wpdb;
        
        if (!empty($this->applying_attached_filter)) {
            $where .= " AND {$wpdb->posts}.post_parent > 0";
        }
        
        return $where;
    }

    /**
     * Process batch of images using extension API
     * 
     * @param array $images Array of image data
     * @param array $processing_options Processing options
     * @param array $alttext_settings Alt text generation settings
     * @return array Processing results
     * @since 1.1.0
     */
    public function process_batch($images, $processing_options, $alttext_settings) {
        $results = array(
            'success' => false,
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => array(),
            'error' => null,
            'credits_used' => 0,
            'remaining_credits' => 0
        );

        if (empty($images)) {
            $results['success'] = true;
            return $results;
        }

        try {
            $api_key = $this->get_api_key();
            if (empty($api_key)) {
                throw new Exception(__('API key not configured.', 'w3a11y-artisan'));
            }

            $config = w3a11y_get_api_config();
            $endpoint = w3a11y_artisan_get_api_url('alttext', 'generate');

            // Prepare enhancement options from alttext settings
            $enhancement_options = array(
                'language' => $alttext_settings['alttext_language'] ?? 'en',
                'maxLength' => intval($alttext_settings['alttext_max_length'] ?? 125),
                'style' => $alttext_settings['alttext_style'] ?? 'detailed',
                'customInstructions' => $alttext_settings['alttext_custom_instructions'] ?? ''
            );

            // Prepare batch request
            $body = array(
                'images' => $images,
                'enhancementOptions' => $enhancement_options,
                'processingOptions' => array(
                    'skip_processed' => !empty($processing_options['skip_processed']),
                    'overwrite_existing' => !empty($processing_options['overwrite_existing']),
                    'only_attached' => !empty($processing_options['only_attached'])
                ),
                'domain' => wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost'
            );

            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'User-Agent' => $config['user_agent']
                ),
                'body' => wp_json_encode($body),
                'timeout' => 120 // Longer timeout for batch processing
            ));

            if (is_wp_error($response)) {
                throw new Exception('Network error: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);

            // Handle 402 insufficient credits error
            if ($status_code === 402) {
                $results['error'] = array(
                    'type' => 'insufficient_credits',
                    'message' => $data['error'] ?? __('Insufficient credits for batch processing.', 'w3a11y-artisan'),
                    'credits_required' => $data['creditsRequired'] ?? 0,
                    'credits_available' => $data['creditsAvailable'] ?? 0,
                    'status_code' => 402
                );
                return $results;
            }

            if ($status_code !== 200) {
                throw new Exception('API error: ' . ($data['error'] ?? 'Unknown error'));
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new Exception($data['error'] ?? 'API request failed');
            }

            // Process successful results
            $batch_results = $data['results'] ?? array();
            
            foreach ($batch_results as $result) {
                $attachment_id = $result['attachmentId'] ?? 0;
                
                if ($result['success'] && !empty($result['altText']) && $attachment_id > 0) {
                    // Update WordPress attachment alt text
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($result['altText']));
                    
                    if (!empty($result['skipped'])) {
                        $results['skipped']++;
                    } else {
                        $results['processed']++;
                    }
                    
                    $results['details'][] = array(
                        'id' => $attachment_id,
                        'status' => 'success',
                        'alt_text' => $result['altText'],
                        'skipped' => !empty($result['skipped'])
                    );
                } else {
                    $results['failed']++;
                    $results['details'][] = array(
                        'id' => $attachment_id,
                        'status' => 'failed',
                        'error' => $result['error'] ?? 'Unknown error'
                    );
                }
            }

            $results['success'] = true;
            $results['credits_used'] = $data['creditsUsed'] ?? 0;
            $results['remaining_credits'] = $data['remainingCredits'] ?? 0;

        } catch (Exception $e) {
            $results['error'] = array(
                'type' => 'processing_error',
                'message' => $e->getMessage(),
                'status_code' => 500
            );
            W3A11Y_Artisan::log('Batch processing error: ' . $e->getMessage(), 'error');
        }

        return $results;
    }

    /**
     * Start bulk processing with session management
     * 
     * @param array $processing_options Processing options from form
     * @return array Session data or error
     * @since 1.1.0
     */
    public function start_bulk_processing($processing_options) {
        try {
            // Get filtered images based on processing options
            $images = $this->get_filtered_images($processing_options);

            if (empty($images)) {
                return array(
                    'success' => false,
                    'message' => __('No images found matching the selected criteria.', 'w3a11y-artisan')
                );
            }

            // Get server configuration for batching
            $server_config = $this->get_server_config();
            $batch_size = $server_config['batchSize'] ?? 8;

            // Create session
            $session_id = wp_generate_uuid4();
            $session_data = array(
                'images' => $images,
                'processing_options' => $processing_options,
                'alttext_settings' => array(
                    'alttext_custom_instructions' => $processing_options['custom_instructions'] ?? '',
                    'alttext_language' => $processing_options['language'] ?? 'en',
                    'alttext_max_length' => intval($processing_options['max_length'] ?? 125),
                    'alttext_style' => 'detailed'
                ),
                'server_config' => $server_config,
                'total_images' => count($images),
                'processed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'current_batch' => 0,
                'total_batches' => ceil(count($images) / $batch_size),
                'started_at' => current_time('timestamp'),
                'status' => 'running',
                'completed_batches' => array(),
                'last_error' => null
            );

            set_transient('w3a11y_bulk_session_' . $session_id, $session_data, DAY_IN_SECONDS);

            return array(
                'success' => true,
                'session_id' => $session_id,
                'total_images' => count($images),
                'batch_size' => $batch_size,
                'total_batches' => $session_data['total_batches']
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Process next batch in bulk processing session
     * 
     * @param string $session_id Session ID
     * @return array Processing results
     * @since 1.1.0
     */
    public function process_next_batch($session_id) {
        $session_data = get_transient('w3a11y_bulk_session_' . $session_id);

        if (!$session_data || $session_data['status'] !== 'running') {
            return array(
                'success' => false,
                'message' => __('Invalid or expired session.', 'w3a11y-artisan')
            );
        }

        $server_config = $session_data['server_config'];
        $batch_size = $server_config['batchSize'] ?? 8;
        $batch_start = $session_data['current_batch'] * $batch_size;
        $batch_images = array_slice($session_data['images'], $batch_start, $batch_size);

        if (empty($batch_images)) {
            // Processing complete
            $session_data['status'] = 'completed';
            $session_data['completed_at'] = current_time('timestamp');
            set_transient('w3a11y_bulk_session_' . $session_id, $session_data, DAY_IN_SECONDS);

            return array(
                'success' => true,
                'status' => 'completed',
                'processed' => $session_data['processed'],
                'failed' => $session_data['failed'],
                'skipped' => $session_data['skipped'],
                'total' => $session_data['total_images']
            );
        }

        // Process current batch
        $batch_results = $this->process_batch(
            $batch_images, 
            $session_data['processing_options'], 
            $session_data['alttext_settings']
        );

        // Handle 402 error (insufficient credits)
        if (!$batch_results['success'] && 
            isset($batch_results['error']['type']) && 
            $batch_results['error']['type'] === 'insufficient_credits') {
            
            // Save session with error for potential resume
            $session_data['status'] = 'paused_credits';
            $session_data['last_error'] = $batch_results['error'];
            $session_data['paused_at'] = current_time('timestamp');
            set_transient('w3a11y_bulk_session_' . $session_id, $session_data, WEEK_IN_SECONDS);

            return array(
                'success' => false,
                'status' => 'insufficient_credits',
                'error' => $batch_results['error'],
                'processed' => $session_data['processed'],
                'failed' => $session_data['failed'],
                'skipped' => $session_data['skipped'],
                'total' => $session_data['total_images'],
                'can_resume' => true,
                'session_id' => $session_id
            );
        }

        // Update session data
        if ($batch_results['success']) {
            $session_data['processed'] += $batch_results['processed'];
            $session_data['failed'] += $batch_results['failed'];
            $session_data['skipped'] += $batch_results['skipped'];
            $session_data['current_batch']++;
            
            // Save completed batch info
            $session_data['completed_batches'][] = array(
                'batch_number' => $session_data['current_batch'],
                'processed' => $batch_results['processed'],
                'failed' => $batch_results['failed'],
                'skipped' => $batch_results['skipped'],
                'completed_at' => current_time('timestamp')
            );
        } else {
            // Handle other errors
            $session_data['failed'] += count($batch_images);
            $session_data['last_error'] = $batch_results['error'];
        }

        set_transient('w3a11y_bulk_session_' . $session_id, $session_data, DAY_IN_SECONDS);

        $progress_percentage = round(
            (($session_data['processed'] + $session_data['failed'] + $session_data['skipped']) / $session_data['total_images']) * 100, 
            1
        );

        return array(
            'success' => $batch_results['success'],
            'status' => 'processing',
            'processed' => $session_data['processed'],
            'failed' => $session_data['failed'],
            'skipped' => $session_data['skipped'],
            'total' => $session_data['total_images'],
            'current_batch' => $session_data['current_batch'],
            'total_batches' => $session_data['total_batches'],
            'progress_percentage' => $progress_percentage,
            'credits_used' => $batch_results['credits_used'] ?? 0,
            'remaining_credits' => $batch_results['remaining_credits'] ?? 0,
            'batch_results' => $batch_results['details'] ?? array(),
            'error' => $batch_results['error'] ?? null
        );
    }

    /**
     * Cancel bulk processing session
     * 
     * @param string $session_id Session ID
     * @return array Results
     * @since 1.1.0
     */
    public function cancel_bulk_processing($session_id) {
        $session_data = get_transient('w3a11y_bulk_session_' . $session_id);

        if ($session_data) {
            // If there's progress, mark as cancelled but resumable, otherwise just cancelled
            if (isset($session_data['processed']) && $session_data['processed'] > 0) {
                $session_data['status'] = 'cancelled_resumable';
            } else {
                $session_data['status'] = 'cancelled';
            }
            $session_data['cancelled_at'] = current_time('timestamp');
            // Extend transient time for resumable sessions
            $transient_time = ($session_data['status'] === 'cancelled_resumable') ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
            set_transient('w3a11y_bulk_session_' . $session_id, $session_data, $transient_time);
        }

        return array(
            'success' => true,
            'message' => __('Bulk processing cancelled. Progress has been saved.', 'w3a11y-artisan'),
            'session_data' => $session_data
        );
    }

    /**
     * Resume paused bulk processing session
     * 
     * @param string $session_id Session ID
     * @return array Results
     * @since 1.1.0
     */
    public function resume_bulk_processing($session_id) {
        $session_data = get_transient('w3a11y_bulk_session_' . $session_id);

        if (!$session_data || !in_array($session_data['status'], ['paused_credits', 'cancelled_resumable'])) {
            return array(
                'success' => false,
                'message' => __('No paused session found to resume.', 'w3a11y-artisan')
            );
        }

        // Reset session to running
        $session_data['status'] = 'running';
        $session_data['resumed_at'] = current_time('timestamp');
        $session_data['last_error'] = null;
        set_transient('w3a11y_bulk_session_' . $session_id, $session_data, DAY_IN_SECONDS);

        return array(
            'success' => true,
            'message' => __('Bulk processing resumed.', 'w3a11y-artisan'),
            'session_id' => $session_id,
            'processed' => $session_data['processed'],
            'total' => $session_data['total_images']
        );
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
     * Get API key
     * 
     * @return string API key
     * @since 1.1.0
     */
    private function get_api_key() {
        $settings = get_option('w3a11y_artisan_settings', array());
        return $settings['api_key'] ?? '';
    }
}