# AI Translation Engine - Admin Settings Configuration

## Overview

This document describes the admin settings panel for configuring AI translation engines (Gemini/OpenAI) in the GTranslate plugin. All AI-related settings are managed through the WordPress admin interface and stored in the WordPress options table.

## Admin Panel Location

**Path:** WordPress Admin → Settings → GTranslate

**Hook:** `add_options_page()` registered in `gtranslate.php:378`

**Function:** `GTranslate::options()` (gtranslate.php:379+)

## Settings Database Storage

### WordPress Options Table

All settings are stored in a single serialized option:

```php
// Option name: 'GTranslate'
$settings = get_option('GTranslate');

// Structure:
[
    // Existing settings (keep all current settings)
    'widget_look' => 'dropdown_with_flags',
    'default_language' => 'ja',
    'pro_version' => false,
    'enterprise_version' => false,
    // ... existing settings ...

    // NEW: AI Translation Engine Settings
    'ai_engine_enabled' => true,              // Enable AI translation
    'ai_engine_provider' => 'gemini',         // 'gemini' | 'openai' | 'google' (legacy)
    'ai_engine_model' => 'gemini-2.5-flash',  // Model selection

    // API Keys (encrypted)
    'gemini_api_key' => 'encrypted_key_here',
    'openai_api_key' => 'encrypted_key_here',

    // Translation Quality Settings
    'translation_quality' => 'standard',      // 'standard' | 'high'
    'translation_temperature' => 0.3,         // 0.0-1.0 for OpenAI

    // Cache Settings
    'cache_enabled' => true,
    'cache_ttl' => 86400,                     // 24 hours in seconds
    'cache_backend' => 'transients',          // 'transients' | 'redis' | 'memcached'

    // Redis Configuration (if cache_backend = 'redis')
    'redis_host' => '127.0.0.1',
    'redis_port' => 6379,
    'redis_password' => '',
    'redis_database' => 0,

    // Rate Limiting
    'rate_limit_enabled' => true,
    'rate_limit_per_hour' => 1000,
    'rate_limit_per_day' => 20000,

    // Cost Management
    'monthly_budget_limit' => 50,             // USD
    'cost_alert_threshold' => 80,             // Percentage
    'auto_disable_on_budget' => false,

    // Advanced Settings
    'html_translation_mode' => 'smart',       // 'smart' | 'full' | 'text_only'
    'preserve_html_attributes' => true,
    'batch_translation_size' => 50,
    'background_translation' => false,
    'fallback_to_cache' => true,
    'fallback_to_google' => false,            // Use Google Translate as fallback

    // Monitoring & Debugging
    'enable_debug_logging' => false,
    'log_api_calls' => false,
    'track_translation_quality' => false,
]
```

### Encryption for API Keys

**Implementation:**

```php
// File: includes/class-api-key-manager.php

class GTranslate_API_Key_Manager {
    private $encryption_key;

    public function __construct() {
        // Use WordPress AUTH_KEY as encryption base
        $this->encryption_key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }

    /**
     * Encrypt API key before storing
     */
    public function encrypt_key($plain_key) {
        if (empty($plain_key)) {
            return '';
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt(
            $plain_key,
            'aes-256-cbc',
            $this->encryption_key,
            0,
            $iv
        );

        // Combine IV and encrypted data
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt API key when retrieving
     */
    public function decrypt_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        $data = base64_decode($encrypted_key);
        list($iv, $encrypted) = explode('::', $data, 2);

        return openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $this->encryption_key,
            0,
            $iv
        );
    }

    /**
     * Validate API key by testing connection
     */
    public function validate_gemini_key($api_key) {
        $test_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

        $response = wp_remote_post($test_url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [
                    ['parts' => [['text' => 'Hello']]]
                ]
            ])
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'valid' => $code === 200,
            'error' => $code !== 200 ? 'Invalid API key or quota exceeded' : null
        ];
    }

    /**
     * Validate OpenAI API key
     */
    public function validate_openai_key($api_key) {
        $test_url = 'https://api.openai.com/v1/models';

        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ]
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'valid' => $code === 200,
            'error' => $code !== 200 ? 'Invalid API key' : null
        ];
    }
}
```

## Admin Panel UI Design

### Tab Structure

Add a new tab to the existing GTranslate settings page:

```
[Widget Options] [Translation Engine ⭐NEW] [Advanced] [Analytics ⭐NEW]
```

### Translation Engine Tab

**File to modify:** `gtranslate.php` (around line 913-1400)

**New HTML Section:**

```php
<div id="translation-engine-settings" class="postbox" style="display:none;">
    <h3><?php _e('AI Translation Engine Settings', 'gtranslate'); ?> ⭐</h3>
    <div class="inside">
        <table class="form-table" role="presentation">
            <!-- Engine Selection -->
            <tr>
                <th scope="row">
                    <label for="ai_engine_enabled">
                        <?php _e('AI Translation Engine', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="ai_engine_enabled" name="ai_engine_enabled" value="1" <?php checked($data['ai_engine_enabled'], true); ?>>
                        <?php _e('Enable AI-powered translation', 'gtranslate'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Use advanced AI models (Gemini/OpenAI) for higher quality translations. Requires API key.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Provider Selection -->
            <tr class="ai-engine-option">
                <th scope="row">
                    <label for="ai_engine_provider">
                        <?php _e('Translation Provider', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="ai_engine_provider" name="ai_engine_provider" style="width:300px;">
                        <option value="gemini" <?php selected($data['ai_engine_provider'], 'gemini'); ?>>
                            Google Gemini (Recommended - Lower cost)
                        </option>
                        <option value="openai" <?php selected($data['ai_engine_provider'], 'openai'); ?>>
                            OpenAI GPT (Premium - Best quality)
                        </option>
                        <option value="google" <?php selected($data['ai_engine_provider'], 'google'); ?>>
                            Google Translate (Legacy)
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Select your preferred AI translation provider.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Model Selection for Gemini -->
            <tr class="ai-engine-option gemini-option">
                <th scope="row">
                    <label for="ai_engine_model_gemini">
                        <?php _e('Gemini Model', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="ai_engine_model_gemini" name="ai_engine_model_gemini" style="width:300px;">
                        <option value="gemini-2.5-flash" <?php selected($data['ai_engine_model'], 'gemini-2.5-flash'); ?>>
                            Gemini 2.5 Flash (Best value) ⭐
                        </option>
                        <option value="gemini-2.5-pro" <?php selected($data['ai_engine_model'], 'gemini-2.5-pro'); ?>>
                            Gemini 2.5 Pro (Advanced reasoning)
                        </option>
                        <option value="gemini-2.5-flash-lite" <?php selected($data['ai_engine_model'], 'gemini-2.5-flash-lite'); ?>>
                            Gemini 2.5 Flash-Lite (Ultra fast & low cost)
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Gemini 2.5 Flash is recommended for most use cases (Free tier: 15 requests/min).', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Model Selection for OpenAI -->
            <tr class="ai-engine-option openai-option" style="display:none;">
                <th scope="row">
                    <label for="ai_engine_model_openai">
                        <?php _e('OpenAI Model', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="ai_engine_model_openai" name="ai_engine_model_openai" style="width:300px;">
                        <option value="gpt-4.1-mini" <?php selected($data['ai_engine_model'], 'gpt-4.1-mini'); ?>>
                            GPT-4.1 Mini (Recommended) ⭐
                        </option>
                        <option value="gpt-4.1" <?php selected($data['ai_engine_model'], 'gpt-4.1'); ?>>
                            GPT-4.1 (Highest quality)
                        </option>
                        <option value="gpt-4o-mini" <?php selected($data['ai_engine_model'], 'gpt-4o-mini'); ?>>
                            GPT-4o Mini (Budget option)
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('GPT-4.1 Mini offers the best quality/cost balance for translation.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Gemini API Key -->
            <tr class="ai-engine-option gemini-option">
                <th scope="row">
                    <label for="gemini_api_key">
                        <?php _e('Gemini API Key', 'gtranslate'); ?> *
                    </label>
                </th>
                <td>
                    <input type="password" id="gemini_api_key" name="gemini_api_key"
                           value="<?php echo esc_attr($data['gemini_api_key_display'] ?? ''); ?>"
                           class="regular-text" placeholder="AIzaSy...">
                    <button type="button" id="test_gemini_key" class="button" style="margin-left:10px;">
                        <?php _e('Test Connection', 'gtranslate'); ?>
                    </button>
                    <button type="button" id="show_gemini_key" class="button">
                        <?php _e('Show', 'gtranslate'); ?>
                    </button>
                    <div id="gemini_key_status" style="margin-top:8px;"></div>
                    <p class="description">
                        <?php _e('Get your API key from', 'gtranslate'); ?>:
                        <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
                        | <a href="#" id="gemini_setup_guide"><?php _e('Setup Guide', 'gtranslate'); ?></a>
                    </p>
                </td>
            </tr>

            <!-- OpenAI API Key -->
            <tr class="ai-engine-option openai-option" style="display:none;">
                <th scope="row">
                    <label for="openai_api_key">
                        <?php _e('OpenAI API Key', 'gtranslate'); ?> *
                    </label>
                </th>
                <td>
                    <input type="password" id="openai_api_key" name="openai_api_key"
                           value="<?php echo esc_attr($data['openai_api_key_display'] ?? ''); ?>"
                           class="regular-text" placeholder="sk-...">
                    <button type="button" id="test_openai_key" class="button" style="margin-left:10px;">
                        <?php _e('Test Connection', 'gtranslate'); ?>
                    </button>
                    <button type="button" id="show_openai_key" class="button">
                        <?php _e('Show', 'gtranslate'); ?>
                    </button>
                    <div id="openai_key_status" style="margin-top:8px;"></div>
                    <p class="description">
                        <?php _e('Get your API key from', 'gtranslate'); ?>:
                        <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                        | <a href="#" id="openai_setup_guide"><?php _e('Setup Guide', 'gtranslate'); ?></a>
                    </p>
                </td>
            </tr>

            <!-- Translation Quality -->
            <tr class="ai-engine-option">
                <th scope="row">
                    <label for="translation_quality">
                        <?php _e('Translation Quality', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="translation_quality" name="translation_quality">
                        <option value="standard" <?php selected($data['translation_quality'], 'standard'); ?>>
                            <?php _e('Standard (Faster, Lower cost)', 'gtranslate'); ?>
                        </option>
                        <option value="high" <?php selected($data['translation_quality'], 'high'); ?>>
                            <?php _e('High (Slower, Better quality)', 'gtranslate'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Standard mode is recommended for most sites. High quality uses more detailed prompts and context.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Temperature (OpenAI) -->
            <tr class="ai-engine-option openai-option" style="display:none;">
                <th scope="row">
                    <label for="translation_temperature">
                        <?php _e('Translation Temperature', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <input type="range" id="translation_temperature" name="translation_temperature"
                           min="0" max="1" step="0.1"
                           value="<?php echo esc_attr($data['translation_temperature'] ?? 0.3); ?>">
                    <span id="temperature_value">0.3</span>
                    <p class="description">
                        <?php _e('Lower = More consistent, Higher = More creative. Recommended: 0.3', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Divider -->
            <tr>
                <td colspan="2"><hr style="margin:20px 0;"></td>
            </tr>

            <!-- Cache Settings -->
            <tr>
                <th scope="row" colspan="2">
                    <h4 style="margin:0;"><?php _e('Cache Settings', 'gtranslate'); ?></h4>
                </th>
            </tr>

            <tr>
                <th scope="row">
                    <label for="cache_enabled">
                        <?php _e('Enable Cache', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="cache_enabled" name="cache_enabled" value="1"
                               <?php checked($data['cache_enabled'], true); ?>>
                        <?php _e('Cache translated pages (Highly recommended)', 'gtranslate'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Caching reduces API costs by 80-95%. Only disable for testing.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row">
                    <label for="cache_ttl">
                        <?php _e('Cache Duration', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="cache_ttl" name="cache_ttl">
                        <option value="3600" <?php selected($data['cache_ttl'], 3600); ?>>1 Hour</option>
                        <option value="21600" <?php selected($data['cache_ttl'], 21600); ?>>6 Hours</option>
                        <option value="43200" <?php selected($data['cache_ttl'], 43200); ?>>12 Hours</option>
                        <option value="86400" <?php selected($data['cache_ttl'], 86400); ?>>24 Hours ⭐</option>
                        <option value="259200" <?php selected($data['cache_ttl'], 259200); ?>>3 Days</option>
                        <option value="604800" <?php selected($data['cache_ttl'], 604800); ?>>7 Days</option>
                    </select>
                    <p class="description">
                        <?php _e('How long to store translated pages. Longer = Lower cost, but updates take longer to appear.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row">
                    <label for="cache_backend">
                        <?php _e('Cache Backend', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <select id="cache_backend" name="cache_backend">
                        <option value="transients" <?php selected($data['cache_backend'], 'transients'); ?>>
                            WordPress Transients (Default)
                        </option>
                        <option value="redis" <?php selected($data['cache_backend'], 'redis'); ?>>
                            Redis (Recommended for high-traffic sites)
                        </option>
                        <option value="memcached" <?php selected($data['cache_backend'], 'memcached'); ?>>
                            Memcached
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('WordPress Transients work for most sites. Use Redis/Memcached for >100k pageviews/month.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <!-- Redis Configuration -->
            <tr class="cache-option redis-option" style="display:none;">
                <th scope="row">
                    <label><?php _e('Redis Configuration', 'gtranslate'); ?></label>
                </th>
                <td>
                    <input type="text" name="redis_host" placeholder="127.0.0.1"
                           value="<?php echo esc_attr($data['redis_host'] ?? '127.0.0.1'); ?>"
                           style="width:150px;">
                    <label>Host</label>
                    <br>
                    <input type="number" name="redis_port" placeholder="6379"
                           value="<?php echo esc_attr($data['redis_port'] ?? 6379); ?>"
                           style="width:100px;">
                    <label>Port</label>
                    <br>
                    <input type="password" name="redis_password" placeholder="Password (optional)"
                           value="<?php echo esc_attr($data['redis_password'] ?? ''); ?>"
                           style="width:200px;">
                    <label>Password</label>
                    <br>
                    <button type="button" id="test_redis_connection" class="button" style="margin-top:8px;">
                        <?php _e('Test Redis Connection', 'gtranslate'); ?>
                    </button>
                    <div id="redis_status"></div>
                </td>
            </tr>

            <tr class="cache-option">
                <th scope="row"></th>
                <td>
                    <button type="button" id="clear_translation_cache" class="button">
                        <?php _e('Clear All Translation Cache', 'gtranslate'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Use this after updating content to force re-translation.', 'gtranslate'); ?>
                    </p>
                    <div id="cache_clear_status"></div>
                </td>
            </tr>

            <!-- Divider -->
            <tr>
                <td colspan="2"><hr style="margin:20px 0;"></td>
            </tr>

            <!-- Rate Limiting -->
            <tr>
                <th scope="row" colspan="2">
                    <h4 style="margin:0;"><?php _e('Rate Limiting & Cost Control', 'gtranslate'); ?></h4>
                </th>
            </tr>

            <tr>
                <th scope="row">
                    <label for="rate_limit_enabled">
                        <?php _e('Rate Limiting', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="rate_limit_enabled" name="rate_limit_enabled" value="1"
                               <?php checked($data['rate_limit_enabled'], true); ?>>
                        <?php _e('Enable rate limiting', 'gtranslate'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Prevents excessive API usage during traffic spikes.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <tr class="rate-limit-option">
                <th scope="row">
                    <label for="rate_limit_per_hour">
                        <?php _e('Hourly Limit', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" id="rate_limit_per_hour" name="rate_limit_per_hour"
                           value="<?php echo esc_attr($data['rate_limit_per_hour'] ?? 1000); ?>"
                           min="10" max="10000" style="width:100px;">
                    <?php _e('API calls per hour', 'gtranslate'); ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="monthly_budget_limit">
                        <?php _e('Monthly Budget', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" id="monthly_budget_limit" name="monthly_budget_limit"
                           value="<?php echo esc_attr($data['monthly_budget_limit'] ?? 50); ?>"
                           min="0" max="10000" style="width:100px;"> USD
                    <p class="description">
                        <?php _e('Set to 0 to disable budget tracking. You will receive alerts at 80% usage.', 'gtranslate'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="auto_disable_on_budget">
                        <?php _e('Auto-disable on Budget', 'gtranslate'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="auto_disable_on_budget" name="auto_disable_on_budget" value="1"
                               <?php checked($data['auto_disable_on_budget'], true); ?>>
                        <?php _e('Automatically switch to cache-only mode when budget is exceeded', 'gtranslate'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>
</div>
```

### Analytics Tab (NEW)

Monitor API usage, costs, and translation quality:

```php
<div id="translation-analytics" class="postbox">
    <h3><?php _e('Translation Analytics', 'gtranslate'); ?></h3>
    <div class="inside">
        <?php
        $stats = GTranslate_Analytics::get_monthly_stats();
        ?>

        <div class="gtranslate-stats-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:30px;">
            <!-- API Calls This Month -->
            <div class="stat-box" style="background:#f0f6fc; padding:20px; border-radius:8px;">
                <div style="font-size:32px; font-weight:bold; color:#0066cc;">
                    <?php echo number_format($stats['api_calls_month']); ?>
                </div>
                <div style="color:#666; margin-top:8px;">API Calls (This Month)</div>
                <div style="color:#999; font-size:12px; margin-top:4px;">
                    <?php echo number_format($stats['api_calls_today']); ?> today
                </div>
            </div>

            <!-- Estimated Cost -->
            <div class="stat-box" style="background:#fff4e6; padding:20px; border-radius:8px;">
                <div style="font-size:32px; font-weight:bold; color:#ff8c00;">
                    $<?php echo number_format($stats['estimated_cost'], 2); ?>
                </div>
                <div style="color:#666; margin-top:8px;">Estimated Cost (Month)</div>
                <div style="color:#999; font-size:12px; margin-top:4px;">
                    Budget: $<?php echo number_format($data['monthly_budget_limit'] ?? 50, 2); ?>
                </div>
            </div>

            <!-- Cache Hit Rate -->
            <div class="stat-box" style="background:#e8f5e9; padding:20px; border-radius:8px;">
                <div style="font-size:32px; font-weight:bold; color:#2e7d32;">
                    <?php echo number_format($stats['cache_hit_rate'], 1); ?>%
                </div>
                <div style="color:#666; margin-top:8px;">Cache Hit Rate</div>
                <div style="color:#999; font-size:12px; margin-top:4px;">
                    <?php echo number_format($stats['cache_hits']); ?> hits
                </div>
            </div>

            <!-- Avg Response Time -->
            <div class="stat-box" style="background:#fce4ec; padding:20px; border-radius:8px;">
                <div style="font-size:32px; font-weight:bold; color:#c2185b;">
                    <?php echo number_format($stats['avg_response_time'], 2); ?>s
                </div>
                <div style="color:#666; margin-top:8px;">Avg Response Time</div>
                <div style="color:#999; font-size:12px; margin-top:4px;">
                    Cached: <?php echo number_format($stats['cached_response_time'], 2); ?>s
                </div>
            </div>
        </div>

        <!-- Usage Chart -->
        <div style="margin-bottom:30px;">
            <h4><?php _e('API Usage (Last 30 Days)', 'gtranslate'); ?></h4>
            <canvas id="api-usage-chart" width="800" height="300"></canvas>
        </div>

        <!-- Top Translated Languages -->
        <div style="margin-bottom:30px;">
            <h4><?php _e('Most Requested Languages', 'gtranslate'); ?></h4>
            <table class="widefat" style="max-width:500px;">
                <thead>
                    <tr>
                        <th><?php _e('Language', 'gtranslate'); ?></th>
                        <th><?php _e('Requests', 'gtranslate'); ?></th>
                        <th><?php _e('Percentage', 'gtranslate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_languages'] as $lang => $count): ?>
                    <tr>
                        <td><?php echo esc_html(GTranslate::$lang_array[$lang] ?? $lang); ?></td>
                        <td><?php echo number_format($count); ?></td>
                        <td><?php echo number_format($count / $stats['api_calls_month'] * 100, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Translations -->
        <div>
            <h4><?php _e('Recent Translations', 'gtranslate'); ?></h4>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'gtranslate'); ?></th>
                        <th><?php _e('Page', 'gtranslate'); ?></th>
                        <th><?php _e('Language', 'gtranslate'); ?></th>
                        <th><?php _e('Provider', 'gtranslate'); ?></th>
                        <th><?php _e('Response Time', 'gtranslate'); ?></th>
                        <th><?php _e('Status', 'gtranslate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_translations'] as $trans): ?>
                    <tr>
                        <td><?php echo esc_html($trans['time']); ?></td>
                        <td><?php echo esc_html($trans['page']); ?></td>
                        <td><?php echo esc_html($trans['language']); ?></td>
                        <td><?php echo esc_html($trans['provider']); ?></td>
                        <td><?php echo esc_html($trans['response_time']); ?>s</td>
                        <td>
                            <span class="status-<?php echo esc_attr($trans['status']); ?>">
                                <?php echo esc_html($trans['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

## JavaScript for Admin Panel

**File:** `admin/js/gtranslate-admin.js` (NEW)

```javascript
jQuery(document).ready(function($) {
    // Show/hide options based on provider selection
    $('#ai_engine_provider').on('change', function() {
        const provider = $(this).val();

        $('.gemini-option, .openai-option').hide();

        if (provider === 'gemini') {
            $('.gemini-option').show();
        } else if (provider === 'openai') {
            $('.openai-option').show();
        }
    }).trigger('change');

    // Show/hide AI engine options
    $('#ai_engine_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('.ai-engine-option').show();
        } else {
            $('.ai-engine-option').hide();
        }
    }).trigger('change');

    // Show/hide cache options
    $('#cache_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('.cache-option').show();
        } else {
            $('.cache-option').hide();
        }
    }).trigger('change');

    // Show/hide Redis options
    $('#cache_backend').on('change', function() {
        if ($(this).val() === 'redis') {
            $('.redis-option').show();
        } else {
            $('.redis-option').hide();
        }
    }).trigger('change');

    // Temperature slider
    $('#translation_temperature').on('input', function() {
        $('#temperature_value').text($(this).val());
    });

    // Test Gemini API Key
    $('#test_gemini_key').on('click', function() {
        const $btn = $(this);
        const $status = $('#gemini_key_status');
        const apiKey = $('#gemini_api_key').val();

        if (!apiKey) {
            $status.html('<span style="color:red;">Please enter an API key</span>');
            return;
        }

        $btn.prop('disabled', true).text('Testing...');
        $status.html('<span style="color:#666;">Validating API key...</span>');

        $.post(ajaxurl, {
            action: 'gtranslate_test_gemini_key',
            api_key: apiKey,
            nonce: gtranslateAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Test Connection');

            if (response.success) {
                $status.html('<span style="color:green;">✓ API key is valid!</span>');
            } else {
                $status.html('<span style="color:red;">✗ ' + response.data.error + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Test Connection');
            $status.html('<span style="color:red;">Connection failed</span>');
        });
    });

    // Test OpenAI API Key
    $('#test_openai_key').on('click', function() {
        const $btn = $(this);
        const $status = $('#openai_key_status');
        const apiKey = $('#openai_api_key').val();

        if (!apiKey) {
            $status.html('<span style="color:red;">Please enter an API key</span>');
            return;
        }

        $btn.prop('disabled', true).text('Testing...');
        $status.html('<span style="color:#666;">Validating API key...</span>');

        $.post(ajaxurl, {
            action: 'gtranslate_test_openai_key',
            api_key: apiKey,
            nonce: gtranslateAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Test Connection');

            if (response.success) {
                $status.html('<span style="color:green;">✓ API key is valid!</span>');
            } else {
                $status.html('<span style="color:red;">✗ ' + response.data.error + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Test Connection');
            $status.html('<span style="color:red;">Connection failed</span>');
        });
    });

    // Show/Hide API Keys
    $('#show_gemini_key').on('click', function() {
        const $input = $('#gemini_api_key');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $input.attr('type', 'password');
            $(this).text('Show');
        }
    });

    $('#show_openai_key').on('click', function() {
        const $input = $('#openai_api_key');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $input.attr('type', 'password');
            $(this).text('Show');
        }
    });

    // Clear Translation Cache
    $('#clear_translation_cache').on('click', function() {
        if (!confirm('Are you sure you want to clear all translation cache? This will trigger re-translation of all pages.')) {
            return;
        }

        const $btn = $(this);
        const $status = $('#cache_clear_status');

        $btn.prop('disabled', true).text('Clearing...');

        $.post(ajaxurl, {
            action: 'gtranslate_clear_cache',
            nonce: gtranslateAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Clear All Translation Cache');

            if (response.success) {
                $status.html('<span style="color:green;">✓ Cache cleared successfully!</span>');
                setTimeout(() => $status.html(''), 3000);
            } else {
                $status.html('<span style="color:red;">✗ Failed to clear cache</span>');
            }
        });
    });

    // Test Redis Connection
    $('#test_redis_connection').on('click', function() {
        const $btn = $(this);
        const $status = $('#redis_status');

        $btn.prop('disabled', true).text('Testing...');

        $.post(ajaxurl, {
            action: 'gtranslate_test_redis',
            host: $('input[name="redis_host"]').val(),
            port: $('input[name="redis_port"]').val(),
            password: $('input[name="redis_password"]').val(),
            nonce: gtranslateAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Test Redis Connection');

            if (response.success) {
                $status.html('<span style="color:green;">✓ Connected to Redis!</span>');
            } else {
                $status.html('<span style="color:red;">✗ ' + response.data.error + '</span>');
            }
        });
    });

    // Setup guide modals
    $('#gemini_setup_guide').on('click', function(e) {
        e.preventDefault();
        showSetupGuide('gemini');
    });

    $('#openai_setup_guide').on('click', function(e) {
        e.preventDefault();
        showSetupGuide('openai');
    });

    function showSetupGuide(provider) {
        const guides = {
            gemini: `
                <h2>Gemini API Setup Guide</h2>
                <ol>
                    <li>Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                    <li>Sign in with your Google account</li>
                    <li>Click "Create API Key"</li>
                    <li>Select or create a Google Cloud project</li>
                    <li>Copy the generated API key (starts with "AIzaSy...")</li>
                    <li>Paste it in the field above and click "Test Connection"</li>
                </ol>
                <p><strong>Free Tier:</strong> 15 requests per minute, 1500 requests per day</p>
                <p><strong>Paid Tier:</strong> $0.075 per 1M input tokens, $0.30 per 1M output tokens</p>
            `,
            openai: `
                <h2>OpenAI API Setup Guide</h2>
                <ol>
                    <li>Go to <a href="https://platform.openai.com/signup" target="_blank">OpenAI Platform</a></li>
                    <li>Create an account or sign in</li>
                    <li>Navigate to <a href="https://platform.openai.com/api-keys" target="_blank">API Keys</a></li>
                    <li>Click "+ Create new secret key"</li>
                    <li>Copy the generated key (starts with "sk-...")</li>
                    <li>Paste it in the field above and click "Test Connection"</li>
                </ol>
                <p><strong>Note:</strong> OpenAI requires a paid account with billing information.</p>
                <p><strong>Pricing (GPT-4o-mini):</strong> $0.150 per 1M input tokens, $0.600 per 1M output tokens</p>
            `
        };

        const content = guides[provider];

        // Simple modal using WordPress ThickBox
        const modalHtml = `
            <div id="setup-guide-modal" style="display:none;">
                <div style="padding:20px;">
                    ${content}
                </div>
            </div>
        `;

        $('body').append(modalHtml);
        tb_show('API Setup Guide', '#TB_inline?inlineId=setup-guide-modal&width=600&height=450');
    }
});
```

## AJAX Handlers (PHP Backend)

**File:** `includes/class-admin-ajax-handlers.php` (NEW)

```php
<?php
class GTranslate_Admin_AJAX_Handlers {
    public function __construct() {
        add_action('wp_ajax_gtranslate_test_gemini_key', array($this, 'test_gemini_key'));
        add_action('wp_ajax_gtranslate_test_openai_key', array($this, 'test_openai_key'));
        add_action('wp_ajax_gtranslate_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_gtranslate_test_redis', array($this, 'test_redis'));
    }

    public function test_gemini_key() {
        check_ajax_referer('gtranslate_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        $key_manager = new GTranslate_API_Key_Manager();
        $result = $key_manager->validate_gemini_key($api_key);

        if ($result['valid']) {
            wp_send_json_success(['message' => 'API key is valid']);
        } else {
            wp_send_json_error(['error' => $result['error']]);
        }
    }

    public function test_openai_key() {
        check_ajax_referer('gtranslate_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
            return;
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        $key_manager = new GTranslate_API_Key_Manager();
        $result = $key_manager->validate_openai_key($api_key);

        if ($result['valid']) {
            wp_send_json_success(['message' => 'API key is valid']);
        } else {
            wp_send_json_error(['error' => $result['error']]);
        }
    }

    public function clear_cache() {
        check_ajax_referer('gtranslate_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
            return;
        }

        $cache = new GTranslate_Translation_Cache();
        $cache->clear_all();

        // Increment cache version to invalidate all cached translations
        $current_version = get_option('gtranslate_cache_version', 1);
        update_option('gtranslate_cache_version', $current_version + 1);

        wp_send_json_success(['message' => 'Cache cleared successfully']);
    }

    public function test_redis() {
        check_ajax_referer('gtranslate_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'Unauthorized']);
            return;
        }

        $host = sanitize_text_field($_POST['host']);
        $port = intval($_POST['port']);
        $password = sanitize_text_field($_POST['password']);

        try {
            $redis = new Redis();
            $connected = $redis->connect($host, $port, 2); // 2 second timeout

            if (!$connected) {
                wp_send_json_error(['error' => 'Could not connect to Redis server']);
                return;
            }

            if (!empty($password)) {
                if (!$redis->auth($password)) {
                    wp_send_json_error(['error' => 'Redis authentication failed']);
                    return;
                }
            }

            // Test write/read
            $redis->set('gtranslate_test', 'success');
            $test = $redis->get('gtranslate_test');
            $redis->del('gtranslate_test');

            if ($test === 'success') {
                wp_send_json_success(['message' => 'Connected to Redis successfully']);
            } else {
                wp_send_json_error(['error' => 'Redis read/write test failed']);
            }

        } catch (Exception $e) {
            wp_send_json_error(['error' => $e->getMessage()]);
        }
    }
}

// Initialize AJAX handlers
new GTranslate_Admin_AJAX_Handlers();
```

## Settings Save Handler

**Modification to:** `gtranslate.php` (around line 800-850)

```php
// Add to the settings save section
if (isset($_POST['ai_engine_enabled'])) {
    $key_manager = new GTranslate_API_Key_Manager();

    // Sanitize and save all new settings
    $data['ai_engine_enabled'] = isset($_POST['ai_engine_enabled']);
    $data['ai_engine_provider'] = sanitize_text_field($_POST['ai_engine_provider']);

    // Save appropriate model based on provider
    if ($data['ai_engine_provider'] === 'gemini') {
        $data['ai_engine_model'] = sanitize_text_field($_POST['ai_engine_model_gemini']);
    } elseif ($data['ai_engine_provider'] === 'openai') {
        $data['ai_engine_model'] = sanitize_text_field($_POST['ai_engine_model_openai']);
    }

    // Encrypt and save API keys
    if (!empty($_POST['gemini_api_key'])) {
        $data['gemini_api_key'] = $key_manager->encrypt_key($_POST['gemini_api_key']);
        $data['gemini_api_key_display'] = substr($_POST['gemini_api_key'], 0, 10) . '...';
    }

    if (!empty($_POST['openai_api_key'])) {
        $data['openai_api_key'] = $key_manager->encrypt_key($_POST['openai_api_key']);
        $data['openai_api_key_display'] = substr($_POST['openai_api_key'], 0, 10) . '...';
    }

    $data['translation_quality'] = sanitize_text_field($_POST['translation_quality']);
    $data['translation_temperature'] = floatval($_POST['translation_temperature']);

    $data['cache_enabled'] = isset($_POST['cache_enabled']);
    $data['cache_ttl'] = intval($_POST['cache_ttl']);
    $data['cache_backend'] = sanitize_text_field($_POST['cache_backend']);

    $data['redis_host'] = sanitize_text_field($_POST['redis_host']);
    $data['redis_port'] = intval($_POST['redis_port']);
    $data['redis_password'] = sanitize_text_field($_POST['redis_password']);

    $data['rate_limit_enabled'] = isset($_POST['rate_limit_enabled']);
    $data['rate_limit_per_hour'] = intval($_POST['rate_limit_per_hour']);
    $data['monthly_budget_limit'] = floatval($_POST['monthly_budget_limit']);
    $data['auto_disable_on_budget'] = isset($_POST['auto_disable_on_budget']);

    update_option('GTranslate', $data);
}
```

## Usage in Translation Services

**Retrieving settings in translation service classes:**

```php
// File: includes/class-gemini-translate-service.php

class GTranslate_Gemini_Service implements GTranslate_Translation_Service_Interface {
    private $api_key;
    private $model;
    private $quality;

    public function __construct() {
        $settings = get_option('GTranslate');
        $key_manager = new GTranslate_API_Key_Manager();

        // Decrypt API key
        $this->api_key = $key_manager->decrypt_key($settings['gemini_api_key'] ?? '');
        $this->model = $settings['ai_engine_model'] ?? 'gemini-2.5-flash';
        $this->quality = $settings['translation_quality'] ?? 'standard';

        if (empty($this->api_key)) {
            throw new Exception('Gemini API key not configured');
        }
    }

    public function translate_text($text, $source_lang, $target_lang) {
        // Use $this->api_key, $this->model, $this->quality
        // ... implementation ...
    }
}
```

## Summary

### Settings Storage
- All settings in single WordPress option: `GTranslate`
- API keys encrypted using OpenSSL AES-256-CBC
- Encryption key derived from WordPress AUTH_KEY

### Admin UI Features
1. **Translation Engine Tab**
   - Provider selection (Gemini/OpenAI/Google)
   - Model selection per provider
   - API key input with test/validation
   - Quality settings
   - Cache configuration
   - Rate limiting & budget controls

2. **Analytics Tab (NEW)**
   - Real-time API usage stats
   - Cost tracking
   - Cache performance metrics
   - Language distribution
   - Recent translation log

3. **Interactive Features**
   - One-click API key testing
   - Redis connection testing
   - Cache clearing
   - Setup guides with modals
   - Visual feedback for all actions

### Security
- Nonce verification for all AJAX requests
- Capability checks (`manage_options`)
- API key encryption at rest
- Sanitization of all inputs
- No API keys in browser console/network tab

### File Structure
```
wp-content/plugins/gtranslate/
├── gtranslate.php (MODIFY: add new settings sections)
├── includes/
│   ├── class-api-key-manager.php (NEW)
│   ├── class-admin-ajax-handlers.php (NEW)
│   └── class-analytics.php (NEW)
└── admin/
    ├── js/
    │   └── gtranslate-admin.js (NEW)
    └── css/
        └── gtranslate-admin.css (NEW)
```

All settings are centrally managed through the WordPress admin interface and accessible throughout the plugin via `get_option('GTranslate')`.
