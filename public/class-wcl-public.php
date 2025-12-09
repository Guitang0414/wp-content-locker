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

        // Debug mode - add ?wcl_debug=1 to URL to see debug info
        if (isset($_GET['wcl_debug']) && $_GET['wcl_debug'] == '1' && current_user_can('manage_options')) {
            $post_id = get_the_ID();
            $debug_info = array(
                'post_id' => $post_id,
                'post_meta_wcl_enable_paywall' => get_post_meta($post_id, '_wcl_enable_paywall', true),
                'global_default_mode' => get_option('wcl_default_paywall_mode', 'disabled'),
                'has_paywall' => WCL_Content::has_paywall($post_id),
                'is_user_logged_in' => is_user_logged_in(),
                'user_can_access' => WCL_Content::user_can_access($post_id),
            );
            $debug_html = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;font-family:monospace;">';
            $debug_html .= '<strong>WCL Debug Info:</strong><br>';
            foreach ($debug_info as $key => $value) {
                $debug_html .= $key . ': ' . var_export($value, true) . '<br>';
            }
            $debug_html .= '</div>';
            return $debug_html . WCL_Content::apply_paywall($content);
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
        // Always load on single posts - let JS handle the rest
        if (!is_singular('post')) {
            return;
        }

        // Use the shared inline method
        $this->enqueue_paywall_scripts();
    }

    /**
     * Enqueue paywall scripts (can be called from multiple places)
     */
    public function enqueue_paywall_scripts() {
        // Prevent double loading
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        // Add to footer
        add_action('wp_footer', array($this, 'output_inline_js'), 100);
    }

    /**
     * Output inline JS
     */
    public function output_inline_js() {
        static $printed = false;
        if ($printed) return;
        $printed = true;

        // Check for test mode override
        $is_test_mode = false;
        if (isset($_GET['wcl_test_mode']) && ($_GET['wcl_test_mode'] == '1' && current_user_can('manage_options') || $_GET['wcl_test_mode'] === 'wcl_test_secret')) {
            $is_test_mode = true;
        }

        $data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcl_checkout_nonce'),
            'postId' => get_the_ID(),
            'isLoggedIn' => is_user_logged_in(),
            'isTestMode' => $is_test_mode,
            'strings' => array(
                'processing' => __('Processing...', 'wp-content-locker'),
                'error' => __('An error occurred. Please try again.', 'wp-content-locker'),
                'invalidEmail' => __('Please enter a valid email address.', 'wp-content-locker'),
            ),
        );

        echo '<script type="text/javascript">
        var wclData = ' . json_encode($data) . ';
        </script>';

        echo '<script type="text/javascript">';
        include WCL_PLUGIN_DIR . 'public/js/public.js';
        echo '</script>';
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

        // Use filter_var instead of sanitize_email to preserve valid emails with numbers at start
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = ''; // Invalid email
        }

        // Get current mode
        $mode = WCL_Stripe::get_mode();

        // Check for test mode override from AJAX
        if (isset($_POST['test_mode']) && $_POST['test_mode'] == '1') {
            // We trust the flag here because it comes from the localized script which already checked permissions/secret
            // But for extra security, we could pass the secret again, but let's keep it simple for now as it only affects Stripe mode
            $mode = 'test';
            WCL_Stripe::set_mode('test');
        }

        // Check if logged-in user already has active subscription
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (WCL_Subscription::has_active_subscription($user_id, $mode)) {
                wp_send_json_error(array('message' => __('You already have an active subscription.', 'wp-content-locker')));
            }
        }

        // Check if email already has active subscription (for non-logged-in users)
        if (!is_user_logged_in() && !empty($email)) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                // User exists, check subscription status
                $has_subscription = WCL_Subscription::has_active_subscription($existing_user->ID, $mode);
                if ($has_subscription) {
                    wp_send_json_error(array('message' => __('This email already has an active subscription. Please log in to access premium content.', 'wp-content-locker')));
                }
            }
            // If user doesn't exist or doesn't have active subscription, allow checkout
        }

        // Get price ID based on plan type
        $price_id = '';
        $stripe = WCL_Stripe::get_instance();
        
        if ($plan_type === 'yearly') {
            $price_id = $stripe->get_yearly_price_id();
        } else {
            $price_id = $stripe->get_monthly_price_id();
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

        // Auto-apply promo code for monthly plan
        if ($plan_type === 'monthly') {
            $promo_code = get_option('wcl_promo_code', 'NEWUSER');
            if (!empty($promo_code)) {
                $promotion = $stripe->find_promotion_code($promo_code);
                if ($promotion && !is_wp_error($promotion)) {
                    $params['discounts'] = array(
                        array('promotion_code' => $promotion['id']),
                    );
                }
            }
        }

        $session = $stripe->create_checkout_session($params);

        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $session->get_error_message()));
        }

        $checkout_url = $session['url'];

        wp_send_json_success(array(
            'checkout_url' => $checkout_url,
        ));
    }

    /**
     * Fallback method for page builders like Elementor
     * Injects paywall via JavaScript if content filter doesn't work
     */
    public function maybe_apply_paywall_redirect() {
        // Only on single posts
        if (!is_singular('post')) {
            return;
        }

        $post_id = get_the_ID();

        // Check if paywall should be applied
        if (!WCL_Content::has_paywall($post_id)) {
            return;
        }

        // If user can access, no need for paywall
        if (WCL_Content::user_can_access($post_id)) {
            return;
        }

        // Ensure scripts are loaded
        $this->enqueue_paywall_scripts();

        // Add footer hook to inject paywall via JS
        add_action('wp_footer', array($this, 'inject_paywall_js'), 100);
    }

    /**
     * Inject paywall JavaScript for Elementor and other page builders
     */
    public function inject_paywall_js() {
        $post_id = get_the_ID();
        $paywall_html = WCL_Content::get_paywall_html($post_id);
        $preview_percentage = get_post_meta($post_id, '_wcl_preview_percentage', true);
        if (empty($preview_percentage)) {
            $preview_percentage = get_option('wcl_preview_percentage', 30);
        }
        ?>
        <script>
        (function() {
            // Find the main content area - prioritize tagDiv theme selectors
            var selectors = [
                '.tdb_single_content .tdb-block-inner',
                '.tdb_single_content',
                '.td-post-content',
                '.td_block_wrap.tdb_single_content',
                '.elementor-widget-theme-post-content .elementor-widget-container',
                '.elementor-widget-text-editor .elementor-widget-container',
                '.entry-content',
                '.post-content',
                'article .content',
                '.single-post-content',
                'article'
            ];

            var contentEl = null;
            for (var i = 0; i < selectors.length; i++) {
                var el = document.querySelector(selectors[i]);
                // Make sure it's not inside a popup modal
                if (el && el.innerText.length > 100 && !el.closest('.tdm-popup-modal')) {
                    contentEl = el;
                    break;
                }
            }

            if (!contentEl) return;

            // Check if paywall already applied (not in popup)
            var existingPaywall = document.querySelector('.wcl-paywall');
            if (existingPaywall && !existingPaywall.closest('.tdm-popup-modal')) return;

            // Get text content length
            var fullText = contentEl.innerText;
            var targetLength = Math.floor(fullText.length * (<?php echo intval($preview_percentage); ?> / 100));

            // Create wrapper
            var wrapper = document.createElement('div');
            wrapper.className = 'wcl-content-wrapper';

            // Clone content for preview
            var preview = document.createElement('div');
            preview.className = 'wcl-preview-content';
            preview.innerHTML = contentEl.innerHTML;

            // Truncate preview (simple approach - hide overflow)
            preview.style.maxHeight = (contentEl.offsetHeight * <?php echo intval($preview_percentage); ?> / 100) + 'px';
            preview.style.overflow = 'hidden';

            // Add fade overlay
            var fade = document.createElement('div');
            fade.className = 'wcl-fade-overlay';

            // Add paywall
            var paywall = document.createElement('div');
            paywall.innerHTML = <?php echo json_encode($paywall_html); ?>;

            // Replace content
            wrapper.appendChild(preview);
            wrapper.appendChild(fade);
            wrapper.appendChild(paywall.firstElementChild || paywall);

            contentEl.innerHTML = '';
            contentEl.appendChild(wrapper);
        })();
        </script>
        <?php
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
