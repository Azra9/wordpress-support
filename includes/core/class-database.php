<?php
/**
 * Database Manager
 * Handles custom database tables for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_Database {

    /**
     * Install/Create database tables
     */
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table for ticket conversations/replies
        $table_conversations = $wpdb->prefix . 'wpspt_conversations';
        $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            message longtext NOT NULL,
            is_admin tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Table for website credentials (encrypted)
        $table_credentials = $wpdb->prefix . 'wpspt_credentials';
        $sql_credentials = "CREATE TABLE IF NOT EXISTS $table_credentials (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            site_url varchar(255) DEFAULT '',
            admin_url varchar(255) DEFAULT '',
            username_encrypted text,
            password_encrypted text,
            notes_encrypted text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Table for user credits
        $table_credits = $wpdb->prefix . 'wpspt_credits';
        $sql_credits = "CREATE TABLE IF NOT EXISTS $table_credits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            credits int(11) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_conversations);
        dbDelta($sql_credentials);
        dbDelta($sql_credits);
    }

    /**
     * Get user credits
     */
    public static function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_credits';
        $credits = $wpdb->get_var($wpdb->prepare(
            "SELECT credits FROM $table WHERE user_id = %d",
            $user_id
        ));
        return $credits !== null ? (int)$credits : 0;
    }

    /**
     * Update user credits
     */
    public static function update_user_credits($user_id, $credits) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_credits';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                ['credits' => $credits],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                ['user_id' => $user_id, 'credits' => $credits],
                ['%d', '%d']
            );
        }
    }

    /**
     * Add conversation/reply to ticket
     */
    public static function add_conversation($ticket_id, $user_id, $message, $is_admin = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_conversations';

        $wpdb->insert(
            $table,
            [
                'ticket_id' => $ticket_id,
                'user_id' => $user_id,
                'message' => $message,
                'is_admin' => $is_admin ? 1 : 0
            ],
            ['%d', '%d', '%s', '%d']
        );

        return $wpdb->insert_id;
    }

    /**
     * Get ticket conversations
     */
    public static function get_conversations($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_conversations';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket_id
        ));
    }

    /**
     * Save ticket credentials
     */
    public static function save_credentials($ticket_id, $user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_credentials';

        // Check if credentials already exist for this ticket
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE ticket_id = %d",
            $ticket_id
        ));

        $credentials_data = [
            'ticket_id' => $ticket_id,
            'user_id' => $user_id,
            'site_url' => !empty($data['site_url']) ? $data['site_url'] : '',
            'admin_url' => !empty($data['admin_url']) ? $data['admin_url'] : '',
            'username_encrypted' => !empty($data['username_encrypted']) ? $data['username_encrypted'] : '',
            'password_encrypted' => !empty($data['password_encrypted']) ? $data['password_encrypted'] : '',
            'notes_encrypted' => !empty($data['notes_encrypted']) ? $data['notes_encrypted'] : '',
        ];

        if ($existing) {
            $wpdb->update(
                $table,
                $credentials_data,
                ['ticket_id' => $ticket_id],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                $credentials_data,
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    /**
     * Get ticket credentials
     */
    public static function get_credentials($ticket_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpspt_credentials';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE ticket_id = %d",
            $ticket_id
        ));
    }
}
