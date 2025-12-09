<?php
declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\Foundation;

/**
 * Minimal application / service container for Yivic Kernel Theme.
 *
 * This is a very small, Laravel-inspired Application implementation.
 * It provides:
 * - A global singleton instance.
 * - Basic IoC container (bind, singleton, instance, make).
 * - Aliases (e.g. alias(Request::class, 'request')).
 * - Service provider registration and booting.
 * - Simple configuration repository with dot-notation support.
 */
class Application
{
    /**
     * Simple application version marker (used by helper methods).
     */
    public const VERSION = '1.0.0';

    /**
     * Global application instance.
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * Container bindings [abstract => resolver].
     *
     * @var array<string, callable>
     */
    protected array $bindings = [];

    /**
     * Resolved instances [abstract => object].
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Aliases [alias => abstract].
     *
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Registered service providers.
     *
     * @var array<int, object>
     */
    protected array $providers = [];

    /**
     * Raw configuration array.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Create a new application instance.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        static::$instance = $this;

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
    }

    /**
     * Register core container bindings.
     *
     * Child classes (e.g. WPApplication) may extend this.
     */
    protected function registerBaseBindings(): void
    {
        // Make the application itself resolvable from the container.
        $this->instance(self::class, $this);
    }

    /**
     * Register base service providers.
     *
     * Intentionally empty – theme / plugin code can override this.
     */
    protected function registerBaseServiceProviders(): void
    {
        // No-op in the base Application.
    }

    /**
     * Get the global application instance (if any).
     */
    public static function getInstance(): ?self
    {
        return static::$instance;
    }

    /**
     * Basic CLI detection.
     */
    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /**
     * Bind a class or key to the container.
     *
     * @param string   $abstract
     * @param callable $resolver  function (Application $app): object
     */
    public function bind(string $abstract, callable $resolver): void
    {
        $this->bindings[$abstract] = $resolver;
    }

    /**
     * Register a singleton (same resolver as bind, but instance cached).
     *
     * @param string   $abstract
     * @param callable $resolver
     */
    public function singleton(string $abstract, callable $resolver): void
    {
        $this->bindings[$abstract] = $resolver;
    }

    /**
     * Store a concrete instance.
     *
     * @param string $abstract
     * @param mixed  $object
     */
    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
    }

    /**
     * Define an alias name for an abstract.
     *
     * Example:
     *   $app->alias(Request::class, 'request');
     *   $app->make('request'); // resolves Request::class
     *
     * @param string $abstract
     * @param string $alias
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolve an instance from the container.
     *
     * @throws \RuntimeException
     */
    public function make(string $abstract)
    {
        // Resolve alias first.
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->instances[$abstract] = ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return $this->instances[$abstract] = new $abstract();
        }

        throw new \RuntimeException("Container cannot resolve [{$abstract}]");
    }

    /**
     * Register a service provider class.
     *
     * The provider is expected to have a constructor accepting Application
     * and a register() method. A boot() method is optional.
     *
     * @param string $providerClass
     */
    public function register(string $providerClass): void
    {
        $provider = new $providerClass($this);

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        $this->providers[] = $provider;
    }

    /**
     * Boot all registered providers.
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    /**
     * Simple config getter with dot-notation support.
     *
     * Example:
     *   $app->config('app.debug', false);
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->config;
        }

        $segments = explode('.', $key);
        $value    = $this->config;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}