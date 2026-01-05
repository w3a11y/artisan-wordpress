<?php
/**
 * Plugin Name: W3A11Y Artisan
 * Plugin URI: https://w3a11y.com/artisan
 * Description: AI-powered image and alttext generation and editing directly in WordPress Media Library. Create and edit images with simple text prompts using advanced AI technology.
 * Version: 1.0
 * Author: W3A11Y
 * Author URI: https://w3a11y.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: w3a11y-artisan
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 *
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('W3A11Y_ARTISAN_VERSION', '1.0');
define('W3A11Y_ARTISAN_PLUGIN_FILE', __FILE__);
define('W3A11Y_ARTISAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('W3A11Y_ARTISAN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('W3A11Y_ARTISAN_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('W3A11Y_ARTISAN_TEXT_DOMAIN', 'w3a11y-artisan');

/**
 * Main W3A11Y Artisan Plugin Class
 * 
 * @since 1.0.0
 */
class W3A11Y_Artisan {
    
    /**
     * Plugin instance
     * 
     * @var W3A11Y_Artisan
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return W3A11Y_Artisan
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
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Plugin activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin after WordPress loads
        add_action('init', array($this, 'init'));
        
        // Load plugin text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . W3A11Y_ARTISAN_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    /**
     * Load plugin dependencies
     * 
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core classes
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-database.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-logger.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-notification-manager.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-admin.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-media-integration.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-batch-processor.php';
        require_once W3A11Y_ARTISAN_PLUGIN_DIR . 'includes/class-alttext-handler.php';
    }
    
    /**
     * Initialize plugin
     * 
     * @since 1.0.0
     */
    public function init() {
        // Check if user has required capabilities
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Check for database upgrades
        W3A11Y_Artisan_Database::maybe_upgrade();
        
        // Initialize core components
        W3A11Y_Logger::get_instance();
        W3A11Y_Notification_Manager::get_instance();
        W3A11Y_Artisan_Admin::get_instance();
        W3A11Y_Artisan_Media_Integration::get_instance();
        W3A11Y_Artisan_API_Handler::get_instance();
        W3A11Y_AltText_Handler::get_instance();
        
        // Hook into WordPress actions
        $this->init_wordpress_hooks();
    }
    
    /**
     * Initialize WordPress-specific hooks
     * 
     * @since 1.0.0
     */
    private function init_wordpress_hooks() {
        // AJAX handlers for authenticated users
        add_action('wp_ajax_w3a11y_artisan_generate', array('W3A11Y_Artisan_API_Handler', 'handle_generate_request'));
        add_action('wp_ajax_w3a11y_artisan_edit', array('W3A11Y_Artisan_API_Handler', 'handle_edit_request'));
        add_action('wp_ajax_w3a11y_artisan_inspire', array('W3A11Y_Artisan_API_Handler', 'handle_inspire_request'));
        add_action('wp_ajax_w3a11y_artisan_credits', array('W3A11Y_Artisan_API_Handler', 'handle_credits_request'));
        add_action('wp_ajax_w3a11y_artisan_convert', array('W3A11Y_Artisan_API_Handler', 'handle_convert_request'));
        add_action('wp_ajax_w3a11y_artisan_save_image', array('W3A11Y_Artisan_API_Handler', 'handle_save_image_request'));
        add_action('wp_ajax_w3a11y_get_prompt_history', array('W3A11Y_Artisan_API_Handler', 'handle_get_prompt_history_request'));
        
        // AltText AJAX handlers are registered in W3A11Y_AltText_Handler class
        
        // Cleanup cron job
        add_action('w3a11y_artisan_cleanup_cron', array('W3A11Y_Artisan_Database', 'cleanup_expired_data'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('w3a11y_artisan_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'w3a11y_artisan_cleanup_cron');
        }
    }
    
    /**
     * Plugin activation
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Check WordPress version compatibility
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(
                esc_html(__('W3A11Y Artisan requires WordPress 5.0 or higher. Please update WordPress before activating this plugin.', 'w3a11y-artisan')),
                esc_html(__('Plugin Activation Error', 'w3a11y-artisan')),
                array('back_link' => true)
            );
        }
        
        // Check PHP version compatibility
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(
                esc_html(__('W3A11Y Artisan requires PHP 7.4 or higher. Please contact your hosting provider to upgrade PHP.', 'w3a11y-artisan')),
                esc_html(__('Plugin Activation Error', 'w3a11y-artisan')),
                array('back_link' => true)
            );
        }
        
        // Create default options
        $this->create_default_options();
        
        // Create database tables
        W3A11Y_Artisan_Database::create_tables();
        
        // Set activation flag for redirect to settings
        set_transient('w3a11y_artisan_activation_redirect', true, 30);
        
        // Log activation
        W3A11Y_Artisan::log('Plugin activated successfully', 'info');
    }
    
    /**
     * Plugin deactivation
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('w3a11y_artisan_activation_redirect');
        
        // Log deactivation
        W3A11Y_Artisan::log('Plugin deactivated', 'info');
    }
    
    /**
     * Create default plugin options
     * 
     * @since 1.0.0
     */
    private function create_default_options() {
        $default_options = array(
            'api_key' => '',
            'credit_check_enabled' => true,
            'enable_logging' => false,
            'version' => W3A11Y_ARTISAN_VERSION
        );
        
        // Only add if options don't exist
        if (!get_option('w3a11y_artisan_settings')) {
            add_option('w3a11y_artisan_settings', $default_options);
        }
    }
    
    /**
     * Load plugin text domain for translations
     * 
     * Note: Since WordPress 4.6, translations are automatically loaded
     * from WordPress.org when the plugin is hosted there. No need to
     * manually call load_plugin_textdomain() for plugins on WordPress.org.
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        // Translations are automatically loaded by WordPress.org
        // Text domain: w3a11y-artisan
        // For local development, you can uncomment the line below:
        // load_plugin_textdomain('w3a11y-artisan', false, dirname(W3A11Y_ARTISAN_PLUGIN_BASENAME)) . '/languages');
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook_suffix The current admin page hook suffix.
     * @since 1.0.0
     */
    public function admin_enqueue_scripts($hook_suffix) {
        // Only load on relevant admin pages
        $relevant_pages = array(
            'upload.php',           // Media Library
            'post.php',             // Edit Post
            'post-new.php',         // New Post
            'media.php',            // Media pages
            'admin_page_w3a11y-artisan-settings', // Our settings page
            'admin_page_w3a11y-bulk-alttext'      // Our bulk alttext page
        );
        
        // Check if we're on a relevant page or our settings page
        if (!in_array($hook_suffix, $relevant_pages) && strpos($hook_suffix, 'w3a11y') === false) {
            return;
        }
        
        // Register a minimal inline script handler for pages that need inline scripts
        // This allows us to use wp_add_inline_script() properly
        wp_register_script(
            'w3a11y-artisan-inline',
            false, // No file - just a placeholder for inline scripts
            array(),
            W3A11Y_ARTISAN_VERSION,
            true
        );
        wp_enqueue_script('w3a11y-artisan-inline');
        
        // Note: JavaScript files are now enqueued by W3A11Y_Artisan_Media_Integration class
        // to avoid conflicts and ensure proper loading conditions
        
        // Only enqueue CSS from here - let media integration handle JS
        
        // Enqueue admin styles
        wp_enqueue_style(
            'w3a11y-artisan-admin',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            W3A11Y_ARTISAN_VERSION
        );
        
        // Enqueue modal styles
        wp_enqueue_style(
            'w3a11y-artisan-modal',
            W3A11Y_ARTISAN_PLUGIN_URL . 'assets/css/modal-style.css',
            array(),
            W3A11Y_ARTISAN_VERSION
        );
        
        // Enqueue AltText scripts and styles
        if ($hook_suffix === 'admin_page_w3a11y-bulk-alttext' || strpos($hook_suffix, 'w3a11y') !== false) {
            wp_enqueue_script(
                'w3a11y-alttext-integration',
                W3A11Y_ARTISAN_PLUGIN_URL . 'assets/js/alttext-integration.js',
                array(), // No jQuery dependency - pure vanilla JavaScript
                W3A11Y_ARTISAN_VERSION,
                true
            );
            
            // Localize script with AJAX data for AltText functionality
            wp_localize_script('w3a11y-alttext-integration', 'w3a11yAltTextAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('w3a11y_artisan_nonce'),
                'batch_size' => 8,
                'batch_delay' => 2000,
                'generating_text' => __('Generating...', 'w3a11y-artisan')
            ));
            
            // Enqueue AltText styles
            wp_enqueue_style(
                'w3a11y-alttext-styles',
                W3A11Y_ARTISAN_PLUGIN_URL . 'assets/css/alttext-styles.css',
                array(),
                W3A11Y_ARTISAN_VERSION
            );
        }
        
        // Add inline script for settings page to handle dynamic dimension updates
        if (strpos($hook_suffix, 'w3a11y-artisan-settings') !== false) {
            $inline_script = "
            document.addEventListener('DOMContentLoaded', function() {
                const resolutionOptions = document.querySelectorAll('input[name=\"w3a11y_artisan_settings[default_resolution]\"]');
                const dimensionElements = document.querySelectorAll('.w3a11y-aspect-ratio-options .dimensions');
                
                function updateDimensions() {
                    const selectedResolution = document.querySelector('input[name=\"w3a11y_artisan_settings[default_resolution]\"]:checked');
                    if (!selectedResolution) return;
                    
                    const resolution = selectedResolution.value.toLowerCase();
                    
                    dimensionElements.forEach(function(element) {
                        const dimensionValue = element.getAttribute('data-' + resolution);
                        if (dimensionValue) {
                            element.textContent = dimensionValue;
                        }
                    });
                }
                
                resolutionOptions.forEach(function(option) {
                    option.addEventListener('change', updateDimensions);
                });
            });
            ";
            
            wp_add_inline_script('w3a11y-artisan-inline', $inline_script);
        }
    }
    
    /**
     * Localize admin scripts with PHP data
     * 
     * @since 1.0.0
     */
    private function localize_admin_scripts() {
        $settings = get_option('w3a11y_artisan_settings', array());
        
        $localized_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('w3a11y_artisan_nonce'),
            'api_configured' => !empty($settings['api_key']),
            'enable_logging' => !empty($settings['enable_logging']),
            'plugin_url' => W3A11Y_ARTISAN_PLUGIN_URL,
            'version' => W3A11Y_ARTISAN_VERSION,
            'media_library_url' => admin_url('upload.php?w3a11y_generate=1'),
            'texts' => array(
                'generating' => __('Generating image...', 'w3a11y-artisan'),
                'editing' => __('Editing image...', 'w3a11y-artisan'),
                'analyzing' => __('Analyzing image for inspiration...', 'w3a11y-artisan'),
                'saving' => __('Saving to Media Library...', 'w3a11y-artisan'),
                'error_generic' => __('An error occurred. Please try again.', 'w3a11y-artisan'),
                'error_no_api_key' => __('Please configure your W3A11Y API key in Settings.', 'w3a11y-artisan'),
                'error_credits' => __('Insufficient credits. Please purchase more credits.', 'w3a11y-artisan'),
                'confirm_revert' => __('Are you sure you want to revert to the original image? This cannot be undone.', 'w3a11y-artisan'),
                'success_saved' => __('Image saved successfully to Media Library!', 'w3a11y-artisan')
            ),
            'settings_url' => admin_url('admin.php?page=w3a11y-artisan-settings'),
            'max_file_size' => wp_max_upload_size(),
            'supported_formats' => array('image/jpeg', 'image/png', 'image/webp')
        );
        
        wp_localize_script('w3a11y-artisan-admin', 'w3a11yArtisan', $localized_data);
        wp_localize_script('w3a11y-artisan-media', 'w3a11yArtisan', $localized_data);
    }
    
    /**
     * Add plugin action links
     * 
     * @param array $links Existing action links.
     * @return array Modified action links.
     * @since 1.0.0
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=w3a11y-artisan-settings'),
            __('Settings', 'w3a11y-artisan')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Get plugin settings
     * 
     * @param string $key Optional. Specific setting key to retrieve.
     * @return mixed Settings array or specific setting value.
     * @since 1.0.0
     */
    public static function get_settings($key = null) {
        $settings = get_option('w3a11y_artisan_settings', array());
        
        if ($key !== null) {
            return isset($settings[$key]) ? $settings[$key] : null;
        }
        
        return $settings;
    }
    
    /**
     * Update plugin settings
     * 
     * @param array $new_settings New settings to save.
     * @return bool Whether the settings were updated successfully.
     * @since 1.0.0
     */
    public static function update_settings($new_settings) {
        $current_settings = self::get_settings();
        $updated_settings = wp_parse_args($new_settings, $current_settings);
        
        return update_option('w3a11y_artisan_settings', $updated_settings);
    }
    
    /**
     * Log plugin messages using plugin's logger
     * 
     * @param string $message Log message.
     * @param string $level Log level (error, warning, info, debug).
     * @since 1.0.0
     */
    public static function log($message, $level = 'info') {
        $logger = W3A11Y_Logger::get_instance();
        $logger->log($message, $level);
    }
}

/**
 * Initialize the plugin
 * 
 * @since 1.0.0
 */
function w3a11y_artisan_init() {
    return W3A11Y_Artisan::get_instance();
}

// Start the plugin
w3a11y_artisan_init();

/**
 * Helper function to check if plugin is properly configured
 * 
 * @return bool Whether the plugin has required settings.
 * @since 1.0.0
 */
function w3a11y_artisan_is_configured() {
    $settings = W3A11Y_Artisan::get_settings();
    return !empty($settings['api_key']);
}

/**
 * Get centralized W3A11Y API configuration
 * 
 * @return array API configuration array
 * @since 1.0.0
 */
function w3a11y_get_api_config() {
    
    $base_url = 'https://w3a11y.com/api';
    
    return array(
        'base_url' => rtrim($base_url, '/'),
        'endpoints' => array(
            'artisan' => array(
                'generate' => '/artisan/generate',
                'edit' => '/artisan/edit',
                'inspire' => '/artisan/inspire',
                'credits' => '/artisan/credits',
                'convert' => '/artisan/convert'
            ),
            'alttext' => array(
                'generate' => '/alttext/generate',
                'config' => '/alttext/config',
                'credits' => '/artisan/credits'
            )
        ),
        'timeout' => 60,
        'user_agent' => 'W3A11Y-WordPress-Plugin/' . W3A11Y_ARTISAN_VERSION
    );
}

/**
 * Helper function to get API endpoint URL
 * 
 * @param string $service Service name (artisan, alttext)
 * @param string $endpoint Specific API endpoint
 * @return string Full API URL
 * @since 1.0.0
 */
function w3a11y_artisan_get_api_url($service = 'artisan', $endpoint = '') {
    $config = w3a11y_get_api_config();
    
    // Handle legacy single parameter usage
    if (empty($endpoint) && !empty($service)) {
        // If service contains a slash, treat it as a full endpoint path
        if (strpos($service, '/') !== false) {
            return $config['base_url'] . '/' . ltrim($service, '/');
        }
        // Otherwise treat as artisan endpoint for backwards compatibility
        $endpoint = $service;
        $service = 'artisan';
    }
    
    // Build URL from service and endpoint
    if (isset($config['endpoints'][$service][$endpoint])) {
        return $config['base_url'] . $config['endpoints'][$service][$endpoint];
    }
    
    // Fallback: construct URL manually
    $service_path = ($service !== 'artisan') ? '/' . $service : '';
    $endpoint_path = !empty($endpoint) ? '/' . ltrim($endpoint, '/') : '';
    
    return $config['base_url'] . $service_path . $endpoint_path;
}