<?php
declare( strict_types = 1 );

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

use Yivic\YivicKernelTheme\App\Support\YivicKernelThemeHelper;

/*
|--------------------------------------------------------------------------
| Kernel Theme initialization
|--------------------------------------------------------------------------
|
| This file is loaded from functions.php once the theme is included by
| WordPress. It should stay very small and only be responsible for
| bootstrapping the kernel and delegating work to dedicated classes.
*/

/**
 * Allow child themes to override the concrete kernel/helper class.
 *
 * For now we treat the kernel as a static helper that bootstraps the wp-app
 * layer (YivicKernelThemeHelper). A child theme could provide its own class
 * that exposes a compatible static ::initialize() signature.
 */
if ( ! defined( 'YIVIC_KERNEL_THEME_CLASS' ) ) {
    define( 'YIVIC_KERNEL_THEME_CLASS', YivicKernelThemeHelper::class );
}

$kernel_class = YIVIC_KERNEL_THEME_CLASS;

// If the class does not exist, silently bail out (useful during early dev).
if ( ! class_exists( $kernel_class ) ) {
    if ( defined( 'YIVIC_KERNEL_THEME_DEBUG' ) && YIVIC_KERNEL_THEME_DEBUG ) {
        error_log(
            sprintf(
                '[Yivic Kernel Theme] Kernel class "%s" not found. Check your autoloader configuration.',
                $kernel_class
            )
        );
    }

    return;
}

/*
|--------------------------------------------------------------------------
| Static initialization hook
|--------------------------------------------------------------------------
|
| YivicKernelThemeHelper::initialize() is the main entry point to bridge the
| classic WordPress theme with the wp-app layer living in uploads/wp-app.
|
| We pass the theme URL and base path so the helper can resolve all related
| paths (config, storage, public assets, etc.).
*/

if ( method_exists( $kernel_class, 'initialize' ) ) {
    // Prefer constants if the theme defines them, otherwise fall back to WP.
    $theme_url = defined( 'YIVIC_KERNEL_THEME_URL' )
        ? YIVIC_KERNEL_THEME_URL
        : get_stylesheet_directory_uri();

    $theme_dir = defined( 'YIVIC_KERNEL_THEME_PATH' )
        ? YIVIC_KERNEL_THEME_PATH
        : get_stylesheet_directory();

    // Call the static initializer with both arguments.
    $kernel_class::initialize( $theme_url, $theme_dir );
}