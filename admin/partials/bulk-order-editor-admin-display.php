<?php
// Check if this file is being accessed directly
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
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
                <?php
                $order_statuses = wc_get_order_statuses();
                foreach ($order_statuses as $status_key => $status_label) :
                ?>
                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="order_total">Order Total:</label>
            <input type="number" step="0.01" id="order_total" name="order_total">
        </div>

        <div class="form-group">
            <label for="promo_code">Promo Code:</label>
            <input type="text" id="promo_code" name="promo_code">
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
            <label for="order_datetime">Order Date and Time:</label>
            <input type="datetime-local" id="order_datetime" name="order_datetime">
        </div>

        <h2>Shipping Details</h2>
        <div class="form-group">
            <label for="shipping_method">Shipping Method:</label>
            <select id="shipping_method" name="shipping_method">
                <option value="">Select shipping method</option>
                <?php
                $shipping_methods = WC()->shipping()->get_shipping_methods();
                foreach ($shipping_methods as $method) {
                    echo '<option value="' . esc_attr($method->id) . '">' . esc_html($method->method_title) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="tracking_number">Tracking Number:</label>
            <input type="text" id="tracking_number" name="tracking_number">
        </div>

        <?php wp_nonce_field('bulk_order_editor_nonce', 'bulk_order_editor_nonce'); ?>
        <input type="submit" value="Update Orders" class="button button-primary">
    </form>

    <div class="log-area">
        <h2>Update Log</h2>
        <p id="update-progress" style="display:none;">Progress: <span id="progress-percentage">0%</span></p>
        <ul id="log-list"></ul>
    </div>
</div>