<?php
/**
 * Bulk AltText Generation Page Template
 * 
 * @package W3A11Y_Artisan
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current stats
$w3a11y_alttext_handler = W3A11Y_AltText_Handler::get_instance();
$w3a11y_stats = $w3a11y_alttext_handler->get_image_statistics(array('missing_alt_only' => false));

// Get current settings
$w3a11y_settings = get_option('w3a11y_artisan_settings', array());
?>

<div class="wrap w3a11y-alttext-page">
    
    <div class="w3a11y-bulk-container">
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 1.875rem; font-weight: bold; color: #1e293b; margin: 0 0 0.25rem 0;">W3A11Y AltText</h1>
            <p style="color: #64748b; margin: 0;">Bulk generate SEO-friendly alt text for your images.</p>
        </header>
        
        <!-- Statistics Dashboard -->
        <div class="w3a11y-stats-grid">
            <div class="w3a11y-stat-card stat-total">
                <div class="w3a11y-stat-icon">
                    <span class="dashicons dashicons-images-alt2"></span>
                </div>
                <div class="w3a11y-stat-content">
                    <div class="w3a11y-stat-label">Total Images</div>
                    <div class="w3a11y-stat-number" id="bulk-total-images"><?php echo number_format($w3a11y_stats['total_images']); ?></div>
                </div>
            </div>
            
            <div class="w3a11y-stat-card stat-missing">
                <div class="w3a11y-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="w3a11y-stat-content">
                    <div class="w3a11y-stat-label">Missing Alt Text</div>
                    <div class="w3a11y-stat-number" id="bulk-missing-alt">
                        <?php echo number_format($w3a11y_stats['missing_alt_text']); ?> 
                        <span style="font-size: 1rem; font-weight: normal; color: #64748b;" id="bulk-missing-percentage">(<?php echo esc_html($w3a11y_stats['missing_percentage']); ?>%)</span>
                    </div>
                </div>
            </div>
            
            <div class="w3a11y-stat-card stat-complete">
                <div class="w3a11y-stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="w3a11y-stat-content">
                    <div class="w3a11y-stat-label">With Alt Text</div>
                    <div class="w3a11y-stat-number" id="bulk-with-alt">
                        <?php echo number_format($w3a11y_stats['with_alt_text']); ?> 
                        <span style="font-size: 1rem; font-weight: normal; color: #64748b;">(<?php echo esc_html(100 - $w3a11y_stats['missing_percentage']); ?>%)</span>
                    </div>
                </div>
            </div>
            
            <div class="w3a11y-stat-card stat-credits">
                <div class="w3a11y-stat-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="w3a11y-stat-content">
                    <div class="w3a11y-stat-label">Available Credits</div>
                    <div class="w3a11y-stat-number" id="bulk-credits-available">
                        <span style="font-size: 1rem">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Processing Form -->
        <div id="bulk-processing-form">
            
            <?php /*
            <!-- Keywords Section -->
            <div class="w3a11y-form-section">
                <div class="w3a11y-section-header">
                    <span class="dashicons dashicons-admin-customizer w3a11y-section-icon"></span>
                    <h2 class="w3a11y-section-title">Keywords</h2>
                </div>
                
                <div class="w3a11y-grid-2">
                    <div>
                        <label class="w3a11y-form-label" for="keywords">Keywords (maximum of 6)</label>
                        <textarea class="w3a11y-textarea" id="keywords" name="keywords" rows="3" placeholder="e.g., SEO keywords, product features, winter boots, waterproof, comfortable, durable"></textarea>
                        <p class="w3a11y-help-text">Separate keywords with commas or one per line.</p>
                    </div>
                    <div>
                        <label class="w3a11y-form-label" for="negative-keywords">Negative Keywords</label>
                        <textarea class="w3a11y-textarea" id="negative-keywords" name="negative-keywords" rows="3" placeholder="e.g., cheap, old, outdated"></textarea>
                        <p class="w3a11y-help-text">Exclude these keywords from the generated alt text.</p>
                    </div>
                </div>
            </div>
            */ ?>
            
            <!-- Custom AI Instructions -->
            <div class="w3a11y-form-section">
                <div class="w3a11y-section-header">
                    <span class="dashicons dashicons-edit w3a11y-section-icon"></span>
                    <h2 class="w3a11y-section-title">Custom AI Instructions</h2>
                    <span class="w3a11y-optional-badge">Optional</span>
                </div>
                
                <div>
                    <textarea class="w3a11y-textarea" id="custom-instructions" name="custom-instructions" rows="4" placeholder="e.g., Always mention brand names when visible, Focus on emotional tone, Include technical details for screenshots..."><?php echo isset($settings['alttext_custom_instructions']) ? esc_textarea($settings['alttext_custom_instructions']) : ''; ?></textarea>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                        <p class="w3a11y-help-text" style="margin: 0;">Additional instructions for the AI to customize alt text generation.</p>
                        <p class="w3a11y-help-text" style="margin: 0;" id="instruction-count"><?php echo esc_html(isset($settings['alttext_custom_instructions']) ? strlen($settings['alttext_custom_instructions']) : 0); ?> / 500</p>
                    </div>
                </div>
            </div>
            
            <!-- Language Preferences -->
            <div class="w3a11y-form-section">
                <div class="w3a11y-section-header">
                    <span class="dashicons dashicons-translation w3a11y-section-icon"></span>
                    <h2 class="w3a11y-section-title">Language Preferences</h2>
                </div>
                
                <div class="w3a11y-grid-2">
                    <div>
                        <label class="w3a11y-form-label" for="alt-text-language">Alt Text Language</label>
                        <select class="w3a11y-select" id="alt-text-language" name="alt-text-language">
                                        <?php $w3a11y_saved_language = isset($w3a11y_settings['alttext_language']) ? $w3a11y_settings['alttext_language'] : 'en'; ?>
                                        <option value="af"<?php echo ($w3a11y_saved_language === 'af') ? ' selected' : ''; ?>>Afrikaans</option>
                                        <option value="sq"<?php echo ($w3a11y_saved_language === 'sq') ? ' selected' : ''; ?>>Albanian</option>
                                        <option value="am"<?php echo ($w3a11y_saved_language === 'am') ? ' selected' : ''; ?>>Amharic</option>
                                        <option value="ar"<?php echo ($w3a11y_saved_language === 'ar') ? ' selected' : ''; ?>>Arabic</option>
                                        <option value="hy"<?php echo ($w3a11y_saved_language === 'hy') ? ' selected' : ''; ?>>Armenian</option>
                                        <option value="az"<?php echo ($w3a11y_saved_language === 'az') ? ' selected' : ''; ?>>Azerbaijani</option>
                                        <option value="eu"<?php echo ($w3a11y_saved_language === 'eu') ? ' selected' : ''; ?>>Basque</option>
                                        <option value="be"<?php echo ($w3a11y_saved_language === 'be') ? ' selected' : ''; ?>>Belarusian</option>
                                        <option value="bn"<?php echo ($w3a11y_saved_language === 'bn') ? ' selected' : ''; ?>>Bengali</option>
                                        <option value="bs"<?php echo ($w3a11y_saved_language === 'bs') ? ' selected' : ''; ?>>Bosnian</option>
                                        <option value="bg"<?php echo ($w3a11y_saved_language === 'bg') ? ' selected' : ''; ?>>Bulgarian</option>
                                        <option value="ca"<?php echo ($w3a11y_saved_language === 'ca') ? ' selected' : ''; ?>>Catalan</option>
                                        <option value="ceb"<?php echo ($w3a11y_saved_language === 'ceb') ? ' selected' : ''; ?>>Cebuano</option>
                                        <option value="ny"<?php echo ($w3a11y_saved_language === 'ny') ? ' selected' : ''; ?>>Chichewa</option>
                                        <option value="zh"<?php echo ($w3a11y_saved_language === 'zh') ? ' selected' : ''; ?>>Chinese (Simplified)</option>
                                        <option value="zh-TW"<?php echo ($w3a11y_saved_language === 'zh-TW') ? ' selected' : ''; ?>>Chinese (Traditional)</option>
                                        <option value="co"<?php echo ($w3a11y_saved_language === 'co') ? ' selected' : ''; ?>>Corsican</option>
                                        <option value="hr"<?php echo ($w3a11y_saved_language === 'hr') ? ' selected' : ''; ?>>Croatian</option>
                                        <option value="cs"<?php echo ($w3a11y_saved_language === 'cs') ? ' selected' : ''; ?>>Czech</option>
                                        <option value="da"<?php echo ($w3a11y_saved_language === 'da') ? ' selected' : ''; ?>>Danish</option>
                                        <option value="nl"<?php echo ($w3a11y_saved_language === 'nl') ? ' selected' : ''; ?>>Dutch</option>
                                        <option value="en"<?php echo ($w3a11y_saved_language === 'en') ? ' selected' : ''; ?>>English</option>
                                        <option value="eo"<?php echo ($w3a11y_saved_language === 'eo') ? ' selected' : ''; ?>>Esperanto</option>
                                        <option value="et"<?php echo ($w3a11y_saved_language === 'et') ? ' selected' : ''; ?>>Estonian</option>
                                        <option value="tl"<?php echo ($w3a11y_saved_language === 'tl') ? ' selected' : ''; ?>>Filipino</option>
                                        <option value="fi"<?php echo ($w3a11y_saved_language === 'fi') ? ' selected' : ''; ?>>Finnish</option>
                                        <option value="fr"<?php echo ($w3a11y_saved_language === 'fr') ? ' selected' : ''; ?>>French</option>
                                        <option value="fy"<?php echo ($w3a11y_saved_language === 'fy') ? ' selected' : ''; ?>>Frisian</option>
                                        <option value="gl"<?php echo ($w3a11y_saved_language === 'gl') ? ' selected' : ''; ?>>Galician</option>
                                        <option value="ka"<?php echo ($w3a11y_saved_language === 'ka') ? ' selected' : ''; ?>>Georgian</option>
                                        <option value="de"<?php echo ($w3a11y_saved_language === 'de') ? ' selected' : ''; ?>>German</option>
                                        <option value="el"<?php echo ($w3a11y_saved_language === 'el') ? ' selected' : ''; ?>>Greek</option>
                                        <option value="gu"<?php echo ($w3a11y_saved_language === 'gu') ? ' selected' : ''; ?>>Gujarati</option>
                                        <option value="ht"<?php echo ($w3a11y_saved_language === 'ht') ? ' selected' : ''; ?>>Haitian Creole</option>
                                        <option value="ha"<?php echo ($w3a11y_saved_language === 'ha') ? ' selected' : ''; ?>>Hausa</option>
                                        <option value="haw"<?php echo ($w3a11y_saved_language === 'haw') ? ' selected' : ''; ?>>Hawaiian</option>
                                        <option value="he"<?php echo ($w3a11y_saved_language === 'he') ? ' selected' : ''; ?>>Hebrew</option>
                                        <option value="hi"<?php echo ($w3a11y_saved_language === 'hi') ? ' selected' : ''; ?>>Hindi</option>
                                        <option value="hmn"<?php echo ($w3a11y_saved_language === 'hmn') ? ' selected' : ''; ?>>Hmong</option>
                                        <option value="hu"<?php echo ($w3a11y_saved_language === 'hu') ? ' selected' : ''; ?>>Hungarian</option>
                                        <option value="is"<?php echo ($w3a11y_saved_language === 'is') ? ' selected' : ''; ?>>Icelandic</option>
                                        <option value="ig"<?php echo ($w3a11y_saved_language === 'ig') ? ' selected' : ''; ?>>Igbo</option>
                                        <option value="id"<?php echo ($w3a11y_saved_language === 'id') ? ' selected' : ''; ?>>Indonesian</option>
                                        <option value="ga"<?php echo ($w3a11y_saved_language === 'ga') ? ' selected' : ''; ?>>Irish</option>
                                        <option value="it"<?php echo ($w3a11y_saved_language === 'it') ? ' selected' : ''; ?>>Italian</option>
                                        <option value="ja"<?php echo ($w3a11y_saved_language === 'ja') ? ' selected' : ''; ?>>Japanese</option>
                                        <option value="jw"<?php echo ($w3a11y_saved_language === 'jw') ? ' selected' : ''; ?>>Javanese</option>
                                        <option value="kn"<?php echo ($w3a11y_saved_language === 'kn') ? ' selected' : ''; ?>>Kannada</option>
                                        <option value="kk"<?php echo ($w3a11y_saved_language === 'kk') ? ' selected' : ''; ?>>Kazakh</option>
                                        <option value="km"<?php echo ($w3a11y_saved_language === 'km') ? ' selected' : ''; ?>>Khmer</option>
                                        <option value="rw"<?php echo ($w3a11y_saved_language === 'rw') ? ' selected' : ''; ?>>Kinyarwanda</option>
                                        <option value="ko"<?php echo ($w3a11y_saved_language === 'ko') ? ' selected' : ''; ?>>Korean</option>
                                        <option value="ku"<?php echo ($w3a11y_saved_language === 'ku') ? ' selected' : ''; ?>>Kurdish (Kurmanji)</option>
                                        <option value="ky"<?php echo ($w3a11y_saved_language === 'ky') ? ' selected' : ''; ?>>Kyrgyz</option>
                                        <option value="lo"<?php echo ($w3a11y_saved_language === 'lo') ? ' selected' : ''; ?>>Lao</option>
                                        <option value="la"<?php echo ($w3a11y_saved_language === 'la') ? ' selected' : ''; ?>>Latin</option>
                                        <option value="lv"<?php echo ($w3a11y_saved_language === 'lv') ? ' selected' : ''; ?>>Latvian</option>
                                        <option value="lt"<?php echo ($w3a11y_saved_language === 'lt') ? ' selected' : ''; ?>>Lithuanian</option>
                                        <option value="lb"<?php echo ($w3a11y_saved_language === 'lb') ? ' selected' : ''; ?>>Luxembourgish</option>
                                        <option value="mk"<?php echo ($w3a11y_saved_language === 'mk') ? ' selected' : ''; ?>>Macedonian</option>
                                        <option value="mg"<?php echo ($w3a11y_saved_language === 'mg') ? ' selected' : ''; ?>>Malagasy</option>
                                        <option value="ms"<?php echo ($w3a11y_saved_language === 'ms') ? ' selected' : ''; ?>>Malay</option>
                                        <option value="ml"<?php echo ($w3a11y_saved_language === 'ml') ? ' selected' : ''; ?>>Malayalam</option>
                                        <option value="mt"<?php echo ($w3a11y_saved_language === 'mt') ? ' selected' : ''; ?>>Maltese</option>
                                        <option value="mi"<?php echo ($w3a11y_saved_language === 'mi') ? ' selected' : ''; ?>>Maori</option>
                                        <option value="mr"<?php echo ($w3a11y_saved_language === 'mr') ? ' selected' : ''; ?>>Marathi</option>
                                        <option value="mn"<?php echo ($w3a11y_saved_language === 'mn') ? ' selected' : ''; ?>>Mongolian</option>
                                        <option value="my"<?php echo ($w3a11y_saved_language === 'my') ? ' selected' : ''; ?>>Myanmar (Burmese)</option>
                                        <option value="ne"<?php echo ($w3a11y_saved_language === 'ne') ? ' selected' : ''; ?>>Nepali</option>
                                        <option value="no"<?php echo ($w3a11y_saved_language === 'no') ? ' selected' : ''; ?>>Norwegian</option>
                                        <option value="or"<?php echo ($w3a11y_saved_language === 'or') ? ' selected' : ''; ?>>Odia</option>
                                        <option value="ps"<?php echo ($w3a11y_saved_language === 'ps') ? ' selected' : ''; ?>>Pashto</option>
                                        <option value="fa"<?php echo ($w3a11y_saved_language === 'fa') ? ' selected' : ''; ?>>Persian</option>
                                        <option value="pl"<?php echo ($w3a11y_saved_language === 'pl') ? ' selected' : ''; ?>>Polish</option>
                                        <option value="pt"<?php echo ($w3a11y_saved_language === 'pt') ? ' selected' : ''; ?>>Portuguese</option>
                                        <option value="pa"<?php echo ($w3a11y_saved_language === 'pa') ? ' selected' : ''; ?>>Punjabi</option>
                                        <option value="ro"<?php echo ($w3a11y_saved_language === 'ro') ? ' selected' : ''; ?>>Romanian</option>
                                        <option value="ru"<?php echo ($w3a11y_saved_language === 'ru') ? ' selected' : ''; ?>>Russian</option>
                                        <option value="sm"<?php echo ($w3a11y_saved_language === 'sm') ? ' selected' : ''; ?>>Samoan</option>
                                        <option value="gd"<?php echo ($w3a11y_saved_language === 'gd') ? ' selected' : ''; ?>>Scots Gaelic</option>
                                        <option value="sr"<?php echo ($w3a11y_saved_language === 'sr') ? ' selected' : ''; ?>>Serbian</option>
                                        <option value="st"<?php echo ($w3a11y_saved_language === 'st') ? ' selected' : ''; ?>>Sesotho</option>
                                        <option value="sn"<?php echo ($w3a11y_saved_language === 'sn') ? ' selected' : ''; ?>>Shona</option>
                                        <option value="sd"<?php echo ($w3a11y_saved_language === 'sd') ? ' selected' : ''; ?>>Sindhi</option>
                                        <option value="si"<?php echo ($w3a11y_saved_language === 'si') ? ' selected' : ''; ?>>Sinhala</option>
                                        <option value="sk"<?php echo ($w3a11y_saved_language === 'sk') ? ' selected' : ''; ?>>Slovak</option>
                                        <option value="sl"<?php echo ($w3a11y_saved_language === 'sl') ? ' selected' : ''; ?>>Slovenian</option>
                                        <option value="so"<?php echo ($w3a11y_saved_language === 'so') ? ' selected' : ''; ?>>Somali</option>
                                        <option value="es"<?php echo ($w3a11y_saved_language === 'es') ? ' selected' : ''; ?>>Spanish</option>
                                        <option value="su"<?php echo ($w3a11y_saved_language === 'su') ? ' selected' : ''; ?>>Sundanese</option>
                                        <option value="sw"<?php echo ($w3a11y_saved_language === 'sw') ? ' selected' : ''; ?>>Swahili</option>
                                        <option value="sv"<?php echo ($w3a11y_saved_language === 'sv') ? ' selected' : ''; ?>>Swedish</option>
                                        <option value="tg"<?php echo ($w3a11y_saved_language === 'tg') ? ' selected' : ''; ?>>Tajik</option>
                                        <option value="ta"<?php echo ($w3a11y_saved_language === 'ta') ? ' selected' : ''; ?>>Tamil</option>
                                        <option value="tt"<?php echo ($w3a11y_saved_language === 'tt') ? ' selected' : ''; ?>>Tatar</option>
                                        <option value="te"<?php echo ($w3a11y_saved_language === 'te') ? ' selected' : ''; ?>>Telugu</option>
                                        <option value="th"<?php echo ($w3a11y_saved_language === 'th') ? ' selected' : ''; ?>>Thai</option>
                                        <option value="tr"<?php echo ($w3a11y_saved_language === 'tr') ? ' selected' : ''; ?>>Turkish</option>
                                        <option value="tk"<?php echo ($w3a11y_saved_language === 'tk') ? ' selected' : ''; ?>>Turkmen</option>
                                        <option value="uk"<?php echo ($w3a11y_saved_language === 'uk') ? ' selected' : ''; ?>>Ukrainian</option>
                                        <option value="ur"<?php echo ($w3a11y_saved_language === 'ur') ? ' selected' : ''; ?>>Urdu</option>
                                        <option value="ug"<?php echo ($w3a11y_saved_language === 'ug') ? ' selected' : ''; ?>>Uyghur</option>
                                        <option value="uz"<?php echo ($w3a11y_saved_language === 'uz') ? ' selected' : ''; ?>>Uzbek</option>
                                        <option value="vi"<?php echo ($w3a11y_saved_language === 'vi') ? ' selected' : ''; ?>>Vietnamese</option>
                                        <option value="cy"<?php echo ($w3a11y_saved_language === 'cy') ? ' selected' : ''; ?>>Welsh</option>
                                        <option value="xh"<?php echo ($w3a11y_saved_language === 'xh') ? ' selected' : ''; ?>>Xhosa</option>
                                        <option value="yi"<?php echo ($w3a11y_saved_language === 'yi') ? ' selected' : ''; ?>>Yiddish</option>
                                        <option value="yo"<?php echo ($w3a11y_saved_language === 'yo') ? ' selected' : ''; ?>>Yoruba</option>
                                        <option value="zu"<?php echo ($w3a11y_saved_language === 'zu') ? ' selected' : ''; ?>>Zulu</option>
                        </select>
                        <p class="w3a11y-help-text">
                            <span class="dashicons dashicons-info-outline w3a11y-help-icon"></span>
                            This will override your default language preference for this image only.
                        </p>
                    </div>
                    <div>
                        <label class="w3a11y-form-label" for="max-length">Maximum Length</label>
                        <div style="position: relative;">
                            <input class="w3a11y-input" id="max-length" name="max-length" type="number" value="<?php echo isset($w3a11y_settings['alttext_max_length']) ? intval($w3a11y_settings['alttext_max_length']) : 125; ?>" style="padding-right: 5rem;" />
                            <span style="position: absolute; top: 50%; right: 0.75rem; transform: translateY(-50%); font-size: 0.875rem; color: #64748b;">characters</span>
                        </div>
                        <p class="w3a11y-help-text">
                            <span class="dashicons dashicons-info-outline w3a11y-help-icon"></span>
                            To overwrite your default Maximum Length preference, you can make changes here.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Processing Options -->
            <div class="w3a11y-form-section">
                <div class="w3a11y-section-header">
                    <span class="dashicons dashicons-admin-settings w3a11y-section-icon"></span>
                    <h2 class="w3a11y-section-title">Processing Options</h2>
                </div>
                
                <div class="w3a11y-checkbox-group">
                    <label class="w3a11y-checkbox-item" for="overwrite-existing">
                        <input class="w3a11y-checkbox" id="overwrite-existing" type="checkbox" />
                        <span class="w3a11y-checkbox-label">Overwrite existing alt text</span>
                    </label>
                    <p class="w3a11y-checkbox-description">Enable this to replace any current alt text with newly generated text. Be cautious, this cannot be undone.</p>
                    
                    <label class="w3a11y-checkbox-item" for="only-attached">
                        <input class="w3a11y-checkbox" id="only-attached" type="checkbox" />
                        <span class="w3a11y-checkbox-label">Only attached images</span>
                    </label>
                    <p class="w3a11y-checkbox-description">Process only images that are attached to a post or page, ignoring unattached media library items.</p>
                    
                    <label class="w3a11y-checkbox-item" for="skip-processed">
                        <input class="w3a11y-checkbox" id="skip-processed" type="checkbox" checked />
                        <span class="w3a11y-checkbox-label">Skip previously processed</span>
                    </label>
                    <p class="w3a11y-checkbox-description">Avoid re-processing images that have been previously handled by W3A11Y Artisan to save time and resources.</p>
                </div>
            </div>
            
            <!-- Generate Button -->
            <button class="w3a11y-generate-btn" type="button" id="w3a11y-start-bulk">
                <span class="dashicons dashicons-update w3a11y-btn-icon"></span>
                Generate Alt Text
            </button>
        </div>

        <!-- Progress Container (Initially Hidden) -->
        <div id="bulk-progress-container" class="w3a11y-progress-container" style="display: none;">
            <div class="w3a11y-progress-header">
                <span class="dashicons dashicons-update w3a11y-progress-icon"></span>
                <h2 class="w3a11y-progress-title">Generating Alt Text...</h2>
            </div>
            <p class="w3a11y-progress-description" id="bulk-progress-status">Please keep this page open. The process may take a few minutes depending on the number of images.</p>
            
            <div class="w3a11y-progress-bar-container">
                <div class="w3a11y-progress-stats">
                    <span class="w3a11y-progress-stats-left">Progress</span>
                    <span class="w3a11y-progress-stats-right" id="bulk-progress-display">0 / 0 (0%)</span>
                </div>
                <div class="w3a11y-progress-bar-outer">
                    <div class="w3a11y-progress-bar-inner" id="bulk-main-progress" style="width: 0%"></div>
                </div>
                <p class="w3a11y-progress-time" id="bulk-time-remaining">Estimated time remaining: calculating...</p>
            </div>
            
            <button class="w3a11y-cancel-btn" type="button" id="w3a11y-cancel-bulk">
                <span class="dashicons dashicons-no w3a11y-btn-icon"></span>
                Cancel Generation
            </button>
        </div>


    </div>
</div>

<?php
// Enqueue inline script
$w3a11y_nonce = wp_create_nonce('w3a11y_artisan_nonce');
?>
<script type="text/javascript">
    // Pass configuration to JavaScript
    window.w3a11yAltTextAjax = {
        ajax_url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
        nonce: '<?php echo esc_js($w3a11y_nonce); ?>',
        batch_size: <?php echo esc_js($w3a11y_alttext_handler->batch_config['batch_size']); ?>,
        batch_delay: <?php echo esc_js($w3a11y_alttext_handler->batch_config['batch_delay_ms']); ?>,
        generating_text: '<?php echo esc_js(__('Generating...', 'w3a11y-artisan')); ?>',
        strings: {
            processing: '<?php echo esc_js(__('Processing...', 'w3a11y-artisan')); ?>',
            completed: '<?php echo esc_js(__('Completed!', 'w3a11y-artisan')); ?>',
            cancelled: '<?php echo esc_js(__('Cancelled', 'w3a11y-artisan')); ?>',
            error: '<?php echo esc_js(__('Error occurred', 'w3a11y-artisan')); ?>'
        }
    };
    
    // Character count for custom instructions
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('custom-instructions');
        const counter = document.getElementById('instruction-count');
        
        if (textarea && counter) {
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = length + ' / 500';
            });
        }

        // Initialize W3A11Y AltText functionality
        if (typeof W3A11YAltText !== 'undefined') {
            W3A11YAltText.init();
            
            // Initialize bulk processing if on bulk page  
            if (document.querySelector('.w3a11y-bulk-container')) {
                W3A11YAltText.initBulkProcessing();
            }
        }
    });
</script>