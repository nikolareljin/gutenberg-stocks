<?php
/**
 * Tests for the [nr-stocks] shortcode render.
 */

use Nikolareljin\NrStocks\Tests\Mocks_Http;

class NR_Stocks_Shortcode_Test extends WP_UnitTestCase {

	use Mocks_Http;

	public function set_up() {
		parent::set_up();
		update_option(
			NR_STOCKS_OPTION,
			array(
				'keys' => array( 'alphavantage' => 'TESTAV' ),
			)
		);
	}

	public function test_renders_figure_with_baked_json_and_fallback() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$html = do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage"]' );

		$this->assertStringContainsString( '<figure class="nr-stocks"', $html );
		$this->assertStringContainsString( 'nr-stocks-data', $html );
		$this->assertStringContainsString( '<li>IBM:', $html );

		$this->assertSame( 1, preg_match( '#<script type="application/json" class="nr-stocks-data">(.*?)</script>#s', $html, $matches ) );
		$payload = json_decode( $matches[1], true );
		$this->assertSame( 'alphavantage', $payload['provider'] );
		$this->assertCount( 3, $payload['symbols']['IBM']['rows'] );
	}

	public function test_quote_flag_adds_change_to_fallback() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$this->mock_http( self::fixture( 'av_quote.csv' ) );
		$html = do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage" quote="1"]' );

		$this->assertStringContainsString( '+1.90%', $html );
	}

	public function test_all_symbols_failing_renders_notice() {
		$this->mock_http( self::fixture( 'av_note.json' ) );
		$html = do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage"]' );

		$this->assertStringContainsString( 'nr-stocks-error', $html );
		$this->assertStringContainsString( 'unavailable', $html );
	}

	public function test_no_symbols_renders_nothing() {
		$this->assertSame( '', do_shortcode( '[nr-stocks symbols=""]' ) );
		$this->assertSame( '', Nikolareljin\NrStocks\Block::render( array( 'symbols' => array() ) ) );
	}

	public function test_baked_payload_escapes_upstream_markup() {
		update_option( NR_STOCKS_OPTION, array( 'keys' => array( 'twelvedata' => 'TESTTD' ) ) );
		$body = str_replace( 'USD', '</script><b>', self::fixture( 'td_series.json' ) );
		$this->mock_http( $body );
		$html = do_shortcode( '[nr-stocks symbols="AAPL" provider="twelvedata"]' );
		preg_match( '@<script type="application/json" class="nr-stocks-data">(.*?)</script>@s', $html, $match );
		$this->assertNotEmpty( $match );
		$this->assertStringNotContainsString( '<b>', $match[1] );
		$this->assertStringContainsString( '</script><b>', json_decode( $match[1], true )['symbols']['AAPL']['currency'] );
	}

	public function test_legacy_scan_runs_after_blocks_render() {
		$priority = has_filter( 'the_content', array( Nikolareljin\NrStocks\Block::class, 'load_for_legacy_markup' ) );
		$this->assertGreaterThan( has_filter( 'the_content', 'do_blocks' ), $priority );
	}

	public function test_temporary_failure_marks_the_page_uncacheable() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		$this->mock_http( '', 500 );
		do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage"]' );
		$this->assertSame( $before + 1, did_action( 'nr_stocks_page_uncacheable' ) );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) );
	}

	public function test_partial_failure_marks_the_page_uncacheable() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$this->mock_http( '', 500 );
		do_shortcode( '[nr-stocks symbols="IBM,MSFT" provider="alphavantage"]' );
		$this->assertGreaterThan( $before, did_action( 'nr_stocks_page_uncacheable' ) );
	}

	public function test_temporary_quote_failure_marks_the_page_uncacheable() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$this->mock_http( self::fixture( 'av_note_multiline.json' ) );
		do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage" quote="1"]' );
		$this->assertSame( $before + 1, did_action( 'nr_stocks_page_uncacheable' ) );
	}

	public function test_permanent_failure_leaves_page_caching_alone() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		do_shortcode( '[nr-stocks symbols="AAPL" provider="frankfurter"]' );
		$this->assertSame( $before, did_action( 'nr_stocks_page_uncacheable' ) );
	}

	public function test_rejected_symbol_leaves_page_caching_alone() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		$this->mock_http( "{\n    \"Error Message\": \"Invalid API call.\"\n}" );
		do_shortcode( '[nr-stocks symbols="NOPE" provider="alphavantage"]' );
		$this->assertSame( $before, did_action( 'nr_stocks_page_uncacheable' ) );
	}

	public function test_success_leaves_page_caching_alone() {
		$before = did_action( 'nr_stocks_page_uncacheable' );
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage"]' );
		$this->assertSame( $before, did_action( 'nr_stocks_page_uncacheable' ) );
	}

	public function test_only_temporary_errors_are_kept_out_of_page_caches() {
		$renderer = Nikolareljin\NrStocks\Renderer::class;
		foreach ( array( 'busy', 'http_error', 'upstream_limit', 'upstream_budget', 'parse_error', 'fetch_failed' ) as $code ) {
			$this->assertTrue( $renderer::is_temporary_error( $code ), $code );
		}
		foreach ( array( 'unsupported_series', 'unsupported_pair', 'bad_symbol', 'unknown_provider', 'missing_key', 'forbidden_host', 'upstream_error' ) as $code ) {
			$this->assertFalse( $renderer::is_temporary_error( $code ), $code );
		}
	}

	public function test_legacy_markup_detection() {
		$block = Nikolareljin\NrStocks\Block::class;
		$this->assertTrue( $block::has_legacy_markup( '<div class="stock-information-item" data-symbol="IBM"></div>' ) );
		$this->assertFalse( $block::has_legacy_markup( '<p>Plain post</p>' ) );
	}

	public function test_baked_payload_carries_translated_chart_labels() {
		$this->mock_http( self::fixture( 'av_daily.csv' ) );
		$html = do_shortcode( '[nr-stocks symbols="IBM" provider="alphavantage"]' );
		$this->assertStringContainsString( '"labels":{"chart":"Price chart","noData":"No data"}', $html );
	}
}
