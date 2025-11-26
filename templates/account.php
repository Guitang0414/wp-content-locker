<?php
/**
 * Account page template
 *
 * This template displays the user account page with login form (if not logged in)
 * or subscription management (if logged in).
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$subscription_data = $is_logged_in ? WCL_Account::get_subscription_display_data(get_current_user_id()) : null;

// Check for messages
$login_error = isset($_GET['wcl_login_error']) ? sanitize_text_field($_GET['wcl_login_error']) : '';
$subscription_canceled = isset($_GET['wcl_canceled']) && $_GET['wcl_canceled'] === '1';
?>

<div class="wcl-account-wrapper">
    <?php if (!$is_logged_in) : ?>
        <!-- Login Form -->
        <div class="wcl-account-section wcl-login-section">
            <h2 class="wcl-account-title"><?php _e('Login', 'wp-content-locker'); ?></h2>

            <div class="wcl-login-error" style="display: none;"></div>

            <form class="wcl-login-form" method="post">
                <div class="wcl-form-field">
                    <label for="wcl_username"><?php _e('Username or Email', 'wp-content-locker'); ?></label>
                    <input type="text" id="wcl_username" name="username" required />
                </div>

                <div class="wcl-form-field">
                    <label for="wcl_password"><?php _e('Password', 'wp-content-locker'); ?></label>
                    <input type="password" id="wcl_password" name="password" required />
                </div>

                <div class="wcl-form-field wcl-remember-field">
                    <label>
                        <input type="checkbox" name="remember" value="1" />
                        <?php _e('Remember me', 'wp-content-locker'); ?>
                    </label>
                </div>

                <button type="submit" class="wcl-btn wcl-btn-primary wcl-login-btn">
                    <?php _e('Login', 'wp-content-locker'); ?>
                </button>

                <p class="wcl-forgot-password">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                        <?php _e('Forgot your password?', 'wp-content-locker'); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else : ?>
        <!-- Account Dashboard -->
        <div class="wcl-account-section wcl-dashboard-section">
            <h2 class="wcl-account-title"><?php _e('My Account', 'wp-content-locker'); ?></h2>

            <!-- User Info -->
            <div class="wcl-user-info">
                <div class="wcl-user-avatar">
                    <?php echo get_avatar($current_user->ID, 64); ?>
                </div>
                <div class="wcl-user-details">
                    <h3 class="wcl-user-name"><?php echo esc_html($current_user->display_name); ?></h3>
                    <p class="wcl-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                </div>
            </div>

            <!-- Subscription Info -->
            <div class="wcl-subscription-section">
                <h3 class="wcl-section-title"><?php _e('Subscription', 'wp-content-locker'); ?></h3>

                <?php if ($subscription_canceled) : ?>
                    <div class="wcl-notice wcl-notice-warning">
                        <?php _e('Your subscription has been canceled. You will have access until the end of your billing period.', 'wp-content-locker'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($subscription_data) : ?>
                    <div class="wcl-subscription-card">
                        <div class="wcl-subscription-header">
                            <span class="wcl-subscription-plan"><?php echo esc_html($subscription_data['plan_label']); ?></span>
                            <span class="wcl-subscription-status" style="background-color: <?php echo esc_attr($subscription_data['status_color']); ?>">
                                <?php echo esc_html($subscription_data['status_label']); ?>
                            </span>
                        </div>

                        <div class="wcl-subscription-details">
                            <?php if ($subscription_data['status'] === 'active') : ?>
                                <p>
                                    <strong><?php _e('Next billing date:', 'wp-content-locker'); ?></strong>
                                    <?php echo esc_html($subscription_data['current_period_end_formatted']); ?>
                                </p>
                            <?php elseif ($subscription_data['status'] === 'canceling') : ?>
                                <p>
                                    <strong><?php _e('Access until:', 'wp-content-locker'); ?></strong>
                                    <?php echo esc_html($subscription_data['current_period_end_formatted']); ?>
                                </p>
                                <p class="wcl-cancel-note">
                                    <?php _e('Your subscription will not renew after this date.', 'wp-content-locker'); ?>
                                </p>
                            <?php elseif ($subscription_data['status'] === 'canceled') : ?>
                                <p class="wcl-expired-note">
                                    <?php _e('Your subscription has expired.', 'wp-content-locker'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($subscription_data['can_cancel']) : ?>
                            <div class="wcl-subscription-actions">
                                <button type="button" class="wcl-btn wcl-btn-danger wcl-cancel-subscription-btn">
                                    <?php _e('Cancel Subscription', 'wp-content-locker'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else : ?>
                    <!-- No Subscription -->
                    <div class="wcl-no-subscription">
                        <p><?php _e('You don\'t have an active subscription.', 'wp-content-locker'); ?></p>
                        <p><?php _e('Subscribe to access all premium content.', 'wp-content-locker'); ?></p>
                        <!-- You can add a subscribe button here if needed -->
                    </div>
                <?php endif; ?>
            </div>

            <!-- Logout -->
            <div class="wcl-logout-section">
                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="wcl-btn wcl-btn-secondary">
                    <?php _e('Logout', 'wp-content-locker'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
