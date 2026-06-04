<?php
/**
 * Safety net against accidental wcl_* option wipes.
 *
 * Background: WordPress's wp-admin/options.php iterates every option registered
 * under the submitted option_group. For any option whose field is NOT present
 * in $_POST, it calls update_option($option, null). The old "one form per tab"
 * design caused exactly this — saving on the Stripe tab silently wiped Pricing
 * / Display / Email / Bot Protection. (2026-06-04 incident.)
 *
 * This guard short-circuits that pattern: during a form submission to
 * options.php, if a wcl_* option's field is missing from $_POST, we preserve
 * the existing value instead of wiping it.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Options_Guard {

    public static function register() {
        add_filter('pre_update_option', array(__CLASS__, 'preserve_unsubmitted'), 10, 3);
    }

    /**
     * Return the old value when a wcl_* option is being updated during an
     * options.php form submission but its field isn't in $_POST.
     */
    public static function preserve_unsubmitted($value, $option, $old_value) {
        // Only protect our own options.
        if (strpos($option, 'wcl_') !== 0) {
            return $value;
        }

        // Only intervene during an actual wp-admin options form submission.
        // Other contexts (WP-CLI, programmatic updates, REST API) pass through untouched.
        if (!is_admin()) {
            return $value;
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
        if ($script !== 'options.php') {
            return $value;
        }
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'wcl_settings') {
            return $value;
        }

        // If the field wasn't submitted, preserve the existing value.
        // (POST-present-but-empty is fine — that's a legit user clearing.)
        if (!isset($_POST[$option])) {
            return $old_value;
        }

        return $value;
    }
}
