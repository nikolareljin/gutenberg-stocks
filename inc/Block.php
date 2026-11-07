<?php
/**
 * Block registration (block.json metadata + server render callback).
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Stock Chart block and its view assets.
 */
class Block {

	/**
	 * View script handle.
	 *
	 * @var string
	 */
	const VIEW_HANDLE = 'nr-stocks-view';

	/**
	 * View/frontend style handle.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'nr-stocks-style';

	/**
	 * Class on charts saved by the retired 0.x block.
	 *
	 * @var string
	 */
	const LEGACY_CLASS = 'stock-information-item';

	/**
	 * Registers the block type on init.
	 */
	public static function register() {
		$dir = NR_STOCKS_DIR . 'build/blocks/stock-info';
		if ( ! is_readable( $dir . '/block.json' ) ) {
			$dir = NR_STOCKS_DIR . 'src/blocks/stock-info';
		}
		if ( ! function_exists( 'register_block_type' ) || ! is_readable( $dir . '/block.json' ) ) {
			return;
		}
		self::register_jsx_runtime_fallback();
		register_block_type(
			$dir,
			array(
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	/**
	 * Registers `react-jsx-runtime` where WordPress does not (before 6.6).
	 *
	 * The editor build imports the automatic JSX runtime. Without this handle,
	 * WordPress 6.3 to 6.5 drop the editor script and the block is missing.
	 */
	public static function register_jsx_runtime_fallback() {
		if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			return;
		}
		wp_register_script( 'react-jsx-runtime', false, array( 'react' ), NR_STOCKS_VERSION, false );
		wp_add_inline_script(
			'react-jsx-runtime',
			'window.ReactJSXRuntime=window.ReactJSXRuntime||(function(R){function jsx(t,p,k){return R.createElement(t,k===undefined?p:Object.assign({},p,{key:k}));}return{jsx:jsx,jsxs:jsx,Fragment:R.Fragment};})(window.React);'
		);
	}

	/**
	 * Server-side block render: maps attributes to the shared renderer.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		return Renderer::render_chart(
			array(
				'provider'   => isset( $attributes['provider'] ) ? $attributes['provider'] : '',
				'symbols'    => isset( $attributes['symbols'] ) ? $attributes['symbols'] : array(),
				'series'     => isset( $attributes['series'] ) ? $attributes['series'] : 'daily',
				'range'      => isset( $attributes['range'] ) ? $attributes['range'] : 30,
				'show_quote' => ! empty( $attributes['showQuote'] ),
				'ttl'        => isset( $attributes['cacheTtl'] ) ? $attributes['cacheTtl'] : 0,
			)
		);
	}

	/**
	 * Loads the view script when content holds charts saved by the 0.x block.
	 *
	 * @param string $content Post content.
	 * @return string Unchanged content.
	 */
	public static function load_for_legacy_markup( $content ) {
		static $done = false;
		if ( $done || is_admin() || ! self::has_legacy_markup( $content ) ) {
			return $content;
		}
		$done = true;
		self::enqueue_view_assets();
		if ( wp_script_is( self::VIEW_HANDLE, 'registered' ) ) {
			wp_add_inline_script(
				self::VIEW_HANDLE,
				'window.nrStocks = ' . wp_json_encode( array( 'restBase' => rest_url( NR_STOCKS_REST_NAMESPACE ) ) ) . ';',
				'before'
			);
		}
		return $content;
	}

	/**
	 * Whether content holds charts saved by the 0.x block.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public static function has_legacy_markup( $content ) {
		return is_string( $content ) && false !== strpos( $content, self::LEGACY_CLASS );
	}

	/**
	 * Enqueues the frontend view script + styles (for shortcode contexts;
	 * blocks get them automatically via block.json viewScript/style).
	 */
	public static function enqueue_view_assets() {
		$js  = NR_STOCKS_DIR . 'build/blocks/stock-info/view.js';
		$css = NR_STOCKS_DIR . 'build/blocks/stock-info/style-index.css';
		if ( is_readable( $js ) ) {
			wp_register_script(
				self::VIEW_HANDLE,
				NR_STOCKS_URL . 'build/blocks/stock-info/view.js',
				array(),
				(string) filemtime( $js ),
				true
			);
			wp_enqueue_script( self::VIEW_HANDLE );
		}
		if ( is_readable( $css ) ) {
			wp_register_style(
				self::STYLE_HANDLE,
				NR_STOCKS_URL . 'build/blocks/stock-info/style-index.css',
				array(),
				(string) filemtime( $css )
			);
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}
}
