<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Activator {

    /**
     * Run on plugin activation
     */
    public static function activate() {
        self::create_subscriber_role();
        self::create_database_tables();
        self::set_default_options();
        flush_rewrite_rules();
    }

    /**
     * Create subscriber role if not exists
     */
    private static function create_subscriber_role() {
        // WordPress already has a 'subscriber' role, we'll use it
        // Just ensure it exists
        $role = get_role('subscriber');
        if (!$role) {
            add_role('subscriber', __('Subscriber', 'wp-content-locker'), array(
                'read' => true,
            ));
        }
    }

    /**
     * Create database tables for subscription tracking
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wcl_subscriptions';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            stripe_customer_id varchar(255) NOT NULL,
            stripe_subscription_id varchar(255) NOT NULL,
            plan_type varchar(50) NOT NULL,
            mode varchar(10) DEFAULT 'test',
            status varchar(50) NOT NULL DEFAULT 'active',
            current_period_start datetime DEFAULT NULL,
            current_period_end datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY stripe_customer_id (stripe_customer_id),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'wcl_stripe_mode' => 'test',
            'wcl_stripe_test_publishable_key' => '',
            'wcl_stripe_test_secret_key' => '',
            'wcl_stripe_live_publishable_key' => '',
            'wcl_stripe_live_secret_key' => '',
            'wcl_stripe_webhook_secret' => '',
            'wcl_monthly_price_id' => '',
            'wcl_yearly_price_id' => '',
            'wcl_monthly_price' => '9.99',
            'wcl_yearly_price' => '99.99',
            'wcl_default_paywall_mode' => 'disabled',
            'wcl_preview_percentage' => 30,
            'wcl_paywall_title' => __('Premium Content', 'wp-content-locker'),
            'wcl_paywall_description' => __('Subscribe to read the full article and get access to all premium content.', 'wp-content-locker'),
            'wcl_subscribe_button_text' => __('Subscribe Now', 'wp-content-locker'),
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
