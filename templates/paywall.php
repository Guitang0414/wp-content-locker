<?php
/**
 * Paywall template
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

$login_url = wp_login_url(get_permalink($post_id));
?>

<div class="wcl-paywall">
    <div class="wcl-paywall-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
    </div>

    <h3 class="wcl-paywall-title"><?php echo esc_html($title); ?></h3>
    <p class="wcl-paywall-description"><?php echo esc_html($description); ?></p>

    <div class="wcl-pricing">
        <div class="wcl-price-card selected" data-plan="monthly">
            <div class="wcl-price-card-title"><?php _e('Monthly', 'wp-content-locker'); ?></div>
            <div class="wcl-price-card-price">
                $<?php echo esc_html($monthly_price); ?>
                <span>/<?php _e('month', 'wp-content-locker'); ?></span>
            </div>
        </div>

        <div class="wcl-price-card" data-plan="yearly">
            <div class="wcl-price-card-title"><?php _e('Yearly', 'wp-content-locker'); ?></div>
            <div class="wcl-price-card-price">
                $<?php echo esc_html($yearly_price); ?>
                <span>/<?php _e('year', 'wp-content-locker'); ?></span>
            </div>
            <?php if ($savings_percent > 0) : ?>
                <div class="wcl-price-card-savings">
                    <?php printf(__('Save %d%%', 'wp-content-locker'), $savings_percent); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!is_user_logged_in()) : ?>
        <div class="wcl-email-input">
            <input type="email" placeholder="<?php esc_attr_e('Enter your email', 'wp-content-locker'); ?>" required />
        </div>
    <?php endif; ?>

    <button type="button" class="wcl-subscribe-btn">
        <?php echo esc_html($button_text); ?>
    </button>

    <?php if (!is_user_logged_in()) : ?>
        <p class="wcl-login-link">
            <?php _e('Already have an account?', 'wp-content-locker'); ?>
            <a href="<?php echo esc_url($login_url); ?>"><?php _e('Log in', 'wp-content-locker'); ?></a>
        </p>
    <?php endif; ?>
</div>
