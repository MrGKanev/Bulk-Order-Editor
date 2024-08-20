<?php
class Bulk_Order_Editor_Admin
{
    public function enqueue_styles($hook)
    {
        if ('woocommerce_page_bulk-order-editor' !== $hook) {
            return;
        }
        wp_enqueue_style('bulk-order-editor', plugin_dir_url(BULK_ORDER_EDITOR_PLUGIN_FILE) . 'admin/css/bulk-order-editor-admin.css', array(), '1.0.0', 'all');
    }

    public function enqueue_scripts($hook)
    {
        if ('woocommerce_page_bulk-order-editor' !== $hook) {
            return;
        }
        wp_enqueue_script('bulk-order-editor', plugin_dir_url(BULK_ORDER_EDITOR_PLUGIN_FILE) . 'admin/js/bulk-order-editor-admin.js', array('jquery'), '1.0.0', false);
        wp_localize_script('bulk-order-editor', 'bulkOrderEditor', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('bulk_order_editor_nonce')
        ));
    }

    public function add_plugin_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            'Bulk Order Editor',
            'Bulk Order Editor',
            'manage_woocommerce',
            'bulk-order-editor',
            array($this, 'display_plugin_admin_page')
        );
    }

    public function display_plugin_admin_page()
    {
        require_once BULK_ORDER_EDITOR_PLUGIN_DIR . 'admin/partials/bulk-order-editor-admin-display.php';
    }

    public function handle_batch_update_orders()
    {
        check_ajax_referer('bulk_order_editor_nonce', 'nonce');

        $order_ids = isset($_POST['order_ids']) ? array_map('intval', explode(',', $_POST['order_ids'])) : [];
        $batch_size = 10; // Process 10 orders at a time
        $processed = isset($_POST['processed']) ? intval($_POST['processed']) : 0;

        $batch = array_slice($order_ids, $processed, $batch_size);
        $results = [];

        foreach ($batch as $order_id) {
            $result = $this->process_single_order($order_id, $_POST);
            $results[] = $result;
        }

        $processed += count($batch);
        $is_complete = $processed >= count($order_ids);

        wp_send_json([
            'success' => true,
            'processed' => $processed,
            'total' => count($order_ids),
            'is_complete' => $is_complete,
            'results' => $results
        ]);
    }

    private function process_single_order($order_id, $data)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return ['error' => sprintf('Order #%d not found', $order_id)];
        }

        $log_entries = [];

        // Process order status
        if (!empty($data['order_status'])) {
            $new_status = sanitize_text_field($data['order_status']);
            if ($order->get_status() !== $new_status) {
                $order->set_status($new_status);
                $log_entries[] = sprintf('Order #%d status changed to "%s"', $order_id, wc_get_order_status_name($new_status));
            }
        }

        // Process order total
        if (!empty($data['order_total'])) {
            $new_total = floatval($data['order_total']);
            if ($order->get_total() != $new_total) {
                $order->set_total($new_total);
                $log_entries[] = sprintf('Order #%d total changed to %.2f', $order_id, $new_total);
            }
        }

        // Process promo code
        if (!empty($data['promo_code'])) {
            $promo_code = sanitize_text_field($data['promo_code']);
            $coupon = new WC_Coupon($promo_code);
            if ($coupon->get_id()) {
                $result = $order->apply_coupon($coupon);
                if (!is_wp_error($result)) {
                    $log_entries[] = sprintf('Promo code "%s" applied to order #%d', $promo_code, $order_id);
                }
            }
        }

        // Process customer note
        if (!empty($data['customer_note'])) {
            $note = sanitize_textarea_field($data['customer_note']);
            $note_type = sanitize_text_field($data['note_type']) === 'customer' ? 1 : 0;
            $order->add_order_note($note, $note_type);
            $log_entries[] = sprintf('Note added to order #%d', $order_id);
        }

        // Process customer ID
        if (!empty($data['customer_id'])) {
            $customer_id = intval($data['customer_id']);
            if ($order->get_customer_id() !== $customer_id) {
                $order->set_customer_id($customer_id);
                $log_entries[] = sprintf('Customer ID for order #%d changed to %d', $order_id, $customer_id);
            }
        }

        // Process order date and time
        if (!empty($data['order_datetime'])) {
            $new_datetime = new WC_DateTime($data['order_datetime']);
            $order->set_date_created($new_datetime);
            $log_entries[] = sprintf('Order #%d date and time changed to %s', $order_id, $new_datetime->format('Y-m-d H:i:s'));
        }

        $order->save();

        return ['order_id' => $order_id, 'log_entries' => $log_entries];
    }

    public function handle_update_single_order_ajax()
    {
        check_ajax_referer('bulk_order_editor_nonce', 'nonce');

        if (isset($_POST['order_id'])) {
            $order_id = intval(sanitize_text_field($_POST['order_id']));
            $order = wc_get_order($order_id);
            if ($order) {
                $log_entries = [];
                $current_user = wp_get_current_user();
                $current_user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

                // Update customer ID
                if (isset($_POST['customer_id']) && $order->get_customer_id() !== intval($_POST['customer_id'])) {
                    $previous_customer_id = $order->get_customer_id();
                    $order->set_customer_id(intval($_POST['customer_id']));
                    $log_entries[] = sprintf('Order #%d customer ID changed from "%d" to "%d"', $order_id, $previous_customer_id, intval($_POST['customer_id']));
                    $order->add_order_note(sprintf('Order customer ID changed from "%d" to "%d" by <b>%s</b>', $previous_customer_id, intval($_POST['customer_id']), $current_user_name));
                }

                // Update order status
                if (isset($_POST['order_status']) && $order->get_status() !== $_POST['order_status']) {
                    $previous_status = $order->get_status();
                    $order->set_status($_POST['order_status']);
                    $log_entries[] = sprintf('Order #%d status changed from "%s" to "%s"', $order_id, wc_get_order_status_name($previous_status), wc_get_order_status_name($_POST['order_status']));
                    $order->add_order_note(sprintf('Order status changed from "%s" to "%s" by <b>%s</b>', wc_get_order_status_name($previous_status), wc_get_order_status_name($_POST['order_status']), $current_user_name));
                }

                // Update order total
                if (isset($_POST['order_total']) && $order->get_total() != floatval($_POST['order_total'])) {
                    $previous_total = $order->get_total();
                    $order->set_total(floatval($_POST['order_total']));
                    $log_entries[] = sprintf('Order #%d total changed from %.2f to %.2f', $order_id, $previous_total, floatval($_POST['order_total']));
                    $order->add_order_note(sprintf('Order total changed from %.2f to %.2f by <b>%s</b>', $previous_total, floatval($_POST['order_total']), $current_user_name));
                }

                // Apply promo code
                if (!empty($_POST['promo_code'])) {
                    $coupon = new WC_Coupon(sanitize_text_field($_POST['promo_code']));
                    if ($coupon->get_id()) {
                        $result = $order->apply_coupon($coupon);
                        if (is_wp_error($result)) {
                            $log_entries[] = sprintf('Failed to add promo code "%s" to order #%d: %s', $_POST['promo_code'], $order_id, $result->get_error_message());
                        } else {
                            $log_entries[] = sprintf('Order #%d promo code added: "%s"', $order_id, $_POST['promo_code']);
                            $order->add_order_note(sprintf('Promo code "%s" added by <b>%s</b>', $_POST['promo_code'], $current_user_name));
                        }
                    } else {
                        $log_entries[] = sprintf('Failed to add promo code "%s" to order #%d: Coupon not found', $_POST['promo_code'], $order_id);
                    }
                }

                // Add customer note
                if (!empty($_POST['customer_note'])) {
                    $note_type = isset($_POST['note_type']) && $_POST['note_type'] === 'customer' ? true : false;
                    $order->add_order_note(sanitize_textarea_field($_POST['customer_note']), $note_type, false, $current_user_name);
                    $log_entries[] = sprintf('Order #%d note added: "%s"', $order_id, $_POST['customer_note']);
                }

                // Update order date
                if (!empty($_POST['order_datetime'])) {
                    $previous_datetime = $order->get_date_created()->format('Y-m-d H:i:s');
                    $new_datetime = new WC_DateTime($_POST['order_datetime']);
                    $order->set_date_created($new_datetime);
                    $log_entries[] = sprintf('Order #%d date and time of creation changed from "%s" to "%s"', $order_id, $previous_datetime, $new_datetime->format('Y-m-d H:i:s'));
                    $order->add_order_note(sprintf('Order date and time of creation changed from "%s" to "%s" by <b>%s</b>', $previous_datetime, $new_datetime->format('Y-m-d H:i:s'), $current_user_name));
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
}

// Register AJAX handlers
add_action('wp_ajax_batch_update_orders', array($this, 'handle_batch_update_orders'));
add_action('wp_ajax_update_single_order', array($this, 'handle_update_single_order_ajax'));