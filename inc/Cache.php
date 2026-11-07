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
	 * How long a request waits for another request's fetch, in milliseconds.
	 * Long enough to outlast the 12-second upstream timeout.
	 *
	 * @var int
	 */
	const LOCK_WAIT_MS = 13000;

	/**
	 * How often a waiting request checks for the result, in milliseconds.
	 *
	 * @var int
	 */
	const LOCK_POLL_MS = 250;

	/**
	 * How long upstream errors are cached, in seconds.
	 *
	 * @var int
	 */
	const NEGATIVE_TTL = 60;

	/**
	 * How long an entry outlives its TTL, in seconds, for use when a refresh
	 * cannot run or fails. Fresh reads still honor the TTL.
	 *
	 * @var int
	 */
	const STALE_GRACE = 3600;

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
	 * Optional check that may refuse an upstream fetch on a cache miss.
	 *
	 * @var callable|null
	 */
	protected static $fetch_guard = null;

	/**
	 * Sets or clears the check run before an upstream fetch.
	 *
	 * @param callable|null $guard Returns false to refuse the fetch.
	 */
	public static function set_fetch_guard( $guard ) {
		self::$fetch_guard = $guard;
	}

	/**
	 * Counts one event in a window that keeps its original expiry.
	 *
	 * @param string $key    Transient key.
	 * @param int    $limit  Events allowed per window.
	 * @param int    $window Window length in seconds.
	 * @return int 0 when allowed, otherwise seconds until the window resets.
	 */
	public static function throttle( $key, $limit, $window = 60 ) {
		// Other requests update this counter while a slow request is running.
		self::forget_local_copy( $key );
		$now   = time();
		$state = get_transient( $key );
		if ( ! is_array( $state ) || ! isset( $state['count'], $state['reset'] ) || (int) $state['reset'] <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + (int) $window,
			);
		}
		if ( (int) $state['count'] >= (int) $limit ) {
			return max( 1, (int) $state['reset'] - $now );
		}
		++$state['count'];
		set_transient( $key, $state, max( 1, (int) $state['reset'] - $now ) );
		return 0;
	}

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
		// Entries are shared across TTLs: a short-TTL refresh must not shorten
		// how long a longer-TTL caller can keep using the entry.
		$lifetime = $ttl + self::STALE_GRACE;
		$prior    = self::get( $key );
		if ( null !== $prior && isset( $prior['expires_at'] ) ) {
			$lifetime = max( $lifetime, (int) $prior['expires_at'] - time() );
		}
		set_transient(
			$key,
			array(
				'data'       => $data,
				'fetched_at' => time(),
				'expires_at' => time() + $lifetime,
			),
			$lifetime
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
			if ( ! self::fetch_allowed() ) {
				return self::failure( 'upstream_budget' );
			}
			$result               = call_user_func( $fetcher );
			$result['cached']     = false;
			$result['fetched_at'] = time();
			return $result;
		}

		// Entries are shared across TTLs, so each caller applies its own limit.
		$hit = self::read_response( $key );
		if ( null !== $hit && ( empty( $hit['ok'] ) || $hit['fetched_at'] + $ttl > time() ) ) {
			return $hit;
		}
		// Past this caller's TTL but still valid: better than any failure below.
		$stale = $hit;
		if ( null !== $stale && false !== get_transient( $key . '_retry' ) ) {
			// The last refresh failed moments ago; do not hit upstream again yet.
			return $stale;
		}

		if ( ! self::acquire_lock( $key ) ) {
			if ( null !== $stale ) {
				// Someone is already refreshing and there is data to serve now.
				return $stale;
			}
			// Another request owns the fetch: wait for its result instead of
			// adding a second upstream call for the same key.
			/**
			 * Filters how long a request waits for another request's fetch.
			 *
			 * @param int $wait_ms Milliseconds to wait.
			 */
			$wait_ms = (int) apply_filters( 'nr_stocks_lock_wait_ms', self::LOCK_WAIT_MS );
			$waited  = 0;
			while ( $waited < $wait_ms ) {
				usleep( self::LOCK_POLL_MS * 1000 );
				$waited += self::LOCK_POLL_MS;
				self::forget_local_copy( $key );
				$hit = self::read_response( $key );
				if ( null !== $hit && ( empty( $hit['ok'] ) || $hit['fetched_at'] + $ttl > time() ) ) {
					return $hit;
				}
				if ( ! self::lock_held( $key ) ) {
					// The holder may have stored its result just before releasing.
					self::forget_local_copy( $key );
					$hit = self::read_response( $key );
					if ( null !== $hit && ( empty( $hit['ok'] ) || $hit['fetched_at'] + $ttl > time() ) ) {
						return $hit;
					}
					break;
				}
			}
			return self::failure( 'busy' );
		}

		if ( ! self::fetch_allowed() ) {
			self::release_lock( $key );
			return null !== $stale ? $stale : self::failure( 'upstream_budget' );
		}

		$result = call_user_func( $fetcher );

		// The lock is released only after the result is stored, so a request
		// arriving in between cannot start a second fetch.
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			if ( null !== $stale ) {
				// Keep the valid entry instead of replacing it with an error, and
				// back off like the negative cache does.
				set_transient( $key . '_retry', 1, self::NEGATIVE_TTL );
				self::release_lock( $key );
				return $stale;
			}
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
			self::release_lock( $key );
			return self::failure( $error );
		}

		$stored = $result;
		unset( $stored['cached'], $stored['fetched_at'] );
		self::set( $key, $stored, $ttl );
		self::release_lock( $key );
		$result['cached']     = false;
		$result['fetched_at'] = time();
		return $result;
	}

	/**
	 * Drops this request's cached view of an entry so the next read is fresh.
	 *
	 * Without an object cache, a missed read is remembered in `notoptions` for
	 * the rest of the request, which would hide another process's result.
	 *
	 * @param string $key Cache key.
	 */
	protected static function forget_local_copy( $key ) {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_get( $key, 'transient', true );
			return;
		}
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( '_transient_' . $key, 'options' );
		wp_cache_delete( '_transient_timeout_' . $key, 'options' );
	}

	/**
	 * Reads a cached success or failure in response form.
	 *
	 * @param string $key Cache key.
	 * @return array|null
	 */
	protected static function read_response( $key ) {
		$entry = self::get( $key );
		if ( null === $entry || ! isset( $entry['data']['ok'] ) ) {
			return null;
		}
		$stored               = $entry['data'];
		$stored['cached']     = true;
		$stored['fetched_at'] = isset( $entry['fetched_at'] ) ? (int) $entry['fetched_at'] : time();
		return $stored;
	}

	/**
	 * Whether the current request may call upstream.
	 *
	 * @return bool
	 */
	protected static function fetch_allowed() {
		return null === self::$fetch_guard || false !== call_user_func( self::$fetch_guard );
	}

	/**
	 * Builds an uncached failure response.
	 *
	 * @param string $error Error code.
	 * @return array
	 */
	protected static function failure( $error ) {
		return array(
			'ok'         => false,
			'error'      => $error,
			'cached'     => false,
			'fetched_at' => time(),
		);
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
	 * Whether another request still holds the fetch mutex.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	protected static function lock_held( $key ) {
		$lock = $key . '_lock';
		if ( wp_using_ext_object_cache() ) {
			return false !== wp_cache_get( $lock, '', true );
		}
		self::forget_local_copy( $lock );
		return false !== get_transient( $lock );
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
