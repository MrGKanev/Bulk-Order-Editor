<?php

/**
 * Plugin Name:             Bulk Order Editor
 * Plugin URI:              https://github.com/MrGKanev/Bulk-Order-Editor/
 * Description:             Bulk Order Editor is a simple plugin that allows you to change the status of multiple WooCommerce orders at once.
 * Version:                 0.0.2
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 MIT
 * Requires at least:       6.4
 * Requires PHP:            7.4
 * WC requires at least:    6.0
 * WC tested up to:         9.1.2
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    define('__FP_FILE__', __FILE__);

    // Enqueue the custom JavaScript
    add_action('admin_enqueue_scripts', 'enqueue_custom_admin_script');

    function enqueue_custom_admin_script()
    {
        wp_enqueue_script('bulk-order-editor-js', plugin_dir_url(__FP_FILE__) . 'js/bulk-order-editor.js', array('jquery'), '1.0', true);
        wp_localize_script('bulk-order-editor-js', 'bulkOrderEditor', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bulk_order_editor_nonce')
        ));
    }

    add_action('admin_enqueue_scripts', 'enqueue_plugin_styles');

    function enqueue_plugin_styles()
    {
        // Check if we're on the plugin's page to avoid loading it on all admin pages
        $screen = get_current_screen();
        if ($screen->id === 'woocommerce_page_order-status-changer') {  // Adjust this ID based on your actual plugin page ID
            wp_enqueue_style('bulk-order-editor-css', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0', 'all');
        }
    }

    // Add a new menu item to the WooCommerce menu
    add_action('admin_menu', 'register_custom_woocommerce_menu_page');

    function register_custom_woocommerce_menu_page()
    {
        add_submenu_page(
            'woocommerce', // Parent slug
            'Bulk Order Editor', // Page title
            'Bulk Order Editor', // Menu title
            'manage_woocommerce', // Capability
            'order-status-changer', // Menu slug
            'order_status_editor_page_content' // Callback function
        );
    }

    // Function to retrieve all WooCommerce order statuses
    function get_woocommerce_order_statuses()
    {
        return wc_get_order_statuses();
    }

    // Display the custom admin page content
    function order_status_editor_page_content()
    {
        $order_statuses = get_woocommerce_order_statuses();
?>
        <div class="wrap">
            <h1>Bulk Order Editor</h1>
            <div id="response-message"></div>
            <form id="order-status-form">
                <h2>Order Details</h2>
                <div class="form-group">
                    <label for="order_ids">Order IDs (comma-separated):</label>
                    <input type="text" id="order_ids" name="order_ids" required>
                </div>

                <div class="form-group">
                    <label for="order_status">Order Status:</label>
                    <select id="order_status" name="order_status">
                        <option value="">Select status</option>
                        <?php foreach ($order_statuses as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="order_total">Order Total:</label>
                    <input type="number" step="0.01" id="order_total" name="order_total">
                </div>

                <h2>Customer Details</h2>
                <div class="form-group">
                    <label for="customer_id">Customer ID:</label>
                    <input type="number" id="customer_id" name="customer_id">
                </div>

                <div class="form-group">
                    <label for="customer_note">Note:</label>
                    <textarea id="customer_note" name="customer_note"></textarea>
                </div>

                <div class="form-group">
                    <label for="note_type">Note Type:</label>
                    <select id="note_type" name="note_type">
                        <option value="private">Private</option>
                        <option value="customer">Customer</option>
                    </select>
                </div>

                <h2>Order Processing</h2>
                <div class="form-group">
                    <label for="order_date">Order Date (YYYY-MM-DD):</label>
                    <input type="date" id="order_date" name="order_date">
                </div>

                <input type="submit" value="Update Orders" class="button button-primary">
            </form>

            <div class="log-area">
                <h2>Update Log</h2>
                <p id="update-progress" style="display:none;">Progress: <span id="progress-percentage">0%</span></p>
                <ul id="log-list">
                    <li>No log entries found.</li>
                </ul>
            </div>
        </div>
<?php
    }

    // Handle AJAX request for individual order updates
    add_action('wp_ajax_update_single_order', 'handle_update_single_order_ajax');

    function handle_update_single_order_ajax()
    {
        check_ajax_referer('bulk_order_editor_nonce', 'nonce');

        if (isset($_POST['order_id'])) {
            $order_id = intval(sanitize_text_field($_POST['order_id']));
            $order_status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : '';
            $order_total = isset($_POST['order_total']) ? floatval($_POST['order_total']) : '';
            $customer_note = isset($_POST['customer_note']) ? sanitize_textarea_field($_POST['customer_note']) : '';
            $note_type = isset($_POST['note_type']) ? sanitize_text_field($_POST['note_type']) : 'private';
            $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $order_date = isset($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : '';
            $current_user = wp_get_current_user();
            $current_user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

            $order = wc_get_order($order_id);
            if ($order) {
                $log_entries = [];

                if ($customer_id && $order->get_customer_id() !== $customer_id) {
                    $previous_customer_id = $order->get_customer_id();
                    $order->set_customer_id($customer_id);
                    $log_entries[] = sprintf('Order #%d customer ID changed from "%d" to "%d"', $order_id, $previous_customer_id, $customer_id);
                    $order->add_order_note(sprintf('Order customer ID changed from "%d" to "%d" by <b>%s</b>', $previous_customer_id, $customer_id, $current_user_name));
                }

                if ($order_status && $order->get_status() !== $order_status) {
                    $previous_status = $order->get_status();
                    $order->update_status($order_status);
                    $log_entries[] = sprintf('Order #%d status changed from "%s" to "%s"', $order_id, wc_get_order_status_name($previous_status), wc_get_order_status_name($order_status));
                    $order->add_order_note(sprintf('Order status changed from "%s" to "%s" by <b>%s</b>', wc_get_order_status_name($previous_status), wc_get_order_status_name($order_status), $current_user_name));
                }

                if ($order_total && $order->get_total() != $order_total) {
                    $previous_total = $order->get_total();
                    $order->set_total($order_total);
                    $log_entries[] = sprintf('Order #%d total changed from %.2f to %.2f', $order_id, $previous_total, $order_total);
                    $order->add_order_note(sprintf('Order total changed from %.2f to %.2f by <b>%s</b>', $previous_total, $order_total, $current_user_name));
                }

                if (!empty($customer_note)) {
                    $order->add_order_note(sprintf('Note added by %s: "%s"', $current_user_name, $customer_note), $note_type === 'customer');
                    $log_entries[] = sprintf('Order #%d note added: "%s"', $order_id, $customer_note);
                }

                if ($order_date && $order->get_date_created()->format('Y-m-d') !== $order_date) {
                    $previous_date = $order->get_date_created()->format('Y-m-d');
                    $order->set_date_created($order_date);
                    $log_entries[] = sprintf('Order #%d date of creation changed from "%s" to "%s"', $order_id, $previous_date, $order_date);
                    $order->add_order_note(sprintf('Order date of creation changed from "%s" to "%s" by <b>%s</b>', $previous_date, $order_date, $current_user_name));
                }

                // Add separator line after all changes for this order
                if (!empty($log_entries)) {
                    $log_entries[] = "--------------------------------";
                }

                $order->save();

                wp_send_json_success(array(
                    'log_entries' => $log_entries,
                    'status'      => 'success'
                ));
            } else {
                wp_send_json_error(array('message' => sprintf('Order #%d not found', $order_id)));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid order ID.'));
        }
    }
} else {
    // Display an admin notice if WooCommerce is not active
    function your_plugin_woocommerce_inactive_notice()
    {
        echo '<div class="notice notice-error is-dismissible">
            <p>"Bulk Order Editor" requires WooCommerce to be active. Please activate WooCommerce first.</p>
        </div>';
    }
    add_action('admin_notices', 'your_plugin_woocommerce_inactive_notice');

    // Deactivate the plugin
    function your_plugin_deactivate_self()
    {
        deactivate_plugins(plugin_basename(__FILE__));
    }
    add_action('admin_init', 'your_plugin_deactivate_self');
}
