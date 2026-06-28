<?php
/**
 * Restrict wp-admin and the admin toolbar to staff users.
 *
 * On a paywall site the vast majority of logged-in users are readers
 * (the `subscriber` role). WordPress, by default, still lets any logged-in
 * user reach a stripped-down /wp-admin/ dashboard and shows them the
 * front-end admin toolbar. To a paying reader that looks like they have
 * "backend access" they should not have.
 *
 * This locks both down to users who can actually edit content:
 *   - readers get the admin toolbar hidden on the front end, and
 *   - any attempt to load /wp-admin/ is redirected to their account page.
 *
 * AJAX (admin-ajax.php) and cron are left alone so the front-end account
 * page and background tasks keep working for readers.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCL_Admin_Access')) {
class WCL_Admin_Access {

    /**
     * Capability that distinguishes a "staff" user (contributor and up)
     * from a reader. Subscribers do not have it.
     */
    const STAFF_CAP = 'edit_posts';

    /**
     * Wire up the hooks.
     */
    public static function register() {
        add_action('after_setup_theme', array(__CLASS__, 'maybe_hide_admin_bar'));
        // Priority 1 so we redirect before other admin_init work runs.
        add_action('admin_init', array(__CLASS__, 'maybe_block_admin'), 1);
    }

    /**
     * Hide the front-end admin toolbar for readers.
     */
    public static function maybe_hide_admin_bar() {
        if (is_user_logged_in() && !current_user_can(self::STAFF_CAP)) {
            show_admin_bar(false);
        }
    }

    /**
     * Redirect readers away from /wp-admin/ to their account page.
     */
    public static function maybe_block_admin() {
        // Never interfere with AJAX or cron requests.
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        if (current_user_can(self::STAFF_CAP)) {
            return;
        }

        wp_safe_redirect(self::account_url());
        exit;
    }

    /**
     * Best-effort URL of the page holding the [wcl_account] shortcode,
     * falling back to the site home.
     *
     * @return string
     */
    private static function account_url() {
        global $wpdb;
        $account_page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE '%[wcl_account]%'
             AND post_status = 'publish' AND post_type = 'page' LIMIT 1"
        );
        if ($account_page_id) {
            return get_permalink($account_page_id);
        }
        return home_url('/');
    }
}
}
