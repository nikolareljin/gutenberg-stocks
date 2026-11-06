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
