# Lifegence AITranslator - Installation & Setup Guide

Developed by **Lifegence Corporation** (https://lifegence.com)

## Quick Start (5 minutes)

### Step 1: Install Plugin

1. Upload `lifegence-aitranslator` folder to `/wp-content/plugins/`
2. Activate via WordPress Admin → Plugins
3. You'll see "Lifegence AITranslator" in Settings menu

### Step 2: Get API Key (Choose One)

#### Option A: Google Gemini (Recommended - Free Tier Available)

1. Visit https://aistudio.google.com/app/apikey
2. Sign in with Google account
3. Click "Create API Key"
4. Copy key (starts with `AIzaSy...`)

**Pricing:**
- Free tier: 15 requests/minute
- Paid tier: $0.075 per 1M input tokens

#### Option B: OpenAI (Premium Quality)

1. Visit https://platform.openai.com/api-keys
2. Sign in or create account
3. Click "+ Create new secret key"
4. Copy key (starts with `sk-...`)

**Pricing:**
- GPT-4.1 Mini: $0.40 per 1M input tokens
- Requires paid account

### Step 3: Configure Plugin

1. Go to **Settings → Lifegence AITranslator**

2. **General Tab:**
   - ✅ Enable Translation
   - Set Default Language (e.g., English)
   - Check supported languages (e.g., Japanese, Chinese, Spanish)

3. **Translation Engine Tab:**
   - Select Provider (Gemini recommended)
   - Choose Model:
     - Gemini: `gemini-2.5-flash` (recommended)
     - OpenAI: `gpt-4o-mini` (recommended)
   - Paste API Key
   - Click "Test Connection" ✓
   - Quality: Standard (faster) or High (better)

4. **Cache Tab:**
   - ✅ Enable Cache (important!)
   - Duration: 24 Hours (recommended)
   - Backend: WordPress Transients

5. **Advanced Tab:**
   - ✅ Enable Rate Limiting
   - Requests/Hour: 1000
   - Monthly Budget: $50 USD
   - ✅ Auto-disable on Budget (optional)

6. Click **Save Settings**

### Step 4: Add Custom Languages (Optional)

If you need languages not in the preset list (English, Japanese, Chinese, Spanish, etc.):

1. Go to **Settings → Lifegence AITranslator → General** tab
2. Scroll to **Custom Languages** section
3. Enter language code (e.g., `tl` for Tagalog, `ms` for Malay)
4. Enter language name (e.g., `Tagalog`, `Malay`)
5. Click **Add Custom Language**
6. Repeat for additional languages
7. Click **Save Settings**

**Popular Custom Languages:**
- Tagalog: `tl`
- Filipino: `fil`
- Malay: `ms`
- Bengali: `bn`
- Urdu: `ur`
- Persian: `fa`
- Hebrew: `he`
- Greek: `el`

Custom languages will appear in all language selectors alongside preset languages.

### Step 5: Add Language Switcher

#### Method 1: Widget (Easiest)
1. Go to **Appearance → Widgets**
2. Add "Lifegence Language Switcher" widget to sidebar
3. Configure:
   - Title: "Select Language"
   - Type: Dropdown
   - ✅ Show flags
   - ✅ Show native names
4. Save

#### Method 2: Shortcode (Flexible)
Add to any page or post:
```
[lg-translator type="dropdown" flags="yes" native_names="yes"]
```

#### Method 3: PHP Template (Advanced)
Add to your theme:
```php
<?php
if (function_exists('lg_aitranslator')) {
    echo do_shortcode('[lg-translator]');
}
?>
```

### Step 6: Test Translation

1. Visit your website
2. Select a different language from switcher
3. Page should reload with `?lang=ja` (or chosen language)
4. Content will be translated using AI

## Verification Checklist

- [ ] Plugin activated
- [ ] API key configured and tested
- [ ] Translation enabled in General settings
- [ ] At least 2 languages selected
- [ ] Cache enabled
- [ ] Language switcher visible on frontend
- [ ] Translation works when switching languages

## Expected Costs

### Small Site (5,000 views/month)
- With 90% cache: **~$0.50/month** (essentially free)

### Medium Site (50,000 views/month)
- With 90% cache: **~$5/month**

### Large Site (500,000 views/month)
- With 90% cache: **~$50/month**
- Consider using Redis cache

## Optimization Tips

### 1. Enable Caching (CRITICAL)
Without cache: Very expensive
With cache: 80-95% cost reduction

### 2. Choose Right Model
- **Gemini 2.5 Flash**: Best cost/quality for most sites
- **GPT-4.1 Mini**: Higher quality, reasonable cost
- **GPT-4.1**: Premium quality, higher cost (rarely needed)

### 3. Cache Duration
- 24 hours: Good for frequently updated sites
- 7 days: Good for static content sites
- Longer = Lower costs

### 4. Budget Protection
- Set monthly budget limit
- Enable auto-disable to prevent overruns
- Monitor usage in API provider dashboard

### 5. High-Traffic Sites
If you have >100k monthly views:
1. Install Redis: `sudo apt-get install redis-server`
2. Install PHP extension: `sudo apt-get install php-redis`
3. Enable Redis in plugin settings
4. Test connection

## Common Issues

### "API Key Invalid"
- Check for typos (no spaces)
- Verify billing enabled (OpenAI requires payment method)
- Check API quota not exceeded

### "Translation Not Working"
1. Check plugin enabled (General tab)
2. Verify API key tested successfully
3. Check JavaScript console for errors
4. Try clearing cache

### "Costs Too High"
1. ✅ Enable caching
2. Increase cache duration
3. Switch to Gemini from OpenAI
4. Check rate limiting enabled
5. Monitor actual API usage in provider dashboard

### "Widget Not Showing"
1. Verify widget added to sidebar/widget area
2. Check theme supports widgets
3. Try shortcode instead
4. Check plugin activated

## Advanced Configuration

### Redis Setup (Ubuntu/Debian)

```bash
# Install Redis
sudo apt-get update
sudo apt-get install redis-server php-redis

# Start Redis
sudo systemctl start redis
sudo systemctl enable redis

# Test Redis
redis-cli ping
# Should return: PONG
```

Then in plugin:
- Cache Backend: Redis
- Host: 127.0.0.1
- Port: 6379
- Test Connection ✓

### Custom Language Mapping (Legacy Method)

**Note:** You can now add custom languages via admin interface (Settings → Lifegence AITranslator → General → Custom Languages). This programmatic method is provided for advanced use cases only.

Add to your theme's `functions.php`:

```php
add_filter('lg_aitrans_supported_languages', function($languages) {
    // Add custom language programmatically
    $languages['my'] = 'မြန်မာဘာသာ'; // Burmese
    return $languages;
});
```

### API Usage Tracking

Add to your theme to track usage:

```php
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        $cache = new LG_Translation_Cache();
        $stats = $cache->get_stats();
        echo '<!-- Cache: ' . $stats['total_keys'] . ' items -->';
    }
});
```

## Next Steps

1. **Monitor Costs**: Check API usage in provider dashboard after first week
2. **Optimize Cache**: Adjust duration based on content update frequency
3. **Add More Languages**: Enable additional languages as needed
4. **Customize Widget**: Style language switcher to match your theme
5. **Set Budget Alerts**: Configure spending notifications in API provider

## Support

Need help?
- 📖 Read full [README.md](README.md)
- 💬 Check WordPress support forums
- 🐛 Report issues on GitHub
- 📧 Contact plugin developer

## Security Notes

- API keys are encrypted in WordPress database
- Keys use OpenSSL AES-256-CBC encryption
- Never commit API keys to version control
- Regularly rotate API keys (quarterly recommended)
- Monitor API usage for unusual activity

---

**Congratulations!** Your WordPress site is now powered by AI translation. 🎉
