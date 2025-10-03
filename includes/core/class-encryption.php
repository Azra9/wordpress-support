<?php
/**
 * Encryption Handler
 * Handles encryption/decryption of sensitive data like credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_Encryption {

    private static $key_option = 'wpspt_encryption_key';

    /**
     * Ensure encryption key exists
     */
    public static function ensure_key() {
        $key = get_option(self::$key_option);

        if (empty($key)) {
            $key = self::generate_key();
            update_option(self::$key_option, $key, false); // false = don't autoload
        }

        return $key;
    }

    /**
     * Generate a random encryption key
     */
    private static function generate_key() {
        if (function_exists('random_bytes')) {
            return base64_encode(random_bytes(32));
        } else {
            return base64_encode(openssl_random_pseudo_bytes(32));
        }
    }

    /**
     * Get the encryption key
     */
    private static function get_key() {
        $key = get_option(self::$key_option);

        if (empty($key)) {
            $key = self::ensure_key();
        }

        return base64_decode($key);
    }

    /**
     * Encrypt data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }

        $key = self::get_key();
        $cipher = 'aes-256-cbc';

        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return '';
        }

        $key = self::get_key();
        $cipher = 'aes-256-cbc';

        $data = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher);

        // Extract IV from the beginning
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);

        return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Encrypt credentials array
     */
    public static function encrypt_credentials($credentials) {
        $encrypted = [];

        if (!empty($credentials['username'])) {
            $encrypted['username_encrypted'] = self::encrypt($credentials['username']);
        }

        if (!empty($credentials['password'])) {
            $encrypted['password_encrypted'] = self::encrypt($credentials['password']);
        }

        if (!empty($credentials['notes'])) {
            $encrypted['notes_encrypted'] = self::encrypt($credentials['notes']);
        }

        return $encrypted;
    }

    /**
     * Decrypt credentials array
     */
    public static function decrypt_credentials($credentials) {
        $decrypted = [];

        if (!empty($credentials->username_encrypted)) {
            $decrypted['username'] = self::decrypt($credentials->username_encrypted);
        }

        if (!empty($credentials->password_encrypted)) {
            $decrypted['password'] = self::decrypt($credentials->password_encrypted);
        }

        if (!empty($credentials->notes_encrypted)) {
            $decrypted['notes'] = self::decrypt($credentials->notes_encrypted);
        }

        return $decrypted;
    }
}
