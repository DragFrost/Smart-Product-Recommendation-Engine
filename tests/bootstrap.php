<?php
/**
 * PHPUnit Test Suite Bootstrapper.
 *
 * @package SPRE\Tests
 */

declare(strict_types=1);

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/bootstrap.php' ) ) {
	echo esc_html( "Could not find $_tests_dir/includes/bootstrap.php. Please specify WP_TESTS_DIR environment variable.\n" );
	exit( 1 );
}

// Incorporate helper hooks from WP Testing Library
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually boot our plugin wrapper on mock load.
 */
function _spre_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/smart-product-recommendation-engine.php';
}
tests_add_filter( 'muplugins_loaded', '_spre_manually_load_plugin' );

// Call core tests bootstrapper
require $_tests_dir . '/includes/bootstrap.php';
