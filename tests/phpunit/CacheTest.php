<?php
/**
 * Tests for the cache layer: TTL resolution, fetch-once, negative cache, purge.
 */

use Nikolareljin\NrStocks\Cache;
use Nikolareljin\NrStocks\Settings;

class NR_Stocks_Cache_Test extends WP_UnitTestCase {

	public function test_make_key_is_stable_sized_and_distinct() {
		$one   = Cache::make_key( 'twelvedata', 'series', array( 'AAPL', 'daily' ) );
		$two   = Cache::make_key( 'twelvedata', 'series', array( 'AAPL', 'daily' ) );
		$other = Cache::make_key( 'twelvedata', 'series', array( 'MSFT', 'daily' ) );
		$this->assertSame( $one, $two );
		$this->assertNotSame( $one, $other );
		$this->assertLessThan( 172, strlen( '_transient_' . $one ) );
	}

	public function test_resolve_ttl_prefers_override_then_setting_then_default() {
		$this->assertSame( 120, Cache::resolve_ttl( 'series', 120 ) );
		$this->assertSame( 86400, Cache::resolve_ttl( 'series', 99999 ) );

		$settings                   = Settings::get();
		$settings['ttls']['series'] = 999;
		update_option( NR_STOCKS_OPTION, $settings );
		$this->assertSame( 999, Cache::resolve_ttl( 'series', 0 ) );
		$this->assertSame( 60, Cache::resolve_ttl( 'quote', 0 ) );
		$this->assertSame( 300, Cache::resolve_ttl( 'nope', 0 ) );
	}

	public function test_get_or_fetch_fetches_once_then_serves_cache() {
		$calls   = 0;
		$fetcher = function () use ( &$calls ) {
			++$calls;
			return array(
				'ok'   => true,
				'data' => array( 'rows' ),
			);
		};
		$first   = Cache::get_or_fetch( 'nrstx_phpunit_once', 300, $fetcher );
		$second  = Cache::get_or_fetch( 'nrstx_phpunit_once', 300, $fetcher );
		$this->assertTrue( $first['ok'] );
		$this->assertFalse( $first['cached'] );
		$this->assertTrue( $second['cached'] );
		$this->assertSame( 1, $calls );
		$this->assertSame( array( 'rows' ), $second['data'] );
	}

	public function test_errors_are_cached_briefly() {
		$calls   = 0;
		$fetcher = function () use ( &$calls ) {
			++$calls;
			return array(
				'ok'    => false,
				'error' => 'http_error',
			);
		};
		$first   = Cache::get_or_fetch( 'nrstx_phpunit_err', 300, $fetcher );
		$second  = Cache::get_or_fetch( 'nrstx_phpunit_err', 300, $fetcher );
		$this->assertFalse( $first['ok'] );
		$this->assertTrue( $second['cached'] );
		$this->assertSame( 'http_error', $second['error'] );
		$this->assertSame( 1, $calls );
	}

	public function test_zero_ttl_bypasses_the_cache() {
		$calls   = 0;
		$fetcher = function () use ( &$calls ) {
			++$calls;
			return array(
				'ok'   => true,
				'data' => $calls,
			);
		};
		Cache::get_or_fetch( 'nrstx_phpunit_bypass', 0, $fetcher );
		$second = Cache::get_or_fetch( 'nrstx_phpunit_bypass', 0, $fetcher );
		$this->assertSame( 2, $calls );
		$this->assertSame( 2, $second['data'] );
		$this->assertNull( Cache::get( 'nrstx_phpunit_bypass' ) );
	}

	public function test_purge_all_removes_entries() {
		Cache::set( 'nrstx_phpunit_a', array( 'a' ), 300 );
		Cache::set( 'nrstx_phpunit_b', array( 'b' ), 300 );
		$this->assertNotNull( Cache::get( 'nrstx_phpunit_a' ) );
		$count = Cache::purge_all();
		$this->assertGreaterThanOrEqual( 2, $count );
		$this->assertNull( Cache::get( 'nrstx_phpunit_a' ) );
		$this->assertNull( Cache::get( 'nrstx_phpunit_b' ) );
	}

	public function test_request_that_loses_the_lock_never_fetches() {
		set_transient( 'nrstx_phpunit_lock_lock', 1, 15 );
		add_filter( 'nr_stocks_lock_wait_ms', array( $this, 'short_lock_wait' ) );
		$calls  = 0;
		$result = Cache::get_or_fetch(
			'nrstx_phpunit_lock',
			300,
			function () use ( &$calls ) {
				++$calls;
				return array(
					'ok'   => true,
					'data' => array(),
				);
			}
		);
		remove_filter( 'nr_stocks_lock_wait_ms', array( $this, 'short_lock_wait' ) );
		$this->assertSame( 0, $calls );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'busy', $result['error'] );
	}

	public function short_lock_wait() {
		return 250;
	}

	public function test_hit_older_than_the_callers_ttl_is_refetched() {
		$calls   = 0;
		$fetcher = function () use ( &$calls ) {
			++$calls;
			return array(
				'ok'   => true,
				'data' => array( $calls ),
			);
		};
		set_transient(
			'nrstx_phpunit_age',
			array(
				'data'       => array(
					'ok'   => true,
					'data' => array( 'old' ),
				),
				'fetched_at' => time() - 600,
			),
			86400
		);
		$long  = Cache::get_or_fetch( 'nrstx_phpunit_age', 86400, $fetcher );
		$short = Cache::get_or_fetch( 'nrstx_phpunit_age', 300, $fetcher );
		$this->assertSame( array( 'old' ), $long['data'] );
		$this->assertSame( array( 1 ), $short['data'] );
		$this->assertSame( 1, $calls );
	}

	public function test_waiting_request_sees_a_result_stored_by_another_process() {
		global $wpdb;
		$key = 'nrstx_phpunit_wait';
		set_transient( $key . '_lock', 1, 15 );
		$reads = 0;
		// Another request finishes after this one's first miss, writing straight
		// to the database as a separate PHP process would.
		$other_process = function ( $pre ) use ( &$reads, $key, $wpdb ) {
			if ( 2 === ++$reads ) {
				$value = array(
					'data'       => array(
						'ok'   => true,
						'data' => array( 'from-other' ),
					),
					'fetched_at' => time(),
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => '_transient_timeout_' . $key,
						'option_value' => time() + 300,
						'autoload'     => 'off',
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => '_transient_' . $key,
						'option_value' => maybe_serialize( $value ),
						'autoload'     => 'off',
					)
				);
			}
			return $pre;
		};
		add_filter( 'pre_transient_' . $key, $other_process );
		$started = microtime( true );
		$result  = Cache::get_or_fetch(
			$key,
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'must-not-fetch' ),
				);
			}
		);
		remove_filter( 'pre_transient_' . $key, $other_process );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'from-other' ), $result['data'] );
		$this->assertLessThan( 2, microtime( true ) - $started );
	}

	public function test_waiter_sees_a_result_stored_just_before_the_lock_was_released() {
		global $wpdb;
		$key = 'nrstx_phpunit_race';
		set_transient( $key . '_lock', 1, 15 );
		$lock_reads = 0;
		// Runs as the waiter checks the lock, right after its last read missed.
		$holder_finishes = function ( $pre ) use ( &$lock_reads, $key, $wpdb ) {
			if ( 2 === ++$lock_reads ) {
				$value = array(
					'data'       => array(
						'ok'   => true,
						'data' => array( 'from-holder' ),
					),
					'fetched_at' => time(),
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => '_transient_timeout_' . $key,
						'option_value' => time() + 300,
						'autoload'     => 'off',
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => '_transient_' . $key,
						'option_value' => maybe_serialize( $value ),
						'autoload'     => 'off',
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->delete( $wpdb->options, array( 'option_name' => '_transient_' . $key . '_lock' ) );
			}
			return $pre;
		};
		add_filter( 'pre_transient_' . $key . '_lock', $holder_finishes );
		$result = Cache::get_or_fetch(
			$key,
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'must-not-fetch' ),
				);
			}
		);
		remove_filter( 'pre_transient_' . $key . '_lock', $holder_finishes );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'from-holder' ), $result['data'] );
	}

	public function test_throttle_sees_counts_written_by_other_requests() {
		global $wpdb;
		$key = 'nrstx_phpunit_rl_shared';
		$this->assertSame( 0, Cache::throttle( $key, 2, 60 ) );
		// Another request used up the window after this request cached its copy.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
		$wpdb->update(
			$wpdb->options,
			array(
				'option_value' => maybe_serialize(
					array(
						'count' => 2,
						'reset' => time() + 60,
					)
				),
			),
			array( 'option_name' => '_transient_' . $key )
		);
		$this->assertGreaterThan( 0, Cache::throttle( $key, 2, 60 ) );
	}

	public function test_waiter_stops_when_the_holder_stored_nothing() {
		global $wpdb;
		$key = 'nrstx_phpunit_gone';
		set_transient( $key . '_lock', 1, 15 );
		$reads = 0;
		// The holder ends without a result, as a separate process would.
		$holder_exits = function ( $pre ) use ( &$reads, $key, $wpdb ) {
			if ( 2 === ++$reads ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another process.
				$wpdb->delete( $wpdb->options, array( 'option_name' => '_transient_' . $key . '_lock' ) );
			}
			return $pre;
		};
		add_filter( 'pre_transient_' . $key, $holder_exits );
		$started = microtime( true );
		$result  = Cache::get_or_fetch(
			$key,
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array(),
				);
			}
		);
		remove_filter( 'pre_transient_' . $key, $holder_exits );

		$this->assertSame( 'busy', $result['error'] );
		$this->assertLessThan( 3, microtime( true ) - $started );
	}

	/**
	 * Stores an entry older than a 300-second caller's limit.
	 *
	 * @param string $key Cache key.
	 */
	protected function seed_stale_entry( $key ) {
		set_transient(
			$key,
			array(
				'data'       => array(
					'ok'   => true,
					'data' => array( 'old' ),
				),
				'fetched_at' => time() - 600,
				'expires_at' => time() + 80000,
			),
			80000
		);
	}

	public function test_failed_refresh_keeps_the_valid_entry() {
		$this->seed_stale_entry( 'nrstx_phpunit_keep' );
		$result = Cache::get_or_fetch(
			'nrstx_phpunit_keep',
			300,
			function () {
				return array(
					'ok'    => false,
					'error' => 'upstream_limit',
				);
			}
		);
		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'old' ), $result['data'] );
		$this->assertTrue( Cache::get( 'nrstx_phpunit_keep' )['data']['ok'] );
		$this->assertFalse( get_transient( 'nrstx_phpunit_keep_lock' ) );
	}

	public function test_entry_past_its_ttl_is_refreshed_but_survives_a_failure() {
		$succeed = function () {
			return array(
				'ok'   => true,
				'data' => array( 'first' ),
			);
		};
		Cache::get_or_fetch( 'nrstx_phpunit_grace', 300, $succeed );
		$entry               = Cache::get( 'nrstx_phpunit_grace' );
		$entry['fetched_at'] = time() - 301;
		set_transient( 'nrstx_phpunit_grace', $entry, 3000 );

		$calls  = 0;
		$result = Cache::get_or_fetch(
			'nrstx_phpunit_grace',
			300,
			function () use ( &$calls ) {
				++$calls;
				return array(
					'ok'    => false,
					'error' => 'upstream_limit',
				);
			}
		);
		$this->assertSame( 1, $calls );
		$this->assertSame( array( 'first' ), $result['data'] );
	}

	public function test_failed_refresh_backs_off_upstream() {
		$this->seed_stale_entry( 'nrstx_phpunit_backoff' );
		$calls   = 0;
		$fetcher = function () use ( &$calls ) {
			++$calls;
			return array(
				'ok'    => false,
				'error' => 'upstream_limit',
			);
		};
		Cache::get_or_fetch( 'nrstx_phpunit_backoff', 300, $fetcher );
		$second = Cache::get_or_fetch( 'nrstx_phpunit_backoff', 300, $fetcher );
		$this->assertSame( 1, $calls );
		$this->assertSame( array( 'old' ), $second['data'] );

		// Once a refresh succeeds, the entry is fresh and no longer stale.
		delete_transient( 'nrstx_phpunit_backoff_retry' );
		$fresh = Cache::get_or_fetch(
			'nrstx_phpunit_backoff',
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'new' ),
				);
			}
		);
		$this->assertSame( array( 'new' ), $fresh['data'] );
	}

	public function test_stale_entry_is_served_at_once_while_another_request_refreshes() {
		$this->seed_stale_entry( 'nrstx_phpunit_inflight' );
		set_transient( 'nrstx_phpunit_inflight_lock', 1, 15 );
		$started = microtime( true );
		$result  = Cache::get_or_fetch(
			'nrstx_phpunit_inflight',
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'must-not-fetch' ),
				);
			}
		);
		$this->assertSame( array( 'old' ), $result['data'] );
		$this->assertLessThan( 1, microtime( true ) - $started );
	}

	public function test_budget_refusal_serves_the_valid_entry() {
		$this->seed_stale_entry( 'nrstx_phpunit_budget' );
		Cache::set_fetch_guard( '__return_false' );
		$result = Cache::get_or_fetch(
			'nrstx_phpunit_budget',
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'new' ),
				);
			}
		);
		Cache::set_fetch_guard( null );
		$this->assertSame( array( 'old' ), $result['data'] );
	}

	public function test_short_ttl_refresh_keeps_the_longer_expiry() {
		$this->seed_stale_entry( 'nrstx_phpunit_expiry' );
		Cache::get_or_fetch(
			'nrstx_phpunit_expiry',
			300,
			function () {
				return array(
					'ok'   => true,
					'data' => array( 'new' ),
				);
			}
		);
		$entry = Cache::get( 'nrstx_phpunit_expiry' );
		$this->assertSame( array( 'new' ), $entry['data']['data'] );
		$this->assertGreaterThan( time() + 70000, $entry['expires_at'] );
	}

	public function test_lock_is_released_after_fetching() {
		$fetcher = function () {
			return array(
				'ok'   => true,
				'data' => array(),
			);
		};
		Cache::get_or_fetch( 'nrstx_phpunit_release', 300, $fetcher );
		$this->assertFalse( get_transient( 'nrstx_phpunit_release_lock' ) );
	}

	public function test_throttle_window_keeps_its_original_expiry() {
		$this->assertSame( 0, Cache::throttle( 'nrstx_phpunit_rl', 2, 60 ) );
		$reset = get_transient( 'nrstx_phpunit_rl' )['reset'];
		$this->assertSame( 0, Cache::throttle( 'nrstx_phpunit_rl', 2, 60 ) );
		$this->assertSame( $reset, get_transient( 'nrstx_phpunit_rl' )['reset'] );
		$this->assertGreaterThan( 0, Cache::throttle( 'nrstx_phpunit_rl', 2, 60 ) );
	}

	public function test_throttle_resets_after_the_window() {
		set_transient(
			'nrstx_phpunit_rl_old',
			array(
				'count' => 99,
				'reset' => time() - 1,
			),
			60
		);
		$this->assertSame( 0, Cache::throttle( 'nrstx_phpunit_rl_old', 2, 60 ) );
		$this->assertSame( 1, get_transient( 'nrstx_phpunit_rl_old' )['count'] );
	}

	public function test_fetch_guard_refuses_misses_but_serves_hits() {
		$fetcher = function () {
			return array(
				'ok'   => true,
				'data' => array( 'rows' ),
			);
		};
		Cache::get_or_fetch( 'nrstx_phpunit_guard_hit', 300, $fetcher );
		Cache::set_fetch_guard( '__return_false' );
		$hit  = Cache::get_or_fetch( 'nrstx_phpunit_guard_hit', 300, $fetcher );
		$miss = Cache::get_or_fetch( 'nrstx_phpunit_guard_miss', 300, $fetcher );
		Cache::set_fetch_guard( null );

		$this->assertTrue( $hit['ok'] );
		$this->assertSame( 'upstream_budget', $miss['error'] );
		$this->assertNull( Cache::get( 'nrstx_phpunit_guard_miss' ) );
		$this->assertFalse( get_transient( 'nrstx_phpunit_guard_miss_lock' ) );
	}
}
