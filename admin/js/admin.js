/**
 * WP Content Locker Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Tabbed settings: switch panes without reloading the page.
        $('.wcl-tab-link').on('click', function (e) {
            e.preventDefault();
            var slug = $(this).data('tab');
            if (!slug) return;

            $('.wcl-tab-link').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.wcl-settings-tab').removeClass('active');
            $('.wcl-settings-tab[data-tab="' + slug + '"]').addClass('active');

            // Keep URL shareable / back-button friendly without a reload.
            if (window.history && window.history.pushState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', slug);
                window.history.pushState({ tab: slug }, '', url.toString());
            }
        });

        // Browser back/forward: re-sync active tab from the URL.
        $(window).on('popstate', function () {
            var slug = new URL(window.location.href).searchParams.get('tab') || 'stripe';
            $('.wcl-tab-link[data-tab="' + slug + '"]').trigger('click');
        });

        // Toggle Stripe key fields based on mode
        var $modeSelect = $('#wcl_stripe_mode');

        function toggleStripeFields() {
            var mode = $modeSelect.val();

            if (mode === 'test') {
                $('.stripe-test-field').closest('tr').show();
                $('.stripe-live-field').closest('tr').hide();
            } else {
                $('.stripe-test-field').closest('tr').hide();
                $('.stripe-live-field').closest('tr').show();
            }
        }

        // Initial toggle
        toggleStripeFields();

        // On change
        $modeSelect.on('change', toggleStripeFields);
    });

})(jQuery);
