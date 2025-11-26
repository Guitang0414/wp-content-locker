/**
 * WP Content Locker - Account Page JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Login form handler
        $('.wcl-login-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.wcl-login-btn');
            var $error = $form.find('.wcl-login-error');
            var originalText = $btn.text();

            // Get form data
            var username = $form.find('#wcl_username').val();
            var password = $form.find('#wcl_password').val();
            var remember = $form.find('input[name="remember"]').is(':checked');

            // Disable button and show loading
            $btn.prop('disabled', true).text(wclAccount.strings.loggingIn);
            $error.hide();

            $.ajax({
                url: wclAccount.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_login',
                    nonce: wclAccount.nonce,
                    username: username,
                    password: password,
                    remember: remember ? 'true' : 'false'
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page on success
                        window.location.reload();
                    } else {
                        $error.text(response.data.message).show();
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    $error.text(wclAccount.strings.error).show();
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Cancel subscription handler
        $('.wcl-cancel-subscription-btn').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.text();

            // Confirm cancellation
            if (!confirm(wclAccount.strings.confirmCancel)) {
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true).text(wclAccount.strings.canceling);

            $.ajax({
                url: wclAccount.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_cancel_subscription',
                    nonce: wclAccount.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated status
                        window.location.href = window.location.pathname + '?wcl_canceled=1';
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(wclAccount.strings.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
    });

})(jQuery);
