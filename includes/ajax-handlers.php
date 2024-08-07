<?php

function handle_update_single_order_ajax()
{
    check_ajax_referer('bulk_order_editor_nonce', 'nonce');

    if (isset($_POST['order_id'])) {
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

        // Log the received data
        error_log('Received Data: ' . print_r($_POST, true));

        // Check if HPOS is enabled
        $is_hpos_enabled = function_exists('wc_get_order') && function_exists('wc_get_orders');

        if ($is_hpos_enabled) {
            // Use HPOS functions
            $order = wc_get_order($order_id);
        } else {
            // Use legacy functions
            $order = new WC_Order($order_id);
        }

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

            if (!empty($promo_code)) {
                $result = $order->apply_coupon($promo_code);
                if (is_wp_error($result)) {
                    $log_entries[] = sprintf('Failed to add promo code "%s" to order #%d: %s', $promo_code, $order_id, $result->get_error_message());
                } else {
                    $log_entries[] = sprintf('Order #%d promo code added: "%s"', $order_id, $promo_code);
                    $order->add_order_note(sprintf('Promo code "%s" added by <b>%s</b>', $promo_code, $current_user_name));
                }
            }

            if (!empty($customer_note)) {
                if ($note_type === 'customer') {
                    $order->add_order_note(sprintf('Note added: "%s"', $customer_note), true);
                } else {
                    $order->add_order_note(sprintf('Note added by %s: "%s"', $current_user_name, $customer_note), false);
                }
                $log_entries[] = sprintf('Order #%d note added: "%s"', $order_id, $customer_note);
            }

            if ($order_date) {
                $datetime = $order_date . ' ' . $order_time . ':00';
                $previous_date = $order->get_date_created()->format('Y-m-d H:i:s');
                $order->set_date_created($datetime);
                $log_entries[] = sprintf('Order #%d date of creation changed from "%s" to "%s"', $order_id, $previous_date, $datetime);
                $order->add_order_note(sprintf('Order date of creation changed from "%s" to "%s" by <b>%s</b>', $previous_date, $datetime, $current_user_name));
            }

            // Add separator line after all changes for this order
            if (!empty($log_entries)) {
                $log_entries[] = "--------------------------------";
            }

            if ($is_hpos_enabled) {
                // Use HPOS function to update the order
                wc_update_order($order);
            } else {
                // Use legacy function to update the order
                $order->save();
            }

            wp_send_json_success(array(
                'log_entries' => $log_entries,
                'status'      => 'success'
            ));
        } else {
            error_log(sprintf('Order #%d not found', $order_id)); // Add error logging
            wp_send_json_error(array('message' => sprintf('Order #%d not found', $order_id)));
        }
    } else {
        error_log('Invalid order ID.'); // Add error logging
        wp_send_json_error(array('message' => 'Invalid order ID.'));
    }
}
