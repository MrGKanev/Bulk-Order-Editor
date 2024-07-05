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
                    $('#response-message').html('<div class="notice notice-success"><p>Order status updated successfully.</p></div>');
                } else {
                    $('#response-message').html('<div class="notice notice-error"><p>Failed to update order status.</p></div>');
                }
            },
            error: function() {
                $('#response-message').html('<div class="notice notice-error"><p>An error occurred.</p></div>');
            }
        });
    });
});