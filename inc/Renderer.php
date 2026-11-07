<?php
/**
 * Shared server-side chart renderer (shortcode + block + REST preview).
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

use Nikolareljin\NrStocks\Providers\Provider_Registry;
use Nikolareljin\NrStocks\Providers\Rest_Provider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches market data and renders accessible chart markup with baked JSON.
 */
class Renderer {

	/**
	 * Renders the chart figure. Used by the shortcode and the block callback.
	 *
	 * @param array $args provider, symbols, series, range, show_quote, ttl.
	 * @return string HTML.
	 */
	public static function render_chart( $args ) {
		$args = self::normalize_args( $args );
		if ( empty( $args['symbols'] ) ) {
			// Nothing to chart yet, for example a block with no symbols added.
			return '';
		}
		$settings = Settings::get();
		$color    = isset( $settings['line_color'] ) ? $settings['line_color'] : '#0b163b';

		$symbols = array();
		$failed  = 0;
		foreach ( $args['symbols'] as $symbol ) {
			$entry = array(
				'rows'  => array(),
				'quote' => null,
				'error' => '',
			);
			$res   = Rest_Provider::series(
				$args['provider'],
				$symbol,
				$args['series'],
				$args['range'],
				'',
				$args['ttl']
			);
			if ( ! empty( $res['ok'] ) && ! empty( $res['data'] ) ) {
				$entry['rows'] = $res['data'];
				if ( ! empty( $res['meta']['currency'] ) ) {
					$entry['currency'] = $res['meta']['currency'];
				}
			} else {
				$entry['error'] = isset( $res['error'] ) ? $res['error'] : 'fetch_failed';
				++$failed;
			}
			if ( $args['show_quote'] && '' === $entry['error'] ) {
				$quote = Rest_Provider::quote( $args['provider'], $symbol, $args['ttl'] );
				if ( ! empty( $quote['ok'] ) ) {
					$entry['quote'] = $quote['data'];
				} elseif ( isset( $quote['error'] ) && self::is_temporary_error( $quote['error'] ) ) {
					self::prevent_page_caching();
				}
			}
			$symbols[ $symbol ] = $entry;
		}

		foreach ( $symbols as $entry ) {
			if ( '' !== $entry['error'] && self::is_temporary_error( $entry['error'] ) ) {
				self::prevent_page_caching();
				break;
			}
		}

		if ( count( $symbols ) === $failed ) {
			return sprintf(
				'<div class="nr-stocks nr-stocks-error" data-error="%s"><p>%s</p></div>',
				esc_attr( reset( $symbols )['error'] ),
				esc_html__( 'Market data is unavailable right now. Please try again later.', 'nr-stocks' )
			);
		}

		$payload = array(
			'provider' => $args['provider'],
			'series'   => $args['series'],
			'color'    => $color,
			'symbols'  => $symbols,
			'labels'   => array(
				'chart'  => __( 'Price chart', 'nr-stocks' ),
				'noData' => __( 'No data', 'nr-stocks' ),
			),
		);

		$fallback = '';
		foreach ( $symbols as $symbol => $entry ) {
			if ( '' !== $entry['error'] || empty( $entry['rows'] ) ) {
				$fallback .= sprintf(
					'<li>%s</li>',
					sprintf(
						/* translators: %s: ticker symbol. */
						esc_html__( '%s: unavailable', 'nr-stocks' ),
						esc_html( $symbol )
					)
				);
				continue;
			}
			$last      = $entry['rows'][ count( $entry['rows'] ) - 1 ];
			$fallback .= sprintf(
				'<li>%s</li>',
				esc_html( self::fallback_line( $symbol, $entry, $last ) )
			);
		}

		return sprintf(
			'<figure class="nr-stocks" data-nr-stocks="1"><script type="application/json" class="nr-stocks-data">%s</script><div class="nr-stocks-canvas" role="img" aria-label="%s"></div><ul class="nr-stocks-fallback">%s</ul></figure>',
			wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP ),
			esc_attr__( 'Stock price chart', 'nr-stocks' ),
			$fallback
		);
	}

	/**
	 * Whether an error can clear up without the site owner changing anything.
	 *
	 * @param string $code Error code.
	 * @return bool
	 */
	public static function is_temporary_error( $code ) {
		return ! in_array(
			$code,
			array( 'unsupported_series', 'unsupported_asset', 'unsupported_pair', 'bad_symbol', 'unknown_provider', 'missing_key', 'forbidden_host', 'upstream_error' ),
			true
		);
	}

	/**
	 * Keeps a page holding a temporary failure out of page caches.
	 */
	protected static function prevent_page_caching() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- convention read by page-cache plugins.
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		/**
		 * Fires when a temporary data failure keeps the page out of page caches.
		 */
		do_action( 'nr_stocks_page_uncacheable' );
	}

	/**
	 * Normalizes shortcode/block attributes into renderer args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	public static function normalize_args( $args ) {
		$settings = Settings::get();
		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : '';
		if ( '' === $provider || null === Provider_Registry::get( $provider ) ) {
			$provider = $settings['default_provider'];
		}
		if ( null === Provider_Registry::get( $provider ) ) {
			$provider = 'alphavantage';
		}

		$raw_symbols = isset( $args['symbols'] ) ? $args['symbols'] : '';
		if ( is_string( $raw_symbols ) ) {
			$raw_symbols = explode( ',', $raw_symbols );
		}
		if ( isset( $args['symbol'] ) && '' !== $args['symbol'] ) {
			$raw_symbols[] = $args['symbol'];
		}
		$symbols = array();
		foreach ( (array) $raw_symbols as $symbol ) {
			$clean = Rest_Provider::clean_symbol( $symbol );
			if ( '' !== $clean && ! in_array( $clean, $symbols, true ) ) {
				$symbols[] = $clean;
			}
			if ( count( $symbols ) >= 5 ) {
				break;
			}
		}

		$series = isset( $args['series'] ) ? sanitize_key( $args['series'] ) : 'daily';
		if ( ! in_array( $series, array( 'daily', 'weekly', 'monthly', 'intraday' ), true ) ) {
			$series = 'daily';
		}

		return array(
			'provider'   => $provider,
			'symbols'    => $symbols,
			'series'     => $series,
			'range'      => isset( $args['range'] ) ? max( 1, min( 365, absint( $args['range'] ) ) ) : 30,
			'show_quote' => ! empty( $args['show_quote'] ),
			'ttl'        => isset( $args['ttl'] ) ? max( 0, min( 86400, absint( $args['ttl'] ) ) ) : 0,
		);
	}

	/**
	 * Builds the no-JavaScript fallback line for one symbol.
	 *
	 * @param string $symbol Ticker symbol.
	 * @param array  $entry  Symbol entry with rows/quote.
	 * @param array  $last   Latest row.
	 * @return string
	 */
	protected static function fallback_line( $symbol, $entry, $last ) {
		$close = isset( $last['close'] ) && null !== $last['close'] ? (string) $last['close'] : '?';
		$line  = $symbol . ': ' . $close;
		if ( ! empty( $entry['currency'] ) ) {
			$line .= ' ' . $entry['currency'];
		}
		if ( isset( $entry['quote']['change_percent'] ) && null !== $entry['quote']['change_percent'] ) {
			$line .= sprintf( ' (%+.2f%%)', (float) $entry['quote']['change_percent'] );
		}
		return $line;
	}
}
