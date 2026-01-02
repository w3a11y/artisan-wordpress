<?php
/**
 * W3A11Y Artisan Modal Template
 * 
 * This template creates the exact modal interface matching the provided design
 * from code.html for AI-powered image generation and editing.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get admin default settings
$w3a11y_settings = get_option('w3a11y_artisan_settings', array());
$w3a11y_default_aspect_ratio = isset($w3a11y_settings['default_aspect_ratio']) ? $w3a11y_settings['default_aspect_ratio'] : '1:1';
$w3a11y_default_style = isset($w3a11y_settings['default_style']) ? $w3a11y_settings['default_style'] : 'photorealistic';
?>

<!-- W3A11Y Artisan Modal -->
<div id="w3a11y-artisan-modal" class="w3a11y-modal" style="display: none;">
    <div class="w3a11y-modal-overlay"></div>
    <div class="w3a11y-modal-container">
        
        <!-- Modal Header -->
        <header class="w3a11y-modal-header">
            <div class="w3a11y-header-left">
                <svg class="w3a11y-logo" width="24" height="24" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path d="M44 4H30.6666V17.3334H17.3334V30.6666H4V44H44V4Z" fill="#137fec"></path>
                </svg>
                <h1 class="w3a11y-modal-title"><?php esc_html_e('W3A11Y Artisan Image Editor', 'w3a11y-artisan'); ?></h1>
            </div>
            <div class="w3a11y-header-controls">
                <button type="button" class="w3a11y-control-btn" id="w3a11y-help-btn" title="<?php esc_attr_e('Help', 'w3a11y-artisan'); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </button>
                <button type="button" class="w3a11y-control-btn" id="w3a11y-fullscreen-btn" title="<?php esc_attr_e('Fullscreen', 'w3a11y-artisan'); ?>">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
                <button type="button" class="w3a11y-control-btn" id="w3a11y-close-modal" title="<?php esc_attr_e('Close', 'w3a11y-artisan'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </header>
        
        <!-- Modal Content -->
        <div class="w3a11y-modal-content">
            
            <!-- Main Image Area -->
            <main class="w3a11y-main-image-area">
                <div class="w3a11y-image-container">
                    
                    <!-- Image Display -->
                    <div class="w3a11y-image-display" id="w3a11y-image-display">
                        <div class="w3a11y-image-placeholder" id="w3a11y-image-placeholder">
                            <span class="dashicons dashicons-format-image"></span>
                            <div class="w3a11y-placeholder-content">
                                <p><?php esc_html_e('Upload an image or enter a prompt to generate', 'w3a11y-artisan'); ?></p>
                            </div>
                        </div>
                        
                        <img id="w3a11y-main-image" class="w3a11y-main-image" alt="" style="display: none;" />
                        
                        <!-- Selection Box -->
                        <div id="w3a11y-selection-box" class="w3a11y-selection-box" style="display: none;"></div>
                        
                        <!-- Loading Overlay -->
                        <div id="w3a11y-loading-overlay" class="w3a11y-loading-overlay" style="display: none;">
                            <div class="w3a11y-spinner"></div>
                            <p id="w3a11y-loading-text"><?php esc_html_e('Generating image...', 'w3a11y-artisan'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Image Controls Overlay -->
                    <div class="w3a11y-image-controls">
                        
                        <!-- Undo Redo Controls -->
                        <div class="w3a11y-controls-undo-redo">
                            <button type="button" class="w3a11y-image-control-btn" id="w3a11y-undo-btn" title="<?php esc_attr_e('Undo', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-undo"></span>
                            </button>
                            <button type="button" class="w3a11y-image-control-btn" id="w3a11y-redo-btn" title="<?php esc_attr_e('Redo', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-redo"></span>
                            </button>
                        </div>
                        
                        <!-- Before/After Toggle -->
                        <div class="w3a11y-controls-before-after">
                            <div class="w3a11y-before-after-toggle">
                                <button type="button" class="w3a11y-toggle-btn active" id="w3a11y-after-btn"><?php esc_html_e('After', 'w3a11y-artisan'); ?></button>
                                <button type="button" class="w3a11y-toggle-btn" id="w3a11y-before-btn"><?php esc_html_e('Before', 'w3a11y-artisan'); ?></button>
                            </div>
                        </div>
                        
                        <?php /*
                        <!-- Edit an area Controls -->
                        <div class="w3a11y-controls-edit-area">
                            <button type="button" class="w3a11y-edit-area-btn" id="w3a11y-edit-area-btn">
                                <span class="dashicons dashicons-admin-customizer"></span>
                                <?php esc_html_e('Edit an Area', 'w3a11y-artisan'); ?>
                            </button>
                        </div>
                        */ ?>
                        
                        <!-- Zoom In Out Controls -->
                        <div class="w3a11y-controls-zoom-in-out">
                            <button type="button" class="w3a11y-zoom-btn" id="w3a11y-zoom-out-btn" title="<?php esc_attr_e('Zoom Out', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-minus"></span>
                            </button>
                            <button type="button" class="w3a11y-zoom-btn" id="w3a11y-zoom-in-btn" title="<?php esc_attr_e('Zoom In', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-plus"></span>
                            </button>
                        </div>
                        
                        <!-- Remove Background Control -->
                        <div class="w3a11y-controls-remove-bg">
                            <button type="button" class="w3a11y-remove-bg-btn" id="w3a11y-remove-bg-btn" title="<?php esc_attr_e('Remove Background', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-image-filter"></span>
                                <?php esc_html_e('Remove BG', 'w3a11y-artisan'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
            
            <!-- Sidebar -->
            <aside class="w3a11y-sidebar">
                <div class="w3a11y-sidebar-content">
                    
                    <!-- Prompt Section -->
                    <div class="w3a11y-prompt-section">
                        <label for="w3a11y-prompt" class="w3a11y-label"><?php esc_html_e('Your prompt', 'w3a11y-artisan'); ?></label>
                        <div class="w3a11y-prompt-container">
                            <textarea 
                                id="w3a11y-prompt" 
                                class="w3a11y-prompt-textarea" 
                                name="prompt" 
                                placeholder="<?php esc_attr_e('e.g., \'Change the sofa color to blue\'', 'w3a11y-artisan'); ?>" 
                                rows="4"
                            ></textarea>
                            <button type="button" class="w3a11y-voice-input-btn" id="w3a11y-voice-input" title="<?php esc_attr_e('Voice Input', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-microphone"></span>
                            </button>
                            <button type="button" class="w3a11y-attach-btn" id="w3a11y-attach-reference" title="<?php esc_attr_e('Attach Reference Images (max 3)', 'w3a11y-artisan'); ?>">
                                <span class="dashicons dashicons-paperclip"></span>
                            </button>
                            <input type="file" id="w3a11y-reference-file" accept="image/*" multiple style="display: none;" />
                        </div>
                        
                        <!-- Reference Images Preview -->
                        <div class="w3a11y-reference-preview-container" id="w3a11y-reference-preview-container" style="display: none;">
                            <div class="w3a11y-reference-preview-header">
                                <span class="w3a11y-reference-label"><?php esc_html_e('Reference Images:', 'w3a11y-artisan'); ?></span>
                                <span class="w3a11y-reference-count" id="w3a11y-reference-count">0/13</span>
                            </div>
                            <div class="w3a11y-reference-images" id="w3a11y-reference-images">
                                <!-- Reference image previews will be added here dynamically -->
                            </div>
                            <p class="w3a11y-help-text"><?php esc_html_e('Up to 13 total images: 6 for objects (high-fidelity), 5 for humans (character consistency).', 'w3a11y-artisan'); ?></p>
                        </div>
                        
                        <!-- Generation Options -->
                        <div class="w3a11y-generation-options">

                            <!-- Google Search Grounding -->
                            <div class="w3a11y-option-group">
                                <h3 class="w3a11y-section-title"><?php esc_html_e('Real-Time Information', 'w3a11y-artisan'); ?></h3>
                                <label class="w3a11y-checkbox-wrapper">
                                    <input type="checkbox" id="w3a11y-google-search-checkbox" />
                                    <span class="w3a11y-checkbox-custom"></span>
                                    <span class="w3a11y-checkbox-label">
                                        <span class="w3a11y-checkbox-icon">🌐</span>
                                        <?php esc_html_e('Enable Google Search', 'w3a11y-artisan'); ?>
                                    </span>
                                </label>
                                <p class="w3a11y-help-text"><?php esc_html_e('Use real-time data like weather, sports scores, stock charts, recent events etc. Best for prompts requiring current information.', 'w3a11y-artisan'); ?></p>
                            </div>
                            
                            <!-- Aspect Ratio Selection -->
                            <div class="w3a11y-option-group">
                                <h3 class="w3a11y-section-title"><?php esc_html_e('Aspect Ratio', 'w3a11y-artisan'); ?></h3>
                                <div class="w3a11y-option-buttons w3a11y-aspect-ratio-grid" id="w3a11y-aspect-ratio-options">
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '1:1') ? ' active' : ''; ?>" data-aspect="1:1" data-width-1k="1024" data-height-1k="1024" data-width-2k="2048" data-height-2k="2048" data-width-4k="4096" data-height-4k="4096">
                                        <span class="w3a11y-aspect-icon"><svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="1" y="1" width="20" height="20" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">1:1</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Square', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '2:3') ? ' active' : ''; ?>" data-aspect="2:3" data-width-1k="848" data-height-1k="1264" data-width-2k="1696" data-height-2k="2528" data-width-4k="3392" data-height-4k="5056">
                                        <span class="w3a11y-aspect-icon"><svg width="12" height="18" viewBox="0 0 12 18" fill="none"><rect x="1" y="1" width="10" height="16" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">2:3</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Portrait', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '3:2') ? ' active' : ''; ?>" data-aspect="3:2" data-width-1k="1264" data-height-1k="848" data-width-2k="2528" data-height-2k="1696" data-width-4k="5056" data-height-4k="3392">
                                        <span class="w3a11y-aspect-icon"><svg width="18" height="12" viewBox="0 0 18 12" fill="none"><rect x="1" y="1" width="16" height="10" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">3:2</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Landscape', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '3:4') ? ' active' : ''; ?>" data-aspect="3:4" data-width-1k="896" data-height-1k="1200" data-width-2k="1792" data-height-2k="2400" data-width-4k="3584" data-height-4k="4800">
                                        <span class="w3a11y-aspect-icon"><svg width="12" height="16" viewBox="0 0 12 16" fill="none"><rect x="1" y="1" width="10" height="14" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">3:4</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Classic Portrait', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '4:3') ? ' active' : ''; ?>" data-aspect="4:3" data-width-1k="1200" data-height-1k="896" data-width-2k="2400" data-height-2k="1792" data-width-4k="4800" data-height-4k="3584">
                                        <span class="w3a11y-aspect-icon"><svg width="18" height="14" viewBox="0 0 18 14" fill="none"><rect x="1" y="1" width="16" height="12" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">4:3</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Classic Landscape', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '4:5') ? ' active' : ''; ?>" data-aspect="4:5" data-width-1k="928" data-height-1k="1152" data-width-2k="1856" data-height-2k="2304" data-width-4k="3712" data-height-4k="4608">
                                        <span class="w3a11y-aspect-icon"><svg width="12" height="15" viewBox="0 0 12 15" fill="none"><rect x="1" y="1" width="10" height="13" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">4:5</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Social Portrait', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '5:4') ? ' active' : ''; ?>" data-aspect="5:4" data-width-1k="1152" data-height-1k="928" data-width-2k="2304" data-height-2k="1856" data-width-4k="4608" data-height-4k="3712">
                                        <span class="w3a11y-aspect-icon"><svg width="15" height="12" viewBox="0 0 15 12" fill="none"><rect x="1" y="1" width="13" height="10" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">5:4</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Social Landscape', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '9:16') ? ' active' : ''; ?>" data-aspect="9:16" data-width-1k="768" data-height-1k="1376" data-width-2k="1536" data-height-2k="2752" data-width-4k="3072" data-height-4k="5504">
                                        <span class="w3a11y-aspect-icon"><svg width="12" height="22" viewBox="0 0 12 22" fill="none"><rect x="1" y="1" width="10" height="20" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">9:16</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Mobile Portrait', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '16:9') ? ' active' : ''; ?>" data-aspect="16:9" data-width-1k="1376" data-height-1k="768" data-width-2k="2752" data-height-2k="1536" data-width-4k="5504" data-height-4k="3072">
                                        <span class="w3a11y-aspect-icon"><svg width="22" height="12" viewBox="0 0 22 12" fill="none"><rect x="1" y="1" width="20" height="10" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">16:9</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Widescreen', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_aspect_ratio === '21:9') ? ' active' : ''; ?>" data-aspect="21:9" data-width-1k="1584" data-height-1k="672" data-width-2k="3168" data-height-2k="1344" data-width-4k="6336" data-height-4k="2688">
                                        <span class="w3a11y-aspect-icon"><svg width="26" height="11" viewBox="0 0 26 11" fill="none"><rect x="1" y="1" width="24" height="9" stroke="#8E9099" stroke-width="2"></rect></svg></span>
                                        <span class="w3a11y-aspect-label">21:9</span>
                                        <span class="w3a11y-aspect-desc"><?php esc_html_e('Ultra Wide', 'w3a11y-artisan'); ?></span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Resolution Selection -->
                            <div class="w3a11y-option-group">
                                <h3 class="w3a11y-section-title"><?php esc_html_e('Resolution', 'w3a11y-artisan'); ?></h3>
                                <div class="w3a11y-option-buttons" id="w3a11y-resolution-options">
                                    <button type="button" class="w3a11y-option-btn active" data-resolution="1K">
                                        <span class="w3a11y-resolution-icon">📱</span>
                                        <span class="w3a11y-resolution-label">1K</span>
                                        <span class="w3a11y-resolution-desc"><?php esc_html_e('Standard', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn" data-resolution="2K">
                                        <span class="w3a11y-resolution-icon">💻</span>
                                        <span class="w3a11y-resolution-label">2K</span>
                                        <span class="w3a11y-resolution-desc"><?php esc_html_e('High', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn" data-resolution="4K">
                                        <span class="w3a11y-resolution-icon">🖥️</span>
                                        <span class="w3a11y-resolution-label">4K</span>
                                        <span class="w3a11y-resolution-desc"><?php esc_html_e('Ultra', 'w3a11y-artisan'); ?></span>
                                    </button>
                                </div>
                                <p class="w3a11y-help-text"><?php esc_html_e('Higher resolution images take longer to generate and use more credits.', 'w3a11y-artisan'); ?></p>
                            </div>
                            
                            <!-- Style Selection -->
                            <div class="w3a11y-option-group">
                                <h3 class="w3a11y-section-title"><?php esc_html_e('Style', 'w3a11y-artisan'); ?></h3>
                                <div class="w3a11y-option-buttons" id="w3a11y-style-options">
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'photorealistic') ? ' active' : ''; ?>" data-style="photorealistic">
                                        <span class="w3a11y-style-icon">📷</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Photorealistic', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'illustration') ? ' active' : ''; ?>" data-style="illustration">
                                        <span class="w3a11y-style-icon">🎨</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Illustration', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'artistic') ? ' active' : ''; ?>" data-style="artistic">
                                        <span class="w3a11y-style-icon">🖼️</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Artistic', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'minimalist') ? ' active' : ''; ?>" data-style="minimalist">
                                        <span class="w3a11y-style-icon">⚪</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Minimalist', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'product') ? ' active' : ''; ?>" data-style="product">
                                        <span class="w3a11y-style-icon">📦</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Product', 'w3a11y-artisan'); ?></span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn<?php echo ($w3a11y_default_style === 'logo') ? ' active' : ''; ?>" data-style="logo">
                                        <span class="w3a11y-style-icon">🏷️</span>
                                        <span class="w3a11y-style-label"><?php esc_html_e('Logo', 'w3a11y-artisan'); ?></span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Optimize & Convert Checkbox -->
                            <div class="w3a11y-option-group">
                                <label class="w3a11y-optimize-checkbox-label">
                                    <input type="checkbox" id="w3a11y-optimize-convert" class="w3a11y-optimize-checkbox" />
                                    <span class="w3a11y-optimize-text"><?php esc_html_e('Optimize & Convert Image', 'w3a11y-artisan'); ?></span>
                                </label>
                                <p class="w3a11y-help-text"><?php esc_html_e('Images are generated in PNG format. Enable this option to convert to JPEG or WebP, optimize quality settings, and reduce file size for faster loading.', 'w3a11y-artisan'); ?></p>
                            </div>
                            
                            <!-- Output Format Options -->
                            <div class="w3a11y-option-group w3a11y-optimization-option" id="w3a11y-format-group" style="display: none;">
                                <h3 class="w3a11y-section-title"><?php esc_html_e('Output Format', 'w3a11y-artisan'); ?></h3>
                                <div class="w3a11y-option-buttons" id="w3a11y-format-options">
                                    <button type="button" class="w3a11y-option-btn" data-format="png">
                                        <span class="w3a11y-format-icon">🖼️</span>
                                        <span class="w3a11y-format-label">PNG</span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn" data-format="jpeg">
                                        <span class="w3a11y-format-icon">📸</span>
                                        <span class="w3a11y-format-label">JPEG</span>
                                    </button>
                                    <button type="button" class="w3a11y-option-btn" data-format="webp">
                                        <span class="w3a11y-format-icon">🌐</span>
                                        <span class="w3a11y-format-label">WEBP</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Quality Control -->
                            <div class="w3a11y-option-group w3a11y-optimization-option" id="w3a11y-quality-group" style="display: none;">
                                <label class="w3a11y-option-label" for="w3a11y-quality-slider">
                                    <?php esc_html_e('Quality', 'w3a11y-artisan'); ?>: <span id="w3a11y-quality-value" class="w3a11y-quality-value">90</span>%
                                </label>
                                <div class="w3a11y-quality-slider-container">
                                    <input type="range" id="w3a11y-quality-slider" class="w3a11y-quality-slider" 
                                        min="50" max="100" value="90" step="5" />
                                    <div class="w3a11y-quality-labels">
                                        <span><?php esc_html_e('Lower size', 'w3a11y-artisan'); ?></span>
                                        <span><?php esc_html_e('Higher quality', 'w3a11y-artisan'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                        
                        <button type="button" class="w3a11y-generate-btn" id="w3a11y-generate-btn">
                            <span class="w3a11y-btn-text"><?php esc_html_e('Generate', 'w3a11y-artisan'); ?></span>
                            <span class="w3a11y-btn-spinner" style="display: none;">
                                <span class="w3a11y-spinner-small"></span>
                                <?php esc_html_e('Generating...', 'w3a11y-artisan'); ?>
                            </span>
                        </button>
                    </div>
                    
                    <!-- Inspiration Section -->
                    <div class="w3a11y-inspiration-section">
                        <h3 class="w3a11y-section-title"><?php esc_html_e('Inspiration', 'w3a11y-artisan'); ?></h3>
                        <div class="w3a11y-inspiration-content" id="w3a11y-inspiration-content">
                            <div class="w3a11y-inspiration-loading" id="w3a11y-inspiration-loading" style="display: none;">
                                <span class="w3a11y-spinner-small"></span>
                                <span><?php esc_html_e('Analyzing image...', 'w3a11y-artisan'); ?></span>
                            </div>
                            <div class="w3a11y-inspiration-tags" id="w3a11y-inspiration-tags">
                                <!-- AI-generated inspiration tags will be inserted here -->
                            </div>
                            <div class="w3a11y-inspiration-placeholder" id="w3a11y-inspiration-placeholder">
                                <p><?php esc_html_e('Upload or generate an image to get AI-powered inspiration suggestions', 'w3a11y-artisan'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- History Section -->
                    <div class="w3a11y-history-section">
                        <h3 class="w3a11y-section-title"><?php esc_html_e('History', 'w3a11y-artisan'); ?></h3>
                        <div class="w3a11y-history-content" id="w3a11y-history-content">
                            <div class="w3a11y-history-placeholder">
                                <p><?php esc_html_e('Your recent prompts will appear here', 'w3a11y-artisan'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <footer class="w3a11y-modal-footer">
                    <div class="w3a11y-credits-info">
                        <span id="w3a11y-credits-count"><?php esc_html_e('Loading credits...', 'w3a11y-artisan'); ?></span>
                        <a href="https://w3a11y.com/pricing" target="_blank" class="w3a11y-buy-credits"><?php esc_html_e('Buy more', 'w3a11y-artisan'); ?></a>
                    </div>
                    <div class="w3a11y-footer-actions">
                        <div class="w3a11y-footer-left">
                            <button type="button" class="w3a11y-revert-btn" id="w3a11y-revert-btn">
                                <span class="dashicons dashicons-image-rotate"></span>
                                <?php esc_html_e('Revert to Original', 'w3a11y-artisan'); ?>
                            </button>
                        </div>
                        <button type="button" class="w3a11y-apply-btn" id="w3a11y-apply-btn">
                            <span class="w3a11y-btn-text"><?php esc_html_e('Apply & Save', 'w3a11y-artisan'); ?></span>
                            <span class="w3a11y-btn-spinner" style="display: none;">
                                <span class="w3a11y-spinner-small"></span>
                                <?php esc_html_e('Saving...', 'w3a11y-artisan'); ?>
                            </span>
                        </button>
                    </div>
                </footer>
            </aside>
        </div>
    </div>
</div>

<!-- Save Image Options Modal -->
<div id="w3a11y-save-options-modal" class="w3a11y-modal w3a11y-save-modal" style="display: none;">
    <div class="w3a11y-modal-overlay"></div>
    <div class="w3a11y-save-modal-container">
        <div class="w3a11y-save-modal-header">
            <h2><?php esc_html_e('Save Image to Media Library', 'w3a11y-artisan'); ?></h2>
            <button type="button" class="w3a11y-control-btn" id="w3a11y-close-save-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="w3a11y-save-modal-content">
            <form id="w3a11y-save-form">
                <div class="w3a11y-form-group">
                    <label for="w3a11y-save-filename"><?php esc_html_e('Filename:', 'w3a11y-artisan'); ?></label>
                    <input type="text" id="w3a11y-save-filename" name="filename" class="w3a11y-input" />
                </div>
                <div class="w3a11y-form-group">
                    <label for="w3a11y-save-title"><?php esc_html_e('Title:', 'w3a11y-artisan'); ?></label>
                    <input type="text" id="w3a11y-save-title" name="title" class="w3a11y-input" />
                </div>
                <div class="w3a11y-form-group">
                    <label for="w3a11y-save-alt-text"><?php esc_html_e('Alt Text:', 'w3a11y-artisan'); ?></label>
                    <textarea id="w3a11y-save-alt-text" name="alt_text" class="w3a11y-input"></textarea>
                    <p class="w3a11y-help-text"><?php esc_html_e('Describe the image for accessibility', 'w3a11y-artisan'); ?></p>
                </div>
                <div class="w3a11y-form-group" id="w3a11y-replace-option" style="display: none;">
                    <label>
                        <input type="checkbox" id="w3a11y-replace-existing" name="replace_existing" />
                        <?php esc_html_e('Replace the original image', 'w3a11y-artisan'); ?>
                    </label>
                    <p class="w3a11y-help-text"><?php esc_html_e('This will overwrite the original file', 'w3a11y-artisan'); ?></p>
                </div>
            </form>
        </div>
        <div class="w3a11y-save-modal-footer">
            <button type="button" class="w3a11y-cancel-btn" id="w3a11y-cancel-save"><?php esc_html_e('Cancel', 'w3a11y-artisan'); ?></button>
            <button type="button" class="w3a11y-save-btn" id="w3a11y-confirm-save">
                <span class="w3a11y-btn-text"><?php esc_html_e('Save to Media Library', 'w3a11y-artisan'); ?></span>
                <span class="w3a11y-btn-spinner" style="display: none;">
                    <span class="w3a11y-spinner-small"></span>
                    <?php esc_html_e('Saving...', 'w3a11y-artisan'); ?>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Hidden Elements for Functionality -->
<div id="w3a11y-hidden-data" style="display: none;">
    <input type="hidden" id="w3a11y-current-image-base64" />
    <input type="hidden" id="w3a11y-original-image-base64" />
    <input type="hidden" id="w3a11y-reference-images-base64" />
    <input type="hidden" id="w3a11y-current-attachment-id" />
    <input type="hidden" id="w3a11y-modal-mode" value="generate" /> <!-- generate or edit -->
</div>

<?php
// Add inline script using wp_add_inline_script
$w3a11y_modal_script = "
// Initialize modal functionality when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the modal system
    if (typeof window.W3A11YArtisan === 'undefined') {
        window.W3A11YArtisan = {};
    }
    
    // Store modal state
    window.W3A11YArtisan.modalState = {
        isOpen: false,
        mode: 'generate', // 'generate' or 'edit'
        currentImage: null,
        originalImage: null,
        referenceImage: null,
        attachmentId: null,
        history: [],
        zoomLevel: 1,
        selectionArea: null,
        credits: null
    };
    
    // Modal will be fully initialized by the main JavaScript file
    console.log('W3A11Y Artisan modal template loaded');
});
";
wp_add_inline_script('w3a11y-artisan-inline', $w3a11y_modal_script);
?>