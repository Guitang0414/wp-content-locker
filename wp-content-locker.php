<?php
/**
 * Plugin Name: WP Content Locker
 * Plugin URI: https://example.com/wp-content-locker
 * Description: Lock premium content behind a Stripe-powered subscription paywall. Show 30% preview with gradient fade for non-subscribers.
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-content-locker
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
if (!defined('WCL_VERSION')) {
    define('WCL_VERSION', '1.1.0');
}
if (!defined('WCL_PLUGIN_DIR')) {
    define('WCL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WCL_PLUGIN_URL')) {
    define('WCL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('WCL_PLUGIN_BASENAME')) {
    define('WCL_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/*
require_once WCL_PLUGIN_DIR . 'includes/class-wcl-activator.php';
require_once WCL_PLUGIN_DIR . 'includes/class-wcl-deactivator.php';

if (!class_exists('WP_Content_Locker')) {
... [rest of the class]
}

register_activation_hook(__FILE__, array('WP_Content_Locker', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Content_Locker', 'deactivate'));

function wcl_init() {
    return WP_Content_Locker::get_instance();
}
add_action('plugins_loaded', 'wcl_init');
*/
echo '<!-- WP Content Locker is temporarily disabled -->';
