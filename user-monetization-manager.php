<?php
/*
Plugin Name: User Monetization Manager
Plugin URI: https://example.com
Description: Complete solution for rewarding users via myCRED and handling withdrawals.
Version: 2.0
Author: Matthew

License: GPL2
Text Domain: user-monetization-manager
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Define constants
define('UMM_VERSION', '2.0');
define('UMM_PATH', plugin_dir_path(__FILE__));
define('UMM_URL', plugin_dir_url(__FILE__));

// Activation hook
register_activation_hook(__FILE__, 'activate_user_monetization_manager');

function activate_user_monetization_manager() {
    require_once UMM_PATH . 'includes/class-umm-activator.php';
    UMM_Activator::activate();
}

// Load core class
require_once UMM_PATH . 'includes/class-umm-loader.php';

// Initialize
function run_user_monetization_manager() {
    $plugin = new UMM_Loader();
    $plugin->run();
}
run_user_monetization_manager();
