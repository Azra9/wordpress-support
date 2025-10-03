<?php
/**
 * Admin Interface
 * Handles admin dashboard for managing tickets
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_Admin {

    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_wpspt_ticket', [__CLASS__, 'save_ticket_meta']);

        // AJAX handlers
        add_action('wp_ajax_wpspt_admin_add_reply', [__CLASS__, 'ajax_admin_add_reply']);
        add_action('wp_ajax_wpspt_admin_update_status', [__CLASS__, 'ajax_admin_update_status']);
        add_action('wp_ajax_wpspt_admin_add_credits', [__CLASS__, 'ajax_admin_add_credits']);
        add_action('wp_ajax_wpspt_update_user_credits', [__CLASS__, 'ajax_update_user_credits']);

        // Custom columns
        add_filter('manage_wpspt_ticket_posts_columns', [__CLASS__, 'ticket_columns']);
        add_action('manage_wpspt_ticket_posts_custom_column', [__CLASS__, 'ticket_column_content'], 10, 2);

        // User list credits column
        add_filter('manage_users_columns', [__CLASS__, 'add_user_credits_column']);
        add_filter('manage_users_custom_column', [__CLASS__, 'display_user_credits_column'], 10, 3);
        add_action('admin_footer-users.php', [__CLASS__, 'add_user_credits_inline_edit']);
    }

    /**
     * Add admin menu pages
     */
    public static function add_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=wpspt_ticket',
            __('Ticket Settings', 'wpspt'),
            __('Settings', 'wpspt'),
            'manage_options',
            'wpspt-settings',
            [__CLASS__, 'render_settings_page']
        );

        add_submenu_page(
            'edit.php?post_type=wpspt_ticket',
            __('Manage Credits', 'wpspt'),
            __('User Credits', 'wpspt'),
            'manage_options',
            'wpspt-credits',
            [__CLASS__, 'render_credits_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        global $post_type;

        if ('wpspt_ticket' === $post_type || strpos($hook, 'wpspt') !== false) {
            wp_enqueue_style('wpspt-admin', WPSPT_PLUGIN_URL . 'assets/admin.css', [], WPSPT_VERSION);
            wp_enqueue_script('wpspt-admin', WPSPT_PLUGIN_URL . 'assets/admin.js', ['jquery'], WPSPT_VERSION, true);

            wp_localize_script('wpspt-admin', 'wpsptAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpspt_admin_nonce'),
            ]);
        }
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'wpspt_ticket_details',
            __('Ticket Details', 'wpspt'),
            [__CLASS__, 'render_ticket_details_metabox'],
            'wpspt_ticket',
            'normal',
            'high'
        );

        add_meta_box(
            'wpspt_ticket_conversation',
            __('Ticket Conversation', 'wpspt'),
            [__CLASS__, 'render_conversation_metabox'],
            'wpspt_ticket',
            'normal',
            'high'
        );

        add_meta_box(
            'wpspt_ticket_credentials',
            __('Website Credentials', 'wpspt'),
            [__CLASS__, 'render_credentials_metabox'],
            'wpspt_ticket',
            'side',
            'default'
        );

        add_meta_box(
            'wpspt_ticket_status',
            __('Ticket Status', 'wpspt'),
            [__CLASS__, 'render_status_metabox'],
            'wpspt_ticket',
            'side',
            'high'
        );
    }

    /**
     * Render ticket details metabox
     */
    public static function render_ticket_details_metabox($post) {
        $ticket_type = get_post_meta($post->ID, '_wpspt_ticket_type', true);
        $created_at = get_post_meta($post->ID, '_wpspt_created_at', true);
        $author = get_user_by('ID', $post->post_author);
        $credits = WPSPT_Database::get_user_credits($post->post_author);

        ?>
        <table class="wpspt-details-table">
            <tr>
                <th><?php _e('Client:', 'wpspt'); ?></th>
                <td><?php echo esc_html($author->display_name); ?> (<?php echo esc_html($author->user_email); ?>)</td>
            </tr>
            <tr>
                <th><?php _e('Client Credits:', 'wpspt'); ?></th>
                <td><?php echo esc_html($credits); ?></td>
            </tr>
            <tr>
                <th><?php _e('Ticket Type:', 'wpspt'); ?></th>
                <td><?php echo esc_html($ticket_type); ?></td>
            </tr>
            <tr>
                <th><?php _e('Created:', 'wpspt'); ?></th>
                <td><?php echo esc_html($created_at ? $created_at : get_the_date('Y-m-d H:i:s', $post)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Description:', 'wpspt'); ?></th>
                <td><?php echo wpautop(wp_kses_post($post->post_content)); ?></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render conversation metabox
     */
    public static function render_conversation_metabox($post) {
        $conversations = WPSPT_Database::get_conversations($post->ID);
        wp_nonce_field('wpspt_conversation_nonce', 'wpspt_conversation_nonce_field');

        ?>
        <div class="wpspt-conversation-container">
            <div class="wpspt-conversation-messages">
                <?php if (empty($conversations)): ?>
                    <p class="wpspt-no-messages"><?php _e('No messages yet.', 'wpspt'); ?></p>
                <?php else: ?>
                    <?php foreach ($conversations as $conv):
                        $user = get_user_by('ID', $conv->user_id);
                        $is_admin_msg = (bool)$conv->is_admin;
                    ?>
                        <div class="wpspt-message <?php echo $is_admin_msg ? 'wpspt-message-admin' : 'wpspt-message-client'; ?>">
                            <div class="wpspt-message-header">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <?php if ($is_admin_msg): ?>
                                    <span class="wpspt-badge wpspt-badge-admin"><?php _e('Admin', 'wpspt'); ?></span>
                                <?php endif; ?>
                                <span class="wpspt-message-date"><?php echo esc_html($conv->created_at); ?></span>
                            </div>
                            <div class="wpspt-message-content">
                                <?php echo wpautop(wp_kses_post($conv->message)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="wpspt-conversation-reply">
                <h4><?php _e('Add Reply', 'wpspt'); ?></h4>
                <textarea id="wpspt-admin-reply" rows="5" placeholder="<?php esc_attr_e('Type your reply here...', 'wpspt'); ?>"></textarea>
                <button type="button" class="button button-primary" id="wpspt-admin-reply-btn" data-ticket-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e('Send Reply', 'wpspt'); ?>
                </button>
                <span class="wpspt-reply-status"></span>
            </div>
        </div>

        <style>
            .wpspt-conversation-messages { max-height: 400px; overflow-y: auto; margin-bottom: 20px; padding: 10px; background: #f9f9f9; border-radius: 4px; }
            .wpspt-message { margin-bottom: 15px; padding: 12px; border-radius: 6px; border-left: 4px solid #ccc; }
            .wpspt-message-admin { background: #e7f3ff; border-left-color: #2271b1; }
            .wpspt-message-client { background: #fff; border-left-color: #999; }
            .wpspt-message-header { margin-bottom: 8px; font-size: 13px; color: #666; }
            .wpspt-message-content { margin: 0; }
            .wpspt-badge { padding: 2px 8px; background: #2271b1; color: white; border-radius: 3px; font-size: 11px; margin-left: 5px; }
            .wpspt-message-date { float: right; font-size: 12px; }
            .wpspt-conversation-reply textarea { width: 100%; margin-bottom: 10px; }
            .wpspt-reply-status { margin-left: 10px; }
            .wpspt-details-table { width: 100%; }
            .wpspt-details-table th { width: 150px; text-align: left; padding: 8px; background: #f5f5f5; }
            .wpspt-details-table td { padding: 8px; }
        </style>
        <?php
    }

    /**
     * Render credentials metabox
     */
    public static function render_credentials_metabox($post) {
        $credentials_data = WPSPT_Database::get_credentials($post->ID);

        if (!$credentials_data) {
            echo '<p>' . __('No credentials provided for this ticket.', 'wpspt') . '</p>';
            return;
        }

        $credentials = WPSPT_Encryption::decrypt_credentials($credentials_data);

        ?>
        <div class="wpspt-credentials-box">
            <?php if (!empty($credentials_data->site_url)): ?>
                <div class="wpspt-credential-item">
                    <strong><?php _e('Site URL:', 'wpspt'); ?></strong>
                    <p><a href="<?php echo esc_url($credentials_data->site_url); ?>" target="_blank"><?php echo esc_html($credentials_data->site_url); ?></a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($credentials_data->admin_url)): ?>
                <div class="wpspt-credential-item">
                    <strong><?php _e('Admin URL:', 'wpspt'); ?></strong>
                    <p><a href="<?php echo esc_url($credentials_data->admin_url); ?>" target="_blank"><?php echo esc_html($credentials_data->admin_url); ?></a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($credentials['username'])): ?>
                <div class="wpspt-credential-item">
                    <strong><?php _e('Username:', 'wpspt'); ?></strong>
                    <p><code><?php echo esc_html($credentials['username']); ?></code></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($credentials['password'])): ?>
                <div class="wpspt-credential-item">
                    <strong><?php _e('Password:', 'wpspt'); ?></strong>
                    <p>
                        <code class="wpspt-password-field" style="display: none;"><?php echo esc_html($credentials['password']); ?></code>
                        <button type="button" class="button wpspt-show-password"><?php _e('Show Password', 'wpspt'); ?></button>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($credentials['notes'])): ?>
                <div class="wpspt-credential-item">
                    <strong><?php _e('Notes:', 'wpspt'); ?></strong>
                    <p><?php echo nl2br(esc_html($credentials['notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .wpspt-credentials-box { padding: 10px; }
            .wpspt-credential-item { margin-bottom: 15px; }
            .wpspt-credential-item strong { display: block; margin-bottom: 5px; }
            .wpspt-credential-item code { background: #f0f0f0; padding: 4px 8px; border-radius: 3px; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.wpspt-show-password').on('click', function() {
                var btn = $(this);
                var passwordField = btn.siblings('.wpspt-password-field');

                if (passwordField.is(':visible')) {
                    passwordField.hide();
                    btn.text('<?php _e('Show Password', 'wpspt'); ?>');
                } else {
                    passwordField.show();
                    btn.text('<?php _e('Hide Password', 'wpspt'); ?>');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render status metabox
     */
    public static function render_status_metabox($post) {
        $current_status = $post->post_status;
        $statuses = WPSPT_CPT_Ticket::get_statuses();
        wp_nonce_field('wpspt_status_nonce', 'wpspt_status_nonce_field');

        ?>
        <div class="wpspt-status-box">
            <label for="wpspt_ticket_status"><strong><?php _e('Current Status:', 'wpspt'); ?></strong></label>
            <select id="wpspt_ticket_status" name="wpspt_ticket_status" class="widefat">
                <?php foreach ($statuses as $status_key => $status_label): ?>
                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($current_status, $status_key); ?>>
                        <?php echo esc_html($status_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Save ticket meta
     */
    public static function save_ticket_meta($post_id) {
        // Check nonce
        if (!isset($_POST['wpspt_status_nonce_field']) || !wp_verify_nonce($_POST['wpspt_status_nonce_field'], 'wpspt_status_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Update status
        if (isset($_POST['wpspt_ticket_status'])) {
            $new_status = sanitize_text_field($_POST['wpspt_ticket_status']);
            if (array_key_exists($new_status, WPSPT_CPT_Ticket::get_statuses())) {
                remove_action('save_post_wpspt_ticket', [__CLASS__, 'save_ticket_meta']);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $new_status,
                ]);
                add_action('save_post_wpspt_ticket', [__CLASS__, 'save_ticket_meta']);
            }
        }
    }

    /**
     * Custom columns for ticket list
     */
    public static function ticket_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['client'] = __('Client', 'wpspt');
        $new_columns['ticket_type'] = __('Type', 'wpspt');
        $new_columns['status'] = __('Status', 'wpspt');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Custom column content
     */
    public static function ticket_column_content($column, $post_id) {
        switch ($column) {
            case 'client':
                $author = get_user_by('ID', get_post_field('post_author', $post_id));
                echo esc_html($author->display_name);
                break;

            case 'ticket_type':
                $type = get_post_meta($post_id, '_wpspt_ticket_type', true);
                echo esc_html($type);
                break;

            case 'status':
                $status = get_post_status($post_id);
                $statuses = WPSPT_CPT_Ticket::get_statuses();
                $status_label = isset($statuses[$status]) ? $statuses[$status] : $status;
                echo '<span class="wpspt-status-badge wpspt-status-' . esc_attr(str_replace('wpspt_', '', $status)) . '">' . esc_html($status_label) . '</span>';
                break;
        }
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (isset($_POST['wpspt_save_settings']) && check_admin_referer('wpspt_settings_nonce')) {
            $ticket_types = [];

            if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
                foreach ($_POST['ticket_types'] as $type) {
                    if (!empty($type['label']) && !empty($type['id']) && isset($type['credits'])) {
                        $ticket_types[] = [
                            'id' => sanitize_key($type['id']),
                            'label' => sanitize_text_field($type['label']),
                            'credits' => intval($type['credits']),
                        ];
                    }
                }
            }

            update_option('wpspt_ticket_types', $ticket_types);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'wpspt') . '</p></div>';
        }

        $ticket_types = get_option('wpspt_ticket_types', []);

        ?>
        <div class="wrap">
            <h1><?php _e('Ticket Settings', 'wpspt'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('wpspt_settings_nonce'); ?>

                <h2><?php _e('Ticket Types', 'wpspt'); ?></h2>
                <p><?php _e('Configure ticket types and their credit costs.', 'wpspt'); ?></p>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'wpspt'); ?></th>
                            <th><?php _e('Label', 'wpspt'); ?></th>
                            <th><?php _e('Credits', 'wpspt'); ?></th>
                            <th><?php _e('Actions', 'wpspt'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpspt-ticket-types">
                        <?php foreach ($ticket_types as $index => $type): ?>
                            <tr>
                                <td><input type="text" name="ticket_types[<?php echo $index; ?>][id]" value="<?php echo esc_attr($type['id']); ?>" class="regular-text"></td>
                                <td><input type="text" name="ticket_types[<?php echo $index; ?>][label]" value="<?php echo esc_attr($type['label']); ?>" class="regular-text"></td>
                                <td><input type="number" name="ticket_types[<?php echo $index; ?>][credits]" value="<?php echo esc_attr($type['credits']); ?>" class="small-text"></td>
                                <td><button type="button" class="button wpspt-remove-type"><?php _e('Remove', 'wpspt'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="wpspt-add-type"><?php _e('Add Type', 'wpspt'); ?></button>
                </p>

                <p class="submit">
                    <input type="submit" name="wpspt_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'wpspt'); ?>">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var typeIndex = <?php echo count($ticket_types); ?>;

            $('#wpspt-add-type').on('click', function() {
                var row = '<tr>' +
                    '<td><input type="text" name="ticket_types[' + typeIndex + '][id]" class="regular-text"></td>' +
                    '<td><input type="text" name="ticket_types[' + typeIndex + '][label]" class="regular-text"></td>' +
                    '<td><input type="number" name="ticket_types[' + typeIndex + '][credits]" class="small-text"></td>' +
                    '<td><button type="button" class="button wpspt-remove-type"><?php _e('Remove', 'wpspt'); ?></button></td>' +
                    '</tr>';
                $('#wpspt-ticket-types').append(row);
                typeIndex++;
            });

            $(document).on('click', '.wpspt-remove-type', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Render credits management page
     */
    public static function render_credits_page() {
        if (isset($_POST['wpspt_update_credits']) && check_admin_referer('wpspt_credits_nonce')) {
            $user_id = intval($_POST['user_id']);
            $credits = intval($_POST['credits']);

            WPSPT_Database::update_user_credits($user_id, $credits);
            echo '<div class="notice notice-success"><p>' . __('Credits updated successfully.', 'wpspt') . '</p></div>';
        }

        // Get all users with wpcustomer role
        $customers = get_users(['role' => 'wpcustomer']);

        ?>
        <div class="wrap">
            <h1><?php _e('User Credits Management', 'wpspt'); ?></h1>

            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('User', 'wpspt'); ?></th>
                        <th><?php _e('Email', 'wpspt'); ?></th>
                        <th><?php _e('Current Credits', 'wpspt'); ?></th>
                        <th><?php _e('Actions', 'wpspt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer):
                        $credits = WPSPT_Database::get_user_credits($customer->ID);
                    ?>
                        <tr>
                            <td><?php echo esc_html($customer->display_name); ?></td>
                            <td><?php echo esc_html($customer->user_email); ?></td>
                            <td><strong><?php echo esc_html($credits); ?></strong></td>
                            <td>
                                <button type="button" class="button wpspt-edit-credits" data-user-id="<?php echo esc_attr($customer->ID); ?>" data-credits="<?php echo esc_attr($credits); ?>">
                                    <?php _e('Edit Credits', 'wpspt'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Credits Edit Modal -->
        <div id="wpspt-credits-modal" style="display:none;">
            <form method="post">
                <?php wp_nonce_field('wpspt_credits_nonce'); ?>
                <input type="hidden" name="user_id" id="wpspt-credits-user-id">
                <p>
                    <label for="wpspt-credits-amount"><?php _e('Credits:', 'wpspt'); ?></label>
                    <input type="number" name="credits" id="wpspt-credits-amount" class="regular-text">
                </p>
                <p>
                    <input type="submit" name="wpspt_update_credits" class="button button-primary" value="<?php esc_attr_e('Update Credits', 'wpspt'); ?>">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.wpspt-edit-credits').on('click', function() {
                var userId = $(this).data('user-id');
                var credits = $(this).data('credits');

                $('#wpspt-credits-user-id').val(userId);
                $('#wpspt-credits-amount').val(credits);

                // Simple inline form display
                var form = $('#wpspt-credits-modal').html();
                $(this).closest('tr').after('<tr class="wpspt-credits-edit-row"><td colspan="4">' + form + '</td></tr>');
                $('.wpspt-credits-edit-row').show();
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Admin add reply
     */
    public static function ajax_admin_add_reply() {
        check_ajax_referer('wpspt_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $ticket_id = intval($_POST['ticket_id']);
        $message = wp_kses_post($_POST['message']);

        if (empty($message)) {
            wp_send_json_error(['message' => __('Message cannot be empty.', 'wpspt')]);
        }

        $user_id = get_current_user_id();
        $conversation_id = WPSPT_Database::add_conversation($ticket_id, $user_id, $message, true);

        if ($conversation_id) {
            wp_send_json_success(['message' => __('Reply added successfully.', 'wpspt')]);
        } else {
            wp_send_json_error(['message' => __('Failed to add reply.', 'wpspt')]);
        }
    }

    /**
     * AJAX: Update ticket status
     */
    public static function ajax_admin_update_status() {
        check_ajax_referer('wpspt_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $ticket_id = intval($_POST['ticket_id']);
        $status = sanitize_text_field($_POST['status']);

        $result = WPSPT_CPT_Ticket::update_status($ticket_id, $status);

        if ($result) {
            wp_send_json_success(['message' => __('Status updated successfully.', 'wpspt')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update status.', 'wpspt')]);
        }
    }

    /**
     * AJAX: Add credits to user
     */
    public static function ajax_admin_add_credits() {
        check_ajax_referer('wpspt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $user_id = intval($_POST['user_id']);
        $credits = intval($_POST['credits']);

        WPSPT_Database::update_user_credits($user_id, $credits);

        wp_send_json_success(['message' => __('Credits updated successfully.', 'wpspt')]);
    }

    /**
     * AJAX: Update user credits from user list
     */
    public static function ajax_update_user_credits() {
        check_ajax_referer('wpspt_user_credits_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $user_id = intval($_POST['user_id']);
        $credits = intval($_POST['credits']);

        WPSPT_Database::update_user_credits($user_id, $credits);

        wp_send_json_success([
            'message' => __('Credits updated successfully.', 'wpspt'),
            'credits' => $credits
        ]);
    }

    /**
     * Add credits column to user list
     */
    public static function add_user_credits_column($columns) {
        $columns['wpspt_credits'] = __('Support Credits', 'wpspt');
        return $columns;
    }

    /**
     * Display credits in user list column
     */
    public static function display_user_credits_column($value, $column_name, $user_id) {
        if ($column_name === 'wpspt_credits') {
            $credits = WPSPT_Database::get_user_credits($user_id);
            return '<span class="wpspt-user-credits" data-user-id="' . esc_attr($user_id) . '">' . esc_html($credits) . '</span> ' .
                   '<a href="#" class="wpspt-edit-user-credits button button-small" data-user-id="' . esc_attr($user_id) . '" data-credits="' . esc_attr($credits) . '">' . __('Edit', 'wpspt') . '</a>';
        }
        return $value;
    }

    /**
     * Add inline edit script and styles for user credits
     */
    public static function add_user_credits_inline_edit() {
        ?>
        <style>
            .wpspt-user-credits {
                font-weight: bold;
                display: inline-block;
                min-width: 30px;
                margin-right: 5px;
            }
            .wpspt-credits-edit-form {
                display: inline-block;
                margin-left: 10px;
            }
            .wpspt-credits-edit-form input[type="number"] {
                width: 80px;
                margin-right: 5px;
            }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentEditRow = null;

            // Edit button click
            $(document).on('click', '.wpspt-edit-user-credits', function(e) {
                e.preventDefault();

                // Close any open edit forms
                if (currentEditRow) {
                    currentEditRow.find('.wpspt-credits-edit-form').remove();
                    currentEditRow.find('.wpspt-edit-user-credits').show();
                }

                var btn = $(this);
                var userId = btn.data('user-id');
                var credits = btn.data('credits');
                var row = btn.closest('td');

                // Hide edit button
                btn.hide();

                // Create inline edit form
                var form = $('<span class="wpspt-credits-edit-form"></span>');
                var input = $('<input type="number" value="' + credits + '" min="0" step="1">');
                var saveBtn = $('<button type="button" class="button button-small button-primary wpspt-save-credits">' + '<?php echo esc_js(__('Save', 'wpspt')); ?>' + '</button>');
                var cancelBtn = $('<button type="button" class="button button-small wpspt-cancel-credits">' + '<?php echo esc_js(__('Cancel', 'wpspt')); ?>' + '</button>');
                var spinner = $('<span class="spinner" style="float:none;margin:0 5px;"></span>');

                form.append(input).append(saveBtn).append(cancelBtn).append(spinner);
                row.append(form);

                currentEditRow = row;

                // Focus input
                input.focus().select();

                // Save button
                saveBtn.on('click', function() {
                    var newCredits = parseInt(input.val());
                    if (isNaN(newCredits) || newCredits < 0) {
                        alert('<?php echo esc_js(__('Please enter a valid number.', 'wpspt')); ?>');
                        return;
                    }

                    spinner.addClass('is-active');
                    saveBtn.prop('disabled', true);
                    cancelBtn.prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpspt_update_user_credits',
                            nonce: '<?php echo wp_create_nonce('wpspt_user_credits_nonce'); ?>',
                            user_id: userId,
                            credits: newCredits
                        },
                        success: function(response) {
                            if (response.success) {
                                row.find('.wpspt-user-credits').text(response.data.credits);
                                btn.data('credits', response.data.credits);
                                form.remove();
                                btn.show();
                                currentEditRow = null;
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Failed to update credits.', 'wpspt')); ?>');
                                spinner.removeClass('is-active');
                                saveBtn.prop('disabled', false);
                                cancelBtn.prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('An error occurred.', 'wpspt')); ?>');
                            spinner.removeClass('is-active');
                            saveBtn.prop('disabled', false);
                            cancelBtn.prop('disabled', false);
                        }
                    });
                });

                // Cancel button
                cancelBtn.on('click', function() {
                    form.remove();
                    btn.show();
                    currentEditRow = null;
                });

                // Enter key to save
                input.on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        saveBtn.click();
                    }
                });

                // Escape key to cancel
                input.on('keyup', function(e) {
                    if (e.which === 27) {
                        cancelBtn.click();
                    }
                });
            });
        });
        </script>
        <?php
    }
}
