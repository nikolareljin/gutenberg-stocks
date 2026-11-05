<?php
/**
 * WP-CLI commands: cache purge, provider list, fetch/warm.
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

use Nikolareljin\NrStocks\Providers\Provider_Registry;
use Nikolareljin\NrStocks\Providers\Rest_Provider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements `wp nr-stocks ...` commands.
 */
class Cli {

	/**
	 * Registers the command when WP-CLI is present.
	 */
	public static function register() {
		if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'nr-stocks cache purge', array( self::class, 'cache_purge' ) );
		\WP_CLI::add_command( 'nr-stocks providers', array( self::class, 'providers' ) );
		\WP_CLI::add_command( 'nr-stocks fetch', array( self::class, 'fetch' ) );
	}

	/**
	 * Purges all cached market data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nr-stocks cache purge
	 */
	public static function cache_purge() {
		$count = Cache::purge_all();
		\WP_CLI::success( sprintf( 'Purged %d cache keys.', $count ) );
	}

	/**
	 * Lists providers and key status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nr-stocks providers
	 */
	public static function providers() {
		$rows = array();
		foreach ( Provider_Registry::describe() as $item ) {
			$rows[] = array(
				'id'        => $item['id'],
				'label'     => $item['label'],
				'needs_key' => $item['needs_key'] ? 'yes' : 'no',
				'has_key'   => '' !== Settings::get_key( $item['id'] ) ? 'yes' : 'no',
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'label', 'needs_key', 'has_key' ) );
	}

	/**
	 * Fetches (and caches) one series. Useful to warm the cache.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<id>]
	 * : Provider id. Defaults to the saved default.
	 *
	 * [--symbol=<symbol>]
	 * : Ticker symbol. Defaults to AAPL.
	 *
	 * [--series=<series>]
	 * : daily, weekly, monthly, or intraday. Defaults to daily.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nr-stocks fetch --provider=frankfurter --symbol=EUR/USD
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 */
	public static function fetch( $args, $assoc_args ) {
		$provider = isset( $assoc_args['provider'] ) ? $assoc_args['provider'] : Settings::get()['default_provider'];
		$symbol   = isset( $assoc_args['symbol'] ) ? $assoc_args['symbol'] : 'AAPL';
		$series   = isset( $assoc_args['series'] ) ? $assoc_args['series'] : 'daily';
		$result   = Rest_Provider::series( $provider, $symbol, $series, 30 );
		if ( empty( $result['ok'] ) ) {
			\WP_CLI::error( 'Fetch failed: ' . ( isset( $result['error'] ) ? $result['error'] : 'unknown' ) );
		}
		\WP_CLI::success(
			sprintf(
				'Fetched %d rows for %s (%s, cached: %s).',
				count( $result['data'] ),
				$symbol,
				$provider,
				! empty( $result['cached'] ) ? 'yes' : 'no'
			)
		);
	}
}
