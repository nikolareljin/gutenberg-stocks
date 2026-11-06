<?php
/**
 * Plugin service wiring.
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

use Nikolareljin\NrStocks\Rest\Routes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps all plugin services.
 */
class Plugin {

	/**
	 * Activation: seed default options.
	 */
	public static function activate() {
		if ( false === get_option( NR_STOCKS_OPTION, false ) ) {
			add_option( NR_STOCKS_OPTION, Settings::defaults() );
		}
	}

	/**
	 * Wires hooks. Runs on plugins_loaded.
	 */
	public static function init() {
		// Translations for WordPress.org plugins load automatically.
		$settings = new Settings();
		$settings->register();

		Shortcodes::register();

		add_action( 'init', array( 'Nikolareljin\\NrStocks\\Block', 'register' ) );
		add_action( 'rest_api_init', array( 'Nikolareljin\\NrStocks\\Rest\\Routes', 'register' ) );
		add_action( 'admin_init', array( 'Nikolareljin\\NrStocks\\Migration', 'maybe_migrate' ) );

		Cli::register();

		// Prevent stale data after option imports/syncs outside the UI.
		add_action(
			'update_option_' . NR_STOCKS_OPTION,
			function () {
				Cache::purge_all();
			}
		);
	}
}
