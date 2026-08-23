<?php
/**
 * PHPUnit bootstrap for Set Tag Order.
 *
 * @package SetTagOrder\Tests
 */

if ( ! defined( 'WP_TESTS_DIR' ) ) {
	$tests_dir = getenv( 'WP_TESTS_DIR' );

	if ( ! $tests_dir ) {
		$wp_root_tests_dir = dirname( __DIR__, 4 ) . '/tests/phpunit';
		if ( is_dir( $wp_root_tests_dir ) && file_exists( $wp_root_tests_dir . '/includes/functions.php' ) ) {
			$tests_dir = $wp_root_tests_dir;
		}
	}

	if ( ! $tests_dir && is_dir( '/tmp/wordpress-tests-lib' ) ) {
		$tests_dir = '/tmp/wordpress-tests-lib';
	}

	if ( ! $tests_dir && getenv( 'TMPDIR' ) ) {
		$tmpdir_path = rtrim( getenv( 'TMPDIR' ), '/' ) . '/wordpress-tests-lib';
		if ( is_dir( $tmpdir_path ) ) {
			$tests_dir = $tmpdir_path;
		}
	}

	if ( ! $tests_dir ) {
		$tests_dir = '/tmp/wordpress-tests-lib';
	}

	define( 'WP_TESTS_DIR', $tests_dir );
}

if ( ! file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
	echo 'Could not find WordPress test suite at: ' . WP_TESTS_DIR . PHP_EOL;
	echo "From the plugin directory, run:\n";
	echo "  composer test:setup\n";
	echo "Then run:\n";
	echo "  composer test\n";
	exit( 1 );
}

require_once WP_TESTS_DIR . '/includes/functions.php';

function _manually_load_set_tag_order_plugin() {
	require dirname( __DIR__ ) . '/set-tag-order.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_set_tag_order_plugin' );

require WP_TESTS_DIR . '/includes/bootstrap.php';

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Shared test helpers. Lives outside tests/integration so PHPUnit does not try
// to collect it as a test case.
require_once __DIR__ . '/lib/TagOrderTestHelpers.php';

if ( ! function_exists( 'settagord_get_ordered_post_tags' ) ) {
	echo "Failed to load Set Tag Order plugin\n";
	exit( 1 );
}

echo "Set Tag Order test environment loaded successfully\n";
