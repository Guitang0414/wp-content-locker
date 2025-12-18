/**
 * WP Content Locker Public Scripts - WSJ/WaPo Style
 */

(function ($) {
    'use strict';

    var WCLPaywall = {
        init: function () {
            this.checkSuccessMessage();
            this.redirectMobileAccountLinks();
        },

        redirectMobileAccountLinks: function () {
            // DEBUG MODE: Run on ALL devices for testing
            // if ($(window).width() >= 768) return; 

            // Target URL provided by backend
            var targetUrl = (typeof wclData !== 'undefined' && wclData.accountPageUrl) ? wclData.accountPageUrl : '';

            // Visual Debug Banner
            if (!$('#wcl-debug-banner').length) {
                $('body').prepend('<div id="wcl-debug-banner" style="position:fixed;top:0;left:0;width:100%;background:red;color:white;z-index:999999;text-align:center;padding:10px;font-weight:bold;border-bottom:2px solid yellow;font-size:16px;">WCL v2 LOADED - Waiting...</div>');
            }

            if (!targetUrl) {
                $('#wcl-debug-banner').append(' (No Target URL)');
                return;
            }

            // Function to apply logic to found elements
            function interceptLinks() {
                var selector = '.tdw-wml-link, .tdw-wml-wrap a, a[href*="login-register"], .mobile-menu a[href*="account"]';
                var $links = $(selector);

                if ($links.length) {
                    $links.css({
                        'border': '5px solid red',
                        'background': 'rgba(255,0,0,0.2)',
                        'display': 'block' // Ensure visibility
                    });
                    $('#wcl-debug-banner').text('WCL JS: FOUND TARGET (' + $links.length + ')').css('background', '#0f0').css('color', '#000');
                }
            }

            // 1. Run immediately
            interceptLinks();

            // 2. Setup MutationObserver for dynamic content
            var observer = new MutationObserver(function (mutations) {
                interceptLinks();
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // 3. Keep Capture Phase Listener active
            document.addEventListener('click', function (e) {
                var selector = '.tdw-wml-link, .tdw-wml-wrap a, a[href*="login-register"], .mobile-menu a[href*="account"]';
                var link = e.target.closest(selector);

                if (link) {
                    console.log('WCL: Intercepted click on:', link);
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    window.location.href = targetUrl;
                }
            }, true);
        },

        checkSuccessMessage: function () {
            // Check URL for success parameter
            var urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('wcl_new_user') === '1') {
                this.showWelcomePopup();
            } else if (urlParams.get('wcl_subscribed') === '1') {
                // Show standard success message if not new user popup
                var $content = $('.wcl-content-wrapper');
                if ($content.length) {
                    $content.before(
                        '<div class="wcl-success-notice">' +
                        'Thank you for subscribing! You now have access to all premium content.' +
                        '</div>'
                    );
                }
            }

            // Clean up URL if we have either parameter
            if ((urlParams.get('wcl_subscribed') === '1' || urlParams.get('wcl_new_user') === '1') && window.history.replaceState) {
                var cleanUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, cleanUrl);
            }
        },

        showWelcomePopup: function () {
            var emailText = (typeof wclData !== 'undefined' && wclData.userEmail)
                ? 'We have sent an email to <strong>' + wclData.userEmail + '</strong> containing your initial password.'
                : 'We have sent an email containing your initial password.';

            var popupHtml =
                '<div class="wcl-welcome-popup-overlay">' +
                '<div class="wcl-welcome-popup">' +
                '<button class="wcl-popup-close">&times;</button>' +
                '<div class="wcl-popup-icon">&#10004;</div>' + // Checkmark
                '<h3 class="wcl-popup-title">Welcome Aboard!</h3>' +
                '<div class="wcl-popup-message">' +
                '<p>' + emailText + '</p>' +
                '<p>Please check your inbox and reset your password at your convenience.</p>' +
                '</div>' +
                '<button class="wcl-popup-btn">Got it</button>' +
                '</div>' +
                '</div>';

            $('body').append(popupHtml);

            // Trigger animation
            setTimeout(function () {
                $('.wcl-welcome-popup-overlay').addClass('active');
            }, 10);

            // Bind events
            $(document).on('click', '.wcl-popup-close, .wcl-popup-btn', function () {
                $('.wcl-welcome-popup-overlay').removeClass('active');
                setTimeout(function () {
                    $('.wcl-welcome-popup-overlay').remove();
                }, 300);
            });
        }
    };

    // Initialize on DOM ready
    $(document).ready(function () {
        WCLPaywall.init();
    });

})(jQuery);
