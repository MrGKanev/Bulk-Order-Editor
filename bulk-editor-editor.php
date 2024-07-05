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
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_script');

function enqueue_custom_admin_script()
{
    wp_enqueue_script('bulk-order-editor-js', plugin_dir_url(__FP_FILE__) . 'js/bulk-order-editor.js', array('jquery'), '1.0', true);
    wp_localize_script('bulk-order-editor-js', 'bulkOrderEditor', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bulk_order_editor_nonce')
    ));
}

// Add a new menu item to the WooCommerce menu
add_action('admin_menu', 'register_custom_woocommerce_menu_page');

function register_custom_woocommerce_menu_page()
{
    add_submenu_page(
        'woocommerce', // Parent slug
        'Bulk Order Changer', // Page title
        'Bulk Order Changer', // Menu title
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
?>
    <div class="wrap">
        <h1>Bulk Order Changer</h1>
        <div id="response-message"></div>
        <form id="order-status-form">
            <label for="order_ids">Order IDs (comma-separated):</label>
            <input type="text" id="order_ids" name="order_ids" required>
            <label for="order_status">Order Status:</label>
            <select id="order_status" name="order_status" required>
                <?php foreach ($order_statuses as $status_key => $status_label) : ?>
                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Update Order Status">
        </form>
        <div id="status-log">
            <h2>Update Log</h2>
            <ul id="log-list">
                <li>No log entries found.</li>
            </ul>
        </div>
    </div>
<?php
}

// Handle AJAX request
add_action('wp_ajax_update_order_status', 'handle_update_order_status_ajax');

function handle_update_order_status_ajax()
{
    check_ajax_referer('bulk_order_editor_nonce', 'nonce');

    if (isset($_POST['order_ids']) && isset($_POST['order_status'])) {
        $order_ids = explode(',', sanitize_text_field($_POST['order_ids']));
        $order_status = sanitize_text_field($_POST['order_status']);
        $log_entries = [];
        $success = true;

        foreach ($order_ids as $order_id) {
            $order_id = intval(trim($order_id));
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->update_status($order_status);

                    $log_entry = sprintf('Order #%d status changed to %s', $order_id, wc_get_order_status_name($order_status));
                    $log_entries[] = $log_entry;
                } else {
                    $log_entries[] = sprintf('Order #%d not found', $order_id);
                    $success = false;
                }
            } else {
                $log_entries[] = sprintf('Invalid Order ID: %d', $order_id);
                $success = false;
            }
        }

        wp_send_json_success(array(
            'log_entries' => $log_entries,
            'status'      => $success ? 'success' : 'error'
        ));
    } else {
        wp_send_json_error(array('message' => 'Invalid input.'));
    }
}

?>

// JavaScript file: bulk-order-editor.js
jQuery(document).ready(function($) {
$('#order-status-form').on('submit', function(event) {
event.preventDefault();

var orderIds = $('#order_ids').val();
var orderStatus = $('#order_status').val();

$.ajax({
url: bulkOrderEditor.ajax_url,
type: 'POST',
data: {
action: 'update_order_status',
nonce: bulkOrderEditor.nonce,
order_ids: orderIds,
order_status: orderStatus
},
success: function(response) {
if (response.success) {
var logList = $('#log-list');
logList.empty();
response.data.log_entries.forEach(function(log) {
logList.append('<li>' + log + '</li>');
});
$('#response-message').html('<div class="notice notice-success">
    <p>Order status updated successfully.</p>
</div>');
} else {
$('#response-message').html('<div class="notice notice-error">
    <p>Failed to update order status.</p>
</div>');
}
},
error: function() {
$('#response-message').html('<div class="notice notice-error">
    <p>An error occurred.</p>
</div>');
}
});
});
});