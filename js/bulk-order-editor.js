jQuery(document).ready(function($) {
    const $form = $('#order-status-form');
    const $logList = $('#log-list');
    const $progressBar = $('#update-progress');
    const $progressPercentage = $('#progress-percentage');
    const $responseMessage = $('#response-message');

    $form.on('submit', function(event) {
        event.preventDefault();

        const orderIds = $('#order_ids').val().split(',').map(id => id.trim()).filter(Boolean);
        const orderStatus = $('#order_status').val();
        const orderTotal = $('#order_total').val();
        const promoCode = $('#promo_code').val();
        const customerNote = $('#customer_note').val();
        const noteType = $('#note_type').val();
        const customerId = $('#customer_id').val();
        const orderDate = $('#order_date').val();
        const orderTime = $('#order_time').val();

        if (orderIds.length === 0) {
            alert('Please enter at least one valid order ID.');
            return;
        }

        $logList.empty();
        $progressPercentage.text('0%');
        $progressBar.show();
        $responseMessage.empty();

        let completedRequests = 0;
        let successfulUpdates = 0;
        let errorsEncountered = false;

        orderIds.forEach(function(orderId) {
            $.ajax({
                url: boeAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'boe_update_order',
                    nonce: boeAjax.nonce,
                    order_id: orderId,
                    order_status: orderStatus,
                    order_total: orderTotal,
                    promo_code: promoCode,
                    customer_note: customerNote,
                    note_type: noteType,
                    customer_id: customerId,
                    order_date: orderDate,
                    order_time: orderTime
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'success') {
                            response.data.log_entries.forEach(function(log) {
                                $logList.append('<li>' + log + '</li>');
                            });
                            successfulUpdates++;
                        } else if (response.data.status === 'no_changes') {
                            $logList.append('<li>No changes made to order #' + orderId + '</li>');
                        }
                    } else {
                        $logList.append('<li>Error with order #' + orderId + ': ' + response.data.message + '</li>');
                        errorsEncountered = true;
                    }
                },
                error: function(xhr, status, error) {
                    $logList.append('<li>Request failed for order #' + orderId + ': ' + error + '</li>');
                    errorsEncountered = true;
                },
                complete: function() {
                    completedRequests++;
                    updateProgress(completedRequests, orderIds.length);
                    if (completedRequests === orderIds.length) {
                        displayFinalMessage(successfulUpdates, orderIds.length, errorsEncountered);
                    }
                }
            });
        });
    });

    function updateProgress(completed, total) {
        const progressPercentage = Math.round((completed / total) * 100);
        $progressPercentage.text(progressPercentage + '%');
    }

    function displayFinalMessage(successful, total, hasErrors) {
        let message, messageClass;
        if (successful === total && !hasErrors) {
            message = 'All orders have been processed successfully.';
            messageClass = 'notice-success';
        } else if (successful > 0) {
            message = `Completed with ${successful} out of ${total} orders updated successfully.`;
            messageClass = hasErrors ? 'notice-warning' : 'notice-success';
        } else {
            message = 'Failed to update any orders. Please check the log for details.';
            messageClass = 'notice-error';
        }
        $responseMessage.html('<div class="notice ' + messageClass + '"><p>' + message + '</p></div>');
    }

    // Optional: Add real-time validation for order IDs
    $('#order_ids').on('change', function() {
        const orderIds = $(this).val().split(',').map(id => id.trim()).filter(Boolean);
        if (orderIds.length === 0) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });

    // Optional: Add datepicker for order date field if you're using jQuery UI
    if ($.datepicker) {
        $('#order_date').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }

    // Optional: Add timepicker for order time field if you're using jQuery UI Timepicker
    if ($.timepicker) {
        $('#order_time').timepicker({
            timeFormat: 'HH:mm'
        });
    }
});