<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handle the AJAX request to update a single order
 */
function handle_update_single_order_ajax()
{
    // Prevent any output before headers are sent
    ob_start();

    check_ajax_referer('bulk_order_editor_nonce', 'nonce');

    if (!function_exists('wc_get_order')) {
        wp_send_json_error(array('message' => 'WooCommerce is not active.'));
        exit;
    }

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(array('message' => 'You do not have permission to edit orders.'));
        exit;
    }

    if (!isset($_POST['order_id'])) {
        wp_send_json_error(array('message' => 'Invalid order ID.'));
        exit;
    }

    $order_id = intval(sanitize_text_field($_POST['order_id']));
    $order_status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : '';
    $order_total = isset($_POST['order_total']) ? floatval($_POST['order_total']) : '';
    $promo_code = isset($_POST['promo_code']) ? sanitize_text_field($_POST['promo_code']) : '';
    $customer_note = isset($_POST['customer_note']) ? sanitize_textarea_field($_POST['customer_note']) : '';
    $note_type = isset($_POST['note_type']) ? sanitize_text_field($_POST['note_type']) : 'private';
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $order_date = isset($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : '';
    $order_time = isset($_POST['order_time']) ? sanitize_text_field($_POST['order_time']) : '00:00';
    $current_user = wp_get_current_user();
    $current_user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

    try {
        $order = wc_get_order($order_id);

        if (!$order) {
            throw new Exception(sprintf('Order #%d not found', $order_id));
        }

        $log_entries = [];
        $changes_made = false;

        if ($customer_id && $order->get_customer_id() !== $customer_id) {
            $previous_customer_id = $order->get_customer_id();
            $order->set_customer_id($customer_id);
            $log_entries[] = sprintf('Order #%d customer ID changed from "%d" to "%d"', $order_id, $previous_customer_id, $customer_id);
            $order->add_order_note(sprintf('Order customer ID changed from "%d" to "%d" by %s', $previous_customer_id, $customer_id, $current_user_name));
            $changes_made = true;
        }

        if ($order_status) {
            $previous_status = $order->get_status();
            $new_status = 'wc-' === substr($order_status, 0, 3) ? substr($order_status, 3) : $order_status;

            if ($previous_status !== $new_status) {
                $order->update_status($new_status);
                $log_entries[] = sprintf('Order #%d status changed from "%s" to "%s"', $order_id, wc_get_order_status_name($previous_status), wc_get_order_status_name($new_status));
                $order->add_order_note(sprintf('Order status changed from "%s" to "%s" by %s', wc_get_order_status_name($previous_status), wc_get_order_status_name($new_status), $current_user_name));
                $changes_made = true;
            }
        }

        if ($order_total && $order->get_total() != $order_total) {
            $previous_total = $order->get_total();
            $order->set_total($order_total);
            $log_entries[] = sprintf('Order #%d total changed from %.2f to %.2f', $order_id, $previous_total, $order_total);
            $order->add_order_note(sprintf('Order total changed from %.2f to %.2f by %s', $previous_total, $order_total, $current_user_name));
            $changes_made = true;
        }

        if (!empty($promo_code)) {
            $result = $order->apply_coupon($promo_code);
            if (is_wp_error($result)) {
                $log_entries[] = sprintf('Failed to add promo code "%s" to order #%d: %s', $promo_code, $order_id, $result->get_error_message());
            } else {
                $log_entries[] = sprintf('Order #%d promo code added: "%s"', $order_id, $promo_code);
                $order->add_order_note(sprintf('Promo code "%s" added by %s', $promo_code, $current_user_name));
                $changes_made = true;
            }
        }

        if (!empty($customer_note)) {
            if ($note_type === 'customer') {
                $order->add_order_note($customer_note, 1, false);
                $log_entries[] = sprintf('Customer-visible note added to Order #%d: "%s"', $order_id, $customer_note);
            } else {
                $order->add_order_note(sprintf('Note added by %s: %s', $current_user_name, $customer_note), 0, false);
                $log_entries[] = sprintf('Private note added to Order #%d: "%s"', $order_id, $customer_note);
            }
            $changes_made = true;
        }

        if ($order_date) {
            $datetime = $order_date . ' ' . $order_time . ':00';
            $previous_date = $order->get_date_created()->format('Y-m-d H:i:s');
            $order->set_date_created($datetime);
            $log_entries[] = sprintf('Order #%d date of creation changed from "%s" to "%s"', $order_id, $previous_date, $datetime);
            $order->add_order_note(sprintf('Order date of creation changed from "%s" to "%s" by %s', $previous_date, $datetime, $current_user_name));
            $changes_made = true;
        }

        if ($changes_made) {
            // Save the order only if changes were made
            $order->save();
            error_log(sprintf('Order #%d updated successfully', $order_id));
        } else {
            $log_entries[] = sprintf('No changes made to Order #%d', $order_id);
        }

        wp_send_json_success(array(
            'log_entries' => $log_entries,
            'status' => $changes_made ? 'success' : 'no_changes'
        ));
    } catch (Exception $e) {
        error_log(sprintf('Error updating order #%d: %s', $order_id, $e->getMessage()));
        error_log('Stack trace: ' . $e->getTraceAsString());
        wp_send_json_error(array('message' => $e->getMessage()));
    }

    // This line should never be reached, but we'll include it just in case
    exit;
}

// Hook the function to the WordPress AJAX action
add_action('wp_ajax_boe_update_order', 'handle_update_single_order_ajax');
