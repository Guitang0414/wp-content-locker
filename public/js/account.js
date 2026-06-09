jQuery(document).ready(function ($) {
    console.log('WCL Account Script Loaded');

    // Helper to parse URL params
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Check for tab parameter
    var initialTab = getUrlParameter('tab');
    if (initialTab && $('#wcl-tab-' + initialTab).length) {
        $('.wcl-nav-item').removeClass('active');
        $('.wcl-nav-item[data-tab="' + initialTab + '"]').addClass('active');

        $('.wcl-tab-content').removeClass('active');
        $('#wcl-tab-' + initialTab).addClass('active');
    }

    // Tab Switching
    $('.wcl-nav-item').click(function () {
        var tab = $(this).data('tab');

        // Update URL without reloading
        if (history.pushState) {
            var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tab;
            window.history.pushState({ path: newurl }, '', newurl);
        }

        $('.wcl-nav-item').removeClass('active');
        $(this).addClass('active');

        $('.wcl-tab-content').removeClass('active');
        $('#wcl-tab-' + tab).addClass('active');

        // Mobile UX: Scroll to content if sidebar is stacked
        if ($(window).width() < 768) {
            $('html, body').animate({
                scrollTop: $('.wcl-main-content').offset().top - 20
            }, 500);
        }
    });

    // GetResponse: force re-check membership
    $(document).on('click', '.wcl-gr-refresh-btn', function () {
        var btn = $(this);
        var row = btn.closest('[data-gr-row]');
        var originalText = btn.text();
        btn.prop('disabled', true).text('Refreshing…');
        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_refresh_gr',
            nonce: wclAccount.nonce
        }, function (response) {
            btn.prop('disabled', false).text(originalText);
            if (!response.success) return;
            var statusEl = row.find('.wcl-ext-sub-status');
            var actionEl = row.find('.wcl-ext-sub-action');
            if (response.data.subscribed) {
                statusEl.html('<span style="color:#22c55e;font-weight:600;">✅</span>');
                actionEl.remove();
            } else if (response.data.unknown) {
                statusEl.html('<span style="color:#999;">…</span>');
            } else {
                statusEl.html('<span style="color:#ef4444;font-weight:600;">❌</span>');
                if (response.data.subscribe_url && !actionEl.length) {
                    row.find('.wcl-ext-sub-label').after(
                        '<a href="' + response.data.subscribe_url + '" class="wcl-ext-sub-action" target="_blank" rel="noopener">Subscribe →</a>'
                    );
                }
            }
        });
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

    // Reset Turnstile widget inside a form after a failed submission
    // (tokens are single-use; without reset the next attempt fails too).
    function wclResetTurnstile(form) {
        if (!window.turnstile) return;
        var widget = form.find('.cf-turnstile')[0];
        if (widget) {
            try { turnstile.reset(widget); } catch (e) { /* ignore */ }
        }
    }

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
            remember: form.find('input[name="remember"]').is(':checked'),
            redirect_to: form.find('input[name="redirect_to"]').val(),
            wcl_hp_website: form.find('input[name="wcl_hp_website"]').val() || '',
            'cf-turnstile-response': form.find('[name="cf-turnstile-response"]').val() || ''
        }, function (response) {
            if (response.success) {
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    location.reload();
                }
            } else {
                btn.prop('disabled', false).text('Login');
                msg.addClass('error').text(response.data.message).show();
                wclResetTurnstile(form);
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

    // Resume Subscription
    $('.wcl-resume-subscription-btn').click(function () {
        if (!confirm(wclAccount.strings.confirmResume)) {
            return;
        }

        var btn = $(this);
        var originalText = btn.text();
        btn.prop('disabled', true).text(wclAccount.strings.resuming);

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_resume_subscription',
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
            password: form.find('input[name="password"]').val(),
            wcl_hp_website: form.find('input[name="wcl_hp_website"]').val() || '',
            'cf-turnstile-response': form.find('[name="cf-turnstile-response"]').val() || ''
        }, function (response) {
            if (response.success) {
                msg.addClass('success').text(response.data.message).show();
                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                btn.prop('disabled', false).text('Register');
                msg.addClass('error').text(response.data.message).show();
                wclResetTurnstile(form);
            }
        });
    });

    // Lost Password Form
    $(document).on('submit', '.wcl-lost-password-form', function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button');
        var msg = form.siblings('.wcl-message');

        btn.prop('disabled', true).text(wclAccount.strings.processing || 'Processing...');
        msg.hide().removeClass('success error');

        $.post(wclAccount.ajaxUrl, {
            action: 'wcl_lost_password',
            nonce: wclAccount.nonce,
            user_login: form.find('input[name="user_login"]').val(),
            wcl_hp_website: form.find('input[name="wcl_hp_website"]').val() || '',
            'cf-turnstile-response': form.find('[name="cf-turnstile-response"]').val() || ''
        }, function (response) {
            btn.prop('disabled', false).text('Get New Password');
            if (response.success) {
                msg.addClass('success').text(response.data.message).show();
                form[0].reset();
            } else {
                msg.addClass('error').text(response.data.message).show();
                wclResetTurnstile(form);
            }
        });
    });

    // Update Auth Toggle logic for lost password
    $(document).on('click', '.wcl-toggle-auth', function (e) {
        e.preventDefault();
        var target = $(this).data('target');
        console.log('Toggle clicked', target);

        $('#wcl-login-wrapper, #wcl-register-wrapper, #wcl-lost-password-wrapper').hide();

        if (target === 'register') {
            $('#wcl-register-wrapper').fadeIn();
        } else if (target === 'lost-password') {
            $('#wcl-lost-password-wrapper').fadeIn();
        } else {
            $('#wcl-login-wrapper').fadeIn();
        }
    });
});
