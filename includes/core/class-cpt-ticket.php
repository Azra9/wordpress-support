<?php
/**
 * Custom Post Type: Support Ticket
 * Registers and manages the support ticket CPT
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_CPT_Ticket {

    /**
     * Register the custom post type
     */
    public static function register_cpt() {
        $labels = [
            'name'                  => __('Support Tickets', 'wpspt'),
            'singular_name'         => __('Support Ticket', 'wpspt'),
            'menu_name'             => __('Support Tickets', 'wpspt'),
            'name_admin_bar'        => __('Support Ticket', 'wpspt'),
            'add_new'               => __('Add New', 'wpspt'),
            'add_new_item'          => __('Add New Ticket', 'wpspt'),
            'new_item'              => __('New Ticket', 'wpspt'),
            'edit_item'             => __('Edit Ticket', 'wpspt'),
            'view_item'             => __('View Ticket', 'wpspt'),
            'all_items'             => __('All Tickets', 'wpspt'),
            'search_items'          => __('Search Tickets', 'wpspt'),
            'not_found'             => __('No tickets found.', 'wpspt'),
            'not_found_in_trash'    => __('No tickets found in Trash.', 'wpspt'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => false,
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-tickets-alt',
            'supports'              => ['title', 'editor', 'author'],
            'show_in_rest'          => false,
        ];

        register_post_type('wpspt_ticket', $args);
    }

    /**
     * Register custom post statuses
     */
    public static function register_statuses() {
        register_post_status('wpspt_open', [
            'label'                     => _x('Open', 'post status', 'wpspt'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'wpspt'),
        ]);

        register_post_status('wpspt_in_progress', [
            'label'                     => _x('In Progress', 'post status', 'wpspt'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('In Progress <span class="count">(%s)</span>', 'In Progress <span class="count">(%s)</span>', 'wpspt'),
        ]);

        register_post_status('wpspt_resolved', [
            'label'                     => _x('Resolved', 'post status', 'wpspt'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Resolved <span class="count">(%s)</span>', 'Resolved <span class="count">(%s)</span>', 'wpspt'),
        ]);

        register_post_status('wpspt_closed', [
            'label'                     => _x('Closed', 'post status', 'wpspt'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wpspt'),
        ]);
    }

    /**
     * Get all ticket statuses
     */
    public static function get_statuses() {
        return [
            'wpspt_open'        => __('Open', 'wpspt'),
            'wpspt_in_progress' => __('In Progress', 'wpspt'),
            'wpspt_resolved'    => __('Resolved', 'wpspt'),
            'wpspt_closed'      => __('Closed', 'wpspt'),
        ];
    }

    /**
     * Create a new ticket
     */
    public static function create_ticket($user_id, $title, $description, $ticket_type) {
        $ticket_id = wp_insert_post([
            'post_type'     => 'wpspt_ticket',
            'post_title'    => sanitize_text_field($title),
            'post_content'  => wp_kses_post($description),
            'post_status'   => 'wpspt_open',
            'post_author'   => $user_id,
        ]);

        if (is_wp_error($ticket_id)) {
            return false;
        }

        // Save ticket type as meta
        update_post_meta($ticket_id, '_wpspt_ticket_type', sanitize_text_field($ticket_type));
        update_post_meta($ticket_id, '_wpspt_created_at', current_time('mysql'));

        return $ticket_id;
    }

    /**
     * Get tickets by user
     */
    public static function get_user_tickets($user_id, $status = '') {
        $args = [
            'post_type'      => 'wpspt_ticket',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($status)) {
            $args['post_status'] = $status;
        } else {
            $args['post_status'] = ['wpspt_open', 'wpspt_in_progress', 'wpspt_resolved', 'wpspt_closed'];
        }

        return get_posts($args);
    }

    /**
     * Get all tickets (admin)
     */
    public static function get_all_tickets($status = '', $limit = -1) {
        $args = [
            'post_type'      => 'wpspt_ticket',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($status)) {
            $args['post_status'] = $status;
        } else {
            $args['post_status'] = ['wpspt_open', 'wpspt_in_progress', 'wpspt_resolved', 'wpspt_closed'];
        }

        return get_posts($args);
    }

    /**
     * Update ticket status
     */
    public static function update_status($ticket_id, $status) {
        $valid_statuses = array_keys(self::get_statuses());

        if (!in_array($status, $valid_statuses)) {
            return false;
        }

        return wp_update_post([
            'ID'          => $ticket_id,
            'post_status' => $status,
        ]);
    }
}
