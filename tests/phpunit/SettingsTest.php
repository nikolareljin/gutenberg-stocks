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

	public function test_saved_key_is_not_printed_and_survives_an_empty_save() {
		$settings                      = new Settings();
		$saved                         = Settings::defaults();
		$saved['keys']['alphavantage'] = 'SECRETKEY';
		update_option( NR_STOCKS_OPTION, $saved );

		ob_start();
		$settings->render_key_field( array( 'provider_id' => 'alphavantage' ) );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'SECRETKEY', $html );
		$this->assertStringContainsString( 'keys_clear', $html );

		$kept = $settings->sanitize( array( 'keys' => array( 'alphavantage' => '' ) ) );
		$this->assertSame( 'SECRETKEY', $kept['keys']['alphavantage'] );

		$replaced = $settings->sanitize( array( 'keys' => array( 'alphavantage' => 'NEWKEY' ) ) );
		$this->assertSame( 'NEWKEY', $replaced['keys']['alphavantage'] );

		$cleared = $settings->sanitize(
			array(
				'keys'       => array( 'alphavantage' => '' ),
				'keys_clear' => array( 'alphavantage' => '1' ),
			)
		);
		$this->assertSame( '', $cleared['keys']['alphavantage'] );
	}

	public function test_custom_provider_key_survives_saving() {
		$add_provider = function ( $providers ) {
			$providers['myapi'] = array(
				'id'          => 'myapi',
				'label'       => 'My API',
				'needs_key'   => true,
				'key_setting' => 'myapi',
			);
			return $providers;
		};
		add_filter( 'nr_stocks_providers', $add_provider );
		$settings = new Settings();
		$clean    = $settings->sanitize( array( 'keys' => array( 'myapi' => 'MYKEY' ) ) );
		update_option( NR_STOCKS_OPTION, $clean );
		$key = Settings::get_key( 'myapi' );
		remove_filter( 'nr_stocks_providers', $add_provider );

		$this->assertSame( 'MYKEY', $key );
	}

	public function test_sanitize_is_stable_when_run_twice() {
		$settings = new Settings();
		$first    = $settings->sanitize( array( 'rest_cors_origins' => "app.example.com\nfoo.test" ) );
		$second   = $settings->sanitize( $first );
		$this->assertSame( array( 'app.example.com', 'foo.test' ), $second['rest_cors_origins'] );
		$this->assertSame( $first, $second );
	}

	public function test_saving_with_the_option_absent_keeps_cors_hosts() {
		delete_option( NR_STOCKS_OPTION );
		$settings = new Settings();
		register_setting( 'nr_stocks', NR_STOCKS_OPTION, array( $settings, 'sanitize' ) );
		update_option( NR_STOCKS_OPTION, array( 'rest_cors_origins' => 'app.example.com' ) );
		unregister_setting( 'nr_stocks', NR_STOCKS_OPTION );
		$this->assertSame( array( 'app.example.com' ), get_option( NR_STOCKS_OPTION )['rest_cors_origins'] );
	}

	public function test_sanitize_clamps_the_upstream_budget() {
		$settings = new Settings();
		$this->assertSame( 30, $settings->sanitize( array() )['rest_miss_limit'] );
		$this->assertSame( 0, $settings->sanitize( array( 'rest_miss_limit' => '0' ) )['rest_miss_limit'] );
		$this->assertSame( 3600, $settings->sanitize( array( 'rest_miss_limit' => '99999' ) )['rest_miss_limit'] );
	}

	public function test_every_setting_and_save_button_share_one_form() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$settings = new Settings();
		$settings->register_settings();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		$settings->render_page();
		$html = (string) ob_get_clean();

		$this->assertSame( 2, substr_count( $html, '<form' ) );
		$this->assertStringNotContainsString( 'display:none', $html );
		$first_form_end = strpos( $html, '</form>' );
		$last_field     = strrpos( $html, 'name="' . NR_STOCKS_OPTION . '[' );
		$save_button    = strpos( $html, 'name="submit"' );
		$purge_action   = strpos( $html, 'admin-post.php' );
		$this->assertLessThan( $first_form_end, $last_field );
		$this->assertLessThan( $first_form_end, $save_button );
		$this->assertGreaterThan( $first_form_end, $purge_action );
	}
}
