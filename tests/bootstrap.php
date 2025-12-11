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

// Load plugin files manually since we are not in a full WP environment
// We need to be careful about dependencies. 
// For unit tests, we usually load the class file under test in the test file or setup method.
// But we can autoload them via composer or meaningful require calls here.

// Include core classes to be available
require_once __DIR__ . '/../includes/class-wcl-content.php';
require_once __DIR__ . '/../includes/class-wcl-stripe.php';
require_once __DIR__ . '/../includes/class-wcl-subscription.php';
require_once __DIR__ . '/../includes/class-wcl-user.php';
