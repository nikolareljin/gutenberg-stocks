<?php
/**
 * Uninstall handler for NR Stocks.
 *
 * Removes the plugin options and all cached market data. Runs only when the
 * plugin is deleted through the WordPress admin.
 *
 * @package Nikolareljin\NrStocks
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$nr_stocks_options = array(
	'nr_stocks_settings',
	'nr_stocks_cache_index',
	// Legacy option key from the 0.x releases.
	'stockinfo_options_api',
);

foreach ( $nr_stocks_options as $nr_stocks_option ) {
	delete_option( $nr_stocks_option );
}

// Remove all transients created by the cache layer (prefix nrstx_).
global $wpdb;
$nr_stocks_like = $wpdb->esc_like( '_transient_nrstx_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge by prefix has no caching API.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$nr_stocks_like,
		$wpdb->esc_like( '_transient_timeout_nrstx_' ) . '%'
	)
);
