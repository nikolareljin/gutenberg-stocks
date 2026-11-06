<?php
/**
 * Tests for the nr-stocks/v1 REST routes (offline via HTTP mocks).
 */

use Nikolareljin\NrStocks\Tests\Mocks_Http;

class NR_Stocks_Rest_Test extends WP_UnitTestCase {

	use Mocks_Http;

	/**
	 * Whether routes were registered in this process.
	 *
	 * @var bool
	 */
	protected static $routes_ready = false;

	public function set_up() {
		parent::set_up();
		update_option(
			NR_STOCKS_OPTION,
			array(
				'keys'            => array(
					'alphavantage' => 'TESTAV',
					'twelvedata'   => 'TESTTD',
				),
				'rest_rate_limit' => 3600,
			)
		);
		if ( ! self::$routes_ready ) {
			do_action( 'rest_api_init' );
			self::$routes_ready = true;
		}
	}

	/**
	 * Dispatches a GET request.
	 *
	 * @param string $route  Route path.
	 * @param array  $params Query params.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function get( $route, $params = array() ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	public function test_providers_lists_descriptors_without_secrets() {
		$response = $this->get( '/nr-stocks/v1/providers' );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 5, $data['data'] );
		$this->assertTrue( $data['data'][0]['has_key'] );
		$this->assertFalse( $data['data'][2]['has_key'] );
		$json = wp_json_encode( $data );
		$this->assertStringNotContainsString( 'TESTAV', $json );
		$this->assertStringNotContainsString( 'hosts', $json );
	}

	public function test_series_envelope_and_cache_headers() {
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$first = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL',
			)
		);
		$this->assertSame( 200, $first->get_status() );
		$data = $first->get_data();
		$this->assertTrue( $data['data']['AAPL']['ok'] );
		$this->assertCount( 3, $data['data']['AAPL']['data'] );
		$headers = $first->get_headers();
		$this->assertStringContainsString( 'max-age=300', $headers['Cache-Control'] );
		$this->assertArrayHasKey( 'ETag', $headers );
		$this->assertSame( 'MISS', $headers['X-NR-Stocks-Cache'] );

		$second = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL',
			)
		);
		$this->assertSame( 'HIT', $second->get_headers()['X-NR-Stocks-Cache'] );
	}

	public function test_quote_and_search_routes() {
		$this->mock_http( self::fixture( 'av_quote.csv' ) );
		$quote = $this->get(
			'/nr-stocks/v1/quote',
			array(
				'provider' => 'alphavantage',
				'symbol'   => 'IBM',
			)
		);
		$this->assertSame( 200, $quote->get_status() );
		$this->assertSame( 142.2, $quote->get_data()['data']['IBM']['data']['price'] );

		$this->mock_http( self::fixture( 'td_search.json' ) );
		$search = $this->get(
			'/nr-stocks/v1/search',
			array(
				'provider' => 'twelvedata',
				'q'        => 'apple',
			)
		);
		$this->assertSame( 200, $search->get_status() );
		$this->assertSame( 'AAPL', $search->get_data()['data'][0]['symbol'] );
	}

	public function test_validation_errors() {
		$bad_series = $this->get(
			'/nr-stocks/v1/series',
			array(
				'symbols' => 'AAPL',
				'series'  => 'banana',
			)
		);
		$this->assertSame( 400, $bad_series->get_status() );
		$this->assertSame( 'rest_invalid_param', $bad_series->get_data()['code'] );

		$empty = $this->get( '/nr-stocks/v1/series', array( 'symbols' => '!!!' ) );
		$this->assertSame( 400, $empty->get_status() );
		$this->assertSame( 'nr_stocks_bad_symbol', $empty->get_data()['code'] );
	}

	public function test_missing_key_reported_in_envelope() {
		update_option( NR_STOCKS_OPTION, array( 'keys' => array() ) );
		$response = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'alphavantage',
				'symbols'  => 'IBM',
			)
		);
		// Multi-symbol routes always answer 200; failures stay per-symbol.
		$this->assertSame( 200, $response->get_status() );
		$ibm = $response->get_data()['data']['IBM'];
		$this->assertFalse( $ibm['ok'] );
		$this->assertSame( 'missing_key', $ibm['error'] );
	}

	public function test_rate_limit_returns_429() {
		$settings                    = Nikolareljin\NrStocks\Settings::get();
		$settings['rest_rate_limit'] = 2;
		update_option( NR_STOCKS_OPTION, $settings );

		$this->assertSame( 200, $this->get( '/nr-stocks/v1/providers' )->get_status() );
		$this->assertSame( 200, $this->get( '/nr-stocks/v1/providers' )->get_status() );
		$limited = $this->get( '/nr-stocks/v1/providers' );
		$this->assertSame( 429, $limited->get_status() );
		$this->assertSame( 'nr_stocks_rate_limited', $limited->get_data()['code'] );
	}

	public function test_anonymous_ttl_is_ignored() {
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$response = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL',
				'ttl'      => 86400,
			)
		);
		$this->assertStringContainsString( 'max-age=300', $response->get_headers()['Cache-Control'] );
	}

	public function test_editor_ttl_is_honored() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$response = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL',
				'ttl'      => 900,
			)
		);
		$this->assertStringContainsString( 'max-age=900', $response->get_headers()['Cache-Control'] );
	}

	public function test_failed_symbols_are_not_stored_downstream() {
		update_option( NR_STOCKS_OPTION, array( 'keys' => array() ) );
		$response = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'alphavantage',
				'symbols'  => 'IBM',
			)
		);
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_anonymous_upstream_budget_stops_misses_and_clears_the_guard() {
		$settings                    = Nikolareljin\NrStocks\Settings::get();
		$settings['rest_miss_limit'] = 1;
		update_option( NR_STOCKS_OPTION, $settings );
		$this->mock_http( self::fixture( 'td_series.json' ) );

		$response = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL,MSFT',
			)
		);
		$data     = $response->get_data()['data'];
		$this->assertTrue( $data['AAPL']['ok'] );
		$this->assertSame( 'upstream_budget', $data['MSFT']['error'] );
		$this->assertCount( 1, $this->mock_calls );

		// Server-side rendering runs outside the handlers and stays unlimited.
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$rendered = Nikolareljin\NrStocks\Providers\Rest_Provider::series( 'twelvedata', 'TSLA', 'daily', 30 );
		$this->assertTrue( $rendered['ok'] );
	}

	public function test_editors_are_exempt_from_the_upstream_budget() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$settings                    = Nikolareljin\NrStocks\Settings::get();
		$settings['rest_miss_limit'] = 1;
		update_option( NR_STOCKS_OPTION, $settings );
		$this->mock_http( self::fixture( 'td_series.json' ) );
		$this->mock_http( self::fixture( 'td_series.json' ) );

		$data = $this->get(
			'/nr-stocks/v1/series',
			array(
				'provider' => 'twelvedata',
				'symbols'  => 'AAPL,MSFT',
			)
		)->get_data()['data'];
		$this->assertTrue( $data['AAPL']['ok'] );
		$this->assertTrue( $data['MSFT']['ok'] );
	}

	public function test_rate_limit_retry_after_counts_down_and_ip_is_filterable() {
		$settings                    = Nikolareljin\NrStocks\Settings::get();
		$settings['rest_rate_limit'] = 1;
		update_option( NR_STOCKS_OPTION, $settings );

		$this->assertSame( 200, $this->get( '/nr-stocks/v1/providers' )->get_status() );
		$limited = $this->get( '/nr-stocks/v1/providers' );
		$this->assertSame( 429, $limited->get_status() );
		$this->assertLessThanOrEqual( 60, $limited->get_data()['data']['retry_after'] );

		$other_client = function () {
			return '203.0.113.9';
		};
		add_filter( 'nr_stocks_client_ip', $other_client );
		$this->assertSame( 200, $this->get( '/nr-stocks/v1/providers' )->get_status() );
		remove_filter( 'nr_stocks_client_ip', $other_client );
	}

	public function test_error_headers_on_own_routes_only() {
		$routes  = Nikolareljin\NrStocks\Rest\Routes::class;
		$limited = new WP_REST_Response( array( 'data' => array( 'retry_after' => 12 ) ), 429 );
		$own     = new WP_REST_Request( 'GET', '/nr-stocks/v1/series' );
		$headers = $routes::error_headers( $limited, null, $own )->get_headers();
		$this->assertSame( 'no-store', $headers['Cache-Control'] );
		$this->assertSame( '12', $headers['Retry-After'] );

		$other = new WP_REST_Response( array(), 404 );
		$core  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$this->assertSame( array(), $routes::error_headers( $other, null, $core )->get_headers() );
	}

	public function test_jsonp_is_disabled_on_own_routes_only() {
		global $wp;
		$routes = Nikolareljin\NrStocks\Rest\Routes::class;
		$saved  = $wp->query_vars;

		$wp->query_vars['rest_route'] = '/NR-Stocks/v1/series';
		$this->assertFalse( $routes::jsonp_enabled( true ) );
		$wp->query_vars['rest_route'] = '/wp/v2/posts';
		$this->assertTrue( $routes::jsonp_enabled( true ) );

		$wp->query_vars = $saved;
	}

	public function test_cors_decision_uses_site_and_allowlist() {
		$settings                      = Nikolareljin\NrStocks\Settings::get();
		$settings['rest_cors_origins'] = array( 'app.example.com' );
		update_option( NR_STOCKS_OPTION, $settings );
		$routes = Nikolareljin\NrStocks\Rest\Routes::class;

		$this->assertSame( 'allow', $routes::cors_decision( 'https://app.example.com' ) );
		$this->assertSame( 'deny', $routes::cors_decision( 'https://unlisted.example' ) );
		$this->assertSame( 'deny', $routes::cors_decision( 'null' ) );
		$this->assertSame( 'same', $routes::cors_decision( home_url() ) );
		$this->assertSame( 'deny', $routes::cors_decision( home_url() . ':8443' ) );
		$this->assertTrue( $routes::is_own_route( '/nr-stocks/v1/series' ) );
		$this->assertTrue( $routes::is_own_route( '/NR-Stocks/v1/series' ) );
		$this->assertTrue( $routes::is_own_route( '/nr-stocks/v1' ) );
		$this->assertFalse( $routes::is_own_route( '/nr-stocks/v10/series' ) );
		$this->assertFalse( $routes::is_own_route( '/wp/v2/posts' ) );
	}
}
