<?php
/**
 * Account page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
$subscription_data = $is_logged_in ? WCL_Account::get_subscription_display_data(get_current_user_id()) : null;
?>

<div class="wcl-account-wrapper">
    <?php if (!$is_logged_in) : ?>
        <div class="wcl-auth-section">
            <!-- Login Form -->
            <div id="wcl-login-wrapper">
                <h2 class="wcl-account-title"><?php _e('Login', 'wp-content-locker'); ?></h2>
                <div class="wcl-message error" style="display: none;"></div>
                
                <form class="wcl-login-form" method="post">
                    <div class="wcl-form-field">
                        <label for="wcl_username"><?php _e('Username or Email', 'wp-content-locker'); ?></label>
                        <input type="text" id="wcl_username" name="username" required />
                    </div>
                    <div class="wcl-form-field">
                        <label for="wcl_password"><?php _e('Password', 'wp-content-locker'); ?></label>
                        <input type="password" id="wcl_password" name="password" required />
                    </div>
                    <div class="wcl-form-field">
                        <label>
                            <input type="checkbox" name="remember" value="1" />
                            <?php _e('Remember me', 'wp-content-locker'); ?>
                        </label>
                    </div>
                    <button type="submit" class="wcl-btn wcl-btn-primary wcl-login-btn">
                        <?php _e('Login', 'wp-content-locker'); ?>
                    </button>
                    <div class="wcl-auth-links">
                        <p>
                            <a href="#" class="wcl-toggle-auth" data-target="lost-password"><?php _e('Forgot your password?', 'wp-content-locker'); ?></a>
                        </p>
                        <p>
                            <?php _e('Don\'t have an account?', 'wp-content-locker'); ?> 
                            <a href="#" class="wcl-toggle-auth" data-target="register"><?php _e('Register Now', 'wp-content-locker'); ?></a>
                        </p>
                    </div>
                </form>
            </div>

            <!-- Register Form -->
            <div id="wcl-register-wrapper" style="display: none;">
                <h2 class="wcl-account-title"><?php _e('Create Account', 'wp-content-locker'); ?></h2>
                <div class="wcl-message error" style="display: none;"></div>
                
                <form class="wcl-register-form" method="post">
                    <div class="wcl-form-field">
                        <label for="wcl_reg_email"><?php _e('Email Address', 'wp-content-locker'); ?></label>
                        <input type="email" id="wcl_reg_email" name="email" required />
                    </div>
                    <div class="wcl-form-field">
                        <label for="wcl_reg_name"><?php _e('Full Name', 'wp-content-locker'); ?></label>
                        <input type="text" id="wcl_reg_name" name="name" required />
                    </div>
                    <div class="wcl-form-field">
                        <label for="wcl_reg_password"><?php _e('Password', 'wp-content-locker'); ?></label>
                        <input type="password" id="wcl_reg_password" name="password" required minlength="8" />
                    </div>
                    <button type="submit" class="wcl-btn wcl-btn-primary wcl-register-btn">
                        <?php _e('Register', 'wp-content-locker'); ?>
                    </button>
                    <div class="wcl-auth-links">
                        <p>
                            <?php _e('Already have an account?', 'wp-content-locker'); ?> 
                            <a href="#" class="wcl-toggle-auth" data-target="login"><?php _e('Login Here', 'wp-content-locker'); ?></a>
                        </p>
                    </div>
                </form>
                </form>
            </div>

            <!-- Lost Password Form -->
            <div id="wcl-lost-password-wrapper" style="display: none;">
                <h2 class="wcl-account-title"><?php _e('Reset Password', 'wp-content-locker'); ?></h2>
                <div class="wcl-message error" style="display: none;"></div>
                <p class="wcl-auth-desc"><?php _e('Enter your email address and we\'ll send you a link to reset your password.', 'wp-content-locker'); ?></p>
                
                <form class="wcl-lost-password-form" method="post">
                    <div class="wcl-form-field">
                        <label for="wcl_lost_email"><?php _e('Username or Email', 'wp-content-locker'); ?></label>
                        <input type="text" id="wcl_lost_email" name="user_login" required />
                    </div>
                    <button type="submit" class="wcl-btn wcl-btn-primary wcl-lost-password-btn">
                        <?php _e('Get New Password', 'wp-content-locker'); ?>
                    </button>
                    <div class="wcl-auth-links">
                        <p>
                            <a href="#" class="wcl-toggle-auth" data-target="login"><?php _e('Back to Login', 'wp-content-locker'); ?></a>
                        </p>
                    </div>
                </form>
            </div>
        </div>

    <?php else : ?>
        <!-- Dashboard Layout -->
        <div class="wcl-dashboard-container">
            <!-- Sidebar -->
            <div class="wcl-sidebar">
                <div class="wcl-user-brief">
                    <h3 class="wcl-user-name"><?php echo esc_html($current_user->display_name); ?></h3>
                    <p class="wcl-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                </div>
                
                <ul class="wcl-nav-menu">
                    <li class="wcl-nav-item active" data-tab="info"><?php _e('My Info', 'wp-content-locker'); ?></li>
                    <li class="wcl-nav-item" data-tab="password"><?php _e('Password', 'wp-content-locker'); ?></li>
                    <li class="wcl-nav-item" data-tab="subscription"><?php _e('Subscription', 'wp-content-locker'); ?></li>
                    <li class="wcl-nav-item" data-tab="billing"><?php _e('Billing History', 'wp-content-locker'); ?></li>
                    <li class="wcl-nav-item" data-tab="newsletters"><?php _e('Newsletters', 'wp-content-locker'); ?></li>
                </ul>

                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="wcl-logout-btn">
                    <?php _e('Log Out', 'wp-content-locker'); ?>
                </a>
            </div>

            <!-- Main Content -->
            <div class="wcl-main-content">
                <!-- My Info Tab -->
                <div id="wcl-tab-info" class="wcl-tab-content active">
                    <h2 class="wcl-section-title"><?php _e('Account Information', 'wp-content-locker'); ?></h2>
                    <div class="wcl-message" style="display: none;"></div>
                    
                    <form class="wcl-dashboard-form" id="wcl-profile-form">
                        <div class="wcl-form-field">
                            <label><?php _e('First Name', 'wp-content-locker'); ?></label>
                            <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" />
                        </div>
                        <div class="wcl-form-field">
                            <label><?php _e('Last Name', 'wp-content-locker'); ?></label>
                            <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" />
                        </div>
                        <div class="wcl-form-field">
                            <label><?php _e('Email Address', 'wp-content-locker'); ?></label>
                            <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" disabled style="background: #f5f5f5;" />
                            <small style="color: #888;"><?php _e('Contact support to change email.', 'wp-content-locker'); ?></small>
                        </div>
                        <button type="submit" class="wcl-btn wcl-btn-save"><?php _e('Save Changes', 'wp-content-locker'); ?></button>
                    </form>
                </div>

                <!-- Password Tab -->
                <div id="wcl-tab-password" class="wcl-tab-content">
                    <h2 class="wcl-section-title"><?php _e('Change Password', 'wp-content-locker'); ?></h2>
                    <div class="wcl-message" style="display: none;"></div>

                    <form class="wcl-dashboard-form" id="wcl-password-form">
                        <div class="wcl-form-field">
                            <label><?php _e('Current Password', 'wp-content-locker'); ?></label>
                            <input type="password" name="current_password" required />
                        </div>
                        <div class="wcl-form-field">
                            <label><?php _e('New Password', 'wp-content-locker'); ?></label>
                            <input type="password" name="new_password" required />
                        </div>
                        <div class="wcl-form-field">
                            <label><?php _e('Confirm New Password', 'wp-content-locker'); ?></label>
                            <input type="password" name="confirm_password" required />
                        </div>
                        <button type="submit" class="wcl-btn wcl-btn-save"><?php _e('Update Password', 'wp-content-locker'); ?></button>
                    </form>
                </div>

                <!-- Subscription Tab -->
                <div id="wcl-tab-subscription" class="wcl-tab-content">
                    <h2 class="wcl-section-title"><?php _e('My Subscription', 'wp-content-locker'); ?></h2>
                    
                    <?php if ($subscription_data) : ?>
                        <div class="wcl-subscription-card">
                            <div class="wcl-sub-header">
                                <span class="wcl-plan-name"><?php echo esc_html($subscription_data['plan_label']); ?></span>
                                <span class="wcl-status-badge" style="background-color: <?php echo esc_attr($subscription_data['status_color']); ?>">
                                    <?php echo esc_html($subscription_data['status_label']); ?>
                                </span>
                            </div>
                            
                            <div class="wcl-sub-details">
                                <p>
                                    <strong><?php _e('Next Billing Date', 'wp-content-locker'); ?></strong>
                                    <span><?php echo esc_html($subscription_data['current_period_end_formatted']); ?></span>
                                </p>
                                <p>
                                    <strong><?php _e('Payment Method', 'wp-content-locker'); ?></strong>
                                    <span><?php echo esc_html($subscription_data['payment_method']); ?></span>
                                </p>
                            </div>

                            <?php if ($subscription_data['can_cancel']) : ?>
                                <button type="button" class="wcl-btn wcl-cancel-btn wcl-cancel-subscription-btn">
                                    <?php _e('Cancel Subscription', 'wp-content-locker'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <p><?php _e('You do not have an active subscription.', 'wp-content-locker'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Billing History Tab -->
                <div id="wcl-tab-billing" class="wcl-tab-content">
                    <h2 class="wcl-section-title"><?php _e('Billing History', 'wp-content-locker'); ?></h2>
                    
                    <?php if ($subscription_data && !empty($subscription_data['invoices'])) : ?>
                        <table class="wcl-billing-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'wp-content-locker'); ?></th>
                                    <th><?php _e('Amount', 'wp-content-locker'); ?></th>
                                    <th><?php _e('Status', 'wp-content-locker'); ?></th>
                                    <th><?php _e('Invoice', 'wp-content-locker'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscription_data['invoices'] as $invoice) : ?>
                                    <tr>
                                        <td><?php echo esc_html($invoice['date']); ?></td>
                                        <td><?php echo esc_html($invoice['amount']); ?></td>
                                        <td><?php echo esc_html($invoice['status']); ?></td>
                                        <td>
                                            <?php if ($invoice['pdf']) : ?>
                                                <a href="<?php echo esc_url($invoice['pdf']); ?>" target="_blank" class="wcl-pdf-link">Download PDF</a>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('No billing history found.', 'wp-content-locker'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Newsletters Tab -->
                <div id="wcl-tab-newsletters" class="wcl-tab-content">
                    <h2 class="wcl-section-title"><?php _e('Newsletters', 'wp-content-locker'); ?></h2>
                    <div style="padding: 20px; background: #f9f9f9; border-radius: 5px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" checked disabled style="margin-right: 10px;">
                            <span>
                                <strong><?php _e('Daily Briefing', 'wp-content-locker'); ?></strong><br>
                                <span style="color: #666; font-size: 14px;"><?php _e('Get the latest news delivered to your inbox every morning.', 'wp-content-locker'); ?></span>
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>
</div>


