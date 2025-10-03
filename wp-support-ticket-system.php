<?php
/*
Plugin Name: WP Support Pro - Credit-Based Support Ticket System
Plugin URI: https://example.com/
Description: A credit-based support ticket system with client dashboard, secure website credential storage, and ticket conversations.
Version: 0.1.0
Author: WP Support Pro
Author URI: https://example.com/
Text Domain: wpspt
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
if (!defined('WPSPT_VERSION')) define('WPSPT_VERSION', '0.1.0');
if (!defined('WPSPT_PLUGIN_FILE')) define('WPSPT_PLUGIN_FILE', __FILE__);
if (!defined('WPSPT_PLUGIN_DIR')) define('WPSPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('WPSPT_PLUGIN_URL')) define('WPSPT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load class loader
require_once WPSPT_PLUGIN_DIR . 'includes/class-loader.php';
WPSPT_Loader::register();

// Require core classes early
WPSPT_Loader::require_classes([
    'WPSPT_Database' => 'includes/core/class-database.php',
    'WPSPT_CPT_Ticket' => 'includes/core/class-cpt-ticket.php',
    'WPSPT_Encryption' => 'includes/core/class-encryption.php',
    'WPSPT_Client_Dashboard' => 'includes/client/class-client-dashboard.php',
    'WPSPT_Admin' => 'includes/admin/class-admin.php',
]);

// Activation / Deactivation / Uninstall
register_activation_hook(__FILE__, function() {
    // Add role wpcustomer with basic capabilities
    add_role('wpcustomer', __('WP Customer', 'wpspt'), [
        'read' => true,
        'read_private_posts' => true,
        'edit_posts' => false,
        'upload_files' => false,
    ]);

    // Ensure encryption key exists
    WPSPT_Encryption::ensure_key();

    // Create DB tables
    WPSPT_Database::install();

    // Register custom post type and statuses for flushing rewrite rules
    WPSPT_CPT_Ticket::register_cpt();
    WPSPT_CPT_Ticket::register_statuses();
    flush_rewrite_rules();

    // Add default ticket types if not set
    if (!get_option('wpspt_ticket_types')) {
        $defaults = [
            ['id' => 'small_fix', 'label' => __('Small Fix - 1 Credit', 'wpspt'), 'credits' => 1],
            ['id' => 'theme_setup', 'label' => __('Theme Setup - 3 Credits', 'wpspt'), 'credits' => 3],
        ];
        update_option('wpspt_ticket_types', $defaults);
    }
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Bootstrap plugin
add_action('init', function() {
    load_plugin_textdomain('wpspt', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Register CPT and statuses
    WPSPT_CPT_Ticket::register_cpt();
    WPSPT_CPT_Ticket::register_statuses();
});

add_action('plugins_loaded', function() {
    // Init Admin and Client modules
    if (is_admin()) {
        WPSPT_Admin::init();
    }
    WPSPT_Client_Dashboard::init();
});

// Utility: capability check for dashboard access
function wpspt_current_user_can_access_dashboard() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('administrator')) return true;
    $user = wp_get_current_user();
    return in_array('wpcustomer', (array) $user->roles, true);
}
