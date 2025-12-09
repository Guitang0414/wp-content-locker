/**
 * WP Content Locker Public Scripts - WSJ/WaPo Style
 */

(function ($) {
    'use strict';

    var WCLPaywall = {
        selectedPlan: 'monthly',

        init: function () {
            this.bindEvents();
            this.checkSuccessMessage();
        },

        bindEvents: function () {
            var self = this;

            // Open modal button (Step 1 -> Step 2)
            $(document).on('click', '.wcl-open-modal-btn', function (e) {
                e.preventDefault();
                self.openModal();
            });

            // Close modal
            $(document).on('click', '.wcl-modal-close', function (e) {
                e.preventDefault();
                self.closeModal();
            });

            // Close modal on overlay click
            $(document).on('click', '.wcl-modal-overlay', function (e) {
                if ($(e.target).hasClass('wcl-modal-overlay')) {
                    self.closeModal();
                }
            });

            // Close modal on ESC key
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });

            // Plan selection in modal
            $(document).on('click', '.wcl-plan-card', function () {
                var $card = $(this);
                var plan = $card.data('plan');

                // Update selection
                $('.wcl-plan-card').removeClass('selected');
                $card.addClass('selected');
                self.selectedPlan = plan;
            });

            // Checkout button in modal
            $(document).on('click', '.wcl-checkout-btn', function (e) {
                e.preventDefault();
                self.handleCheckout();
            });

            // Email input enter key
            $(document).on('keypress', '#wcl-checkout-email', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.handleCheckout();
                }
            });
        },

        openModal: function () {
            $('.wcl-modal-overlay').fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        closeModal: function () {
            $('.wcl-modal-overlay').fadeOut(200);
            $('body').css('overflow', '');
            this.hideError();
        },

        handleCheckout: function () {
            var self = this;
            var $btn = $('.wcl-checkout-btn');
            var email = '';

            // Get email if not logged in
            if (!wclData.isLoggedIn) {
                email = $('#wcl-checkout-email').val().trim();
                if (!email || !self.isValidEmail(email)) {
                    self.showError(wclData.strings.invalidEmail || 'Please enter a valid email address.');
                    return;
                }
            }

            // Disable button and show loading
            $btn.prop('disabled', true).addClass('loading');
            self.hideError();

            // Check URL for test mode override (client-side fallback for cached pages)
            var urlParams = new URLSearchParams(window.location.search);
            var isTestMode = wclData.isTestMode;
            if (urlParams.get('wcl_test_mode') === 'wcl_test_secret' || (urlParams.get('wcl_test_mode') === '1' && wclData.isAdmin)) {
                isTestMode = true;
            }

            // Send AJAX request
            $.ajax({
                url: wclData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_create_checkout',
                    nonce: wclData.nonce,
                    plan_type: self.selectedPlan,
                    post_id: wclData.postId,
                    email: email,
                    test_mode: isTestMode ? 1 : 0
                },
                success: function (response) {
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
                error: function () {
                    self.showError(wclData.strings.error);
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        isValidEmail: function (email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        showError: function (message) {
            var $error = $('.wcl-modal-error');
            if ($error.length) {
                $error.text(message).show();
            }
        },

        hideError: function () {
            $('.wcl-modal-error').hide();
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
