<?php
/*
Plugin Name: FCM myCRED Integration
Description: Award myCRED points for Fluent Community posts and comments, with admin settings.
Version: 1.1
Author: Matthew
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FCM_MYCred_PATH', plugin_dir_path( __FILE__ ) );
define( 'FCM_MYCred_URL', plugin_dir_url( __FILE__ ) );

// Load required files
require_once FCM_MYCred_PATH . 'includes/class-fc-mycred.php';
require_once FCM_MYCred_PATH . 'includes/class-fc-admin.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    new FC_MyCRED_Integration();
    new FC_MyCRED_Admin();
});
