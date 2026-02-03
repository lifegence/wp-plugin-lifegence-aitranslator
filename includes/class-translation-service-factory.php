<?php
/**
 * Translation Service Factory
 *
 * @package LIFEAI_AITranslator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory class for creating translation service instances
 */
class LIFEAI_Translation_Service_Factory {

    /**
     * Create translation service instance based on settings
     *
     * @return LIFEAI_Translation_Service_Interface
     * @throws Exception If provider is not supported or API key is missing
     */
    public static function create() {
        $settings = get_option('lifeai_aitranslator_settings', array());
        $provider = $settings['provider'] ?? 'gemini';

        switch ($provider) {
            case 'gemini':
                return new LIFEAI_Gemini_Translation_Service();

            case 'openai':
                return new LIFEAI_OpenAI_Translation_Service();

            default:
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new Exception(
                    /* translators: %s: Provider name */
                    sprintf(__('Unsupported translation provider: %s', 'lifegence-aitranslator'), $provider)
                );
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    /**
     * Get available providers
     *
     * @return array
     */
    public static function get_providers() {
        return array(
            'gemini' => array(
                'name' => __('Google Gemini', 'lifegence-aitranslator'),
                'description' => __('Fast and cost-effective AI translation', 'lifegence-aitranslator'),
                'models' => array(
                    'gemini-2.5-flash' => __('Gemini 2.5 Flash (Recommended)', 'lifegence-aitranslator'),
                    'gemini-2.5-pro' => __('Gemini 2.5 Pro (Higher Quality)', 'lifegence-aitranslator'),
                    'gemini-2.5-flash-lite' => __('Gemini 2.5 Flash-Lite (Budget)', 'lifegence-aitranslator')
                )
            ),
            'openai' => array(
                'name' => __('OpenAI GPT', 'lifegence-aitranslator'),
                'description' => __('Premium quality AI translation', 'lifegence-aitranslator'),
                'models' => array(
                    'gpt-4.1-mini' => __('GPT-4.1 Mini (Recommended)', 'lifegence-aitranslator'),
                    'gpt-4.1' => __('GPT-4.1 (Highest Quality)', 'lifegence-aitranslator'),
                    'gpt-4o-mini' => __('GPT-4o Mini (Budget)', 'lifegence-aitranslator')
                )
            )
        );
    }
}
