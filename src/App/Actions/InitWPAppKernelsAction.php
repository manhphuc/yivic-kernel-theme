<?php

declare(strict_types=1);

namespace Yivic\YivicKernelTheme\App\Actions;

use Yivic\YivicKernelTheme\App\WP\WPApplication;
use Yivic\YivicKernelTheme\Foundation\Actions\BaseAction;
use Yivic\YivicKernelTheme\Foundation\Support\ExecutableTrait;

/**
 * Initialize the kernel theme application bindings.
 *
 * This is called once from WPApplication::load_instance().
 */
class InitWPAppKernelsAction extends BaseAction
{
    use ExecutableTrait;

    /**
     * Execute the job.
     *
     * In the original plugin this method registered the HTTP / Console
     * kernels and exception handler. For the kernel theme we only bind
     * a few lightweight values into the container.
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function handle(): mixed
    {
        // Resolve the global application instance (WPApplication).
        if (! function_exists('app')) {
            // If there is no global helper yet, nothing to initialize.
            return null;
        }

        $app = app();

        if (! $app instanceof WPApplication) {
            return null;
        }

        // Expose the current environment as a simple container entry.
        $env = $app->config('app.env', 'production');
        $app->instance('env', $env);

        // You can return anything useful here; for now return the env.
        return $env;
    }
}