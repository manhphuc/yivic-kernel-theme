<?php
declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\App\Support;

class AppConst {

    const ACTION_WP_APP_LOADED                      = 'yivic_kernel_theme_wp_app_loaded';

    const ACTION_WP_APP_BOOTED                      = 'yivic_kernel_theme_wp_app_booted';
    const FILTER_WP_APP_PREPARE_CONFIG              = 'yivic_kernel_theme_wp_app_prepare_config';

    const FILTER_WP_APP_MAIN_SERVICE_PROVIDERS      = 'yivic_kernel_theme_wp_app_main_service_providers';

    const ACTION_WP_APP_REGISTERED                  = 'yivic_kernel_theme_wp_app_registered';

    const FILTER_WP_APP_WEB_PAGE_TITLE              = 'yivic_kernel_theme_wp_app_web_page_title';

    const FILTER_WP_APP_CHECK                       = 'yivic_kernel_theme_wp_app_check';

    const OPTION_VERSION                            = '_yivic_kernel_theme_version';


    const OPTION_SETUP_INFO                         = '_yivic_kernel_theme_setup_info';


}
