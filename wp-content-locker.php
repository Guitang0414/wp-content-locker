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


require_once WCL_PLUGIN_DIR . 'includes/class-wcl-activator.php';
require_once WCL_PLUGIN_DIR . 'includes/class-wcl-deactivator.php';

if (!class_exists('WP_Content_Locker')) {
	/**
	 * The core plugin class.
	 *
	 * This is used to define internationalization, admin-specific hooks, and
	 * public-facing site hooks.
	 *
	 * Also maintains the unique identifier of this plugin as well as the current
	 * version of the plugin.
	 */
	class WP_Content_Locker {

		/**
		 * The loader that's responsible for maintaining and registering all hooks that power
		 * the plugin.
		 */
		protected $loader;

		/**
		 * The unique identifier of this plugin.
		 */
		protected $plugin_name;

		/**
		 * The current version of the plugin.
		 */
		protected $version;

		/**
		 * Define the core functionality of the plugin.
		 */
		public function __construct() {
			$this->version = WCL_VERSION;
			$this->plugin_name = 'wp-content-locker';

			$this->load_dependencies();
			$this->set_locale();
			$this->define_admin_hooks();
			$this->define_public_hooks();
		}

		/**
		 * Load the required dependencies for this plugin.
		 */
		private function load_dependencies() {
			require_once WCL_PLUGIN_DIR . 'includes/class-wcl-stripe.php';
			require_once WCL_PLUGIN_DIR . 'includes/class-wcl-subscription.php';
			require_once WCL_PLUGIN_DIR . 'includes/class-wcl-user.php';
			require_once WCL_PLUGIN_DIR . 'includes/class-wcl-content.php';
			require_once WCL_PLUGIN_DIR . 'admin/class-wcl-admin.php';
			require_once WCL_PLUGIN_DIR . 'admin/class-wcl-metabox.php';
			require_once WCL_PLUGIN_DIR . 'public/class-wcl-public.php';
			require_once WCL_PLUGIN_DIR . 'public/class-wcl-account.php';
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 */
		private function set_locale() {
			add_action('plugins_loaded', function() {
				load_plugin_textdomain('wp-content-locker', false, dirname(WCL_PLUGIN_BASENAME) . '/languages/');
			});
		}

		/**
		 * Register all of the hooks related to the admin-area functionality
		 * of the plugin.
		 */
		private function define_admin_hooks() {
			$plugin_admin = new WCL_Admin();
			$plugin_metabox = new WCL_Metabox();

			add_action('admin_menu', array($plugin_admin, 'add_settings_page'));
			add_action('admin_init', array($plugin_admin, 'register_settings'));
			add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
			add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
			
			// Metaboxes
			add_action('add_meta_boxes', array($plugin_metabox, 'add_metabox'));
			add_action('save_post', array($plugin_metabox, 'save_metabox'));

            // Cleanup
            add_action('delete_user', array('WCL_Subscription', 'delete_by_user_id'));
		}

		/**
		 * Register all of the hooks related to the public-facing functionality
		 * of the plugin.
		 */
		private function define_public_hooks() {
			$plugin_public = new WCL_Public();
			$plugin_account = new WCL_Account();

			add_filter('the_content', array($plugin_public, 'filter_content'));
			add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_styles'));
			add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_scripts'));
			
			// AJAX handlers
			add_action('wp_ajax_wcl_create_checkout', array($plugin_public, 'create_checkout_session'));
			add_action('wp_ajax_nopriv_wcl_create_checkout', array($plugin_public, 'create_checkout_session'));
			
			// Success redirect
			add_action('init', array($plugin_public, 'handle_checkout_success'));
			
			// Stripe Webhook
			add_action('rest_api_init', array(WCL_Stripe::get_instance(), 'register_webhook_endpoint'));

            // Page builder fallbacks
            add_action('wp', array($plugin_public, 'maybe_apply_paywall_redirect'));

			// Account shortcode
			add_shortcode('wcl_account', array($plugin_account, 'render_account_page'));
			
			// Account AJAX actions
			add_action('wp_ajax_wcl_login', array($plugin_account, 'handle_login'));
			add_action('wp_ajax_nopriv_wcl_login', array($plugin_account, 'handle_login'));
			add_action('wp_ajax_wcl_register', array($plugin_account, 'handle_register'));
			add_action('wp_ajax_nopriv_wcl_register', array($plugin_account, 'handle_register'));
			add_action('wp_ajax_wcl_lost_password', array($plugin_account, 'handle_lost_password'));
			add_action('wp_ajax_nopriv_wcl_lost_password', array($plugin_account, 'handle_lost_password'));
            add_action('wp_ajax_wcl_update_profile', array($plugin_account, 'handle_update_profile'));
            add_action('wp_ajax_wcl_update_password', array($plugin_account, 'handle_update_password'));
            add_action('wp_ajax_wcl_cancel_subscription', array($plugin_account, 'handle_cancel_subscription'));
            add_action('wp_ajax_wcl_resume_subscription', array($plugin_account, 'handle_resume_subscription'));

            // Subscription Page Rewrite
            add_action('init', array($plugin_public, 'add_rewrite_rules'));
            add_filter('query_vars', array($plugin_public, 'register_query_vars'));
            add_filter('template_include', array($plugin_public, 'subscription_page_template'));
		}

		/**
		 * Static methods for activation/deactivation
		 */
		public static function activate() {
			WCL_Activator::activate();
		}

		public static function deactivate() {
			// WCL_Deactivator::deactivate();
		}

		/**
		 * Singleton instance
		 */
		public static function get_instance() {
			static $instance = null;
			if (null === $instance) {
				$instance = new self();
			}
			return $instance;
		}
	}
}

register_activation_hook(__FILE__, array('WP_Content_Locker', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Content_Locker', 'deactivate'));

function wcl_init() {
    return WP_Content_Locker::get_instance();
}
add_action('plugins_loaded', 'wcl_init');
