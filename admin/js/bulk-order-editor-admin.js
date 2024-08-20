jQuery(document).ready(function($) {
    $('#order-status-form').on('submit', function(event) {
        event.preventDefault();

        var orderIds = $('#order_ids').val().split(',').map(function(id) { return id.trim(); }).filter(Boolean);
        var formData = $(this).serialize();

        if (orderIds.length === 0) {
            alert('Please enter at least one order ID.');
            return;
        }

        $('#log-list').empty();
        $('#progress-percentage').text('0%');
        $('#update-progress').show();
        $('#response-message').empty();

        processOrders(orderIds, formData, 0);
    });

    function processOrders(orderIds, formData, processed) {
        if (processed >= orderIds.length) {
            $('#response-message').html('<div class="notice notice-success"><p>All orders have been processed successfully.</p></div>');
            $('#update-progress').hide();
            clearFormFields();
            return;
        }

        var orderId = orderIds[processed];
        
        $.ajax({
            url: bulkOrderEditor.ajax_url,
            type: 'POST',
            data: formData + '&action=update_single_order&order_id=' + orderId + '&nonce=' + bulkOrderEditor.nonce,
            success: function(response) {
                if (response.success) {
                    updateLog(response.data.log_entries);
                } else {
                    $('#log-list').append('<li class="error">Error processing order #' + orderId + ': ' + response.data.message + '</li>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#log-list').append('<li class="error">Error processing order #' + orderId + ': ' + textStatus + ' - ' + errorThrown + '</li>');
            },
            complete: function() {
                processed++;
                updateProgress(processed, orderIds.length);
                processOrders(orderIds, formData, processed);
            }
        });
    }

    function updateProgress(processed, total) {
        var percentage = Math.round((processed / total) * 100);
        $('#progress-percentage').text(percentage + '%');
    }

    function updateLog(logEntries) {
        if (Array.isArray(logEntries)) {
            logEntries.forEach(function(entry) {
                $('#log-list').append('<li>' + entry + '</li>');
            });
        } else if (typeof logEntries === 'string') {
            $('#log-list').append('<li>' + logEntries + '</li>');
        }
        var logList = document.getElementById('log-list');
        logList.scrollTop = logList.scrollHeight;
    }

    // New function to log shipping method changes
    $('#shipping_method').on('change', function() {
        var selectedMethod = $(this).val();
        var selectedMethodText = $(this).find('option:selected').text();
        if (selectedMethod) {
            updateLog('Shipping method selected: ' + selectedMethodText);
        }
    });

    // New function to log tracking number changes
    var trackingNumberTimeout;
    $('#tracking_number').on('input', function() {
        var trackingNumber = $(this).val();
        clearTimeout(trackingNumberTimeout);
        trackingNumberTimeout = setTimeout(function() {
            if (trackingNumber.length > 0) {
                updateLog('Tracking number entered: ' + trackingNumber);
            }
        }, 500); // Wait for 500ms after the user stops typing
    });

    function clearFormFields() {
        $('#order_status, #order_total, #promo_code, #customer_id, #customer_note, #note_type, #order_datetime, #shipping_method, #tracking_number').val('');
        updateLog('Form fields cleared');
    }

    // Initial log entry when the page loads
    updateLog('Bulk Order Editor ready. Enter order IDs and make changes to begin.');
});