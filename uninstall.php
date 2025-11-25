<?php
/**
 * Uninstall script - runs when plugin is deleted
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
$options = array(
    'wcl_stripe_mode',
    'wcl_stripe_test_publishable_key',
    'wcl_stripe_test_secret_key',
    'wcl_stripe_live_publishable_key',
    'wcl_stripe_live_secret_key',
    'wcl_stripe_webhook_secret',
    'wcl_monthly_price_id',
    'wcl_yearly_price_id',
    'wcl_monthly_price',
    'wcl_yearly_price',
    'wcl_preview_percentage',
    'wcl_paywall_title',
    'wcl_paywall_description',
    'wcl_subscribe_button_text',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete post meta
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wcl_%'");

// Delete user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_wcl_%'");

// Drop custom table
$table_name = $wpdb->prefix . 'wcl_subscriptions';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
