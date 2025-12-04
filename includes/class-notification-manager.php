<?php
/**
 * W3A11Y Notification Manager Class
 * 
 * Handles centralized notification system for both admin notices and frontend alerts.
 * Manages credit warnings, API errors, and other plugin notifications.
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Notification Manager Class
 * 
 * @since 1.1.0
 */
class W3A11Y_Notification_Manager {
    
    /**
     * Notification manager instance
     * 
     * @var W3A11Y_Notification_Manager
     * @since 1.1.0
     */
    private static $instance = null;
    
    /**
     * Stored notifications
     * 
     * @var array
     * @since 1.1.0
     */
    private $notifications = array();
    
    /**
     * Credit thresholds
     * 
     * @var array
     * @since 1.1.0
     */
    private $credit_thresholds = array(
        'critical' => 0,   // No credits left
        'low' => 10,       // Low credits warning
        'warning' => 25    // General warning threshold
    );
    
    /**
     * Get notification manager instance (Singleton pattern)
     * 
     * @return W3A11Y_Notification_Manager
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
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @since 1.1.0
     */
    private function init_hooks() {
        // Admin notices hook
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // AJAX endpoint for frontend notifications
        add_action('wp_ajax_w3a11y_dismiss_notice', array($this, 'ajax_dismiss_notice'));
        add_action('wp_ajax_w3a11y_add_notification', array($this, 'ajax_add_notification'));
        
        // Hook to check credit levels on admin pages
        add_action('admin_init', array($this, 'check_credit_levels'));
        
        // Add frontend notification system to admin pages
        add_action('admin_footer', array($this, 'add_frontend_notification_system'));
    }
    
    /**
     * Add a notification to the queue
     * 
     * @param string $type    Notification type (error, warning, success, info)
     * @param string $message Notification message
     * @param string $context Context where notification should appear (admin, frontend, both)
     * @param array  $meta    Additional metadata
     * @since 1.1.0
     */
    public function add_notification($type, $message, $context = 'admin', $meta = array()) {
        $notification = array(
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'meta' => $meta,
            'timestamp' => time(),
            'dismissible' => isset($meta['dismissible']) ? $meta['dismissible'] : true,
            'persistent' => isset($meta['persistent']) ? $meta['persistent'] : false,
            'id' => isset($meta['id']) ? $meta['id'] : uniqid('w3a11y_notice_')
        );
        
        // Store notification
        $this->notifications[] = $notification;
        
        // Store persistent notifications in WordPress options
        if ($notification['persistent']) {
            $persistent_notices = get_option('w3a11y_persistent_notices', array());
            $persistent_notices[$notification['id']] = $notification;
            update_option('w3a11y_persistent_notices', $persistent_notices);
        }
        
        W3A11Y_Artisan::log("Added notification: {$type} - {$message}", 'info');
    }
    
    /**
     * Add low credits warning
     * 
     * @param int    $available_credits Current available credits
     * @param string $context          Context (admin, frontend, both)
     * @since 1.1.0
     */
    public function add_low_credits_warning($available_credits, $context = 'admin') {
        $message = sprintf(
            /* translators: 1: Number of credits remaining, 2: URL to purchase more credits */
            wp_kses_post(__('Low credits warning: Only %1$d credits remaining. <a href="%2$s" target="_blank">Purchase more credits</a> to avoid service interruption.', 'w3a11y-artisan')),
            $available_credits,
            esc_url('https://w3a11y.com/pricing')
        );
        
        $this->add_notification('warning', $message, $context, array(
            'id' => 'w3a11y_low_credits_' . gmdate('Y-m-d'), // One per day
            'dismissible' => true,
            'persistent' => true, // Persist until dismissed or credits replenished
            'credit_level' => 'low'
        ));
    }
    
    /**
     * Add API error notification
     * 
     * @param string $error_message API error message
     * @param int    $status_code   HTTP status code
     * @param string $endpoint      API endpoint that failed
     * @param string $context       Context (admin, frontend, both)
     * @since 1.1.0
     */
    public function add_api_error_notice($error_message, $status_code = 0, $endpoint = '', $context = 'both') {
        $message = $error_message;
        
        // Add additional context for common errors
        if ($status_code === 401) {
            $settings_url = admin_url('admin.php?page=w3a11y-artisan-settings');
            $message .= sprintf(' <a href="%s">Check your API key settings</a>.', $settings_url);
        } elseif ($status_code === 429) {
            $message .= ' Please wait a few minutes before trying again.';
        }
        
        $this->add_notification('error', $message, $context, array(
            'id' => 'w3a11y_api_error_' . $status_code,
            'dismissible' => true,
            'persistent' => false,
            'api_error' => true,
            'status_code' => $status_code,
            'endpoint' => $endpoint
        ));
    }
    
    /**
     * Check current credit levels (disabled - only frontend notifications now)
     * 
     * @since 1.1.0
     */
    public function check_credit_levels() {
        // Disabled automatic credit checking to avoid persistent admin notices
        // Credit warnings will only show via frontend notification system
        return;
    }
    
    /**
     * Get current credit count from available handlers
     * 
     * @return int|false Current credits or false on failure
     * @since 1.1.0
     */
    private function get_current_credits() {
        // Use API handler to get credits
        if (class_exists('W3A11Y_Artisan_API_Handler')) {
            $api_url = w3a11y_artisan_get_api_url('artisan', 'credits');
            $settings = W3A11Y_Artisan::get_settings();
            
            if (empty($settings['api_key'])) {
                return false;
            }
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $settings['api_key'],
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 10
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['available_credits'])) {
                    return intval($data['available_credits']);
                } elseif (isset($data['credits'])) {
                    return intval($data['credits']);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Display admin notices
     * 
     * @since 1.1.0
     */
    public function display_admin_notices() {
        // Skip admin notices on pages that have frontend notification system
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for context only, not processing form data
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $pages_with_frontend_notifications = array(
            'w3a11y-bulk-alttext',
            'w3a11y-artisan-settings'
        );
        
        if (in_array($current_page, $pages_with_frontend_notifications, true)) {
            return; // Skip showing admin notices on these pages
        }
        
        // Load persistent notices
        $persistent_notices = get_option('w3a11y_persistent_notices', array());
        $dismissed_notices = get_option('w3a11y_dismissed_notices', array());
        
        // Merge with current session notifications
        $all_notifications = array_merge($persistent_notices, $this->notifications);
        
        foreach ($all_notifications as $notification) {
            // Skip if not for admin context
            if ($notification['context'] !== 'admin' && $notification['context'] !== 'both') {
                continue;
            }
            
            // Skip if dismissed
            if (isset($dismissed_notices[$notification['id']])) {
                continue;
            }
            
            $this->render_admin_notice($notification);
        }
    }
    
    /**
     * Render a single admin notice
     * 
     * @param array $notification Notification data
     * @since 1.1.0
     */
    private function render_admin_notice($notification) {
        $type_class = 'notice-info';
        
        switch ($notification['type']) {
            case 'error':
                $type_class = 'notice-error';
                break;
            case 'warning':
                $type_class = 'notice-warning';
                break;
            case 'success':
                $type_class = 'notice-success';
                break;
        }
        
        $dismissible_class = $notification['dismissible'] ? 'is-dismissible' : '';
        $notice_id_value = esc_attr($notification['id']);
        
        echo '<div class="notice ' . esc_attr($type_class) . ' ' . esc_attr($dismissible_class) . '" data-notice-id="' . esc_attr($notice_id_value) . '">';
        echo '<p><strong>W3A11Y:</strong> ' . wp_kses_post($notification['message']) . '</p>';
        echo '</div>';
        
        // Add dismissible JavaScript if needed
        if ($notification['dismissible']) {
            $this->add_dismissible_js();
        }
    }
    
    /**
     * Add JavaScript for dismissible notices
     * 
     * @since 1.1.0
     */
    private function add_dismissible_js() {
        static $js_added = false;
        
        if ($js_added) {
            return;
        }
        
        $js_added = true;
        
        $nonce = wp_create_nonce('w3a11y_dismiss_notice');
        $inline_script = "
        document.addEventListener('DOMContentLoaded', function() {
            // Handle dismissible notices
            document.querySelectorAll('.notice.is-dismissible[data-notice-id]').forEach(function(notice) {
                var dismissButton = notice.querySelector('.notice-dismiss');
                if (dismissButton) {
                    dismissButton.addEventListener('click', function() {
                        var noticeId = notice.getAttribute('data-notice-id');
                        if (noticeId) {
                            // Send AJAX request to dismiss notice
                            var formData = new FormData();
                            formData.append('action', 'w3a11y_dismiss_notice');
                            formData.append('notice_id', noticeId);
                            formData.append('nonce', '" . esc_js($nonce) . "');
                            
                            fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            });
                        }
                    });
                }
            });
        });
        ";
        wp_add_inline_script('w3a11y-artisan-inline', $inline_script);
    }
    
    /**
     * AJAX handler for dismissing notices
     * 
     * @since 1.1.0
     */
    public function ajax_dismiss_notice() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_dismiss_notice')) {
            wp_die('Security check failed.');
        }
        
        $notice_id = isset($_POST['notice_id']) ? sanitize_text_field(wp_unslash($_POST['notice_id'])) : '';
        $dismissed_notices = get_option('w3a11y_dismissed_notices', array());
        $dismissed_notices[$notice_id] = time();
        
        // Clean up old dismissed notices (older than 30 days)
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        foreach ($dismissed_notices as $id => $timestamp) {
            if ($timestamp < $thirty_days_ago) {
                unset($dismissed_notices[$id]);
            }
        }
        
        update_option('w3a11y_dismissed_notices', $dismissed_notices);
        
        // Remove from persistent notices if applicable
        $persistent_notices = get_option('w3a11y_persistent_notices', array());
        if (isset($persistent_notices[$notice_id])) {
            unset($persistent_notices[$notice_id]);
            update_option('w3a11y_persistent_notices', $persistent_notices);
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for adding notifications from frontend
     * 
     * @since 1.1.0
     */
    public function ajax_add_notification() {
        // Verify nonce - accept multiple nonce types for flexibility
        $nonce_verified = false;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        // Try different nonce actions that might be available
        $nonce_actions = array('w3a11y_notification', 'w3a11y_artisan_nonce', 'w3a11y_nonce'); 
        foreach ($nonce_actions as $action) {
            if (wp_verify_nonce($nonce, $action)) {
                $nonce_verified = true;
                break;
            }
        }
        
        if (!$nonce_verified) {
            wp_send_json_error('Security check failed.');
            return;
        }
        
        // Verify user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'error';
        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'both';
        $status_code = intval($_POST['status_code'] ?? 0);
        
        if (empty($message)) {
            wp_send_json_error('Message is required.');
            return;
        }
        
        // Generic notification
        $this->add_notification($type, $message, $context, array(
            'dismissible' => true,
            'persistent' => false
        ));
        $frontend_message = $message;
        
        // Return response with frontend notification data
        wp_send_json_success(array(
            'message' => 'Notification added successfully.',
            'frontend_notification' => array(
                'message' => $frontend_message,
                'type' => $type,
                'duration' => $status_code === 402 ? 0 : 50000 // Persistent for credit errors
            )
        ));
    }
    
    /**
     * Add frontend notification container and JavaScript to admin pages
     * 
     * @since 1.1.0
     */
    public function add_frontend_notification_system() {
        // Only add on admin pages
        if (!is_admin()) {
            return;
        }
        
        ?>
        <div id="w3a11y-notifications" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999999; max-width: 400px;"></div>
        <?php
        
        $inline_style = "
        @keyframes w3a11ySlideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes w3a11ySlideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .w3a11y-notification {
            animation: w3a11ySlideIn 0.3s ease-out;
        }
        
        .w3a11y-notification.w3a11y-notification-sliding-out {
            animation: w3a11ySlideOut 0.3s ease-in forwards;
        }
        ";
        wp_add_inline_style('wp-admin', $inline_style);
        
        $inline_script = "
        if (typeof window.W3A11YNotifications === 'undefined') {
            window.W3A11YNotifications = {
                /**
                 * Show a frontend notification
                 * @param {string} message - Notification message  
                 * @param {string} type - Notification type (error, warning, success, info)
                 * @param {number} duration - Duration in milliseconds (0 for permanent)
                 */
                show: function(message, type, duration) {
                    type = type || 'info';
                    duration = duration || 50000;

                    const container = document.getElementById('w3a11y-notifications');
                    if (!container) return;

                    const notification = document.createElement('div');
                    notification.className = 'w3a11y-notification w3a11y-notification-' + type;
                    notification.setAttribute('role', 'alert');
                    notification.style.cssText = 
                        'background: #fff;' +
                        'border-left: 4px solid ' + this.getColor(type) + ';' +
                        'box-shadow: 0 2px 5px rgba(0,0,0,0.2);' +
                        'padding: 12px 16px;' +
                        'border-radius: 3px;' +
                        'font-family: -apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Oxygen-Sans,Ubuntu,Cantarell,sans-serif;' +
                        'font-size: 13px;' +
                        'line-height: 1.4;'

                    notification.innerHTML = 
                        '<div style=\"display: flex; justify-content: space-between; align-items: center;\">' +
                            '<div style=\"flex: 1; margin-right: 10px;\">' +
                                '<strong>W3A11Y:</strong> ' + message +
                            '</div>' +
                            '<button type=\"button\" style=\"background: none; border: none; font-size: 18px; cursor: pointer; opacity: 0.5;\" title=\"Dismiss\">×</button>' +
                        '</div>';

                    // Add dismiss functionality
                    const closeBtn = notification.querySelector('button');
                    closeBtn.addEventListener('click', () => {
                        this.remove(notification);
                    });

                    container.appendChild(notification);

                    // Auto-remove after duration
                    if (duration > 0) {
                        setTimeout(() => {
                            this.remove(notification);
                        }, duration);
                    }

                    return notification;
                },

                /**
                 * Get notification color by type
                 * @param {string} type - Notification type
                 * @returns {string} Color code
                 */
                getColor: function(type) {
                    const colors = {
                        error: '#dc3232',
                        warning: '#ffb900', 
                        success: '#46b450',
                        info: '#00a0d2'
                    };
                    return colors[type] || colors.info;
                },

                /**
                 * Remove a notification with animation
                 * @param {Element} notification - Notification element
                 */
                remove: function(notification) {
                    if (notification && notification.parentNode) {
                        notification.classList.add('w3a11y-notification-sliding-out');
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.parentNode.removeChild(notification);
                            }
                        }, 300);
                    }
                }
            };
        }
        ";
        wp_add_inline_script('w3a11y-artisan-inline', $inline_script);
    }
    
    /**
     * Get notifications for frontend display
     * 
     * @param string $context Context filter (frontend, both)
     * @return array Array of notifications
     * @since 1.1.0
     */
    public function get_frontend_notifications($context = 'frontend') {
        $frontend_notifications = array();
        $dismissed_notices = get_option('w3a11y_dismissed_notices', array());
        
        foreach ($this->notifications as $notification) {
            if ($notification['context'] === $context || $notification['context'] === 'both') {
                if (!isset($dismissed_notices[$notification['id']])) {
                    $frontend_notifications[] = $notification;
                }
            }
        }
        
        return $frontend_notifications;
    }
    
    /**
     * Clear all notifications
     * 
     * @since 1.1.0
     */
    public function clear_notifications() {
        $this->notifications = array();
        delete_option('w3a11y_persistent_notices');
    }
    
    /**
     * Clear notifications by type
     * 
     * @param string $type Notification type to clear
     * @since 1.1.0
     */
    public function clear_notifications_by_type($type) {
        $this->notifications = array_filter($this->notifications, function($notification) use ($type) {
            return $notification['type'] !== $type;
        });
        
        // Also clear from persistent notices
        $persistent_notices = get_option('w3a11y_persistent_notices', array());
        foreach ($persistent_notices as $id => $notification) {
            if ($notification['type'] === $type) {
                unset($persistent_notices[$id]);
            }
        }
        update_option('w3a11y_persistent_notices', $persistent_notices);
    }
}