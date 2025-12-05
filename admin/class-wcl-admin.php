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

        // Add Subscriptions submenu
        add_submenu_page(
            'wp-content-locker',
            __('Subscriptions', 'wp-content-locker'),
            __('Subscriptions', 'wp-content-locker'),
            'manage_options',
            'wcl-subscriptions',
            array($this, 'render_subscriptions_page')
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
        register_setting('wcl_settings', 'wcl_monthly_price');
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
        register_setting('wcl_settings', 'wcl_yearly_price');
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

    /**
     * Render subscriptions page
     */
    public function render_subscriptions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_subscriptions';
        
        // Handle search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = '';
        if ($search) {
            $where = $wpdb->prepare(
                "WHERE stripe_customer_id LIKE %s OR stripe_subscription_id LIKE %s OR user_id IN (SELECT ID FROM {$wpdb->users} WHERE user_email LIKE %s OR user_login LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        $total_pages = ceil($total_items / $per_page);

        $subscriptions = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset)
        );

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'wp-content-locker'); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="wcl-subscriptions" />
                <?php
                $search_box = new WP_List_Table(array('screen' => 'wcl-subscriptions'));
                $search_box->search_box(__('Search Subscriptions', 'wp-content-locker'), 'subscription-search');
                ?>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Ends</th>
                        <th>Stripe ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)) : ?>
                        <tr>
                            <td colspan="7"><?php _e('No subscriptions found.', 'wp-content-locker'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($subscriptions as $sub) : 
                            $user = get_user_by('id', $sub->user_id);
                            $user_link = $user ? sprintf('<a href="%s">%s</a><br><span class="email">%s</span>', get_edit_user_link($user->ID), esc_html($user->display_name), esc_html($user->user_email)) : __('Unknown User', 'wp-content-locker');
                            
                            $status_class = 'status-' . $sub->status;
                            $status_label = WCL_Subscription::get_status_label($sub->status);
                            
                            // Style status
                            $status_style = '';
                            if ($sub->status === 'active') $status_style = 'color:green;font-weight:bold;';
                            elseif ($sub->status === 'canceled') $status_style = 'color:red;';
                            elseif ($sub->status === 'past_due') $status_style = 'color:orange;';
                        ?>
                            <tr>
                                <td><?php echo esc_html($sub->id); ?></td>
                                <td><?php echo $user_link; ?></td>
                                <td><?php echo esc_html(ucfirst($sub->plan_type)); ?></td>
                                <td><span style="<?php echo esc_attr($status_style); ?>"><?php echo esc_html($status_label); ?></span></td>
                                <td><?php echo esc_html($sub->current_period_start); ?></td>
                                <td><?php echo esc_html($sub->current_period_end); ?></td>
                                <td><code><?php echo esc_html($sub->stripe_subscription_id); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf(__('%d items', 'wp-content-locker'), $total_items); ?></span>
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
