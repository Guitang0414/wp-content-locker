<?php
define('ABSPATH', 1);
function add_action($tag, $callback, $priority=10, $accepted_args=1){}
function add_filter($tag, $callback, $priority=10, $accepted_args=1){}
function register_activation_hook($f, $c){}
function register_deactivation_hook($f, $c){}
function plugin_dir_path($f) { return __DIR__ . '/'; }
function plugin_dir_url($f) { return 'http://localhost/wp-content/plugins/wcl/'; }
function plugin_basename($f) { return basename($f); }
function get_option($k, $d=null){ return $d; }
function __($s, $d){ return $s; }
function _e($s, $d){ echo $s; }
function is_admin(){ return false; }
class WP_Error { public function __construct($c,$m,$d=''){} }

try {
    require 'wp-content-locker.php';
    WP_Content_Locker::get_instance();
    echo "No fatal errors on load!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
