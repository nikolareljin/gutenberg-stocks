<?php
/**
 * Tests for the provider registry and its config-only extensibility.
 */

use Nikolareljin\NrStocks\Providers\Provider_Registry;
use Nikolareljin\NrStocks\Providers\Rest_Provider;

class NR_Stocks_Registry_Test extends WP_UnitTestCase {

	public function test_defaults_contain_the_five_verified_providers() {
		$this->assertSame(
			array( 'alphavantage', 'twelvedata', 'frankfurter', 'coingecko', 'ecb' ),
			Provider_Registry::ids()
		);
	}

	public function test_unknown_provider_returns_null() {
		$this->assertNull( Provider_Registry::get( 'nope' ) );
	}

	public function test_filter_registers_a_config_only_provider() {
		add_filter(
			'nr_stocks_providers',
			function ( $providers ) {
				$providers['dummy'] = array(
					'id'        => 'dummy',
					'label'     => 'Dummy',
					'needs_key' => false,
					'hosts'     => array( 'https://dummy.test' ),
					'series'    => array(
						'daily' => array(
							array(
								'assets' => array( 'equity' ),
								'url'    => '{host}/daily?s={symbol}',
								'format' => 'json',
								'rows'   => 'rows',
								'order'  => 'asc',
								'map'    => array(
									'date'  => 'd',
									'close' => 'c',
								),
							),
						),
					),
					'quote'     => null,
					'search'    => null,
				);
				return $providers;
			}
		);

		$config = Provider_Registry::get( 'dummy' );
		$this->assertSame( 'Dummy', $config['label'] );

		// The generic executor builds URLs for the new provider untouched.
		$url = Rest_Provider::build_url(
			$config,
			$config['series']['daily'][0],
			array( 'symbol' => 'AAA' )
		);
		$this->assertSame( 'https://dummy.test/daily?s=AAA', $url );
	}

	public function test_per_provider_override_filter() {
		add_filter(
			'nr_stocks_provider_config_frankfurter',
			function ( $config ) {
				$config['label'] = 'Custom Forex';
				return $config;
			}
		);
		$config = Provider_Registry::get( 'frankfurter' );
		$this->assertSame( 'Custom Forex', $config['label'] );
		$this->assertSame( 'Alpha Vantage', Provider_Registry::get( 'alphavantage' )['label'] );
	}

	public function test_describe_exposes_no_keys_or_hosts() {
		$items = Provider_Registry::describe();
		$this->assertCount( 5, $items );
		foreach ( $items as $item ) {
			$this->assertSame(
				array( 'id', 'label', 'needs_key', 'series', 'supports', 'rate_note' ),
				array_keys( $item )
			);
		}
		$td = $items[1];
		$this->assertSame( 'twelvedata', $td['id'] );
		$this->assertTrue( $td['needs_key'] );
		$this->assertArrayHasKey( 'daily', $td['series'] );
	}
}
