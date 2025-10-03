jQuery(document).ready(function($) {
    'use strict';

    // Admin reply button handler
    $('#wpspt-admin-reply-btn').on('click', function() {
        var btn = $(this);
        var ticketId = btn.data('ticket-id');
        var message = $('#wpspt-admin-reply').val();
        var statusSpan = $('.wpspt-reply-status');

        if (!message.trim()) {
            statusSpan.text('Please enter a message.').css('color', 'red');
            return;
        }

        btn.prop('disabled', true);
        statusSpan.text('Sending...').css('color', '#666');

        $.ajax({
            url: wpsptAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpspt_admin_add_reply',
                nonce: wpsptAdmin.nonce,
                ticket_id: ticketId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.text('Reply sent! Reloading...').css('color', 'green');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    statusSpan.text('Error: ' + response.data.message).css('color', 'red');
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                statusSpan.text('Network error. Please try again.').css('color', 'red');
                btn.prop('disabled', false);
            }
        });
    });

    // Status update handler (if needed for quick status changes)
    $('.wpspt-status-quick-change').on('change', function() {
        var ticketId = $(this).data('ticket-id');
        var newStatus = $(this).val();

        $.ajax({
            url: wpsptAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpspt_admin_update_status',
                nonce: wpsptAdmin.nonce,
                ticket_id: ticketId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });
});
