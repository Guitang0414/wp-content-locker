/**
 * WP Content Locker Public Scripts
 */

(function($) {
    'use strict';

    var WCLPaywall = {
        selectedPlan: 'monthly',

        init: function() {
            this.bindEvents();
            this.checkSuccessMessage();
        },

        bindEvents: function() {
            var self = this;

            // Plan selection
            $(document).on('click', '.wcl-price-card', function() {
                var $card = $(this);
                var plan = $card.data('plan');

                // Update selection
                $('.wcl-price-card').removeClass('selected');
                $card.addClass('selected');
                self.selectedPlan = plan;
            });

            // Subscribe button
            $(document).on('click', '.wcl-subscribe-btn', function(e) {
                e.preventDefault();
                self.handleSubscribe();
            });

            // Email input enter key
            $(document).on('keypress', '.wcl-email-input input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.handleSubscribe();
                }
            });
        },

        handleSubscribe: function() {
            var self = this;
            var $btn = $('.wcl-subscribe-btn');
            var email = '';

            // Get email if not logged in
            if (!wclData.isLoggedIn) {
                email = $('.wcl-email-input input').val().trim();
                if (!email || !self.isValidEmail(email)) {
                    self.showError(wclData.strings.error || 'Please enter a valid email address.');
                    return;
                }
            }

            // Disable button and show loading
            $btn.prop('disabled', true).addClass('loading');
            self.hideError();

            // Send AJAX request
            $.ajax({
                url: wclData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_create_checkout',
                    nonce: wclData.nonce,
                    plan_type: self.selectedPlan,
                    post_id: wclData.postId,
                    email: email
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        // Redirect to Stripe Checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        var message = response.data && response.data.message
                            ? response.data.message
                            : wclData.strings.error;
                        self.showError(message);
                        $btn.prop('disabled', false).removeClass('loading');
                    }
                },
                error: function() {
                    self.showError(wclData.strings.error);
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        showError: function(message) {
            var $paywall = $('.wcl-paywall');
            var $error = $paywall.find('.wcl-error');

            if ($error.length === 0) {
                $error = $('<div class="wcl-error"></div>');
                $paywall.find('.wcl-paywall-description').after($error);
            }

            $error.text(message).show();
        },

        hideError: function() {
            $('.wcl-error').hide();
        },

        checkSuccessMessage: function() {
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
    $(document).ready(function() {
        WCLPaywall.init();
    });

})(jQuery);
