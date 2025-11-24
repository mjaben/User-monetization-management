jQuery(document).ready(function($) {
    
    // Toggle withdrawal method
    $('#umm-withdrawal-method').on('change', function() {
        var method = $(this).val();
        $('.umm-method-group').hide();
        $('#umm-method-' + method).show();
    });

    // Trigger change on load to set initial state
    $('#umm-withdrawal-method').trigger('change');

    // Handle Form Submission
    $('#mycred-isp-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var btn  = form.find('button');
        var msg  = form.find('.mycred-isp-message');

        btn.prop('disabled', true).text('Processing...');
        msg.html('');

        var data = form.serialize();
        data += '&action=mycred_isp_withdraw&security=' + MyCredISP.nonce;

        $.post(MyCredISP.ajaxurl, data, function(response) {
            if ( response.success ) {
                msg.html('<div class="mycred-isp-success">' + response.data.message + '</div>');
                form[0].reset();
                // Reset toggle
                $('#umm-withdrawal-method').trigger('change');
            } else {
                msg.html('<div class="mycred-isp-error">' + response.data.message + '</div>');
            }
            btn.prop('disabled', false).text('Redeem Points');
        });
    });

});
