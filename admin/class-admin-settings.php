<?php
/**
 * Admin Settings Page
 *
 * @package LIFEAI_AITranslator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings page handler
 */
class LIFEAI_AITranslator_Admin_Settings {

    /**
     * Render settings page
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle form submission
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside save_settings()
        if (isset($_POST['lifeai_aitranslator_settings_nonce'])) {
            $this->save_settings();
        }

        $settings = get_option('lifeai_aitranslator_settings', array());
        $this->load_defaults($settings);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('lifeai_aitranslator_messages'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('lifeai_aitranslator_settings', 'lifeai_aitranslator_settings_nonce'); ?>

                <div class="lg-aitrans-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'lifegence-aitranslator'); ?></a>
                        <a href="#engine" class="nav-tab"><?php esc_html_e('Translation Engine', 'lifegence-aitranslator'); ?></a>
                        <a href="#cache" class="nav-tab"><?php esc_html_e('Cache', 'lifegence-aitranslator'); ?></a>
                        <a href="#advanced" class="nav-tab"><?php esc_html_e('Advanced', 'lifegence-aitranslator'); ?></a>
                    </nav>

                    <!-- General Tab -->
                    <div id="general" class="tab-content active">
                        <?php $this->render_general_settings($settings); ?>
                    </div>

                    <!-- Translation Engine Tab -->
                    <div id="engine" class="tab-content" style="display:none;">
                        <?php $this->render_engine_settings($settings); ?>
                    </div>

                    <!-- Cache Tab -->
                    <div id="cache" class="tab-content" style="display:none;">
                        <?php $this->render_cache_settings($settings); ?>
                    </div>

                    <!-- Advanced Tab -->
                    <div id="advanced" class="tab-content" style="display:none;">
                        <?php $this->render_advanced_settings($settings); ?>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'lifegence-aitranslator')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings($settings) {
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="enabled"><?php esc_html_e('Enable Translation', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="enabled" name="enabled" value="1" <?php checked($settings['enabled'], true); ?>>
                        <?php esc_html_e('Enable AI translation on your website', 'lifegence-aitranslator'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="default_language"><?php esc_html_e('Default Language', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="default_language" name="default_language" class="regular-text">
                        <?php foreach (LIFEAI_AITranslator::get_all_languages() as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['default_language'], $code); ?>>
                                <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('The original language of your website content', 'lifegence-aitranslator'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Supported Languages', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php esc_html_e('Supported Languages', 'lifegence-aitranslator'); ?></span></legend>
                        <?php
                        $supported = $settings['supported_languages'] ?? array();
                        foreach (LIFEAI_AITranslator::get_all_languages() as $code => $name):
                        ?>
                            <label style="display: inline-block; width: 200px; margin-bottom: 5px;">
                                <input type="checkbox" name="supported_languages[]" value="<?php echo esc_attr($code); ?>"
                                    <?php checked(in_array($code, $supported)); ?>>
                                <?php echo esc_html($name); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e('Select languages to enable for translation', 'lifegence-aitranslator'); ?></p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Custom Languages', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <?php $this->render_custom_languages_section(); ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Language Switcher', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <p><strong><?php esc_html_e('Display language switcher on your site:', 'lifegence-aitranslator'); ?></strong></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>
                            <strong><?php esc_html_e('Widget:', 'lifegence-aitranslator'); ?></strong>
                            <?php esc_html_e('Go to Appearance > Widgets and add "Lifegence Language Switcher"', 'lifegence-aitranslator'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Shortcode:', 'lifegence-aitranslator'); ?></strong>
                            <code>[lifeai_language_switcher]</code>
                            <br>
                            <span class="description">
                                <?php esc_html_e('Options:', 'lifegence-aitranslator'); ?>
                                <code>type="dropdown|list|flags"</code>,
                                <code>flags="yes|no"</code>,
                                <code>native_names="yes|no"</code>
                            </span>
                            <br>
                            <span class="description">
                                <?php esc_html_e('Example:', 'lifegence-aitranslator'); ?>
                                <code>[lifeai_language_switcher type="flags" flags="yes"]</code>
                            </span>
                        </li>
                    </ul>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render engine settings
     */
    private function render_engine_settings($settings) {
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="provider"><?php esc_html_e('Translation Provider', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="provider" name="provider" class="regular-text">
                        <option value="gemini" <?php selected($settings['provider'], 'gemini'); ?>>
                            <?php esc_html_e('Google Gemini (Recommended)', 'lifegence-aitranslator'); ?>
                        </option>
                        <option value="openai" <?php selected($settings['provider'], 'openai'); ?>>
                            <?php esc_html_e('OpenAI GPT (Premium Quality)', 'lifegence-aitranslator'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <!-- Gemini Settings -->
            <tr class="gemini-setting" style="display:none;">
                <th scope="row">
                    <label for="gemini_model"><?php esc_html_e('Gemini Model', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="gemini_model" name="gemini_model" class="regular-text">
                        <optgroup label="<?php esc_html_e('Latest Generation (3.0)', 'lifegence-aitranslator'); ?>">
                            <option value="gemini-3-flash" <?php selected($settings['model'], 'gemini-3-flash'); ?>>
                                <?php esc_html_e('Gemini 3 Flash - Frontier intelligence', 'lifegence-aitranslator'); ?>
                            </option>
                        </optgroup>
                        <optgroup label="<?php esc_html_e('Stable Generation (2.5)', 'lifegence-aitranslator'); ?>">
                            <option value="gemini-2.5-pro" <?php selected($settings['model'], 'gemini-2.5-pro'); ?>>
                                <?php esc_html_e('Gemini 2.5 Pro - Advanced reasoning', 'lifegence-aitranslator'); ?>
                            </option>
                            <option value="gemini-2.5-flash" <?php selected($settings['model'], 'gemini-2.5-flash'); ?>>
                                <?php esc_html_e('Gemini 2.5 Flash - Best value (Recommended)', 'lifegence-aitranslator'); ?>
                            </option>
                            <option value="gemini-2.5-flash-lite" <?php selected($settings['model'], 'gemini-2.5-flash-lite'); ?>>
                                <?php esc_html_e('Gemini 2.5 Flash-Lite - Ultra fast & low cost', 'lifegence-aitranslator'); ?>
                            </option>
                        </optgroup>
                    </select>
                </td>
            </tr>

            <tr class="gemini-setting" style="display:none;">
                <th scope="row">
                    <label for="gemini_api_key"><?php esc_html_e('Gemini API Key', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <input type="password" id="gemini_api_key" name="gemini_api_key"
                        value="" class="regular-text"
                        placeholder="<?php echo !empty($settings['gemini_api_key']) ? '••••••••••' : 'AIzaSy...'; ?>">
                    <button type="button" id="test-gemini-key" class="button"><?php esc_html_e('Test Connection', 'lifegence-aitranslator'); ?></button>
                    <div id="gemini-key-status"></div>
                    <p class="description">
                        <?php
                        /* translators: %s: URL to Google AI Studio */
                        printf(
                            esc_html__('Get your API key from ', 'lifegence-aitranslator') . '<a href="%s" target="_blank">Google AI Studio</a>',
                            esc_url('https://aistudio.google.com/app/apikey')
                        );
                        ?>
                        <br>
                        <?php if (!empty($settings['gemini_api_key'])): ?>
                            <strong style="color: green;">✓ <?php esc_html_e('API key is saved (encrypted). Leave blank to keep existing key.', 'lifegence-aitranslator'); ?></strong>
                        <?php else: ?>
                            <strong style="color: #d63638;"><?php esc_html_e('No API key saved. Please enter your API key.', 'lifegence-aitranslator'); ?></strong>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <!-- OpenAI Settings -->
            <tr class="openai-setting" style="display:none;">
                <th scope="row">
                    <label for="openai_model"><?php esc_html_e('OpenAI Model', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="openai_model" name="openai_model" class="regular-text">
                        <option value="gpt-4.1-mini" <?php selected($settings['model'], 'gpt-4.1-mini'); ?>>
                            <?php esc_html_e('GPT-4.1 Mini (Recommended)', 'lifegence-aitranslator'); ?>
                        </option>
                        <option value="gpt-4.1" <?php selected($settings['model'], 'gpt-4.1'); ?>>
                            <?php esc_html_e('GPT-4.1 (Highest Quality)', 'lifegence-aitranslator'); ?>
                        </option>
                        <option value="gpt-4o-mini" <?php selected($settings['model'], 'gpt-4o-mini'); ?>>
                            <?php esc_html_e('GPT-4o Mini (Budget)', 'lifegence-aitranslator'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr class="openai-setting" style="display:none;">
                <th scope="row">
                    <label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <input type="password" id="openai_api_key" name="openai_api_key"
                        value="" class="regular-text"
                        placeholder="<?php echo !empty($settings['openai_api_key']) ? '••••••••••' : 'sk-...'; ?>">
                    <button type="button" id="test-openai-key" class="button"><?php esc_html_e('Test Connection', 'lifegence-aitranslator'); ?></button>
                    <div id="openai-key-status"></div>
                    <p class="description">
                        <?php
                        /* translators: %s: URL to OpenAI Platform */
                        printf(
                            esc_html__('Get your API key from ', 'lifegence-aitranslator') . '<a href="%s" target="_blank">OpenAI Platform</a>',
                            esc_url('https://platform.openai.com/api-keys')
                        );
                        ?>
                        <br>
                        <?php if (!empty($settings['openai_api_key'])): ?>
                            <strong style="color: green;">✓ <?php esc_html_e('API key is saved (encrypted). Leave blank to keep existing key.', 'lifegence-aitranslator'); ?></strong>
                        <?php else: ?>
                            <strong style="color: #d63638;"><?php esc_html_e('No API key saved. Please enter your API key.', 'lifegence-aitranslator'); ?></strong>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="translation_quality"><?php esc_html_e('Translation Quality', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="translation_quality" name="translation_quality">
                        <option value="standard" <?php selected($settings['translation_quality'], 'standard'); ?>>
                            <?php esc_html_e('Standard (Faster)', 'lifegence-aitranslator'); ?>
                        </option>
                        <option value="high" <?php selected($settings['translation_quality'], 'high'); ?>>
                            <?php esc_html_e('High (Better Quality)', 'lifegence-aitranslator'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr class="openai-setting" style="display:none;">
                <th scope="row">
                    <label for="translation_temperature"><?php esc_html_e('Temperature', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <input type="range" id="translation_temperature" name="translation_temperature"
                        min="0" max="1" step="0.1" value="<?php echo esc_attr($settings['translation_temperature'] ?? 0.3); ?>">
                    <span id="temperature-value">0.3</span>
                    <p class="description"><?php esc_html_e('Lower = More consistent, Higher = More creative (0.3 recommended)', 'lifegence-aitranslator'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render cache settings
     */
    private function render_cache_settings($settings) {
        $cache = new LIFEAI_Translation_Cache();
        $stats = $cache->get_stats();
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="cache_enabled"><?php esc_html_e('Enable Cache', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="cache_enabled" name="cache_enabled" value="1" <?php checked($settings['cache_enabled'], true); ?>>
                        <?php esc_html_e('Cache translated content (Highly recommended)', 'lifegence-aitranslator'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Caching reduces API costs by 80-95%', 'lifegence-aitranslator'); ?></p>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row">
                    <label for="cache_ttl"><?php esc_html_e('Cache Duration', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="cache_ttl" name="cache_ttl">
                        <option value="3600" <?php selected($settings['cache_ttl'], 3600); ?>><?php esc_html_e('1 Hour', 'lifegence-aitranslator'); ?></option>
                        <option value="21600" <?php selected($settings['cache_ttl'], 21600); ?>><?php esc_html_e('6 Hours', 'lifegence-aitranslator'); ?></option>
                        <option value="43200" <?php selected($settings['cache_ttl'], 43200); ?>><?php esc_html_e('12 Hours', 'lifegence-aitranslator'); ?></option>
                        <option value="86400" <?php selected($settings['cache_ttl'], 86400); ?>><?php esc_html_e('24 Hours (Recommended)', 'lifegence-aitranslator'); ?></option>
                        <option value="259200" <?php selected($settings['cache_ttl'], 259200); ?>><?php esc_html_e('3 Days', 'lifegence-aitranslator'); ?></option>
                        <option value="604800" <?php selected($settings['cache_ttl'], 604800); ?>><?php esc_html_e('7 Days', 'lifegence-aitranslator'); ?></option>
                    </select>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row">
                    <label for="cache_backend"><?php esc_html_e('Cache Backend', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <select id="cache_backend" name="cache_backend">
                        <option value="transients" <?php selected($settings['cache_backend'], 'transients'); ?>>
                            <?php esc_html_e('WordPress Transients (Default)', 'lifegence-aitranslator'); ?>
                        </option>
                        <option value="redis" <?php selected($settings['cache_backend'], 'redis'); ?>>
                            <?php esc_html_e('Redis (High-traffic sites)', 'lifegence-aitranslator'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row">
                    <label><?php esc_html_e('Cache Statistics', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <p><strong><?php esc_html_e('Total Cached Items:', 'lifegence-aitranslator'); ?></strong> <?php echo esc_html($stats['total_keys']); ?></p>
                    <p><strong><?php esc_html_e('Total Size:', 'lifegence-aitranslator'); ?></strong> <?php echo esc_html(size_format($stats['total_size'])); ?></p>
                    <?php
                    $cache_version = get_option('lifeai_aitranslator_cache_version', 1);
                    ?>
                    <p><strong><?php esc_html_e('Cache Version:', 'lifegence-aitranslator'); ?></strong> <?php echo esc_html($cache_version); ?></p>
                    <p class="description">
                        <?php esc_html_e('Incrementing the cache version will invalidate all existing translations and force re-translation on next page load.', 'lifegence-aitranslator'); ?>
                    </p>
                    <button type="button" id="increment-cache-version" class="button button-secondary" style="margin-right: 10px;">
                        <?php esc_html_e('Increment Cache Version (Force Re-translate)', 'lifegence-aitranslator'); ?>
                    </button>
                    <button type="button" id="clear-cache" class="button">
                        <?php esc_html_e('Clear All Cache', 'lifegence-aitranslator'); ?>
                    </button>
                    <div id="cache-status"></div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render advanced settings
     */
    private function render_advanced_settings($settings) {
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="rate_limit_enabled"><?php esc_html_e('Rate Limiting', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="rate_limit_enabled" name="rate_limit_enabled" value="1" <?php checked($settings['rate_limit_enabled'], true); ?>>
                        <?php esc_html_e('Enable rate limiting', 'lifegence-aitranslator'); ?>
                    </label>
                </td>
            </tr>

            <tr class="rate-limit-option">
                <th scope="row">
                    <label for="rate_limit_per_hour"><?php esc_html_e('Requests per Hour', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <input type="number" id="rate_limit_per_hour" name="rate_limit_per_hour"
                        value="<?php echo esc_attr($settings['rate_limit_per_hour'] ?? 1000); ?>"
                        min="10" max="10000" class="small-text">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="monthly_budget_limit"><?php esc_html_e('Monthly Budget (USD)', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <input type="number" id="monthly_budget_limit" name="monthly_budget_limit"
                        value="<?php echo esc_attr($settings['monthly_budget_limit'] ?? 50); ?>"
                        min="0" max="10000" class="small-text">
                    <p class="description"><?php esc_html_e('Set to 0 to disable budget tracking', 'lifegence-aitranslator'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="auto_disable_on_budget"><?php esc_html_e('Auto-disable on Budget', 'lifegence-aitranslator'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="auto_disable_on_budget" name="auto_disable_on_budget" value="1" <?php checked($settings['auto_disable_on_budget'], true); ?>>
                        <?php esc_html_e('Switch to cache-only mode when budget is exceeded', 'lifegence-aitranslator'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified on the next line
        if (!isset($_POST['lifeai_aitranslator_settings_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lifeai_aitranslator_settings_nonce'])), 'lifeai_aitranslator_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = array();

        // General settings
        $settings['enabled'] = isset($_POST['enabled']);
        $settings['default_language'] = isset($_POST['default_language']) ? sanitize_text_field(wp_unslash($_POST['default_language'])) : 'en';
        $settings['supported_languages'] = isset($_POST['supported_languages']) ? array_map('sanitize_text_field', wp_unslash($_POST['supported_languages'])) : array();

        // Provider settings
        $settings['provider'] = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : 'gemini';

        // Model selection based on provider
        if ($settings['provider'] === 'gemini' && !empty($_POST['gemini_model'])) {
            $settings['model'] = sanitize_text_field(wp_unslash($_POST['gemini_model']));
        } elseif ($settings['provider'] === 'openai' && !empty($_POST['openai_model'])) {
            $settings['model'] = sanitize_text_field(wp_unslash($_POST['openai_model']));
        }

        // API keys - only update if non-empty value provided
        $key_manager = new LIFEAI_API_Key_Manager();
        $old_settings = get_option('lifeai_aitranslator_settings', array());

        // Handle Gemini API key
        if (!empty($_POST['gemini_api_key'])) {
            $gemini_key = wp_unslash($_POST['gemini_api_key']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API keys should not be sanitized
            $settings['gemini_api_key'] = $key_manager->encrypt_key($gemini_key);
            $settings['gemini_api_key_display'] = substr($gemini_key, 0, 10) . '...';
        } else {
            // Preserve existing encrypted key if no new key provided
            $settings['gemini_api_key'] = $old_settings['gemini_api_key'] ?? '';
            $settings['gemini_api_key_display'] = $old_settings['gemini_api_key_display'] ?? '';
        }

        // Handle OpenAI API key
        if (!empty($_POST['openai_api_key'])) {
            $openai_key = wp_unslash($_POST['openai_api_key']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API keys should not be sanitized
            $settings['openai_api_key'] = $key_manager->encrypt_key($openai_key);
            $settings['openai_api_key_display'] = substr($openai_key, 0, 10) . '...';
        } else {
            // Preserve existing encrypted key if no new key provided
            $settings['openai_api_key'] = $old_settings['openai_api_key'] ?? '';
            $settings['openai_api_key_display'] = $old_settings['openai_api_key_display'] ?? '';
        }

        // Quality settings
        $settings['translation_quality'] = isset($_POST['translation_quality']) ? sanitize_text_field(wp_unslash($_POST['translation_quality'])) : 'standard';
        $settings['translation_temperature'] = isset($_POST['translation_temperature']) ? floatval(wp_unslash($_POST['translation_temperature'])) : 0.3;

        // Cache settings
        $settings['cache_enabled'] = isset($_POST['cache_enabled']);
        $settings['cache_ttl'] = isset($_POST['cache_ttl']) ? intval(wp_unslash($_POST['cache_ttl'])) : 86400;
        $settings['cache_backend'] = isset($_POST['cache_backend']) ? sanitize_text_field(wp_unslash($_POST['cache_backend'])) : 'transients';

        // Advanced settings
        $settings['rate_limit_enabled'] = isset($_POST['rate_limit_enabled']);
        $settings['rate_limit_per_hour'] = intval($_POST['rate_limit_per_hour'] ?? 1000);
        $settings['monthly_budget_limit'] = floatval($_POST['monthly_budget_limit'] ?? 50);
        $settings['auto_disable_on_budget'] = isset($_POST['auto_disable_on_budget']);

        update_option('lifeai_aitranslator_settings', $settings);

        // Save custom languages
        $this->save_custom_languages();

        // Update .htaccess with rewrite rules
        $this->update_htaccess($settings);

        // Flush rewrite rules
        flush_rewrite_rules();

        add_settings_error(
            'lifeai_aitranslator_messages',
            'lifeai_aitranslator_message',
            __('Settings saved successfully.', 'lifegence-aitranslator'),
            'success'
        );
    }

    /**
     * Update .htaccess file with rewrite rules
     */
    private function update_htaccess($settings) {
        $htaccess_file = ABSPATH . '.htaccess';

        // Check if .htaccess is writable using WP_Filesystem
        if (!file_exists($htaccess_file) || !wp_is_writable($htaccess_file)) {
            add_settings_error(
                'lifeai_aitranslator_messages',
                'lifeai_aitranslator_htaccess_error',
                __('.htaccess file is not writable. Please check file permissions.', 'lifegence-aitranslator'),
                'warning'
            );
            return;
        }

        // Read current .htaccess content
        $htaccess_content = file_get_contents($htaccess_file);

        // Check if our rules already exist
        if (strpos($htaccess_content, '# BEGIN LG-AITranslator') !== false) {
            // Remove old rules first
            $htaccess_content = preg_replace(
                '/# BEGIN LG-AITranslator.*?# END LG-AITranslator\s*/s',
                '',
                $htaccess_content
            );
        }

        // Generate language pattern for rewrite rules
        $languages = $settings['supported_languages'] ?? array('en', 'ja', 'zh-CN', 'es', 'fr');
        $lang_pattern = implode('|', array_map('preg_quote', $languages));

        // Create rewrite rules
        $rewrite_rules = "# BEGIN Lifegence AITranslator\n";
        $rewrite_rules .= "<IfModule mod_rewrite.c>\n";
        $rewrite_rules .= "RewriteEngine On\n";
        $rewrite_rules .= "RewriteBase /\n";
        $rewrite_rules .= "RewriteRule ^($lang_pattern)(/(.*))?/?$ index.php?lang=\$1&lifeai_translated_path=\$3 [L,QSA]\n";
        $rewrite_rules .= "</IfModule>\n";
        $rewrite_rules .= "# END Lifegence AITranslator\n\n";

        // Prepend our rules to existing content
        $new_htaccess = $rewrite_rules . $htaccess_content;

        // Write updated content
        $result = file_put_contents($htaccess_file, $new_htaccess);

        if ($result !== false) {
            add_settings_error(
                'lifeai_aitranslator_messages',
                'lifeai_aitranslator_htaccess_success',
                __('. htaccess file updated with rewrite rules.', 'lifegence-aitranslator'),
                'info'
            );
        } else {
            add_settings_error(
                'lifeai_aitranslator_messages',
                'lifeai_aitranslator_htaccess_error',
                __('Failed to update .htaccess file.', 'lifegence-aitranslator'),
                'error'
            );
        }
    }

    /**
     * Load default values
     */
    private function load_defaults(&$settings) {
        $defaults = array(
            'enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'default_language' => 'en',
            'supported_languages' => array('en', 'ja', 'zh-CN', 'es', 'fr'),
            'cache_enabled' => true,
            'cache_ttl' => 86400,
            'cache_backend' => 'transients',
            'translation_quality' => 'standard',
            'translation_temperature' => 0.3,
            'rate_limit_enabled' => true,
            'rate_limit_per_hour' => 1000,
            'monthly_budget_limit' => 50,
            'auto_disable_on_budget' => false
        );

        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
    }

    /**
     * Render custom languages section
     */
    private function render_custom_languages_section() {
        $custom = LIFEAI_AITranslator::get_custom_languages();
        ?>
        <div class="lg-custom-languages">
            <div id="lg-custom-language-list" class="lg-custom-lang-list">
                <?php if (empty($custom)): ?>
                    <p class="description"><?php esc_html_e('No custom languages added yet.', 'lifegence-aitranslator'); ?></p>
                <?php else: ?>
                    <?php foreach ($custom as $code => $name): ?>
                        <div class="lg-custom-language-item" data-code="<?php echo esc_attr($code); ?>">
                            <input type="hidden" name="custom_language_codes[]" value="<?php echo esc_attr($code); ?>">
                            <input type="hidden" name="custom_language_names[]" value="<?php echo esc_attr($name); ?>">
                            <span class="lg-lang-display">
                                <strong><?php echo esc_html($name); ?></strong>
                                (<?php echo esc_html($code); ?>)
                            </span>
                            <button type="button" class="button lg-remove-language" data-code="<?php echo esc_attr($code); ?>">
                                <?php esc_html_e('Remove', 'lifegence-aitranslator'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="lg-add-language-form" style="margin-top: 15px;">
                <input type="text"
                    id="lg-new-lang-code"
                    class="regular-text"
                    placeholder="<?php esc_attr_e('Language code (e.g., tl, ms, fil)', 'lifegence-aitranslator'); ?>"
                    style="width: 200px; margin-right: 10px;">
                <input type="text"
                    id="lg-new-lang-name"
                    class="regular-text"
                    placeholder="<?php esc_attr_e('Language name (e.g., Tagalog)', 'lifegence-aitranslator'); ?>"
                    style="width: 200px; margin-right: 10px;">
                <button type="button" id="lg-add-language-btn" class="button">
                    <?php esc_html_e('Add Custom Language', 'lifegence-aitranslator'); ?>
                </button>
            </div>

            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e('Add custom languages not in the preset list. Use standard language codes (e.g., tl for Tagalog, ms for Malay).', 'lifegence-aitranslator'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save custom languages from POST data
     *
     * @return bool True on success
     */
    private function save_custom_languages() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in parent save_settings()
        $codes = isset($_POST['custom_language_codes']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_language_codes'])) : array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in parent save_settings()
        $names = isset($_POST['custom_language_names']) ? array_map('sanitize_text_field', wp_unslash($_POST['custom_language_names'])) : array();

        $custom_languages = array();

        foreach ($codes as $index => $code) {
            // Skip if no corresponding name
            if (!isset($names[$index])) {
                continue;
            }

            // Validate code
            if (!LIFEAI_AITranslator::validate_language_code($code)) {
                continue;
            }

            // Sanitize name
            $name = sanitize_text_field($names[$index]);

            // Skip if name is empty
            if (empty(trim($name))) {
                continue;
            }

            // Add to custom languages
            $custom_languages[$code] = $name;
        }

        return update_option('lifeai_aitranslator_custom_languages', $custom_languages);
    }

    /**
     * Get custom languages data in array format
     *
     * @return array
     */
    private function get_custom_languages_data() {
        $custom = LIFEAI_AITranslator::get_custom_languages();
        $data = array();

        foreach ($custom as $code => $name) {
            $data[] = array(
                'code' => $code,
                'name' => $name,
            );
        }

        return $data;
    }

    /**
     * AJAX handler for adding custom language
     */
    public function ajax_add_custom_language() {
        check_ajax_referer('lifeai_aitranslator_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'lifegence-aitranslator')));
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (empty($code) || empty($name)) {
            wp_send_json_error(array('message' => __('Code and name are required', 'lifegence-aitranslator')));
        }

        $result = LIFEAI_AITranslator::add_custom_language($code, $name);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Language added successfully', 'lifegence-aitranslator'),
                'code' => $code,
                'name' => $name,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to add language', 'lifegence-aitranslator')));
        }
    }

    /**
     * AJAX handler for removing custom language
     */
    public function ajax_remove_custom_language() {
        check_ajax_referer('lifeai_aitranslator_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'lifegence-aitranslator')));
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        if (empty($code)) {
            wp_send_json_error(array('message' => __('Code is required', 'lifegence-aitranslator')));
        }

        $result = LIFEAI_AITranslator::remove_custom_language($code);

        if ($result) {
            wp_send_json_success(array('message' => __('Language removed successfully', 'lifegence-aitranslator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove language', 'lifegence-aitranslator')));
        }
    }
}
