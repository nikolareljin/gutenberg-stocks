<?php
/**
 * Transient-backed cache with TTL presets, stampede mutex, and purge.
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches upstream market data. Works with plain transients and automatically
 * benefits from a persistent object cache when the host provides one.
 */
class Cache {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'nrstx_';

	/**
	 * How long a fetch mutex is held, in seconds.
	 *
	 * @var int
	 */
	const LOCK_TTL = 15;

	/**
	 * How long upstream errors are cached, in seconds.
	 *
	 * @var int
	 */
	const NEGATIVE_TTL = 60;

	/**
	 * Option holding the key index used to purge under object caching.
	 *
	 * @var string
	 */
	const INDEX_OPTION = 'nr_stocks_cache_index';

	/**
	 * Default TTLs per endpoint, in seconds.
	 *
	 * @var array
	 */
	const DEFAULT_TTLS = array(
		'series' => 300,
		'quote'  => 60,
		'search' => 3600,
	);

	/**
	 * Builds a transient key. Stays well under the 172-character limit.
	 *
	 * @param string $provider Provider id.
	 * @param string $endpoint Endpoint name (series, quote, search).
	 * @param array  $params   Request parameters.
	 * @return string
	 */
	public static function make_key( $provider, $endpoint, $params ) {
		return sprintf(
			'%s%s_%s_%s',
			self::PREFIX,
			substr( sanitize_key( $provider ), 0, 20 ),
			substr( sanitize_key( $endpoint ), 0, 20 ),
			md5( wp_json_encode( $params ) )
		);
	}

	/**
	 * Resolves the TTL for an endpoint: explicit override wins, then the
	 * saved setting, then the built-in default. 0 disables caching.
	 *
	 * @param string $endpoint Endpoint name.
	 * @param int    $override Explicit override in seconds (0 = none).
	 * @return int
	 */
	public static function resolve_ttl( $endpoint, $override = 0 ) {
		$override = (int) $override;
		if ( $override > 0 ) {
			return min( 86400, $override );
		}
		$settings = Settings::get();
		if ( isset( $settings['ttls'][ $endpoint ] ) ) {
			return max( 0, min( 86400, (int) $settings['ttls'][ $endpoint ] ) );
		}
		return isset( self::DEFAULT_TTLS[ $endpoint ] ) ? self::DEFAULT_TTLS[ $endpoint ] : 300;
	}

	/**
	 * Reads a cached entry.
	 *
	 * @param string $key Cache key.
	 * @return array|null Array with data/fetched_at, or null on miss.
	 */
	public static function get( $key ) {
		$entry = get_transient( $key );
		if ( ! is_array( $entry ) || ! array_key_exists( 'data', $entry ) ) {
			return null;
		}
		return $entry;
	}

	/**
	 * Stores a cache entry and tracks the key for purge.
	 *
	 * @param string $key  Cache key.
	 * @param mixed  $data Payload.
	 * @param int    $ttl  Seconds.
	 */
	public static function set( $key, $data, $ttl ) {
		$ttl = (int) $ttl;
		if ( $ttl <= 0 ) {
			return;
		}
		set_transient(
			$key,
			array(
				'data'       => $data,
				'fetched_at' => time(),
			),
			$ttl
		);
		self::index_add( $key );
	}

	/**
	 * Gets a cached value or fetches, stores, and returns a fresh one.
	 *
	 * The fetcher must return array( 'ok' => bool, 'data' => mixed ) on
	 * success or array( 'ok' => false, 'error' => string ) on failure.
	 * Failures are cached briefly so a dead upstream does not stampede.
	 *
	 * @param string   $key     Cache key.
	 * @param int      $ttl     Seconds (0 bypasses the cache).
	 * @param callable $fetcher Fresh-data callback.
	 * @return array Array with ok, data/error, cached, fetched_at keys.
	 */
	public static function get_or_fetch( $key, $ttl, $fetcher ) {
		$ttl = (int) $ttl;
		if ( $ttl <= 0 ) {
			$result               = call_user_func( $fetcher );
			$result['cached']     = false;
			$result['fetched_at'] = time();
			return $result;
		}

		$hit = self::get( $key );
		if ( null !== $hit && isset( $hit['data']['ok'] ) ) {
			$stored               = $hit['data'];
			$stored['cached']     = true;
			$stored['fetched_at'] = isset( $hit['fetched_at'] ) ? (int) $hit['fetched_at'] : time();
			return $stored;
		}

		if ( ! self::acquire_lock( $key ) ) {
			// Another request is fetching; give it a moment, then fetch anyway.
			sleep( 1 );
			$retry = self::get( $key );
			if ( null !== $retry && isset( $retry['data']['ok'] ) && ! empty( $retry['data']['ok'] ) ) {
				$stored               = $retry['data'];
				$stored['cached']     = true;
				$stored['fetched_at'] = isset( $retry['fetched_at'] ) ? (int) $retry['fetched_at'] : time();
				return $stored;
			}
		}

		$result = call_user_func( $fetcher );
		self::release_lock( $key );

		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			$error = is_array( $result ) && isset( $result['error'] ) ? (string) $result['error'] : 'fetch_failed';
			set_transient(
				$key,
				array(
					'data'       => array(
						'ok'    => false,
						'error' => $error,
					),
					'fetched_at' => time(),
				),
				self::NEGATIVE_TTL
			);
			self::index_add( $key );
			return array(
				'ok'         => false,
				'error'      => $error,
				'cached'     => false,
				'fetched_at' => time(),
			);
		}

		$stored = $result;
		unset( $stored['cached'], $stored['fetched_at'] );
		self::set( $key, $stored, $ttl );
		$result['cached']     = false;
		$result['fetched_at'] = time();
		return $result;
	}

	/**
	 * Deletes every cache entry this plugin created.
	 *
	 * @return int Number of keys purged from the index.
	 */
	public static function purge_all() {
		$index = get_option( self::INDEX_OPTION, array() );
		$count = 0;
		if ( is_array( $index ) ) {
			foreach ( array_keys( $index ) as $key ) {
				delete_transient( $key );
				++$count;
			}
		}
		delete_option( self::INDEX_OPTION );

		// Fallback for orphaned entries (plain transients in options table).
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- transient purge by prefix has no caching API.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%'
			)
		);
		return $count;
	}

	/**
	 * Tracks a key so purge works under persistent object caching too.
	 *
	 * @param string $key Cache key.
	 */
	protected static function index_add( $key ) {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$index[ $key ] = time();
		if ( count( $index ) > 500 ) {
			$index = array_slice( $index, -500, null, true );
		}
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Best-effort fetch mutex. Atomic under object caching, advisory otherwise.
	 *
	 * @param string $key Cache key.
	 * @return bool True when this request owns the fetch.
	 */
	protected static function acquire_lock( $key ) {
		$lock = $key . '_lock';
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $lock, 1, '', self::LOCK_TTL );
		}
		if ( false !== get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, self::LOCK_TTL );
		return true;
	}

	/**
	 * Releases a fetch mutex.
	 *
	 * @param string $key Cache key.
	 */
	protected static function release_lock( $key ) {
		$lock = $key . '_lock';
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $lock, '' );
		}
		delete_transient( $lock );
	}
}
