<?php
/**
 * Paywall template - WSJ/WaPo style
 *
 * Variables available:
 * $title - Paywall title
 * $description - Paywall description
 * $button_text - Subscribe button text
 * $monthly_price - Monthly display price
 * $yearly_price - Yearly display price
 * $post_id - Current post ID
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get configurable values
$monthly_original = get_option('wcl_monthly_original_price', '$2');
$monthly_discounted = get_option('wcl_monthly_discounted_price', '50¢');
$monthly_desc = get_option('wcl_monthly_description', 'every week for first 3 months,<br>then $8.99 per month');

// Calculate yearly savings
$yearly_original_str = get_option('wcl_yearly_original_price', '');
$yearly_original_val = 0;
if (!empty($yearly_original_str)) {
    // Remove non-numeric characters except dot
    $yearly_original_val = floatval(preg_replace('/[^0-9.]/', '', $yearly_original_str));
}

$monthly_total = floatval($monthly_price) * 12;
$yearly_total = floatval($yearly_price);

// Use configured original price as base if available, otherwise use monthly * 12
$base_price = ($yearly_original_val > 0) ? $yearly_original_val : $monthly_total;
$savings_percent = $base_price > 0 ? round((($base_price - $yearly_total) / $base_price) * 100) : 0;

// Format yearly original price for display if not set
if (empty($yearly_original_str) && $monthly_total > 0) {
    $yearly_original_str = '$' . number_format($monthly_total, 2);
}

// Get site name for branding
$site_name = get_bloginfo('name');

// Get My Account page URL (fallback to login URL)
$account_page_url = '';
$account_page = get_pages(array(
    'post_status' => 'publish',
    'meta_key' => '_wp_page_template',
    'hierarchical' => 0,
));

// Try to find page with [wcl_account] shortcode
global $wpdb;
$account_page_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%[wcl_account]%' AND post_status = 'publish' AND post_type = 'page' LIMIT 1");
if ($account_page_id) {
    $account_page_url = get_permalink($account_page_id);
}

$login_url = !empty($account_page_url) ? $account_page_url : wp_login_url(get_permalink($post_id));
?>

<!-- Step 1: Simple Paywall CTA -->
<!-- Debug: WCL 1.1.0 Verified -->
<div class="wcl-paywall">
    <div class="wcl-paywall-brand">
        <?php echo esc_html($site_name); ?>
    </div>

    <h3 class="wcl-paywall-title"><?php echo esc_html($title); ?></h3>
    <p class="wcl-paywall-description"><?php echo esc_html($description); ?></p>

    <a href="<?php echo home_url('/subscribe/'); ?>" class="wcl-subscribe-btn">
        <?php echo esc_html($button_text); ?>
    </a>

    <p class="wcl-login-link">
        <?php _e('Already a subscriber?', 'wp-content-locker'); ?>
        <a href="<?php echo esc_url($login_url); ?>"><?php _e('Sign In', 'wp-content-locker'); ?></a>
    </p>
</div>


