<?php
/**
 * W3A11Y Artisan API Handler Class
 * 
 * Handles all API communications with W3A11Y Artisan services
 * including generation, editing, inspiration, and credits.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Artisan API Handler Class
 * 
 * @since 1.0.0
 */
class W3A11Y_Artisan_API_Handler {
    
    /**
     * API handler instance
     * 
     * @var W3A11Y_Artisan_API_Handler
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Get API handler instance (Singleton pattern)
     * 
     * @return W3A11Y_Artisan_API_Handler
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Sanitize and validate base64 image data
     * 
     * @param string $base64_data The base64 encoded image data.
     * @return string|false Sanitized base64 data or false if invalid.
     * @since 1.1.0
     */
    private static function sanitize_base64_image($base64_data) {
        if (empty($base64_data)) {
            return '';
        }
        
        // Remove any whitespace
        $base64_data = preg_replace('/\s+/', '', $base64_data);
        
        // Handle data URI format (data:image/png;base64,...)
        if (strpos($base64_data, 'data:image/') === 0) {
            $parts = explode(',', $base64_data, 2);
            if (count($parts) === 2) {
                // Validate the data URI header
                if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64$/', $parts[0])) {
                    return false;
                }
                $base64_data = $parts[1];
            }
        }
        
        // Validate base64 characters (A-Z, a-z, 0-9, +, /, =)
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $base64_data)) {
            return false;
        }
        
        // Validate that it decodes properly
        $decoded = base64_decode($base64_data, true);
        if ($decoded === false) {
            return false;
        }
        
        // Validate minimum size (at least some bytes for a valid image)
        if (strlen($decoded) < 100) {
            return false;
        }
        
        return $base64_data;
    }
    
    /**
     * Sanitize selection area data
     * 
     * @param mixed $selection_area The selection area data (JSON string or array).
     * @return array|null Sanitized selection area array or null if invalid.
     * @since 1.1.0
     */
    private static function sanitize_selection_area($selection_area) {
        if (empty($selection_area)) {
            return null;
        }
        
        // If it's a string, try to decode it
        if (is_string($selection_area)) {
            $selection_area = json_decode(sanitize_text_field(wp_unslash($selection_area)), true);
        }
        
        // Validate it's an array with required keys
        if (!is_array($selection_area)) {
            return null;
        }
        
        $required_keys = array('x', 'y', 'width', 'height');
        foreach ($required_keys as $key) {
            if (!isset($selection_area[$key])) {
                return null;
            }
        }
        
        // Return sanitized values
        return array(
            'x' => floatval($selection_area['x']),
            'y' => floatval($selection_area['y']),
            'width' => floatval($selection_area['width']),
            'height' => floatval($selection_area['height'])
        );
    }
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    private function __construct() {
        // Constructor is private for singleton pattern
    }
    
    /**
     * Handle image generation request
     * 
     * @since 1.0.0
     */
    public static function handle_generate_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Validate and sanitize input
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
        
        // Handle multiple reference images (backwards compatible)
        $reference_images_base64 = array();
        if (isset($_POST['referenceImagesBase64'])) {
            // New format: JSON array of base64 images (camelCase from JavaScript)
            $ref_images_json = sanitize_text_field(wp_unslash($_POST['referenceImagesBase64']));
            $ref_images_array = json_decode($ref_images_json, true);
            if (is_array($ref_images_array) && count($ref_images_array) <= 3) {
                // Sanitize each base64 image
                foreach (array_slice($ref_images_array, 0, 3) as $ref_image) {
                    $sanitized = self::sanitize_base64_image($ref_image);
                    if ($sanitized !== false && !empty($sanitized)) {
                        $reference_images_base64[] = $sanitized;
                    }
                }
            }
        } elseif (isset($_POST['reference_images_base64'])) {
            // Fallback: snake_case format
            $ref_images_json = sanitize_text_field(wp_unslash($_POST['reference_images_base64']));
            $ref_images_array = json_decode($ref_images_json, true);
            if (is_array($ref_images_array) && count($ref_images_array) <= 3) {
                // Sanitize each base64 image
                foreach (array_slice($ref_images_array, 0, 3) as $ref_image) {
                    $sanitized = self::sanitize_base64_image($ref_image);
                    if ($sanitized !== false && !empty($sanitized)) {
                        $reference_images_base64[] = $sanitized;
                    }
                }
            }
        } elseif (isset($_POST['reference_image_base64']) && !empty($_POST['reference_image_base64'])) {
            // Legacy format: single base64 image
            $sanitized = self::sanitize_base64_image(sanitize_textarea_field(wp_unslash($_POST['reference_image_base64'])));
            if ($sanitized !== false && !empty($sanitized)) {
                $reference_images_base64 = array($sanitized);
            }
        }
        
        $style = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : 'photorealistic';
        $quality = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : 'standard';
        
        // Get aspect ratio and dimensions from the request
        $aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field(wp_unslash($_POST['aspect_ratio'])) : '1:1';
        $width = isset($_POST['width']) ? intval(wp_unslash($_POST['width'])) : 1024;
        $height = isset($_POST['height']) ? intval(wp_unslash($_POST['height'])) : 1024;
        
        // Validate prompt
        if (empty($prompt) || strlen($prompt) < 10) {
            wp_send_json_error(array('message' => __('Please provide a detailed prompt (minimum 10 characters)) . ', 'w3a11y-artisan')));
        }
        
        if (strlen($prompt) > 2000) {
            wp_send_json_error(array('message' => __('Prompt is too long (maximum 2000 characters)) . ', 'w3a11y-artisan')));
        }
        
        // Validate style
        $valid_styles = array('photorealistic', 'illustration', 'artistic', 'minimalist', 'product', 'logo');
        if (!in_array($style, $valid_styles)) {
            $style = 'photorealistic';
        }
        
        // Validate quality
        $valid_qualities = array('standard', 'hd');
        if (!in_array($quality, $valid_qualities)) {
            $quality = 'standard';
        }
        
        // Validate aspect ratio and ensure dimensions are valid
        $valid_aspect_ratios = array('1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9');
        if (!in_array($aspect_ratio, $valid_aspect_ratios)) {
            $aspect_ratio = '1:1';
            $width = 1024;
            $height = 1024;
        }
        
        // Ensure dimensions are within reasonable bounds (official Gemini API supports up to 1536px for 21:9)
        if ($width < 512 || $width > 1536) {
            $width = 1024;
        }
        if ($height < 512 || $height > 1344) {
            $height = 1024;
        }
        
        // Set up dimensions array
        $dimensions = array(
            'width' => $width,
            'height' => $height
        );
        
        // Prepare API request data
        $request_data = array(
            'prompt' => $prompt,
            'style' => $style,
            'quality' => $quality,
            'aspect_ratio' => $aspect_ratio,
            'dimensions' => $dimensions
        );
        
        // Debug: Log what reference images we have
        W3A11Y_Artisan::log('Reference images count: ' . count($reference_images_base64), 'debug');

        // Add reference images if provided (max 3)
        if (!empty($reference_images_base64)) {
            if (count($reference_images_base64) === 1) {
                // Single image - use legacy format for backwards compatibility
                $request_data['referenceImageBase64'] = $reference_images_base64[0];
            } else {
                // Multiple images - use new array format
                $request_data['referenceImagesBase64'] = $reference_images_base64;
            }
        }
        
        // Make API request
        $result = self::make_api_request('artisan/generate', $request_data);
        
        if ($result['success']) {
            // For generation, we need to use the generated image hash from the API response
            // If the API returns image data, use that for hash, otherwise use a context-based hash
            $image_hash = null;
            if (isset($result['data']['imageBase64']) && !empty($result['data']['imageBase64'])) {
                // Use the generated image data for hash (same as inspiration)
                $image_hash = md5($result['data']['imageBase64']);
            } else {
                // Fallback: create hash from generation parameters
                $context_string = $prompt . '|' . $style . '|' . $quality;
                if (!empty($reference_images_base64)) {
                    // Create hash from all reference images
                    $ref_images_hash = '';
                    foreach ($reference_images_base64 as $ref_base64) {
                        $ref_images_hash .= md5($ref_base64);
                    }
                    $context_string .= '|' . md5($ref_images_hash);
                }
                $image_hash = md5($context_string);
            }
            
            // Debug logging
            W3A11Y_Artisan::log("Saving generate prompt - user_id=" . get_current_user_id() . ", prompt='$prompt', image_hash=$image_hash", 'debug');
            
            // Save prompt to history for reuse with image association
            $saved_id = W3A11Y_Artisan_Database::save_prompt_history(
                get_current_user_id(),
                'generate',
                $prompt,
                array(
                    'style' => $style,
                    'quality' => $quality,
                    'dimensions' => $dimensions,
                    'reference_used' => !empty($reference_images_base64),
                    'reference_count' => count($reference_images_base64)
                ),
                null, // No attachment_id for generate (not saved yet)
                $image_hash // Image hash for association
            );
            
            W3A11Y_Artisan::log("Image generation successful for user " . get_current_user_id(), 'info');
            wp_send_json_success($result['data']);
        } else {
            W3A11Y_Artisan::log("Image generation failed: " . $result['message'], 'error');
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle image editing request
     * 
     * @since 1.0.0
     */
    public static function handle_edit_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Validate and sanitize input
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
        
        // Sanitize base64 image data
        $image_base64_raw = isset($_POST['image_base64']) ? sanitize_textarea_field(wp_unslash($_POST['image_base64'])) : '';
        $image_base64 = self::sanitize_base64_image($image_base64_raw);
        if ($image_base64 === false) {
            wp_send_json_error(array('message' => __('Invalid image data format.', 'w3a11y-artisan')));
        }
        
        // Handle multiple reference images (backwards compatible)
        $reference_images_base64 = array();
        if (isset($_POST['referenceImagesBase64'])) {
            // New format: JSON array of base64 images (camelCase from JavaScript)
            $ref_images_json = sanitize_text_field(wp_unslash($_POST['referenceImagesBase64']));
            $ref_images_array = json_decode($ref_images_json, true);
            if (is_array($ref_images_array) && count($ref_images_array) <= 3) {
                foreach (array_slice($ref_images_array, 0, 3) as $ref_image) {
                    $sanitized = self::sanitize_base64_image($ref_image);
                    if ($sanitized !== false && !empty($sanitized)) {
                        $reference_images_base64[] = $sanitized;
                    }
                }
            }
        } elseif (isset($_POST['reference_images_base64'])) {
            // Fallback: snake_case format
            $ref_images_json = sanitize_text_field(wp_unslash($_POST['reference_images_base64']));
            $ref_images_array = json_decode($ref_images_json, true);
            if (is_array($ref_images_array) && count($ref_images_array) <= 3) {
                foreach (array_slice($ref_images_array, 0, 3) as $ref_image) {
                    $sanitized = self::sanitize_base64_image($ref_image);
                    if ($sanitized !== false && !empty($sanitized)) {
                        $reference_images_base64[] = $sanitized;
                    }
                }
            }
        } elseif (isset($_POST['reference_image_base64']) && !empty($_POST['reference_image_base64'])) {
            // Legacy format: single base64 image
            $sanitized = self::sanitize_base64_image(sanitize_textarea_field(wp_unslash($_POST['reference_image_base64'])));
            if ($sanitized !== false && !empty($sanitized)) {
                $reference_images_base64 = array($sanitized);
            }
        }
        $edit_type = isset($_POST['edit_type']) ? sanitize_text_field(wp_unslash($_POST['edit_type'])) : 'modify';
        
        // Sanitize selection area
        $selection_area_raw = isset($_POST['selection_area']) ? wp_unslash($_POST['selection_area']) : null;
        $selection_area = self::sanitize_selection_area($selection_area_raw);
        
        // Get aspect ratio from the request (missing in original edit implementation!)
        $aspect_ratio = isset($_POST['aspect_ratio']) ? sanitize_text_field(wp_unslash($_POST['aspect_ratio'])) : '1:1';
        
        // Validate prompt
        if (empty($prompt) || strlen($prompt) < 5) {
            wp_send_json_error(array('message' => __('Please provide an editing instruction (minimum 5 characters)) . ', 'w3a11y-artisan')));
        }
        
        if (strlen($prompt) > 2000) {
            wp_send_json_error(array('message' => __('Prompt is too long (maximum 2000 characters)) . ', 'w3a11y-artisan')));
        }
        
        // Validate image base64
        if (empty($image_base64)) {
            wp_send_json_error(array('message' => __('No image data provided.', 'w3a11y-artisan')));
        }
        
        // Validate edit type
        $valid_edit_types = array('add_element', 'remove_element', 'style_transfer', 'modify', 'enhance');
        if (!in_array($edit_type, $valid_edit_types)) {
            $edit_type = 'modify';
        }
        
        // Validate aspect ratio
        $valid_aspect_ratios = array('1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9');
        if (!in_array($aspect_ratio, $valid_aspect_ratios)) {
            $aspect_ratio = '1:1';
        }
        
        // Prepare API request data
        $request_data = array(
            'prompt' => $prompt,
            'imageBase64' => $image_base64,
            'editType' => $edit_type,
            'aspect_ratio' => $aspect_ratio
        );
        
        // Add reference images if provided (max 3)
        if (!empty($reference_images_base64)) {
            if (count($reference_images_base64) === 1) {
                // Single image - use legacy format for backwards compatibility
                $request_data['referenceImageBase64'] = $reference_images_base64[0];
            } else {
                // Multiple images - use new array format
                $request_data['referenceImagesBase64'] = $reference_images_base64;
            }
        }
        
        // Add selection area if provided (already sanitized)
        if ($selection_area !== null) {
            $request_data['selectionArea'] = $selection_area;
        }
        
        // Make API request
        $result = self::make_api_request('artisan/edit', $request_data);
        
        if ($result['success']) {
            // Generate image hash from the original image for prompt association
            $image_hash = md5($image_base64);
            
            // Try to get attachment_id if this image came from WordPress media library
            $attachment_id = isset($_POST['attachment_id']) ? intval(wp_unslash($_POST['attachment_id'])) : null;
            
            // Debug logging
            W3A11Y_Artisan::log("Saving edit prompt - user_id=" . get_current_user_id() . ", prompt='$prompt', attachment_id=$attachment_id, image_hash=$image_hash", 'debug');
            
            // Save prompt to history for reuse with image association
            $saved_id = W3A11Y_Artisan_Database::save_prompt_history(
                get_current_user_id(),
                'edit',
                $prompt,
                array(
                    'edit_type' => $edit_type,
                    'reference_used' => !empty($reference_images_base64),
                    'reference_count' => count($reference_images_base64),
                    'selection_area' => $selection_area
                ),
                $attachment_id, // WordPress attachment ID if available
                $image_hash // Image hash for association
            );
            
            W3A11Y_Artisan::log("Image editing successful for user " . get_current_user_id(), 'info');
            wp_send_json_success($result['data']);
        } else {
            W3A11Y_Artisan::log("Image editing failed: " . $result['message'], 'error');
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle inspiration request
     * 
     * @since 1.0.0
     */
    public static function handle_inspire_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Validate and sanitize input
        $raw_base64 = isset($_POST['image_base64']) ? sanitize_textarea_field(wp_unslash($_POST['image_base64'])) : '';
        $image_base64 = self::sanitize_base64_image($raw_base64);
        
        // Validate image base64
        if (empty($image_base64)) {
            wp_send_json_error(array('message' => __('No image data provided for analysis.', 'w3a11y-artisan')));
        }
        
        // Create image hash for caching
        $image_hash = md5($image_base64);
        $user_id = get_current_user_id();
        
        // Check for cached inspiration
        $cached_inspiration = W3A11Y_Artisan_Database::get_inspiration($user_id, $image_hash);
        
        if ($cached_inspiration) {
            W3A11Y_Artisan::log("Using cached inspiration for user " . $user_id, 'info');
            wp_send_json_success(array('suggestions' => $cached_inspiration['suggestions']));
            return;
        }
        
        // Prepare API request data
        $request_data = array(
            'imageBase64' => $image_base64
        );
        
        // Make API request
        $result = self::make_api_request('artisan/inspire', $request_data);
        
        if ($result['success']) {
            // Cache the inspiration
            $suggestions = isset($result['data']['suggestions']) ? $result['data']['suggestions'] : array();
            W3A11Y_Artisan_Database::save_inspiration($user_id, $image_hash, $suggestions);
            
            W3A11Y_Artisan::log("Inspiration generation successful for user " . $user_id, 'info');
            wp_send_json_success($result['data']);
        } else {
            W3A11Y_Artisan::log("Inspiration generation failed: " . $result['message'], 'error');
            
            // Provide fallback generic suggestions if API fails
            $fallback_suggestions = array(
                array('suggestion' => __('Brighten the lighting', 'w3a11y-artisan'), 'category' => 'lighting'),
                array('suggestion' => __('Add warm colors', 'w3a11y-artisan'), 'category' => 'color'),
                array('suggestion' => __('Enhance contrast', 'w3a11y-artisan'), 'category' => 'enhancement'),
                array('suggestion' => __('Make it more dramatic', 'w3a11y-artisan'), 'category' => 'mood'),
                array('suggestion' => __('Add depth of field', 'w3a11y-artisan'), 'category' => 'effect')
            );
            
            wp_send_json_success(array('suggestions' => $fallback_suggestions));
        }
    }
    
    /**
     * Handle credits check request
     * 
     * @since 1.0.0
     */
    public static function handle_credits_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Make API request
        $result = self::make_api_request('artisan/credits', array(), 'GET');
        
        if ($result['success']) {
            // Transform response to match expected format
            $response_data = $result['data'];
            $credits = isset($response_data['available_credits']) ? $response_data['available_credits'] : 0;
            
            wp_send_json_success(array(
                'credits' => $credits,
                'available_credits' => $credits,
                'artisan_cost_per_operation' => isset($response_data['artisan_cost_per_operation']) ? $response_data['artisan_cost_per_operation'] : 10,
                'operations_possible' => isset($response_data['operations_possible']) ? $response_data['operations_possible'] : 0
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle save image to media library request
     * 
     * @since 1.0.0
     */
    public static function handle_save_image_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Validate and sanitize input
        $raw_base64 = isset($_POST['image_base64']) ? sanitize_textarea_field(wp_unslash($_POST['image_base64'])) : '';
        $image_base64 = self::sanitize_base64_image($raw_base64);
        $filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field(wp_unslash($_POST['alt_text'])) : '';
        $replace_existing = isset($_POST['replace_existing']) ? (bool) wp_unslash($_POST['replace_existing']) : false;
        $existing_attachment_id = isset($_POST['existing_attachment_id']) ? intval(wp_unslash($_POST['existing_attachment_id'])) : 0;
        
        // Validate image data
        if (empty($image_base64)) {
            wp_send_json_error(array('message' => __('No image data provided.', 'w3a11y-artisan')));
        }
        
        // Decode base64
        $image_data = base64_decode($image_base64);
        if (!$image_data) {
            wp_send_json_error(array('message' => __('Invalid image data.', 'w3a11y-artisan')));
        }
        
        // Validate filename
        if (empty($filename)) {
            $filename = 'w3a11y-artisan-' . time() . '.jpg';
        }
        
        // Ensure proper file extension
        $pathinfo = pathinfo($filename);
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), array('jpg', 'jpeg', 'png', 'webp'))) {
            $filename = $pathinfo['filename'] . '.jpg';
        }
        
        try {
            if ($replace_existing && $existing_attachment_id) {
                // Replace existing attachment
                $result = self::replace_attachment($existing_attachment_id, $image_data, $filename, $title, $alt_text);
            } else {
                // Create new attachment
                $result = self::create_attachment($image_data, $filename, $title, $alt_text);
            }
            
            if ($result['success']) {
                // Update prompt history entries with the new attachment_id
                $image_hash = md5($image_base64);
                $updated_count = W3A11Y_Artisan_Database::update_prompt_history_attachment_id(
                    get_current_user_id(),
                    $image_hash,
                    $result['attachment_id']
                );
                
                W3A11Y_Artisan::log("Updated $updated_count prompt history entries with attachment_id=" . $result['attachment_id'] . " for image_hash=$image_hash", 'debug');
                
                W3A11Y_Artisan::log("Image saved to media library: ID " . $result['attachment_id'], 'info');
                wp_send_json_success($result);
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            W3A11Y_Artisan::log("Failed to save image: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => __('Failed to save image to media library.', 'w3a11y-artisan')));
        }
    }
    
    /**
     * Make API request to W3A11Y service
     * 
     * @param string $endpoint API endpoint (without base URL).
     * @param array $data Request data.
     * @param string $method HTTP method (GET, POST).
     * @return array Result array with 'success' boolean and 'data'/'message'.
     * @since 1.0.0
     */
    private static function make_api_request($endpoint, $data = array(), $method = 'POST') {
        $settings = W3A11Y_Artisan::get_settings();
        
        // Check if API is configured
        if (empty($settings['api_key'])) {
            return array(
                'success' => false,
                'message' => __('W3A11Y API key is not configured. Please check your settings.', 'w3a11y-artisan')
            );
        }
        
        // Use centralized API configuration
        $config = w3a11y_get_api_config();
        $api_url = $config['base_url'] . '/' . ltrim($endpoint, '/');
        
        // Prepare request arguments
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
                'User-Agent' => $config['user_agent']
            ),
            'timeout' => $config['timeout'],
            'sslverify' => true
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        // Make request
        $response = wp_remote_request($api_url, $args);
        
        // Handle request errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                /* translators: %s: Error message from the API request */
                'message' => sprintf(__('API request failed: %s', 'w3a11y-artisan'), $response->get_error_message())
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Parse response
        $decoded_body = json_decode($body, true);
        
        if ($status_code === 200 && isset($decoded_body['success']) && $decoded_body['success']) {
            return array(
                'success' => true,
                'data' => $decoded_body
            );
        } else {
            // Handle specific error codes
            $error_message = isset($decoded_body['message']) ? $decoded_body['message'] : __('Unknown API error', 'w3a11y-artisan');
            
            switch ($status_code) {
                case 400:
                    $error_message = __('Invalid request data. Please check your input.', 'w3a11y-artisan');
                    break;
                case 401:
                    $error_message = __('Invalid API key. Please check your settings.', 'w3a11y-artisan');
                    break;
                case 402:
                    $error_message = __('Insufficient credits. Please purchase more credits to continue.', 'w3a11y-artisan');
                    // Add notification for insufficient credits
                    if (class_exists('W3A11Y_Notification_Manager')) {
                        $notification_manager = W3A11Y_Notification_Manager::get_instance();
                        $notification_manager->add_insufficient_credits_notice(0, 'both');
                    }
                    break;
                case 403:
                    $error_message = __('Insufficient permissions. Please check your API key.', 'w3a11y-artisan');
                    break;
                case 429:
                    // Use detailed rate limit message from backend API if available
                    if (isset($decoded_body['message'])) {
                        $error_message = $decoded_body['message'];
                    } else {
                        // Fallback with details if available
                        $limit = isset($decoded_body['limit']) ? $decoded_body['limit'] : 20;
                        $reset_minutes = isset($decoded_body['resetInMinutes']) ? $decoded_body['resetInMinutes'] : 'some time';
                        $error_message = sprintf(
                            /* translators: 1: Request limit per hour, 2: Minutes until reset */
                            __('Rate limit exceeded. You can make %1$d requests per hour. Please try again in %2$s minutes.', 'w3a11y-artisan'),
                            $limit,
                            $reset_minutes
                        );
                    }
                    break;
                case 500:
                    $error_message = __('Server error. Please try again later.', 'w3a11y-artisan');
                    break;
            }
            
            // Add API error notification for non-credit issues
            if ($status_code !== 402 && class_exists('W3A11Y_Notification_Manager')) {
                $notification_manager = W3A11Y_Notification_Manager::get_instance();
                $notification_manager->add_api_error_notice($error_message, $status_code, $endpoint, 'both');
            }
            
            // Prepare error response
            $error_response = array(
                'success' => false,
                'message' => $error_message
            );
            
            // Add rate limit details if available (429 error)
            if ($status_code === 429 && isset($decoded_body['error'])) {
                $error_response['error'] = $decoded_body['error']; // RATE_LIMIT_EXCEEDED
                $error_response['limit'] = isset($decoded_body['limit']) ? $decoded_body['limit'] : null;
                $error_response['used'] = isset($decoded_body['used']) ? $decoded_body['used'] : null;
                $error_response['resetIn'] = isset($decoded_body['resetIn']) ? $decoded_body['resetIn'] : null;
                $error_response['resetInMinutes'] = isset($decoded_body['resetInMinutes']) ? $decoded_body['resetInMinutes'] : null;
            }
            
            return $error_response;
        }
    }
    
    /**
     * Create new attachment in media library
     * 
     * @param string $image_data Binary image data.
     * @param string $filename Desired filename.
     * @param string $title Image title.
     * @param string $alt_text Image alt text.
     * @return array Result with success status and attachment data.
     * @since 1.0.0
     */
    private static function create_attachment($image_data, $filename, $title, $alt_text) {
        // Upload image to WordPress uploads directory
        $upload_result = wp_upload_bits($filename, null, $image_data);
        
        if ($upload_result['error']) {
            return array(
                'success' => false,
                'message' => $upload_result['error']
            );
        }
        
        // Create attachment post
        $attachment_data = array(
            'post_mime_type' => wp_check_filetype($upload_result['file'])['type'],
            'post_title' => $title ?: pathinfo($filename, PATHINFO_FILENAME),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $upload_result['file']);
        
        if (is_wp_error($attachment_id)) {
            return array(
                'success' => false,
                'message' => $attachment_id->get_error_message()
            );
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload_result['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Set alt text
        if ($alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
        
        return array(
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'title' => get_the_title($attachment_id),
            'edit_url' => get_edit_post_link($attachment_id)
        );
    }
    
    /**
     * Replace existing attachment with new image data
     * 
     * @param int $attachment_id Existing attachment ID.
     * @param string $image_data Binary image data.
     * @param string $filename New filename.
     * @param string $title New title.
     * @param string $alt_text New alt text.
     * @return array Result with success status and attachment data.
     * @since 1.0.0
     */
    private static function replace_attachment($attachment_id, $image_data, $filename, $title, $alt_text) {
        // Get existing attachment
        $existing_file = get_attached_file($attachment_id);
        if (!$existing_file) {
            return array(
                'success' => false,
                'message' => __('Existing attachment not found.', 'w3a11y-artisan')
            );
        }
        
        // Create backup of original (optional)
        $backup_file = $existing_file . '.w3a11y-backup-' . time();
        copy($existing_file, $backup_file);
        
        // Write new image data
        $write_result = file_put_contents($existing_file, $image_data);
        if ($write_result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to write new image data.', 'w3a11y-artisan')
            );
        }
        
        // Update attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $existing_file);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Update title if provided
        if ($title) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_title' => $title
            ));
        }
        
        // Update alt text if provided
        if ($alt_text) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
        
        // Clear any caches
        clean_attachment_cache($attachment_id);
        
        return array(
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id) . '?v=' . time(), // Cache busting
            'title' => get_the_title($attachment_id),
            'edit_url' => get_edit_post_link($attachment_id),
            'replaced' => true
        );
    }
    
    /**
     * Handle get attachment data request
     * 
     * @since 1.0.0
     */
    public static function handle_get_attachment_data_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval(wp_unslash($_POST['attachment_id'])) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID.', 'w3a11y-artisan')));
        }
        
        // Get attachment data
        $attachment_url = wp_get_attachment_url($attachment_id);
        if (!$attachment_url) {
            wp_send_json_error(array('message' => __('Attachment not found.', 'w3a11y-artisan')));
        }
        
        // Get attachment metadata
        $title = get_the_title($attachment_id);
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        // Convert image to base64
        $image_path = get_attached_file($attachment_id);
        if (!$image_path || !file_exists($image_path)) {
            wp_send_json_error(array('message' => __('Image file not found.', 'w3a11y-artisan')));
        }
        
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            wp_send_json_error(array('message' => __('Could not read image file.', 'w3a11y-artisan')));
        }
        
        $image_base64 = base64_encode($image_data);
        $image_hash = md5($image_base64);
        
        // Debug logging
        W3A11Y_Artisan::log("Attachment data - attachment_id=$attachment_id, image_hash=$image_hash, base64_length=" . strlen($image_base64), 'debug');
        
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url' => $attachment_url,
            'title' => $title,
            'alt_text' => $alt_text,
            'image_base64' => $image_base64,
            'image_hash' => $image_hash // Provide server-calculated hash
        ));
    }

    /**
     * Handle image format conversion request
     * 
     * @since 1.0.0
     */
    public static function handle_convert_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        // Validate and sanitize input
        $raw_base64 = isset($_POST['image_base64']) ? sanitize_textarea_field(wp_unslash($_POST['image_base64'])) : '';
        $image_base64 = self::sanitize_base64_image($raw_base64);
        $output_format = isset($_POST['output_format']) ? sanitize_text_field(wp_unslash($_POST['output_format'])) : 'png';
        $quality = isset($_POST['quality']) ? intval(wp_unslash($_POST['quality'])) : 90;
        $remove_background = isset($_POST['remove_background']) ? (bool) wp_unslash($_POST['remove_background']) : false;
        
        // Validate image base64
        if (empty($image_base64)) {
            wp_send_json_error(array('message' => __('No image data provided.', 'w3a11y-artisan')));
        }
        
        // Validate format
        $valid_formats = array('png', 'jpeg', 'webp');
        if (!in_array($output_format, $valid_formats)) {
            $output_format = 'png';
        }
        
        // Validate quality
        if ($quality < 50 || $quality > 100) {
            $quality = 90;
        }
        
        // Prepare API request data
        $request_data = array(
            'imageBase64' => $image_base64,
            'outputFormat' => $output_format,
            'quality' => $quality,
            'removeBackground' => $remove_background
        );
        
        // Make API request
        $result = self::make_api_request('artisan/convert', $request_data);
        
        if ($result['success']) {
            W3A11Y_Artisan::log("Image conversion successful for user " . get_current_user_id() . " - format: $output_format", 'info');
            wp_send_json_success($result['data']);
        } else {
            W3A11Y_Artisan::log("Image conversion failed: " . $result['message'], 'error');
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle get prompt history request
     * 
     * @since 1.0.0
     */
    public static function handle_get_prompt_history_request() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_artisan_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'w3a11y-artisan')));
        }
        
        $operation_type = isset($_POST['operation_type']) ? sanitize_text_field(wp_unslash($_POST['operation_type'])) : null;
        $limit = isset($_POST['limit']) ? intval(wp_unslash($_POST['limit'])) : 20;
        $attachment_id = isset($_POST['attachment_id']) ? intval(wp_unslash($_POST['attachment_id'])) : null;
        $image_hash = isset($_POST['image_hash']) ? sanitize_text_field(wp_unslash($_POST['image_hash'])) : null;

        // Debug logging
        W3A11Y_Artisan::log("Getting prompt history - user_id=" . get_current_user_id() . ", operation_type=$operation_type, attachment_id=$attachment_id, image_hash=$image_hash", 'debug');

        $history = W3A11Y_Artisan_Database::get_prompt_history(
            get_current_user_id(), 
            $operation_type, 
            $limit, 
            $attachment_id, 
            $image_hash
        );

        // Debug logging
        W3A11Y_Artisan::log("Found " . count($history) . " prompts in history", 'debug');

        wp_send_json_success(array(
            'prompts' => $history
        ));
    }
}