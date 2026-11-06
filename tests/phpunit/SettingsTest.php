<?php
/**
 * Tests for settings sanitization.
 */

use Nikolareljin\NrStocks\Settings;

class NR_Stocks_Settings_Test extends WP_UnitTestCase {

	public function test_sanitize_falls_back_on_unknown_provider() {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'default_provider' => 'nope',
				'keys'             => array( 'alphavantage' => 'K<script>' ),
				'ttls'             => array(
					'series' => '99999',
					'quote'  => '7',
				),
				'line_color'       => 'not-a-color',
			)
		);
		$this->assertSame( 'alphavantage', $clean['default_provider'] );
		$this->assertSame( 'K', $clean['keys']['alphavantage'] );
		$this->assertSame( 86400, $clean['ttls']['series'] );
		$this->assertSame( 7, $clean['ttls']['quote'] );
		$this->assertSame( 3600, $clean['ttls']['search'] );
		$this->assertSame( '#0b163b', $clean['line_color'] );
	}

	public function test_sanitize_keeps_valid_values() {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'default_provider'  => 'frankfurter',
				'line_color'        => '#ff0000',
				'rest_rate_limit'   => '120',
				'rest_cors_origins' => "https://app.example.com/\nfoo.test, BAR.EXAMPLE.COM\n",
				'av_entrypoint'     => 'Query!',
			)
		);
		$this->assertSame( 'frankfurter', $clean['default_provider'] );
		$this->assertSame( '#ff0000', $clean['line_color'] );
		$this->assertSame( 120, $clean['rest_rate_limit'] );
		$this->assertSame(
			array( 'app.example.com', 'foo.test', 'bar.example.com' ),
			$clean['rest_cors_origins']
		);
		$this->assertSame( 'query', $clean['av_entrypoint'] );
	}

	public function test_get_merges_saved_values_over_defaults() {
		update_option(
			NR_STOCKS_OPTION,
			array(
				'keys' => array( 'twelvedata' => 'TDKEY' ),
			)
		);
		$settings = Settings::get();
		$this->assertSame( 'TDKEY', $settings['keys']['twelvedata'] );
		$this->assertSame( '', $settings['keys']['alphavantage'] );
		$this->assertSame( 300, $settings['ttls']['series'] );
		$this->assertSame( 'alphavantage', $settings['default_provider'] );
	}

	public function test_get_key_returns_empty_for_keyless_providers() {
		$this->assertSame( '', Settings::get_key( 'frankfurter' ) );
		$this->assertSame( '', Settings::get_key( 'nope' ) );
		update_option(
			NR_STOCKS_OPTION,
			array(
				'keys' => array( 'alphavantage' => 'AVKEY' ),
			)
		);
		$this->assertSame( 'AVKEY', Settings::get_key( 'alphavantage' ) );
	}
}
