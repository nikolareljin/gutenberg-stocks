<?php
/**
 * Tests for block registration.
 */

use Nikolareljin\NrStocks\Block;

class NR_Stocks_Block_Test extends WP_UnitTestCase {

	public function test_editor_script_dependencies_are_registered() {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'nr-stocks/stock-chart' );
		$this->assertNotNull( $type );
		$handles = isset( $type->editor_script_handles ) ? $type->editor_script_handles : array( $type->editor_script );
		foreach ( $handles as $handle ) {
			foreach ( wp_scripts()->registered[ $handle ]->deps as $dep ) {
				$this->assertTrue( wp_script_is( $dep, 'registered' ), "Missing editor dependency: {$dep}" );
			}
		}
	}

	public function test_jsx_runtime_fallback_fills_a_missing_handle() {
		$core = wp_scripts()->query( 'react-jsx-runtime', 'registered' );
		wp_deregister_script( 'react-jsx-runtime' );
		Block::register_jsx_runtime_fallback();
		$this->assertTrue( wp_script_is( 'react-jsx-runtime', 'registered' ) );
		$this->assertStringContainsString( 'ReactJSXRuntime', implode( '', (array) wp_scripts()->get_data( 'react-jsx-runtime', 'after' ) ) );
		if ( $core ) {
			wp_deregister_script( 'react-jsx-runtime' );
			wp_scripts()->registered['react-jsx-runtime'] = $core;
		}
	}

	public function test_jsx_runtime_fallback_keeps_the_core_handle() {
		if ( ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			$this->markTestSkipped( 'WordPress before 6.6 has no core handle.' );
		}
		$src = wp_scripts()->registered['react-jsx-runtime']->src;
		Block::register_jsx_runtime_fallback();
		$this->assertSame( $src, wp_scripts()->registered['react-jsx-runtime']->src );
	}
}
