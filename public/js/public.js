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
            // Only strictly on mobile (width < 768px)
            if ($(window).width() >= 768) {
                return;
            }

            // Target URL provided by backend
            var targetUrl = (typeof wclData !== 'undefined' && wclData.accountPageUrl) ? wclData.accountPageUrl : '';
            if (!targetUrl) return;

            // Use Native Capture Phase to intercept clicks before theme handlers
            document.addEventListener('click', function (e) {
                // Check if the clicked element or its parent matches our target
                // We use closest() to handle clicks on the icon <i> inside the <a>
                var selector = '.tdw-wml-link, .tdw-wml-wrap a, a[href*="login-register"], .mobile-menu a[href*="account"]';
                var link = e.target.closest(selector);

                if (link) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    window.location.href = targetUrl;
                }
            }, true); // 'true' enables Capture Phase
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
