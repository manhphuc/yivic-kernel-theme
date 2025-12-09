<?php

declare(strict_types=1);

namespace Yivic\YivicKernelTheme\App\WP;

use Yivic\YivicKernelTheme\Foundation\Application;
use Yivic\YivicKernelTheme\Foundation\Http\Request;
use Yivic\YivicKernelTheme\Foundation\Http\Response;
use Yivic\YivicKernelTheme\App\Support\AppConst;
use Yivic\YivicKernelTheme\App\Actions\InitWPAppKernelsAction;
use Yivic\YivicKernelTheme\App\Support\YivicKernelThemeHelper;
use Yivic\YivicKernelTheme\App\Support\ConfigRepository;

/**
 * Minimal application bridge for the Yivic Kernel Theme.
 *
 * This class wraps the lightweight Application container and:
 * - Bootstraps it from the wp-app-config/app.php file.
 * - Registers configured service providers.
 * - Exposes a few helpers for paths, config, and headers.
 *
 * IMPORTANT:
 * - There is NO dedicated /wp-app or /wp-api routing here.
 * - Routing, HTTP kernel, etc. are out of scope for the theme kernel.
 */
class WPApplication extends Application
{
    /**
     * Raw bootstrap configuration used to seed the config repository.
     *
     * Typically, has the shape:
     * [
     *     'app' => [ 'providers' => [...], 'debug' => bool, ... ],
     * ]
     *
     * @var array<string, mixed>
     */
    protected static array $bootstrapConfig = [];

    /**
     * Base path of the kernel theme "application".
     *
     * Usually the theme root or a dedicated subdirectory.
     *
     * @var string
     */
    protected string $basePath = '';

    /**
     * Optional application namespace used by tools that need
     * to resolve classes relative to a root namespace.
     *
     * @var string|null
     */
    protected ?string $namespace = null;

    /**
     * WordPress headers captured for later merge, if needed.
     *
     * @var array<string, string>|null
     */
    protected ?array $wp_headers = null;

    /**
     * Config repository with simple dot-notation support.
     *
     * @var ConfigRepository|null
     */
    protected ?ConfigRepository $configRepository = null;

    /**
     * Determine if a global application instance already exists.
     */
    public static function isset(): bool
    {
        return static::$instance instanceof self;
    }

    /**
     * Bootstrap the global WPApplication instance if it has not been created yet.
     *
     * This is the main entry point that should be called once
     * from the theme (usually in functions.php or theme bootstrap).
     * @throws \ReflectionException
     */
    public static function load_instance(): void
    {
        if (static::isset()) {
            return;
        }

        // Resolve the base path for the kernel "app" part.
        $basePath = YivicKernelThemeHelper::get_wp_app_base_path();

        // Allow external code to adjust the bootstrap config if needed.
        // The app.php file should return an array of Laravel-style config
        // (providers, debug, etc.).
        $config = apply_filters(
            AppConst::FILTER_WP_APP_PREPARE_CONFIG,
            [
                'app' => require dirname(__DIR__, 3)
                    . DIRECTORY_SEPARATOR . 'wp-app-config'
                    . DIRECTORY_SEPARATOR . 'app.php',
            ]
        );

        // Ensure there is an application key for crypto / hashing usage.
        if (empty($config['app']['key'])) {
            $authKey = md5(uniqid('', true));
            $config['app']['key'] = $authKey;

            // Use a theme-specific option name to avoid conflicting with plugins.
            add_option('yivic_kernel_theme_wp_app_auth_key', $authKey);
        }

        $app = static::init_instance_with_config($basePath, $config);

        // Register any base service providers (if defined below).
        $app->registerBaseServiceProviders();

        // Let the kernels (controllers / services) hook into this app.
        InitWPAppKernelsAction::exec();

        // Notify listeners that the kernel theme app is ready.
        do_action(AppConst::ACTION_WP_APP_LOADED, $app);
    }

    /**
     * Construct a new WPApplication instance.
     *
     * Do not call this directly. Always go through load_instance().
     *
     * @param array<string, mixed> $config   Flattened configuration for the container
     *                                       (usually the contents of app.php).
     * @param string               $basePath Base application path.
     */
    public function __construct(array $config = [], string $basePath = '')
    {
        parent::__construct($config);

        $this->basePath = $basePath !== '' ? $basePath : dirname(__DIR__, 3);

        // Initialize the config repository from the stored bootstrap config.
        $this->configRepository = new ConfigRepository(static::$bootstrapConfig);

        // Optionally expose it via the container so other code can resolve it.
        $this->instance('config', $this->configRepository);
    }

    /**
     * Initialize the singleton instance with base path and bootstrap config.
     *
     * @param string                     $basePath
     * @param array<string, mixed>|null  $config
     *
     * @return self
     */
    public static function init_instance_with_config(string $basePath = '', ?array $config = null): self
    {
        if (static::$instance instanceof self) {
            return static::$instance;
        }

        static::$bootstrapConfig = $config ?? [];

        // We keep only the inner "app" config for the Application container.
        $appConfig = [];
        if (isset(static::$bootstrapConfig['app']) && is_array(static::$bootstrapConfig['app'])) {
            $appConfig = static::$bootstrapConfig['app'];
        }

        $instance = new static($appConfig, $basePath);

        static::$instance = $instance;

        return $instance;
    }

    /**
     * Get the base path for the kernel application.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Resolve a path inside the "resources" directory.
     *
     * This is useful for templates, views, language files, etc.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath
            . DIRECTORY_SEPARATOR . 'resources'
            . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get the default root namespace for the kernel theme code.
     */
    public function getNamespace(): string
    {
        if ($this->namespace !== null) {
            return $this->namespace;
        }

        $this->namespace = 'Yivic\\YivicKernelTheme\\';

        return $this->namespace;
    }

    /**
     * Read configuration values using dot-notation from the ConfigRepository.
     *
     * Examples:
     *  $this->config('app.debug', false);
     *  $this->config('app.providers', []);
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        if ($this->configRepository instanceof ConfigRepository) {
            return $this->configRepository->get($key, $default);
        }

        // Fallback to parent behaviour if the repository is not set.
        return parent::config($key, $default);
    }

    /**
     * Register the configured service providers.
     *
     * This reads the list from `app.providers` in the config repository
     * and calls `$this->register()` on each existing class.
     */
    public function registerConfiguredProviders(): void
    {
        $providersList = (array) $this->config('app.providers', []);

        $providersList = (array) apply_filters(
            AppConst::FILTER_WP_APP_MAIN_SERVICE_PROVIDERS,
            $providersList
        );

        foreach ($providersList as $providerClass) {
            if (!is_string($providerClass) || $providerClass === '') {
                continue;
            }

            if (!class_exists($providerClass)) {
                continue;
            }

            $this->register($providerClass);
        }

        do_action(AppConst::ACTION_WP_APP_REGISTERED, $this);
    }

    /**
     * Boot the application and trigger ACTION_WP_APP_BOOTED.
     */
    public function boot(): void
    {
        parent::boot();

        do_action(AppConst::ACTION_WP_APP_BOOTED, $this);
    }

    /**
     * Determine if the application is in debug mode.
     */
    public function is_debug_mode(): bool
    {
        return (bool) $this->config('app.debug', false);
    }

    /**
     * Return the "major" version of the kernel application.
     *
     * For now this simply wraps the Application::VERSION constant,
     * but the helper is kept for future compatibility.
     */
    public function get_kernel_major_version(): int
    {
        return (int) YivicKernelThemeHelper::get_major_version(Application::VERSION);
    }

    /**
     * Get the path to the Composer vendor directory used by the theme.
     */
    public function get_composer_path(): string
    {
        if (defined('COMPOSER_VENDOR_DIR')) {
            return COMPOSER_VENDOR_DIR;
        }

        return dirname($this->resourcePath()) . DIRECTORY_SEPARATOR . 'vendor';
    }

    /**
     * Store WordPress headers that should later be merged with
     * response headers from the kernel theme (if you need it).
     *
     * @param array<string, string> $headers
     */
    public function set_wp_headers(array $headers): void
    {
        $this->wp_headers = $headers;
    }

    /**
     * Get the stored WordPress headers (if any).
     *
     * @return array<string, string>
     */
    public function get_wp_headers(): array
    {
        return $this->wp_headers ?? [];
    }

    /**
     * Store the current Request instance in the container.
     */
    public function set_request(Request $request): void
    {
        $this->instance('request', $request);
        $this->instance(Request::class, $request);
    }

    /**
     * Store the current Response instance in the container.
     */
    public function set_response(Response $response): void
    {
        $this->instance('response', $response);
        $this->instance(Response::class, $response);
    }

    /**
     * Register base service providers required by the kernel theme.
     *
     * By default, this is empty. You can add core providers here later,
     * for example:
     *
     *  - Event / Dispatcher
     *  - Logging
     *  - Database / ORM
     */
    protected function registerBaseServiceProviders(): void
    {
        $providers = [
            // Example:
            \Yivic\YivicKernelTheme\App\Providers\TestServiceProvider::class,
        ];

        foreach ($providers as $providerClass) {
            if (class_exists($providerClass)) {
                $this->register($providerClass);
            }
        }
    }
}