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
    public static function create_user($email, $name = '') {
        // Generate username from email
        $username = self::generate_username($email);

        // Generate random password
        $password = wp_generate_password(12, true);

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

        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . sprintf(__('Welcome to %s', 'wp-content-locker'), $site_name) . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #ffffff;">
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px;">
                    <h1 style="color: #2c3e50; margin: 0; font-size: 24px;">' . sprintf(__('Welcome to %s — Your Subscription Is Active', 'wp-content-locker'), $site_name) . '</h1>
                </div>

                <div style="margin-bottom: 30px;">
                    <p>' . __('Thank you for subscribing! Your payment was successful, and your account is now active.', 'wp-content-locker') . '</p>
                    
                    ' . ($data['new_user'] ? '
                    <div style="background-color: #f9f9f9; border-left: 4px solid #2c3e50; padding: 15px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #2c3e50;">' . __('Your Login Credentials', 'wp-content-locker') . '</h3>
                        <p style="margin: 5px 0;"><strong>' . __('Username:', 'wp-content-locker') . '</strong> ' . $user->user_login . '</p>
                        <p style="margin: 5px 0;"><strong>' . __('Password:', 'wp-content-locker') . '</strong> ' . $data['password'] . '</p>
                        <p style="margin-top: 15px;"><a href="' . esc_url($login_url) . '" style="background-color: #2c3e50; color: #ffffff; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block;">' . __('Log In Now', 'wp-content-locker') . '</a></p>
                    </div>
                    ' : '') . '

                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 4px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #166534; font-size: 18px;">' . __('Receipt & Billing Overview', 'wp-content-locker') . '</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">' . __('Plan:', 'wp-content-locker') . '</td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">' . esc_html($data['plan_name']) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">' . __('Amount:', 'wp-content-locker') . '</td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold;">' . esc_html($data['amount']) . '</td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin: 20px 0; padding: 15px; background-color: #eff6ff; border-radius: 4px;">
                        <p style="margin: 0; color: #1e40af;"><strong>' . __('Newsletter Confirmed', 'wp-content-locker') . '</strong></p>
                        <p style="margin: 5px 0 0; font-size: 14px; color: #1e3a8a;">' . __('You have been successfully subscribed to our daily newsletter.', 'wp-content-locker') . '</p>
                    </div>

                    <p>' . sprintf(__('You can manage your subscription and account details at any time by visiting your <a href="%s" style="color: #2c3e50;">Account Dashboard</a>.', 'wp-content-locker'), esc_url($account_url)) . '</p>
                </div>

                <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
                    <a href="' . esc_url($data['post_url']) . '" style="background-color: #e74c3c; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 18px; display: inline-block;">' . __('Start Reading', 'wp-content-locker') . '</a>
                    <p style="margin-top: 20px; font-size: 12px; color: #999;">' . sprintf(__('&copy; %s %s. All rights reserved.', 'wp-content-locker'), date('Y'), $site_name) . '</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        
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
