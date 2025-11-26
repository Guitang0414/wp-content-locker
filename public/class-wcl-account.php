<?php
/**
 * Account page functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Account {

    /**
     * Constructor - register shortcode and AJAX handlers
     */
    public function __construct() {
        add_shortcode('wcl_account', array($this, 'render_account_page'));

        // AJAX handlers
        add_action('wp_ajax_wcl_cancel_subscription', array($this, 'ajax_cancel_subscription'));
        add_action('wp_ajax_wcl_login', array($this, 'ajax_login'));
        add_action('wp_ajax_nopriv_wcl_login', array($this, 'ajax_login'));
    }

    /**
     * Enqueue account page assets
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'wcl-account',
            WCL_PLUGIN_URL . 'public/css/account.css',
            array(),
            WCL_VERSION
        );

        wp_enqueue_script(
            'wcl-account',
            WCL_PLUGIN_URL . 'public/js/account.js',
            array('jquery'),
            WCL_VERSION,
            true
        );

        wp_localize_script('wcl-account', 'wclAccount', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_account_nonce'),
            'strings' => array(
                'confirmCancel' => __('Are you sure you want to cancel your subscription? You will still have access until the end of your billing period.', 'wp-content-locker'),
                'canceling' => __('Canceling...', 'wp-content-locker'),
                'loggingIn' => __('Logging in...', 'wp-content-locker'),
                'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
            ),
        ));
    }

    /**
     * Render account page shortcode
     */
    public function render_account_page($atts) {
        // Enqueue assets
        $this->enqueue_assets();

        ob_start();
        include WCL_PLUGIN_DIR . 'templates/account.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for login
     */
    public function ajax_login() {
        // Verify nonce
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

        if (empty($username) || empty($password)) {
            wp_send_json_error(array('message' => __('Please enter username and password.', 'wp-content-locker')));
        }

        // Attempt login
        $user = wp_signon(array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ), is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => __('Invalid username or password.', 'wp-content-locker')));
        }

        wp_send_json_success(array(
            'message' => __('Login successful!', 'wp-content-locker'),
            'redirect' => '',
        ));
    }

    /**
     * AJAX handler for cancel subscription
     */
    public function ajax_cancel_subscription() {
        // Verify nonce
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'wp-content-locker')));
        }

        $user_id = get_current_user_id();

        // Get active subscription
        $subscription = WCL_Subscription::get_active_subscription($user_id);

        if (!$subscription) {
            wp_send_json_error(array('message' => __('No active subscription found.', 'wp-content-locker')));
        }

        // Cancel subscription (at period end)
        $result = WCL_Subscription::cancel_subscription($subscription->id, true);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Your subscription has been canceled. You will have access until the end of your billing period.', 'wp-content-locker'),
        ));
    }

    /**
     * Get subscription data for display
     */
    public static function get_subscription_display_data($user_id) {
        $subscription = WCL_Subscription::get_active_subscription($user_id);

        if (!$subscription) {
            // Check for any subscription (including canceled)
            $subscription = WCL_Subscription::get_by_user_id($user_id);
        }

        if (!$subscription) {
            return null;
        }

        $plan_labels = array(
            'monthly' => __('Monthly', 'wp-content-locker'),
            'yearly' => __('Yearly', 'wp-content-locker'),
        );

        $status_labels = array(
            'active' => __('Active', 'wp-content-locker'),
            'canceling' => __('Canceling', 'wp-content-locker'),
            'canceled' => __('Canceled', 'wp-content-locker'),
            'past_due' => __('Past Due', 'wp-content-locker'),
        );

        $status_colors = array(
            'active' => '#22c55e',
            'canceling' => '#f59e0b',
            'canceled' => '#ef4444',
            'past_due' => '#ef4444',
        );

        return array(
            'id' => $subscription->id,
            'plan_type' => $subscription->plan_type,
            'plan_label' => isset($plan_labels[$subscription->plan_type]) ? $plan_labels[$subscription->plan_type] : $subscription->plan_type,
            'status' => $subscription->status,
            'status_label' => isset($status_labels[$subscription->status]) ? $status_labels[$subscription->status] : $subscription->status,
            'status_color' => isset($status_colors[$subscription->status]) ? $status_colors[$subscription->status] : '#666',
            'current_period_end' => $subscription->current_period_end,
            'current_period_end_formatted' => $subscription->current_period_end ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end)) : '',
            'can_cancel' => in_array($subscription->status, array('active')),
        );
    }
}
