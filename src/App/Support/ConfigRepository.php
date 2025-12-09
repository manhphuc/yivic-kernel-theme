<?php

declare(strict_types=1);

namespace Yivic\YivicKernelTheme\App\Support;

/**
 * Minimal configuration repository used by the kernel theme.
 *
 * It supports "dot" notation keys similar to Laravel's config().
 */
class ConfigRepository
{
    /**
     * All configuration items.
     *
     * @var array<string, mixed>
     */
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get the specified configuration value.
     *
     * @param string      $key
     * @param mixed|null  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if ($key === '') {
            return $this->items;
        }

        $segments = explode('.', $key);
        $value    = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a given configuration value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $array    =& $this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array =& $array[$segment];
        }

        $array[array_shift($segments)] = $value;
    }

    /**
     * Return all configuration items as an array.
     */
    public function all(): array
    {
        return $this->items;
    }
}