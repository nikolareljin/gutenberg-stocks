<?php
/**
 * One-time migration from the 0.x option schema.
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates stockinfo_options_api into nr_stocks_settings on first admin load.
 */
class Migration {

	/**
	 * Runs the migration when needed.
	 */
	public static function maybe_migrate() {
		if ( false !== get_option( NR_STOCKS_OPTION, false ) ) {
			return;
		}
		$old = get_option( 'stockinfo_options_api', array() );
		if ( ! is_array( $old ) || empty( $old ) ) {
			return;
		}

		$settings = Settings::defaults();
		if ( ! empty( $old['stockinfo_api_token'] ) ) {
			$settings['keys']['alphavantage'] = sanitize_text_field( $old['stockinfo_api_token'] );
		}
		if ( ! empty( $old['stockinfo_api_line_color'] ) ) {
			$color = sanitize_hex_color( $old['stockinfo_api_line_color'] );
			if ( $color ) {
				$settings['line_color'] = $color;
			}
		}
		if ( ! empty( $old['stockinfo_api_host'] ) && 'https://www.alphavantage.co' !== trim( $old['stockinfo_api_host'] ) ) {
			$settings['av_host'] = esc_url_raw( trim( $old['stockinfo_api_host'] ) );
		}
		if ( ! empty( $old['stockinfo_api_entrypoint'] ) && 'query' !== trim( $old['stockinfo_api_entrypoint'] ) ) {
			$settings['av_entrypoint'] = sanitize_key( trim( $old['stockinfo_api_entrypoint'] ) );
		}
		add_option( NR_STOCKS_OPTION, $settings );
	}
}
