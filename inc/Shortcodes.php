<?php
/**
 * Classic shortcode: [nr-stocks symbols="AAPL,MSFT" ...].
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the shortcode through the shared renderer.
 */
class Shortcodes {

	/**
	 * Registers the shortcode.
	 */
	public static function register() {
		add_shortcode( 'nr-stocks', array( self::class, 'render' ) );
	}

	/**
	 * Renders [nr-stocks].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts               = shortcode_atts(
			array(
				'provider'   => '',
				'symbols'    => '',
				'symbol'     => '',
				'series'     => 'daily',
				'range'      => 30,
				'quote'      => '0',
				'show_quote' => '0',
				'ttl'        => 0,
			),
			$atts,
			'nr-stocks'
		);
		$atts['show_quote'] = $atts['show_quote'] || $atts['quote'];
		Block::enqueue_view_assets();
		return Renderer::render_chart( $atts );
	}
}
