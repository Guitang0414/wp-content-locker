<?php
/**
 * Plugin deactivation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCL_Deactivator')) {
    class WCL_Deactivator {

    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
        // Note: We don't delete database tables or options on deactivation
        // to preserve user data. Use uninstall.php for complete cleanup.
    }
}
}
