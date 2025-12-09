<?php
declare( strict_types = 1 );

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Detect if the kernel theme has already been bootstrapped.
 * Useful when the kernel is reused from a child theme.
 */
$yivic_kernel_theme_existed = defined( 'YIVIC_KERNEL_THEME_VERSION' );

/*
|--------------------------------------------------------------------------
| General directory separator
|--------------------------------------------------------------------------
*/
defined( 'DIR_SEP' ) || define( 'DIR_SEP', DIRECTORY_SEPARATOR );

/*
|--------------------------------------------------------------------------
| Kernel Theme version
|--------------------------------------------------------------------------
|
| Keep this value in sync with style.css.
*/
defined( 'YIVIC_KERNEL_THEME_VERSION' ) || define( 'YIVIC_KERNEL_THEME_VERSION', '1.0.0' );

/*
|--------------------------------------------------------------------------
| Slug & text domain
|--------------------------------------------------------------------------
|
| Slug should match the theme folder name and the text domain used for
| translations.
*/
defined( 'YIVIC_KERNEL_THEME_SLUG' )         || define( 'YIVIC_KERNEL_THEME_SLUG', 'yivic-kernel-theme' );
defined( 'YIVIC_KERNEL_THEME_TEXT_DOMAIN' )  || define( 'YIVIC_KERNEL_THEME_TEXT_DOMAIN', 'yivic-kernel-theme' );

/*
|--------------------------------------------------------------------------
| Base filesystem path & URL
|--------------------------------------------------------------------------
*/
defined( 'YIVIC_KERNEL_THEME_PATH' ) || define( 'YIVIC_KERNEL_THEME_PATH', __DIR__ );

if ( ! defined( 'YIVIC_KERNEL_THEME_URL' ) ) {
    if ( function_exists( 'get_template_directory_uri' ) ) {
        // Always points to the parent theme directory URI.
        define( 'YIVIC_KERNEL_THEME_URL', get_template_directory_uri() );
    } else {
        define( 'YIVIC_KERNEL_THEME_URL', '' );
    }
}

defined( 'YIVIC_KERNEL_THEME_SETUP_HOOK_NAME' ) || define(
    'YIVIC_KERNEL_THEME_SETUP_HOOK_NAME',
    ! empty( getenv( 'YIVIC_KERNEL_THEME_SETUP_HOOK_NAME' ) )
        ? getenv( 'YIVIC_KERNEL_THEME_SETUP_HOOK_NAME' )
        : 'after_setup_theme'
);

/*
|--------------------------------------------------------------------------
| Optional debug flag
|--------------------------------------------------------------------------
|
| When enabled, the kernel may use non-cached asset versions, etc.
*/
if ( ! defined( 'YIVIC_KERNEL_THEME_DEBUG' ) ) {
    $debug_from_env = getenv( 'YIVIC_KERNEL_THEME_DEBUG' );

    if ( $debug_from_env !== false ) {
        define( 'YIVIC_KERNEL_THEME_DEBUG', (bool) $debug_from_env );
    } else {
        define( 'YIVIC_KERNEL_THEME_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
    }
}

/*
|--------------------------------------------------------------------------
| Load helpers
|--------------------------------------------------------------------------
|
| Helpers should be framework-agnostic and safe to load multiple times.
*/
$helpers_file = __DIR__ . DIR_SEP . 'src' . DIR_SEP . 'helpers.php';
if ( file_exists( $helpers_file ) ) {
    require_once $helpers_file;
}

/*
|--------------------------------------------------------------------------
| Fallback Composer autoload
|--------------------------------------------------------------------------
|
| We keep this here for flexibility. Later you can swap Composer with your
| own autoloader without touching functions.php.
*/
$autoload_file = __DIR__ . DIR_SEP . 'vendor' . DIR_SEP . 'autoload.php';

if ( file_exists( $autoload_file ) && ! $yivic_kernel_theme_existed ) {
    require_once $autoload_file;
}