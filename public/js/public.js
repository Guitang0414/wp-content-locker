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

            // Common selectors for account/user icons in headers or mobile menus
            // - a[href*="my-account"] : standard Woo
            // - a[href*="login"] : standard WP
            // - .account-link, .user-icon : common class names (guesswork)

            var selectors = [
                'a[href*="my-account"]',
                'a[href*="login-register"]', // User mentioned this specific slug
                'a[href*="/login"]',
                '.account-link a',
                '.user-account-link',
                '.mobile-menu a[href*="account"]'
            ];

            var $links = $(selectors.join(', '));

            if ($links.length) {
                console.log('WCL: Found ' + $links.length + ' account links to redirect on mobile.');
                $links.attr('href', targetUrl);

                // Also unbind potential existing events (like theme sidebars) and force navigation
                $links.off('click').on('click', function (e) {
                    e.stopPropagation();
                    // Let default action proceed with new href, or force it:
                    // window.location.href = targetUrl;
                });
            }
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
