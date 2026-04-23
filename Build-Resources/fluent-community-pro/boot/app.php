<?php

use FluentCommunityPro\App\Core\Application;

return function ($file) {
    add_action('fluent_community/portal_loaded', function ($app) use ($file) {
        new Application($app, $file);
        (new \FluentCommunityPro\App\Modules\ModulesInit())->register($app);

        $licenseManager = new \FluentCommunityPro\App\Services\PluginManager\LicenseManager();
        $licenseManager->initUpdater();
        $licenseMessage = $licenseManager->getLicenseMessages();
        if ($licenseMessage) {
            add_action('admin_notices', function () use ($licenseMessage) {
                $class = 'notice notice-error fc_message';
                $message = $licenseMessage['message'];
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
            });
            add_filter('fluent_community/portal_notices', function ($notices) use ($licenseMessage) {
                if (!$licenseMessage || !\FluentCommunity\App\Services\Helper::isSiteAdmin()) {
                    return;
                }

                if (!empty($licenseMessage['message'])) {
                    $notices[] = '<div style="padding: 10px;" class="error; background-color: var(--fcom-primary-bg, white);">' . $licenseMessage['message'] . '</div>';
                }

                return $notices;
            });
        }
    });

    add_action('plugins_loaded', function () {
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            (new \FluentCommunityPro\App\Hooks\Handlers\CoreDepenedencyHandler())->register();
        } else {
            add_filter('fluent_community/portal_notices', function ($notices) {
                if (FLUENT_COMMUNITY_MIN_CORE_VERSION !== FLUENT_COMMUNITY_PLUGIN_VERSION && version_compare(FLUENT_COMMUNITY_MIN_CORE_VERSION, FLUENT_COMMUNITY_PLUGIN_VERSION, '>')) {
                    if(!\FluentCommunity\App\Services\Helper::isSiteAdmin()) {
                        return $notices;
                    }
                    $updateUrl = admin_url('plugins.php?s=fluent-community&plugin_status=all&fluent-community_check_update=' . time());
                    $notices[] = '<div style="padding: 10px;" class="error; background-color: var(--fcom-primary-bg, white);"><b>Heads UP: </b> FluentCommunity Base Plugin needs to be updated to the latest version. <a href="' . esc_url($updateUrl) . '">Click here to update</a></div>';
                }
                return $notices;
            });
        }
    });

};
