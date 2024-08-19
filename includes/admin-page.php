<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Render the admin page content
 */
function boe_admin_page_content()
{
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Get WooCommerce order statuses
    $order_statuses = wc_get_order_statuses();

?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <form id="bulk-order-editor-form" method="post">
            <?php wp_nonce_field('bulk_order_editor_action', 'bulk_order_editor_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="order_ids"><?php _e('Order IDs', 'bulk-order-editor'); ?></label></th>
                    <td>
                        <input type="text" id="order_ids" name="order_ids" class="regular-text" placeholder="<?php esc_attr_e('Enter comma-separated order IDs', 'bulk-order-editor'); ?>" required>
                        <p class="description"><?php _e('Enter the IDs of the orders you want to edit, separated by commas.', 'bulk-order-editor'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="order_status"><?php _e('New Order Status', 'bulk-order-editor'); ?></label></th>
                    <td>
                        <select id="order_status" name="order_status">
                            <option value=""><?php _e('— Select —', 'bulk-order-editor'); ?></option>
                            <?php foreach ($order_statuses as $status => $label) : ?>
                                <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="customer_note"><?php _e('Customer Note', 'bulk-order-editor'); ?></label></th>
                    <td>
                        <textarea id="customer_note" name="customer_note" rows="5" cols="50" class="large-text"></textarea>
                        <p class="description"><?php _e('Add a note visible to customers. Leave blank for no change.', 'bulk-order-editor'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="private_note"><?php _e('Private Note', 'bulk-order-editor'); ?></label></th>
                    <td>
                        <textarea id="private_note" name="private_note" rows="5" cols="50" class="large-text"></textarea>
                        <p class="description"><?php _e('Add a private note. Leave blank for no change.', 'bulk-order-editor'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Update Orders', 'bulk-order-editor')); ?>
        </form>

        <div id="bulk-order-editor-results" style="display:none;">
            <h2><?php _e('Update Results', 'bulk-order-editor'); ?></h2>
            <div id="update-log"></div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#bulk-order-editor-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var results = $('#bulk-order-editor-results');
                var log = $('#update-log');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: form.serialize() + '&action=boe_update_orders',
                    beforeSend: function() {
                        form.find('input[type="submit"]').prop('disabled', true);
                        results.show();
                        log.html('<p><?php _e('Updating orders...', 'bulk-order-editor'); ?></p>');
                    },
                    success: function(response) {
                        if (response.success) {
                            log.html('<p>' + response.data.message + '</p>');
                            if (response.data.updates) {
                                var updates = '<ul>';
                                $.each(response.data.updates, function(index, update) {
                                    updates += '<li>' + update + '</li>';
                                });
                                updates += '</ul>';
                                log.append(updates);
                            }
                        } else {
                            log.html('<p><?php _e('Error: ', 'bulk-order-editor'); ?>' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        log.html('<p><?php _e('An error occurred. Please try again.', 'bulk-order-editor'); ?></p>');
                    },
                    complete: function() {
                        form.find('input[type="submit"]').prop('disabled', false);
                    }
                });
            });
        });
    </script>
<?php
}
