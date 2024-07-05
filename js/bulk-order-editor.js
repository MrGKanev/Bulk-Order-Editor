jQuery(document).ready(function($) {
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',');
        var orderStatus = $('#order_status').val();
        var orderTotal = $('#order_total').val();
        var customerNote = $('#customer_note').val();

        $('#log-list').empty();
        orderIds.forEach(function(orderId) {
            orderId = orderId.trim();
            $.ajax({
                url: bulkOrderEditor.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_single_order',
                    nonce: bulkOrderEditor.nonce,
                    order_id: orderId,
                    order_status: orderStatus,
                    order_total: orderTotal,
                    customer_note: customerNote
                },
                success: function(response) {
                    var logList = $('#log-list');
                    if (response.success) {
                        response.data.log_entries.forEach(function(log) {
                            logList.append('<li>' + log + '</li>');
                        });
                        $('#response-message').html('<div class="notice notice-success"><p>Order #' + orderId + ' updated successfully.</p></div>');
                    } else {
                        $('#response-message').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $('#response-message').html('<div class="notice notice-error"><p>An error occurred with order #' + orderId + '.</p></div>');
                }
            });
        });
    });
});