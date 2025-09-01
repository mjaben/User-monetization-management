<?php
/*
Plugin Name: FCM myCRED Addon
Description: Award myCRED points for Fluent Community posts and comments.
Version: 1.1
Author: Matthew John Alex (mjaben)
Text Domain: fcm-mycred-integration
Domain Path: /languages
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: mycred, fluent community, points, rewards, gamification
GitHub Plugin URI: https://github.com/mjaben/Fcm-mycred-reward-users
Requires at least: 5.0
Requires PHP: 7.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Define constants

define( 'FCM_MYCred_PATH', plugin_dir_path( __FILE__ ) );
define( 'FCM_MYCred_URL', plugin_dir_url( __FILE__ ) );

// Load required files
require_once FCM_MYCred_PATH . 'includes/class-fcm-mycred.php';
require_once FCM_MYCred_PATH . 'includes/class-fcm-admin.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    new FC_MyCRED_Integration();
    new FC_MyCRED_Admin();
});
