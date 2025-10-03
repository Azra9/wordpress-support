<?php
/**
 * Client Dashboard
 * Handles frontend dashboard for clients to manage tickets
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSPT_Client_Dashboard {

    /**
     * Initialize the client dashboard
     */
    public static function init() {
        add_shortcode('wpspt_dashboard', [__CLASS__, 'render_dashboard']);
        add_action('wp_ajax_wpspt_submit_ticket', [__CLASS__, 'ajax_submit_ticket']);
        add_action('wp_ajax_wpspt_add_reply', [__CLASS__, 'ajax_add_reply']);
        add_action('wp_ajax_wpspt_save_credentials', [__CLASS__, 'ajax_save_credentials']);
    }

    /**
     * Render the dashboard shortcode
     */
    public static function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access the support dashboard.', 'wpspt') . '</p>';
        }

        if (!wpspt_current_user_can_access_dashboard()) {
            return '<p>' . __('You do not have permission to access this dashboard.', 'wpspt') . '</p>';
        }

        // Enqueue assets
        wp_enqueue_style('wpspt-style');
        wp_enqueue_script('wpspt-app');

        // Enqueue client dashboard specific assets
        wp_enqueue_style('wpspt-client-dashboard', WPSPT_PLUGIN_URL . 'src/client/create-ticket.css', [], WPSPT_VERSION);
        wp_enqueue_script('wpspt-client-dashboard', WPSPT_PLUGIN_URL . 'src/client/create-ticket.js', ['jquery'], WPSPT_VERSION, true);

        // Localize script with data
        wp_localize_script('wpspt-client-dashboard', 'wpsptData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpspt_nonce'),
            'userId' => get_current_user_id(),
        ]);

        ob_start();
        self::render_dashboard_html();
        return ob_get_clean();
    }

    /**
     * Render dashboard HTML
     */
    private static function render_dashboard_html() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $credits = WPSPT_Database::get_user_credits($user_id);
        $tickets = WPSPT_CPT_Ticket::get_user_tickets($user_id);
        $ticket_types = get_option('wpspt_ticket_types', []);

        ?>
        <div id="wpspt-dashboard" class="wpspt-dashboard">

            <!-- Header -->
            <div class="wpspt-header">
                <h2><?php _e('Support Dashboard', 'wpspt'); ?></h2>
                <div class="wpspt-credits">
                    <span class="wpspt-credits-label"><?php _e('Available Credits:', 'wpspt'); ?></span>
                    <span class="wpspt-credits-count"><?php echo esc_html($credits); ?></span>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="wpspt-tabs">
                <button class="wpspt-tab-btn active" data-tab="tickets"><?php _e('My Tickets', 'wpspt'); ?></button>
                <button class="wpspt-tab-btn" data-tab="new-ticket"><?php _e('New Ticket', 'wpspt'); ?></button>
            </div>

            <!-- Tickets List Tab -->
            <div id="tickets-tab" class="wpspt-tab-content active">
                <div class="wpspt-tickets-list">
                    <?php if (empty($tickets)): ?>
                        <p class="wpspt-no-tickets"><?php _e('You have no tickets yet.', 'wpspt'); ?></p>
                    <?php else: ?>
                        <table class="wpspt-tickets-table">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'wpspt'); ?></th>
                                    <th><?php _e('Title', 'wpspt'); ?></th>
                                    <th><?php _e('Status', 'wpspt'); ?></th>
                                    <th><?php _e('Type', 'wpspt'); ?></th>
                                    <th><?php _e('Date', 'wpspt'); ?></th>
                                    <th><?php _e('Actions', 'wpspt'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket):
                                    $ticket_type = get_post_meta($ticket->ID, '_wpspt_ticket_type', true);
                                    $status = $ticket->post_status;
                                    $status_label = self::get_status_label($status);
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($ticket->ID); ?></td>
                                        <td><?php echo esc_html($ticket->post_title); ?></td>
                                        <td><span class="wpspt-status wpspt-status-<?php echo esc_attr(str_replace('wpspt_', '', $status)); ?>"><?php echo esc_html($status_label); ?></span></td>
                                        <td><?php echo esc_html($ticket_type); ?></td>
                                        <td><?php echo esc_html(get_the_date('', $ticket)); ?></td>
                                        <td>
                                            <button class="wpspt-btn wpspt-btn-view" data-ticket-id="<?php echo esc_attr($ticket->ID); ?>"><?php _e('View', 'wpspt'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- New Ticket Tab -->
            <div id="new-ticket-tab" class="wpspt-tab-content">
                <form id="wpspt-new-ticket-form" class="wpspt-form">
                    <div class="wpspt-form-group">
                        <label for="ticket-title"><?php _e('Ticket Title', 'wpspt'); ?> <span class="required">*</span></label>
                        <input type="text" id="ticket-title" name="title" required placeholder="<?php esc_attr_e('Brief description of your issue', 'wpspt'); ?>">
                    </div>

                    <div class="wpspt-form-group">
                        <label for="ticket-type"><?php _e('Ticket Type', 'wpspt'); ?> <span class="required">*</span></label>
                        <select id="ticket-type" name="ticket_type" required>
                            <option value=""><?php _e('Select a type', 'wpspt'); ?></option>
                            <?php foreach ($ticket_types as $type): ?>
                                <option value="<?php echo esc_attr($type['id']); ?>" data-credits="<?php echo esc_attr($type['credits']); ?>">
                                    <?php echo esc_html($type['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="wpspt-help-text"><?php printf(__('You have %d credits available.', 'wpspt'), $credits); ?></p>
                    </div>

                    <div class="wpspt-form-group">
                        <label for="ticket-description"><?php _e('Description', 'wpspt'); ?> <span class="required">*</span></label>
                        <textarea id="ticket-description" name="description" rows="6" required placeholder="<?php esc_attr_e('Provide detailed information about your support request', 'wpspt'); ?>"></textarea>
                    </div>

                    <!-- Website Credentials Section (Optional) -->
                    <div class="wpspt-credentials-section">
                        <h3><?php _e('Website Access (Optional)', 'wpspt'); ?></h3>
                        <p class="wpspt-help-text"><?php _e('Provide website credentials if admin access is needed. All data is encrypted.', 'wpspt'); ?></p>

                        <div class="wpspt-form-group">
                            <label for="site-url"><?php _e('Site URL', 'wpspt'); ?></label>
                            <input type="url" id="site-url" name="site_url" placeholder="https://example.com">
                        </div>

                        <div class="wpspt-form-group">
                            <label for="admin-url"><?php _e('Admin URL', 'wpspt'); ?></label>
                            <input type="url" id="admin-url" name="admin_url" placeholder="https://example.com/wp-admin">
                        </div>

                        <div class="wpspt-form-group">
                            <label for="wp-username"><?php _e('Username', 'wpspt'); ?></label>
                            <input type="text" id="wp-username" name="wp_username" autocomplete="off">
                        </div>

                        <div class="wpspt-form-group">
                            <label for="wp-password"><?php _e('Password', 'wpspt'); ?></label>
                            <input type="password" id="wp-password" name="wp_password" autocomplete="off">
                        </div>

                        <div class="wpspt-form-group">
                            <label for="credentials-notes"><?php _e('Additional Notes', 'wpspt'); ?></label>
                            <textarea id="credentials-notes" name="credentials_notes" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="wpspt-form-actions">
                        <button type="submit" class="wpspt-btn wpspt-btn-primary"><?php _e('Submit Ticket', 'wpspt'); ?></button>
                    </div>

                    <div class="wpspt-message" style="display: none;"></div>
                </form>
            </div>

            <!-- Ticket Details Modal (loaded dynamically) -->
            <div id="wpspt-ticket-modal" class="wpspt-modal" style="display: none;">
                <div class="wpspt-modal-content">
                    <span class="wpspt-modal-close">&times;</span>
                    <div class="wpspt-ticket-details"></div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Get status label
     */
    private static function get_status_label($status) {
        $statuses = WPSPT_CPT_Ticket::get_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * AJAX: Submit new ticket
     */
    public static function ajax_submit_ticket() {
        check_ajax_referer('wpspt_nonce', 'nonce');

        if (!is_user_logged_in() || !wpspt_current_user_can_access_dashboard()) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $user_id = get_current_user_id();
        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $ticket_type = sanitize_text_field($_POST['ticket_type']);

        // Validate required fields
        if (empty($title) || empty($description) || empty($ticket_type)) {
            wp_send_json_error(['message' => __('Please fill all required fields.', 'wpspt')]);
        }

        // Get ticket type info
        $ticket_types = get_option('wpspt_ticket_types', []);
        $selected_type = null;
        foreach ($ticket_types as $type) {
            if ($type['id'] === $ticket_type) {
                $selected_type = $type;
                break;
            }
        }

        if (!$selected_type) {
            wp_send_json_error(['message' => __('Invalid ticket type.', 'wpspt')]);
        }

        // Check if user has enough credits
        $user_credits = WPSPT_Database::get_user_credits($user_id);
        if ($user_credits < $selected_type['credits']) {
            wp_send_json_error(['message' => __('Insufficient credits.', 'wpspt')]);
        }

        // Create ticket
        $ticket_id = WPSPT_CPT_Ticket::create_ticket($user_id, $title, $description, $ticket_type);

        if (!$ticket_id) {
            wp_send_json_error(['message' => __('Failed to create ticket.', 'wpspt')]);
        }

        // Deduct credits
        $new_credits = $user_credits - $selected_type['credits'];
        WPSPT_Database::update_user_credits($user_id, $new_credits);

        // Save credentials if provided
        if (!empty($_POST['site_url']) || !empty($_POST['wp_username']) || !empty($_POST['wp_password'])) {
            $credentials = [
                'username' => sanitize_text_field($_POST['wp_username']),
                'password' => $_POST['wp_password'],
                'notes' => sanitize_textarea_field($_POST['credentials_notes']),
            ];

            $encrypted = WPSPT_Encryption::encrypt_credentials($credentials);
            $encrypted['site_url'] = esc_url_raw($_POST['site_url']);
            $encrypted['admin_url'] = esc_url_raw($_POST['admin_url']);

            WPSPT_Database::save_credentials($ticket_id, $user_id, $encrypted);
        }

        wp_send_json_success([
            'message' => __('Ticket submitted successfully!', 'wpspt'),
            'ticket_id' => $ticket_id
        ]);
    }

    /**
     * AJAX: Add reply to ticket
     */
    public static function ajax_add_reply() {
        check_ajax_referer('wpspt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $user_id = get_current_user_id();
        $ticket_id = intval($_POST['ticket_id']);
        $message = wp_kses_post($_POST['message']);

        if (empty($message)) {
            wp_send_json_error(['message' => __('Message cannot be empty.', 'wpspt')]);
        }

        // Verify ticket ownership or admin
        $ticket = get_post($ticket_id);
        if (!$ticket || ($ticket->post_author != $user_id && !current_user_can('administrator'))) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $is_admin = current_user_can('administrator');
        $conversation_id = WPSPT_Database::add_conversation($ticket_id, $user_id, $message, $is_admin);

        if ($conversation_id) {
            wp_send_json_success(['message' => __('Reply added successfully.', 'wpspt')]);
        } else {
            wp_send_json_error(['message' => __('Failed to add reply.', 'wpspt')]);
        }
    }

    /**
     * AJAX: Save credentials
     */
    public static function ajax_save_credentials() {
        check_ajax_referer('wpspt_nonce', 'nonce');

        if (!is_user_logged_in() || !wpspt_current_user_can_access_dashboard()) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $user_id = get_current_user_id();
        $ticket_id = intval($_POST['ticket_id']);

        // Verify ticket ownership
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_author != $user_id) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpspt')]);
        }

        $credentials = [
            'username' => sanitize_text_field($_POST['wp_username']),
            'password' => $_POST['wp_password'],
            'notes' => sanitize_textarea_field($_POST['credentials_notes']),
        ];

        $encrypted = WPSPT_Encryption::encrypt_credentials($credentials);
        $encrypted['site_url'] = esc_url_raw($_POST['site_url']);
        $encrypted['admin_url'] = esc_url_raw($_POST['admin_url']);

        WPSPT_Database::save_credentials($ticket_id, $user_id, $encrypted);

        wp_send_json_success(['message' => __('Credentials saved successfully.', 'wpspt')]);
    }
}
