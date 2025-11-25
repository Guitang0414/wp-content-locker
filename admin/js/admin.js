/**
 * WP Content Locker Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
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
