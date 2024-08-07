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
        var orderDate = $('#order_date').val();
        var orderTime = $('#order_time').val();
        var actionerId = $('#actioner_id').val();

        if (orderIds[0].trim() === '') {
            alert('Please enter at least one order ID.');
            return;
        }

        $('#log-list').empty();
        $('#progress-percentage').text('0%');
        $('#update-progress').show();

        var completedRequests = 0;
        var totalRequests = orderIds.length;
        var errorsEncountered = false;

        orderIds.forEach(function(orderId) {
            orderId = orderId.trim();
            if (!orderId) {
                completedRequests++;
                updateProgress(completedRequests, totalRequests);
                return;
            }

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
                order_date: orderDate,
                order_time: orderTime,
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
                    order_date: orderDate,
                    order_time: orderTime,
                    actioner_id: actionerId,
                    hpos_enabled: true
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