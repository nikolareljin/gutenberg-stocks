<?php
/**
 * Tests for the uninstall handler.
 */

use Nikolareljin\NrStocks\Cache;

class NR_Stocks_Uninstall_Test extends WP_UnitTestCase {

	/**
	 * Stores everything the plugin can leave behind on the current site.
	 */
	protected function seed_site() {
		update_option( 'nr_stocks_settings', array( 'keys' => array( 'alphavantage' => 'SECRET' ) ) );
		update_option( 'nr_stocks_migrated', 1 );
		update_option( 'stockinfo_options_api', array( 'stockinfo_api_token' => 'OLD' ) );
		Cache::set( 'nrstx_phpunit_uninstall', array( 'data' ), 300 );
	}

	/**
	 * Asserts the current site holds none of the plugin's data.
	 */
	protected function assert_site_is_clean() {
		foreach ( array( 'nr_stocks_settings', 'nr_stocks_cache_index', 'nr_stocks_migrated', 'stockinfo_options_api' ) as $option ) {
			$this->assertFalse( get_option( $option, false ), $option );
		}
		$this->assertNull( Cache::get( 'nrstx_phpunit_uninstall' ) );
	}

	public function test_uninstall_removes_data_from_every_site() {
		$this->seed_site();
		$other_site = 0;
		if ( is_multisite() ) {
			$other_site = self::factory()->blog->create();
			switch_to_blog( $other_site );
			$this->seed_site();
			restore_current_blog();
		}

		define( 'WP_UNINSTALL_PLUGIN', 'nr-stocks/nr-stocks.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assert_site_is_clean();
		if ( $other_site ) {
			switch_to_blog( $other_site );
			$this->assert_site_is_clean();
			restore_current_blog();
		}
	}
}
