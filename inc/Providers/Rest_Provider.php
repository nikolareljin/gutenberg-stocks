<?php
/**
 * Config-driven provider executor: builds URLs, fetches, parses, normalizes.
 *
 * @package Nikolareljin\NrStocks\Providers
 */

namespace Nikolareljin\NrStocks\Providers;

use Nikolareljin\NrStocks\Cache;
use Nikolareljin\NrStocks\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes provider configs from Provider_Registry over HTTP + cache.
 */
class Rest_Provider {

	/**
	 * Allowed Alpha Vantage intraday intervals.
	 *
	 * @var string[]
	 */
	const INTRADAY_INTERVALS = array( '1min', '5min', '15min', '30min', '60min' );

	/**
	 * ISO codes treated as fiat for forex-vs-crypto detection.
	 *
	 * @var string[]
	 */
	const FIAT = array(
		'USD',
		'EUR',
		'GBP',
		'JPY',
		'CHF',
		'CAD',
		'AUD',
		'NZD',
		'CNY',
		'HKD',
		'SGD',
		'SEK',
		'NOK',
		'DKK',
		'PLN',
		'CZK',
		'HUF',
		'ILS',
		'MXN',
		'BRL',
		'ZAR',
		'KRW',
		'INR',
		'TWD',
		'THB',
		'MYR',
		'IDR',
		'PHP',
		'TRY',
		'AED',
		'SAR',
		'RUB',
	);

	/**
	 * Maximum upstream response body, in bytes.
	 *
	 * @var int
	 */
	const MAX_BODY = 2097152;

	/**
	 * Fetches a normalized time series.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $symbol      Symbol (AAPL, EUR/USD, BTC/USD).
	 * @param string $series      Series id (daily, weekly, monthly, intraday).
	 * @param int    $range       Max points, newest last.
	 * @param string $interval    Intraday interval (default 15min).
	 * @param int    $ttl_override Cache TTL override in seconds (0 = none).
	 * @return array Envelope with ok/data/meta or ok/error.
	 */
	public static function series( $provider_id, $symbol, $series = 'daily', $range = 30, $interval = '', $ttl_override = 0 ) {
		$config = Provider_Registry::get( $provider_id );
		if ( null === $config ) {
			return self::error( 'unknown_provider' );
		}
		$symbol = self::clean_symbol( $symbol );
		if ( '' === $symbol ) {
			return self::error( 'bad_symbol' );
		}
		$asset = self::detect_asset( $symbol );
		$op    = self::find_series_op( $config, $series, $asset );
		if ( null === $op ) {
			return self::error( 'unsupported_series' );
		}
		$key = Settings::get_key( $provider_id );
		if ( ! empty( $config['needs_key'] ) && '' === $key ) {
			return self::error( 'missing_key' );
		}

		$params = self::build_params( $config, $provider_id, $symbol, $asset, $range, $interval, $key );
		if ( isset( $op['base_currency'] ) && $params['from'] !== $op['base_currency'] ) {
			return self::error( 'unsupported_pair' );
		}
		$url = self::build_url( $config, $op, $params );
		if ( '' === $url ) {
			return self::error( 'forbidden_host' );
		}

		$cache_key = Cache::make_key(
			$provider_id,
			'series',
			array( $symbol, $series, $range, $interval, $asset )
		);
		$ttl       = Cache::resolve_ttl( 'series', $ttl_override );

		$result = Cache::get_or_fetch(
			$cache_key,
			$ttl,
			function () use ( $op, $url, $params, $range ) {
				return self::fetch_rows( $op, $url, $params, $range );
			}
		);
		if ( empty( $result['ok'] ) ) {
			return $result;
		}
		$result['meta'] = array(
			'provider' => $provider_id,
			'symbol'   => $symbol,
			'asset'    => $asset,
			'series'   => $series,
			'currency' => isset( $result['currency'] ) ? $result['currency'] : '',
			'exchange' => isset( $result['exchange'] ) ? $result['exchange'] : '',
			'ttl'      => $ttl,
		);
		unset( $result['currency'], $result['exchange'] );
		return $result;
	}

	/**
	 * Fetches a quote snapshot (synthesized from series when unconfigured).
	 *
	 * @param string $provider_id  Provider id.
	 * @param string $symbol       Symbol.
	 * @param int    $ttl_override Cache TTL override in seconds (0 = none).
	 * @return array Envelope with ok/data/meta or ok/error.
	 */
	public static function quote( $provider_id, $symbol, $ttl_override = 0 ) {
		$config = Provider_Registry::get( $provider_id );
		if ( null === $config ) {
			return self::error( 'unknown_provider' );
		}
		if ( empty( $config['quote'] ) || ! is_array( $config['quote'] ) ) {
			return self::synthetic_quote( $provider_id, $symbol, $ttl_override );
		}
		$symbol = self::clean_symbol( $symbol );
		if ( '' === $symbol ) {
			return self::error( 'bad_symbol' );
		}
		$asset = self::detect_asset( $symbol );
		$op    = $config['quote'];
		if ( isset( $op['assets'] ) && ! in_array( $asset, (array) $op['assets'], true ) ) {
			return self::synthetic_quote( $provider_id, $symbol, $ttl_override );
		}
		$key = Settings::get_key( $provider_id );
		if ( ! empty( $config['needs_key'] ) && '' === $key ) {
			return self::error( 'missing_key' );
		}

		$params    = self::build_params( $config, $provider_id, $symbol, $asset, 1, '', $key );
		$url       = self::build_url( $config, $op, $params );
		$cache_key = Cache::make_key( $provider_id, 'quote', array( $symbol, $asset ) );
		$ttl       = Cache::resolve_ttl( 'quote', $ttl_override );

		$result = Cache::get_or_fetch(
			$cache_key,
			$ttl,
			function () use ( $op, $url, $params ) {
				return self::fetch_row( $op, $url, $params );
			}
		);
		if ( empty( $result['ok'] ) ) {
			return $result;
		}
		$result['meta'] = array(
			'provider' => $provider_id,
			'symbol'   => $symbol,
			'asset'    => $asset,
			'ttl'      => $ttl,
		);
		return $result;
	}

	/**
	 * Searches symbols.
	 *
	 * @param string $provider_id  Provider id.
	 * @param string $query        Search text.
	 * @param int    $ttl_override Cache TTL override in seconds (0 = none).
	 * @return array Envelope with ok/data/meta or ok/error.
	 */
	public static function search( $provider_id, $query, $ttl_override = 0 ) {
		$config = Provider_Registry::get( $provider_id );
		if ( null === $config || empty( $config['search'] ) || ! is_array( $config['search'] ) ) {
			return self::error( 'unsupported_series' );
		}
		$query = trim( (string) $query );
		if ( '' === $query ) {
			return self::error( 'bad_symbol' );
		}
		$key = Settings::get_key( $provider_id );
		if ( ! empty( $config['needs_key'] ) && '' === $key ) {
			return self::error( 'missing_key' );
		}

		$op        = $config['search'];
		$params    = array(
			'q'   => $query,
			'key' => $key,
		);
		$cache_key = Cache::make_key( $provider_id, 'search', array( strtolower( $query ) ) );
		$ttl       = Cache::resolve_ttl( 'search', $ttl_override );

		if ( ! empty( $op['local_list'] ) && is_array( $op['local_list'] ) ) {
			$data = self::filter_local( $op['local_list'], isset( $op['map'] ) ? $op['map'] : array(), $query );
			return array(
				'ok'         => true,
				'data'       => $data,
				'cached'     => false,
				'fetched_at' => time(),
				'meta'       => array(
					'provider' => $provider_id,
					'ttl'      => $ttl,
				),
			);
		}

		$url    = self::build_url( $config, $op, $params );
		$result = Cache::get_or_fetch(
			$cache_key,
			$ttl,
			function () use ( $op, $url, $params, $query ) {
				return self::fetch_search( $op, $url, $params, $query );
			}
		);
		if ( empty( $result['ok'] ) ) {
			return $result;
		}
		$result['meta'] = array(
			'provider' => $provider_id,
			'ttl'      => $ttl,
		);
		return $result;
	}

	/**
	 * Builds a quote from the latest series point when no quote op exists.
	 *
	 * @param string $provider_id  Provider id.
	 * @param string $symbol       Symbol.
	 * @param int    $ttl_override Cache TTL override.
	 * @return array
	 */
	protected static function synthetic_quote( $provider_id, $symbol, $ttl_override ) {
		$series = self::series( $provider_id, $symbol, 'daily', 2, '', $ttl_override );
		if ( empty( $series['ok'] ) || empty( $series['data'] ) ) {
			return self::error( 'unsupported_series' );
		}
		$rows = $series['data'];
		$last = $rows[ count( $rows ) - 1 ];
		$prev = count( $rows ) > 1 ? $rows[ count( $rows ) - 2 ] : null;
		$data = array(
			'symbol'         => $symbol,
			'price'          => isset( $last['close'] ) ? $last['close'] : null,
			'open'           => isset( $last['open'] ) ? $last['open'] : null,
			'high'           => isset( $last['high'] ) ? $last['high'] : null,
			'low'            => isset( $last['low'] ) ? $last['low'] : null,
			'volume'         => isset( $last['volume'] ) ? $last['volume'] : null,
			'previous_close' => null !== $prev && isset( $prev['close'] ) ? $prev['close'] : null,
			'change'         => null,
			'change_percent' => null,
			'date'           => isset( $last['date'] ) ? $last['date'] : '',
			'currency'       => isset( $series['meta']['currency'] ) ? $series['meta']['currency'] : '',
		);
		if ( is_numeric( $data['price'] ) && is_numeric( $data['previous_close'] ) && 0.0 !== (float) $data['previous_close'] ) {
			$data['change']         = (float) $data['price'] - (float) $data['previous_close'];
			$data['change_percent'] = $data['change'] / (float) $data['previous_close'] * 100;
		}
		return array(
			'ok'         => true,
			'data'       => $data,
			'cached'     => isset( $series['cached'] ) ? $series['cached'] : false,
			'fetched_at' => isset( $series['fetched_at'] ) ? $series['fetched_at'] : time(),
			'meta'       => array(
				'provider'  => $provider_id,
				'symbol'    => $symbol,
				'synthetic' => true,
				'ttl'       => Cache::resolve_ttl( 'quote', $ttl_override ),
			),
		);
	}

	/**
	 * Detects the asset kind of a symbol.
	 *
	 * @param string $symbol Cleaned symbol.
	 * @return string equity|forex|crypto.
	 */
	public static function detect_asset( $symbol ) {
		if ( false === strpos( $symbol, '/' ) ) {
			return 'equity';
		}
		$parts = explode( '/', $symbol );
		if ( 2 !== count( $parts ) ) {
			return 'equity';
		}
		$base  = strtoupper( trim( $parts[0] ) );
		$quote = strtoupper( trim( $parts[1] ) );
		if ( in_array( $base, self::FIAT, true ) && in_array( $quote, self::FIAT, true ) ) {
			return 'forex';
		}
		return 'crypto';
	}

	/**
	 * Finds the series op for an asset kind.
	 *
	 * @param array  $config Provider config.
	 * @param string $series Series id.
	 * @param string $asset  Asset kind.
	 * @return array|null
	 */
	protected static function find_series_op( $config, $series, $asset ) {
		if ( ! isset( $config['series'][ $series ] ) || ! is_array( $config['series'][ $series ] ) ) {
			return null;
		}
		foreach ( $config['series'][ $series ] as $op ) {
			if ( isset( $op['assets'] ) && in_array( $asset, (array) $op['assets'], true ) ) {
				return $op;
			}
		}
		return null;
	}

	/**
	 * Builds template params for a request.
	 *
	 * @param array  $config      Provider config.
	 * @param string $provider_id Provider id.
	 * @param string $symbol      Cleaned symbol.
	 * @param string $asset       Asset kind.
	 * @param int    $range       Point count.
	 * @param string $interval    Intraday interval.
	 * @param string $key         API key (may be empty).
	 * @return array
	 */
	protected static function build_params( $config, $provider_id, $symbol, $asset, $range, $interval, $key ) {
		$from = '';
		$to   = '';
		if ( false !== strpos( $symbol, '/' ) ) {
			$parts = explode( '/', $symbol );
			$from  = strtoupper( trim( $parts[0] ) );
			$to    = isset( $parts[1] ) ? strtoupper( trim( $parts[1] ) ) : '';
		}
		if ( ! in_array( $interval, self::INTRADAY_INTERVALS, true ) ) {
			$interval = '15min';
		}
		$range = max( 1, min( 365, (int) $range ) );
		$now   = time();

		$params = array(
			'symbol'    => $symbol,
			'from'      => $from,
			'to'        => $to,
			'market'    => $to,
			'range'     => (string) $range,
			'interval'  => $interval,
			'key'       => $key,
			'cur'       => strtolower( '' !== $to ? $to : 'usd' ),
			'to_date'   => gmdate( 'Y-m-d', $now ),
			// Extra headroom: weekends/holidays have no rows.
			'from_date' => gmdate( 'Y-m-d', $now - ( $range * 3 + 5 ) * 86400 ),
		);

		if ( 'coingecko' === $provider_id ) {
			$ids             = Configs::coingecko_ids();
			$base            = '' !== $from ? $from : strtoupper( $symbol );
			$params['cg_id'] = isset( $ids[ $base ] ) ? $ids[ $base ] : strtolower( $symbol );
		}
		return $params;
	}

	/**
	 * Substitutes placeholders and validates the host against the allowlist.
	 *
	 * @param array $config Provider config.
	 * @param array $op     Operation config.
	 * @param array $params Template params.
	 * @return string URL, or empty string when the host is not allowlisted.
	 */
	public static function build_url( $config, $op, $params ) {
		$host = self::resolve_host( $config );
		if ( '' === $host ) {
			return '';
		}
		$url   = preg_replace_callback(
			'/\{([a-z_]+)\}/',
			function ( $matches ) use ( $params, $host ) {
				$name = $matches[1];
				if ( 'host' === $name ) {
					return rtrim( $host, '/' );
				}
				return isset( $params[ $name ] ) ? rawurlencode( (string) $params[ $name ] ) : '';
			},
			$op['url']
		);
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}
		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			return '';
		}
		foreach ( (array) $config['hosts'] as $allowed ) {
			$allowed_host = wp_parse_url( $allowed, PHP_URL_HOST );
			if ( $allowed_host && 0 === strcasecmp( $parts['host'], $allowed_host ) ) {
				return $url;
			}
		}
		// Alpha Vantage host override from settings (proxy mirrors).
		if ( 'alphavantage' === $config['id'] ) {
			$settings = Settings::get();
			if ( ! empty( $settings['av_host'] ) ) {
				$custom = wp_parse_url( $settings['av_host'], PHP_URL_HOST );
				if ( $custom && 0 === strcasecmp( $parts['host'], $custom ) ) {
					return $url;
				}
			}
		}
		return '';
	}

	/**
	 * Resolves the base host, honoring the AV override when set.
	 *
	 * @param array $config Provider config.
	 * @return string
	 */
	protected static function resolve_host( $config ) {
		if ( 'alphavantage' === $config['id'] ) {
			$settings = Settings::get();
			if ( ! empty( $settings['av_host'] ) ) {
				$entry = ! empty( $settings['av_entrypoint'] ) ? '/' . $settings['av_entrypoint'] : '';
				// Templates append their own path; the override replaces the host only.
				if ( '' === $entry || '/query' === $entry ) {
					return rtrim( $settings['av_host'], '/' );
				}
			}
		}
		return isset( $config['hosts'][0] ) ? $config['hosts'][0] : '';
	}

	/**
	 * Fetches + parses a multi-row op.
	 *
	 * @param array  $op     Operation config.
	 * @param string $url   Request URL.
	 * @param array  $params Template params.
	 * @param int    $range  Max rows to keep.
	 * @return array
	 */
	protected static function fetch_rows( $op, $url, $params, $range ) {
		$body = self::get_body( $url );
		if ( ! $body['ok'] ) {
			return $body;
		}
		$raw = $body['body'];

		if ( isset( $op['parser'] ) && is_callable( $op['parser'] ) ) {
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return self::error( 'parse_error' );
			}
			$rows = call_user_func( $op['parser'], $decoded, $params );
			return self::rows_envelope( $rows, array(), $range );
		}

		if ( 'csv' === $op['format'] ) {
			$table = self::parse_csv( $raw );
			if ( null === $table || empty( $table ) ) {
				// Alpha Vantage answers quota errors as JSON even for CSV calls.
				return self::looks_like_limit( $raw ) ? self::error( 'upstream_limit' ) : self::error( 'parse_error' );
			}
			$rows = self::apply_map( $table, isset( $op['map'] ) ? $op['map'] : array(), $params, isset( $op['transforms'] ) ? $op['transforms'] : array() );
			$rows = self::normalize_series( $rows );
			if ( isset( $op['order'] ) && 'desc' === $op['order'] ) {
				$rows = array_reverse( $rows );
			}
			return self::rows_envelope( $rows, array(), $range );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return self::error( 'parse_error' );
		}
		$err = self::find_upstream_error( $op, $decoded );
		if ( null !== $err ) {
			return self::error( $err );
		}

		$meta = array();
		if ( isset( $op['meta_paths'] ) && is_array( $op['meta_paths'] ) ) {
			foreach ( $op['meta_paths'] as $name => $path ) {
				$meta[ $name ] = self::dot_get( $decoded, $path );
			}
		}

		if ( isset( $op['pairs'] ) && is_array( $op['pairs'] ) ) {
			$list = self::dot_get( $decoded, $op['pairs']['path'] );
			$rows = self::pairs_to_rows( is_array( $list ) ? $list : array(), $op['pairs'] );
			return self::rows_envelope( $rows, $meta, $range );
		}

		$list = null;
		if ( array_key_exists( 'rows', $op ) && null !== $op['rows'] ) {
			$list = self::dot_get( $decoded, $op['rows'] );
		} else {
			$list = $decoded;
		}
		if ( ! is_array( $list ) ) {
			return self::error( 'parse_error' );
		}
		$rows = self::apply_map( array_values( $list ), isset( $op['map'] ) ? $op['map'] : array(), $params, array() );
		$rows = self::normalize_series( $rows );
		if ( isset( $op['order'] ) && 'desc' === $op['order'] ) {
			$rows = array_reverse( $rows );
		}
		return self::rows_envelope( $rows, $meta, $range );
	}

	/**
	 * Fetches + parses a single-row (quote) op.
	 *
	 * @param array  $op     Operation config.
	 * @param string $url   Request URL (may be empty for local ops).
	 * @param array  $params Template params.
	 * @return array
	 */
	protected static function fetch_row( $op, $url, $params ) {
		$body = self::get_body( $url );
		if ( ! $body['ok'] ) {
			return $body;
		}
		$map        = isset( $op['map'] ) ? $op['map'] : array();
		$transforms = isset( $op['transforms'] ) ? $op['transforms'] : array();

		if ( 'csv' === $op['format'] ) {
			$table = self::parse_csv( $body['body'] );
			if ( null === $table || empty( $table ) ) {
				return self::looks_like_limit( $body['body'] ) ? self::error( 'upstream_limit' ) : self::error( 'parse_error' );
			}
			$rows = self::apply_map( array( $table[0] ), $map, $params, $transforms );
			return array(
				'ok'   => true,
				'data' => self::normalize_quote( $rows[0] ),
			);
		}

		$decoded = json_decode( $body['body'], true );
		if ( ! is_array( $decoded ) ) {
			return self::error( 'parse_error' );
		}
		$err = self::find_upstream_error( $op, $decoded );
		if ( null !== $err ) {
			return self::error( $err );
		}
		$row = $decoded;
		if ( isset( $op['keyed_by'] ) && isset( $params[ $op['keyed_by'] ] ) ) {
			$k = $params[ $op['keyed_by'] ];
			if ( ! isset( $decoded[ $k ] ) || ! is_array( $decoded[ $k ] ) ) {
				return self::error( 'parse_error' );
			}
			$row = $decoded[ $k ];
		} elseif ( array_key_exists( 'rows', $op ) && null !== $op['rows'] ) {
			$sub = self::dot_get( $decoded, $op['rows'] );
			if ( ! is_array( $sub ) ) {
				return self::error( 'parse_error' );
			}
			$row = $sub;
		}
		$rows = self::apply_map( array( $row ), $map, $params, $transforms );
		return array(
			'ok'   => true,
			'data' => self::normalize_quote( $rows[0] ),
		);
	}

	/**
	 * Fetches + parses a search op (remote or whole-list filter).
	 *
	 * @param array  $op     Operation config.
	 * @param string $url    Request URL.
	 * @param array  $params Template params.
	 * @param string $query  Search text.
	 * @return array
	 */
	protected static function fetch_search( $op, $url, $params, $query ) {
		$body = self::get_body( $url );
		if ( ! $body['ok'] ) {
			return $body;
		}
		$decoded = json_decode( $body['body'], true );
		if ( ! is_array( $decoded ) ) {
			return self::error( 'parse_error' );
		}
		$map = isset( $op['map'] ) ? $op['map'] : array();

		if ( ! empty( $op['local_filter'] ) ) {
			// Whole-list endpoints (e.g. currency catalogs): normalize both
			// the list-of-objects and code->name-map shapes, then filter.
			$table = array();
			if ( self::is_list( $decoded ) ) {
				$table = $decoded;
			} else {
				foreach ( $decoded as $code => $name ) {
					$table[] = array(
						'code' => $code,
						'name' => is_string( $name ) ? $name : (string) $code,
					);
				}
			}
			return array(
				'ok'   => true,
				'data' => self::filter_local( $table, $map, $query ),
			);
		}

		$list = null;
		if ( array_key_exists( 'rows', $op ) && null !== $op['rows'] ) {
			$list = self::dot_get( $decoded, $op['rows'] );
		} else {
			$list = $decoded;
		}
		if ( ! is_array( $list ) ) {
			return self::error( 'parse_error' );
		}
		$rows = self::apply_map( array_values( $list ), $map, $params, array() );
		foreach ( $rows as &$row ) {
			$row = array_merge(
				array(
					'symbol'   => '',
					'name'     => '',
					'exchange' => '',
					'type'     => '',
					'currency' => '',
				),
				$row
			);
		}
		unset( $row );
		return array(
			'ok'   => true,
			'data' => array_slice( $rows, 0, 10 ),
		);
	}

	/**
	 * Filters a local list by query substring and maps canonical fields.
	 *
	 * @param array  $candidates Candidate rows.
	 * @param array  $map        Field map.
	 * @param string $query      Search text.
	 * @return array
	 */
	protected static function filter_local( $candidates, $map, $query ) {
		$out = array();
		$q   = strtolower( $query );
		foreach ( $candidates as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$mapped = array();
			foreach ( $map as $canonical => $source ) {
				$mapped[ $canonical ] = isset( $row[ $source ] ) ? $row[ $source ] : '';
			}
			$hay = strtolower( implode( ' ', $mapped ) );
			if ( false === strpos( $hay, $q ) ) {
				continue;
			}
			$mapped = array_merge(
				array(
					'symbol'   => '',
					'name'     => '',
					'exchange' => '',
					'type'     => '',
					'currency' => '',
				),
				$mapped
			);
			$out[]  = $mapped;
			if ( count( $out ) >= 10 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Performs the HTTP request with strict caps.
	 *
	 * @param string $url Request URL.
	 * @return array Array with ok/body or ok/error.
	 */
	protected static function get_body( $url ) {
		if ( '' === $url ) {
			return self::error( 'forbidden_host' );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 12,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BODY,
				'reject_unsafe_urls'  => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::error( 'http_error' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::error( 429 === $code ? 'upstream_limit' : 'http_error' );
		}
		return array(
			'ok'   => true,
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Parses CSV text into associative rows via the header line.
	 *
	 * @param string $text CSV body.
	 * @return array|null
	 */
	public static function parse_csv( $text ) {
		$lines = preg_split( "/\r\n|\n|\r/", trim( $text ) );
		if ( ! is_array( $lines ) || count( $lines ) < 2 ) {
			return null;
		}
		$headers = str_getcsv( array_shift( $lines ) );
		if ( empty( $headers ) ) {
			return null;
		}
		$headers = array_map( 'trim', $headers );
		$rows    = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = str_getcsv( $line );
			if ( count( $cells ) !== count( $headers ) ) {
				continue;
			}
			$rows[] = array_combine( $headers, $cells );
		}
		return $rows;
	}

	/**
	 * Applies a field map to rows, substituting {placeholders} from params.
	 *
	 * @param array $rows       Source rows.
	 * @param array $map        Canonical => source map.
	 * @param array $params     Template params.
	 * @param array $transforms Canonical => transform name.
	 * @return array
	 */
	public static function apply_map( $rows, $map, $params, $transforms ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$mapped = array();
			foreach ( $map as $canonical => $source ) {
				$source = preg_replace_callback(
					'/\{([a-z_]+)\}/',
					function ( $matches ) use ( $params ) {
						return isset( $params[ $matches[1] ] ) ? (string) $params[ $matches[1] ] : '';
					},
					$source
				);
				$value  = array_key_exists( $source, $row ) ? $row[ $source ] : null;
				if ( null !== $value && isset( $transforms[ $canonical ] ) ) {
					$value = self::transform( $value, $transforms[ $canonical ] );
				}
				$mapped[ $canonical ] = $value;
			}
			$out[] = $mapped;
		}
		return $out;
	}

	/**
	 * Applies a scalar transform.
	 *
	 * @param mixed  $value Value.
	 * @param string $name  Transform name.
	 * @return mixed
	 */
	protected static function transform( $value, $name ) {
		if ( 'percent' === $name && is_string( $value ) ) {
			return (float) rtrim( trim( $value ), '%' );
		}
		return $value;
	}

	/**
	 * Converts [timestamp, value] pairs (CoinGecko) into rows.
	 *
	 * @param array $pairs Pair rows.
	 * @param array $spec  pairs spec (ts unit, value field).
	 * @return array
	 */
	protected static function pairs_to_rows( $pairs, $spec ) {
		$rows  = array();
		$field = isset( $spec['value'] ) ? $spec['value'] : 'close';
		$ms    = isset( $spec['ts'] ) && 'ms' === $spec['ts'];
		foreach ( $pairs as $pair ) {
			if ( ! is_array( $pair ) || count( $pair ) < 2 ) {
				continue;
			}
			$ts = (int) $pair[0];
			if ( $ms ) {
				$ts = (int) floor( $ts / 1000 );
			}
			$rows[] = array(
				'date' => gmdate( 'Y-m-d H:i:s', $ts ),
				$field => (float) $pair[1],
			);
		}
		return $rows;
	}

	/**
	 * Normalizes series rows to canonical fields and numeric types.
	 *
	 * @param array $rows Mapped rows.
	 * @return array
	 */
	public static function normalize_series( $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['date'] ) && empty( $row['close'] ) ) {
				continue;
			}
			$out[] = array(
				'date'   => isset( $row['date'] ) ? trim( (string) $row['date'] ) : '',
				'open'   => self::num_or_null( isset( $row['open'] ) ? $row['open'] : null ),
				'high'   => self::num_or_null( isset( $row['high'] ) ? $row['high'] : null ),
				'low'    => self::num_or_null( isset( $row['low'] ) ? $row['low'] : null ),
				'close'  => self::num_or_null( isset( $row['close'] ) ? $row['close'] : null ),
				'volume' => self::num_or_null( isset( $row['volume'] ) ? $row['volume'] : null ),
			);
		}
		return $out;
	}

	/**
	 * Normalizes a quote row.
	 *
	 * @param array $row Mapped row.
	 * @return array
	 */
	public static function normalize_quote( $row ) {
		return array(
			'symbol'         => isset( $row['symbol'] ) ? (string) $row['symbol'] : '',
			'name'           => isset( $row['name'] ) ? (string) $row['name'] : '',
			'price'          => self::num_or_null( isset( $row['price'] ) ? $row['price'] : null ),
			'open'           => self::num_or_null( isset( $row['open'] ) ? $row['open'] : null ),
			'high'           => self::num_or_null( isset( $row['high'] ) ? $row['high'] : null ),
			'low'            => self::num_or_null( isset( $row['low'] ) ? $row['low'] : null ),
			'volume'         => self::num_or_null( isset( $row['volume'] ) ? $row['volume'] : null ),
			'previous_close' => self::num_or_null( isset( $row['previous_close'] ) ? $row['previous_close'] : null ),
			'change'         => self::num_or_null( isset( $row['change'] ) ? $row['change'] : null ),
			'change_percent' => self::num_or_null( isset( $row['change_percent'] ) ? $row['change_percent'] : null ),
			'date'           => isset( $row['date'] ) ? (string) $row['date'] : '',
			'currency'       => isset( $row['currency'] ) ? (string) $row['currency'] : '',
		);
	}

	/**
	 * Wraps rows with meta for a series envelope.
	 *
	 * @param array $rows  Rows, oldest first.
	 * @param array $meta  Top-level meta (currency, exchange).
	 * @param int   $range Max rows to keep.
	 * @return array
	 */
	protected static function rows_envelope( $rows, $meta, $range ) {
		$range = max( 1, (int) $range );
		if ( count( $rows ) > $range ) {
			$rows = array_slice( $rows, -$range );
		}
		$result = array(
			'ok'   => true,
			'data' => array_values( $rows ),
		);
		if ( isset( $meta['currency'] ) ) {
			$result['currency'] = (string) $meta['currency'];
		}
		if ( isset( $meta['exchange'] ) ) {
			$result['exchange'] = (string) $meta['exchange'];
		}
		return $result;
	}

	/**
	 * Reads a dotted path from nested arrays.
	 *
	 * @param mixed  $data Subject.
	 * @param string $path Dotted path.
	 * @return mixed|null
	 */
	public static function dot_get( $data, $path ) {
		$node = $data;
		foreach ( explode( '.', (string) $path ) as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return null;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * Finds an upstream error/note payload.
	 *
	 * @param array $op      Operation config.
	 * @param array $decoded Decoded body.
	 * @return string|null Error code or null.
	 */
	protected static function find_upstream_error( $op, $decoded ) {
		if ( empty( $op['error_paths'] ) || ! is_array( $op['error_paths'] ) ) {
			return null;
		}
		foreach ( $op['error_paths'] as $path ) {
			$value = self::dot_get( $decoded, $path );
			if ( is_string( $value ) && '' !== $value ) {
				if ( false !== stripos( $value, 'rate' ) || false !== stripos( $value, 'limit' ) || false !== stripos( $value, 'premium' ) ) {
					return 'upstream_limit';
				}
				return 'upstream_error';
			}
		}
		return null;
	}

	/**
	 * Heuristic for Alpha Vantage text/JSON limit notes on CSV calls.
	 *
	 * @param string $body Raw body.
	 * @return bool
	 */
	protected static function looks_like_limit( $body ) {
		return (bool) preg_match( '/rate|limit|premium|call frequency/i', substr( $body, 0, 500 ) );
	}

	/**
	 * Cleans a user-supplied symbol.
	 *
	 * @param string $symbol Raw symbol.
	 * @return string
	 */
	public static function clean_symbol( $symbol ) {
		$symbol = strtoupper( trim( wp_strip_all_tags( (string) $symbol ) ) );
		$symbol = preg_replace( '/[^A-Z0-9.\-\/=]/', '', $symbol );
		return substr( $symbol, 0, 32 );
	}

	/**
	 * Numeric value or null.
	 *
	 * @param mixed $value Raw value.
	 * @return float|int|null
	 */
	protected static function num_or_null( $value ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		$float = (float) $value;
		return (float) (int) $float === $float && false === strpos( (string) $value, '.' ) ? (int) $float : $float;
	}

	/**
	 * Whether an array is a list (0..n-1 keys).
	 *
	 * @param array $subject Subject.
	 * @return bool
	 */
	protected static function is_list( $subject ) {
		$i = 0;
		foreach ( array_keys( $subject ) as $key ) {
			if ( $key !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	/**
	 * Builds an error envelope (never includes keys or URLs).
	 *
	 * @param string $code Machine-readable code.
	 * @return array
	 */
	protected static function error( $code ) {
		return array(
			'ok'         => false,
			'error'      => $code,
			'cached'     => false,
			'fetched_at' => time(),
		);
	}
}
