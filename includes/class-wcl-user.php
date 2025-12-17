<?php
/**
 * User management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_User {

    /**
     * Get or create a WordPress user by email
     * Returns array with user_id, password (if new), and new_user flag
     */
    public static function get_or_create_user($email, $name = '') {
        if (empty($email)) {
            return new WP_Error('no_email', __('Email is required.', 'wp-content-locker'));
        }

        // Check if user exists
        $user = get_user_by('email', $email);
        if ($user) {
            return array(
                'user_id' => $user->ID,
                'password' => null,
                'new_user' => false,
                'user_login' => $user->user_login
            );
        }

        // Create new user
        return self::create_user($email, $name);
    }

    /**
     * Create a new WordPress user
     */
    public static function create_user($email, $name = '', $password = null) {
        // Generate username from email
        $username = self::generate_username($email);

        // Generate random password if not provided
        if (empty($password)) {
            $password = wp_generate_password(12, true);
        }

        // Parse name
        $first_name = '';
        $last_name = '';
        if (!empty($name)) {
            $name_parts = explode(' ', $name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        }

        // Create user
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        return array(
            'user_id' => $user_id,
            'password' => $password,
            'new_user' => true,
            'user_login' => $username
        );
    }

    /**
     * Generate unique username from email
     */
    private static function generate_username($email) {
        $base_username = sanitize_user(strstr($email, '@', true), true);

        // If empty after sanitization, use a generic prefix
        if (empty($base_username)) {
            $base_username = 'subscriber';
        }

        $username = $base_username;
        $suffix = 1;

        // Ensure uniqueness
        while (username_exists($username)) {
            $username = $base_username . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Send enhanced subscription email
     */
    public static function send_subscription_email($data) {
        $user_id = $data['user_id'];
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        
        // Try to find My Account page
        global $wpdb;
        $account_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[wcl_account]%' AND post_status = 'publish' AND post_type = 'page' LIMIT 1");
        if ($account_page_id) {
            $login_url = get_permalink($account_page_id);
        }

        $subject = sprintf(__('Welcome to %s - Your Subscription Is Active', 'wp-content-locker'), $site_name);
        
        // Prepare variables
        $username = $user->user_login;
        $password = isset($data['password']) ? $data['password'] : '';
        $is_new_user = isset($data['new_user']) && $data['new_user'];
        $plan_name = isset($data['plan_name']) ? $data['plan_name'] : 'Premium Subscription';
        $amount = isset($data['amount']) ? $data['amount'] : '';
        $post_url = isset($data['post_url']) ? $data['post_url'] : home_url();
        $account_url = $login_url;

    $plan_type = isset($data['plan_type']) ? $data['plan_type'] : 'monthly';
    $amount_subtotal = isset($data['subtotal']) ? $data['subtotal'] : $data['amount'];
    $amount_tax = isset($data['tax']) ? $data['tax'] : '';
    
    // Generate renewal text logic
    $renewal_text = '';
    if ($plan_type === 'yearly') {
        $renewal_text = sprintf(__('Your payment method will be automatically charged <strong>%s every year for the first year</strong>.', 'wp-content-locker'), $data['amount']);
        $renewal_text .= '<br>' . __('Your payment method will then be automatically charged the standard rate of <strong>$159.00 every year</strong> thereafter.', 'wp-content-locker');
    } else {
        $renewal_text = sprintf(__('Your payment method will be automatically charged <strong>%s every month for the first 3 months</strong>.', 'wp-content-locker'), $data['amount']);
        $renewal_text .= '<br>' . __('Your payment method will then be automatically charged the standard rate of <strong>$12.00 every month</strong> thereafter.', 'wp-content-locker');
    }

    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . sprintf(__('Welcome to %s', 'wp-content-locker'), $site_name) . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #ffffff;">
            
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px;">
                <h1 style="font-family: Georgia, \'Times New Roman\', serif; color: #000; margin: 0; font-size: 28px; text-transform: uppercase; letter-spacing: -0.5px;">' . $site_name . '</h1>
                <div style="margin-top: 10px; font-family: Georgia, serif; font-size: 18px; color: #333;">' . __('Welcome — Your Subscription Is Active', 'wp-content-locker') . '</div>
            </div>

            <div style="margin-bottom: 30px;">
                <p style="font-size: 16px;">' . __('Thank you for subscribing! Your payment was successful. Below you will find your login credentials and order details.', 'wp-content-locker') . '</p>
                
                ' . ($data['new_user'] ? '
                <div style="background-color: #f9f9f9; border-left: 4px solid #000; padding: 20px; margin: 25px 0;">
                    <h3 style="margin-top: 0; color: #000; font-family: Arial, sans-serif;">' . __('Your Login Credentials', 'wp-content-locker') . '</h3>
                    <p style="margin: 8px 0;"><strong>' . __('Username:', 'wp-content-locker') . '</strong> ' . $user->user_login . '</p>
                    <p style="margin: 8px 0;"><strong>' . __('Password:', 'wp-content-locker') . '</strong> ' . $data['password'] . '</p>
                    <p style="margin-top: 20px;"><a href="' . esc_url($login_url) . '" style="background-color: #000; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 3px; font-weight: bold; display: inline-block;">' . __('Log In Now', 'wp-content-locker') . '</a></p>
                </div>
                ' : '') . '

                <div style="margin: 30px 0; border: 1px solid #eee; border-radius: 4px; padding: 15px;">
                    <div style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; font-weight: bold; font-size: 14px; color: #000; letter-spacing: 0.5px;">' . __('ORDER DETAILS', 'wp-content-locker') . '</div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Plan', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right; color: #333; font-size: 16px; font-weight: bold;">' . esc_html($data['plan_name']) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Account Email', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right; color: #333; font-size: 16px;">' . esc_html($user->user_email) . '</td>
                        </tr>
                        ' . (!empty($amount_tax) ? '
                        <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Wait Amount', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right; color: #333; font-size: 16px;">' . esc_html($amount_subtotal) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Tax', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right; color: #333; font-size: 16px;">' . esc_html($amount_tax) . '</td>
                        </tr>' : '') . '
                         <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Total Amount', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right; color: #333; font-size: 16px; font-weight: bold;">' . esc_html($data['amount']) . '</td>
                        </tr>
                        ' . (!empty($data['invoice_pdf']) ? '
                        <tr>
                            <td style="padding: 8px 0; color: #333; font-weight: bold; font-size: 12px;">' . __('Receipt', 'wp-content-locker') . '</td>
                            <td style="padding: 8px 0; text-align: right;">
                                <a href="' . esc_url($data['invoice_pdf']) . '" style="color: #000; text-decoration: underline;">' . __('Download PDF', 'wp-content-locker') . '</a>
                            </td>
                        </tr>
                        ' : '') . '
                    </table>
                </div>

                <div style="background-color: #f2f2f2; border: 2px solid #000; padding: 25px; margin: 30px 0;">
                    <h3 style="margin-top: 0; color: #000; font-size: 18px; text-align: center; margin-bottom: 15px;">' . __('AUTOMATIC RENEWAL REMINDER', 'wp-content-locker') . '</h3>
                    <ul style="padding-left: 20px; margin-bottom: 0; color: #000; line-height: 1.5;">
                        <li style="margin-bottom: 10px;">' . $renewal_text . '</li>
                        <li style="margin-bottom: 10px;">' . __('Sales tax may apply. Prices subject to change.', 'wp-content-locker') . '</li>
                        <li style="margin-bottom: 0;">' . sprintf(__('You can cancel anytime in your <a href="%s" style="color: #309FFE; text-decoration: underline;">account settings</a>.', 'wp-content-locker'), esc_url($account_url)) . '</li>
                    </ul>
                </div>

            </div>

            <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
                <a href="' . esc_url($data['post_url']) . '" style="background-color: #d93900; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 18px; display: inline-block;">' . __('Start Reading', 'wp-content-locker') . '</a>
                    
                    <div style="margin-top: 30px; font-size: 11px; color: #999; line-height: 1.6;">
                        ' . sprintf(__('This email was sent to %s.', 'wp-content-locker'), esc_html($user->user_email)) . '<br><br>
                        <a href="' . esc_url($account_url) . '" style="color: #999; text-decoration: underline;">' . __('Account Login', 'wp-content-locker') . '</a> | 
                        <a href="' . home_url('/feedback-form/') . '" style="color: #999; text-decoration: underline;">' . __('Help Center', 'wp-content-locker') . '</a> | 
                        <a href="' . home_url('/terms-of-service/') . '" style="color: #999; text-decoration: underline;">' . __('Terms of Service', 'wp-content-locker') . '</a> | 
                        <a href="' . home_url('/data-protection-privacy-policy/') . '" style="color: #999; text-decoration: underline;">' . __('Privacy Policy', 'wp-content-locker') . '</a>
                        <br><br>
                        ' . sprintf(__('&copy; %s %s. All rights reserved.', 'wp-content-locker'), date('Y'), $site_name) . '
                    </div>
                </div>
            </div>
        </body>
        </html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = 'From: ' . $site_name . ' <' . get_option('admin_email') . '>';
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Get user subscription status
     */
    public static function get_subscription_status($user_id) {
        return get_user_meta($user_id, '_wcl_subscription_status', true);
    }

    /**
     * Check if user is a subscriber
     */
    public static function is_subscriber($user_id) {
        $status = self::get_subscription_status($user_id);
        return in_array($status, array('active', 'canceling'));
    }

    /**
     * Get user's Stripe customer ID
     */
    public static function get_stripe_customer_id($user_id) {
        return get_user_meta($user_id, '_wcl_stripe_customer_id', true);
    }

    /**
     * Auto-login user after subscription
     */
    public static function auto_login($user_id) {
        if (is_user_logged_in()) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);
    }
}
