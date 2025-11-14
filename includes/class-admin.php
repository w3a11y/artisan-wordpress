<?php
/**
 * W3A11Y Artisan Admin Class
 * 
 * Handles all admin-related functionality including settings page, 
 * admin notices, and admin-specific hooks.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Artisan Admin Class
 * 
 * @since 1.0.0
 */
class W3A11Y_Artisan_Admin {
    
    /**
     * Admin instance
     * 
     * @var W3A11Y_Artisan_Admin
     * @since 1.0.0
     */
    private static $instance = null;
    
    /**
     * Get admin instance (Singleton pattern)
     * 
     * @return W3A11Y_Artisan_Admin
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
     * Initialize admin hooks
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Handle settings notices
        add_action('admin_notices', array($this, 'settings_notices'));
        
        // Handle activation redirect
        add_action('admin_init', array($this, 'activation_redirect'));
        
        // AJAX handler for API key validation
        add_action('wp_ajax_w3a11y_validate_api_key', array($this, 'ajax_validate_api_key'));
    }
    
    /**
     * Add admin menu
     * 
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __('W3A11Y Artisan Settings', 'w3a11y-artisan'),     // Page title
            __('W3A11Y', 'w3a11y-artisan'),                      // Menu title
            'manage_options',                             // Capability
            'w3a11y-artisan-settings',                   // Menu slug
            array($this, 'settings_page'),               // Callback
            $this->get_menu_icon(),                      // Icon
            30                                           // Position
        );
        
        // Add submenu for settings (to show "Settings" under W3A11Y)
        add_submenu_page(
            'w3a11y-artisan-settings',                   // Parent slug
            __('Artisan Settings', 'w3a11y-artisan'),           // Page title
            __('Artisan Settings', 'w3a11y-artisan'),           // Menu title
            'manage_options',                             // Capability
            'w3a11y-artisan-settings',                   // Menu slug (same as parent)
            array($this, 'settings_page')               // Callback
        );
        
        // Add submenu for bulk alt text generation
        add_submenu_page(
            'w3a11y-artisan-settings',                   // Parent slug
            __('Bulk Alt Text Generation', 'w3a11y-artisan'),   // Page title
            __('Bulk Alt Text', 'w3a11y-artisan'),              // Menu title
            'manage_options',                             // Capability
            'w3a11y-bulk-alttext',                      // Menu slug
            array($this, 'bulk_alttext_page')           // Callback
        );
    }
    
    /**
     * Get menu icon SVG
     * 
     * @return string Base64 encoded SVG icon
     * @since 1.0.0
     */
    private function get_menu_icon() {
        // W3A11Y logo SVG (simplified version)
        $svg = '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="currentColor"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Register plugin settings
     * 
     * @since 1.0.0
     */
    public function register_settings() {
        // Register settings group
        register_setting(
            'w3a11y_artisan_settings_group',
            'w3a11y_artisan_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'show_in_rest' => false
            )
        );
        
        // Add settings sections
        add_settings_section(
            'w3a11y_artisan_api_section',
            __('API Configuration', 'w3a11y-artisan'),
            array($this, 'api_section_callback'),
            'w3a11y-artisan-settings'
        );
        
        add_settings_section(
            'w3a11y_artisan_general_section',
            __('General Settings', 'w3a11y-artisan'),
            array($this, 'general_section_callback'),
            'w3a11y-artisan-settings'
        );
        
        add_settings_section(
            'w3a11y_artisan_image_section',
            __('Image Settings', 'w3a11y-artisan'),
            array($this, 'image_section_callback'),
            'w3a11y-artisan-settings'
        );
        
        add_settings_section(
            'w3a11y_artisan_alttext_section',
            __('AltText Settings', 'w3a11y-artisan'),
            array($this, 'alttext_section_callback'),
            'w3a11y-artisan-settings'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add individual settings fields
     * 
     * @since 1.0.0
     */
    private function add_settings_fields() {
        // API Key field
        add_settings_field(
            'api_key',
            __('API Key', 'w3a11y-artisan'),
            array($this, 'api_key_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_api_section'
        );
        

        
        // Enable Logging field
        add_settings_field(
            'enable_logging',
            __('Enable Debug Logging', 'w3a11y-artisan'),
            array($this, 'enable_logging_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_general_section'
        );
        
        // Default Aspect Ratio field
        add_settings_field(
            'default_aspect_ratio',
            __('Default Aspect Ratio', 'w3a11y-artisan'),
            array($this, 'default_aspect_ratio_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_image_section'
        );
        
        // Default Style field
        add_settings_field(
            'default_style',
            __('Default Style', 'w3a11y-artisan'),
            array($this, 'default_style_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_image_section'
        );
        
        // AltText Custom Instructions field
        add_settings_field(
            'alttext_custom_instructions',
            __('Custom AI Instructions', 'w3a11y-artisan'),
            array($this, 'alttext_custom_instructions_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_alttext_section'
        );
        
        // AltText Language field
        add_settings_field(
            'alttext_language',
            __('Alt Text Language', 'w3a11y-artisan'),
            array($this, 'alttext_language_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_alttext_section'
        );
        
        // AltText Max Length field
        add_settings_field(
            'alttext_max_length',
            __('Maximum Length', 'w3a11y-artisan'),
            array($this, 'alttext_max_length_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_alttext_section'
        );
        
        // AltText Style field
        add_settings_field(
            'alttext_style',
            __('Alt Text Style', 'w3a11y-artisan'),
            array($this, 'alttext_style_field_callback'),
            'w3a11y-artisan-settings',
            'w3a11y_artisan_alttext_section'
        );
    }
    
    /**
     * Settings page callback
     * 
     * @since 1.0.0
     */
    public function settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have sufficient permissions to access this page.', 'w3a11y-artisan')));
        }
        
        // Include settings page template
        include W3A11Y_ARTISAN_PLUGIN_DIR . 'admin/admin-settings.php';
    }
    
    /**
     * Bulk Alt Text page callback
     * 
     * @since 1.1.0
     */
    public function bulk_alttext_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('You do not have sufficient permissions to access this page.', 'w3a11y-artisan')));
        }
        
        // Check if API key is configured
        $settings = W3A11Y_Artisan::get_settings();
        if (empty($settings['api_key'])) {
            wp_die(
                esc_html(__('API key is required for bulk alt text generation. Please configure your API key in the settings first.', 'w3a11y-artisan')),
                esc_html(__('Configuration Required', 'w3a11y-artisan')),
                array('back_link' => true)
            );
        }
        
        // Include bulk alt text page template
        include W3A11Y_ARTISAN_PLUGIN_DIR . 'admin/partials/alttext-bulk.php';
    }
    
    /**
     * API section callback
     * 
     * @since 1.0.0
     */
    public function api_section_callback() {
        echo '<p>' . esc_html(__('Configure your W3A11Y API credentials to enable Artisan image generation features.', 'w3a11y-artisan')) . '</p>';
        
        // Show API key status
        $settings = W3A11Y_Artisan::get_settings();
        if (!empty($settings['api_key'])) {
            echo '<div class="notice notice-success inline"><p>';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
            echo esc_html(__('API key is configured and ready to use.', 'w3a11y-artisan'));
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>';
            echo '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> ';
            echo esc_html(__('Please enter your W3A11Y API key to enable image generation features.', 'w3a11y-artisan'));
            echo '</p></div>';
        }
    }
    
    /**
     * General section callback
     * 
     * @since 1.0.0
     */
    public function general_section_callback() {
        echo '<p>' . esc_html(__('Configure general plugin behavior and performance settings.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * Image section callback
     * 
     * @since 1.0.0
     */
    public function image_section_callback() {
        echo '<p>' . esc_html(__('Configure default image generation settings that will be pre-selected in the Artisan editor.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * Default Aspect Ratio field callback
     * 
     * @since 1.0.0
     */
    public function default_aspect_ratio_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $default_aspect_ratio = isset($settings['default_aspect_ratio']) ? $settings['default_aspect_ratio'] : '1:1';
        
        $aspect_ratios = array(
            '1:1' => array(
                'label' => __('Square', 'w3a11y-artisan'),
                'dimensions' => '1024×1024',
                'icon' => '<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="1" y="1" width="20" height="20" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '2:3' => array(
                'label' => __('Portrait', 'w3a11y-artisan'),
                'dimensions' => '832×1248',
                'icon' => '<svg width="12" height="18" viewBox="0 0 12 18" fill="none"><rect x="1" y="1" width="10" height="16" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '3:2' => array(
                'label' => __('Landscape', 'w3a11y-artisan'),
                'dimensions' => '1248×832',
                'icon' => '<svg width="18" height="12" viewBox="0 0 18 12" fill="none"><rect x="1" y="1" width="16" height="10" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '3:4' => array(
                'label' => __('Classic Portrait', 'w3a11y-artisan'),
                'dimensions' => '864×1184',
                'icon' => '<svg width="12" height="16" viewBox="0 0 12 16" fill="none"><rect x="1" y="1" width="10" height="14" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '4:3' => array(
                'label' => __('Classic Landscape', 'w3a11y-artisan'),
                'dimensions' => '1184×864',
                'icon' => '<svg width="18" height="14" viewBox="0 0 18 14" fill="none"><rect x="1" y="1" width="16" height="12" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '4:5' => array(
                'label' => __('Social Portrait', 'w3a11y-artisan'),
                'dimensions' => '896×1152',
                'icon' => '<svg width="12" height="15" viewBox="0 0 12 15" fill="none"><rect x="1" y="1" width="10" height="13" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '5:4' => array(
                'label' => __('Social Landscape', 'w3a11y-artisan'),
                'dimensions' => '1152×896',
                'icon' => '<svg width="15" height="12" viewBox="0 0 15 12" fill="none"><rect x="1" y="1" width="13" height="10" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '9:16' => array(
                'label' => __('Mobile Portrait', 'w3a11y-artisan'),
                'dimensions' => '768×1344',
                'icon' => '<svg width="12" height="22" viewBox="0 0 12 22" fill="none"><rect x="1" y="1" width="10" height="20" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '16:9' => array(
                'label' => __('Widescreen', 'w3a11y-artisan'),
                'dimensions' => '1344×768',
                'icon' => '<svg width="22" height="12" viewBox="0 0 22 12" fill="none"><rect x="1" y="1" width="20" height="10" stroke="currentColor" stroke-width="2"></rect></svg>'
            ),
            '21:9' => array(
                'label' => __('Ultra Wide', 'w3a11y-artisan'),
                'dimensions' => '1536×672',
                'icon' => '<svg width="26" height="11" viewBox="0 0 26 11" fill="none"><rect x="1" y="1" width="24" height="9" stroke="currentColor" stroke-width="2"></rect></svg>'
            )
        );
        
        // Define allowed SVG tags and attributes for wp_kses
        $svg_allowed = array(
            'svg' => array(
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'fill' => true,
                'xmlns' => true,
            ),
            'rect' => array(
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'stroke' => true,
                'stroke-width' => true,
                'fill' => true,
            ),
        );
        
        echo '<div class="w3a11y-aspect-ratio-options">';
        foreach ($aspect_ratios as $ratio => $info) {
            $checked = ($default_aspect_ratio === $ratio) ? 'checked' : '';
            echo '<label class="w3a11y-aspect-option">';
            echo '<input type="radio" name="w3a11y_artisan_settings[default_aspect_ratio]" value="' . esc_attr($ratio) . '" ' . esc_attr($checked) . ' />';
            echo '<span class="w3a11y-aspect-visual">';
            echo '<span class="w3a11y-aspect-icon">' . wp_kses($info['icon'], $svg_allowed) . '</span>';
            echo '<span class="w3a11y-aspect-info">';
            echo '<strong>' . esc_html($ratio) . '</strong><br>';
            echo '<small>' . esc_html($info['label']) . '</small><br>';
            echo '<small class="dimensions">' . esc_html($info['dimensions']) . '</small>';
            echo '</span>';
            echo '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . esc_html(__('Select the default aspect ratio that will be pre-selected when users open the Artisan editor.', 'w3a11y-artisan')) . '</p>';
        
    }

    /**
     * Default Style field callback
     * 
     * @since 1.0.0
     */
    public function default_style_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $default_style = isset($settings['default_style']) ? $settings['default_style'] : 'photorealistic';
        
        $styles = array(
            'photorealistic' => array(
                'label' => __('Photorealistic', 'w3a11y-artisan'),
                'description' => __('Highly realistic, photographic quality', 'w3a11y-artisan'),
                'icon' => '📷'
            ),
            'illustration' => array(
                'label' => __('Illustration', 'w3a11y-artisan'),
                'description' => __('Digital artwork with clean lines and vibrant colors', 'w3a11y-artisan'),
                'icon' => '🎨'
            ),
            'artistic' => array(
                'label' => __('Artistic', 'w3a11y-artisan'),
                'description' => __('Creative interpretation with artistic flair', 'w3a11y-artisan'),
                'icon' => '🖼️'
            ),
            'minimalist' => array(
                'label' => __('Minimalist', 'w3a11y-artisan'),
                'description' => __('Clean, simple composition with negative space', 'w3a11y-artisan'),
                'icon' => '⚪'
            ),
            'product' => array(
                'label' => __('Product', 'w3a11y-artisan'),
                'description' => __('Professional product photography style', 'w3a11y-artisan'),
                'icon' => '📦'
            ),
            'logo' => array(
                'label' => __('Logo', 'w3a11y-artisan'),
                'description' => __('Clean, professional logo design', 'w3a11y-artisan'),
                'icon' => '🏷️'
            )
        );
        
        echo '<div class="w3a11y-style-options">';
        foreach ($styles as $style => $info) {
            $checked = ($default_style === $style) ? 'checked' : '';
            echo '<label class="w3a11y-style-option">';
            echo '<input type="radio" name="w3a11y_artisan_settings[default_style]" value="' . esc_attr($style) . '" ' . esc_attr($checked) . ' />';
            echo '<span class="w3a11y-style-visual">';
            echo '<span class="w3a11y-style-icon">' . esc_html($info['icon']) . '</span>';
            echo '<span class="w3a11y-style-info">';
            echo '<strong>' . esc_html($info['label']) . '</strong><br>';
            echo '<small>' . esc_html($info['description']) . '</small>';
            echo '</span>';
            echo '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . esc_html(__('Select the default style that will be pre-selected when users open the Artisan editor.', 'w3a11y-artisan')) . '</p>';
        
    }
    public function api_key_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        
        echo '<input type="password" id="api_key" name="w3a11y_artisan_settings[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<button type="button" class="button button-secondary" id="toggle-api-key" style="margin-left: 10px;">' . esc_html(__('Show/Hide', 'w3a11y-artisan')) . '</button>';
        echo '<button type="button" class="button button-secondary" id="validate-api-key" style="margin-left: 10px;">' . esc_html(__('Validate', 'w3a11y-artisan')) . '</button>';
        echo '<div id="api-key-validation-result" style="margin-top: 10px;"></div>';
        echo '<p class="description">' . esc_html(__('Enter your W3A11Y API key. You can get this from your W3A11Y dashboard.', 'w3a11y-artisan')) . '</p>';
        
        // Add inline JavaScript for toggle and validation
        $this->api_key_inline_js();
    }

    
    /**
     * Enable Logging field callback
     * 
     * @since 1.0.0
     */
    public function enable_logging_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $enabled = isset($settings['enable_logging']) ? $settings['enable_logging'] : false;
        
        echo '<label for="enable_logging">';
        echo '<input type="checkbox" id="enable_logging" name="w3a11y_artisan_settings[enable_logging]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo esc_html(__('Enable debug logging', 'w3a11y-artisan'));
        echo '</label>';
        echo '<p class="description">' . esc_html(__('Enable detailed logging for troubleshooting. Logs are saved to plugin log file.', 'w3a11y-artisan')) . '</p>';
        
        // Show log viewer if logging is enabled
        if ($enabled) {
            echo '<div id="w3a11y-log-viewer" style="margin-top: 15px;">';
            echo '<h4>' . esc_html(__('Debug Log', 'w3a11y-artisan')) . '</h4>';
            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">';
            echo '<button type="button" class="button" onclick="w3a11yViewLogs()">' . esc_html(__('View Logs', 'w3a11y-artisan')) . '</button> ';
            echo '<button type="button" class="button" onclick="w3a11yDownloadLogs()">' . esc_html(__('Download Logs', 'w3a11y-artisan')) . '</button> ';
            echo '<button type="button" class="button" onclick="w3a11yClearLogs()">' . esc_html(__('Clear Logs', 'w3a11y-artisan')) . '</button>';
            echo '</div>';
            echo '<textarea id="w3a11y-log-content" style="width: 100%; height: 300px; font-family: monospace; font-size: 11px;" readonly placeholder="' . esc_html(__('Click View Logs to see debug information...', 'w3a11y-artisan')) . '"></textarea>';
            echo '</div>';
            
            // Add JavaScript for log management
            $nonce = wp_create_nonce('w3a11y_logs');
            $download_url = admin_url('admin-ajax.php?action=w3a11y_download_logs&nonce=' . $nonce);
            ?>
            <script type="text/javascript">
            window.w3a11yViewLogs = function() {
                const textarea = document.getElementById('w3a11y-log-content');
                const button = event.target;
                button.disabled = true;
                button.textContent = 'Loading...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=w3a11y_view_logs&nonce=<?php echo esc_js($nonce); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = data.data.logs;
                        textarea.scrollTop = textarea.scrollHeight;
                    } else {
                        textarea.value = 'Error: ' + data.data;
                    }
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = '<?php echo esc_js(__('View Logs', 'w3a11y-artisan')); ?>';
                });
            };
            
            window.w3a11yDownloadLogs = function() {
                window.open('<?php echo esc_url($download_url); ?>');
            };
            
            window.w3a11yClearLogs = function() {
                if (confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'w3a11y-artisan')); ?>')) {
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=w3a11y_clear_logs&nonce=<?php echo esc_js($nonce); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('w3a11y-log-content').value = '';
                            alert('<?php echo esc_js(__('Logs cleared successfully.', 'w3a11y-artisan')); ?>');
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                }
            };
            </script>
            <?php
        }
    }
    
    /**
     * AltText section callback
     * 
     * @since 1.1.0
     */
    public function alttext_section_callback() {
        echo '<p>' . esc_html(__('Configure AI-powered alt text generation settings for enhanced accessibility.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * AltText Custom Instructions field callback
     * 
     * @since 1.1.0
     */
    public function alttext_custom_instructions_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $instructions = isset($settings['alttext_custom_instructions']) ? $settings['alttext_custom_instructions'] : '';
        
        echo '<textarea id="alttext_custom_instructions" name="w3a11y_artisan_settings[alttext_custom_instructions]" rows="4" cols="50" class="large-text" maxlength="500">' . esc_textarea($instructions) . '</textarea>';
        echo '<div class="character-counter" style="margin-top: 5px; font-size: 12px; color: #666;"><span id="alttext-instructions-count">' . esc_html(strlen($instructions)) . '</span> / 500 characters</div>';
        echo '<p class="description">' . esc_html(__('Additional instructions for the AI to customize alt text generation. You can make changes here to overwrite your default Custom AI Instructions.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * AltText Language field callback
     * 
     * @since 1.1.0
     */
    public function alttext_language_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $language = isset($settings['alttext_language']) ? $settings['alttext_language'] : 'en';
        
        $languages = array(
            'af' => __('Afrikaans', 'w3a11y-artisan'),
            'sq' => __('Albanian', 'w3a11y-artisan'),
            'am' => __('Amharic', 'w3a11y-artisan'),
            'ar' => __('Arabic', 'w3a11y-artisan'),
            'hy' => __('Armenian', 'w3a11y-artisan'),
            'az' => __('Azerbaijani', 'w3a11y-artisan'),
            'eu' => __('Basque', 'w3a11y-artisan'),
            'be' => __('Belarusian', 'w3a11y-artisan'),
            'bn' => __('Bengali', 'w3a11y-artisan'),
            'bs' => __('Bosnian', 'w3a11y-artisan'),
            'bg' => __('Bulgarian', 'w3a11y-artisan'),
            'ca' => __('Catalan', 'w3a11y-artisan'),
            'ceb' => __('Cebuano', 'w3a11y-artisan'),
            'ny' => __('Chichewa', 'w3a11y-artisan'),
            'zh' => __('Chinese (Simplified)', 'w3a11y-artisan'),
            'zh-TW' => __('Chinese (Traditional)', 'w3a11y-artisan'),
            'co' => __('Corsican', 'w3a11y-artisan'),
            'hr' => __('Croatian', 'w3a11y-artisan'),
            'cs' => __('Czech', 'w3a11y-artisan'),
            'da' => __('Danish', 'w3a11y-artisan'),
            'nl' => __('Dutch', 'w3a11y-artisan'),
            'en' => __('English', 'w3a11y-artisan'),
            'eo' => __('Esperanto', 'w3a11y-artisan'),
            'et' => __('Estonian', 'w3a11y-artisan'),
            'tl' => __('Filipino', 'w3a11y-artisan'),
            'fi' => __('Finnish', 'w3a11y-artisan'),
            'fr' => __('French', 'w3a11y-artisan'),
            'fy' => __('Frisian', 'w3a11y-artisan'),
            'gl' => __('Galician', 'w3a11y-artisan'),
            'ka' => __('Georgian', 'w3a11y-artisan'),
            'de' => __('German', 'w3a11y-artisan'),
            'el' => __('Greek', 'w3a11y-artisan'),
            'gu' => __('Gujarati', 'w3a11y-artisan'),
            'ht' => __('Haitian Creole', 'w3a11y-artisan'),
            'ha' => __('Hausa', 'w3a11y-artisan'),
            'haw' => __('Hawaiian', 'w3a11y-artisan'),
            'he' => __('Hebrew', 'w3a11y-artisan'),
            'hi' => __('Hindi', 'w3a11y-artisan'),
            'hmn' => __('Hmong', 'w3a11y-artisan'),
            'hu' => __('Hungarian', 'w3a11y-artisan'),
            'is' => __('Icelandic', 'w3a11y-artisan'),
            'ig' => __('Igbo', 'w3a11y-artisan'),
            'id' => __('Indonesian', 'w3a11y-artisan'),
            'ga' => __('Irish', 'w3a11y-artisan'),
            'it' => __('Italian', 'w3a11y-artisan'),
            'ja' => __('Japanese', 'w3a11y-artisan'),
            'jw' => __('Javanese', 'w3a11y-artisan'),
            'kn' => __('Kannada', 'w3a11y-artisan'),
            'kk' => __('Kazakh', 'w3a11y-artisan'),
            'km' => __('Khmer', 'w3a11y-artisan'),
            'rw' => __('Kinyarwanda', 'w3a11y-artisan'),
            'ko' => __('Korean', 'w3a11y-artisan'),
            'ku' => __('Kurdish (Kurmanji)', 'w3a11y-artisan'),
            'ky' => __('Kyrgyz', 'w3a11y-artisan'),
            'lo' => __('Lao', 'w3a11y-artisan'),
            'la' => __('Latin', 'w3a11y-artisan'),
            'lv' => __('Latvian', 'w3a11y-artisan'),
            'lt' => __('Lithuanian', 'w3a11y-artisan'),
            'lb' => __('Luxembourgish', 'w3a11y-artisan'),
            'mk' => __('Macedonian', 'w3a11y-artisan'),
            'mg' => __('Malagasy', 'w3a11y-artisan'),
            'ms' => __('Malay', 'w3a11y-artisan'),
            'ml' => __('Malayalam', 'w3a11y-artisan'),
            'mt' => __('Maltese', 'w3a11y-artisan'),
            'mi' => __('Maori', 'w3a11y-artisan'),
            'mr' => __('Marathi', 'w3a11y-artisan'),
            'mn' => __('Mongolian', 'w3a11y-artisan'),
            'my' => __('Myanmar (Burmese)', 'w3a11y-artisan'),
            'ne' => __('Nepali', 'w3a11y-artisan'),
            'no' => __('Norwegian', 'w3a11y-artisan'),
            'or' => __('Odia', 'w3a11y-artisan'),
            'ps' => __('Pashto', 'w3a11y-artisan'),
            'fa' => __('Persian', 'w3a11y-artisan'),
            'pl' => __('Polish', 'w3a11y-artisan'),
            'pt' => __('Portuguese', 'w3a11y-artisan'),
            'pa' => __('Punjabi', 'w3a11y-artisan'),
            'ro' => __('Romanian', 'w3a11y-artisan'),
            'ru' => __('Russian', 'w3a11y-artisan'),
            'sm' => __('Samoan', 'w3a11y-artisan'),
            'gd' => __('Scots Gaelic', 'w3a11y-artisan'),
            'sr' => __('Serbian', 'w3a11y-artisan'),
            'st' => __('Sesotho', 'w3a11y-artisan'),
            'sn' => __('Shona', 'w3a11y-artisan'),
            'sd' => __('Sindhi', 'w3a11y-artisan'),
            'si' => __('Sinhala', 'w3a11y-artisan'),
            'sk' => __('Slovak', 'w3a11y-artisan'),
            'sl' => __('Slovenian', 'w3a11y-artisan'),
            'so' => __('Somali', 'w3a11y-artisan'),
            'es' => __('Spanish', 'w3a11y-artisan'),
            'su' => __('Sundanese', 'w3a11y-artisan'),
            'sw' => __('Swahili', 'w3a11y-artisan'),
            'sv' => __('Swedish', 'w3a11y-artisan'),
            'tg' => __('Tajik', 'w3a11y-artisan'),
            'ta' => __('Tamil', 'w3a11y-artisan'),
            'tt' => __('Tatar', 'w3a11y-artisan'),
            'te' => __('Telugu', 'w3a11y-artisan'),
            'th' => __('Thai', 'w3a11y-artisan'),
            'tr' => __('Turkish', 'w3a11y-artisan'),
            'tk' => __('Turkmen', 'w3a11y-artisan'),
            'uk' => __('Ukrainian', 'w3a11y-artisan'),
            'ur' => __('Urdu', 'w3a11y-artisan'),
            'ug' => __('Uyghur', 'w3a11y-artisan'),
            'uz' => __('Uzbek', 'w3a11y-artisan'),
            'vi' => __('Vietnamese', 'w3a11y-artisan'),
            'cy' => __('Welsh', 'w3a11y-artisan'),
            'xh' => __('Xhosa', 'w3a11y-artisan'),
            'yi' => __('Yiddish', 'w3a11y-artisan'),
            'yo' => __('Yoruba', 'w3a11y-artisan'),
            'zu' => __('Zulu', 'w3a11y-artisan')
        );
        
        echo '<select id="alttext_language" name="w3a11y_artisan_settings[alttext_language]">';
        foreach ($languages as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($language, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html(__('This will override your default language preference for this image only.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * AltText Max Length field callback
     * 
     * @since 1.1.0
     */
    public function alttext_max_length_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $max_length = isset($settings['alttext_max_length']) ? intval($settings['alttext_max_length']) : 125;
        
        echo '<div class="alttext-length-container">';
        echo '<input type="range" id="alttext_max_length" name="w3a11y_artisan_settings[alttext_max_length]" min="50" max="300" value="' . esc_attr($max_length) . '" class="alttext-length-slider" />';
        echo '<input type="number" id="alttext_max_length_display" value="' . esc_attr($max_length) . '" min="50" max="300" class="small-text alttext-length-display" />';
        echo '<span class="alttext-length-label"> characters</span>';
        echo '</div>';
        echo '<p class="description">' . esc_html(__('To overwrite your default Maximum Length preference, you can make changes here.', 'w3a11y-artisan')) . '</p>';
    }
    
    /**
     * AltText Style field callback
     * 
     * @since 1.1.0
     */
    public function alttext_style_field_callback() {
        $settings = W3A11Y_Artisan::get_settings();
        $style = isset($settings['alttext_style']) ? $settings['alttext_style'] : 'detailed';
        
        $styles = array(
            'detailed' => __('Detailed', 'w3a11y-artisan'),
            'concise' => __('Concise', 'w3a11y-artisan'),
            'functional' => __('Functional', 'w3a11y-artisan')
        );
        
        echo '<div class="alttext-style-options">';
        foreach ($styles as $style_value => $style_name) {
            echo '<label class="alttext-style-option">';
            echo '<input type="radio" name="w3a11y_artisan_settings[alttext_style]" value="' . esc_attr($style_value) . '"' . checked($style, $style_value, false) . ' />';
            echo '<span>' . esc_html($style_name) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        
        $style_descriptions = array(
            'detailed' => __('Provide comprehensive descriptions including context, atmosphere, and detailed visual elements', 'w3a11y-artisan'),
            'concise' => __('Be brief and focus only on essential visual elements', 'w3a11y-artisan'),
            'functional' => __('Focus primarily on the image\'s purpose and function rather than detailed visual descriptions', 'w3a11y-artisan')
        );
        
        foreach ($style_descriptions as $style_value => $description) {
            echo '<p class="description alttext-style-description" data-style="' . esc_attr($style_value) . '" style="display: ' . ($style === $style_value ? 'block' : 'none') . ';">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Sanitize settings
     * 
     * @param array $input Input settings to sanitize.
     * @return array Sanitized settings.
     * @since 1.0.0
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize API key and validate if provided
        if (isset($input['api_key'])) {
            $api_key = sanitize_text_field(trim($input['api_key']));
            
            // If API key is provided, validate it
            if (!empty($api_key)) {
                $current_settings = W3A11Y_Artisan::get_settings();
                $current_api_key = isset($current_settings['api_key']) ? $current_settings['api_key'] : '';
                
                // Validate if the API key has changed or if there's no current key
                if ($api_key !== $current_api_key) {
                    $validation_result = $this->validate_api_credentials($api_key);
                    
                    if (!$validation_result['valid']) {
                        add_settings_error(
                            'w3a11y_artisan_settings',
                            'invalid_api_key',
                            $validation_result['message'],
                            'error'
                        );
                        
                        // Reject the invalid API key - keep the current one
                        $sanitized['api_key'] = $current_api_key;
                    } else {
                        add_settings_error(
                            'w3a11y_artisan_settings',
                            'api_key_validated',
                            __('API Key validated successfully!', 'w3a11y-artisan'),
                            'updated'
                        );
                        $sanitized['api_key'] = $api_key;
                    }
                } else {
                    // API key hasn't changed, keep it
                    $sanitized['api_key'] = $api_key;
                }
            } else {
                // Empty API key provided
                $sanitized['api_key'] = '';
            }
        }
        
        // Remove API endpoint handling - it's fixed internally
        
        // Sanitize image quality
        if (isset($input['image_quality'])) {
            $valid_qualities = array('standard', 'hd');
            $sanitized['image_quality'] = in_array($input['image_quality'], $valid_qualities) ? 
                $input['image_quality'] : 'standard';
        }
        
        // Sanitize max image size
        if (isset($input['max_image_size'])) {
            $size = intval($input['max_image_size']);
            $sanitized['max_image_size'] = max(512, min(4096, $size));
        }
        
        // Sanitize checkboxes
        $sanitized['credit_check_enabled'] = isset($input['credit_check_enabled']) ? true : false;
        $sanitized['enable_logging'] = isset($input['enable_logging']) ? true : false;
        
        // Sanitize default aspect ratio
        if (isset($input['default_aspect_ratio'])) {
            $allowed_ratios = array('1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9');
            $ratio = sanitize_text_field($input['default_aspect_ratio']);
            $sanitized['default_aspect_ratio'] = in_array($ratio, $allowed_ratios) ? $ratio : '1:1';
        }
        
        // Sanitize default style
        if (isset($input['default_style'])) {
            $allowed_styles = array('photorealistic', 'illustration', 'artistic', 'minimalist', 'product', 'logo');
            $style = sanitize_text_field($input['default_style']);
            $sanitized['default_style'] = in_array($style, $allowed_styles) ? $style : 'photorealistic';
        }
        
        // Preserve version
        $current_settings = W3A11Y_Artisan::get_settings();
        if (isset($current_settings['version'])) {
            $sanitized['version'] = $current_settings['version'];
        }
        
        // Sanitize AltText settings
        if (isset($input['alttext_custom_instructions'])) {
            $sanitized['alttext_custom_instructions'] = sanitize_textarea_field($input['alttext_custom_instructions']);
        }
        
        if (isset($input['alttext_language'])) {
            $allowed_languages = array(
                'af', 'sq', 'am', 'ar', 'hy', 'az', 'eu', 'be', 'bn', 'bs', 'bg', 'ca', 'ceb', 'ny',
                'zh', 'zh-TW', 'co', 'hr', 'cs', 'da', 'nl', 'en', 'eo', 'et', 'tl', 'fi', 'fr', 'fy',
                'gl', 'ka', 'de', 'el', 'gu', 'ht', 'ha', 'haw', 'he', 'hi', 'hmn', 'hu', 'is', 'ig',
                'id', 'ga', 'it', 'ja', 'jw', 'kn', 'kk', 'km', 'rw', 'ko', 'ku', 'ky', 'lo', 'la',
                'lv', 'lt', 'lb', 'mk', 'mg', 'ms', 'ml', 'mt', 'mi', 'mr', 'mn', 'my', 'ne', 'no',
                'or', 'ps', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sm', 'gd', 'sr', 'st', 'sn', 'sd',
                'si', 'sk', 'sl', 'so', 'es', 'su', 'sw', 'sv', 'tg', 'ta', 'tt', 'te', 'th', 'tr',
                'tk', 'uk', 'ur', 'ug', 'uz', 'vi', 'cy', 'xh', 'yi', 'yo', 'zu'
            );
            $language = sanitize_text_field($input['alttext_language']);
            $sanitized['alttext_language'] = in_array($language, $allowed_languages) ? $language : 'en';
        }
        
        if (isset($input['alttext_max_length'])) {
            $max_length = intval($input['alttext_max_length']);
            $sanitized['alttext_max_length'] = ($max_length >= 50 && $max_length <= 300) ? $max_length : 125;
        }
        
        if (isset($input['alttext_style'])) {
            $allowed_styles = array('detailed', 'concise', 'functional');
            $style = sanitize_text_field($input['alttext_style']);
            $sanitized['alttext_style'] = in_array($style, $allowed_styles) ? $style : 'detailed';
        }
        
        return $sanitized;
    }
    
    /**
     * Add inline JavaScript for API key functionality
     * 
     * @since 1.0.0
     */
    private function api_key_inline_js() {
        $nonce = wp_create_nonce('w3a11y_validate_api_key');
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle API key visibility
            var toggleButton = document.getElementById('toggle-api-key');
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    var field = document.getElementById('api_key');
                    if (field) {
                        var type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                        field.setAttribute('type', type);
                    }
                });
            }
            
            // Validate API key
            var validateButton = document.getElementById('validate-api-key');
            if (validateButton) {
                validateButton.addEventListener('click', function() {
                    var apiKeyField = document.getElementById('api_key');
                    var resultDiv = document.getElementById('api-key-validation-result');
                    
                    if (!apiKeyField) {
                        console.error('API key field not found');
                        return;
                    }
                    
                    if (!resultDiv) {
                        console.error('Validation result div not found');
                        return;
                    }
                    
                    var apiKey = apiKeyField.value;
                    
                    if (!apiKey.trim()) {
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p><?php echo esc_js(__('Please enter an API key first.', 'w3a11y-artisan')); ?></p></div>';
                        return;
                    }
                    
                    validateButton.disabled = true;
                    validateButton.textContent = '<?php echo esc_js(__('Validating...', 'w3a11y-artisan')); ?>';
                    resultDiv.innerHTML = '<div class="notice notice-info inline"><p><?php echo esc_js(__('Validating API key...', 'w3a11y-artisan')); ?></p></div>';
                    
                    // Create form data
                    var formData = new FormData();
                    formData.append('action', 'w3a11y_validate_api_key');
                    formData.append('api_key', apiKey);
                    formData.append('nonce', '<?php echo esc_js($nonce); ?>');
                    
                    // Send AJAX request
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(response) {
                        validateButton.disabled = false;
                        validateButton.textContent = '<?php echo esc_js(__('Validate', 'w3a11y-artisan')); ?>';
                        
                        if (response.success) {
                            resultDiv.innerHTML = '<div class="notice notice-success inline"><p><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</p></div>';
                            
                            // Trigger custom event for other scripts
                            var event = new CustomEvent('w3a11y_api_validated', {
                                detail: { success: true, message: response.data.message }
                            });
                            document.dispatchEvent(event);
                        } else {
                            resultDiv.innerHTML = '<div class="notice notice-error inline"><p><span class="dashicons dashicons-no-alt"></span> ' + response.data.message + '</p></div>';
                        }
                    })
                    .catch(function() {
                        validateButton.disabled = false;
                        validateButton.textContent = '<?php echo esc_js(__('Validate', 'w3a11y-artisan')); ?>';
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p><?php echo esc_js(__('Validation request failed. Please try again.', 'w3a11y-artisan')); ?></p></div>';
                    });
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for API key validation
     * 
     * @since 1.0.0
     */
    public function ajax_validate_api_key() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'w3a11y_validate_api_key')) {
            wp_die(esc_html(__('Security check failed.', 'w3a11y-artisan')));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html(__('Insufficient permissions.', 'w3a11y-artisan'))));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required.', 'w3a11y-artisan')));
        }
        
        // Validate API key by making a test request with fixed endpoint
        $validation_result = $this->validate_api_credentials($api_key);
        
        if ($validation_result['valid']) {
            wp_send_json_success(array('message' => $validation_result['message']));
        } else {
            wp_send_json_error(array('message' => $validation_result['message']));
        }
    }
    
    /**
     * Validate API credentials by making a test request
     * 
     * @param string $api_key API key to validate.
     * @return array Validation result with 'valid' boolean and 'message' string.
     * @since 1.0.0
     */
    private function validate_api_credentials($api_key) {
        // Use centralized API configuration
        $test_url = w3a11y_artisan_get_api_url('artisan', 'credits');
        $config = w3a11y_get_api_config();
        
        $response = wp_remote_get($test_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => $config['user_agent']
            ),
            'timeout' => $config['timeout']
        ));
        
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                /* translators: %s: Error message from the API connection */
                'message' => sprintf(__('Connection failed: %s', 'w3a11y-artisan'), $response->get_error_message())
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            if (isset($data['success']) && $data['success']) {
                $credits = isset($data['available_credits']) ? $data['available_credits'] : 'unknown';
                return array(
                    'valid' => true,
                    /* translators: %s: Number of available API credits */
                    'message' => sprintf(__('API key is valid! Available credits: %s', 'w3a11y-artisan'), $credits)
                );
            }
        }
        
        // Handle specific error codes
        switch ($status_code) {
            case 401:
                return array(
                    'valid' => false,
                    'message' => __('Invalid API key. Please check your credentials.', 'w3a11y-artisan')
                );
            case 403:
                return array(
                    'valid' => false,
                    'message' => __('API key does not have permission to access Artisan services.', 'w3a11y-artisan')
                );
            case 404:
                return array(
                    'valid' => false,
                    'message' => __('API endpoint not found. Please check the endpoint URL.', 'w3a11y-artisan')
                );
            default:
                return array(
                    'valid' => false,
                    /* translators: %d: HTTP status code from API response */
                    'message' => sprintf(__('API validation failed (Status: %d). Please contact support.', 'w3a11y-artisan'), $status_code)
                );
        }
    }
    
    /**
     * Display admin notices
     * 
     * @since 1.0.0
     */
    public function admin_notices() {
        // Check if API key is configured
        $settings = W3A11Y_Artisan::get_settings();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter set by WordPress settings API
        if (empty($settings['api_key']) && !isset($_GET['settings-updated'])) {
            $this->show_api_key_notice();
        }
        
        // The centralized notification manager will handle other notices
        // including credit warnings and API errors
    }
    
    /**
     * Handle settings-specific notices
     * 
     * @since 1.0.0
     */
    public function settings_notices() {
        // Only show on our settings page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for page context
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'w3a11y-artisan-settings') {
            return;
        }
        
        // Check if we just updated settings
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter set by WordPress settings API
        if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated']))) {
            // Get any settings errors
            $errors = get_settings_errors('w3a11y_artisan_settings');
            
            if (!empty($errors)) {
                // There are errors, don't show the default success message
                foreach ($errors as $error) {
                    $type = $error['type'] === 'error' ? 'error' : 'success';
                    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
                    echo '<p>' . esc_html($error['message']) . '</p>';
                    echo '</div>';
                }
                
                // Remove the default WordPress "Settings saved" message by clearing the query var
                if (count($errors) > 0 && $errors[0]['type'] === 'error') {
                    wp_add_inline_style('wp-admin', '.notice-success { display: none !important; }');
                }
            }
        }
    }
    
    /**
     * Show API key configuration notice
     * 
     * @since 1.0.0
     */
    private function show_api_key_notice() {
        // Don't show on settings page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL parameter for page context
        if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'w3a11y-artisan-settings') {
            return;
        }
        
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings_url = admin_url('admin.php?page=w3a11y-artisan-settings');
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html(__('W3A11Y Artisan:', 'w3a11y-artisan')) . '</strong> ';
        echo wp_kses_post(sprintf(
            /* translators: %s: URL to the plugin settings page */
            __('Please <a href="%s">enter your W3A11Y API key</a> to enable image generation features.', 'w3a11y-artisan'),
            esc_url($settings_url)
        ));
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Handle activation redirect
     * 
     * @since 1.0.0
     */
    public function activation_redirect() {
        // Check for activation redirect transient
        if (get_transient('w3a11y_artisan_activation_redirect')) {
            delete_transient('w3a11y_artisan_activation_redirect');
            
            // Don't redirect if activating multiple plugins
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking WordPress bulk activation parameter
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=w3a11y-artisan-settings'));
                exit;
            }
        }
    }
}