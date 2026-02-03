# Lifegence AITranslator

AI-powered multilingual translation plugin for WordPress using Google Gemini and OpenAI.

Developed by **Lifegence Corporation** (https://lifegence.com)

## Features

- **AI-Powered Translation**: Uses Google Gemini or OpenAI GPT for high-quality, context-aware translations
- **Multiple Providers**: Support for both Gemini and OpenAI with easy switching
- **Smart Caching**: Reduces API costs by 80-95% with intelligent caching system
- **20+ Languages**: Support for major world languages including Japanese, Chinese, Spanish, French, and more
- **Custom Languages**: Add any language not in the preset list via admin interface
- **Language Switcher Widget**: Beautiful, customizable language selector
- **REST API**: Programmatic translation via WordPress REST API
- **Shortcode Support**: Easy integration with `[lg-translator]` shortcode
- **Cost Management**: Budget limits, rate limiting, and usage tracking
- **SEO Friendly**: Maintains HTML structure and supports multilingual SEO

## Installation

1. Upload the `lifegence-aitranslator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Lifegence AITranslator to configure

## Configuration

### 1. Get API Keys

**For Gemini (Recommended):**
1. Visit [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Sign in with your Google account
3. Create a new API key
4. Copy the key (starts with `AIzaSy...`)

**For OpenAI:**
1. Visit [OpenAI Platform](https://platform.openai.com/api-keys)
2. Sign in or create an account
3. Create a new secret key
4. Copy the key (starts with `sk-...`)

### 2. Plugin Settings

Navigate to **Settings → Lifegence AITranslator** and configure:

#### General Tab
- **Enable Translation**: Turn on AI translation
- **Default Language**: Your website's original language
- **Supported Languages**: Select languages to enable
- **Custom Languages**: Add custom languages (see below)

#### Translation Engine Tab
- **Provider**: Choose Gemini or OpenAI
- **Model**: Select AI model (Gemini 2.5 Flash or GPT-4.1 Mini recommended)
- **API Key**: Enter your API key and test connection
- **Quality**: Standard (faster) or High (better quality)

#### Cache Tab
- **Enable Cache**: Highly recommended to reduce costs
- **Cache Duration**: 24 hours recommended
- **Cache Backend**: WordPress Transients (default) or Redis for high-traffic sites

#### Advanced Tab
- **Rate Limiting**: Prevent excessive API usage
- **Monthly Budget**: Set spending limits
- **Auto-disable**: Automatically switch to cache-only mode when budget is exceeded

## Usage

### Widget

1. Go to **Appearance → Widgets**
2. Add "Lifegence Language Switcher" widget
3. Configure display options:
   - Dropdown, List, or Flags only
   - Show/hide flags
   - Show/hide native names

### Shortcode

Add language switcher anywhere using shortcode:

```php
[lg_language_switcher type="dropdown" flags="yes" native_names="yes"]
```

**Parameters:**
- `type`: dropdown, list, or flags
- `flags`: yes or no
- `native_names`: yes or no

### REST API

Translate text programmatically:

```javascript
POST /wp-json/lifegence-aitranslator/v1/translate
{
  "text": "Hello world",
  "target_lang": "ja",
  "source_lang": "en"
}
```

Get supported languages:

```javascript
GET /wp-json/lifegence-aitranslator/v1/languages
```

### PHP Integration

```php
// Get translation service
$service = LG_Translation_Service_Factory::create();

// Translate text
$translation = $service->translate_text(
    'Hello world',
    'en',  // source language
    'ja'   // target language
);

// Translate HTML
$translated_html = $service->translate_html(
    '<h1>Welcome</h1><p>This is a test</p>',
    'en',
    'ja'
);
```

## Supported Languages

### Preset Languages (20)

- English (en)
- 日本語 (ja)
- 简体中文 (zh-CN)
- 繁體中文 (zh-TW)
- 한국어 (ko)
- Español (es)
- Français (fr)
- Deutsch (de)
- Italiano (it)
- Português (pt)
- Русский (ru)
- العربية (ar)
- हिन्दी (hi)
- ไทย (th)
- Tiếng Việt (vi)
- Bahasa Indonesia (id)
- Türkçe (tr)
- Polski (pl)
- Nederlands (nl)
- Svenska (sv)

### Custom Languages

You can add any language not in the preset list through the admin interface.

**How to Add Custom Languages:**

1. Go to **Settings → Lifegence AITranslator → General** tab
2. Scroll to the **Custom Languages** section
3. Enter the language code (e.g., `tl` for Tagalog, `ms` for Malay)
4. Enter the language name (e.g., `Tagalog`, `Malay`)
5. Click **Add Custom Language**
6. Click **Save Settings** to apply changes

**Popular Custom Language Examples:**

- Tagalog (`tl`)
- Filipino (`fil`)
- Malay (`ms`)
- Bengali (`bn`)
- Punjabi (`pa`)
- Urdu (`ur`)
- Persian (`fa`)
- Hebrew (`he`)
- Greek (`el`)
- Czech (`cs`)
- Danish (`da`)
- Finnish (`fi`)
- Norwegian (`no`)
- Hungarian (`hu`)
- Romanian (`ro`)
- Ukrainian (`uk`)

**Language Code Format:**

- Use standard ISO 639-1 codes (2 letters) or extended codes
- Allowed characters: letters, numbers, hyphens, underscores
- Examples: `en`, `ja`, `zh-CN`, `pt-BR`, `fil`

## Pricing Estimates

### With 90% Cache Hit Rate (10,000 pageviews/month)

**Gemini 2.5 Flash:**
- Without cache: ~$10/month
- With cache: ~$1/month ✅

**OpenAI GPT-4.1 Mini:**
- Without cache: ~$20/month
- With cache: ~$2/month

### Tips for Cost Optimization

1. **Enable caching** (reduces costs by 80-95%)
2. **Use longer cache duration** (24 hours or more)
3. **Choose Gemini** for best cost/quality ratio
4. **Set budget limits** to prevent overruns
5. **Use Redis** for high-traffic sites

## Caching

### WordPress Transients (Default)
Works out of the box, suitable for most sites.

### Redis (High-Traffic Sites)
For better performance on high-traffic sites:

1. Install Redis on your server
2. Install PHP Redis extension
3. Configure in plugin settings:
   - Host: `127.0.0.1`
   - Port: `6379`
   - Password: (if required)
4. Test connection

### Edit Translations on Your Site

If an AI translation doesn't sound right, you can edit it directly on your website.

**How to Edit:**

1. **Enable Edit Mode**
   - Log in as admin
   - Click "✏️ Edit Translation" button in the admin bar
   - Or add `?edit_translation=1` to the URL

2. **Click and Edit**
   - Click any translated text on the page
   - Type your correction
   - Click "Save"
   - Refresh the page

3. **Re-translate Everything** (if needed)
   - Go to **Settings → Lifegence AITranslator → Cache** tab
   - Click **Increment Cache Version (Force Re-translate)**
   - All pages will be re-translated on next visit

For detailed instructions with screenshots, see the [Translation Editing Guide](docs/cache-override-guide.md).

## Troubleshooting

### API Key Errors
- Verify key is correct (no extra spaces)
- Check API quota and billing status
- Test connection in plugin settings

### Translation Not Working
- Check if plugin is enabled in General settings
- Verify API key is configured
- Check browser console for JavaScript errors
- Enable debug logging in WordPress

### High Costs
- Enable caching if not already enabled
- Increase cache duration
- Check rate limiting settings
- Monitor usage in API provider dashboard

### Cache Issues
- Clear cache from Cache tab
- Verify cache backend is working (test Redis connection)
- Check disk space for transients

### Correcting AI Translations
If an AI translation is incorrect:
1. Enable edit mode: Add `?edit_translation=1` to the URL or click "✏️ Edit Translation" in admin bar
2. Click the text you want to change
3. Type your correction and save
4. Refresh the page to see changes
5. For bulk changes: Use "Increment Cache Version" in Cache settings

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PHP extensions: curl, json, openssl
- Optional: Redis (for high-traffic sites)

## Development

### File Structure

```
lifegence-aitranslator/
├── lifegence-aitranslator.php   # Main plugin file
├── includes/
│   ├── class-translation-service-interface.php
│   ├── class-translation-service-factory.php
│   ├── class-gemini-translation-service.php
│   ├── class-openai-translation-service.php
│   ├── class-translation-cache.php
│   ├── class-api-key-manager.php
│   └── class-language-switcher-widget.php
├── admin/
│   ├── class-admin-settings.php
│   ├── class-admin-ajax.php
│   ├── js/admin.js
│   └── css/admin.css
├── assets/
│   ├── js/frontend.js
│   └── css/frontend.css
└── languages/
```

### Hooks and Filters

The plugin provides various hooks for customization:

```php
// Modify translation before caching
add_filter('lg_aitrans_translation_result', function($translation, $text, $source_lang, $target_lang) {
    // Modify translation
    return $translation;
}, 10, 4);

// Modify supported languages
add_filter('lg_aitrans_supported_languages', function($languages) {
    // Add or remove languages
    return $languages;
});
```

## License

GPL v2 or later

## Support

For issues and feature requests, please visit:
- GitHub: [Your Repository URL]
- Documentation: [Your Docs URL]

## Credits

Inspired by GTranslate plugin architecture, rebuilt with modern AI translation engines.

## Changelog

### 1.0.0
- Initial release
- Google Gemini integration
- OpenAI GPT integration
- Smart caching system
- Language switcher widget
- REST API support
- Admin settings interface
