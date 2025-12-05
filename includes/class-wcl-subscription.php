<?php
/**
 * Subscription management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Subscription {

    /**
     * Get table name
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcl_subscriptions';
    }

    /**
     * Create a new subscription record
     */
    public static function create_subscription($data) {
        global $wpdb;

        $defaults = array(
            'user_id' => 0,
            'stripe_customer_id' => '',
            'stripe_subscription_id' => '',
            'plan_type' => 'monthly',
            'mode' => 'test',
            'status' => 'active',
            'current_period_start' => null,
            'current_period_end' => null,
        );

        $data = wp_parse_args($data, $defaults);

        // Check if subscription already exists
        $existing = self::get_by_stripe_subscription_id($data['stripe_subscription_id']);
        if ($existing) {
            return self::update_subscription($existing->id, $data);
        }

        $result = $wpdb->insert(
            self::get_table_name(),
            array(
                'user_id' => $data['user_id'],
                'stripe_customer_id' => $data['stripe_customer_id'],
                'stripe_subscription_id' => $data['stripe_subscription_id'],
                'plan_type' => $data['plan_type'],
                'mode' => $data['mode'],
                'status' => $data['status'],
                'current_period_start' => $data['current_period_start'],
                'current_period_end' => $data['current_period_end'],
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create subscription record.', 'wp-content-locker'));
        }

        // Update user role to subscriber
        $user = get_user_by('id', $data['user_id']);
        if ($user && $data['status'] === 'active') {
            $user->set_role('subscriber');
        }

        return $wpdb->insert_id;
    }

    /**
     * Update subscription by ID
     */
    public static function update_subscription($id, $data) {
        global $wpdb;

        $allowed_fields = array(
            'plan_type', 'status', 'current_period_start', 'current_period_end'
        );

        $update_data = array();
        $format = array();

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = '%s';
            }
        }

        if (empty($update_data)) {
            return true;
        }

        return $wpdb->update(
            self::get_table_name(),
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    /**
     * Update subscription by Stripe subscription ID
     */
    public static function update_subscription_by_stripe_id($stripe_subscription_id, $data) {
        $subscription = self::get_by_stripe_subscription_id($stripe_subscription_id);
        if (!$subscription) {
            return false;
        }
        return self::update_subscription($subscription->id, $data);
    }

    /**
     * Get subscription by ID
     */
    public static function get_subscription($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
            $id
        ));
    }

    /**
     * Get subscription by Stripe subscription ID
     */
    public static function get_by_stripe_subscription_id($stripe_subscription_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE stripe_subscription_id = %s",
            $stripe_subscription_id
        ));
    }

    /**
     * Get subscription by user ID
     */
    public static function get_by_user_id($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }

    /**
     * Get all subscriptions for a user
     */
    public static function get_all_by_user_id($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }

    /**
     * Check if user has an active subscription
     */
    public static function has_active_subscription($user_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::get_table_name() . "
             WHERE user_id = %d
             AND status IN ('active', 'canceling')
             AND (current_period_end IS NULL OR current_period_end > NOW())",
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Get active subscription for user
     */
    public static function get_active_subscription($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . "
             WHERE user_id = %d
             AND status IN ('active', 'canceling')
             AND (current_period_end IS NULL OR current_period_end > NOW())
             ORDER BY created_at DESC
             LIMIT 1",
            $user_id
        ));
    }

    /**
     * Cancel subscription
     */
    public static function cancel_subscription($subscription_id, $at_period_end = true) {
        $subscription = self::get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found.', 'wp-content-locker'));
        }

        // Cancel in Stripe
        $stripe = WCL_Stripe::get_instance();
        $result = $stripe->cancel_subscription($subscription->stripe_subscription_id, $at_period_end);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update local record
        $status = $at_period_end ? 'canceling' : 'canceled';
        self::update_subscription($subscription_id, array('status' => $status));

        // Update user meta
        update_user_meta($subscription->user_id, '_wcl_subscription_status', $status);

        return true;
    }

    /**
     * Delete subscription
     */
    public static function delete_subscription($subscription_id) {
        global $wpdb;

        $subscription = self::get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found.', 'wp-content-locker'));
        }

        $result = $wpdb->delete(
            self::get_table_name(),
            array('id' => $subscription_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete subscription.', 'wp-content-locker'));
        }

        // Clean up user meta if this was their active subscription
        $user_id = $subscription->user_id;
        $active_sub = self::get_active_subscription($user_id);
        if (!$active_sub) {
            delete_user_meta($user_id, '_wcl_subscription_status');
            delete_user_meta($user_id, '_wcl_stripe_customer_id');
            
            // Revert role if they have no other subscriptions
            $user = get_user_by('id', $user_id);
            if ($user && in_array('subscriber', (array) $user->roles)) {
                $user->remove_role('subscriber');
            }
        }

        return true;
    }

    /**
     * Get subscription status label
     */
    public static function get_status_label($status) {
        $labels = array(
            'active' => __('Active', 'wp-content-locker'),
            'canceling' => __('Canceling at period end', 'wp-content-locker'),
            'canceled' => __('Canceled', 'wp-content-locker'),
            'past_due' => __('Past due', 'wp-content-locker'),
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}
