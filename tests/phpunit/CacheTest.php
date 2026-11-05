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
}
