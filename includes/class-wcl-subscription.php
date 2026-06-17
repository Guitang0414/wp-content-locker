<?php
/**
 * Subscription management class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCL_Subscription')) {
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
    public static function has_active_subscription($user_id, $mode = null) {
        global $wpdb;

        // Use UTC time because Stripe timestamps are converted to UTC before saving
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT COUNT(*) FROM " . self::get_table_name() . "
             WHERE user_id = %d
             AND (
                 (status IN ('active', 'canceling') AND (current_period_end IS NULL OR current_period_end > %s))
                 OR
                 (status = 'canceled' AND current_period_end > %s)
             )";
        
        $params = array($user_id, $now, $now);

        if ($mode) {
            $sql .= " AND (mode = %s OR stripe_subscription_id LIKE 'manual_%%')";
            $params[] = $mode;
        }

        $count = $wpdb->get_var($wpdb->prepare($sql, $params));

        return $count > 0;
    }

    /**
     * Get active subscription for user
     */
    public static function get_active_subscription($user_id, $mode = null) {
        global $wpdb;

        // Use UTC time because Stripe timestamps are converted to UTC before saving
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM " . self::get_table_name() . "
             WHERE user_id = %d
             AND (
                 (status IN ('active', 'canceling') AND (current_period_end IS NULL OR current_period_end > %s))
                 OR
                 (status = 'canceled' AND current_period_end > %s)
             )";
        
        $params = array($user_id, $now, $now);

        if ($mode) {
            $sql .= " AND (mode = %s OR stripe_subscription_id LIKE 'manual_%%')";
            $params[] = $mode;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    /**
     * Cancel subscription
     */
    public static function cancel_subscription($subscription_id, $at_period_end = true) {
        $subscription = self::get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found.', 'wp-content-locker'));
        }

        // Check if it's a manual subscription (doesn't exist in Stripe)
        $is_manual = (strpos($subscription->stripe_subscription_id, 'manual_') === 0);

        if (!$is_manual) {
            // Cancel in Stripe
            $stripe = WCL_Stripe::get_instance();
            $result = $stripe->cancel_subscription($subscription->stripe_subscription_id, $at_period_end);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update local record
        $status = $at_period_end ? 'canceling' : 'canceled';
        self::update_subscription($subscription_id, array('status' => $status));

        // Update user meta
        update_user_meta($subscription->user_id, '_wcl_subscription_status', $status);

        return true;
    }

    /**
     * Resume subscription
     */
    public static function resume_subscription($subscription_id) {
        $subscription = self::get_subscription($subscription_id);
        if (!$subscription) {
            return new WP_Error('not_found', __('Subscription not found.', 'wp-content-locker'));
        }

        // Resume in Stripe
        $stripe = WCL_Stripe::get_instance();
        $result = $stripe->resume_subscription($subscription->stripe_subscription_id);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update local record
        $status = 'active';
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
            'expired' => __('Expired', 'wp-content-locker'),
            'inactive' => __('Inactive', 'wp-content-locker'),
        );

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Compute the EFFECTIVE status from the stored status + period end.
     *
     * The stored `status` column alone is misleading: a row can read 'active'
     * while its current_period_end has already lapsed (e.g. a missed renewal
     * webhook), so the admin shows green "Active" while the user is actually
     * locked out of content. This reconciles display with real access:
     *   - has access  -> 'active' (or 'canceling' if set to end at period end)
     *   - lapsed      -> 'expired'
     *   - otherwise   -> the underlying reason ('canceled' / 'past_due').
     *
     * Mirrors the access predicate in get_active_subscription().
     *
     * @param array|object $sub A subscription row.
     * @return string One of active|canceling|expired|canceled|past_due|inactive
     */
    public static function get_effective_status($sub) {
        $sub = (array) $sub;
        $status = isset($sub['status']) ? $sub['status'] : '';
        $end = isset($sub['current_period_end']) ? $sub['current_period_end'] : null;
        $now = gmdate('Y-m-d H:i:s');

        $has_future_end = (!empty($end) && $end > $now);
        // NULL end = ongoing/unknown, treated as still within period (matches access query).
        $end_ok = empty($end) || $has_future_end;

        if (in_array($status, array('active', 'canceling'), true) && $end_ok) {
            return $status;
        }
        // Canceled in Stripe but still inside the paid period -> still has access.
        if ($status === 'canceled' && $has_future_end) {
            return 'canceling';
        }
        // Was active/canceling but the period lapsed with no renewal.
        if (in_array($status, array('active', 'canceling'), true) && !$end_ok) {
            return 'expired';
        }
        return $status !== '' ? $status : 'inactive';
    }

    /**
     * Delete all subscriptions for a user when they are deleted from WordPress
     */
    public static function delete_by_user_id($user_id) {
        global $wpdb;
        $wpdb->delete(
            self::get_table_name(),
            array('user_id' => $user_id),
            array('%d')
        );
    }

    /**
     * Get statistics of paid subscriptions (excluding manual)
     */
    public static function get_paid_subscription_stats() {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        
        $sql = "SELECT plan_type, COUNT(*) as count 
                FROM " . self::get_table_name() . " 
                WHERE status IN ('active', 'canceling') 
                AND stripe_subscription_id NOT LIKE 'manual_%' 
                AND (current_period_end IS NULL OR current_period_end > %s)
                GROUP BY plan_type";
                
        $results = $wpdb->get_results($wpdb->prepare($sql, $now), ARRAY_A);
        
        $stats = array(
            'yearly' => 0,
            'monthly' => 0
        );
        
        if ($results) {
            foreach ($results as $row) {
                if (isset($stats[$row['plan_type']])) {
                    $stats[$row['plan_type']] = intval($row['count']);
                }
            }
        }
        
        return $stats;
    }
}
}
