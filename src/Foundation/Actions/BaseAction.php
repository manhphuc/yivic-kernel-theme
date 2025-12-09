<?php

declare( strict_types = 1 );

namespace Yivic\YivicKernelTheme\Foundation\Actions;

/**
 * Minimal base class for "actions" in the kernel theme.
 *
 * An action is a small, self-contained unit of work with a single
 * `handle()` method as the entry point. The `execute()` wrapper
 * exists mainly so traits like ExecutableTrait can call it.
 */
abstract class BaseAction {
    /**
     * Execute the action.
     *
     * Child classes normally do not override this; they should
     * implement `handle()` instead.
     *
     * @return mixed
     */
    public function execute(): mixed {
        return $this->handle();
    }

    /**
     * Perform the actual work of the action.
     *
     * Every concrete action must implement this method.
     *
     * @return mixed
     */
    abstract public function handle(): mixed;
}