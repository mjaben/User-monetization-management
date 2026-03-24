jQuery(document).ready(function ($) {

    var $form   = $('#mycred-isp-form');
    var $select = $('#umm-withdrawal-method');

    /* ── Toggle withdrawal method fields ─────────────────────────── */
    function switchMethod(method) {
        $('.umm-method-group').each(function () {
            var $g = $(this);
            if ($g.attr('id') === 'umm-method-' + method) {
                $g.stop(true, true).slideDown(220);
            } else {
                $g.stop(true, true).slideUp(220);
            }
        });
    }

    $select.on('change', function () {
        switchMethod($(this).val());
    });

    // Set correct initial state on page load
    if ($select.length) {
        // Show only the first selected method; hide the rest instantly
        var initialMethod = $select.val();
        $('.umm-method-group').each(function () {
            if ($(this).attr('id') !== 'umm-method-' + initialMethod) {
                $(this).hide();
            }
        });
    }

    /* ── Form submission ──────────────────────────────────────────── */
    $form.on('submit', function (e) {
        e.preventDefault();

        var $btn      = $form.find('.umm-submit-btn');
        var $msg      = $form.find('.mycred-isp-message');
        var origText  = $btn.text().trim();

        $btn.prop('disabled', true)
            .addClass('umm-loading')
            .text('');       // text cleared; spinner via ::before pseudo-element

        $msg.html('');

        $.post(
            MyCredISP.ajaxurl,
            $form.serialize() + '&action=mycred_isp_withdraw&security=' + MyCredISP.nonce,
            function (response) {
                if (response.success) {
                    $msg.html('<div class="mycred-isp-success">' + response.data.message + '</div>');
                    $form[0].reset();
                    if ($select.length) {
                        switchMethod($select.val());
                    }
                } else {
                    $msg.html('<div class="mycred-isp-error">' + response.data.message + '</div>');
                }
            }
        ).fail(function () {
            $msg.html('<div class="mycred-isp-error">An error occurred. Please try again.</div>');
        }).always(function () {
            $btn.prop('disabled', false)
                .removeClass('umm-loading')
                .text(origText);
        });
    });

});
