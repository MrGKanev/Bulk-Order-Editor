jQuery(document).ready(function($) {
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',');
        var orderStatus = $('#order_status').val();
        var orderTotal = $('#order_total').val();
        var promoCode = $('#promo_code').val();
        var customerNote = $('#customer_note').val();
        var noteType = $('#note_type').val();
        var customerId = $('#customer_id').val();
        var orderDatetime = $('#order_datetime').val();
        var actionerId = $('#actioner_id').val();

        if (orderIds[0].trim() === '') {
            alert('Please enter at least one order ID.');
            return; // Stop execution if no IDs are provided.
        }

        $('#log-list').empty(); // Clear existing logs before starting new submissions.
        $('#progress-percentage').text('0%'); // Reset progress percentage.
        $('#update-progress').show(); // Show the progress text

        var completedRequests = 0;
        var totalRequests = orderIds.length;
        var errorsEncountered = false;

        orderIds.forEach(function(orderId) {
            orderId = orderId.trim();
            if (!orderId) {
                completedRequests++; // Skip empty entries and count them as 'processed'.
                updateProgress(completedRequests, totalRequests);
                return;
            }

            // Log the data being sent
            console.log({
                action: 'update_single_order',
                nonce: bulkOrderEditor.nonce,
                order_id: orderId,
                order_status: orderStatus,
                order_total: orderTotal,
                promo_code: promoCode,
                customer_note: customerNote,
                note_type: noteType,
                customer_id: customerId,
                order_datetime: orderDatetime,
                actioner_id: actionerId
            });

            $.ajax({
                url: bulkOrderEditor.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_single_order',
                    nonce: bulkOrderEditor.nonce,
                    order_id: orderId,
                    order_status: orderStatus,
                    order_total: orderTotal,
                    promo_code: promoCode,
                    customer_note: customerNote,
                    note_type: noteType,
                    customer_id: customerId,
                    order_datetime: orderDatetime,
                    actioner_id: actionerId
                },
                success: function(response) {
                    if (response.success) {
                        response.data.log_entries.forEach(function(log) {
                            $('#log-list').append('<li>' + log + '</li>');
                        });
                    } else {
                        $('#log-list').append('<li>Error with order #' + orderId + ': ' + response.data.message + '</li>');
                        errorsEncountered = true;
                    }
                },
                error: function(xhr, status, error) {
                    $('#log-list').append('<li>Request failed for order #' + orderId + ': ' + error + '</li>');
                    errorsEncountered = true;
                },
                complete: function() {
                    completedRequests++;
                    updateProgress(completedRequests, totalRequests);
                    if (completedRequests === totalRequests) {
                        var messageClass = errorsEncountered ? 'notice-error' : 'notice-success';
                        var messageText = errorsEncountered ? 'Completed with errors. See log for details.' : 'All orders have been processed successfully.';
                        $('#response-message').html('<div class="notice ' + messageClass + '"><p>' + messageText + '</p></div>');
                    }
                }
            });
        });

        function updateProgress(completed, total) {
            var progressPercentage = Math.round((completed / total) * 100);
            $('#progress-percentage').text(progressPercentage + '%');
        }
    });
});

jQuery(document).ready(function($) {
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',').map(function(id) { return id.trim(); }).filter(Boolean);
        var orderStatus = $('#order_status').val();
        var orderTotal = $('#order_total').val();
        var promoCode = $('#promo_code').val();
        var orderDatetime = $('#order_datetime').val();

        if (orderIds.length === 0) {
            alert('Please enter at least one order ID.');
            return;
        }

        $('#log-list').empty();
        $('#progress-percentage').text('0%');
        $('#update-progress').show();

        processBatch(orderIds, 0);
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

    function updateProgress(processed, total) {
        var percentage = Math.round((processed / total) * 100);
        $('#progress-percentage').text(percentage + '%');
    }

    function updateLog(results) {
        results.forEach(function(result) {
            if (result.error) {
                $('#log-list').append('<li class="error">' + result.error + '</li>');
            } else {
                result.log_entries.forEach(function(entry) {
                    $('#log-list').append('<li>' + entry + '</li>');
                });
            }
        });
    }
});