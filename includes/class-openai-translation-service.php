<?php
/**
 * OpenAI Translation Service
 *
 * @package LIFEAI_AITranslator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI GPT translation service implementation
 */
class LIFEAI_OpenAI_Translation_Service extends LIFEAI_Abstract_Translation_Service {

    /**
     * Constructor
     *
     * @throws Exception If API key is not configured
     */
    public function __construct() {
        parent::__construct('openai', 'gpt-4.1-mini');
    }

    /**
     * Call OpenAI API to translate text
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @return string Translated text
     * @throws Exception If translation fails
     */
    protected function call_translation_api($text, $source_lang, $target_lang) {
        $all_languages = LIFEAI_AITranslator::get_all_languages();
        $source_name = $all_languages[$source_lang] ?? $source_lang;
        $target_name = $all_languages[$target_lang] ?? $target_lang;

        // Build messages
        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->build_system_message($source_name, $target_name)
            ),
            array(
                'role' => 'user',
                'content' => $text
            )
        );

        // Make API request
        $url = 'https://api.openai.com/v1/chat/completions';

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => floatval($this->temperature),
            'max_tokens' => 4096
        );

        $response = wp_remote_post($url, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body)
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            /* translators: 1: HTTP status code, 2: Error message from API */
            throw new Exception(sprintf(__('OpenAI API error (code %1$d): %2$s', 'lifegence-aitranslator'), $code, $error_body)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($result['choices'][0]['message']['content'])) {
            throw new Exception(__('Invalid response from OpenAI API', 'lifegence-aitranslator')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return trim($result['choices'][0]['message']['content']);
    }

    /**
     * Validate credentials
     *
     * @return array
     */
    public function validate_credentials() {
        $key_manager = new LIFEAI_API_Key_Manager();
        return $key_manager->validate_openai_key($this->api_key);
    }
}
