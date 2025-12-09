<?php
declare( strict_types = 1 );

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| 0. Composer autoload
|--------------------------------------------------------------------------
|
| If the theme ships with a vendor directory, load the Composer autoloader.
| If vendor is missing, this block is simply skipped.
*/
$autoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

/*
|--------------------------------------------------------------------------
| 1. Kernel Bootstrap (constants + helpers + feature toggles)
|--------------------------------------------------------------------------
|
| This defines all required constants for the Kernel Theme and loads
| pure PHP helpers. NO logic should run yet.
*/
require_once __DIR__ . DIRECTORY_SEPARATOR . 'yivic-kernel-theme-bootstrap.php';

/*
|--------------------------------------------------------------------------
| 2. Theme Init Layer (optional extension point)
|--------------------------------------------------------------------------
|
| This file is where you may put additional initialization logic for
| routing, helpers, view overrides, etc. This keeps functions.php clean.
*/
$init_file = __DIR__ . DIRECTORY_SEPARATOR . 'yivic-kernel-theme-init.php';
if ( file_exists( $init_file ) ) {
    require_once $init_file;
}
