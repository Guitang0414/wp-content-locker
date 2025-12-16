/**
 * WP Content Locker Public Scripts - WSJ/WaPo Style
 */

(function ($) {
    'use strict';

    var WCLPaywall = {
        init: function () {
            this.checkSuccessMessage();
        },

        checkSuccessMessage: function () {
            // Check URL for success parameter
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('wcl_subscribed') === '1') {
                // Show success message
                var $content = $('.wcl-content-wrapper');
                if ($content.length) {
                    $content.before(
                        '<div class="wcl-success-notice">' +
                        'Thank you for subscribing! You now have access to all premium content.' +
                        '</div>'
                    );
                }

                // Clean up URL
                if (window.history.replaceState) {
                    var cleanUrl = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            }
        }
    };

    // Initialize on DOM ready
    $(document).ready(function () {
        WCLPaywall.init();
    });

})(jQuery);
