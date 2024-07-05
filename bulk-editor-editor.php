<?php

/**
 * Plugin Name:       Bulk Order Editor
 * Plugin URI:        https://github.com/MrGKanev/Bulk-Order-Editor/
 * Description:       Bulk Order Editor is a simple plugin that allows you to change the status of multiple WooCommerce orders at once.
 * Requires at least: 5.5
 * Version:           0.0.1
 * Author:            Gabriel Kanev
 * Author URI:        https://gkanev.com
 * License:           MIT
 * License URI:       https://github.com/MrGKanev/Bulk-Order-Editor/blob/master/LICENSE
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/MrGKanev/Bulk-Order-Editor
 */

define('__FP_FILE__', __FILE__);

// Enqueue the custom JavaScript
add_action('admin_enqueue_scripts', 'enqueue_plugin_scripts');

function enqueue_plugin_scripts()
{
    // Enqueue Select2 CSS and JS
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

    // Enqueue your custom JS
    wp_enqueue_script('bulk-order-editor-js', plugin_dir_url(__FILE__) . 'js/bulk-order-editor.js', array('jquery', 'select2-js'), '1.0', true);
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
        'order_status_changer_page_content' // Callback function
    );
}

// Function to retrieve all WooCommerce order statuses
function get_woocommerce_order_statuses()
{
    return wc_get_order_statuses();
}

// Display the custom admin page content
function order_status_changer_page_content()
{
    $order_statuses = get_woocommerce_order_statuses();
    $users = get_users(array('fields' => array('ID', 'display_name')));
?>
    <div class="wrap">
        <h1>Bulk Order Changer</h1>
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

            <div class="form-group">
                <label for="actioner_id">Order Actioner (User ID):</label>
                <select id="actioner_id" name="actioner_id" class="user-select" style="width: 100%;"></select>
            </div>

            <input type="submit" value="Update Orders" class="button button-primary">
        </form>

        <div class="log-area">
            <h2>Update Log</h2>
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
        $actioner_id = isset($_POST['actioner_id']) ? intval($_POST['actioner_id']) : get_current_user_id(); // Defaults to current user if not specified

        $order = wc_get_order($order_id);
        if ($order) {
            $log_entries = [];
            if ($customer_id) {
                $order->set_customer_id($customer_id);
                $log_entries[] = sprintf('Order #%d customer changed', $order_id);
            }
            if ($order_status) {
                $order->update_status($order_status);
                $log_entries[] = sprintf('Order #%d status changed to %s', $order_id, wc_get_order_status_name($order_status));
            }
            if ($order_total) {
                $order->set_total($order_total);
                $log_entries[] = sprintf('Order #%d total changed to %.2f', $order_id, $order_total);
            }
            if (!empty($customer_note)) {
                $note = $order->add_order_note($customer_note, $note_type === 'customer');
                $log_entries[] = sprintf('Order #%d note added: %s', $order_id, $note->id);
            }
            if ($order_date) {
                $order->set_date_created($order_date);
                $log_entries[] = sprintf('Order #%d date of creation set to %s', $order_id, $order_date);
            }
            $order->save();
            update_post_meta($order_id, '_last_actioner_user_id', $actioner_id);

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
