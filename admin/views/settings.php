<?php
/**
 * Admin settings view.
 *
 * @package WP_Artwork_Analyzer
 */

defined('ABSPATH') || exit;

$settings   = WPA_Art_Analyzer_Settings::get_all();
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'ai-provider';
$cf7_forms  = WPA_Art_Analyzer_CF7_Integration::get_forms_list();
$env_key    = WPA_Art_Analyzer_Settings::get_gemini_api_key();
$is_from_env = WPA_Art_Analyzer_Settings::is_api_key_from_env();

$vision_key = WPA_Art_Analyzer_Google_Vision_Search::get_api_key();
$is_vision_env = defined('WPA_GOOGLE_VISION_API_KEY') || defined('GOOGLE_VISION_API_KEY') || getenv('GOOGLE_VISION_API_KEY') || !empty($_ENV['GOOGLE_VISION_API_KEY']);

$active_rev_provider = WPA_Art_Analyzer_Reverse_Search_Factory::get_active_provider();
$active_web_provider = WPA_Art_Analyzer_Web_Research_Factory::get_active_provider();
?>

<div class="wrap wpa-admin-wrap">
    <h1><span class="dashicons dashicons-art" style="font-size: 32px; width: 32px; height: 32px; margin-right: 8px;"></span><?php esc_html_e('Artwork Analyzer Settings', 'wp-artwork-analyzer'); ?></h1>

    <?php settings_errors('wpa_messages'); ?>

    <nav class="nav-tab-wrapper wpa-nav-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=ai-provider')); ?>" class="nav-tab <?php echo $active_tab === 'ai-provider' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('AI Vision Provider', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=reverse-search')); ?>" class="nav-tab <?php echo $active_tab === 'reverse-search' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Reverse Image Search', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=web-research')); ?>" class="nav-tab <?php echo $active_tab === 'web-research' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Web Research', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=analysis')); ?>" class="nav-tab <?php echo $active_tab === 'analysis' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Analysis Prompt', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=cf7')); ?>" class="nav-tab <?php echo $active_tab === 'cf7' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Contact Form 7 & Shortcode', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=security')); ?>" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Security & Limits', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=storage')); ?>" class="nav-tab <?php echo $active_tab === 'storage' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Storage & Privacy', 'wp-artwork-analyzer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-artwork-analyzer&tab=diagnostics')); ?>" class="nav-tab <?php echo $active_tab === 'diagnostics' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Diagnostics & Testing', 'wp-artwork-analyzer'); ?></a>
    </nav>

    <div class="wpa-admin-container">
        <?php if ($active_tab !== 'diagnostics') : ?>
        <form method="post" action="">
            <?php wp_nonce_field('wpa_save_settings_nonce'); ?>
            <input type="hidden" name="wpa_save_settings" value="1" />

            <?php if ($active_tab === 'ai-provider') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ai_provider"><?php esc_html_e('AI Vision Provider', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <select name="ai_provider" id="ai_provider">
                                <option value="gemini" <?php selected($settings['ai_provider'], 'gemini'); ?>>Google Gemini</option>
                            </select>
                            <p class="description"><?php esc_html_e('Uses Google Gemini Vision with streaming SSE content generation.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gemini_api_key"><?php esc_html_e('Gemini API Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <?php if ($is_from_env) : ?>
                                <div class="wpa-notice-env">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php esc_html_e('Active API Key detected from environment (.env file / constant)', 'wp-artwork-analyzer'); ?>:</strong>
                                    <code><?php echo esc_html(substr($env_key, 0, 8) . '...' . substr($env_key, -8)); ?></code>
                                </div>
                            <?php endif; ?>
                            <input type="password" name="gemini_api_key" id="gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key']); ?>" class="regular-text" placeholder="<?php echo $is_from_env ? esc_attr__('Configured via .env (leave blank or enter custom override)', 'wp-artwork-analyzer') : 'AIza...'; ?>" />
                            <p class="description"><?php esc_html_e('Your Gemini API key remains strictly server-side and is never sent to the browser.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="model"><?php esc_html_e('Gemini Model', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <select name="model" id="model">
                                <option value="gemini-3.8-flash" <?php selected($settings['model'], 'gemini-3.8-flash'); ?>>gemini-3.8-flash (Recommended, Latest)</option>
                                <option value="gemini-3.5-flash-lite" <?php selected($settings['model'], 'gemini-3.5-flash-lite'); ?>>gemini-3.5-flash-lite (Fastest, High Availability)</option>
                                <option value="gemini-3.5-flash" <?php selected($settings['model'], 'gemini-3.5-flash'); ?>>gemini-3.5-flash (Balanced)</option>
                                <option value="gemini-3.6-flash" <?php selected($settings['model'], 'gemini-3.6-flash'); ?>>gemini-3.6-flash</option>
                                <option value="gemini-3.1-flash-lite" <?php selected($settings['model'], 'gemini-3.1-flash-lite'); ?>>gemini-3.1-flash-lite (Ultra Fast)</option>
                            </select>
                            <p class="description"><?php esc_html_e('Select which multimodal vision model processes the artwork.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="max_tokens"><?php esc_html_e('Max Output Tokens', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="number" name="max_tokens" id="max_tokens" value="<?php echo esc_attr($settings['max_tokens']); ?>" class="small-text" min="256" max="8192" step="128" />
                            <p class="description"><?php esc_html_e('Maximum tokens generated in the streaming response (default 2048).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="temperature"><?php esc_html_e('Temperature', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="number" name="temperature" id="temperature" value="<?php echo esc_attr($settings['temperature']); ?>" class="small-text" min="0.0" max="2.0" step="0.1" />
                            <p class="description"><?php esc_html_e('Controls creativity (default 0.4 for disciplined art critique).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'reverse-search') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="reverse_search_provider"><?php esc_html_e('Reverse Search Provider', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <select name="reverse_search_provider" id="reverse_search_provider">
                                <option value="google_vision" <?php selected($settings['reverse_search_provider'], 'google_vision'); ?>><?php esc_html_e('Google Cloud Vision (Web Detection) — Recommended', 'wp-artwork-analyzer'); ?></option>
                                <option value="serpapi" <?php selected($settings['reverse_search_provider'], 'serpapi'); ?>><?php esc_html_e('SerpApi (Google Lens / Reverse Search)', 'wp-artwork-analyzer'); ?></option>
                                <option value="none" <?php selected($settings['reverse_search_provider'], 'none'); ?>><?php esc_html_e('Disabled (Visual observation only)', 'wp-artwork-analyzer'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Searches the web for visually matching or identical artwork before AI critique.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="google_vision_api_key"><?php esc_html_e('Google Cloud Vision API Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <?php if ($is_vision_env) : ?>
                                <div class="wpa-notice-env">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <strong><?php esc_html_e('Active key detected from environment (.env / constant)', 'wp-artwork-analyzer'); ?>:</strong>
                                    <code><?php echo esc_html(substr($vision_key, 0, 8) . '...' . substr($vision_key, -8)); ?></code>
                                </div>
                            <?php endif; ?>
                            <input type="password" name="google_vision_api_key" id="google_vision_api_key" value="<?php echo esc_attr($settings['google_vision_api_key']); ?>" class="regular-text" placeholder="<?php echo $is_vision_env ? esc_attr__('Configured via .env (leave blank or override)', 'wp-artwork-analyzer') : esc_attr__('AIza... (or shares Gemini Cloud API key)', 'wp-artwork-analyzer'); ?>" />
                            <p class="description"><?php esc_html_e('Uses Cloud Vision Web Detection (1,000 free requests/month, base64 payload). If blank, automatically attempts to reuse your Google API key.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="serpapi_api_key"><?php esc_html_e('SerpApi Key (Optional)', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="password" name="serpapi_api_key" id="serpapi_api_key" value="<?php echo esc_attr($settings['serpapi_api_key']); ?>" class="regular-text" placeholder="api_key..." />
                            <p class="description"><?php esc_html_e('Required only if SerpApi is selected as your reverse search provider.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'web-research') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="web_research_provider"><?php esc_html_e('Web Research Provider', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <select name="web_research_provider" id="web_research_provider">
                                <option value="wikipedia" <?php selected($settings['web_research_provider'], 'wikipedia'); ?>><?php esc_html_e('Wikipedia & Wikimedia (Built-in, 100% Free, No Key Required)', 'wp-artwork-analyzer'); ?></option>
                                <option value="google_search" <?php selected($settings['web_research_provider'], 'google_search'); ?>><?php esc_html_e('Google Custom Search JSON API', 'wp-artwork-analyzer'); ?></option>
                                <option value="tavily" <?php selected($settings['web_research_provider'], 'tavily'); ?>><?php esc_html_e('Tavily AI Search API', 'wp-artwork-analyzer'); ?></option>
                                <option value="none" <?php selected($settings['web_research_provider'], 'none'); ?>><?php esc_html_e('Disabled', 'wp-artwork-analyzer'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Collects authoritative museum, artist, provenance, and art movement records based on reverse search entities.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="google_search_api_key"><?php esc_html_e('Google Custom Search API Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="password" name="google_search_api_key" id="google_search_api_key" value="<?php echo esc_attr($settings['google_search_api_key']); ?>" class="regular-text" placeholder="AIza..." />
                            <p class="description"><?php esc_html_e('Required if Google Custom Search is chosen (100 free queries/day).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="google_search_cx"><?php esc_html_e('Google Search Engine ID (cx)', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="text" name="google_search_cx" id="google_search_cx" value="<?php echo esc_attr($settings['google_search_cx']); ?>" class="regular-text" placeholder="0123456789..." />
                            <p class="description"><?php esc_html_e('Custom Search Engine ID from programmablesearchengine.google.com.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="tavily_api_key"><?php esc_html_e('Tavily API Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="password" name="tavily_api_key" id="tavily_api_key" value="<?php echo esc_attr($settings['tavily_api_key']); ?>" class="regular-text" placeholder="tvly-..." />
                            <p class="description"><?php esc_html_e('Required only if Tavily is selected as research provider.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'analysis') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="system_prompt"><?php esc_html_e('Master System Instruction', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <textarea name="system_prompt" id="system_prompt" rows="18" class="large-text code"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
                            <p class="description"><?php esc_html_e('Directs Gemini to structure critique into Category A (Visual Observations), Category B (Research-Supported Findings with source links), and Category C (Uncertain Conclusions).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="max_dimension"><?php esc_html_e('Max Image Dimension (px)', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="number" name="max_dimension" id="max_dimension" value="<?php echo esc_attr($settings['max_dimension']); ?>" class="small-text" min="512" max="4096" step="128" />
                            <p class="description"><?php esc_html_e('Uploaded images larger than this width/height are automatically downscaled before sending to Vision / Gemini to optimize latency and save bandwidth (default 2048px).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'cf7') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cf7_form_id"><?php esc_html_e('Target Contact Form 7 Form', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <?php if (empty($cf7_forms)) : ?>
                                <p class="description"><?php esc_html_e('No Contact Form 7 forms found. Please create a form in Contact Form 7 with an artwork file upload field.', 'wp-artwork-analyzer'); ?></p>
                            <?php else : ?>
                                <select name="cf7_form_id" id="cf7_form_id">
                                    <option value="0"><?php esc_html_e('All forms with artwork field', 'wp-artwork-analyzer'); ?></option>
                                    <?php foreach ($cf7_forms as $id => $title) : ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['cf7_form_id'], $id); ?>><?php echo esc_html($title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('The selected form will automatically have the artwork analyzer attached.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Shortcode Usage', 'wp-artwork-analyzer'); ?></th>
                        <td>
                            <code>[wp_artwork_analyzer]</code>
                            <p class="description"><?php esc_html_e('Renders the standalone artwork analyzer and launcher drawer on any page.', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'security') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="max_file_size"><?php esc_html_e('Max Upload File Size (MB)', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="number" name="max_file_size" id="max_file_size" value="<?php echo esc_attr($settings['max_file_size']); ?>" class="small-text" min="1" max="50" step="1" />
                            <p class="description"><?php esc_html_e('Maximum allowed artwork file upload size (default 10 MB).', 'wp-artwork-analyzer'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="rate_limit_requests"><?php esc_html_e('Rate Limit Requests', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="number" name="rate_limit_requests" id="rate_limit_requests" value="<?php echo esc_attr($settings['rate_limit_requests']); ?>" class="small-text" min="1" max="100" />
                            <?php esc_html_e('requests per', 'wp-artwork-analyzer'); ?>
                            <input type="number" name="rate_limit_window" id="rate_limit_window" value="<?php echo esc_attr($settings['rate_limit_window']); ?>" class="small-text" min="60" max="86400" step="60" />
                            <?php esc_html_e('seconds for anonymous visitors (Admins bypass rate limits).', 'wp-artwork-analyzer'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Cloudflare Turnstile', 'wp-artwork-analyzer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="turnstile_enabled" value="1" <?php checked($settings['turnstile_enabled'], 1); ?> />
                                <?php esc_html_e('Enable Cloudflare Turnstile bot protection', 'wp-artwork-analyzer'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="turnstile_site_key"><?php esc_html_e('Turnstile Site Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="text" name="turnstile_site_key" id="turnstile_site_key" value="<?php echo esc_attr($settings['turnstile_site_key']); ?>" class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="turnstile_secret_key"><?php esc_html_e('Turnstile Secret Key', 'wp-artwork-analyzer'); ?></label></th>
                        <td>
                            <input type="password" name="turnstile_secret_key" id="turnstile_secret_key" value="<?php echo esc_attr($settings['turnstile_secret_key']); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

            <?php elseif ($active_tab === 'storage') : ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Save Analysis to Database', 'wp-artwork-analyzer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="save_analysis" value="1" <?php checked($settings['save_analysis'], 1); ?> />
                                <?php esc_html_e('Save completed analyses to wp_artwork_analyses table (Default: Off for privacy)', 'wp-artwork-analyzer'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Save Uploaded Images', 'wp-artwork-analyzer'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="save_images" value="1" <?php checked($settings['save_images'], 1); ?> />
                                <?php esc_html_e('Retain uploaded artworks permanently in uploads/artwork-analyses (Default: Off; temporary files are deleted after analysis)', 'wp-artwork-analyzer'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php submit_button(__('Save Settings', 'wp-artwork-analyzer')); ?>
        </form>

        <?php else : ?>
            <!-- Diagnostics Tab -->
            <div class="wpa-diagnostics-container">
                <h2><?php esc_html_e('System Environment & API Diagnostics', 'wp-artwork-analyzer'); ?></h2>
                <p><?php esc_html_e('Verify that your WordPress environment and external AI/search APIs are operational.', 'wp-artwork-analyzer'); ?></p>

                <div class="wpa-diag-grid">
                    <div class="wpa-diag-card">
                        <div class="wpa-diag-card-title"><?php esc_html_e('PHP Environment', 'wp-artwork-analyzer'); ?></div>
                        <ul class="wpa-diag-list">
                            <li><span>PHP Version:</span> <strong><?php echo esc_html(PHP_VERSION); ?></strong> <span class="wpa-badge-ok">✓</span></li>
                            <li><span>cURL Extension:</span> <strong><?php echo function_exists('curl_version') ? 'Enabled' : 'Disabled'; ?></strong> <?php echo function_exists('curl_version') ? '<span class="wpa-badge-ok">✓</span>' : '<span class="wpa-badge-err">✕</span>'; ?></li>
                            <li><span>GD / Imagick:</span> <strong><?php echo (extension_loaded('gd') || extension_loaded('imagick')) ? 'Enabled' : 'Missing'; ?></strong> <span class="wpa-badge-ok">✓</span></li>
                            <li><span>Output Buffering:</span> <strong><?php echo (int)ini_get('output_buffering') > 0 ? 'Active (' . ini_get('output_buffering') . ')' : 'Off'; ?></strong></li>
                        </ul>
                    </div>

                    <div class="wpa-diag-card">
                        <div class="wpa-diag-card-title"><?php esc_html_e('AI & Research Providers', 'wp-artwork-analyzer'); ?></div>
                        <ul class="wpa-diag-list">
                            <li><span>Gemini Vision:</span> <strong><?php echo !empty($env_key) ? 'Configured' : 'Missing Key'; ?></strong> <?php echo !empty($env_key) ? '<span class="wpa-badge-ok">✓</span>' : '<span class="wpa-badge-err">✕</span>'; ?></li>
                            <li><span>Model:</span> <strong><?php echo esc_html($settings['model']); ?></strong></li>
                            <li><span>Reverse Search:</span> <strong><?php echo $active_rev_provider ? esc_html($active_rev_provider->get_name()) : 'Disabled'; ?></strong> <?php echo ($active_rev_provider && $active_rev_provider->is_configured()) ? '<span class="wpa-badge-ok">✓</span>' : ($active_rev_provider ? '<span class="wpa-badge-warn">⚠</span>' : ''); ?></li>
                            <li><span>Web Research:</span> <strong><?php echo $active_web_provider ? esc_html($active_web_provider->get_name()) : 'Disabled'; ?></strong> <?php echo ($active_web_provider && $active_web_provider->is_configured()) ? '<span class="wpa-badge-ok">✓</span>' : ($active_web_provider ? '<span class="wpa-badge-warn">⚠</span>' : ''); ?></li>
                        </ul>
                    </div>

                    <div class="wpa-diag-card">
                        <div class="wpa-diag-card-title"><?php esc_html_e('Upload & Execution Limits', 'wp-artwork-analyzer'); ?></div>
                        <ul class="wpa-diag-list">
                            <li><span>upload_max_filesize:</span> <strong><?php echo esc_html(ini_get('upload_max_filesize')); ?></strong></li>
                            <li><span>post_max_size:</span> <strong><?php echo esc_html(ini_get('post_max_size')); ?></strong></li>
                            <li><span>memory_limit:</span> <strong><?php echo esc_html(ini_get('memory_limit')); ?></strong></li>
                            <li><span>max_execution_time:</span> <strong><?php echo esc_html(ini_get('max_execution_time')); ?>s</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="wpa-test-actions-box">
                    <h3><?php esc_html_e('Interactive Diagnostics Tests', 'wp-artwork-analyzer'); ?></h3>
                    <div class="wpa-btn-group">
                        <button type="button" class="button button-primary" id="wpa-run-stream-test">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Test Streaming (Latency Test)', 'wp-artwork-analyzer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="wpa-run-gemini-test">
                            <span class="dashicons dashicons-cloud" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Test Gemini Connection', 'wp-artwork-analyzer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="wpa-run-reverse-search-test">
                            <span class="dashicons dashicons-format-image" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Test Reverse Search Provider', 'wp-artwork-analyzer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="wpa-run-web-research-test">
                            <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Test Web Research Provider', 'wp-artwork-analyzer'); ?>
                        </button>
                    </div>

                    <!-- Live Test Output Console -->
                    <div id="wpa-test-console" class="wpa-test-console" style="display:none;">
                        <div class="wpa-console-header">
                            <span><?php esc_html_e('Diagnostic Output Console', 'wp-artwork-analyzer'); ?></span>
                            <span id="wpa-console-status"></span>
                        </div>
                        <pre id="wpa-console-body"></pre>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
