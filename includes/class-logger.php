<?php
/**
 * W3A11Y Logger Class
 * 
 * Handles plugin-specific logging with file-based storage and management.
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Logger Class
 * 
 * @since 1.1.0
 */
class W3A11Y_Logger {
    
    /**
     * Logger instance
     * 
     * @var W3A11Y_Logger
     * @since 1.1.0
     */
    private static $instance = null;
    
    /**
     * Log file path
     * 
     * @var string
     * @since 1.1.0
     */
    private $log_file;
    
    /**
     * Max log file size (5MB)
     * 
     * @var int
     * @since 1.1.0
     */
    private $max_file_size = 5242880;
    
    /**
     * Get logger instance (Singleton pattern)
     * 
     * @return W3A11Y_Logger
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
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/w3a11y-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Add .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($log_dir . '/.htaccess', $htaccess_content);
        }
        
        $this->log_file = $log_dir . '/w3a11y-debug.log';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     * 
     * @since 1.1.0
     */
    private function init_hooks() {
        // AJAX handlers for log management
        add_action('wp_ajax_w3a11y_view_logs', array($this, 'ajax_view_logs'));
        add_action('wp_ajax_w3a11y_download_logs', array($this, 'ajax_download_logs'));
        add_action('wp_ajax_w3a11y_clear_logs', array($this, 'ajax_clear_logs'));
    }
    
    /**
     * Log a message
     * 
     * @param string $message Log message
     * @param string $level   Log level (info, warning, error, debug)
     * @param string $context Context/component
     * @since 1.1.0
     */
    public function log($message, $level = 'info', $context = 'W3A11Y') {
        // Check if logging is enabled
        $settings = W3A11Y_Artisan::get_settings();
        if (empty($settings['enable_logging'])) {
            return;
        }
        
        // Rotate log file if it's too large
        $this->rotate_log_if_needed();
        
        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);
        $context = strtoupper($context);
        
        // Format: [2024-09-28 10:30:45] W3A11Y.INFO: Message
        $log_entry = "[{$timestamp}] {$context}.{$level}: {$message}" . PHP_EOL;
        
        // Write to log file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log an API request
     * 
     * @param string $method   HTTP method
     * @param string $url      Request URL
     * @param array  $data     Request data
     * @param int    $response Response code
     * @since 1.1.0
     */
    public function log_api_request($method, $url, $data = array(), $response = null) {
        $message = "API {$method} {$url}";
        
        if (!empty($data)) {
            $message .= " | Data: " . wp_json_encode($data);
        }
        
        if ($response !== null) {
            $message .= " | Response: {$response}";
        }
        
        $this->log($message, 'debug', 'API');
    }
    
    /**
     * Log an error with context
     * 
     * @param string $message Error message
     * @param array  $context Error context
     * @since 1.1.0
     */
    public function log_error($message, $context = array()) {
        $full_message = $message;
        
        if (!empty($context)) {
            $full_message .= " | Context: " . wp_json_encode($context);
        }
        
        $this->log($full_message, 'error');
    }
    
    /**
     * Get log contents
     * 
     * @param int $lines Number of lines to get (0 for all)
     * @return string Log contents
     * @since 1.1.0
     */
    public function get_logs($lines = 1000) {
        if (!file_exists($this->log_file)) {
            return __('No logs found.', 'w3a11y-artisan');
        }
        
        $content = file_get_contents($this->log_file);
        
        if ($lines > 0) {
            $log_lines = explode("\n", $content);
            $log_lines = array_slice($log_lines, -$lines);
            $content = implode("\n", $log_lines);
        }
        
        return $content;
    }
    
    /**
     * Clear log file
     * 
     * @since 1.1.0
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
    }
    
    /**
     * Get log file path for download
     * 
     * @return string Log file path
     * @since 1.1.0
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
    
    /**
     * Rotate log file if it exceeds maximum size
     * 
     * @since 1.1.0
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        if (filesize($this->log_file) > $this->max_file_size) {
            // Rename current log to backup
            $backup_file = $this->log_file . '.old';
            if (file_exists($backup_file)) {
                wp_delete_file($backup_file);
            }
            // Use WP_Filesystem for file operations
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $wp_filesystem->move($this->log_file, $backup_file, true);
            
            // Start fresh log
            file_put_contents($this->log_file, '');
        }
    }
    
    /**
     * AJAX handler for viewing logs
     * 
     * @since 1.1.0
     */
    public function ajax_view_logs() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_logs')) {
            wp_send_json_error('Security check failed.');
            return;
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }
        
        $logs = $this->get_logs();
        wp_send_json_success(array('logs' => $logs));
    }
    
    /**
     * AJAX handler for downloading logs
     * 
     * @since 1.1.0
     */
    public function ajax_download_logs() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '', 'w3a11y_logs')) {
            wp_die('Security check failed.');
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        
        if (!file_exists($this->log_file)) {
            wp_die('Log file not found.');
        }
        
        $filename = 'w3a11y-debug-' . gmdate('Y-m-d-H-i-s') . '.log';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($this->log_file));
        
        // Use WP_Filesystem for file operations
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a log file download, raw content is required
        echo $wp_filesystem->get_contents($this->log_file);
        exit;
    }
    
    /**
     * AJAX handler for clearing logs
     * 
     * @since 1.1.0
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'w3a11y_logs')) {
            wp_send_json_error('Security check failed.');
            return;
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }
        
        $this->clear_logs();
        wp_send_json_success('Logs cleared successfully.');
    }
}