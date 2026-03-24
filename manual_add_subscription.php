<?php
/**
 * WP Content Locker - Manual Subscription Utility
 * 
 * Usage: 
 * 1. Upload this file to your WordPress root directory (where wp-config.php is).
 * 2. Edit the $user_email and $user_name variables below.
 * 3. Access this file in your browser (e.g., https://yourdomain.com/manual_add_subscription.php).
 * 4. DELETE THIS FILE IMMEDIATELY AFTER USE.
 */

// Try to load WordPress
// If placed in WP root:
if (file_exists('wp-load.php')) {
    require_once('wp-load.php');
} 
// If placed in plugin directory:
elseif (file_exists('../../../wp-load.php')) {
    require_once('../../../wp-load.php');
}
else {
    die('Error: wp-load.php not found. Please place this script in your WordPress root directory.');
}

// --- CONFIGURATION ---
$user_email = 'user@example.com'; // Change this
$user_name  = 'Manual User';       // Change this
$plan_type  = 'yearly';            // 'monthly' or 'yearly'
$duration   = '+1 year';           // e.g., '+1 month', '+1 year'
// --- END CONFIGURATION ---

// Ensure plugin classes are exists
if (!class_exists('WCL_User') || !class_exists('WCL_Subscription')) {
    die('Error: WP Content Locker plugin is not active or its core files are missing.');
}

echo "<h2>WP Content Locker - Manual Subscription Utility</h2>";

// 1. Get or Create User
$user_result = WCL_User::get_or_create_user($user_email, $user_name);

if (is_wp_error($user_result)) {
    die('<p style="color:red;">Error creating/finding user: ' . $user_result->get_error_message() . '</p>');
}

$user_id = is_array($user_result) ? $user_result['user_id'] : $user_result;
$is_new = is_array($user_result) && $user_result['new_user'];

echo "<p>User identified: <strong>#$user_id ($user_email)</strong>" . ($is_new ? " (Newly Created)" : " (Existing Account)") . "</p>";

// 2. Prepare Subscription Data
$start_date = current_time('mysql');
$end_date   = date('Y-m-d H:i:s', strtotime($duration, current_time('timestamp')));

$subscription_data = array(
    'user_id'                => $user_id,
    'stripe_customer_id'     => 'manual_' . time(),
    'stripe_subscription_id' => 'manual_sub_' . time(),
    'plan_type'              => $plan_type,
    'mode'                   => 'live', // Use 'live' to ensure access in production
    'status'                 => 'active',
    'current_period_start'   => $start_date,
    'current_period_end'     => $end_date,
);

// 3. Create/Update Subscription Record
$result = WCL_Subscription::create_subscription($subscription_data);

if (is_wp_error($result)) {
    die('<p style="color:red;">Error creating subscription record: ' . $result->get_error_message() . '</p>');
}

// 4. Force status meta (redundant but helps with some checks)
update_user_meta($user_id, '_wcl_subscription_status', 'active');

echo "<p style='color:green; font-weight:bold;'>Success!</p>";
echo "<ul>";
echo "<li><strong>Access Granted until:</strong> $end_date</li>";
echo "<li><strong>Plan Type:</strong> " . ucfirst($plan_type) . "</li>";
echo "<li><strong>Role:</strong> Subscriber</li>";
echo "</ul>";

echo "<p style='background:#fff3cd; padding:10px; border:1px solid #ffeeba;'><strong>SECURITY WARNING:</strong> Please delete this file (<code>" . basename(__FILE__) . "</code>) from your server immediately!</p>";
