<?php

declare(strict_types=1);

namespace Yivic\YivicKernelTheme\App\Support;

use Yivic\YivicKernelTheme\App\Support\AppConst;

/**
 * Helper utilities for the Yivic Kernel Theme.
 *
 * This class is the "bridge" between classic WordPress and the wp-app layer
 * (Laravel-style application running inside uploads/wp-app).
 *
 * Responsibilities:
 * - Bootstrapping wp-app from the theme (CLI + web).
 * - Managing setup status (completed / failed) and admin notices.
 * - Providing shared helpers for URL, timezone, paths, and asset URLs.
 * - Exposing configuration flags (error handler, Blade template usage, web worker).
 */
class YivicKernelThemeHelper
{
    /**
     * Cached wp-app version option value.
     *
     * @var string|null
     */
    public static ?string $version_option = null;

    /**
     * Cached wp-app setup info option value.
     *
     * @var string|null
     */
    public static ?string $setup_info = null;

    /**
     * Cached result of the wp-app environment check.
     *
     * @var bool|null
     */
    public static ?bool $wp_app_check = null;

    /**
     * Entry point for the kernel theme to initialize the wp-app layer.
     *
     * This method is typically called from the theme bootstrap file, e.g.:
     *
     *  YivicKernelThemeHelper::initialize( get_stylesheet_directory_uri(), __DIR__ );
     *
     * @param string $theme_url Base URL of the theme.
     * @param string $dirname   Filesystem path of the theme directory.
     *
     * @return void
     */
    public static function initialize(string $theme_url, string $dirname): void
    {
        // Abort early if WordPress core is not fully loaded.
        if (! static::is_wp_core_loaded()) {
            return;
        }

        // Register WP-CLI command(s) for preparing the wp-app folders.
        static::register_cli_init_action();

        // When not in console, ensure wp-app environment is healthy.
        if (! static::is_console_mode() && ! static::perform_wp_app_check()) {
            // Keep the theme enabled, but do not continue bootstrapping wp-app.
            return;
        }

        if (! static::is_console_mode()) {
            // On normal HTTP requests, setup redirect logic if wp-app is not ready.
            static::register_setup_app_redirect();
        } elseif (static::is_yivic_kernel_theme_prepare_command()) {
            // When running the CLI "prepare" command, create required wp-app folders.
            static::prepare_wp_app_folders();
        }

        // Register the hook that loads the wp-app application instance.
        static::init_wp_app_instance();
    }

    /**
     * Get the current full URL as seen by the browser.
     *
     * Handles HTTPS and reverse proxies (HTTP_X_FORWARDED_PROTO).
     *
     * @return string
     */
    public static function get_current_url(): string
    {
        if (empty($_SERVER['SERVER_NAME']) && empty($_SERVER['HTTP_HOST'])) {
            return '';
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $_SERVER['HTTPS'] = 'on';
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $http_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        }

        $current_url  = $http_protocol ?? '';
        $current_url .= $current_url ? '://' : '//';

        if (! empty($_SERVER['HTTP_HOST'])) {
            $current_url .= sanitize_text_field($_SERVER['HTTP_HOST'])
                . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '');

            return $current_url;
        }

        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] !== '80') {
            $current_url .= sanitize_text_field($_SERVER['SERVER_NAME'])
                . ':' . sanitize_text_field($_SERVER['SERVER_PORT'])
                . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '');
        } else {
            $current_url .= sanitize_text_field($_SERVER['SERVER_NAME'])
                . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '');
        }

        return $current_url;
    }

    /**
     * Get the wp-app setup URL path (relative or absolute).
     *
     * @param bool $full_url Whether to include the full site URL.
     *
     * @return string
     */
    public static function get_setup_app_uri(bool $full_url = false): string
    {
        $uri = 'wp-app/setup-app/?force_app_running_in_console=1';

        return $full_url ? rtrim(site_url(), '/') . '/' . $uri : $uri;
    }

    /**
     * Get the admin wp-app setup URL path (relative or absolute).
     *
     * @param bool $full_url Whether to include the full site URL.
     *
     * @return string
     */
    public static function get_admin_setup_app_uri(bool $full_url = false): string
    {
        $uri = 'wp-app/admin/setup-app/?force_app_running_in_console=1';

        return $full_url ? rtrim(site_url(), '/') . '/' . $uri : $uri;
    }

    /**
     * Wrapper around wp_login_url() for convenience and consistency.
     *
     * @param string $return_url  Optional URL to redirect to after login.
     * @param bool   $force_reauth Whether to force re-authentication.
     *
     * @return string
     */
    public static function get_wp_login_url(string $return_url = '', bool $force_reauth = false): string
    {
        return wp_login_url($return_url, $force_reauth);
    }

    /**
     * Determine if the current URL matches the wp-app setup URL.
     *
     * @return bool
     */
    public static function at_setup_app_url(): bool
    {
        $current_url   = static::get_current_url();
        $setup_app_uri = static::get_setup_app_uri();

        return (str_contains($current_url, $setup_app_uri));
    }

    /**
     * Determine if the current URL matches the admin wp-app setup URL.
     *
     * @return bool
     */
    public static function at_admin_setup_app_url(): bool
    {
        $current_url  = static::get_current_url();
        $redirect_uri = static::get_admin_setup_app_uri();

        return (str_contains($current_url, $redirect_uri));
    }

    /**
     * Determine if the current URL is the WordPress login URL.
     *
     * @return bool
     */
    public static function at_wp_login_url(): bool
    {
        $current_url = static::get_current_url();
        $login_url   = wp_login_url();

        return (str_contains($current_url, $login_url));
    }

    /**
     * Redirect the browser to the wp-app setup URL if needed.
     *
     * @return void
     */
    public static function redirect_to_setup_url(): void
    {
        $redirect_uri = static::get_setup_app_uri();

        if (! static::at_setup_app_url() && ! static::at_admin_setup_app_url()) {
            $redirect_url = add_query_arg(
                [
                    'return_url' => urlencode(static::get_current_url()),
                ],
                site_url($redirect_uri)
            );
            header('Location: ' . $redirect_url);
            exit(0);
        }
    }

    /**
     * Get the base path component of the site URL.
     *
     * Example: for https://example.com/wp, returns "/wp".
     *
     * @return string
     */
    public static function get_base_url_path(): string
    {
        $site_url_parts = wp_parse_url(site_url());

        return empty($site_url_parts['path']) ? '' : $site_url_parts['path'];
    }

    /**
     * Get the current blog path in a multisite network.
     *
     * Returns null for the main site, or the subsite path (without leading/trailing slash).
     *
     * @return string|null
     */
    public static function get_current_blog_path(): ?string
    {
        $site_url         = site_url();
        $network_site_url = network_site_url();

        if ($site_url === $network_site_url) {
            return null;
        }

        $reverse_pos = strpos(strrev($site_url), strrev($network_site_url));
        if ($reverse_pos === false) {
            return null;
        }

        return trim(substr($site_url, $reverse_pos * -1), '/');
    }

    /**
     * Get (and cache) the current wp-app version from the options table.
     *
     * @return string
     */
    public static function get_version_option(): string
    {
        if (static::$version_option === null) {
            static::$version_option = (string) get_option(AppConst::OPTION_VERSION, '0.0.0');
        }

        return static::$version_option;
    }

    /**
     * Get (and cache) the wp-app setup info flag from the options table.
     *
     * @return string
     */
    public static function get_setup_info(): string
    {
        if (static::$setup_info === null) {
            static::$setup_info = (string) get_option(AppConst::OPTION_SETUP_INFO, '');
        }

        return static::$setup_info;
    }

    /**
     * Determine whether the wp-app setup has been completed successfully.
     *
     * @return bool
     */
    public static function is_setup_app_completed(): bool
    {
        // Migration for DB-backed sessions exists from version 0.7.0.
        return (bool) apply_filters(
            'yivic_kernel_theme_is_setup_app_completed',
            version_compare(static::get_version_option(), '0.7.0', '>=')
        );
    }

    /**
     * Determine whether the last wp-app setup attempt failed.
     *
     * @return bool
     */
    public static function is_setup_app_failed(): bool
    {
        return static::get_setup_info() === 'failed';
    }

    /**
     * Validate the wp-app environment and prepare admin notices if needed.
     *
     * - Checks for required PHP extensions (PDO MySQL).
     * - Ensures setup has completed successfully.
     * - Logs and surfaces errors in the WordPress admin.
     *
     * @return bool True if wp-app is considered healthy.
     */
    public static function perform_wp_app_check(): bool
    {
        // Only evaluate once per request.
        if (static::$wp_app_check !== null) {
            return (bool) static::$wp_app_check;
        }

        if (! static::is_pdo_mysql_loaded()) {
            $error_message = sprintf(
            // translators: %1$s is replaced by an extension name.
                __('Error with PHP extension %1$s. Please enable PHP extension %1$s via your hosting Control Panel or contact your hosting admin.', 'yivic-kernel-theme'),
                'PDO MySQL'
            );
            static::add_wp_app_setup_errors($error_message);
        }

        if (empty(static::get_wp_app_setup_errors()) && static::is_setup_app_completed()) {
            static::$wp_app_check = (bool) apply_filters(AppConst::FILTER_WP_APP_CHECK, true);

            return static::$wp_app_check;
        }

        // If setup previously failed, and we are not on the setup URLs, surface an error.
        if (! static::at_setup_app_url() && ! static::at_admin_setup_app_url() && static::is_setup_app_failed()) {
            $error_message = sprintf(
            // translators: %1$s is replaced by a URL.
                __('The setup has not been completed correctly. Please go to this URL <a href="%1$s">%1$s</a> to complete the setup.', 'yivic-kernel-theme'),
                static::get_admin_setup_app_uri(true)
            );
            static::add_wp_app_setup_errors($error_message);
        }

        if (! empty($GLOBALS['wp_app_setup_errors'])) {
            static::put_messages_to_wp_admin_notice($GLOBALS['wp_app_setup_errors']);
            static::$wp_app_check = (bool) apply_filters(AppConst::FILTER_WP_APP_CHECK, false);

            return static::$wp_app_check;
        }

        static::$wp_app_check = (bool) apply_filters(AppConst::FILTER_WP_APP_CHECK, true);

        return static::$wp_app_check;
    }

    /**
     * Push setup error messages into the WordPress admin notice stack.
     *
     * @param array $error_messages List of error messages.
     *
     * @return void
     */
    public static function put_messages_to_wp_admin_notice(array &$error_messages): void
    {
        add_action(
            'admin_notices',
            static function () use ($error_messages): void {
                YivicKernelThemeHookHandlers::print_admin_notice_messages($error_messages);
            }
        );
    }

    /**
     * Determine whether the script is running in CLI / console mode.
     *
     * @return bool
     */
    public static function is_console_mode(): bool
    {
        $sapi = (string) static::get_php_sapi_name();

        return $sapi === 'cli' || $sapi === 'phpdbg' || $sapi === 'cli-server';
    }

    /**
     * Add a wp-app setup error to the global error store.
     *
     * @param string $error_message Error message text.
     *
     * @return void
     */
    public static function add_wp_app_setup_errors(string $error_message): void
    {
        if (! isset($GLOBALS['wp_app_setup_errors'])) {
            $GLOBALS['wp_app_setup_errors'] = [];
        }

        if (! isset($GLOBALS['wp_app_setup_errors'][$error_message])) {
            $GLOBALS['wp_app_setup_errors'][$error_message] = false;
        }
    }

    /**
     * Get the list of wp-app setup errors stored globally.
     *
     * @return array
     */
    public static function get_wp_app_setup_errors(): array
    {
        return isset($GLOBALS['wp_app_setup_errors'])
            ? (array) $GLOBALS['wp_app_setup_errors']
            : [];
    }

    /**
     * Determine whether the custom error handler for the kernel theme should be used.
     *
     * @return bool
     */
    public static function use_yivic_kernel_theme_error_handler(): bool
    {
        $use_error_handler = static::get_use_yivic_kernel_theme_error_handler_setting();

        return (bool) apply_filters('yivic_kernel_theme_use_error_handler', $use_error_handler);
    }

    /**
     * Resolve the "use error handler" flag from constants or environment.
     *
     * @return bool
     */
    public static function get_use_yivic_kernel_theme_error_handler_setting(): bool
    {
        if (defined('YIVIC_KERNEL_THEME_USE_ERROR_HANDLER')) {
            return (bool) constant('YIVIC_KERNEL_THEME_USE_ERROR_HANDLER');
        }

        $env_value = getenv('YIVIC_KERNEL_THEME_USE_ERROR_HANDLER');

        return $env_value !== false && (bool)$env_value;
    }


    /**
     * Determine whether Blade should be used to render WordPress templates.
     *
     * @return bool
     */
    public static function use_blade_for_wp_template(): bool
    {
        $blade_for_template = static::get_blade_for_wp_template_setting();

        return (bool) apply_filters('yivic_kernel_theme_use_blade_for_wp_template', $blade_for_template);
    }

    /**
     * Resolve the "use Blade for template" flag from constants or environment.
     *
     * @return bool
     */
    public static function get_blade_for_wp_template_setting(): bool
    {
        if (defined('YIVIC_KERNEL_THEME_USE_BLADE_FOR_WP_TEMPLATE')) {
            return (bool) constant('YIVIC_KERNEL_THEME_USE_BLADE_FOR_WP_TEMPLATE');
        }

        $env_value = getenv('YIVIC_KERNEL_THEME_USE_BLADE_FOR_WP_TEMPLATE');

        return $env_value !== false && (bool)$env_value;
    }

    /**
     * Determine whether the wp-app web worker should be disabled.
     *
     * @return bool
     */
    public static function disable_web_worker(): bool
    {
        $disable_web_worker = static::get_disable_web_worker_status();

        return (bool) apply_filters('yivic_kernel_theme_disable_web_worker', $disable_web_worker);
    }

    /**
     * Resolve the "disable web worker" flag from constants or environment.
     *
     * @return bool
     */
    public static function get_disable_web_worker_status(): bool
    {
        if (defined('YIVIC_KERNEL_THEME_DISABLE_WEB_WORKER')) {
            return (bool) constant('YIVIC_KERNEL_THEME_DISABLE_WEB_WORKER');
        }

        $env_value = getenv('YIVIC_KERNEL_THEME_DISABLE_WEB_WORKER');

        return $env_value !== false && (bool)$env_value;
    }

    /**
     * Get the base filesystem path for the wp-app instance used by the theme.
     *
     * @return string
     */
    public static function get_wp_app_base_path(): string
    {
        if (defined('YIVIC_KERNEL_THEME_WP_APP_BASE_PATH') && constant('YIVIC_KERNEL_THEME_WP_APP_BASE_PATH')) {
            return (string) constant('YIVIC_KERNEL_THEME_WP_APP_BASE_PATH');
        }

        return WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'wp-app';
    }

    /**
     * Get the list of wp-app folder paths derived from the base path.
     *
     * @param string $wp_app_base_path Base wp-app path.
     *
     * @return array<string,string>
     */
    public static function get_wp_app_base_folders_paths(string $wp_app_base_path): array
    {
        return [
            'base_path'                        => $wp_app_base_path,
            'config_path'                      => $wp_app_base_path . DIRECTORY_SEPARATOR . 'config',
            'database_path'                    => $wp_app_base_path . DIRECTORY_SEPARATOR . 'database',
            'database_migrations_path'         => $wp_app_base_path . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
            'bootstrap_path'                   => $wp_app_base_path . DIRECTORY_SEPARATOR . 'bootstrap',
            'bootstrap_cache_path'             => $wp_app_base_path . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache',
            'lang_path'                        => $wp_app_base_path . DIRECTORY_SEPARATOR . 'lang',
            'resources_path'                   => $wp_app_base_path . DIRECTORY_SEPARATOR . 'resources',
            'storage_path'                     => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage',
            'storage_logs_path'                => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'storage_framework_path'           => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework',
            'storage_framework_views_path'     => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
            'storage_framework_cache_path'     => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
            'storage_framework_cache_data_path'=> $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data',
            'storage_framework_sessions_path'  => $wp_app_base_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
        ];
    }

    /**
     * Ensure that all required wp-app folders exist and are writable.
     *
     * @param int    $chmod           Directory permissions (e.g. 0755 or 0777).
     * @param string $wp_app_base_path Optional custom base path.
     *
     * @return void
     */
    public static function prepare_wp_app_folders(int $chmod = 0777, string $wp_app_base_path = ''): void
    {
        if ($wp_app_base_path === '') {
            $wp_app_base_path = static::get_wp_app_base_path();
        }

        // Ensure parent directory is writable.
        @chmod(dirname($wp_app_base_path), $chmod); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.chmod_chmod, WordPress.PHP.NoSilencedErrors.Discouraged

        $paths = static::get_wp_app_base_folders_paths($wp_app_base_path);

        foreach ($paths as $filepath) {
            if (! is_dir($filepath)) {
                if (function_exists('wp_mkdir_p')) {
                    wp_mkdir_p($filepath);
                } else {
                    @mkdir($filepath, $chmod, true); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir
                }
            }

            @chmod($filepath, $chmod); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.chmod_chmod, WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Register WP-CLI commands related to the kernel theme.
     *
     * @return void
     */
    public static function wp_cli_init(): void
    {
        if (! class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command(
            'yivic-kernel-theme prepare',
            [static::class, 'wp_cli_prepare']
        );
    }

    /**
     * WP-CLI callback: prepares the wp-app folders.
     *
     * @param array $args       Positional args (unused).
     * @param array $assoc_args Associative args (unused).
     *
     * @return void
     */
    public static function wp_cli_prepare($args, $assoc_args): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
    {
        static::prepare_wp_app_folders();
    }

    /**
     * Conditionally redirect the current HTTP request to the wp-app setup screen.
     *
     * This method is intentionally conservative and **does nothing by default**.
     * Projects must explicitly opt-in via the `yivic_kernel_theme_enable_setup_redirect` filter.
     *
     * Behaviour overview:
     * - Skips entirely when running in CLI / console mode.
     * - Skips when setup has already completed or has been explicitly marked as failed.
     * - Optionally prepares the wp-app folders before redirecting.
     * - Allows projects to override or fully handle the redirect logic via filters/actions.
     *
     * Available filters:
     * - `yivic_kernel_theme_enable_setup_redirect` (bool, default: false)
     *     Enable/disable the automatic redirect mechanism.
     *
     * - `yivic_kernel_theme_skip_setup_redirect` (bool, default: false)
     *     Short-circuit the redirect logic for specific conditions
     *     (e.g. REST API requests, health checks, custom URLs).
     *
     * - `yivic_kernel_theme_prepare_wp_app_folders_before_redirect` (bool, default: true)
     *     Control whether the wp-app directory structure should be created
     *     right before redirecting.
     *
     * - `yivic_kernel_theme_handle_setup_redirect_manually` (bool, default: false)
     *     If set to true, the theme will NOT call redirect_to_setup_url()
     *     and assumes the project will perform its own redirect inside a hooked
     *     callback to `yivic_kernel_theme_before_setup_redirect`.
     *
     * Available actions:
     * - `yivic_kernel_theme_before_setup_redirect`
     *     Fires right before the default redirect occurs, useful for logging
     *     or last-minute adjustments.
     *
     * @return void
     */
    public static function maybe_redirect_to_setup_app(): void
    {
        // Never redirect when running from CLI / console.
        if ( static::is_console_mode() ) {
            return;
        }

        // Opt-in only: by default the redirect is disabled to avoid unexpected 404s
        // on installations that do not provide a /wp-app/setup-app/ route.
        $enable_redirect = (bool) apply_filters(
            'yivic_kernel_theme_enable_setup_redirect',
            false
        );

        if ( ! $enable_redirect ) {
            return;
        }

        // Allow projects to short-circuit the redirect for specific requests.
        if ( (bool) apply_filters( 'yivic_kernel_theme_skip_setup_redirect', false ) ) {
            return;
        }

        // If the setup is already completed or explicitly marked as failed,
        // there is no point in redirecting again.
        if ( static::is_setup_app_completed() || static::is_setup_app_failed() ) {
            return;
        }

        // Optionally ensure the wp-app directory structure exists before redirecting.
        if ( (bool) apply_filters( 'yivic_kernel_theme_prepare_wp_app_folders_before_redirect', true ) ) {
            static::prepare_wp_app_folders();
        }

        /**
         * Give projects a final chance to hook into the process before the redirect.
         * This can be used for logging, telemetry, or custom side effects.
         */
        do_action( 'yivic_kernel_theme_before_setup_redirect' );

        // If a project wants to handle the redirect manually (e.g. using a custom
        // target URL or framework), it can return true from this filter.
        $handled_manually = (bool) apply_filters(
            'yivic_kernel_theme_handle_setup_redirect_manually',
            false
        );

        if ( ! $handled_manually ) {
            static::redirect_to_setup_url();
        }
    }


    /**
     * Determine the correct PHP timezone identifier for wp-app.
     *
     * Uses the WordPress settings (`gmt_offset` / `timezone_string`) and falls back
     * to a WP_APP_TIMEZONE constant if present.
     *
     * @return string
     */
    public static function wp_app_get_timezone(): string
    {
        $current_offset  = (float) get_option('gmt_offset');
        $timezone_string = (string) get_option('timezone_string');

        // Remove legacy Etc/GMT references. Fall back to gmt_offset.
        if (str_contains($timezone_string, 'Etc/GMT')) {
            $timezone_string = '';
        }

        // Build an Etc/GMT identifier compatible with date_default_timezone_set().
        if ($timezone_string === '') {
            if ((int) $current_offset === 0) {
                $timezone_string = 'Etc/GMT';
            } elseif ($current_offset < 0) {
                $timezone_string = 'Etc/GMT+' . abs($current_offset);
            } else {
                $timezone_string = 'Etc/GMT-' . abs($current_offset);
            }
        }

        if (function_exists('wp_timezone')) {
            return str_contains(wp_timezone()->getName(), '/')
                ? wp_timezone()->getName()
                : $timezone_string;
        }

        return defined('WP_APP_TIMEZONE') ? (string) WP_APP_TIMEZONE : $timezone_string;
    }

    /**
     * Build the public URL prefix for wp-app assets.
     *
     * @param bool $full_url Whether to include the full site URL.
     *
     * @return string
     */
    public static function wp_app_get_asset_url(bool $full_url = false): string
    {
        if (defined('YIVIC_KERNEL_THEME_WP_APP_ASSET_URL') && constant('YIVIC_KERNEL_THEME_WP_APP_ASSET_URL')) {
            return (string) constant('YIVIC_KERNEL_THEME_WP_APP_ASSET_URL');
        }

        $slug_to_wp_app       = str_replace(ABSPATH, '', static::get_wp_app_base_path());
        $slug_to_public_asset = '/' . trim($slug_to_wp_app, '/') . '/public';

        return $full_url ? rtrim(get_site_url(), '/') . $slug_to_public_asset : $slug_to_public_asset;
    }

    /**
     * Extract the major version from a semantic version string.
     *
     * @param string $version Version string (e.g. "1.2.3").
     *
     * @return int
     */
    public static function get_major_version(string $version): int
    {
        $parts = explode('.', $version);

        return (int) filter_var($parts[0] ?? '0', FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Build the default wp-app web page title.
     *
     * @return string
     */
    public static function wp_app_web_page_title(): string
    {
        $title = empty(wp_title('', false))
            ? get_bloginfo('name') . ' | ' . (get_bloginfo('description') ?: 'WP App')
            : wp_title('', false);

        return (string) apply_filters(AppConst::FILTER_WP_APP_WEB_PAGE_TITLE, $title);
    }

    /**
     * Check whether WordPress core has defined WP_CONTENT_DIR.
     *
     * @return bool
     */
    public static function is_wp_core_loaded(): bool
    {
        return defined('WP_CONTENT_DIR');
    }

    /**
     * Get the current PHP SAPI name.
     *
     * @return string
     */
    public static function get_php_sapi_name(): string
    {
        return php_sapi_name();
    }

    /**
     * Check whether the PDO MySQL extension is available.
     *
     * @return bool
     */
    public static function is_pdo_mysql_loaded(): bool
    {
        return extension_loaded('pdo_mysql');
    }

    /**
     * Register the kernel theme's CLI init hook.
     *
     * @return void
     */
    public static function register_cli_init_action(): void
    {
        add_action('cli_init', [static::class, 'wp_cli_init']);
    }

    /**
     * Register the redirect-to-setup behavior on the configured kernel setup hook.
     *
     * @return void
     */
    public static function register_setup_app_redirect(): void
    {
        add_action(
            defined('YIVIC_KERNEL_THEME_SETUP_HOOK_NAME') ? YIVIC_KERNEL_THEME_SETUP_HOOK_NAME : 'plugins_loaded',
            [static::class, 'maybe_redirect_to_setup_app'],
            -200
        );
    }

    /**
     * Determine whether the current CLI arguments represent the "prepare" command.
     *
     * @param array|null $argv Optional argv override, defaults to $_SERVER['argv'].
     *
     * @return bool
     */
    public static function is_yivic_kernel_theme_prepare_command(?array $argv = null): bool
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $argv = $argv ?? ($_SERVER['argv'] ?? []);

        return ! empty($argv) && array_intersect((array) $argv, ['yivic-kernel-theme', 'prepare']);
    }

    /**
     * Hook the wp-app application loader into the kernel-theme setup hook.
     *
     * This keeps the wp-app bootstrap timing configurable via
     * YIVIC_KERNEL_THEME_SETUP_HOOK_NAME while staying close to the original
     * plugin behavior.
     *
     * @return void
     */
    public static function init_wp_app_instance(): void
    {
        $hook_name = defined('YIVIC_KERNEL_THEME_SETUP_HOOK_NAME')
            ? YIVIC_KERNEL_THEME_SETUP_HOOK_NAME
            : 'plugins_loaded';

        add_action(
            $hook_name,
            [\Yivic\YivicKernelTheme\App\WP\WPApplication::class, 'load_instance'],
            -100
        );
    }
}