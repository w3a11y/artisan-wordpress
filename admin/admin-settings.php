<?php
/**
 * Admin Settings Page Template
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$w3a11y_settings = W3A11Y_Artisan::get_settings();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <svg style="width: 24px; height: 24px; vertical-align: middle; margin-right: 8px;" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="#137fec"/>
        </svg>
        <?php esc_html_e('W3A11Y Artisan Settings', 'w3a11y-artisan'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Plugin Info Header -->
    <div class="notice notice-info" style="margin-bottom: 20px;">
        <h3 style="margin-top: 10px;"><?php esc_html_e('AI-Powered Image and Alt Text Generation for WordPress', 'w3a11y-artisan'); ?></h3>
        <p><?php esc_html_e('Transform your WordPress Media Library with cutting-edge AI image generation and editing capabilities. Create stunning images from simple text prompts or edit existing images with natural language instructions. Generate or update alt text effortlessly.', 'w3a11y-artisan'); ?></p>
        <p><strong><?php esc_html_e('Features:', 'w3a11y-artisan'); ?></strong></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php esc_html_e('Generate new images from text descriptions via text or voice input', 'w3a11y-artisan'); ?></li>
            <li><?php esc_html_e('Edit existing images consistently', 'w3a11y-artisan'); ?></li>
            <li><?php esc_html_e('Get AI-powered inspiration suggestions based on your images', 'w3a11y-artisan'); ?></li>
            <li><?php esc_html_e('One-click AI-generated alt text directly from Media Library and bulk generation support', 'w3a11y-artisan'); ?></li>
            <li><?php esc_html_e('Seamless integration with WordPress Media Library', 'w3a11y-artisan'); ?></li>
            <li><?php esc_html_e('Various aspect ratio and styles with optimization and convert options', 'w3a11y-artisan'); ?></li>
        </ul>
    </div>
    
    <?php
    // Show success message if settings were saved
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress settings API handles nonce verification via settings_fields()
    if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated']))) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html(__('Settings saved successfully!', 'w3a11y-artisan')) . '</strong></p></div>';
    }
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('w3a11y_artisan_settings_group');
        ?>
        
        <div class="w3a11y-settings-container">
            <div class="w3a11y-main-settings">
                <?php
                // Output all sections and fields
                global $wp_settings_sections, $wp_settings_fields;
                
                if (isset($wp_settings_sections['w3a11y-artisan-settings'])) {
                    foreach ((array) $wp_settings_sections['w3a11y-artisan-settings'] as $w3a11y_section) {
                        echo '<div class="w3a11y-settings-section">';
                        
                        if ($w3a11y_section['title']) {
                            echo '<h2>' . esc_html($w3a11y_section['title']) . "</h2>\n";
                        }
                        
                        if ($w3a11y_section['callback']) {
                            call_user_func($w3a11y_section['callback'], $w3a11y_section);
                        }
                        
                        if (isset($wp_settings_fields['w3a11y-artisan-settings'][$w3a11y_section['id']])) {
                            echo '<table class="form-table" role="presentation">';
                            do_settings_fields('w3a11y-artisan-settings', $w3a11y_section['id']);
                            echo '</table>';
                        }
                        
                        echo '</div>';
                    }
                }
                ?>
                
                <?php submit_button(__('Save Settings', 'w3a11y-artisan'), 'primary', 'submit', true, array('style' => 'margin-top: 20px;')); ?>
            </div>
            
            <div class="w3a11y-sidebar">
                <!-- Quick Start Guide -->
                <div class="w3a11y-sidebar-box">
                    <h3><?php esc_html_e('Quick Start Guide', 'w3a11y-artisan'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Enter your W3A11Y API key above and click "Validate"', 'w3a11y-artisan'); ?></li>
                        <li><?php esc_html_e('Save your settings', 'w3a11y-artisan'); ?></li>
                        <li><?php 
                        /* translators: %s: URL to the WordPress Media Library */
                        printf(wp_kses_post(__('Go to your <a href="%s">Media Library</a>', 'w3a11y-artisan')), esc_url(admin_url('upload.php'))); ?></li>
                        <li><?php esc_html_e('Click "Generate Image With W3A11Y Artisan" to start creating!', 'w3a11y-artisan'); ?></li>
                    </ol>
                </div>
                
                <!-- API Key Status -->
                <div class="w3a11y-sidebar-box">
                    <h3><?php esc_html_e('API Status', 'w3a11y-artisan'); ?></h3>
                    <div id="w3a11y-api-status">
                        <?php if (!empty($w3a11y_settings['api_key'])): ?>
                            <p style="color: #46b450;">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('API key configured', 'w3a11y-artisan'); ?>
                            </p>
                        <?php else: ?>
                            <p style="color: #dc3232;">
                                <span class="dashicons dashicons-no-alt"></span>
                                <?php esc_html_e('API key not configured', 'w3a11y-artisan'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Credits Info -->
                <div class="w3a11y-sidebar-box">
                    <h3><?php esc_html_e('Credits & Billing', 'w3a11y-artisan'); ?></h3>
                    <p><?php esc_html_e('Each image generation or editing operation uses credits from your W3A11Y account.', 'w3a11y-artisan'); ?></p>
                    <div id="w3a11y-credits-info">
                        <?php if (!empty($w3a11y_settings['api_key'])): ?>
                            <p><strong><?php esc_html_e('Available Credits:', 'w3a11y-artisan'); ?></strong> <em><?php esc_html_e('loading...', 'w3a11y-artisan'); ?></em></p>
                        <?php else: ?>
                            <p><em><?php esc_html_e('Configure API key to view credit balance', 'w3a11y-artisan'); ?></em></p>
                        <?php endif; ?>
                    </div>
                    <p>
                        <a href="https://w3a11y.com/pricing" target="_blank" class="button button-secondary">
                            <?php esc_html_e('View Pricing', 'w3a11y-artisan'); ?>
                        </a>
                    </p>
                </div>
                
                <!-- Help & Support -->
                <div class="w3a11y-sidebar-box">
                    <h3><?php esc_html_e('Help & Support', 'w3a11y-artisan'); ?></h3>
                    <p><?php esc_html_e('Need help getting started or have questions?', 'w3a11y-artisan'); ?></p>
                    <p>
                        <a href="https://w3a11y.com/docs/" target="_blank" class="button button-secondary">
                            <?php esc_html_e('View Documentation', 'w3a11y-artisan'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="https://w3a11y.com/contact" target="_blank" class="button button-secondary">
                            <?php esc_html_e('Get Support', 'w3a11y-artisan'); ?>
                        </a>
                    </p>
                </div>
                
                <!-- Plugin Info -->
                <div class="w3a11y-sidebar-box">
                    <h3><?php esc_html_e('Plugin Information', 'w3a11y-artisan'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Version:', 'w3a11y-artisan'); ?></strong></td>
                                <td><?php echo esc_html(W3A11Y_ARTISAN_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('WordPress:', 'w3a11y-artisan'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('PHP:', 'w3a11y-artisan'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
// Enqueue inline styles
$w3a11y_inline_style = "
.w3a11y-settings-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.w3a11y-main-settings {
    flex: 1;
}

.w3a11y-sidebar {
    width: 300px;
    flex-shrink: 0;
}

.w3a11y-sidebar-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.w3a11y-sidebar-box h3 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
}

.w3a11y-sidebar-box p {
    margin-bottom: 10px;
    font-size: 13px;
}

.w3a11y-sidebar-box ol {
    margin-left: 15px;
    font-size: 13px;
}

.w3a11y-sidebar-box ol li {
    margin-bottom: 5px;
}

.w3a11y-sidebar-box .button {
    font-size: 12px;
    padding: 6px 12px;
    height: auto;
}

.w3a11y-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.w3a11y-settings-section h2 {
    margin-top: 0;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Responsive design */
@media (max-width: 1200px) {
    .w3a11y-settings-container {
        flex-direction: column;
    }
    
    .w3a11y-sidebar {
        width: 100%;
    }
}
";
wp_add_inline_style('wp-admin', $w3a11y_inline_style);

// Add inline JavaScript using wp_add_inline_script
$w3a11y_settings_data = get_option('w3a11y_artisan_settings', array());
$w3a11y_nonce = wp_create_nonce('w3a11y_artisan_nonce');
$w3a11y_has_api_key = !empty($w3a11y_settings_data['api_key']);

$w3a11y_inline_js = "
document.addEventListener('DOMContentLoaded', function() {
    // Update API status when key is validated
    document.addEventListener('w3a11y_api_validated', function(e) {
        var data = e.detail;
        if (data.success) {
            var statusElement = document.getElementById('w3a11y-api-status');
            if (statusElement) {
                statusElement.innerHTML = 
                    '<p style=\"color: #46b450;\"><span class=\"dashicons dashicons-yes-alt\"></span> ' +
                    '" . esc_js(__('API key validated successfully', 'w3a11y-artisan')) . "</p>';
            }
            
            // Try to load credits info
            loadCreditsInfo();
        }
    });
    
    // Load credits info if API key is configured
    " . ($w3a11y_has_api_key ? "loadCreditsInfo();" : "") . "
    
    function loadCreditsInfo() {
        var formData = new FormData();
        formData.append('action', 'w3a11y_artisan_credits');
        formData.append('nonce', '" . esc_js($w3a11y_nonce) . "');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            var creditsElement = document.getElementById('w3a11y-credits-info');
            if (creditsElement) {
                if (response.success && response.data.credits !== undefined) {
                    creditsElement.innerHTML = 
                        '<p><strong>" . esc_js(__('Available Credits:', 'w3a11y-artisan')) . "</strong> ' + 
                        response.data.credits + '</p>';
                } else {
                    creditsElement.innerHTML = 
                        '<p><em>" . esc_js(__('Unable to load credit information', 'w3a11y-artisan')) . "</em></p>';
                }
            }
        })
        .catch(function() {
            var creditsElement = document.getElementById('w3a11y-credits-info');
            if (creditsElement) {
                creditsElement.innerHTML = 
                    '<p><em>" . esc_js(__('Unable to load credit information', 'w3a11y-artisan')) . "</em></p>';
            }
        });
    }
});
";
wp_add_inline_script('w3a11y-artisan-inline', $w3a11y_inline_js);