jQuery(document).ready(function($) {
    // Initialize Select2 for user selection
    $('.user-select').select2({
        ajax: {
            url: bulkOrderEditor.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'search_users',
                    nonce: bulkOrderEditor.nonce,
                    q: params.term, // Search term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 1,
        placeholder: 'Select a user',
        allowClear: true,
    });

    // Existing code for form submission
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',');
        var orderStatus = $('#order_status').val();
        var orderTotal = $('#order_total').val();
        var customerNote = $('#customer_note').val();
        var noteType = $('#note_type').val();
        var customerId = $('#customer_id').val();
        var orderDate = $('#order_date').val();
        var actionerId = $('#actioner_id').val();

        if (orderIds[0].trim() === '') {
            alert('Please enter at least one order ID.');
            return; // Stop execution if no IDs are provided.
        }

        $('#log-list').empty(); // Clear existing logs before starting new submissions.

        var completedRequests = 0;
        var totalRequests = orderIds.length;
        var errorsEncountered = false;

        orderIds.forEach(function(orderId) {
            orderId = orderId.trim();
            if (!orderId) {
                completedRequests++; // Skip empty entries and count them as 'processed'.
                return;
            }
            $.ajax({
                url: bulkOrderEditor.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_single_order',
                    nonce: bulkOrderEditor.nonce,
                    order_id: orderId,
                    order_status: orderStatus,
                    order_total: orderTotal,
                    customer_note: customerNote,
                    note_type: noteType,
                    customer_id: customerId,
                    order_date: orderDate,
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
                    if (completedRequests === totalRequests) {
                        var messageClass = errorsEncountered ? 'notice-error' : 'notice-success';
                        var messageText = errorsEncountered ? 'Completed with errors. See log for details.' : 'All orders have been processed successfully.';
                        $('#response-message').html('<div class="notice ' + messageClass + '"><p>' + messageText + '</p></div>');
                    }
                }
            });
        });
    });
});