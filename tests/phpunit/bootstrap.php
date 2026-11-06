<?php
/**
 * PHPUnit bootstrap against the WordPress test library.
 *
 * WP_TESTS_DIR is provisioned by scripts/test.sh (local, via
 * script-helpers ci_wp_phpunit.sh) and by the ci-helpers wp-phpunit workflow.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir || ! is_readable( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI-only bootstrap notice.
	fwrite(
		STDERR,
		"WP_TESTS_DIR is not set to a valid WordPress test library. Run scripts/test.sh instead of phpunit directly.\n"
	);
	exit( 1 );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require_once dirname( __DIR__, 2 ) . '/nr-stocks.php';
	}
);

require_once $_tests_dir . '/includes/bootstrap.php';
