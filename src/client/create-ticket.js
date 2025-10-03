jQuery(document).ready(function($) {
    // Tab switching
    $('.wpspt-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        $('.wpspt-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.wpspt-tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });

    // Submit ticket form
    $('#wpspt-new-ticket-form').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            action: 'wpspt_submit_ticket',
            nonce: wpsptData.nonce,
            title: $('#ticket-title').val(),
            description: $('#ticket-description').val(),
            ticket_type: $('#ticket-type').val(),
            site_url: $('#site-url').val(),
            admin_url: $('#admin-url').val(),
            wp_username: $('#wp-username').val(),
            wp_password: $('#wp-password').val(),
            credentials_notes: $('#credentials-notes').val()
        };

        $.post(wpsptData.ajaxUrl, formData, function(response) {
            if (response.success) {
                $('.wpspt-message').removeClass('error').addClass('success').text(response.data.message).show();
                $('#wpspt-new-ticket-form')[0].reset();
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                $('.wpspt-message').removeClass('success').addClass('error').text(response.data.message).show();
            }
        });
    });
});
