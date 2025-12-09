<?php

namespace Yivic\YivicKernelTheme\App\Providers;

class TestServiceProvider {
    public function register(): void
    {
        // you can bind something to container here
    }

    public function boot(): void
    {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>TestServiceProvider BOOTED</p></div>';
        });
    }
}