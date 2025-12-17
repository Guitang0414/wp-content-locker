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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers
        add_action('wp_ajax_wcl_cancel_subscription', array($this, 'ajax_cancel_subscription'));
        add_action('wp_ajax_wcl_resume_subscription', array($this, 'ajax_resume_subscription'));
        add_action('wp_ajax_wcl_login', array($this, 'ajax_login'));
        add_action('wp_ajax_nopriv_wcl_login', array($this, 'ajax_login'));
        add_action('wp_ajax_wcl_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_wcl_register', array($this, 'ajax_register'));
        add_action('wp_ajax_nopriv_wcl_register', array($this, 'ajax_register'));
        add_action('wp_ajax_wcl_lost_password', array($this, 'ajax_lost_password'));
        add_action('wp_ajax_nopriv_wcl_lost_password', array($this, 'ajax_lost_password'));
    }

    /**
     * Enqueue account page assets
     */
    public function enqueue_assets() {
        global $post;
        // removed has_shortcode check as it can fail with page builders
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        wp_enqueue_style(
            'wcl-account',
            WCL_PLUGIN_URL . 'public/css/account.css',
            array(),
            file_exists(WCL_PLUGIN_DIR . 'public/css/account.css') ? filemtime(WCL_PLUGIN_DIR . 'public/css/account.css') : WCL_VERSION
        );

        wp_enqueue_script(
            'wcl-account',
            WCL_PLUGIN_URL . 'public/js/account.js',
            array('jquery'),
            file_exists(WCL_PLUGIN_DIR . 'public/js/account.js') ? filemtime(WCL_PLUGIN_DIR . 'public/js/account.js') : WCL_VERSION,
            true
        );

        wp_localize_script('wcl-account', 'wclAccount', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_account_nonce'),
            'strings' => array(
                'confirmCancel' => __('Are you sure you want to cancel your subscription? You will still have access until the end of your billing period.', 'wp-content-locker'),
                'confirmResume' => __('Are you sure you want to resume your subscription?', 'wp-content-locker'),
                'canceling' => __('Canceling...', 'wp-content-locker'),
                'resuming' => __('Resuming...', 'wp-content-locker'),
                'loggingIn' => __('Logging in...', 'wp-content-locker'),
                'saving' => __('Saving...', 'wp-content-locker'),
                'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
                'passwordMismatch' => __('Passwords do not match.', 'wp-content-locker'),
            ),
        ));
    }

    /**
     * Render account page shortcode
     */
    public function render_account_page($atts) {
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
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';

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
            'redirect' => $redirect_to,
        ));
    }

    /**
     * AJAX handler for register
     */
    public function ajax_register() {
        // Verify nonce
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'wp-content-locker')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'wp-content-locker')));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'wp-content-locker')));
        }

        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Account already exists with this email.', 'wp-content-locker')));
        }

        // Create user
        $result = WCL_User::create_user($email, $name, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $user_id = is_array($result) ? $result['user_id'] : $result;

        // Auto login
        WCL_User::auto_login($user_id);

        wp_send_json_success(array(
            'message' => __('Registration successful! Redirecting...', 'wp-content-locker'),
        ));
    }

    /**
     * AJAX handler for lost password
     */
    public function ajax_lost_password() {
        // Verify nonce
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        $user_login = isset($_POST['user_login']) ? sanitize_text_field($_POST['user_login']) : '';

        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Please enter a username or email address.', 'wp-content-locker')));
        }

        // Use standard WP function to send password reset email
        // Note: retrieve_password returns WP_Error or true (bool) or object
        // Depending on WP version, it might not be available if not included.
        // It's usually in wp-login.php but also wp-includes/user.php
        
        // We will use the retrieve_password function logic manually if need be, 
        // but 'retrieve_password' is standard since WP 2.5? 
        // Actually, let's use the 'retrieve_password' function if it exists.
        
        if (!function_exists('retrieve_password')) {
            require_once ABSPATH . 'wp-includes/user.php';
        }
        
        // However, retrieve_password isn't a standard public API function in all contexts.
        // The robust way is to use existing WP logic.
        
        $user_data = get_user_by('login', $user_login);
        if (!$user_data) {
            $user_data = get_user_by('email', $user_login);
        }

        // For security, do not reveal if user exists or not, but return success.
        // However, standard WP behavior is to show errors.
        if (!$user_data) {
            wp_send_json_error(array('message' => __('Invalid username or email.', 'wp-content-locker')));
        }

        // Generate reset key
        $key = get_password_reset_key($user_data);
        if (is_wp_error($key)) {
            wp_send_json_error(array('message' => $key->get_error_message()));
        }

        // Send email
        $message = __('Someone has requested a password reset for the following account:', 'wp-content-locker') . "\r\n\r\n";
        $message .= network_home_url('/') . "\r\n\r\n";
        $message .= sprintf(__('Username: %s', 'wp-content-locker'), $user_data->user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, just ignore this email and nothing will happen.', 'wp-content-locker') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:', 'wp-content-locker') . "\r\n\r\n";
        
        // Here we can point to standard wp-login.php OR our custom page if we build stage 2
        // For now, let's point to standard WP login to ensure it works reliably first.
        $message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_data->user_login), 'login') . "\r\n";

        $title = sprintf(__('[%s] Password Reset', 'wp-content-locker'), get_bloginfo('name'));

        if (wp_mail($user_data->user_email, wp_specialchars_decode($title), $message)) {
            wp_send_json_success(array('message' => __('Check your email for the confirmation link.', 'wp-content-locker')));
        } else {
            wp_send_json_error(array('message' => __('The email could not be sent.', 'wp-content-locker')));
        }
    }

    /**
     * AJAX handler for update profile
     */
    public function ajax_update_profile() {
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'wp-content-locker')));
        }

        $user_id = get_current_user_id();
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $display_name = trim($first_name . ' ' . $last_name);

        $user_data = array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
        );

        // Explicitly update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        if (!empty($display_name)) {
            $user_data['display_name'] = $display_name;
        }

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Profile updated successfully.', 'wp-content-locker')));
    }

    /**
     * AJAX handler for change password
     */
    public function ajax_change_password() {
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'wp-content-locker')));
        }

        $user_id = get_current_user_id();
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($current_password) || empty($new_password)) {
            wp_send_json_error(array('message' => __('Please fill in all fields.', 'wp-content-locker')));
        }

        // Validate password length
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => __('Password must be at least 8 characters.', 'wp-content-locker')));
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => __('New passwords do not match.', 'wp-content-locker')));
        }

        $user = get_user_by('id', $user_id);
        // Note: Passwords are not sanitized to preserve special characters
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => __('Current password is incorrect.', 'wp-content-locker')));
        }

        wp_set_password($new_password, $user_id);

        // Re-login user
        wp_signon(array(
            'user_login' => $user->user_login,
            'user_password' => $new_password,
            'remember' => true,
        ));

        wp_send_json_success(array('message' => __('Password changed successfully.', 'wp-content-locker')));
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
     * AJAX handler for resume subscription
     */
    public function ajax_resume_subscription() {
        // Verify nonce
        if (!check_ajax_referer('wcl_account_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'wp-content-locker')));
        }

        $user_id = get_current_user_id();

        // Get canceling subscription
        $subscription = WCL_Subscription::get_active_subscription($user_id);

        if (!$subscription || $subscription->status !== 'canceling') {
            wp_send_json_error(array('message' => __('No pending cancellation found to resume.', 'wp-content-locker')));
        }

        // Resume subscription
        $result = WCL_Subscription::resume_subscription($subscription->id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Your subscription has been resumed.', 'wp-content-locker'),
        ));
    }

    /**
     * Get subscription data for display
     */
    public static function get_subscription_display_data($user_id) {
        // Check cache first
        $cache_key = 'wcl_sub_display_' . $user_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

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

        // Get additional details from Stripe
        $stripe = WCL_Stripe::get_instance();
        
        // Set mode based on subscription if available
        if (isset($subscription->mode)) {
            $stripe->set_mode($subscription->mode);
        }

        $stripe_sub = $stripe->get_subscription($subscription->stripe_subscription_id);
        
        $payment_method_info = 'N/A';
        $invoices = array();

        if (!is_wp_error($stripe_sub)) {
            // Get Payment Method
            if (isset($stripe_sub['default_payment_method']) && !empty($stripe_sub['default_payment_method'])) {
                $pm = $stripe->get_payment_method($stripe_sub['default_payment_method']);
                if (!is_wp_error($pm) && isset($pm['card'])) {
                    $payment_method_info = strtoupper($pm['card']['brand']) . ' •••• ' . $pm['card']['last4'];
                }
            }

            // Get Invoices
            $customer_id = $subscription->stripe_customer_id;
            $invoices_data = $stripe->get_invoices($customer_id, 10);
            if (!is_wp_error($invoices_data) && isset($invoices_data['data'])) {
                foreach ($invoices_data['data'] as $invoice) {
                    $invoices[] = array(
                        'date' => date_i18n(get_option('date_format'), $invoice['created']),
                        'amount' => strtoupper($invoice['currency']) . ' ' . number_format($invoice['amount_paid'] / 100, 2),
                        'status' => ucfirst($invoice['status']),
                        'pdf' => $invoice['invoice_pdf'],
                    );
                }
            }
        }

        $data = array(
            'id' => $subscription->id,
            'plan_type' => $subscription->plan_type,
            'plan_label' => isset($plan_labels[$subscription->plan_type]) ? $plan_labels[$subscription->plan_type] : $subscription->plan_type,
            'status' => $subscription->status,
            'status_label' => isset($status_labels[$subscription->status]) ? $status_labels[$subscription->status] : $subscription->status,
            'status_color' => isset($status_colors[$subscription->status]) ? $status_colors[$subscription->status] : '#666',
            'current_period_end' => $subscription->current_period_end,
            'current_period_end_formatted' => $subscription->current_period_end ? date_i18n(get_option('date_format'), strtotime($subscription->current_period_end)) : '',
            'can_cancel' => in_array($subscription->status, array('active')),
            'can_resume' => in_array($subscription->status, array('canceling')),
            'payment_method' => $payment_method_info,
            'invoices' => $invoices,
        );

        // Cache for 5 minutes
        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);

        return $data;
    }
}
