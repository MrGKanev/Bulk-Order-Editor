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
                $note = sprintf('Order status changed from "%s" to "%s" by %s', wc_get_order_status_name($previous_status), wc_get_order_status_name($new_status), $current_user_name);
                $order->add_order_note($note);
                $log_entries[] = sprintf('Order #%d: %s', $order_id, $note);
            }
        }

        // Process order total
        if (!empty($data['order_total'])) {
            $new_total = floatval($data['order_total']);
            $current_total = floatval($order->get_total());
            if (abs($current_total - $new_total) > 0.01) { // Check if the difference is more than 1 cent
                // Calculate the difference
                $difference = $new_total - $current_total;

                // Add a fee to adjust the total
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Total Adjustment');
                $fee->set_amount($difference);
                $fee->set_total($difference);
                $fee->set_tax_status('none');

                $order->add_item($fee);
                $order->calculate_totals(false); // false to not recalculate taxes

                $note = sprintf('Order total changed from %.2f to %.2f by %s', $current_total, $new_total, $current_user_name);
                $order->add_order_note($note);
                $log_entries[] = sprintf('Order #%d: %s', $order_id, $note);
            }
        }

        // Process promo code
        if (!empty($data['promo_code'])) {
            $promo_code = sanitize_text_field($data['promo_code']);
            $coupon = new WC_Coupon($promo_code);
            if ($coupon->get_id()) {
                $result = $order->apply_coupon($coupon);
                if (!is_wp_error($result)) {
                    $note = sprintf('Promo code "%s" applied by %s', $promo_code, $current_user_name);
                    $order->add_order_note($note);
                    $log_entries[] = sprintf('Order #%d: %s', $order_id, $note);
                }
            }
        }

        // Process customer note
        if (!empty($data['customer_note'])) {
            $note = sanitize_textarea_field($data['customer_note']);
            $is_customer_note = isset($data['note_type']) && $data['note_type'] === 'customer';
            if ($is_customer_note) {
                $note_type = 'Customer note';
                $order->add_order_note($note, 1, false); // 1 for customer note
                $log_entries[] = sprintf('Order #%d: Customer note added: "%s"', $order_id, $note);
            } else {
                $note_type = 'Private note';
                $private_note = sprintf('%s (added by %s)', $note, $current_user_name);
                $order->add_order_note($private_note, 0, false); // 0 for private note
                $log_entries[] = sprintf('Order #%d: Private note added by %s: "%s"', $order_id, $current_user_name, $note);
            }
        }

        // Process customer ID
        if (!empty($data['customer_id'])) {
            $new_customer_id = intval($data['customer_id']);
            $current_customer_id = $order->get_customer_id();
            if ($current_customer_id !== $new_customer_id) {
                $order->set_customer_id($new_customer_id);
                $note = sprintf('Customer ID changed from %d to %d by %s', $current_customer_id, $new_customer_id, $current_user_name);
                $order->add_order_note($note);
                $log_entries[] = sprintf('Order #%d: %s', $order_id, $note);
            }
        }

        // Process order date
        if (!empty($data['order_datetime'])) {
            $new_datetime = new WC_DateTime($data['order_datetime']);
            $current_datetime = $order->get_date_created();
            if ($current_datetime->getTimestamp() !== $new_datetime->getTimestamp()) {
                $order->set_date_created($new_datetime);
                $note = sprintf('Order date changed from %s to %s by %s', $current_datetime->format('Y-m-d H:i:s'), $new_datetime->format('Y-m-d H:i:s'), $current_user_name);
                $order->add_order_note($note);
                $log_entries[] = sprintf('Order #%d: %s', $order_id, $note);
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
