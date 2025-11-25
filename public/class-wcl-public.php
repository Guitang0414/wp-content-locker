<?php
/**
 * Public-facing functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Public {

    /**
     * Filter the content to apply paywall
     */
    public function filter_content($content) {
        // Only apply on single posts
        if (!is_singular('post')) {
            return $content;
        }

        // Don't apply in admin or REST API
        if (is_admin() || defined('REST_REQUEST')) {
            return $content;
        }

        return WCL_Content::apply_paywall($content);
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        if (!is_singular('post')) {
            return;
        }

        wp_enqueue_style(
            'wcl-public',
            WCL_PLUGIN_URL . 'public/css/public.css',
            array(),
            WCL_VERSION
        );
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        if (!is_singular('post')) {
            return;
        }

        wp_enqueue_script(
            'wcl-public',
            WCL_PLUGIN_URL . 'public/js/public.js',
            array('jquery'),
            WCL_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script('wcl-public', 'wclData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_checkout_nonce'),
            'postId' => get_the_ID(),
            'isLoggedIn' => is_user_logged_in(),
            'strings' => array(
                'processing' => __('Processing...', 'wp-content-locker'),
                'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
            ),
        ));
    }

    /**
     * Create Stripe Checkout session (AJAX handler)
     */
    public function create_checkout_session() {
        // Verify nonce
        if (!check_ajax_referer('wcl_checkout_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-content-locker')));
        }

        $plan_type = isset($_POST['plan_type']) ? sanitize_text_field($_POST['plan_type']) : 'monthly';
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        // Get price ID based on plan type
        $price_id = '';
        if ($plan_type === 'yearly') {
            $price_id = get_option('wcl_yearly_price_id', '');
        } else {
            $price_id = get_option('wcl_monthly_price_id', '');
        }

        if (empty($price_id)) {
            wp_send_json_error(array('message' => __('Subscription plan not configured.', 'wp-content-locker')));
        }

        // Build success URL with session ID placeholder
        $success_url = add_query_arg(array(
            'wcl_checkout' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
            'post_id' => $post_id,
        ), home_url());

        // Cancel URL - back to the post
        $cancel_url = $post_id ? get_permalink($post_id) : home_url();

        // Prepare checkout session params
        $params = array(
            'price_id' => $price_id,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => array(
                'post_id' => $post_id,
                'plan_type' => $plan_type,
            ),
        );

        // If user is logged in, use their email and check for existing customer
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_id = WCL_User::get_stripe_customer_id($user->ID);

            if ($customer_id) {
                $params['customer_id'] = $customer_id;
            } else {
                $params['customer_email'] = $user->user_email;
            }

            $params['metadata']['user_id'] = $user->ID;
        } elseif (!empty($email)) {
            // Check if email has existing Stripe customer
            $stripe = WCL_Stripe::get_instance();
            $existing_customer = $stripe->find_customer_by_email($email);

            if ($existing_customer && !is_wp_error($existing_customer)) {
                $params['customer_id'] = $existing_customer['id'];
            } else {
                $params['customer_email'] = $email;
            }
        }

        // Create checkout session
        $stripe = WCL_Stripe::get_instance();
        $session = $stripe->create_checkout_session($params);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        wp_send_json_success(array(
            'checkout_url' => $session['url'],
        ));
    }

    /**
     * Handle checkout success redirect
     */
    public function handle_checkout_success() {
        if (!isset($_GET['wcl_checkout']) || $_GET['wcl_checkout'] !== 'success') {
            return;
        }

        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (empty($session_id)) {
            return;
        }

        // Get checkout session
        $stripe = WCL_Stripe::get_instance();
        $session = $stripe->get_checkout_session($session_id, array('customer', 'subscription'));

        if (is_wp_error($session)) {
            // Redirect to post with error
            if ($post_id) {
                wp_redirect(add_query_arg('wcl_error', 'session', get_permalink($post_id)));
            } else {
                wp_redirect(home_url());
            }
            exit;
        }

        // Get customer email
        $customer_email = '';
        if (isset($session['customer']) && is_array($session['customer'])) {
            $customer_email = $session['customer']['email'];
        } elseif (isset($session['customer_details']['email'])) {
            $customer_email = $session['customer_details']['email'];
        }

        if (!empty($customer_email)) {
            // Get or create user
            $user_id = WCL_User::get_or_create_user($customer_email);

            if (!is_wp_error($user_id)) {
                // Auto-login the user
                WCL_User::auto_login($user_id);

                // The webhook will handle creating the subscription record
                // But we can update user meta here for immediate access
                $customer_id = is_array($session['customer']) ? $session['customer']['id'] : $session['customer'];
                update_user_meta($user_id, '_wcl_stripe_customer_id', $customer_id);
                update_user_meta($user_id, '_wcl_subscription_status', 'active');
            }
        }

        // Redirect to original post
        if ($post_id) {
            wp_redirect(add_query_arg('wcl_subscribed', '1', get_permalink($post_id)));
        } else {
            wp_redirect(home_url('?wcl_subscribed=1'));
        }
        exit;
    }
}
