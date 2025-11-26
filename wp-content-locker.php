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
define('WCL_VERSION', '1.1.2');
define('WCL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load activator early for activation hook
require_once WCL_PLUGIN_DIR . 'includes/class-wcl-activator.php';
require_once WCL_PLUGIN_DIR . 'includes/class-wcl-deactivator.php';

/**
 * Main plugin class
 */
class WP_Content_Locker {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes (activator and deactivator already loaded above)
        require_once WCL_PLUGIN_DIR . 'includes/class-wcl-content.php';
        require_once WCL_PLUGIN_DIR . 'includes/class-wcl-stripe.php';
        require_once WCL_PLUGIN_DIR . 'includes/class-wcl-subscription.php';
        require_once WCL_PLUGIN_DIR . 'includes/class-wcl-user.php';

        // Admin classes - metabox needs to be loaded always for save_post hook
        require_once WCL_PLUGIN_DIR . 'admin/class-wcl-metabox.php';
        if (is_admin()) {
            require_once WCL_PLUGIN_DIR . 'admin/class-wcl-admin.php';
        }

        // Public classes
        require_once WCL_PLUGIN_DIR . 'public/class-wcl-public.php';
        require_once WCL_PLUGIN_DIR . 'public/class-wcl-account.php';
    }

    /**
     * Set plugin locale for translations
     */
    private function set_locale() {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'wp-content-locker',
                false,
                dirname(WCL_PLUGIN_BASENAME) . '/languages/'
            );
        });
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        // Metabox - constructor registers meta for REST API (Gutenberg)
        $metabox = new WCL_Metabox();

        // save_post hook for classic editor fallback
        add_action('save_post', array($metabox, 'save_meta_box'), 10, 1);

        if (is_admin()) {
            $admin = new WCL_Admin();
            add_action('admin_menu', array($admin, 'add_settings_page'));
            add_action('admin_init', array($admin, 'register_settings'));
            add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
            add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));

            add_action('add_meta_boxes', array($metabox, 'add_meta_box'));
        }
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        $public = new WCL_Public();

        // Standard WordPress content filter
        add_filter('the_content', array($public, 'filter_content'), 99);

        // Elementor compatibility
        add_filter('elementor/frontend/the_content', array($public, 'filter_content'), 99);

        // tagDiv / Starter Templates theme compatibility
        add_filter('tdc_single_content', array($public, 'filter_content'), 99);
        add_filter('td_module_content', array($public, 'filter_content'), 99);

        // Alternative: Hook into template_redirect for full page control
        add_action('template_redirect', array($public, 'maybe_apply_paywall_redirect'));

        add_action('wp_enqueue_scripts', array($public, 'enqueue_styles'), 10);
        add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'), 10);

        // AJAX handlers
        add_action('wp_ajax_wcl_create_checkout', array($public, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_wcl_create_checkout', array($public, 'create_checkout_session'));

        // Stripe webhook handler
        $stripe = WCL_Stripe::get_instance();
        add_action('rest_api_init', array($stripe, 'register_webhook_endpoint'));

        // Checkout success handler
        add_action('template_redirect', array($public, 'handle_checkout_success'));

        // Account page shortcode
        new WCL_Account();
    }

    /**
     * Run on plugin activation
     */
    public static function activate() {
        WCL_Activator::activate();
    }

    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        WCL_Deactivator::deactivate();
    }
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('WP_Content_Locker', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Content_Locker', 'deactivate'));

/**
 * Initialize the plugin
 */
function wcl_init() {
    return WP_Content_Locker::get_instance();
}
add_action('plugins_loaded', 'wcl_init');
