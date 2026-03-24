<?php
/**
 * Admin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCL_Admin')) {
class WCL_Admin {

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        // Add as top-level menu for better visibility
        add_menu_page(
            __('WP Content Locker', 'wp-content-locker'),
            __('Content Locker', 'wp-content-locker'),
            'manage_options',
            'wp-content-locker',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            30
        );

        // Add Subscriptions submenu
        add_submenu_page(
            'wp-content-locker',
            __('Subscriptions', 'wp-content-locker'),
            __('Subscriptions', 'wp-content-locker'),
            'manage_options',
            'wcl-subscriptions',
            array($this, 'render_subscriptions_page')
        );

        // Add Logs submenu
        add_submenu_page(
            'wp-content-locker',
            __('Logs', 'wp-content-locker'),
            __('Logs', 'wp-content-locker'),
            'manage_options',
            'wcl-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Stripe Settings Section
        add_settings_section(
            'wcl_stripe_section',
            __('Stripe Settings', 'wp-content-locker'),
            array($this, 'render_stripe_section'),
            'wp-content-locker'
        );

        // Stripe Mode
        register_setting('wcl_settings', 'wcl_stripe_mode');
        add_settings_field(
            'wcl_stripe_mode',
            __('Stripe Mode', 'wp-content-locker'),
            array($this, 'render_stripe_mode_field'),
            'wp-content-locker',
            'wcl_stripe_section'
        );

        // Test Keys
        register_setting('wcl_settings', 'wcl_stripe_test_publishable_key');
        add_settings_field(
            'wcl_stripe_test_publishable_key',
            __('Test Publishable Key', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_test_publishable_key', 'class' => 'stripe-test-field regular-text')
        );

        register_setting('wcl_settings', 'wcl_stripe_test_secret_key');
        add_settings_field(
            'wcl_stripe_test_secret_key',
            __('Test Secret Key', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_test_secret_key', 'type' => 'password', 'class' => 'stripe-test-field regular-text')
        );

        // Live Keys
        register_setting('wcl_settings', 'wcl_stripe_live_publishable_key');
        add_settings_field(
            'wcl_stripe_live_publishable_key',
            __('Live Publishable Key', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_live_publishable_key', 'class' => 'stripe-live-field regular-text')
        );

        register_setting('wcl_settings', 'wcl_stripe_live_secret_key');
        add_settings_field(
            'wcl_stripe_live_secret_key',
            __('Live Secret Key', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_live_secret_key', 'type' => 'password', 'class' => 'stripe-live-field regular-text')
        );

        // Webhook Secret (Live)
        register_setting('wcl_settings', 'wcl_stripe_webhook_secret');
        add_settings_field(
            'wcl_stripe_webhook_secret',
            __('Webhook Secret (Live)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_webhook_secret', 'type' => 'password', 'class' => 'regular-text stripe-live-field')
        );

        // Webhook Secret (Test)
        register_setting('wcl_settings', 'wcl_stripe_test_webhook_secret');
        add_settings_field(
            'wcl_stripe_test_webhook_secret',
            __('Webhook Secret (Test)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_test_webhook_secret', 'type' => 'password', 'class' => 'regular-text stripe-test-field')
        );

        // Price IDs Section
        add_settings_section(
            'wcl_pricing_section',
            __('Subscription Plans', 'wp-content-locker'),
            array($this, 'render_pricing_section'),
            'wp-content-locker'
        );

        // Monthly Price ID (Test)
        register_setting('wcl_settings', 'wcl_monthly_price_id_test');
        add_settings_field(
            'wcl_monthly_price_id_test',
            __('Monthly Price ID (Test)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_price_id_test', 'placeholder' => 'price_xxx', 'class' => 'regular-text stripe-test-field')
        );

        // Monthly Price ID (Live)
        register_setting('wcl_settings', 'wcl_monthly_price_id_live');
        add_settings_field(
            'wcl_monthly_price_id_live',
            __('Monthly Price ID (Live)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_price_id_live', 'placeholder' => 'price_xxx', 'class' => 'regular-text stripe-live-field')
        );

        // Monthly Display Price
        register_setting('wcl_settings', 'wcl_monthly_price', array('sanitize_callback' => 'sanitize_text_field'));
        add_settings_field(
            'wcl_monthly_price',
            __('Monthly Display Price (optional)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_price', 'placeholder' => '9.99', 'class' => 'small-text', 'description' => __('Fallback if Stripe price cannot be fetched', 'wp-content-locker'))
        );

        // Monthly Original Price (for strikethrough)
        register_setting('wcl_settings', 'wcl_monthly_original_price');
        add_settings_field(
            'wcl_monthly_original_price',
            __('Monthly Original Price', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_original_price', 'placeholder' => '$2', 'class' => 'small-text', 'description' => __('Displayed with strikethrough', 'wp-content-locker'))
        );

        // Monthly Discounted Price (Large display)
        register_setting('wcl_settings', 'wcl_monthly_discounted_price');
        add_settings_field(
            'wcl_monthly_discounted_price',
            __('Monthly Discounted Price', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_discounted_price', 'placeholder' => '50¢', 'class' => 'small-text', 'description' => __('Large highlighted price', 'wp-content-locker'))
        );

        // Monthly Description
        register_setting('wcl_settings', 'wcl_monthly_description');
        add_settings_field(
            'wcl_monthly_description',
            __('Monthly Description', 'wp-content-locker'),
            array($this, 'render_textarea_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_description')
        );

        // Promo Code
        register_setting('wcl_settings', 'wcl_promo_code');
        add_settings_field(
            'wcl_promo_code',
            __('Promo Code', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_promo_code', 'placeholder' => 'NEWUSER', 'class' => 'regular-text', 'description' => __('Auto-applied for monthly plan', 'wp-content-locker'))
        );

        // Yearly Price ID (Test)
        register_setting('wcl_settings', 'wcl_yearly_price_id_test');
        add_settings_field(
            'wcl_yearly_price_id_test',
            __('Yearly Price ID (Test)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_price_id_test', 'placeholder' => 'price_xxx', 'class' => 'regular-text stripe-test-field')
        );

        // Yearly Price ID (Live)
        register_setting('wcl_settings', 'wcl_yearly_price_id_live');
        add_settings_field(
            'wcl_yearly_price_id_live',
            __('Yearly Price ID (Live)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_price_id_live', 'placeholder' => 'price_xxx', 'class' => 'regular-text stripe-live-field')
        );

        // Yearly Original Price (for strikethrough)
        register_setting('wcl_settings', 'wcl_yearly_original_price');
        add_settings_field(
            'wcl_yearly_original_price',
            __('Yearly Original Price', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_original_price', 'placeholder' => '$105', 'class' => 'small-text', 'description' => __('Displayed with strikethrough', 'wp-content-locker'))
        );

        // Yearly Display Price
        register_setting('wcl_settings', 'wcl_yearly_price', array('sanitize_callback' => 'sanitize_text_field'));
        add_settings_field(
            'wcl_yearly_price',
            __('Yearly Display Price (optional)', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_price', 'placeholder' => '99.99', 'class' => 'small-text', 'description' => __('Fallback if Stripe price cannot be fetched', 'wp-content-locker'))
        );

        // Display Settings Section
        add_settings_section(
            'wcl_display_section',
            __('Display Settings', 'wp-content-locker'),
            null,
            'wp-content-locker'
        );

        // Default Paywall Mode
        register_setting('wcl_settings', 'wcl_default_paywall_mode');
        add_settings_field(
            'wcl_default_paywall_mode',
            __('Default Paywall Mode', 'wp-content-locker'),
            array($this, 'render_paywall_mode_field'),
            'wp-content-locker',
            'wcl_display_section'
        );

        // Preview Percentage
        register_setting('wcl_settings', 'wcl_preview_percentage', array('sanitize_callback' => 'absint'));
        add_settings_field(
            'wcl_preview_percentage',
            __('Preview Percentage', 'wp-content-locker'),
            array($this, 'render_number_field'),
            'wp-content-locker',
            'wcl_display_section',
            array('name' => 'wcl_preview_percentage', 'min' => 10, 'max' => 90, 'default' => 30)
        );

        // Paywall Title
        register_setting('wcl_settings', 'wcl_paywall_title');
        add_settings_field(
            'wcl_paywall_title',
            __('Paywall Title', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_display_section',
            array('name' => 'wcl_paywall_title', 'class' => 'regular-text')
        );

        // Paywall Description
        register_setting('wcl_settings', 'wcl_paywall_description');
        add_settings_field(
            'wcl_paywall_description',
            __('Paywall Description', 'wp-content-locker'),
            array($this, 'render_textarea_field'),
            'wp-content-locker',
            'wcl_display_section',
            array('name' => 'wcl_paywall_description')
        );

        // Subscribe Button Text
        register_setting('wcl_settings', 'wcl_subscribe_button_text');
        add_settings_field(
            'wcl_subscribe_button_text',
            __('Subscribe Button Text', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_display_section',
            array('name' => 'wcl_subscribe_button_text', 'class' => 'regular-text')
        );

        // Email Settings Section
        add_settings_section(
            'wcl_email_section',
            __('Email Settings', 'wp-content-locker'),
            null,
            'wp-content-locker'
        );

        // Sender Name
        register_setting('wcl_settings', 'wcl_email_sender_name');
        add_settings_field(
            'wcl_email_sender_name',
            __('Sender Name', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_email_section',
            array(
                'name' => 'wcl_email_sender_name', 
                'class' => 'regular-text',
                'placeholder' => get_bloginfo('name'),
                'description' => __('Leave empty to use Site Title', 'wp-content-locker')
            )
        );

        // Sender Email
        register_setting('wcl_settings', 'wcl_email_sender_address');
        add_settings_field(
            'wcl_email_sender_address',
            __('Sender Email Address', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_email_section',
            array(
                'name' => 'wcl_email_sender_address', 
                'class' => 'regular-text',
                'placeholder' => get_option('admin_email'),
                'description' => __('Leave empty to use Admin Email', 'wp-content-locker')
            )
        );

        // System Status Section
        add_settings_section(
            'wcl_system_status_section',
            __('System Status', 'wp-content-locker'),
            array($this, 'render_system_status_section'),
            'wp-content-locker'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show settings saved message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('wcl_messages', 'wcl_message', __('Settings Saved', 'wp-content-locker'), 'updated');
        }

        $webhook_url = rest_url('wp-content-locker/v1/webhook');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('wcl_messages'); ?>

            <div class="wcl-admin-notice">
                <p><strong><?php _e('Webhook URL:', 'wp-content-locker'); ?></strong></p>
                <code><?php echo esc_url($webhook_url); ?></code>
                <p class="description"><?php _e('Add this URL to your Stripe webhook settings. Required events: checkout.session.completed, customer.subscription.updated, customer.subscription.deleted, invoice.payment_failed', 'wp-content-locker'); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('wcl_settings');
                do_settings_sections('wp-content-locker');
                submit_button(__('Save Settings', 'wp-content-locker'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Stripe section description
     */
    public function render_stripe_section() {
        echo '<p>' . __('Configure your Stripe API keys. Get these from your Stripe Dashboard.', 'wp-content-locker') . '</p>';
    }

    /**
     * Render pricing section description
     */
    public function render_pricing_section() {
        echo '<p>' . __('Enter the Stripe Price IDs for your subscription plans. Create these in your Stripe Dashboard under Products.', 'wp-content-locker') . '</p>';
        echo '<p class="description" style="color:#0073aa;"><strong>' . __('Note: Display prices below are optional. When Price IDs are configured, actual prices are automatically fetched from Stripe.', 'wp-content-locker') . '</strong></p>';

        // Show current Stripe prices if available
        $stripe = WCL_Stripe::get_instance();
        // Use the new helper methods to get the ID for the CURRENT mode
        $monthly_price_id = $stripe->get_monthly_price_id();
        $yearly_price_id = $stripe->get_yearly_price_id();

        if (!empty($monthly_price_id) || !empty($yearly_price_id)) {
            $mode = get_option('wcl_stripe_mode', 'test');
            echo '<div style="background:#f0f6fc;border:1px solid #c3c4c7;padding:10px 15px;margin:10px 0;border-radius:4px;">';
            echo '<strong>' . sprintf(__('Current Stripe Prices (%s mode):', 'wp-content-locker'), ucfirst($mode)) . '</strong><br>';

            if (!empty($monthly_price_id)) {
                $monthly_formatted = $stripe->get_formatted_price($monthly_price_id);
                if (!empty($monthly_formatted)) {
                    echo sprintf(__('Monthly: %s', 'wp-content-locker'), '<code>' . esc_html($monthly_formatted) . '</code>') . '<br>';
                }
            }

            if (!empty($yearly_price_id)) {
                $yearly_formatted = $stripe->get_formatted_price($yearly_price_id);
                if (!empty($yearly_formatted)) {
                    echo sprintf(__('Yearly: %s', 'wp-content-locker'), '<code>' . esc_html($yearly_formatted) . '</code>');
                }
            }

            echo '</div>';
        }
    }

    /**
     * Render System Status section
     */
    public function render_system_status_section() {
        echo '<p>' . __('Diagnostic information about your environment.', 'wp-content-locker') . '</p>';
        
        $dom_ok = class_exists('DOMDocument');
        $mb_ok = function_exists('mb_strlen');
        $stripe = WCL_Stripe::get_instance();
        $mode = get_option('wcl_stripe_mode', 'test');
        $sk = ($mode === 'live') ? get_option('wcl_stripe_live_secret_key') : get_option('wcl_stripe_test_secret_key');
        
        echo '<table class="widefat striped" style="max-width: 600px; margin-top: 10px;">';
        
        // PHP Extensions
        echo '<tr><td><strong>' . __('PHP DOM Extension', 'wp-content-locker') . '</strong></td><td>' . ($dom_ok ? '<span style="color:green;">✔ ' . __('Enabled', 'wp-content-locker') . '</span>' : '<span style="color:red;">✘ ' . __('Missing (Critical)', 'wp-content-locker') . '</span>') . '</td></tr>';
        echo '<tr><td><strong>' . __('PHP Multibyte String', 'wp-content-locker') . '</strong></td><td>' . ($mb_ok ? '<span style="color:green;">✔ ' . __('Enabled', 'wp-content-locker') . '</span>' : '<span style="color:red;">✘ ' . __('Missing (Critical)', 'wp-content-locker') . '</span>') . '</td></tr>';
        
        // Stripe Connectivity
        $stripe_status = __('Not Checked', 'wp-content-locker');
        if (!empty($sk)) {
            $response = $stripe->api_request('/balance');
            if (is_wp_error($response)) {
                $stripe_status = '<span style="color:red;">✘ ' . __('Connection Error:', 'wp-content-locker') . ' ' . esc_html($response->get_error_message()) . '</span>';
            } else {
                $stripe_status = '<span style="color:green;">✔ ' . __('Connected', 'wp-content-locker') . '</span>';
            }
        } else {
            $stripe_status = '<em>' . __('Keys not configured', 'wp-content-locker') . '</em>';
        }
        echo '<tr><td><strong>' . __('Stripe Connectivity', 'wp-content-locker') . '</strong></td><td>' . $stripe_status . '</td></tr>';
        
        // WP Version
        global $wp_version;
        echo '<tr><td><strong>' . __('WordPress Version', 'wp-content-locker') . '</strong></td><td>' . esc_html($wp_version) . '</td></tr>';
        
        echo '</table>';
    }

    /**
     * Render Stripe mode field
     */
    public function render_stripe_mode_field() {
        $value = get_option('wcl_stripe_mode', 'test');
        ?>
        <select name="wcl_stripe_mode" id="wcl_stripe_mode">
            <option value="test" <?php selected($value, 'test'); ?>><?php _e('Test Mode', 'wp-content-locker'); ?></option>
            <option value="live" <?php selected($value, 'live'); ?>><?php _e('Live Mode', 'wp-content-locker'); ?></option>
        </select>
        <p class="description"><?php _e('Use Test mode for development, Live mode for production.', 'wp-content-locker'); ?></p>
        <?php
    }

    /**
     * Render paywall mode field
     */
    public function render_paywall_mode_field() {
        $value = get_option('wcl_default_paywall_mode', 'disabled');
        ?>
        <select name="wcl_default_paywall_mode" id="wcl_default_paywall_mode">
            <option value="disabled" <?php selected($value, 'disabled'); ?>><?php _e('Disabled by default (enable per post)', 'wp-content-locker'); ?></option>
            <option value="enabled" <?php selected($value, 'enabled'); ?>><?php _e('Enabled by default (disable per post)', 'wp-content-locker'); ?></option>
        </select>
        <p class="description"><?php _e('Choose whether paywall is enabled or disabled by default for all posts. You can override this setting on individual posts.', 'wp-content-locker'); ?></p>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');
        $type = isset($args['type']) ? $args['type'] : 'text';
        $class = isset($args['class']) ? $args['class'] : 'regular-text';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $description = isset($args['description']) ? $args['description'] : '';

        printf(
            '<input type="%s" name="%s" id="%s" value="%s" class="%s" placeholder="%s" />',
            esc_attr($type),
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($class),
            esc_attr($placeholder)
        );

        if (!empty($description)) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    /**
     * Render number field
     */
    public function render_number_field($args) {
        $name = $args['name'];
        $value = get_option($name, isset($args['default']) ? $args['default'] : '');
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 100;

        printf(
            '<input type="number" name="%s" id="%s" value="%s" min="%d" max="%d" class="small-text" /> %%',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            $min,
            $max
        );
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($args) {
        $name = $args['name'];
        $value = get_option($name, '');

        printf(
            '<textarea name="%s" id="%s" rows="3" class="large-text">%s</textarea>',
            esc_attr($name),
            esc_attr($name),
            esc_textarea($value)
        );
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if ('settings_page_wp-content-locker' !== $hook) {
            return;
        }
        wp_enqueue_style('wcl-admin', WCL_PLUGIN_URL . 'admin/css/admin.css', array(), file_exists(WCL_PLUGIN_DIR . 'admin/css/admin.css') ? filemtime(WCL_PLUGIN_DIR . 'admin/css/admin.css') : WCL_VERSION);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ('settings_page_wp-content-locker' !== $hook) {
            return;
        }
        wp_enqueue_script('wcl-admin', WCL_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), file_exists(WCL_PLUGIN_DIR . 'admin/js/admin.js') ? filemtime(WCL_PLUGIN_DIR . 'admin/js/admin.js') : WCL_VERSION, true);
    }

    /**
     * Render subscriptions page
     */
    /**
     * Render subscriptions page
     */
    public function render_subscriptions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once WCL_PLUGIN_DIR . 'admin/class-wcl-subscriptions-list-table.php';


        // Handle manual addition
        if (isset($_POST['wcl_action']) && $_POST['wcl_action'] === 'add_manual_subscription') {
            check_admin_referer('wcl_add_manual_subscription');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-locker'));
            }

            $email = sanitize_email($_POST['user_email']);
            $name = sanitize_text_field($_POST['user_name']);
            $plan_type = sanitize_text_field($_POST['plan_type']);
            $duration_val = intval($_POST['duration_value']);
            $duration_unit = sanitize_text_field($_POST['duration_unit']);

            if (empty($email)) {
                add_settings_error('wcl_messages', 'wcl_error', __('Email is required.', 'wp-content-locker'), 'error');
            } else {
                $user_result = WCL_User::get_or_create_user($email, $name);
                
                if (is_wp_error($user_result)) {
                    add_settings_error('wcl_messages', 'wcl_error', $user_result->get_error_message(), 'error');
                } else {
                    $user_id = is_array($user_result) ? $user_result['user_id'] : $user_result;
                    
                    $start_date = current_time('mysql');
                    $end_date = date('Y-m-d H:i:s', strtotime("+$duration_val $duration_unit", current_time('timestamp')));

                    $subscription_data = array(
                        'user_id'                => $user_id,
                        'stripe_customer_id'     => 'manual_' . time(),
                        'stripe_subscription_id' => 'manual_sub_' . time(),
                        'plan_type'              => $plan_type,
                        'mode'                   => 'live',
                        'status'                 => 'active',
                        'current_period_start'   => $start_date,
                        'current_period_end'     => $end_date,
                    );

                    $result = WCL_Subscription::create_subscription($subscription_data);
                    
                    if (is_wp_error($result)) {
                        add_settings_error('wcl_messages', 'wcl_error', $result->get_error_message(), 'error');
                    } else {
                        update_user_meta($user_id, '_wcl_subscription_status', 'active');
                        
                        // Prepare and send email notification
                        $plan_name = ($plan_type === 'yearly') ? __('Yearly Subscription', 'wp-content-locker') : __('Monthly Subscription', 'wp-content-locker');
                        $email_data = array(
                            'user_id' => $user_id,
                            'password' => is_array($user_result) ? $user_result['password'] : null,
                            'new_user' => is_array($user_result) ? $user_result['new_user'] : false,
                            'plan_name' => $plan_name,
                            'plan_type' => $plan_type,
                            'amount' => __('$0.00 (Manual)', 'wp-content-locker'),
                            'tax' => '',
                            'subtotal' => '',
                            'post_url' => home_url(),
                            'invoice_pdf' => ''
                        );
                        WCL_User::send_subscription_email($email_data);

                        add_settings_error('wcl_messages', 'wcl_success', sprintf(__('Manual subscription added for %s until %s. Email notification sent.', 'wp-content-locker'), $email, $end_date), 'updated');
                    }
                }
            }
        }

        // Handle deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            check_admin_referer('wcl_delete_subscription');
            $id = absint($_GET['id']);
            
            $result = WCL_Subscription::delete_subscription($id);
            if (is_wp_error($result)) {
                add_settings_error('wcl_messages', 'wcl_error', $result->get_error_message(), 'error');
            } else {
                add_settings_error('wcl_messages', 'wcl_success', __('Subscription deleted.', 'wp-content-locker'), 'updated');
            }
        }

        $list_table = new WCL_Subscriptions_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'wp-content-locker'); ?></h1>
            <?php settings_errors('wcl_messages'); ?>

            <div class="card" style="max-width: 100%; margin-bottom: 20px;">
                <h2 class="title"><?php _e('Add Manual Subscription', 'wp-content-locker'); ?></h2>
                <p class="description"><?php _e('Quickly grant access to a user without Stripe payment.', 'wp-content-locker'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('wcl_add_manual_subscription'); ?>
                    <input type="hidden" name="wcl_action" value="add_manual_subscription">
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="user_email"><?php _e('User Email', 'wp-content-locker'); ?></label></th>
                            <td><input name="user_email" type="email" id="user_email" value="" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="user_name"><?php _e('User Name (Optional)', 'wp-content-locker'); ?></label></th>
                            <td><input name="user_name" type="text" id="user_name" value="" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="plan_type"><?php _e('Plan Type', 'wp-content-locker'); ?></label></th>
                            <td>
                                <select name="plan_type" id="plan_type">
                                    <option value="monthly"><?php _e('Monthly', 'wp-content-locker'); ?></option>
                                    <option value="yearly" selected><?php _e('Yearly', 'wp-content-locker'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="duration_value"><?php _e('Duration', 'wp-content-locker'); ?></label></th>
                            <td>
                                <input name="duration_value" type="number" id="duration_value" value="1" class="small-text" min="1">
                                <select name="duration_unit" id="duration_unit">
                                    <option value="days"><?php _e('Day(s)', 'wp-content-locker'); ?></option>
                                    <option value="months"><?php _e('Month(s)', 'wp-content-locker'); ?></option>
                                    <option value="years" selected><?php _e('Year(s)', 'wp-content-locker'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Add Subscription', 'wp-content-locker'), 'primary', 'submit', false); ?>
                </form>
            </div>
            
            
            <form method="get">
                <input type="hidden" name="page" value="wcl-subscriptions" />
                <?php
                $list_table->search_box(__('Search Subscriptions', 'wp-content-locker'), 'subscription-search');
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';

        // Handle Clear Log action
        if (isset($_POST['wcl_action']) && $_POST['wcl_action'] === 'clear_log') {
            check_admin_referer('wcl_clear_log');
            file_put_contents($log_file, '');
            add_settings_error('wcl_messages', 'wcl_success', __('Log file cleared.', 'wp-content-locker'), 'updated');
        }

        // Handle Test Log action
        if (isset($_POST['wcl_action']) && $_POST['wcl_action'] === 'test_log') {
            check_admin_referer('wcl_test_log');
            error_log('WCL: Test Log Entry generated at ' . date('Y-m-d H:i:s'));
            add_settings_error('wcl_messages', 'wcl_success', __('Test log entry generated. Check if it appears below.', 'wp-content-locker'), 'updated');
        }

        // Check if debug logging is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            add_settings_error('wcl_messages', 'wcl_warning', __('WP_DEBUG and WP_DEBUG_LOG must be enabled in wp-config.php for logging to work.', 'wp-content-locker'), 'warning');
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Debug Logs', 'wp-content-locker'); ?></h1>
            <?php settings_errors('wcl_messages'); ?>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2 class="title"><?php _e('Log Viewer', 'wp-content-locker'); ?></h2>
                <p><?php printf(__('Reading from: %s', 'wp-content-locker'), '<code>' . esc_html($log_file) . '</code>'); ?></p>

                <div style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 15px; height: 500px; overflow-y: scroll; font-family: monospace; white-space: pre-wrap; margin-bottom: 20px;">
                    <?php
                    if (file_exists($log_file)) {
                        $content = file_get_contents($log_file);
                        if (empty($content)) {
                            echo '<em>' . __('Log file is empty.', 'wp-content-locker') . '</em>';
                        } else {
                            // Show last 20000 characters to avoid memory issues with huge logs
                            if (strlen($content) > 20000) {
                                $content = '... ' . substr($content, -20000);
                            }
                            echo esc_html($content);
                        }
                    } else {
                        echo '<em>' . __('Log file not found.', 'wp-content-locker') . '</em>';
                    }
                    ?>
                </div>

                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('wcl_clear_log'); ?>
                    <input type="hidden" name="wcl_action" value="clear_log">
                    <?php submit_button(__('Clear Log', 'wp-content-locker'), 'secondary', 'submit', false); ?>
                </form>
                
                <form method="post" style="display:inline; margin-left: 10px;">
                    <?php wp_nonce_field('wcl_test_log'); ?>
                    <input type="hidden" name="wcl_action" value="test_log">
                    <?php submit_button(__('Generate Test Log', 'wp-content-locker'), 'primary', 'submit', false); ?>
                </form>

                <a href="<?php echo esc_url(add_query_arg(array())); ?>" class="button" style="margin-left: 10px;"><?php _e('Refresh', 'wp-content-locker'); ?></a>
            </div>
        </div>
        <?php
    }
}
}
