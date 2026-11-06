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
		register_block_type(
			$dir,
			array(
				'render_callback' => array( self::class, 'render' ),
			)
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
