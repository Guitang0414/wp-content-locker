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
     */
    public static function get_or_create_user($email, $name = '') {
        if (empty($email)) {
            return new WP_Error('no_email', __('Email is required.', 'wp-content-locker'));
        }

        // Check if user exists
        $user = get_user_by('email', $email);
        if ($user) {
            return $user->ID;
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

        // Send welcome email with login credentials
        self::send_welcome_email($user_id, $password);

        return $user_id;
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
     * Send welcome email to new subscriber
     */
    private static function send_welcome_email($user_id, $password) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $site_name = get_bloginfo('name');

        // Try to find My Account page with [wcl_account] shortcode
        global $wpdb;
        $account_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[wcl_account]%' AND post_status = 'publish' AND post_type = 'page' LIMIT 1");
        $login_url = $account_page_id ? get_permalink($account_page_id) : wp_login_url();

        $subject = sprintf(
            /* translators: %s: site name */
            __('Welcome to %s - Your subscription is active!', 'wp-content-locker'),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: site name, 2: username, 3: password, 4: login URL */
            __(
                "Welcome to %1\$s!\n\n" .
                "Your subscription is now active. You can access all premium content.\n\n" .
                "Your login credentials:\n" .
                "Username: %2\$s\n" .
                "Password: %3\$s\n\n" .
                "Login here: %4\$s\n\n" .
                "We recommend changing your password after logging in.\n\n" .
                "Thank you for subscribing!",
                'wp-content-locker'
            ),
            $site_name,
            $user->user_login,
            $password,
            $login_url
        );

        return wp_mail($user->user_email, $subject, $message);
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
