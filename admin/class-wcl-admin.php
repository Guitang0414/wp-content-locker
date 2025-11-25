<?php
/**
 * Admin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

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

        // Webhook Secret
        register_setting('wcl_settings', 'wcl_stripe_webhook_secret');
        add_settings_field(
            'wcl_stripe_webhook_secret',
            __('Webhook Secret', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_stripe_section',
            array('name' => 'wcl_stripe_webhook_secret', 'type' => 'password', 'class' => 'regular-text')
        );

        // Price IDs Section
        add_settings_section(
            'wcl_pricing_section',
            __('Subscription Plans', 'wp-content-locker'),
            array($this, 'render_pricing_section'),
            'wp-content-locker'
        );

        // Monthly Price ID
        register_setting('wcl_settings', 'wcl_monthly_price_id');
        add_settings_field(
            'wcl_monthly_price_id',
            __('Monthly Price ID', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_price_id', 'placeholder' => 'price_xxx', 'class' => 'regular-text')
        );

        // Monthly Display Price
        register_setting('wcl_settings', 'wcl_monthly_price');
        add_settings_field(
            'wcl_monthly_price',
            __('Monthly Display Price', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_monthly_price', 'placeholder' => '9.99', 'class' => 'small-text')
        );

        // Yearly Price ID
        register_setting('wcl_settings', 'wcl_yearly_price_id');
        add_settings_field(
            'wcl_yearly_price_id',
            __('Yearly Price ID', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_price_id', 'placeholder' => 'price_xxx', 'class' => 'regular-text')
        );

        // Yearly Display Price
        register_setting('wcl_settings', 'wcl_yearly_price');
        add_settings_field(
            'wcl_yearly_price',
            __('Yearly Display Price', 'wp-content-locker'),
            array($this, 'render_text_field'),
            'wp-content-locker',
            'wcl_pricing_section',
            array('name' => 'wcl_yearly_price', 'placeholder' => '99.99', 'class' => 'small-text')
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
        register_setting('wcl_settings', 'wcl_preview_percentage');
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

        printf(
            '<input type="%s" name="%s" id="%s" value="%s" class="%s" placeholder="%s" />',
            esc_attr($type),
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($class),
            esc_attr($placeholder)
        );
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
        wp_enqueue_style('wcl-admin', WCL_PLUGIN_URL . 'admin/css/admin.css', array(), WCL_VERSION);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ('settings_page_wp-content-locker' !== $hook) {
            return;
        }
        wp_enqueue_script('wcl-admin', WCL_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), WCL_VERSION, true);
    }
}
