<?php
/**
 * Tests for the config-driven provider executor (offline via HTTP mocks).
 */

use Nikolareljin\NrStocks\Providers\Rest_Provider;
use Nikolareljin\NrStocks\Tests\Mocks_Http;

class NR_Stocks_Rest_Provider_Test extends WP_UnitTestCase {

	use Mocks_Http;

	public function set_up() {
		parent::set_up();
		update_option(
			NR_STOCKS_OPTION,
			array(
				'keys' => array(
					'alphavantage' => 'TESTAV',
					'twelvedata'   => 'TESTTD',
				),
			)
		);
	}

	public function test_detect_asset() {
		$this->assertSame( 'equity', Rest_Provider::detect_asset( 'AAPL' ) );
		$this->assertSame( 'equity', Rest_Provider::detect_asset( 'BRK.B' ) );
		$this->assertSame( 'forex', Rest_Provider::detect_asset( 'EUR/USD' ) );
		$this->assertSame( 'crypto', Rest_Provider::detect_asset( 'BTC/USD' ) );
		$this->assertSame( 'crypto', Rest_Provider::detect_asset( 'BTC/EUR' ) );
	}

	public function test_clean_symbol() {
		$this->assertSame( 'AAPL', Rest_Provider::clean_symbol( 'aapl ' ) );
		$this->assertSame( 'EUR/USD', Rest_Provider::clean_symbol( 'eur/usd' ) );
		$this->assertSame( 'MSFT', Rest_Provider::clean_symbol( '<b>MSFT</b>$' ) );
		$this->assertSame( 32, strlen( Rest_Provider::clean_symbol( str_repeat( 'A', 40 ) ) ) );
	}

	public function test_av_daily_series() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$result = Rest_Provider::series( 'alphavantage', 'AAPL', 'daily', 30 );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 3, $result['data'] );
		$this->assertSame( '2026-10-28', $result['data'][0]['date'] );
		$this->assertSame( 343.83, $result['data'][0]['close'] );
		$this->assertSame( 35714300, $result['data'][0]['volume'] );

		$url = $this->mock_calls[0];
		$this->assertStringContainsString( 'function=TIME_SERIES_DAILY', $url );
		$this->assertStringContainsString( 'symbol=AAPL', $url );
		$this->assertStringContainsString( 'apikey=TESTAV', $url );
		$this->assertStringContainsString( 'datatype=csv', $url );
	}

	public function test_av_function_routing_and_interval_whitelist() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'IBM', 'weekly', 30 );
		$this->assertStringContainsString( 'function=TIME_SERIES_WEEKLY', $this->mock_calls[0] );

		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'IBM', 'intraday', 30, '9min' );
		$this->assertStringContainsString( 'interval=15min', $this->mock_calls[1] );

		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'IBM', 'intraday', 30, '5min' );
		$this->assertStringContainsString( 'interval=5min', $this->mock_calls[2] );

		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'EUR/USD', 'daily', 30 );
		$this->assertStringContainsString( 'function=FX_DAILY', $this->mock_calls[3] );
		$this->assertStringContainsString( 'from_symbol=EUR', $this->mock_calls[3] );
		$this->assertStringContainsString( 'to_symbol=USD', $this->mock_calls[3] );

		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'BTC/USD', 'daily', 30 );
		$this->assertStringContainsString( 'function=DIGITAL_CURRENCY_DAILY', $this->mock_calls[4] );
		$this->assertStringContainsString( 'symbol=BTC', $this->mock_calls[4] );
		$this->assertStringContainsString( 'market=USD', $this->mock_calls[4] );
		$this->assertStringContainsString( 'symbol=BTC&', $this->mock_calls[4] );
	}

	public function test_av_quote_strips_percent() {
		$this->mock_http( self::fixture( 'av_quote.csv' ) );
		$result = Rest_Provider::quote( 'alphavantage', 'IBM' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 142.2, $result['data']['price'] );
		$this->assertSame( 1.8992, $result['data']['change_percent'] );
		$this->assertSame( 139.55, $result['data']['previous_close'] );
	}

	public function test_av_search_maps_best_matches() {
		$this->mock_http( self::fixture( 'av_search.json' ) );
		$result = Rest_Provider::search( 'alphavantage', 'ibm' );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 2, $result['data'] );
		$this->assertSame( 'IBM', $result['data'][0]['symbol'] );
		$this->assertSame( 'International Business Machines Corp', $result['data'][0]['name'] );
		$this->assertSame( 'USD', $result['data'][0]['currency'] );
	}

	public function test_av_limit_note_maps_to_upstream_limit() {
		$this->mock_http( self::fixture( 'av_note.json' ) );
		$result = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 30 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'upstream_limit', $result['error'] );
	}

	public function test_twelvedata_series_quote_and_meta() {
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$result = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 30 );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 3, $result['data'] );
		$this->assertSame( '2026-11-02', $result['data'][0]['date'] );
		$this->assertSame( 'USD', $result['meta']['currency'] );
		$this->assertSame( 'NASDAQ', $result['meta']['exchange'] );
		$this->assertStringContainsString( 'api.twelvedata.com/time_series', $this->mock_calls[0] );
		$this->assertStringContainsString( 'interval=1day', $this->mock_calls[0] );
		$this->assertStringContainsString( 'apikey=TESTTD', $this->mock_calls[0] );

		$this->mock_http( self::fixture( 'td_quote.json' ) );
		$quote = Rest_Provider::quote( 'twelvedata', 'AAPL' );
		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 347.15, $quote['data']['price'] );
		$this->assertSame( -0.67523381, $quote['data']['change_percent'] );
		$this->assertSame( 'Apple Inc.', $quote['data']['name'] );
	}

	public function test_twelvedata_search_and_errors() {
		$this->mock_http( self::fixture( 'td_search.json' ) );
		$result = Rest_Provider::search( 'twelvedata', 'apple' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'AAPL', $result['data'][0]['symbol'] );
		$this->assertSame( 'Apple Inc.', $result['data'][0]['name'] );

		$this->mock_http( self::fixture( 'td_error.json' ) );
		$err = Rest_Provider::series( 'twelvedata', 'BOGUS', 'daily', 30 );
		$this->assertFalse( $err['ok'] );
		$this->assertSame( 'upstream_error', $err['error'] );

		$this->mock_http( '{"status":"error","message":"Rate limit exceeded"}' );
		$limited = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 30 );
		$this->assertSame( 'upstream_limit', $limited['error'] );
	}

	public function test_frankfurter_series_quote_search() {
		$this->mock_http( self::fixture( 'frankfurter.json' ) );
		$result = Rest_Provider::series( 'frankfurter', 'EUR/USD', 'daily', 30 );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 3, $result['data'] );
		$this->assertSame( 1.1326, $result['data'][0]['close'] );
		$this->assertNull( $result['data'][0]['volume'] );
		$this->assertStringContainsString( 'api.frankfurter.dev/v2/rates', $this->mock_calls[0] );
		$this->assertStringContainsString( 'base=EUR', $this->mock_calls[0] );
		$this->assertStringContainsString( 'quotes=USD', $this->mock_calls[0] );

		// Quote is synthesized from the series and shares its cached entry.
		$quote = Rest_Provider::quote( 'frankfurter', 'EUR/USD' );
		$this->assertCount( 1, $this->mock_calls );
		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 1.1282, $quote['data']['price'] );
		$this->assertTrue( $quote['meta']['synthetic'] );

		$this->mock_http( self::fixture( 'frankfurter_currencies.json' ) );
		$search = Rest_Provider::search( 'frankfurter', 'dollar' );
		$this->assertTrue( $search['ok'] );
		$this->assertSame( 'USD/EUR', $search['data'][0]['symbol'] );

		$this->mock_http( self::fixture( 'frankfurter_currencies.json' ) );
		$euro = Rest_Provider::search( 'frankfurter', 'euro' );
		$this->assertSame( 'EUR/USD', $euro['data'][0]['symbol'] );

		$this->mock_http( self::fixture( 'frankfurter_currencies.json' ) );
		$coins = Rest_Provider::search( 'frankfurter', 'coin' );
		$this->assertSame( array(), $coins['data'] );
	}

	public function test_coingecko_series_quote_and_local_search() {
		$this->mock_http( self::fixture( 'cg_history.json' ) );
		$result = Rest_Provider::series( 'coingecko', 'BTC/USD', 'daily', 30 );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 3, $result['data'] );
		$this->assertSame( '2026-11-03', $result['data'][0]['date'] );
		$this->assertSame( 77623.43, $result['data'][0]['close'] );
		$this->assertStringContainsString( '/coins/bitcoin/market_chart', $this->mock_calls[0] );
		$this->assertStringContainsString( 'vs_currency=usd', $this->mock_calls[0] );

		$this->mock_http( self::fixture( 'cg_spot.json' ) );
		$quote = Rest_Provider::quote( 'coingecko', 'BTC/USD' );
		$this->assertTrue( $quote['ok'] );
		$this->assertSame( 75749, $quote['data']['price'] );
		$this->assertSame( -5.72835777256067, $quote['data']['change_percent'] );

		$calls_before = count( $this->mock_calls );
		$search       = Rest_Provider::search( 'coingecko', 'bit' );
		$this->assertTrue( $search['ok'] );
		$this->assertSame( 'BTC/USD', $search['data'][0]['symbol'] );
		$this->assertCount( $calls_before, $this->mock_calls );
	}

	public function test_ecb_series_and_base_guard() {
		$this->mock_http( self::fixture( 'ecb.json' ) );
		$result = Rest_Provider::series( 'ecb', 'EUR/USD', 'daily', 30 );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 2, $result['data'] );
		$this->assertSame( '2026-11-04', $result['data'][0]['date'] );
		$this->assertSame( 1.1267, $result['data'][0]['close'] );
		$this->assertStringContainsString( 'EXR/D.USD.EUR.SP00.A', $this->mock_calls[0] );

		$calls_before = count( $this->mock_calls );
		$reversed     = Rest_Provider::series( 'ecb', 'USD/EUR', 'daily', 30 );
		$this->assertFalse( $reversed['ok'] );
		$this->assertSame( 'unsupported_pair', $reversed['error'] );
		$this->assertCount( $calls_before, $this->mock_calls );
	}

	public function test_error_paths() {
		$this->assertSame( 'unknown_provider', Rest_Provider::series( 'nope', 'AAPL' )['error'] );
		$this->assertSame( 'bad_symbol', Rest_Provider::series( 'twelvedata', '!!!' )['error'] );
		$this->assertSame( 'unsupported_series', Rest_Provider::series( 'frankfurter', 'EUR/USD', 'intraday' )['error'] );

		update_option( NR_STOCKS_OPTION, array( 'keys' => array() ) );
		$this->assertSame( 'missing_key', Rest_Provider::series( 'alphavantage', 'IBM' )['error'] );
	}

	public function test_forbidden_host_never_fetches() {
		add_filter(
			'nr_stocks_provider_config_twelvedata',
			function ( $config ) {
				$config['hosts'] = array();
				return $config;
			}
		);
		$result = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 30 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'forbidden_host', $result['error'] );
		$this->assertCount( 0, $this->mock_calls );
	}

	public function test_series_is_cached_and_range_sliced() {
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$first  = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 2 );
		$second = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 2 );
		$this->assertCount( 2, $first['data'] );
		$this->assertSame( '2026-11-03', $first['data'][0]['date'] );
		$this->assertFalse( $first['cached'] );
		$this->assertTrue( $second['cached'] );
		$this->assertCount( 1, $this->mock_calls );
	}

	public function test_multiline_av_note_is_a_limit_error_and_not_a_cached_success() {
		$body = self::fixture( 'av_note_multiline.json' );
		$this->mock_http( $body );
		$series = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 30 );
		$this->assertFalse( $series['ok'] );
		$this->assertSame( 'upstream_limit', $series['error'] );

		$this->mock_http( $body );
		$quote = Rest_Provider::quote( 'alphavantage', 'IBM' );
		$this->assertFalse( $quote['ok'] );
		$this->assertSame( 'upstream_limit', $quote['error'] );
	}

	public function test_synthetic_quote_keeps_the_series_error() {
		$this->mock_http( '', 500 );
		$quote = Rest_Provider::quote( 'frankfurter', 'EUR/USD' );
		$this->assertFalse( $quote['ok'] );
		$this->assertSame( 'http_error', $quote['error'] );
	}

	public function test_multiline_av_error_message_is_an_upstream_error() {
		$this->mock_http( "{\n    \"Error Message\": \"Invalid API call.\"\n}" );
		$result = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 30 );
		$this->assertSame( 'upstream_error', $result['error'] );
	}

	public function test_csv_without_expected_columns_is_a_parse_error() {
		$this->mock_http( "foo,bar\n1,2\n" );
		$result = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 30 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'parse_error', $result['error'] );
	}

	public function test_ranges_share_one_fetch_when_the_url_takes_no_range() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$short = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 1 );
		$long  = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 300 );
		$this->assertCount( 1, $this->mock_calls );
		$this->assertCount( 1, $short['data'] );
		$this->assertGreaterThan( 1, count( $long['data'] ) );
	}

	public function test_pairs_outside_the_fiat_list_reach_forex_providers() {
		$this->mock_http( self::fixture( 'frankfurter.json' ) );
		$listed = Rest_Provider::series( 'frankfurter', 'EUR/RON', 'daily', 30 );
		$this->assertTrue( $listed['ok'] );
		$this->assertSame( 'forex', $listed['meta']['asset'] );

		$this->mock_http( self::fixture( 'frankfurter.json' ) );
		$unlisted = Rest_Provider::series( 'frankfurter', 'EUR/XAF', 'daily', 30 );
		$this->assertTrue( $unlisted['ok'] );
		$this->assertSame( 'forex', $unlisted['meta']['asset'] );
		$this->assertStringContainsString( 'quotes=XAF', end( $this->mock_calls ) );
	}

	public function test_any_iso_currency_pair_uses_the_forex_endpoint() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$result = Rest_Provider::series( 'alphavantage', 'EUR/RSD', 'daily', 30 );
		$this->assertSame( 'forex', $result['meta']['asset'] );
		$this->assertStringContainsString( 'function=FX_DAILY', $this->mock_calls[0] );
	}

	public function test_coingecko_unmapped_coin_uses_the_base_as_id() {
		$this->mock_http( self::fixture( 'cg_history.json' ) );
		Rest_Provider::series( 'coingecko', 'PEPE/USD', 'daily', 30 );
		$this->assertStringContainsString( '/coins/pepe/market_chart', $this->mock_calls[0] );

		$this->mock_http( self::fixture( 'cg_history.json' ) );
		Rest_Provider::series( 'coingecko', '../USD', 'daily', 30 );
		$this->assertStringNotContainsString( '..', end( $this->mock_calls ) );
	}

	public function test_search_reports_upstream_limits() {
		$this->mock_http( self::fixture( 'av_note_multiline.json' ) );
		$result = Rest_Provider::search( 'alphavantage', 'ibm' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'upstream_limit', $result['error'] );
	}

	public function test_nearby_ranges_share_one_fetch_when_the_url_takes_a_range() {
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$short = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 2 );
		$long  = Rest_Provider::series( 'twelvedata', 'AAPL', 'daily', 20 );
		$this->assertCount( 1, $this->mock_calls );
		$this->assertStringContainsString( 'outputsize=30', $this->mock_calls[0] );
		$this->assertCount( 2, $short['data'] );
		$this->assertCount( 3, $long['data'] );
		$this->assertTrue( $long['cached'] );
	}

	public function test_coingecko_daily_series_has_one_row_per_day() {
		$start  = 1793721600000;
		$prices = array();
		for ( $hour = 0; $hour < 24 * 40; $hour++ ) {
			$prices[] = array( $start + $hour * 3600000, 100 + $hour );
		}
		$this->mock_http( wp_json_encode( array( 'prices' => $prices ) ) );
		$result = Rest_Provider::series( 'coingecko', 'BTC/USD', 'daily', 30 );
		$this->assertCount( 30, $result['data'] );
		$first = strtotime( $result['data'][0]['date'] );
		$last  = strtotime( $result['data'][29]['date'] );
		$this->assertGreaterThan( 28 * DAY_IN_SECONDS, $last - $first );
		$this->assertSame( (float) ( 100 + 24 * 40 - 1 ), (float) $result['data'][29]['close'] );
	}

	public function test_coingecko_midnight_point_closes_the_previous_day() {
		$midnight = gmmktime( 0, 0, 0, 11, 5, 2026 ) * 1000;
		$prices   = array(
			array( $midnight - 3600000, 10 ),
			array( $midnight, 20 ),
			array( $midnight + 3600000, 30 ),
		);
		$this->mock_http( wp_json_encode( array( 'prices' => $prices ) ) );
		$rows = Rest_Provider::series( 'coingecko', 'BTC/USD', 'daily', 30 )['data'];
		$this->assertSame( '2026-11-04', $rows[0]['date'] );
		$this->assertSame( 20, (int) $rows[0]['close'] );
		$this->assertSame( '2026-11-05', $rows[1]['date'] );
		$this->assertSame( 30, (int) $rows[1]['close'] );
	}

	/**
	 * Days between the from and to dates of a Frankfurter request.
	 *
	 * @param string $series Series id.
	 * @return int
	 */
	protected function frankfurter_window_days( $series ) {
		$this->mock_http( self::fixture( 'frankfurter.json' ) );
		Rest_Provider::series( 'frankfurter', 'EUR/USD', $series, 30 );
		$url = end( $this->mock_calls );
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		return (int) ( ( strtotime( $query['to'] ) - strtotime( $query['from'] ) ) / DAY_IN_SECONDS );
	}

	public function test_frankfurter_window_covers_the_requested_points_per_series() {
		$this->assertGreaterThanOrEqual( 30, $this->frankfurter_window_days( 'daily' ) );
		$this->assertGreaterThanOrEqual( 30 * 7, $this->frankfurter_window_days( 'weekly' ) );
		$this->assertGreaterThanOrEqual( 30 * 31, $this->frankfurter_window_days( 'monthly' ) );
	}

	public function test_unknown_intraday_intervals_share_the_default_entry() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		Rest_Provider::series( 'alphavantage', 'IBM', 'intraday', 30, 'aaa' );
		$second = Rest_Provider::series( 'alphavantage', 'IBM', 'intraday', 30, 'bbb' );
		$third  = Rest_Provider::series( 'alphavantage', 'IBM', 'intraday', 30, '15min' );
		$this->assertCount( 1, $this->mock_calls );
		$this->assertStringContainsString( 'interval=15min', $this->mock_calls[0] );
		$this->assertTrue( $second['cached'] );
		$this->assertTrue( $third['cached'] );
	}

	public function test_csv_with_dates_but_no_close_is_a_parse_error() {
		$this->mock_http( "timestamp,open\n2026-10-30,1\n" );
		$result = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 30 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'parse_error', $result['error'] );
	}

	public function test_unknown_intervals_and_nearby_ranges_share_one_fetch() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$first  = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 2, 'aaa' );
		$second = Rest_Provider::series( 'alphavantage', 'IBM', 'daily', 1, 'bbb' );
		$this->assertCount( 1, $this->mock_calls );
		$this->assertCount( 2, $first['data'] );
		$this->assertCount( 1, $second['data'] );
		$this->assertTrue( $second['cached'] );
		$this->assertSame( end( $first['data'] )['date'], end( $second['data'] )['date'] );
	}
}
