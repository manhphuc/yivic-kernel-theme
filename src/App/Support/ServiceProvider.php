<?php
declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\App\Support;

use Closure;

/**
 * Minimal standalone version of Laravel's ServiceProvider.
 *
 * - No Illuminate dependencies.
 * - Provides a lightweight service provider pattern for the theme kernel.
 * - Supports: booting callbacks, booted callbacks, and publishable file paths.
 */
abstract class ServiceProvider
{
    /**
     * Optional application/container instance.
     *
     * @var mixed|null
     */
    protected $app;

    /**
     * Callbacks executed before the "boot" method.
     *
     * @var array<int, \Closure>
     */
    protected array $bootingCallbacks = [];

    /**
     * Callbacks executed after the "boot" method.
     *
     * @var array<int, \Closure>
     */
    protected array $bootedCallbacks = [];

    /**
     * Files that can be published by this provider.
     *
     * @var array<string, array<string,string>>
     */
    public static array $publishes = [];

    /**
     * Publish groups mapped to file paths.
     *
     * @var array<string, array<string,string>>
     */
    public static array $publishGroups = [];

    /**
     * Create a new service provider instance.
     *
     * @param  mixed|null  $app
     */
    public function __construct($app = null)
    {
        $this->app = $app;
    }

    /**
     * Register bindings or internal services.
     */
    public function register(): void
    {
        // Override in subclass if needed.
    }

    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        // Override to implement boot logic.
    }

    /**
     * Register a callback to run before "boot".
     */
    public function booting(Closure $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a callback to run after "boot".
     */
    public function booted(Closure $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }

    /**
     * Execute all registered booting callbacks.
     */
    public function callBootingCallbacks(): void
    {
        foreach ($this->bootingCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Execute all registered booted callbacks.
     */
    public function callBootedCallbacks(): void
    {
        foreach ($this->bootedCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Register publishable paths for this provider.
     *
     * @param array<string,string> $paths
     * @param string|array<int,string>|null $groups
     */
    protected function publishes(array $paths, $groups = null): void
    {
        $class = static::class;

        $this->ensurePublishArrayInitialized($class);

        static::$publishes[$class] = array_merge(static::$publishes[$class], $paths);

        foreach ((array) $groups as $group) {
            $this->addPublishGroup($group, $paths);
        }
    }

    /**
     * Ensure that the provider has an initialized publish array.
     */
    protected function ensurePublishArrayInitialized(string $class): void
    {
        if (! array_key_exists($class, static::$publishes)) {
            static::$publishes[$class] = [];
        }
    }

    /**
     * Add files to a publish group.
     */
    protected function addPublishGroup(string $group, array $paths): void
    {
        if (! array_key_exists($group, static::$publishGroups)) {
            static::$publishGroups[$group] = [];
        }

        static::$publishGroups[$group] = array_merge(
            static::$publishGroups[$group],
            $paths
        );
    }

    /**
     * Retrieve publishable paths for a provider or group.
     *
     * @return array<string,string>
     */
    public static function pathsToPublish(?string $provider = null, ?string $group = null): array
    {
        if ($provider !== null && $group !== null) {
            return static::pathsForProviderAndGroup($provider, $group);
        }

        if ($group !== null && isset(static::$publishGroups[$group])) {
            return static::$publishGroups[$group];
        }

        if ($provider !== null && isset(static::$publishes[$provider])) {
            return static::$publishes[$provider];
        }

        if ($group !== null || $provider !== null) {
            return [];
        }

        // Return all publishable paths
        $all = [];
        foreach (static::$publishes as $paths) {
            $all = array_merge($all, $paths);
        }

        return $all;
    }

    /**
     * Retrieve paths shared between a provider and a group.
     *
     * @return array<string,string>
     */
    protected static function pathsForProviderAndGroup(string $provider, string $group): array
    {
        if (
            ! empty(static::$publishes[$provider]) &&
            ! empty(static::$publishGroups[$group])
        ) {
            return array_intersect_key(
                static::$publishes[$provider],
                static::$publishGroups[$group]
            );
        }

        return [];
    }

    /**
     * Get all providers that have publishable paths.
     */
    public static function publishableProviders(): array
    {
        return array_keys(static::$publishes);
    }

    /**
     * Get all publishable groups.
     */
    public static function publishableGroups(): array
    {
        return array_keys(static::$publishGroups);
    }
}