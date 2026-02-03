<?php
/**
 * API Key Manager
 *
 * @package LIFEAI_AITranslator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages API key encryption and validation
 */
class LIFEAI_API_Key_Manager {

    /**
     * Encryption key
     */
    private $encryption_key;

    /**
     * Constructor
     */
    public function __construct() {
        // Use WordPress auth keys for encryption
        $this->encryption_key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }

    /**
     * Encrypt API key
     *
     * @param string $plain_key Plain text API key
     * @return string Encrypted key
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

        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted_key Encrypted API key
     * @return string Plain text API key
     */
    public function decrypt_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        $data = base64_decode($encrypted_key);
        $parts = explode('::', $data, 2);

        if (count($parts) !== 2) {
            return '';
        }

        list($iv, $encrypted) = $parts;

        return openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $this->encryption_key,
            0,
            $iv
        );
    }

    /**
     * Validate Gemini API key
     *
     * @param string $api_key API key to validate
     * @return array Validation result
     */
    public function validate_gemini_key($api_key) {
        // Use Gemini 2.5 Flash model
        $test_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

        $response = wp_remote_post($test_url, array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => 'Hello')
                        )
                    )
                )
            ))
        ));

        if (is_wp_error($response)) {
            LIFEAI_Error_Handler::debug('Gemini API validation error', array('error' => $response->get_error_message()));
            return array(
                'valid' => false,
                'error' => $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        LIFEAI_Error_Handler::debug('Gemini API validation response', array('code' => $code, 'body_preview' => substr($body, 0, 500)));

        if ($code !== 200) {
            $body_data = json_decode($body, true);
            $error_message = __('Invalid API key or request failed', 'lifegence-aitranslator');

            if (isset($body_data['error']['message'])) {
                $error_message = $body_data['error']['message'];
            } elseif (isset($body_data['error']['status'])) {
                $error_message = $body_data['error']['status'] . ': ' . ($body_data['error']['message'] ?? 'Unknown error');
            }

            return array(
                'valid' => false,
                'error' => $error_message
            );
        }

        return array(
            'valid' => true,
            'error' => null
        );
    }

    /**
     * Validate OpenAI API key
     *
     * @param string $api_key API key to validate
     * @return array Validation result
     */
    public function validate_openai_key($api_key) {
        $test_url = 'https://api.openai.com/v1/models';

        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'error' => $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        return array(
            'valid' => $code === 200,
            'error' => $code !== 200 ? __('Invalid API key', 'lifegence-aitranslator') : null
        );
    }

    /**
     * Get stored API key for a provider
     *
     * @param string $provider Provider name (gemini or openai)
     * @return string Decrypted API key
     */
    public function get_api_key($provider) {
        $settings = get_option('lifeai_aitranslator_settings', array());
        $key_field = $provider . '_api_key';

        if (empty($settings[$key_field])) {
            return '';
        }

        return $this->decrypt_key($settings[$key_field]);
    }

    /**
     * Store API key for a provider
     *
     * @param string $provider Provider name
     * @param string $api_key API key to store
     */
    public function store_api_key($provider, $api_key) {
        $settings = get_option('lifeai_aitranslator_settings', array());
        $key_field = $provider . '_api_key';

        if (!empty($api_key)) {
            $settings[$key_field] = $this->encrypt_key($api_key);
            $settings[$key_field . '_display'] = substr($api_key, 0, 10) . '...';
        }

        update_option('lifeai_aitranslator_settings', $settings);
    }
}
