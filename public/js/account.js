jQuery(document).ready(function ($) {
    console.log('WCL Account Script Loaded');
    // Tab Switching
    $('.wcl-nav-item').click(function () {
        var tab = $(this).data('tab');

        $('.wcl-nav-item').removeClass('active');
        $(this).addClass('active');

        $('.wcl-tab-content').removeClass('active');
        $('#wcl-tab-' + tab).addClass('active');
    });

    // Profile Update
    $('#wcl-profile-form').submit(function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button');
        var msg = form.siblings('.wcl-message');

        btn.prop('disabled', true).text(wclAccount.strings.saving);
        msg.hide().removeClass('success error');

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_update_profile',
            nonce: wclAccount.nonce,
            first_name: form.find('input[name="first_name"]').val(),
            last_name: form.find('input[name="last_name"]').val()
        }, function (response) {
            btn.prop('disabled', false).text('Save Changes');
            if (response.success) {
                msg.addClass('success').text(response.data.message).show();
                // Update sidebar name
                $('.wcl-user-name').text(form.find('input[name="first_name"]').val() + ' ' + form.find('input[name="last_name"]').val());
            } else {
                msg.addClass('error').text(response.data.message).show();
            }
        });
    });

    // Password Change
    $('#wcl-password-form').submit(function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button');
        var msg = form.siblings('.wcl-message');

        var newPass = form.find('input[name="new_password"]').val();
        var confirmPass = form.find('input[name="confirm_password"]').val();

        if (newPass !== confirmPass) {
            msg.addClass('error').text(wclAccount.strings.passwordMismatch).show();
            return;
        }

        btn.prop('disabled', true).text(wclAccount.strings.saving);
        msg.hide().removeClass('success error');

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_change_password',
            nonce: wclAccount.nonce,
            current_password: form.find('input[name="current_password"]').val(),
            new_password: newPass,
            confirm_password: confirmPass
        }, function (response) {
            btn.prop('disabled', false).text('Update Password');
            if (response.success) {
                msg.addClass('success').text(response.data.message).show();
                form[0].reset();
            } else {
                msg.addClass('error').text(response.data.message).show();
            }
        });
    });

    // Login Form
    $('.wcl-login-form').submit(function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button');
        var msg = form.siblings('.wcl-message');

        btn.prop('disabled', true).text(wclAccount.strings.loggingIn);
        msg.hide().removeClass('success error');

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_login',
            nonce: wclAccount.nonce,
            username: form.find('input[name="username"]').val(),
            password: form.find('input[name="password"]').val(),
            remember: form.find('input[name="remember"]').is(':checked')
        }, function (response) {
            if (response.success) {
                location.reload();
            } else {
                btn.prop('disabled', false).text('Login');
                msg.addClass('error').text(response.data.message).show();
            }
        });
    });

    // Cancel Subscription
    $('.wcl-cancel-subscription-btn').click(function () {
        if (!confirm(wclAccount.strings.confirmCancel)) {
            return;
        }

        var btn = $(this);
        var originalText = btn.text();
        btn.prop('disabled', true).text(wclAccount.strings.canceling);

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_cancel_subscription',
            nonce: wclAccount.nonce
        }, function (response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message);
                btn.prop('disabled', false).text(originalText);
            }
        });
    });
    // Auth Toggle
    $(document).on('click', '.wcl-toggle-auth', function (e) {
        e.preventDefault();
        var target = $(this).data('target');
        console.log('Toggle clicked', target);

        if (target === 'register') {
            $('#wcl-login-wrapper').hide();
            $('#wcl-register-wrapper').fadeIn();
        } else {
            $('#wcl-register-wrapper').hide();
            $('#wcl-login-wrapper').fadeIn();
        }
    });

    // Register Form
    $(document).on('submit', '.wcl-register-form', function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button');
        var msg = form.siblings('.wcl-message');

        btn.prop('disabled', true).text(wclAccount.strings.processing || 'Processing...');
        msg.hide().removeClass('success error');

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_register',
            nonce: wclAccount.nonce,
            email: form.find('input[name="email"]').val(),
            name: form.find('input[name="name"]').val(),
            password: form.find('input[name="password"]').val()
        }, function (response) {
            if (response.success) {
                msg.addClass('success').text(response.data.message).show();
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                btn.prop('disabled', false).text('Register');
                msg.addClass('error').text(response.data.message).show();
            }
        });
    });
});
