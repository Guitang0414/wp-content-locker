<?php
/**
 * Stripe API integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Stripe {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Stripe API base URL
     */
    private $api_base = 'https://api.stripe.com/v1';

    /**
     * Current mode override
     */
    private static $current_mode = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set mode for this instance
     */
    public static function set_mode($mode) {
        if (in_array($mode, array('live', 'test'))) {
            self::$current_mode = $mode;
        }
    }

    /**
     * Get current Stripe mode (test/live)
     * Handles URL override for admins
     */
    public static function get_mode() {
        // Check for static override
        if (self::$current_mode) {
            return self::$current_mode;
        }

        // Check for URL override (Admin or Secret Key)
        // Secret key allows testing in incognito/guest mode
        if (isset($_GET['wcl_test_mode']) && ($_GET['wcl_test_mode'] == '1' && current_user_can('manage_options') || $_GET['wcl_test_mode'] === 'wcl_test_secret')) {
            return 'test';
        }
        return get_option('wcl_stripe_mode', 'test');
    }

    /**
     * Get the secret key based on mode
     */
    private function get_secret_key() {
        $mode = self::get_mode();
        if ($mode === 'live') {
            return get_option('wcl_stripe_live_secret_key', '');
        }
        return get_option('wcl_stripe_test_secret_key', '');
    }

    /**
     * Get the publishable key based on mode
     */
    public function get_publishable_key() {
        $mode = self::get_mode();
        if ($mode === 'live') {
            return get_option('wcl_stripe_live_publishable_key', '');
        }
        return get_option('wcl_stripe_test_publishable_key', '');
    }

    /**
     * Get monthly price ID based on mode
     */
    public function get_monthly_price_id() {
        $mode = self::get_mode();
        if ($mode === 'live') {
            return get_option('wcl_monthly_price_id_live', '');
        }
        return get_option('wcl_monthly_price_id_test', '');
    }

    /**
     * Get yearly price ID based on mode
     */
    public function get_yearly_price_id() {
        $mode = self::get_mode();
        if ($mode === 'live') {
            return get_option('wcl_yearly_price_id_live', '');
        }
        return get_option('wcl_yearly_price_id_test', '');
    }

    /**
     * Get customer invoices
     */
    public function get_invoices($customer_id, $limit = 10) {
        return $this->api_request('/invoices', 'GET', array(
            'customer' => $customer_id,
            'limit' => $limit,
            'status' => 'paid',
        ));
    }

    /**
     * Get payment method details
     */
    public function get_payment_method($payment_method_id) {
        return $this->api_request('/payment_methods/' . $payment_method_id);
    }

    /**
     * Make API request to Stripe
     */
    private function api_request($endpoint, $method = 'GET', $data = array()) {
        $secret_key = $this->get_secret_key();

        if (empty($secret_key)) {
            return new WP_Error('no_api_key', __('Stripe API key not configured.', 'wp-content-locker'));
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 30,
        );

        if (!empty($data) && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = $data;
        }

        $url = $this->api_base . $endpoint;
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (isset($decoded['error'])) {
            return new WP_Error(
                'stripe_error',
                $decoded['error']['message'],
                $decoded['error']
            );
        }

        return $decoded;
    }

    /**
     * Create a Stripe customer
     */
    public function create_customer($email, $name = '', $metadata = array()) {
        $data = array(
            'email' => $email,
        );

        if (!empty($name)) {
            $data['name'] = $name;
        }

        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $data['metadata[' . $key . ']'] = $value;
            }
        }

        return $this->api_request('/customers', 'POST', $data);
    }

    /**
     * Get customer by ID
     */
    public function get_customer($customer_id) {
        return $this->api_request('/customers/' . $customer_id);
    }

    /**
     * Find customer by email
     */
    public function find_customer_by_email($email) {
        $result = $this->api_request('/customers', 'GET', array(
            'email' => $email,
            'limit' => 1,
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        if (!empty($result['data'])) {
            return $result['data'][0];
        }

        return null;
    }

    /**
     * Find promotion code by code
     */
    public function find_promotion_code($code) {
        $result = $this->api_request('/promotion_codes', 'GET', array(
            'code' => $code,
            'active' => 'true',
            'limit' => 1,
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        if (!empty($result['data'])) {
            return $result['data'][0];
        }

        return null;
    }

    /**
     * Create a Checkout Session
     */
    public function create_checkout_session($params) {
        $price_id = $params['price_id'];
        $success_url = $params['success_url'];
        $cancel_url = $params['cancel_url'];
        $customer_email = isset($params['customer_email']) ? $params['customer_email'] : '';
        $customer_id = isset($params['customer_id']) ? $params['customer_id'] : '';
        $metadata = isset($params['metadata']) ? $params['metadata'] : array();

        $data = array(
            'mode' => 'subscription',
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => 1,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'allow_promotion_codes' => 'true',
        );

        if (isset($params['discounts'])) {
            $data['discounts'] = $params['discounts'];
            // When discounts are applied, allow_promotion_codes cannot be true
            unset($data['allow_promotion_codes']);
        }

        if (!empty($customer_id)) {
            $data['customer'] = $customer_id;
        } elseif (!empty($customer_email)) {
            $data['customer_email'] = $customer_email;
        }

        // Add metadata
        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $data['metadata[' . $key . ']'] = $value;
            }
        }

        // Also add metadata to subscription
        if (!empty($metadata)) {
            foreach ($metadata as $key => $value) {
                $data['subscription_data[metadata][' . $key . ']'] = $value;
            }
        }

        return $this->api_request('/checkout/sessions', 'POST', $data);
    }

    /**
     * Retrieve a Checkout Session
     */
    public function get_checkout_session($session_id, $expand = array()) {
        $params = array();
        if (!empty($expand)) {
            foreach ($expand as $index => $field) {
                $params['expand[' . $index . ']'] = $field;
            }
        }
        return $this->api_request('/checkout/sessions/' . $session_id, 'GET', $params);
    }

    /**
     * Get subscription
     */
    public function get_subscription($subscription_id) {
        return $this->api_request('/subscriptions/' . $subscription_id);
    }

    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscription_id, $at_period_end = true) {
        if ($at_period_end) {
            return $this->api_request('/subscriptions/' . $subscription_id, 'POST', array(
                'cancel_at_period_end' => 'true',
            ));
        }
        return $this->api_request('/subscriptions/' . $subscription_id, 'DELETE');
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('wp-content-locker/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle Stripe webhook
     */
    public function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        // DEBUG: Log incoming webhook
        error_log('WCL Webhook Received: ' . substr($payload, 0, 100) . '...');
        error_log('WCL Signature: ' . $sig_header);
        $webhook_secret = get_option('wcl_stripe_webhook_secret', '');
        $test_webhook_secret = get_option('wcl_stripe_test_webhook_secret', '');

        // Verify webhook signature
        $verified = false;

        // Try primary (Live) secret
        if (!empty($webhook_secret)) {
            $verified = $this->verify_webhook_signature($payload, $sig_header, $webhook_secret);
        }

        // If not verified, try Test secret
        if (!$verified && !empty($test_webhook_secret)) {
            $verified = $this->verify_webhook_signature($payload, $sig_header, $test_webhook_secret);
            // If verified with test secret, ensure we treat this as a test event if needed
        }

        if (!$verified && (!empty($webhook_secret) || !empty($test_webhook_secret))) {
             error_log('WCL Webhook Error: Signature Verification Failed');
             return new WP_REST_Response(array('error' => 'Invalid signature'), 400);
        }

        $event = json_decode($payload, true);
        error_log('WCL Webhook Verified. Event Type: ' . (isset($event['type']) ? $event['type'] : 'unknown'));

        if (!$event || !isset($event['type'])) {
            return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
        }

        // Handle different event types
        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed($event['data']['object']);
                break;

            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handle_payment_failed($event['data']['object']);
                break;
        }

        return new WP_REST_Response(array('received' => true), 200);
    }

    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $sig_header, $secret) {
        if (empty($sig_header)) {
            return false;
        }

        // Parse signature header
        $parts = explode(',', $sig_header);
        $timestamp = null;
        $signature = null;

        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                if ($pair[0] === 't') {
                    $timestamp = $pair[1];
                } elseif ($pair[0] === 'v1') {
                    $signature = $pair[1];
                }
            }
        }

        if (!$timestamp || !$signature) {
            return false;
        }

        // Check timestamp (allow 5 minute tolerance)
        $tolerance = 300;
        if (abs(time() - (int)$timestamp) > $tolerance) {
            return false;
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Handle checkout completed event
     */
    private function handle_checkout_completed($session) {
        // Get full session with customer and subscription expanded
        $session = $this->get_checkout_session($session['id'], array('customer', 'subscription'));

        if (is_wp_error($session)) {
            error_log('WCL: Failed to get checkout session - ' . $session->get_error_message());
            return;
        }

        $customer_email = '';
        $customer_id = '';
        $subscription_id = '';

        if (isset($session['customer'])) {
            if (is_array($session['customer'])) {
                $customer_id = $session['customer']['id'];
                $customer_email = $session['customer']['email'];
            } else {
                $customer_id = $session['customer'];
                // Fetch customer to get email
                $customer = $this->get_customer($customer_id);
                if (!is_wp_error($customer)) {
                    $customer_email = $customer['email'];
                }
            }
        } elseif (isset($session['customer_email'])) {
            $customer_email = $session['customer_email'];
        }

        if (isset($session['subscription'])) {
            if (is_array($session['subscription'])) {
                $subscription_id = $session['subscription']['id'];
            } else {
                $subscription_id = $session['subscription'];
            }
        }

        // Get metadata
        $metadata = isset($session['metadata']) ? $session['metadata'] : array();
        $post_id = isset($metadata['post_id']) ? $metadata['post_id'] : 0;

        // Create or get WordPress user
        $user_result = WCL_User::get_or_create_user($customer_email);

        if (is_wp_error($user_result)) {
            error_log('WCL: Failed to create user - ' . $user_result->get_error_message());
            return;
        }

        $user_id = is_array($user_result) ? $user_result['user_id'] : $user_result;

        // Get subscription details
        $subscription = null;
        if (isset($session['subscription']) && is_array($session['subscription'])) {
            $subscription = $session['subscription'];
        } else {
            $subscription = $this->get_subscription($subscription_id);
        }
        $plan_type = 'monthly';
        $period_start = null;
        $period_end = null;
        $amount_formatted = '';
        $plan_name = '';

        if ($subscription && !is_wp_error($subscription)) {
            // Determine plan type from interval
            if (isset($subscription['items']['data'][0]['price']['recurring']['interval'])) {
                $interval = $subscription['items']['data'][0]['price']['recurring']['interval'];
                $plan_type = ($interval === 'year') ? 'yearly' : 'monthly';
                
                // Get price details for email
                $price_id = $subscription['items']['data'][0]['price']['id'];
                $amount_formatted = $this->get_formatted_price($price_id);
                $plan_name = ($interval === 'year') ? __('Yearly Subscription', 'wp-content-locker') : __('Monthly Subscription', 'wp-content-locker');
            }

            $period_start = isset($subscription['current_period_start'])
                ? date('Y-m-d H:i:s', $subscription['current_period_start'])
                : null;
            $period_end = isset($subscription['current_period_end'])
                ? date('Y-m-d H:i:s', $subscription['current_period_end'])
                : null;
        }

        // Create subscription record
        WCL_Subscription::create_subscription(array(
            'user_id' => $user_id,
            'stripe_customer_id' => $customer_id,
            'stripe_subscription_id' => $subscription_id,
            'plan_type' => $plan_type,
            'mode' => (isset($session['livemode']) && $session['livemode']) ? 'live' : 'test',
            'status' => 'active',
            'current_period_start' => $period_start,
            'current_period_end' => $period_end,
        ));

        // Update user meta
        update_user_meta($user_id, '_wcl_stripe_customer_id', $customer_id);
        update_user_meta($user_id, '_wcl_subscription_status', 'active');

        // Send subscription email
        $email_data = array(
            'user_id' => $user_id,
            'password' => is_array($user_result) ? $user_result['password'] : null,
            'new_user' => is_array($user_result) ? $user_result['new_user'] : false,
            'plan_name' => $plan_name,
            'amount' => $amount_formatted,
            'post_url' => $post_id ? get_permalink($post_id) : home_url()
        );
        WCL_User::send_subscription_email($email_data);
    }

    /**
     * Handle subscription updated event
     */
    private function handle_subscription_updated($subscription) {
        $subscription_id = $subscription['id'];
        $status = $subscription['status'];

        // Map Stripe status to our status
        $wcl_status = 'active';
        if (in_array($status, array('canceled', 'unpaid', 'incomplete_expired'))) {
            $wcl_status = 'canceled';
        } elseif ($status === 'past_due') {
            $wcl_status = 'past_due';
        } elseif ($subscription['cancel_at_period_end']) {
            $wcl_status = 'canceling';
        }

        // Update subscription in database
        WCL_Subscription::update_subscription_by_stripe_id($subscription_id, array(
            'status' => $wcl_status,
            'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
        ));

        // Update user meta
        $sub = WCL_Subscription::get_by_stripe_subscription_id($subscription_id);
        if ($sub) {
            update_user_meta($sub->user_id, '_wcl_subscription_status', $wcl_status);
        }
    }

    /**
     * Handle subscription deleted event
     */
    private function handle_subscription_deleted($subscription) {
        $subscription_id = $subscription['id'];

        WCL_Subscription::update_subscription_by_stripe_id($subscription_id, array(
            'status' => 'canceled',
        ));

        $sub = WCL_Subscription::get_by_stripe_subscription_id($subscription_id);
        if ($sub) {
            update_user_meta($sub->user_id, '_wcl_subscription_status', 'canceled');
        }
    }

    /**
     * Handle payment failed event
     */
    private function handle_payment_failed($invoice) {
        $subscription_id = isset($invoice['subscription']) ? $invoice['subscription'] : '';
        if (empty($subscription_id)) {
            return;
        }

        WCL_Subscription::update_subscription_by_stripe_id($subscription_id, array(
            'status' => 'past_due',
        ));

        $sub = WCL_Subscription::get_by_stripe_subscription_id($subscription_id);
        if ($sub) {
            update_user_meta($sub->user_id, '_wcl_subscription_status', 'past_due');
        }
    }

    /**
     * Get price details from Stripe
     *
     * @param string $price_id Stripe Price ID
     * @return array|WP_Error Price details or error
     */
    public function get_price($price_id) {
        if (empty($price_id)) {
            return new WP_Error('no_price_id', __('Price ID not provided.', 'wp-content-locker'));
        }

        // Cache key for transient
        $cache_key = 'wcl_stripe_price_' . md5($price_id);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $result = $this->api_request('/prices/' . $price_id, 'GET', array(
            'expand[0]' => 'product',
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        // Cache for 1 hour
        set_transient($cache_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Get formatted price string from Stripe price object
     *
     * @param string $price_id Stripe Price ID
     * @return string Formatted price (e.g., "$9.99/month")
     */
    public function get_formatted_price($price_id) {
        $price = $this->get_price($price_id);

        if (is_wp_error($price)) {
            return '';
        }

        $amount = isset($price['unit_amount']) ? $price['unit_amount'] / 100 : 0;
        $currency = isset($price['currency']) ? strtoupper($price['currency']) : 'USD';
        $interval = isset($price['recurring']['interval']) ? $price['recurring']['interval'] : '';

        // Format currency symbol
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
        );
        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';

        // Format interval
        $interval_text = '';
        if ($interval === 'month') {
            $interval_text = '/' . __('month', 'wp-content-locker');
        } elseif ($interval === 'year') {
            $interval_text = '/' . __('year', 'wp-content-locker');
        }

        return $symbol . number_format($amount, 2) . $interval_text;
    }
}
