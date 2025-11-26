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
            <div class="wcl-modal-brand"><?php echo esc_html($site_name); ?></div>
            <h2 class="wcl-modal-title"><?php echo esc_html($description); ?></h2>
        </div>

        <div class="wcl-modal-body">
            <div class="wcl-plan-cards">
                <div class="wcl-plan-card selected" data-plan="monthly">
                    <div class="wcl-plan-label"><?php _e('Monthly', 'wp-content-locker'); ?></div>
                    <div class="wcl-plan-price">
                        <span class="wcl-price-amount">$<?php echo esc_html($monthly_price); ?></span>
                    </div>
                    <div class="wcl-plan-period"><?php _e('per month', 'wp-content-locker'); ?></div>
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
