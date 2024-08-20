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
        $current_user = wp_get_current_user();
        $current_user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

        // Process order status
        if (!empty($data['order_status'])) {
            $new_status = sanitize_text_field($data['order_status']);
            if ($order->get_status() !== $new_status) {
                $previous_status = $order->get_status();
                $order->set_status($new_status);
                $log_entries[] = sprintf('Order #%d status changed from "%s" to "%s"', $order_id, wc_get_order_status_name($previous_status), wc_get_order_status_name($new_status));
                $order->add_order_note(sprintf('Order status changed from "%s" to "%s" by <b>%s</b>', wc_get_order_status_name($previous_status), wc_get_order_status_name($new_status), $current_user_name));
            }
        }

        // Process order total
        if (!empty($data['order_total'])) {
            $new_total = floatval($data['order_total']);
            $current_total = floatval($order->get_total());
            if ($current_total !== $new_total) {
                // Calculate the difference
                $difference = $new_total - $current_total;

                // Add a fee to adjust the total
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Total Adjustment');
                $fee->set_amount($difference);
                $fee->set_total($difference);

                $order->add_item($fee);
                $order->calculate_totals();

                $log_entries[] = sprintf('Order #%d total changed from %.2f to %.2f', $order_id, $current_total, $new_total);
                $order->add_order_note(sprintf('Order total changed from %.2f to %.2f by <b>%s</b>', $current_total, $new_total, $current_user_name));
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
                    $order->add_order_note(sprintf('Promo code "%s" applied by <b>%s</b>', $promo_code, $current_user_name));
                }
            }
        }

        // Process customer note
        if (!empty($data['customer_note'])) {
            $note = sanitize_textarea_field($data['customer_note']);
            $is_customer_note = isset($data['note_type']) && $data['note_type'] === 'customer';
            $order->add_order_note($note, $is_customer_note, false);
            $log_entries[] = sprintf('Note added to order #%d', $order_id);
        }

        // Process customer ID
        if (!empty($data['customer_id'])) {
            $new_customer_id = intval($data['customer_id']);
            $current_customer_id = $order->get_customer_id();
            if ($current_customer_id !== $new_customer_id) {
                $order->set_customer_id($new_customer_id);
                $log_entries[] = sprintf('Customer ID for order #%d changed from %d to %d', $order_id, $current_customer_id, $new_customer_id);
                $order->add_order_note(sprintf('Customer ID changed from %d to %d by <b>%s</b>', $current_customer_id, $new_customer_id, $current_user_name));
            }
        }

        // Process order date
        if (!empty($data['order_datetime'])) {
            $new_datetime = new WC_DateTime($data['order_datetime']);
            $current_datetime = $order->get_date_created();
            if ($current_datetime->getTimestamp() !== $new_datetime->getTimestamp()) {
                $order->set_date_created($new_datetime);
                $log_entries[] = sprintf('Order #%d date changed from %s to %s', $order_id, $current_datetime->format('Y-m-d H:i:s'), $new_datetime->format('Y-m-d H:i:s'));
                $order->add_order_note(sprintf('Order date changed from %s to %s by <b>%s</b>', $current_datetime->format('Y-m-d H:i:s'), $new_datetime->format('Y-m-d H:i:s'), $current_user_name));
            }
        }

        $order->save();

        return ['order_id' => $order_id, 'log_entries' => $log_entries];
    }

    public function handle_update_single_order_ajax()
    {
        check_ajax_referer('bulk_order_editor_nonce', 'nonce');

        if (isset($_POST['order_id'])) {
            $result = $this->process_single_order($_POST['order_id'], $_POST);
            if (isset($result['error'])) {
                wp_send_json_error(['message' => $result['error']]);
            } else {
                wp_send_json_success(['log_entries' => $result['log_entries']]);
            }
        } else {
            wp_send_json_error(['message' => 'Invalid order ID.']);
        }
    }
}
