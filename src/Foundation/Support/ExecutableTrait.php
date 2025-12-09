<?php
declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\Foundation\Support;

/**
 * Provide a static `::exec()` shortcut for action classes.
 *
 * Example:
 *  InitWPAppKernelsAction::exec($arg1, $arg2);
 */
trait ExecutableTrait
{
    /**
     * Execute the action using a fresh instance.
     *
     * If a global `app()` helper with a `call()` method exists
     * (Laravel-style container), it will be used to allow
     * dependency injection into `execute()`. Otherwise, the
     * method is called directly.
     *
     * @param mixed ...$arguments
     * @return mixed
     * @throws \ReflectionException
     */
    public static function exec(...$arguments): mixed {
        $instance = new static(...$arguments);

        // Laravel-style: use the container's "call" helper if available.
        if (function_exists('app')) {
            $app = app();

            if (is_object($app) && method_exists($app, 'call')) {
                return $app->call([$instance, 'execute']);
            }
        }

        // Fallback: call execute() directly without DI.
        if (method_exists($instance, 'execute')) {
            return $instance->execute();
        }

        // Ultimate fallback: call handle() if execute() is missing.
        if (method_exists($instance, 'handle')) {
            return $instance->handle();
        }

        return null;
    }
}
