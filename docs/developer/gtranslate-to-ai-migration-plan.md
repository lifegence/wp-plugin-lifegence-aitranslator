# GTranslate to AI Translation Engine Migration Plan

## Executive Summary

This document outlines the plan to migrate the GTranslate WordPress plugin from Google Translate API to modern AI translation engines (Gemini/OpenAI). The current plugin relies on Google's translation service via proxy servers, and this migration will replace it with direct API integration to Gemini or OpenAI's translation capabilities.

## Current Architecture Analysis

### Plugin Structure

**Core Components:**
- `gtranslate.php` - Main plugin file (178KB, ~2700 lines)
  - `GTranslate` class - Main widget/plugin class
  - `GTranslateWidget` - Widget implementation
  - `GTranslate_Notices` - Admin notification system
- `url_addon/` - URL translation proxy system
  - `gtranslate.php` - Proxy handler for page translation
  - `gtranslate-email.php` - Email translation handler
  - `config.php` - Configuration (main language, servers)
- `js/` - Frontend JavaScript for language switcher UI
  - `base.js` - Core translation switching logic
  - Various UI implementations (dropdown, flags, etc.)

### Translation Flow

**Current Implementation (3 modes):**

1. **Free Mode (url_structure: 'none')**
   - Uses Google Translate Widget directly
   - Client-side translation via `translate.google.com/translate_a/element.js`
   - No server-side processing
   - Cookie-based language storage (`googtrans`)

2. **Pro Mode (url_structure: 'sub_directory')**
   - URL pattern: `example.com/ja/page`
   - Proxy requests through GTranslate TDN servers
   - Server: `{server}.tdn.gtranslate.net`
   - CURL-based HTTP proxying

3. **Enterprise Mode (url_structure: 'sub_domain')**
   - URL pattern: `ja.example.com/page`
   - Custom domain support
   - Same proxy mechanism as Pro mode

### Key Translation Points

**gtranslate.php:30-42** - Widget/plugin registration hooks
**gtranslate.php:44-1719** - Main GTranslate class with configuration UI
**url_addon/gtranslate.php:42-194** - Core proxy translation logic
- Lines 42-45: Server selection and URL construction
- Lines 144-194: CURL request to `{lang}.{server}.tdn.gtranslate.net`
**url_addon/gtranslate-email.php:44-62** - Email translation endpoint
- Line 45: `https://tdns.gtranslate.net/tdn-bin/email-translate?lang={lang}`
**js/base.js:100-101** - Frontend Google Translate initialization
- Line 100: `doGTranslate()` function
- Line 101: `googleTranslateElementInit2()` initialization

### Dependencies

**Translation Service:**
- Google Translate API (indirect via proxy servers)
- TDN proxy servers: `van.tdn.gtranslate.net`, `kars.tdn.gtranslate.net`, etc.
- Google Translate Widget: `translate.google.com/translate_a/element.js`

**Technical:**
- PHP CURL extension (required)
- WordPress hooks/filters
- Cookie handling for language persistence

## Migration Strategy

### Phase 1: API Integration Layer

**Objective:** Create abstraction layer for translation engines

**Implementation:**

1. **Create Translation Service Interface**
   ```
   wp-content/plugins/gtranslate/includes/
   ├── class-translation-service-interface.php
   ├── class-google-translate-service.php (legacy)
   ├── class-gemini-translate-service.php
   └── class-openai-translate-service.php
   ```

2. **Service Interface Design**
   ```php
   interface Translation_Service_Interface {
       public function translate_text($text, $source_lang, $target_lang);
       public function translate_html($html, $source_lang, $target_lang);
       public function get_supported_languages();
       public function detect_language($text);
   }
   ```

3. **Configuration Management**
   - Add API key management to admin settings
   - Engine selection: Google (legacy) | Gemini | OpenAI
   - Rate limiting configuration
   - Caching strategy settings

**Files to Create:**
- `includes/class-translation-service-interface.php`
- `includes/class-gemini-translate-service.php`
- `includes/class-openai-translate-service.php`
- `includes/class-translation-service-factory.php`

**Files to Modify:**
- `gtranslate.php` - Add settings UI for API keys and engine selection
- `url_addon/config.php` - Add translation engine configuration

### Phase 2: Gemini Integration

**Objective:** Implement Google Gemini API for translation

**API Endpoint:**
- `https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent`

**Implementation Details:**

1. **Translation Method**
   ```php
   class Gemini_Translate_Service implements Translation_Service_Interface {
       private $api_key;
       private $model = 'gemini-2.5-flash'; // Fast, cost-effective

       public function translate_text($text, $source_lang, $target_lang) {
           // Use Gemini API with structured prompt
           // POST to gemini-pro endpoint with translation instructions
       }

       public function translate_html($html, $source_lang, $target_lang) {
           // Preserve HTML structure during translation
           // Extract text nodes, translate, reconstruct
       }
   }
   ```

2. **Prompt Engineering**
   ```
   System: You are a professional translator. Translate the following text from {source_lang} to {target_lang}.
   Preserve all formatting, HTML tags, and special characters. Return only the translated text.

   Text: {content}
   ```

3. **Features**
   - Context-aware translation
   - Tone preservation
   - HTML structure maintenance
   - Batch translation support
   - Error handling with fallback

**Benefits:**
- More accurate context-aware translations
- Better handling of idioms and cultural nuances
- Cost-effective (Gemini 2.5 Flash has free tier available)
- Owned by Google (similar to current Google Translate)

### Phase 3: OpenAI Integration

**Objective:** Implement OpenAI GPT for translation

**API Endpoint:**
- `https://api.openai.com/v1/chat/completions`

**Implementation Details:**

1. **Translation Method**
   ```php
   class OpenAI_Translate_Service implements Translation_Service_Interface {
       private $api_key;
       private $model = 'gpt-4o-mini'; // Cost-effective for translation

       public function translate_text($text, $source_lang, $target_lang) {
           // Use ChatGPT with structured system prompt
           // Leverage function calling for structured output
       }

       public function translate_html($html, $source_lang, $target_lang) {
           // JSON mode for structured HTML translation
           // Preserve attributes, maintain DOM structure
       }
   }
   ```

2. **Prompt Engineering**
   ```json
   {
     "model": "gpt-4o-mini",
     "messages": [
       {
         "role": "system",
         "content": "You are a professional translator. Translate from {source_lang} to {target_lang}. Preserve HTML tags and formatting exactly."
       },
       {
         "role": "user",
         "content": "{text_to_translate}"
       }
     ],
     "temperature": 0.3
   }
   ```

3. **Features**
   - Superior translation quality
   - Context understanding across paragraphs
   - Function calling for structured output
   - Streaming support for real-time translation
   - Moderation API integration for content safety

**Benefits:**
- Highest quality translations
- Excellent context understanding
- Handles complex technical content
- Proven reliability and uptime

### Phase 4: Proxy System Replacement

**Objective:** Replace TDN proxy servers with direct API calls

**Current Flow:**
```
Browser → WordPress → CURL Proxy → {lang}.tdn.gtranslate.net → Google Translate → Response
```

**New Flow:**
```
Browser → WordPress → Translation Service → Gemini/OpenAI API → Cached Response
```

**Implementation:**

1. **url_addon/gtranslate.php Modifications**
   - Replace CURL proxy logic (lines 144-270)
   - Implement direct translation service calls
   - Add response caching layer
   - Maintain URL structure compatibility

2. **Caching Strategy**
   ```php
   class Translation_Cache {
       public function get($content_hash, $source_lang, $target_lang);
       public function set($content_hash, $source_lang, $target_lang, $translation, $ttl = 86400);
       public function invalidate($content_hash);
   }
   ```
   - Use WordPress Transients API
   - Redis/Memcached support for high-traffic sites
   - Per-page cache with 24-hour TTL
   - Cache invalidation on content update

3. **Translation Processing**
   ```php
   // New proxy handler logic
   function handle_translation_request() {
       $page_content = fetch_original_content();
       $cache_key = generate_cache_key($page_content, $source_lang, $target_lang);

       if ($cached = get_translation_cache($cache_key)) {
           return $cached;
       }

       $service = Translation_Service_Factory::create($selected_engine);
       $translated = $service->translate_html($page_content, $source_lang, $target_lang);

       set_translation_cache($cache_key, $translated);
       return $translated;
   }
   ```

**Files to Modify:**
- `url_addon/gtranslate.php` - Complete rewrite of proxy logic
- `url_addon/gtranslate-email.php` - Update email translation handler

### Phase 5: Frontend Compatibility

**Objective:** Maintain UI/UX while updating backend

**Implementation:**

1. **Language Switcher (No Changes Required)**
   - Keep all existing UI widgets (dropdown, flags, etc.)
   - Maintain URL structure options
   - Preserve language detection logic

2. **JavaScript Updates (js/base.js)**
   - Remove Google Translate Widget dependency (lines 99-101)
   - Implement AJAX-based translation for 'none' mode
   - Add loading states for API calls
   - Error handling for failed translations

3. **Free Mode Alternative**
   ```javascript
   // Replace Google Translate Widget with API calls
   function doTranslate(target_lang) {
       showLoadingState();

       fetch('/wp-json/gtranslate/v1/translate', {
           method: 'POST',
           body: JSON.stringify({
               content: document.documentElement.outerHTML,
               target_lang: target_lang
           })
       })
       .then(response => response.json())
       .then(data => {
           document.open();
           document.write(data.translated_html);
           document.close();
       });
   }
   ```

**Files to Modify:**
- `js/base.js` - Update translation trigger logic (optional for Pro/Enterprise modes)

### Phase 6: Settings & Configuration UI

**Objective:** Admin interface for AI engine management

**Settings Panel Additions:**

1. **Translation Engine Section**
   ```
   [ ] Google Translate (Legacy - via proxy)
   [ ] Google Gemini (Recommended)
   [ ] OpenAI GPT

   API Configuration:
   - Gemini API Key: [__________] [Test Connection]
   - OpenAI API Key: [__________] [Test Connection]
   ```

2. **Advanced Settings**
   ```
   Translation Quality:
   - [ ] Standard (faster, lower cost)
   - [ ] High (slower, better quality)

   Caching:
   - Cache Duration: [24] hours
   - Cache Backend: [WordPress Transients ▼]

   Rate Limiting:
   - Max Requests/Minute: [60]
   - Queue Failed Translations: [✓]
   ```

3. **Migration Tools**
   ```
   [Clear Translation Cache]
   [Test Translation] - Sample translation test
   [View API Usage] - Gemini/OpenAI usage statistics
   [Export/Import Settings]
   ```

**Files to Modify:**
- `gtranslate.php` - Admin settings UI (around lines 900-1400)

### Phase 7: Testing & Validation

**Test Coverage:**

1. **Unit Tests**
   - Translation service interface implementations
   - Cache mechanism
   - Language code mapping
   - HTML structure preservation

2. **Integration Tests**
   - Full page translation (Pro/Enterprise modes)
   - Email translation
   - PDF translation
   - REST API translation

3. **Performance Tests**
   - API response time benchmarks
   - Cache hit ratio measurement
   - Concurrent request handling
   - Cost analysis (API calls vs. cache hits)

4. **Compatibility Tests**
   - WordPress 5.0+ compatibility
   - PHP 7.4+ compatibility
   - Common plugin conflicts (WooCommerce, WPML, etc.)
   - Theme compatibility

**Test Files to Create:**
```
tests/
├── unit/
│   ├── test-gemini-service.php
│   ├── test-openai-service.php
│   └── test-translation-cache.php
├── integration/
│   ├── test-page-translation.php
│   ├── test-email-translation.php
│   └── test-rest-api.php
└── performance/
    └── test-api-benchmarks.php
```

## Migration Path Options

### Option A: Gradual Migration (Recommended)

**Timeline:** 8-12 weeks

**Phases:**
1. Week 1-2: Create abstraction layer, maintain Google Translate as default
2. Week 3-4: Implement Gemini service, beta testing with opt-in flag
3. Week 5-6: Implement OpenAI service, A/B testing
4. Week 7-8: Replace proxy system for opted-in users
5. Week 9-10: Performance optimization, caching improvements
6. Week 11-12: Full rollout, deprecate Google Translate proxy

**Benefits:**
- Low risk - can rollback at any phase
- User testing and feedback collection
- Gradual infrastructure scaling
- Time to optimize costs and performance

### Option B: Direct Migration

**Timeline:** 4-6 weeks

**Phases:**
1. Week 1-2: Implement full abstraction layer and both AI services
2. Week 3-4: Replace proxy system completely
3. Week 5-6: Testing, bug fixes, optimization

**Benefits:**
- Faster time to market
- Immediate cost savings (if applicable)
- Simpler code maintenance

**Risks:**
- Higher initial failure risk
- Limited rollback options
- Potential user disruption

## Cost Analysis

### Current (Google Translate via Proxy)

**Estimated Monthly Cost:**
- GTranslate Pro: $5.99/month (sub_directory mode)
- GTranslate Enterprise: $14.99/month (sub_domain mode)
- Free tier: $0 (widget mode, limited)

### Gemini API

**Pricing (as of 2026):**
- Gemini 2.5 Flash: Free tier available
- Input: $0.15 per 1M tokens (paid tier)
- Output: $0.60 per 1M tokens (paid tier)

**Example Cost (10,000 page views/month):**
- Avg page: 2000 words ≈ 2700 tokens
- Total tokens: 27M input + 27M output = 54M tokens
- Cost: (27M × $0.15) + (27M × $0.60) = $4.05 + $16.20 = $20.25/month
- **With 80% cache hit rate: $4.05/month**

### OpenAI API

**Pricing (as of 2026):**
- GPT-4.1 Mini: $0.40 per 1M input tokens, $1.60 per 1M output tokens
- GPT-4.1: $2.00 per 1M input tokens, $8.00 per 1M output tokens

**Example Cost (10,000 page views/month, GPT-4.1 Mini):**
- Same calculation as Gemini
- Cost: (27M × $0.40) + (27M × $1.60) = $10.80 + $43.20 = $54.00/month
- **With 80% cache hit rate: $10.80/month**

### Recommendation

**Best Value:** Gemini 2.5 Flash
- Free tier for low-volume sites
- Lower cost than OpenAI for high-volume
- Good translation quality
- Google ecosystem integration

**Premium Quality:** OpenAI GPT-4o-mini
- Superior translation for complex/technical content
- Better context understanding
- Worth the premium for professional sites

## Technical Risks & Mitigation

### Risk 1: API Rate Limiting

**Impact:** Translation failures during traffic spikes

**Mitigation:**
- Implement request queuing system
- Progressive retry with exponential backoff
- Fallback to cached versions
- Queue management UI for admins

### Risk 2: API Cost Overruns

**Impact:** Unexpected high monthly costs

**Mitigation:**
- Aggressive caching (80%+ hit rate target)
- Rate limiting per user/IP
- Cost monitoring dashboard
- Spending alerts and hard caps

### Risk 3: Translation Quality Issues

**Impact:** Poor user experience, inaccurate translations

**Mitigation:**
- A/B testing against Google Translate
- Manual review system for critical pages
- User feedback mechanism
- Option to edit/override translations

### Risk 4: API Downtime

**Impact:** Site translation unavailable

**Mitigation:**
- Multi-provider fallback (Gemini → OpenAI → Google)
- Aggressive cache with extended TTL during outages
- Health check monitoring
- Graceful degradation to original language

### Risk 5: Breaking Changes in Existing URLs

**Impact:** SEO damage, broken links

**Mitigation:**
- Maintain exact URL structure compatibility
- Extensive testing of Pro/Enterprise modes
- Redirect rules for any changes
- Gradual rollout with monitoring

## Success Criteria

### Performance Metrics

- **Translation Quality:** >90% accuracy vs. Google Translate baseline
- **Response Time:** <2s for cached, <5s for fresh translations
- **Cache Hit Rate:** >80% for stable content
- **API Cost:** <$20/month for average site (10k views)
- **Uptime:** 99.5% translation availability

### Business Metrics

- **User Satisfaction:** Net Promoter Score >7.0
- **Support Tickets:** <5% increase during migration
- **Migration Success:** >95% sites upgraded without issues
- **Revenue Impact:** Neutral or positive (potential for premium AI tier)

## Implementation Checklist

### Pre-Development

- [ ] Finalize migration approach (Option A vs B)
- [ ] Obtain Gemini/OpenAI API keys for development
- [ ] Set up staging environment
- [ ] Create test site with various content types

### Development

- [ ] Create translation service interface
- [ ] Implement Gemini translation service
- [ ] Implement OpenAI translation service
- [ ] Build translation cache system
- [ ] Update proxy handler (url_addon/gtranslate.php)
- [ ] Add admin settings UI
- [ ] Implement API key validation
- [ ] Create migration scripts

### Testing

- [ ] Unit test coverage >80%
- [ ] Integration tests for all translation modes
- [ ] Performance benchmarking
- [ ] Security audit (API key storage, XSS prevention)
- [ ] Cross-browser testing
- [ ] Mobile responsiveness
- [ ] SEO impact assessment

### Deployment

- [ ] Beta release to opt-in users
- [ ] Monitoring dashboard setup
- [ ] Documentation update
- [ ] Migration guide for existing users
- [ ] Support team training
- [ ] Gradual rollout plan
- [ ] Rollback procedures

### Post-Launch

- [ ] Monitor API costs daily
- [ ] Track translation quality metrics
- [ ] Collect user feedback
- [ ] Optimize cache strategy
- [ ] Performance tuning
- [ ] Consider deprecation timeline for Google proxy

## File Structure After Migration

```
wp-content/plugins/gtranslate/
├── gtranslate.php (modified - add settings, engine selection)
├── includes/ (new)
│   ├── class-translation-service-interface.php
│   ├── class-google-translate-service.php (legacy wrapper)
│   ├── class-gemini-translate-service.php
│   ├── class-openai-translate-service.php
│   ├── class-translation-service-factory.php
│   ├── class-translation-cache.php
│   └── class-api-key-manager.php
├── url_addon/
│   ├── gtranslate.php (modified - new translation logic)
│   ├── gtranslate-email.php (modified)
│   └── config.php (modified - add engine config)
├── js/
│   └── base.js (modified - optional for free mode)
├── admin/
│   ├── settings-page.php (new - API configuration UI)
│   └── usage-dashboard.php (new - cost monitoring)
└── tests/ (new)
    ├── unit/
    ├── integration/
    └── performance/
```

## Next Steps

1. **Immediate Actions:**
   - Set up development API keys (Gemini + OpenAI)
   - Create proof-of-concept for single page translation
   - Benchmark translation quality vs. Google Translate
   - Calculate projected costs for production

2. **Week 1 Goals:**
   - Implement translation service interface
   - Basic Gemini integration working
   - Simple cache mechanism
   - Admin API key input UI

3. **Decision Points:**
   - Choose migration path (Option A vs B)
   - Select primary engine (Gemini vs OpenAI vs both)
   - Define cache strategy (Transients vs Redis)
   - Set budget limits and monitoring

## Conclusion

This migration plan provides a comprehensive roadmap to transition from Google Translate proxy to modern AI translation engines (Gemini/OpenAI). The recommended approach is gradual migration (Option A) with Gemini as the primary engine due to cost-effectiveness and quality balance.

Key advantages of the new architecture:
- **Better Quality:** Context-aware AI translations
- **More Control:** Direct API integration, customizable prompts
- **Cost Efficient:** Aggressive caching reduces API costs significantly
- **Future-Proof:** Abstraction layer allows easy engine switching
- **Maintainable:** Clean architecture, testable components

The migration can be completed in 8-12 weeks with proper testing and validation, ensuring a smooth transition for existing users while providing superior translation capabilities.
