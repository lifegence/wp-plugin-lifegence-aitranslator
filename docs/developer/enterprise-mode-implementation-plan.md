# Enterprise Mode Implementation Plan with AI Translation

## Executive Summary

**Question: Can we implement Enterprise mode with AI translation engines?**

**Answer: YES - Fully achievable with modifications to the URL routing and caching strategy.**

Enterprise mode (sub-domain structure like `ja.example.com`, `en.example.com`) can be implemented with Gemini/OpenAI by replacing the Google Translate proxy with AI-powered translation while maintaining the same URL structure and user experience.

## Current Enterprise Mode Architecture

### URL Structure

**Enterprise Mode (sub_domain):**
- Original: `example.com/page`
- Japanese: `ja.example.com/page`
- English: `en.example.com/page`
- French: `fr.example.com/page`

**Custom Domains Support:**
```json
{
  "ja": "japanese.example.com",
  "en": "english.example.com",
  "fr": "french.example.com"
}
```

### Request Flow (Current)

```
User visits: ja.example.com/about
      ↓
Apache/Nginx receives request
      ↓
No subdomain routing needed (DNS handles it)
      ↓
WordPress PHP redirect handler (gtranslate.php:2109+)
      ↓
Rewrites to: url_addon/gtranslate.php?glang=ja&gurl=about
      ↓
CURL proxy to: ja.{server}.tdn.gtranslate.net/about
      ↓
Google Translate API processes
      ↓
Translated HTML returned
      ↓
URL rewriting: change links to ja.example.com/...
      ↓
Response sent to user
```

### Technical Implementation (Current)

**1. DNS Configuration** (gtranslate.php:610-619)
- User must create DNS A/CNAME records:
  - `ja.example.com` → Server IP or CNAME to main domain
  - `en.example.com` → Server IP or CNAME to main domain
  - etc.

**2. PHP Redirect Handler** (gtranslate.php:2109-2160)
```php
// Detects subdomain and redirects to proxy
if($_SERVER['HTTP_HOST'] != $main_domain) {
    // Extract language from subdomain
    $lang = extract_subdomain_lang($_SERVER['HTTP_HOST']);

    // Redirect to proxy handler
    header('Location: /url_addon/gtranslate.php?glang='.$lang.'&gurl='.$_SERVER['REQUEST_URI']);
}
```

**3. Proxy Handler** (url_addon/gtranslate.php:1-284)
- Receives: `?glang=ja&gurl=/about`
- Constructs proxy URL: `https://ja.van.tdn.gtranslate.net/about`
- CURL request with all headers forwarded
- Receives translated HTML
- Rewrites all URLs in response (lines 242-258):
  - `href="/"` → `href="/ja/"`
  - `example.com` → `ja.example.com`
- Returns modified HTML

**4. Custom Domains** (gtranslate.php:455-493)
- Syncs with GTranslate dashboard API
- Stores mapping: `{"ja": "japanese.example.com"}`
- Uses in URL generation (js/base.js:38-41)

## AI-Based Enterprise Mode Architecture

### New Request Flow

```
User visits: ja.example.com/about
      ↓
Apache/Nginx (no change)
      ↓
WordPress PHP handler detects subdomain
      ↓
Extract language code: ja
      ↓
Translation Service Router
      ↓
Cache Check (Redis/Transients)
      ├─ HIT: Return cached translation (80% of requests)
      └─ MISS: Continue to translation
      ↓
Fetch original content (example.com/about)
      ↓
AI Translation Service (Gemini/OpenAI)
      ├─ Parse HTML structure
      ├─ Extract translatable text
      ├─ Translate via API
      └─ Reconstruct HTML
      ↓
URL Rewriting Engine
      ├─ href="/" → href="/" (keep same subdomain)
      ├─ Links to main domain → current subdomain
      └─ Language switcher links → other subdomains
      ↓
Cache Translated Page (24hr TTL)
      ↓
Return to User
```

### Implementation Components

#### 1. Subdomain Detection & Routing

**File: `includes/class-subdomain-router.php` (NEW)**

```php
<?php
class GTranslate_Subdomain_Router {
    private $main_domain;
    private $custom_domains;
    private $supported_languages;

    public function __construct() {
        $config = get_option('GTranslate');
        $this->main_domain = $this->extract_main_domain(get_site_url());
        $this->custom_domains = $config['custom_domains'] ?? [];
        $this->supported_languages = $config['languages'] ?? [];
    }

    /**
     * Detect if current request is for a translated subdomain
     *
     * @return array|false ['lang' => 'ja', 'is_custom' => true/false]
     */
    public function detect_language_subdomain() {
        $current_host = $_SERVER['HTTP_HOST'];

        // Check custom domains first
        if ($lang = array_search($current_host, $this->custom_domains)) {
            return [
                'lang' => $lang,
                'is_custom' => true,
                'host' => $current_host
            ];
        }

        // Check standard subdomain pattern: ja.example.com
        $subdomain = $this->extract_subdomain($current_host);

        if ($subdomain && in_array($subdomain, $this->supported_languages)) {
            return [
                'lang' => $subdomain,
                'is_custom' => false,
                'host' => $current_host
            ];
        }

        return false;
    }

    /**
     * Extract subdomain from host
     * ja.example.com → ja
     */
    private function extract_subdomain($host) {
        $host = str_replace('www.', '', $host);
        $main = str_replace('www.', '', $this->main_domain);

        if ($host === $main) {
            return false;
        }

        $subdomain = str_replace('.' . $main, '', $host);
        return $subdomain;
    }

    /**
     * Generate subdomain URL for given language
     */
    public function get_language_url($lang, $path = '/') {
        // Custom domain?
        if (isset($this->custom_domains[$lang])) {
            return 'https://' . $this->custom_domains[$lang] . $path;
        }

        // Standard subdomain
        if ($lang === $this->get_default_language()) {
            return 'https://' . $this->main_domain . $path;
        }

        return 'https://' . $lang . '.' . $this->main_domain . $path;
    }
}
```

#### 2. Translation Request Handler

**File: `includes/class-enterprise-translation-handler.php` (NEW)**

```php
<?php
class GTranslate_Enterprise_Translation_Handler {
    private $router;
    private $cache;
    private $translation_service;

    public function __construct() {
        $this->router = new GTranslate_Subdomain_Router();
        $this->cache = new GTranslate_Translation_Cache();
        $this->translation_service = GTranslate_Translation_Service_Factory::create();
    }

    /**
     * Main handler - called early in WordPress init
     */
    public function handle_request() {
        $subdomain_info = $this->router->detect_language_subdomain();

        if (!$subdomain_info) {
            // Not a translated subdomain, continue normally
            return;
        }

        $target_lang = $subdomain_info['lang'];
        $request_uri = $_SERVER['REQUEST_URI'];

        // Generate cache key
        $cache_key = $this->generate_cache_key($target_lang, $request_uri);

        // Check cache
        if ($cached_html = $this->cache->get($cache_key)) {
            $this->output_cached_response($cached_html);
            exit;
        }

        // Cache miss - translate
        $translated_html = $this->translate_page($target_lang, $request_uri);

        // Cache result
        $this->cache->set($cache_key, $translated_html, 86400); // 24hr

        // Output
        $this->output_response($translated_html);
        exit;
    }

    /**
     * Translate entire page
     */
    private function translate_page($target_lang, $request_uri) {
        // Step 1: Fetch original content
        $original_html = $this->fetch_original_content($request_uri);

        // Step 2: Translate with AI
        $translated_html = $this->translation_service->translate_html(
            $original_html,
            $this->get_source_language(),
            $target_lang
        );

        // Step 3: Rewrite URLs for subdomain
        $translated_html = $this->rewrite_urls_for_subdomain(
            $translated_html,
            $target_lang
        );

        return $translated_html;
    }

    /**
     * Fetch original content from main domain
     */
    private function fetch_original_content($request_uri) {
        $main_url = get_site_url() . $request_uri;

        $response = wp_remote_get($main_url, [
            'timeout' => 15,
            'sslverify' => false,
            'headers' => [
                'User-Agent' => 'GTranslate/AI-Engine',
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch original content: ' . $response->get_error_message());
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Rewrite URLs in translated HTML for subdomain structure
     */
    private function rewrite_urls_for_subdomain($html, $target_lang) {
        $main_domain = $this->router->get_main_domain();
        $current_subdomain_host = $_SERVER['HTTP_HOST'];

        // Replace main domain with current subdomain
        $html = str_replace($main_domain, $current_subdomain_host, $html);

        // Fix absolute URLs
        $html = str_replace('href="/', 'href="/', $html);
        $html = str_replace('src="/', 'src="/', $html);

        // Update language switcher links
        $html = $this->update_language_switcher_links($html, $target_lang);

        // Set lang attribute
        $html = preg_replace('/<html([^>]*)>/', '<html$1 lang="'.$target_lang.'">', $html, 1);

        // Add hreflang tags
        $html = $this->inject_hreflang_tags($html);

        return $html;
    }

    /**
     * Update language switcher to use subdomains
     */
    private function update_language_switcher_links($html, $current_lang) {
        $supported_langs = $this->get_supported_languages();
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($supported_langs as $lang) {
            $lang_url = $this->router->get_language_url($lang, $current_path);

            // Update data-gt-lang links
            $html = preg_replace(
                '/<a([^>]*data-gt-lang="'.$lang.'"[^>]*)href="[^"]*"/',
                '<a$1href="'.$lang_url.'"',
                $html
            );
        }

        return $html;
    }

    private function generate_cache_key($lang, $uri) {
        return 'gtranslate_enterprise_' . md5($lang . $uri . get_option('gtranslate_cache_version', '1'));
    }
}
```

#### 3. AI HTML Translation Service

**File: `includes/class-ai-html-translator.php` (NEW)**

```php
<?php
class GTranslate_AI_HTML_Translator {
    private $api_service;

    public function __construct($api_service) {
        $this->api_service = $api_service; // Gemini or OpenAI
    }

    /**
     * Translate HTML while preserving structure
     */
    public function translate_html($html, $source_lang, $target_lang) {
        // Step 1: Parse HTML and extract translatable content
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Step 2: Extract text nodes
        $text_segments = $this->extract_translatable_segments($dom);

        if (empty($text_segments)) {
            return $html;
        }

        // Step 3: Batch translate (API efficiency)
        $translations = $this->batch_translate($text_segments, $source_lang, $target_lang);

        // Step 4: Replace in HTML
        $translated_html = $this->replace_segments($html, $text_segments, $translations);

        return $translated_html;
    }

    /**
     * Extract translatable text segments from HTML
     */
    private function extract_translatable_segments($dom) {
        $segments = [];
        $xpath = new DOMXPath($dom);

        // Get all text nodes, excluding scripts, styles, etc.
        $text_nodes = $xpath->query('//text()[normalize-space(.) != "" and not(ancestor::script) and not(ancestor::style) and not(ancestor::noscript)]');

        foreach ($text_nodes as $node) {
            $text = trim($node->nodeValue);
            if (strlen($text) > 0 && !$this->is_untranslatable($text)) {
                $segments[] = $text;
            }
        }

        // Also extract meta tags, alt text, title attributes
        $segments = array_merge($segments, $this->extract_attributes($xpath));

        return array_unique($segments);
    }

    /**
     * Batch translate segments (up to 100 at a time for API efficiency)
     */
    private function batch_translate($segments, $source_lang, $target_lang) {
        $batch_size = 50; // API token limit consideration
        $translations = [];

        foreach (array_chunk($segments, $batch_size) as $batch) {
            $batch_text = implode("\n---SEGMENT---\n", $batch);

            $translated_batch = $this->api_service->translate_text(
                $batch_text,
                $source_lang,
                $target_lang
            );

            $translated_segments = explode("\n---SEGMENT---\n", $translated_batch);

            foreach ($batch as $i => $original) {
                $translations[$original] = $translated_segments[$i] ?? $original;
            }
        }

        return $translations;
    }

    /**
     * Replace segments in HTML
     */
    private function replace_segments($html, $original_segments, $translations) {
        foreach ($original_segments as $original) {
            $translation = $translations[$original] ?? $original;

            // Escape for regex
            $pattern = '/' . preg_quote($original, '/') . '/u';

            $html = preg_replace($pattern, $translation, $html, 1);
        }

        return $html;
    }

    private function is_untranslatable($text) {
        // Skip URLs, email, numbers only, etc.
        if (preg_match('/^https?:\/\//', $text)) return true;
        if (preg_match('/^[\d\s\.\,]+$/', $text)) return true;
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) return true;

        return false;
    }
}
```

#### 4. Caching Strategy for Enterprise Mode

**File: `includes/class-enterprise-translation-cache.php` (NEW)**

```php
<?php
class GTranslate_Enterprise_Translation_Cache {
    private $cache_backend; // 'transients' | 'redis' | 'memcached'
    private $prefix = 'gtranslate_ent_';

    public function __construct() {
        $this->detect_cache_backend();
    }

    /**
     * Get cached translation
     */
    public function get($key) {
        $full_key = $this->prefix . $key;

        switch ($this->cache_backend) {
            case 'redis':
                return $this->get_from_redis($full_key);
            case 'memcached':
                return $this->get_from_memcached($full_key);
            default:
                return get_transient($full_key);
        }
    }

    /**
     * Set cached translation
     */
    public function set($key, $value, $ttl = 86400) {
        $full_key = $this->prefix . $key;

        switch ($this->cache_backend) {
            case 'redis':
                return $this->set_to_redis($full_key, $value, $ttl);
            case 'memcached':
                return $this->set_to_memcached($full_key, $value, $ttl);
            default:
                return set_transient($full_key, $value, $ttl);
        }
    }

    /**
     * Invalidate cache for specific URL/language
     */
    public function invalidate($lang, $uri) {
        $key = md5($lang . $uri);
        $this->delete($this->prefix . $key);
    }

    /**
     * Invalidate all translations for a language
     */
    public function invalidate_language($lang) {
        // Pattern-based deletion (Redis/Memcached support)
        $pattern = $this->prefix . '*_' . $lang . '_*';

        if ($this->cache_backend === 'redis') {
            $this->delete_redis_pattern($pattern);
        } else {
            // For transients, need to track keys separately
            $this->delete_transient_pattern($lang);
        }
    }

    /**
     * Clear all translation cache
     */
    public function clear_all() {
        if ($this->cache_backend === 'redis') {
            $this->flush_redis_pattern($this->prefix . '*');
        } else {
            // WordPress transients cleanup
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            ));
        }
    }

    private function detect_cache_backend() {
        // Check for Redis
        if (class_exists('Redis') && defined('GTRANSLATE_REDIS_HOST')) {
            $this->cache_backend = 'redis';
            $this->init_redis();
            return;
        }

        // Check for Memcached
        if (class_exists('Memcached') && defined('GTRANSLATE_MEMCACHED_HOST')) {
            $this->cache_backend = 'memcached';
            $this->init_memcached();
            return;
        }

        // Default to WordPress Transients
        $this->cache_backend = 'transients';
    }

    // Redis implementation methods
    private $redis_connection;

    private function init_redis() {
        $this->redis_connection = new Redis();
        $this->redis_connection->connect(
            GTRANSLATE_REDIS_HOST ?? '127.0.0.1',
            GTRANSLATE_REDIS_PORT ?? 6379
        );

        if (defined('GTRANSLATE_REDIS_PASSWORD')) {
            $this->redis_connection->auth(GTRANSLATE_REDIS_PASSWORD);
        }
    }

    private function get_from_redis($key) {
        $value = $this->redis_connection->get($key);
        return $value !== false ? $value : null;
    }

    private function set_to_redis($key, $value, $ttl) {
        return $this->redis_connection->setex($key, $ttl, $value);
    }
}
```

#### 5. DNS & Server Configuration

**Required DNS Setup:**

For standard subdomains:
```
A Record:    ja.example.com    → Your Server IP
A Record:    en.example.com    → Your Server IP
A Record:    fr.example.com    → Your Server IP
```

Or with CNAME (recommended):
```
CNAME:       ja.example.com    → example.com
CNAME:       en.example.com    → example.com
CNAME:       fr.example.com    → example.com
```

**Apache Configuration (if needed):**

```apache
# .htaccess or VirtualHost
<VirtualHost *:80>
    ServerName example.com
    ServerAlias *.example.com

    DocumentRoot /var/www/html

    # All subdomains point to same WordPress installation
    # WordPress will handle language routing
</VirtualHost>
```

**Nginx Configuration (if needed):**

```nginx
server {
    listen 80;
    server_name example.com *.example.com;

    root /var/www/html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### WordPress Integration

**File: `gtranslate.php` (MODIFY)**

Add early init hook for enterprise mode:

```php
// Add after line 36 (after other hooks)
if (is_enterprise_mode_enabled()) {
    add_action('muplugins_loaded', array('GTranslate_Enterprise_Handler', 'init'), 1);
}

class GTranslate_Enterprise_Handler {
    public static function init() {
        require_once dirname(__FILE__) . '/includes/class-subdomain-router.php';
        require_once dirname(__FILE__) . '/includes/class-enterprise-translation-handler.php';
        require_once dirname(__FILE__) . '/includes/class-enterprise-translation-cache.php';
        require_once dirname(__FILE__) . '/includes/class-ai-html-translator.php';

        $handler = new GTranslate_Enterprise_Translation_Handler();
        $handler->handle_request();
    }
}

function is_enterprise_mode_enabled() {
    $config = get_option('GTranslate');
    return isset($config['enterprise_version']) && $config['enterprise_version'] === true;
}
```

## Performance Optimization

### 1. Aggressive Caching

**Cache Layers:**

1. **Page-Level Cache (L1)** - Full HTML, 24hr TTL
   - Key: `{lang}_{url_hash}_{cache_version}`
   - 80-90% hit rate expected
   - Invalidate on content update

2. **Segment-Level Cache (L2)** - Individual translations, 7 days TTL
   - Key: `{source_lang}_{target_lang}_{text_hash}`
   - Used for dynamic content
   - Higher TTL, less likely to change

3. **CDN Layer (Optional)** - CloudFlare, Fastly, etc.
   - Cache translated pages at edge
   - 99%+ hit rate for static content
   - Geo-routing for better performance

### 2. Smart Translation Strategy

**Incremental Translation:**
```php
// Don't translate everything at once
// Translate visible content first, defer below-fold

class Smart_Translation_Strategy {
    public function translate_prioritized($html, $source, $target) {
        // Extract above-fold content
        $above_fold = $this->extract_above_fold($html);

        // Translate immediately
        $translated_above = $this->translate($above_fold, $source, $target);

        // Queue below-fold for background processing
        $this->queue_background_translation($below_fold_content, $source, $target);

        // Return partial translation + placeholder
        return $this->merge_with_placeholders($translated_above, $below_fold);
    }
}
```

**Lazy Translation for Dynamic Content:**
```javascript
// Client-side AJAX translation for user-generated content
document.querySelectorAll('[data-translate-lazy]').forEach(el => {
    fetch('/wp-json/gtranslate/v1/translate-segment', {
        method: 'POST',
        body: JSON.stringify({
            text: el.textContent,
            target_lang: currentLang
        })
    })
    .then(r => r.json())
    .then(data => el.textContent = data.translation);
});
```

### 3. Cost Optimization

**Estimated Monthly Costs (10,000 page views):**

Without caching:
- 10,000 pages × 2,700 tokens avg = 27M tokens
- Gemini: $10.13/month
- OpenAI GPT-4o-mini: $20.25/month

With 90% cache hit rate:
- Only 1,000 API calls needed
- Gemini: $1.01/month ✅
- OpenAI: $2.03/month

**Cost Control Measures:**

1. **Rate Limiting**
   ```php
   class API_Rate_Limiter {
       public function check_limit($lang) {
           $key = 'gtranslate_api_calls_' . date('Y-m-d-H');
           $count = get_transient($key) ?? 0;

           if ($count > 1000) { // 1000 calls/hour limit
               return false;
           }

           set_transient($key, $count + 1, 3600);
           return true;
       }
   }
   ```

2. **Budget Alerts**
   ```php
   // Track monthly spend
   $monthly_calls = get_option('gtranslate_monthly_api_calls', 0);
   $estimated_cost = ($monthly_calls * 2700 * 2) / 1000000 * 0.375; // Gemini pricing

   if ($estimated_cost > 50) { // $50 budget
       // Send admin notification
       // Switch to cache-only mode
       update_option('gtranslate_api_limit_reached', true);
   }
   ```

## Custom Domain Support

### Implementation

**Custom Domains Table:**
```json
{
  "ja": "japanese.example.com",
  "en": "english.example.com",
  "fr": "francais.example.com"
}
```

**DNS Requirements:**
```
A/CNAME:  japanese.example.com  → Server IP or example.com
A/CNAME:  english.example.com   → Server IP or example.com
A/CNAME:  francais.example.com  → Server IP or example.com
```

**Router Modification:**
```php
// In class-subdomain-router.php
public function detect_language_subdomain() {
    $current_host = $_SERVER['HTTP_HOST'];

    // Check custom domains FIRST
    if ($lang = array_search($current_host, $this->custom_domains)) {
        return [
            'lang' => $lang,
            'is_custom' => true,
            'host' => $current_host
        ];
    }

    // Then check standard subdomains
    // ...existing code...
}
```

**URL Generation:**
```php
public function get_language_url($lang, $path = '/') {
    // Custom domain has priority
    if (isset($this->custom_domains[$lang])) {
        return 'https://' . $this->custom_domains[$lang] . $path;
    }

    // Fallback to standard subdomain
    return 'https://' . $lang . '.' . $this->main_domain . $path;
}
```

## SEO Considerations

### 1. Hreflang Tags

**Auto-generation:**
```php
private function inject_hreflang_tags($html) {
    $current_path = $_SERVER['REQUEST_URI'];
    $hreflang_tags = '';

    foreach ($this->get_supported_languages() as $lang) {
        $url = $this->router->get_language_url($lang, $current_path);
        $hreflang_tags .= '<link rel="alternate" hreflang="'.$lang.'" href="'.$url.'" />' . "\n";
    }

    // Add x-default
    $default_url = $this->router->get_language_url($this->get_default_language(), $current_path);
    $hreflang_tags .= '<link rel="alternate" hreflang="x-default" href="'.$default_url.'" />' . "\n";

    // Inject before </head>
    return str_replace('</head>', $hreflang_tags . '</head>', $html);
}
```

### 2. Sitemap Generation

**Multi-language sitemaps:**
```xml
<!-- sitemap-ja.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://ja.example.com/about</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://en.example.com/about"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://fr.example.com/about"/>
    <xhtml:link rel="alternate" hreflang="ja" href="https://ja.example.com/about"/>
  </url>
</urlset>
```

### 3. Canonical URLs

```php
// Set canonical to avoid duplicate content
private function inject_canonical_tag($html, $lang) {
    $current_url = $this->router->get_language_url($lang, $_SERVER['REQUEST_URI']);
    $canonical = '<link rel="canonical" href="'.$current_url.'" />';

    return str_replace('</head>', $canonical . "\n</head>", $html);
}
```

## Migration from Current Enterprise Mode

### Step-by-Step Migration

**Phase 1: Parallel Testing (Week 1-2)**

1. Install AI translation components alongside existing system
2. Add feature flag:
   ```php
   define('GTRANSLATE_AI_ENTERPRISE_BETA', true);
   ```
3. Route 10% of traffic to AI engine for testing
4. Compare translation quality metrics

**Phase 2: Gradual Rollout (Week 3-4)**

1. Increase AI traffic to 50%
2. Monitor:
   - API costs
   - Cache hit rates
   - Translation quality feedback
   - Performance metrics (TTFB, page load)

**Phase 3: Full Migration (Week 5-6)**

1. Switch 100% to AI translation
2. Keep Google proxy as fallback for 30 days
3. Monitor error rates
4. Optimize cache strategy based on real traffic

**Phase 4: Cleanup (Week 7-8)**

1. Remove Google proxy dependencies
2. Update documentation
3. Archive old translation code
4. Optimize database queries and cache

### Rollback Plan

**If issues occur:**

1. Immediate rollback via feature flag:
   ```php
   update_option('gtranslate_use_ai_engine', false);
   ```

2. Fallback routing:
   ```php
   if (get_option('gtranslate_use_ai_engine') === false) {
       // Route to old Google proxy system
       return $this->legacy_google_translate_handler();
   }
   ```

3. Keep both systems running for 90 days minimum

## Comparison: Current vs AI Enterprise Mode

| Feature | Current (Google Proxy) | AI (Gemini/OpenAI) |
|---------|------------------------|---------------------|
| **URL Structure** | ja.example.com ✅ | ja.example.com ✅ |
| **Custom Domains** | ✅ Supported | ✅ Supported |
| **Translation Quality** | Good | Better (context-aware) |
| **Setup Complexity** | DNS + Proxy config | DNS + API keys |
| **Ongoing Cost** | $14.99/month | $1-5/month (with caching) |
| **Cache Control** | Limited | Full control ✅ |
| **Customization** | Limited | Highly customizable ✅ |
| **Dependency** | GTranslate TDN servers | Direct API (no middleman) ✅ |
| **Performance** | ~2-3s TTFB | ~0.1s cached, ~4s uncached |
| **SEO** | Hreflang supported | Full SEO control ✅ |
| **Maintenance** | Vendor dependent | Self-managed ✅ |

## Technical Requirements

### Server Requirements

**Minimum:**
- PHP 7.4+
- WordPress 5.0+
- 512MB RAM
- CURL extension
- JSON extension
- DOM extension

**Recommended:**
- PHP 8.1+
- 2GB RAM
- Redis or Memcached
- CDN integration (CloudFlare, etc.)
- HTTP/2 support

### API Requirements

**Gemini:**
- API key from Google AI Studio
- Free tier: 60 requests/minute
- Paid tier: Higher limits

**OpenAI:**
- API key from OpenAI platform
- Rate limits based on tier
- GPT-4o-mini recommended for cost

### Network Requirements

**Outbound:**
- API access to generativelanguage.googleapis.com (Gemini)
- API access to api.openai.com (OpenAI)
- HTTPS support required

**Inbound:**
- Subdomain DNS routing configured
- Wildcard SSL certificate (*.example.com)
- Or individual SSL certs for each subdomain/custom domain

## Conclusion

**Enterprise mode with AI translation is fully achievable and offers significant advantages:**

### ✅ Feasibility
- All technical components are implementable
- No fundamental limitations
- Standard WordPress/PHP capabilities sufficient

### ✅ Advantages Over Current System
1. **Better Translation Quality** - Context-aware AI vs. word-for-word
2. **Lower Cost** - $1-5/month vs $14.99/month (with proper caching)
3. **Full Control** - No dependency on external proxy servers
4. **Better Performance** - With aggressive caching
5. **Customizable** - Can tune prompts, adjust quality vs. speed
6. **Scalable** - Easy to add more languages or switch engines

### ✅ Challenges & Solutions
1. **Initial Setup** - More complex than current, but one-time effort
   - Solution: Automated setup wizard
2. **API Costs Without Cache** - Could be expensive
   - Solution: 90% cache hit rate target (achieved via smart TTL)
3. **Translation Speed** - 3-5s for uncached pages
   - Solution: Background pre-warming of popular pages
4. **Server Resources** - HTML parsing requires CPU
   - Solution: Cache-first strategy, async processing

### 📋 Implementation Timeline

- **Week 1-2:** Core infrastructure (router, cache, API integration)
- **Week 3-4:** HTML translation logic, URL rewriting
- **Week 5-6:** Testing, optimization, cache tuning
- **Week 7-8:** Gradual rollout, monitoring
- **Total:** 8 weeks to production-ready Enterprise mode with AI

### 💰 Cost Projection (Real-world)

**Small Site (5k views/month):**
- With 90% cache: ~$0.50/month ✅ Essentially free

**Medium Site (50k views/month):**
- With 90% cache: ~$5/month ✅ Much cheaper than $14.99

**Large Site (500k views/month):**
- With 90% cache: ~$50/month
- May need better caching or CDN integration

**Recommendation: Start with Gemini 2.5 Flash for best cost/quality balance.**

---

## Next Steps

1. **Decision:** Confirm Enterprise mode implementation with AI
2. **Infrastructure:** Set up development environment with subdomain routing
3. **POC:** Build proof-of-concept for single language subdomain
4. **Testing:** Validate translation quality, performance, costs
5. **Production:** Full implementation following migration plan

Enterprise mode is not only possible but recommended for AI-based translation due to better SEO, user experience, and full control over the translation process.
