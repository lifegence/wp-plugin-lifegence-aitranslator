# Lifegence AITranslator - WordPress Plugin Description

## Short Description (150 characters max)
AI-powered automatic translation plugin using Google Gemini and OpenAI GPT. Translate your entire WordPress site into multiple languages instantly.

## Description

### Transform Your WordPress Site into a Multilingual Platform with AI

**Lifegence AITranslator** is a powerful WordPress plugin that automatically translates your entire website into multiple languages using cutting-edge AI technology from Google Gemini and OpenAI GPT.

### 🌟 Key Features

#### 🤖 Advanced AI Translation
- **Dual AI Engine Support**: Choose between Google Gemini API or OpenAI GPT API
- **High-Quality Translations**: Natural, context-aware translations that sound human-written
- **Intelligent Text Processing**: Automatically skips URLs, email addresses, and code blocks
- **Batch Translation**: Optimized API usage with single-request-per-page processing

#### 🌍 Multilingual Support
Support for 20+ languages including:
- English, Japanese (日本語), Korean (한국어)
- Chinese Simplified (简体中文), Chinese Traditional (繁體中文)
- Spanish, French, German, Italian, Portuguese
- Russian, Arabic, Hindi, Thai, Vietnamese, Indonesian
- Turkish, Polish, Dutch, Swedish

#### ✏️ Inline Translation Editor
- **Visual Edit Mode**: Click to edit any translated text directly on your page
- **Real-time Updates**: Changes are immediately visible after saving
- **Admin Bar Integration**: Toggle edit mode with one click from the admin bar
- **Cache Override**: Manually correct AI translations when needed

#### ⚡ Performance Optimized
- **Smart Caching System**: WordPress Transient-based caching for lightning-fast page loads
- **Cache Version Control**: Invalidate all translations with one click to force re-translation
- **Selective Caching**: Individual text caching for efficient memory usage
- **Rate Limit Management**: Automatic API request optimization to avoid rate limits

#### 🔧 SEO-Friendly
- **Language Prefix URLs**: Clean URL structure like `/ko/about`, `/ja/contact`
- **Hreflang Tags**: Automatic hreflang tag generation for proper SEO
- **Language Attributes**: Dynamic HTML lang attribute updates
- **Search Engine Friendly**: Properly indexed multilingual content

#### 🎨 Flexible Language Switcher
Multiple display formats:
- Dropdown selector
- Flag icons
- Text links
- Custom positioning via shortcode or widget

#### 🔒 Secure & Reliable
- **API Key Encryption**: Secure storage of API keys in WordPress database
- **Permission Controls**: Admin-only access to translation editing
- **Nonce Verification**: AJAX request security
- **Input Sanitization**: XSS protection on all user inputs

### 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Either Google Gemini API key OR OpenAI API key
- Modern web browser with JavaScript enabled

### 🚀 Installation

1. Upload the plugin files to `/wp-content/plugins/lifegence-aitranslator/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Lifegence AITranslator
4. Enter your Google Gemini API key or OpenAI API key
5. Select supported languages
6. Click "Save Settings"

### ⚙️ Configuration

#### Basic Settings
1. **Enable Translation**: Turn the plugin on/off globally
2. **AI Provider**: Choose between Gemini or OpenAI
3. **API Key**: Enter your API key (stored securely)
4. **Default Language**: Set your site's original language
5. **Supported Languages**: Select which languages to enable

#### Advanced Options
- **Translation Quality**: Standard or High (affects prompt complexity)
- **Temperature**: Control translation creativity (0.0 - 1.0)
- **Cache Settings**: Enable/disable caching, set cache duration
- **Rate Limiting**: Configure API request throttling
- **Model Selection**: Choose specific AI models (GPT-4.1 Mini, Gemini 2.5 Flash, etc.)

### 🎯 How to Use

#### For Site Visitors
1. Navigate to your WordPress site
2. Select language from the language switcher
3. Entire site is automatically translated
4. URLs update to include language prefix (e.g., `/ko/page-name`)

#### For Administrators - Edit Translations
1. Visit any translated page while logged in as admin
2. Click "✏️ 翻訳を編集" in the admin bar
3. Hover over any text to see the edit button (✏️)
4. Click to edit, modify the translation
5. Click "💾 保存" to save changes
6. Changes are immediately visible and cached

#### Language Switcher Shortcodes
```
[lg_lang_switcher type="dropdown"]    // Dropdown selector
[lg_lang_switcher type="list"]        // Text link list
[lg_lang_switcher type="flags"]       // Flag icons only
```

### 💡 Pro Tips

#### Optimize API Costs
- Enable caching to minimize API requests
- Start with fewer languages and expand gradually
- Use "Standard" quality for general content, "High" for critical pages

#### Translation Quality
- Review AI translations in edit mode before publishing
- Customize translations for brand-specific terms
- Use cache version increment to refresh all translations after improvements

#### Performance
- Enable WordPress object caching for better performance
- Set appropriate cache expiration (default: 7 days)
- Monitor API usage via provider dashboards

### 🔄 Cache Management

#### Clear All Cache
- Settings → Lifegence AITranslator → Advanced Tab
- Click "Clear All Cache" button
- All cached translations are deleted
- Next page load triggers fresh translation

#### Increment Cache Version
- Forces re-translation of all content
- Existing cache becomes invalid
- Useful after improving translation prompts
- Previous translations are replaced gradually

### 📊 API Usage & Costs

#### Google Gemini API
- **Free Tier**: 15 requests per minute
- **Model**: gemini-2.5-flash (fastest, cheapest)
- **Recommended For**: Small to medium sites

#### OpenAI API
- **Model**: gpt-4o-mini (balanced cost/quality)
- **Pay-per-use**: ~$0.15 per 1M tokens
- **Recommended For**: High-quality translations

### 🐛 Troubleshooting

#### Translations Not Appearing
1. Check API key is valid (use "Test Connection" button)
2. Verify JavaScript is enabled in browser
3. Check browser console for errors
4. Clear WordPress cache and plugin cache

#### Rate Limit Errors (429)
1. Reduce number of languages
2. Enable caching to minimize API calls
3. Wait a few minutes before retrying
4. Upgrade to paid API tier if needed

#### Only Header Translating
1. Check if JavaScript is loading properly
2. Disable conflicting plugins temporarily
3. Switch to default WordPress theme to test
4. Check for console errors

### 🛠️ Developer Friendly

#### Hooks & Filters
```php
// Modify translation before caching
add_filter('lg_aitranslator_translation', function($translation, $original, $lang) {
    return $translation;
}, 10, 3);

// Skip translation for specific content
add_filter('lg_aitranslator_should_translate', function($should_translate, $content) {
    return $should_translate;
}, 10, 2);
```

#### Custom Language Support
```php
// Add custom language
add_filter('lg_aitranslator_languages', function($languages) {
    $languages['custom'] = 'Custom Language';
    return $languages;
});
```

### 📝 Changelog

#### Version 1.0.0
- Initial release
- Dual AI engine support (Gemini + OpenAI)
- 20+ language support
- Inline translation editor
- Smart caching system
- SEO-friendly URLs
- Language switcher widgets
- Admin settings panel

### 👨‍💻 Support & Documentation

- **Documentation**: [GitHub Repository](https://github.com/lifegence/wp-plugin-lifegence-aitranslator)
- **Issues**: Report bugs via GitHub Issues
- **Support**: Community support via WordPress.org forums

### 📄 License

This plugin is licensed under the GPL v2 or later.

### 🙏 Credits

Developed with ❤️ using:
- Google Gemini API
- OpenAI GPT API
- WordPress Core Functions

### ⭐ Rate Us

If you find this plugin helpful, please leave a 5-star review on WordPress.org!

---

**Note**: This plugin requires external API services (Google Gemini or OpenAI). API usage may incur costs depending on your usage volume. Please review the respective API pricing before use.
