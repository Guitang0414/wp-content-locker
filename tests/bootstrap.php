<?php
/**
 * PHPUnit Bootstrap file.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Setup WP_Mock
WP_Mock::bootstrap();

// Define ABSPATH if not defined (to prevent file access checks failing)
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

if (!defined('WCL_PLUGIN_DIR')) {
    define('WCL_PLUGIN_DIR', dirname(__DIR__) . '/');
}

/**
 * Mock global WP functions that might be called before tests run
 */
function is_admin() {
    return false;
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public function __construct($code = '', $message = '', $data = '') {
            $this->errors[$code][] = $message;
        }
        public function get_error_message() { return reset($this->errors)[0]; }
    }
}

function is_wp_error($thing) {
    return ($thing instanceof WP_Error);
}

function __($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function esc_html($text) {
    return $text;
}

function esc_attr($text) {
    return $text;
}

function esc_url($url) {
    return $url;
}

function home_url($path = '') {
    return 'http://example.org' . $path;
}

function wp_login_url($redirect = '') {
    return 'http://example.org/wp-login.php';
}

function get_bloginfo($show = '') {
    return 'Test Site';
}

function get_permalink($post = 0) {
    return 'http://example.org/post/' . (is_object($post) ? $post->ID : $post);
}

function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
    return true;
}

function current_time($type, $gmt = 0) {
    return time();
}

// Load plugin files manually since we are not in a full WP environment
// We need to be careful about dependencies. 
// For unit tests, we usually load the class file under test in the test file or setup method.
// But we can autoload them via composer or meaningful require calls here.

// Include core classes to be available
require_once __DIR__ . '/../includes/class-wcl-content.php';
require_once __DIR__ . '/../includes/class-wcl-stripe.php';
require_once __DIR__ . '/../includes/class-wcl-subscription.php';
require_once __DIR__ . '/../includes/class-wcl-user.php';
