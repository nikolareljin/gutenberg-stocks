<?php
/**
 * Tests for the 0.x option migration.
 */

use Nikolareljin\NrStocks\Migration;
use Nikolareljin\NrStocks\Settings;

class NR_Stocks_Migration_Test extends WP_UnitTestCase {

	public function test_migrates_legacy_options() {
		delete_option( NR_STOCKS_OPTION );
		update_option(
			'stockinfo_options_api',
			array(
				'stockinfo_api_token'      => 'OLDKEY',
				'stockinfo_api_line_color' => '#123456',
				'stockinfo_api_host'       => 'https://mirror.example.com',
				'stockinfo_api_entrypoint' => 'custom',
			)
		);

		Migration::maybe_migrate();

		$settings = Settings::get();
		$this->assertSame( 'OLDKEY', $settings['keys']['alphavantage'] );
		$this->assertSame( '#123456', $settings['line_color'] );
		$this->assertSame( 'https://mirror.example.com', $settings['av_host'] );
		$this->assertSame( 'custom', $settings['av_entrypoint'] );
	}

	public function test_skips_default_host_and_entrypoint() {
		delete_option( NR_STOCKS_OPTION );
		update_option(
			'stockinfo_options_api',
			array(
				'stockinfo_api_host'       => 'https://www.alphavantage.co',
				'stockinfo_api_entrypoint' => 'query',
			)
		);

		Migration::maybe_migrate();

		$settings = Settings::get();
		$this->assertSame( '', $settings['av_host'] );
		$this->assertSame( '', $settings['av_entrypoint'] );
	}

	public function test_does_nothing_when_new_option_exists() {
		update_option( NR_STOCKS_OPTION, array( 'marker' => true ) );
		update_option( 'stockinfo_options_api', array( 'stockinfo_api_token' => 'OLDKEY' ) );

		Migration::maybe_migrate();

		$this->assertSame( array( 'marker' => true ), get_option( NR_STOCKS_OPTION ) );
	}

	public function test_does_nothing_without_legacy_option() {
		delete_option( NR_STOCKS_OPTION );
		delete_option( 'stockinfo_options_api' );

		Migration::maybe_migrate();

		$this->assertFalse( get_option( NR_STOCKS_OPTION, false ) );
	}
}
