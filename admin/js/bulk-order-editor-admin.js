jQuery(document).ready(function($) {
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',').map(function(id) { return id.trim(); }).filter(Boolean);
        var orderStatus = $('#order_status').val();
        var orderTotal = $('#order_total').val();
        var promoCode = $('#promo_code').val();
        var customerNote = $('#customer_note').val();
        var noteType = $('#note_type').val();
        var customerId = $('#customer_id').val();
        var orderDatetime = $('#order_datetime').val();

        if (orderIds.length === 0) {
            alert('Please enter at least one order ID.');
            return;
        }

        $('#log-list').empty();
        $('#progress-percentage').text('0%');
        $('#update-progress').show();

        if (orderIds.length === 1) {
            processSingleOrder(orderIds[0]);
        } else {
            processBatch(orderIds, 0);
        }
    });

    function processBatch(orderIds, processed) {
        $.ajax({
            url: bulkOrderEditor.ajax_url,
            type: 'POST',
            data: {
                action: 'batch_update_orders',
                nonce: bulkOrderEditor.nonce,
                order_ids: orderIds.join(','),
                processed: processed,
                order_status: $('#order_status').val(),
                order_total: $('#order_total').val(),
                promo_code: $('#promo_code').val(),
                customer_note: $('#customer_note').val(),
                note_type: $('#note_type').val(),
                customer_id: $('#customer_id').val(),
                order_datetime: $('#order_datetime').val()
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(response.processed, response.total);
                    updateLog(response.results);

                    if (!response.is_complete) {
                        processBatch(orderIds, response.processed);
                    } else {
                        $('#response-message').html('<div class="notice notice-success"><p>All orders have been processed successfully.</p></div>');
                    }
                } else {
                    $('#response-message').html('<div class="notice notice-error"><p>An error occurred during processing.</p></div>');
                }
            },
            error: function() {
                $('#response-message').html('<div class="notice notice-error"><p>An error occurred during processing.</p></div>');
            }
        });
    }

    function processSingleOrder(orderId) {
        $.ajax({
            url: bulkOrderEditor.ajax_url,
            type: 'POST',
            data: {
                action: 'update_single_order',
                nonce: bulkOrderEditor.nonce,
                order_id: orderId,
                order_status: $('#order_status').val(),
                order_total: $('#order_total').val(),
                promo_code: $('#promo_code').val(),
                customer_note: $('#customer_note').val(),
                note_type: $('#note_type').val(),
                customer_id: $('#customer_id').val(),
                order_datetime: $('#order_datetime').val()
            },
            success: function(response) {
                if (response.success) {
                    updateLog(response.data.log_entries);
                    $('#response-message').html('<div class="notice notice-success"><p>Order has been processed successfully.</p></div>');
                } else {
                    $('#response-message').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
                $('#update-progress').hide();
            },
            error: function() {
                $('#response-message').html('<div class="notice notice-error"><p>An error occurred during processing.</p></div>');
                $('#update-progress').hide();
            }
        });
    }

    function updateProgress(processed, total) {
        var percentage = Math.round((processed / total) * 100);
        $('#progress-percentage').text(percentage + '%');
    }

    function updateLog(results) {
        if (Array.isArray(results)) {
            results.forEach(function(result) {
                if (result.error) {
                    $('#log-list').append('<li class="error">' + result.error + '</li>');
                } else {
                    result.log_entries.forEach(function(entry) {
                        $('#log-list').append('<li>' + entry + '</li>');
                    });
                }
            });
        } else if (typeof results === 'string') {
            $('#log-list').append('<li>' + results + '</li>');
        }
    }
});