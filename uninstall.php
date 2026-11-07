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

/**
 * Removes this plugin's options and cached data from the current site.
 */
function nr_stocks_uninstall_site() {
	global $wpdb;

	// Under an object cache, transients live outside the options table.
	$index = get_option( 'nr_stocks_cache_index', array() );
	if ( is_array( $index ) ) {
		foreach ( array_keys( $index ) as $key ) {
			delete_transient( $key );
		}
	}

	$options = array(
		'nr_stocks_settings',
		'nr_stocks_cache_index',
		'nr_stocks_migrated',
		// Legacy option key from the 0.x releases.
		'stockinfo_options_api',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Remove all transients created by the cache layer (prefix nrstx_).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall purge by prefix has no caching API.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_nrstx_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_nrstx_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	// Options, including API keys, are stored per site.
	$nr_stocks_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $nr_stocks_site_ids as $nr_stocks_site_id ) {
		switch_to_blog( $nr_stocks_site_id );
		nr_stocks_uninstall_site();
		restore_current_blog();
	}
} else {
	nr_stocks_uninstall_site();
}
