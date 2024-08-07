<?php

function order_status_editor_page_content()
{
    $order_statuses = get_woocommerce_order_statuses();
?>
    <div class="wrap">
        <h1>Bulk Order Editor</h1>
        <div id="response-message"></div>
        <form id="order-status-form" class="order-status-form">
            <div class="order-details">
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

                <div class="form-group">
                    <label for="promo_code">Promo Code:</label>
                    <input type="text" id="promo_code" name="promo_code">
                </div>
            </div>

            <div class="customer-details">
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
            </div>

            <div class="order-processing">
                <h2>Order Processing</h2>
                <div class="form-group">
                    <label for="order_date">Order Date (YYYY-MM-DD):</label>
                    <input type="date" id="order_date" name="order_date">
                </div>

                <div class="form-group">
                    <label for="order_time">Order Time (HH:MM):</label>
                    <input type="time" id="order_time" name="order_time">
                </div>
            </div>

            <div class="form-actions">
                <input type="submit" value="Update Orders" class="button button-primary">
            </div>
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
