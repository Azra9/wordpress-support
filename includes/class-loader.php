<?php
/**
 * Class Loader
 * Simple class autoloader for WPSPT plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_Loader {
    private static $classes = [];

    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Require specific classes
     */
    public static function require_classes($classes) {
        foreach ($classes as $class_name => $file_path) {
            self::$classes[$class_name] = WPSPT_PLUGIN_DIR . $file_path;
            if (file_exists(self::$classes[$class_name])) {
                require_once self::$classes[$class_name];
            }
        }
    }

    /**
     * Autoload classes
     */
    public static function autoload($class_name) {
        if (strpos($class_name, 'WPSPT_') !== 0) {
            return;
        }

        if (isset(self::$classes[$class_name])) {
            require_once self::$classes[$class_name];
            return;
        }

        $file_name = 'class-' . strtolower(str_replace('_', '-', substr($class_name, 6))) . '.php';

        $paths = [
            WPSPT_PLUGIN_DIR . 'includes/' . $file_name,
            WPSPT_PLUGIN_DIR . 'includes/core/' . $file_name,
            WPSPT_PLUGIN_DIR . 'includes/admin/' . $file_name,
            WPSPT_PLUGIN_DIR . 'includes/client/' . $file_name,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    }
}
