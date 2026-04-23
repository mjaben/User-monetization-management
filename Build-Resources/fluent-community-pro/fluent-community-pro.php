<?php defined('ABSPATH') or die;

/*
Plugin Name: FluentCommunity Pro
Description: The Pro version of FluentCommunity Plugin
Version: 1.7.72
Author: WPManageNinja LLC
Author URI: https://fluentcommunity.co
Plugin URI: https://fluentcommunity.co
License: GPLv2 or later
Text Domain: fluent-community-pro
Domain Path: /language
*/

define('FLUENT_COMMUNITY_PRO', true);

add_filter('pre_http_request', function($preempt, $parsed_args, $url) {
    // Check if the request URL matches the desired endpoint
    if (strpos($url, 'https://api3.wpmanageninja.com/plugin') !== false) {
        // Return the custom response
        return [
            'headers' => [],
            'body' => json_encode([
                "success" => true,
                "license" => "valid",
                "item_id" => 7365751,
                "item_name" => "FluentCommunity Pro",
                "license_limit" => 100,
                "site_count" => 1,
                "expires" => "lifetime",
                "activations_left" => 99,
                "checksum" => "GPL001122334455AA6677BB8899CC000",
                "payment_id" => 123456,
                "customer_name" => "GPL",
                "customer_email" => "noreply@gmail.com",
                "price_id" => "7"
            ]),
            'response' => [
                'code' => 200,
                'message' => 'OK',
            ]
        ];
    }

    // If the request does not match, return the $preempt parameter to proceed with the actual HTTP request
    return $preempt;
}, 10, 3);

define('FLUENT_COMMUNITY_PRO_DIR', plugin_dir_path(__FILE__));
define('FLUENT_COMMUNITY_PRO_URL', plugin_dir_url(__FILE__));
define('FLUENT_COMMUNITY_PRO_DIR_FILE', __FILE__);
define('FLUENT_COMMUNITY_PRO_VERSION', '1.7.72');
define('FLUENT_COMMUNITY_MIN_CORE_VERSION', '1.7.72');

require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));
