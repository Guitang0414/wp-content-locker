<?php
/**
 * Paywall template - WSJ/WaPo style
 *
 * Variables available:
 * $title - Paywall title
 * $description - Paywall description
 * $button_text - Subscribe button text
 * $monthly_price - Monthly display price
 * $yearly_price - Yearly display price
 * $post_id - Current post ID
 */

if (!defined('ABSPATH')) {
    exit;
}

// Calculate yearly savings
$monthly_total = floatval($monthly_price) * 12;
$yearly_total = floatval($yearly_price);
$savings_percent = $monthly_total > 0 ? round((($monthly_total - $yearly_total) / $monthly_total) * 100) : 0;

// Get site name for branding
$site_name = get_bloginfo('name');

// Get My Account page URL (fallback to login URL)
$account_page_url = '';
$account_page = get_pages(array(
    'post_status' => 'publish',
    'meta_key' => '_wp_page_template',
    'hierarchical' => 0,
));

// Try to find page with [wcl_account] shortcode
global $wpdb;
$account_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[wcl_account]%' AND post_status = 'publish' AND post_type = 'page' LIMIT 1");
if ($account_page_id) {
    $account_page_url = get_permalink($account_page_id);
}

$login_url = !empty($account_page_url) ? $account_page_url : wp_login_url(get_permalink($post_id));
?>

<!-- Step 1: Simple Paywall CTA -->
<div class="wcl-paywall">
    <div class="wcl-paywall-brand">
        <?php echo esc_html($site_name); ?>
    </div>

    <h3 class="wcl-paywall-title"><?php echo esc_html($title); ?></h3>
    <p class="wcl-paywall-description"><?php echo esc_html($description); ?></p>

    <button type="button" class="wcl-subscribe-btn wcl-open-modal-btn">
        <?php echo esc_html($button_text); ?>
    </button>

    <p class="wcl-login-link">
        <?php _e('Already a subscriber?', 'wp-content-locker'); ?>
        <a href="<?php echo esc_url($login_url); ?>"><?php _e('Sign In', 'wp-content-locker'); ?></a>
    </p>
</div>

<!-- Step 2: Subscription Modal -->
<div class="wcl-modal-overlay" style="display: none;">
    <div class="wcl-modal">
        <button type="button" class="wcl-modal-close">&times;</button>

        <?php if (is_user_logged_in()) : ?>
            <?php $current_user = wp_get_current_user(); ?>
            <div class="wcl-modal-user-bar">
                <?php printf(__("You're logged in as %s.", 'wp-content-locker'), '<strong>' . esc_html($current_user->display_name) . '</strong>'); ?>
                <a href="<?php echo esc_url(wp_logout_url(get_permalink($post_id))); ?>"><?php _e('Sign out', 'wp-content-locker'); ?></a>
            </div>
        <?php endif; ?>

        <div class="wcl-modal-header">
            <div class="wcl-offer-badge">LIMITED TIME OFFER</div>
            <h2 class="wcl-modal-title">Independent News for Independent Thinkers</h2>
            <div class="wcl-offer-details">
                <p><strong>Special Offer:</strong></p>
                <p>✨ Only $0.50/week for the first 3 months</p>
                <p>Then $2/week. Cancel anytime.</p>
            </div>
        </div>

        <div class="wcl-modal-body">
            <div class="wcl-features-section">
                <h3>What You Get:</h3>
                <ul class="wcl-features-list">
                    <li>✔ Unlimited access to all articles</li>
                    <li>✔ Independent, agenda-free reporting</li>
                    <li>✔ Fearless investigations on CCP influence</li>
                    <li>✔ 24/7 news updates</li>
                    <li>✔ Weekly Insider newsletter</li>
                </ul>
            </div>

<?php
// Get configurable values
$monthly_original = get_option('wcl_monthly_original_price', '$2');
$monthly_discounted = get_option('wcl_monthly_discounted_price', '50¢');
$monthly_desc = get_option('wcl_monthly_description', 'every week for first 3 months,<br>then $8.99 per month');
?>
            <div class="wcl-plan-cards">
                <div class="wcl-plan-card selected" data-plan="monthly">
                    <div class="wcl-plan-label"><?php _e('Monthly', 'wp-content-locker'); ?></div>
                    <div class="wcl-plan-price">
                        <div class="wcl-plan-strikethrough"><?php echo esc_html($monthly_original); ?></div>
                        <div class="wcl-plan-price-large"><?php echo esc_html($monthly_discounted); ?></div>
                    </div>
                    <div class="wcl-plan-description">
                        <?php echo wp_kses_post($monthly_desc); ?>
                    </div>
                </div>

                <div class="wcl-plan-card" data-plan="yearly">
                    <div class="wcl-plan-label"><?php _e('Yearly', 'wp-content-locker'); ?></div>
                    <div class="wcl-plan-price">
                        <span class="wcl-price-amount">$<?php echo esc_html($yearly_price); ?></span>
                    </div>
                    <div class="wcl-plan-period"><?php _e('per year', 'wp-content-locker'); ?></div>
                    <?php if ($savings_percent > 0) : ?>
                        <div class="wcl-plan-savings"><?php printf(__('Save %d%%', 'wp-content-locker'), $savings_percent); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <p class="wcl-cancel-note"><?php _e('Cancel anytime.', 'wp-content-locker'); ?></p>

            <?php if (!is_user_logged_in()) : ?>
                <div class="wcl-email-input">
                    <input type="email" id="wcl-checkout-email" placeholder="<?php esc_attr_e('Enter your email', 'wp-content-locker'); ?>" required />
                </div>
            <?php endif; ?>

            <button type="button" class="wcl-checkout-btn">
                <?php echo esc_html($button_text); ?>
            </button>

            <p class="wcl-modal-error" style="display: none;"></p>
        </div>
    </div>
</div>

<!-- Inline fallback script for modal functionality -->
<script>
(function() {
    // Wait for jQuery
    function initWCLModal() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initWCLModal, 100);
            return;
        }

        var $ = jQuery;
        var selectedPlan = 'monthly';

        // Move modal to body to avoid being trapped in popups/overlays
        var $modal = $('.wcl-modal-overlay').first();
        if ($modal.length && !$modal.parent().is('body')) {
            $modal.appendTo('body');
        }

        // Open modal
        $(document).off('click.wcl-modal').on('click.wcl-modal', '.wcl-open-modal-btn', function(e) {
            e.preventDefault();
            $('.wcl-modal-overlay').fadeIn(200);
            $('body').css('overflow', 'hidden');
        });

        // Close modal
        $(document).off('click.wcl-close').on('click.wcl-close', '.wcl-modal-close', function(e) {
            e.preventDefault();
            $('.wcl-modal-overlay').fadeOut(200);
            $('body').css('overflow', '');
        });

        // Close on overlay click
        $(document).off('click.wcl-overlay').on('click.wcl-overlay', '.wcl-modal-overlay', function(e) {
            if ($(e.target).hasClass('wcl-modal-overlay')) {
                $('.wcl-modal-overlay').fadeOut(200);
                $('body').css('overflow', '');
            }
        });

        // Close on ESC
        $(document).off('keydown.wcl').on('keydown.wcl', function(e) {
            if (e.key === 'Escape') {
                $('.wcl-modal-overlay').fadeOut(200);
                $('body').css('overflow', '');
            }
        });

        // Plan selection
        $(document).off('click.wcl-plan').on('click.wcl-plan', '.wcl-plan-card', function() {
            $('.wcl-plan-card').removeClass('selected');
            $(this).addClass('selected');
            selectedPlan = $(this).data('plan');
        });

        // Checkout button
        $(document).off('click.wcl-checkout').on('click.wcl-checkout', '.wcl-checkout-btn', function(e) {
            e.preventDefault();
            console.log('WCL: Checkout button clicked');
            var $btn = $(this);
            var email = '';

            // Get wclData from global or use defaults
            var wclData = window.wclData || {
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('wcl_checkout_nonce')); ?>',
                postId: <?php echo intval($post_id); ?>,
                isLoggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
                strings: {
                    invalidEmail: '<?php echo esc_js(__('Please enter a valid email address.', 'wp-content-locker')); ?>',
                    error: '<?php echo esc_js(__('An error occurred. Please try again.', 'wp-content-locker')); ?>'
                }
            };

            // Convert string to boolean if needed
            var isLoggedIn = wclData.isLoggedIn === true || wclData.isLoggedIn === 'true';
            console.log('WCL: isLoggedIn =', isLoggedIn, 'wclData.isLoggedIn =', wclData.isLoggedIn);

            if (!isLoggedIn) {
                // Find the email input that has a value (in case of multiple instances)
                var $emailInputs = $('input#wcl-checkout-email, input.wcl-checkout-email');
                $emailInputs.each(function() {
                    var val = $(this).val();
                    if (val && val.trim() !== '') {
                        email = val.trim();
                    }
                });
                console.log('WCL: email from input =', email);
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    console.log('WCL: Invalid email, showing error');
                    $('.wcl-modal-error').text(wclData.strings.invalidEmail).show();
                    return;
                }
            }

            console.log('WCL: Sending AJAX request with email =', email, 'plan =', selectedPlan);
            $btn.prop('disabled', true).addClass('loading');
            $('.wcl-modal-error').hide();

            $.ajax({
                url: wclData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcl_create_checkout',
                    nonce: wclData.nonce,
                    plan_type: selectedPlan,
                    post_id: wclData.postId,
                    email: email
                },
                success: function(response) {
                    console.log('WCL: AJAX response =', response);
                    if (response.success && response.data.checkout_url) {
                        console.log('WCL: Redirecting to', response.data.checkout_url);
                        window.location.href = response.data.checkout_url;
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : wclData.strings.error;
                        console.log('WCL: Error message =', msg);
                        $('.wcl-modal-error').text(msg).show();
                        $btn.prop('disabled', false).removeClass('loading');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('WCL: AJAX error =', status, error);
                    $('.wcl-modal-error').text(wclData.strings.error).show();
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWCLModal);
    } else {
        initWCLModal();
    }
})();
</script>
