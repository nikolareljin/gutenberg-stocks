<?php
/**
 * Versioned read-only REST API for the block and headless frontends.
 *
 * @package Nikolareljin\NrStocks\Rest
 */

namespace Nikolareljin\NrStocks\Rest;

use Nikolareljin\NrStocks\Cache;
use Nikolareljin\NrStocks\Providers\Provider_Registry;
use Nikolareljin\NrStocks\Providers\Rest_Provider;
use Nikolareljin\NrStocks\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers nr-stocks/v1 routes with validation, rate limits, and cache headers.
 */
class Routes {

	/**
	 * Registers routes and the CORS filter.
	 */
	public static function register() {
		register_rest_route(
			NR_STOCKS_REST_NAMESPACE,
			'/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'providers' ),
				'permission_callback' => array( self::class, 'permissions_public' ),
			)
		);
		register_rest_route(
			NR_STOCKS_REST_NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'search' ),
				'permission_callback' => array( self::class, 'permissions_public' ),
				'args'                => array(
					'provider' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'q'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( self::class, 'validate_non_empty' ),
					),
				),
			)
		);
		register_rest_route(
			NR_STOCKS_REST_NAMESPACE,
			'/series',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'series' ),
				'permission_callback' => array( self::class, 'permissions_public' ),
				'args'                => self::symbol_args(),
			)
		);
		register_rest_route(
			NR_STOCKS_REST_NAMESPACE,
			'/quote',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'quote' ),
				'permission_callback' => array( self::class, 'permissions_public' ),
				'args'                => self::symbol_args(),
			)
		);

		add_filter( 'rest_pre_serve_request', array( self::class, 'maybe_cors' ), 10, 1 );
	}

	/**
	 * Shared args for the series and quote routes.
	 *
	 * @return array
	 */
	protected static function symbol_args() {
		return array(
			'provider' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'symbols'  => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'symbol'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'series'   => array(
				'default'           => 'daily',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( self::class, 'validate_series' ),
			),
			'range'    => array(
				'default'           => 30,
				'sanitize_callback' => 'absint',
				'validate_callback' => array( self::class, 'validate_range' ),
			),
			'interval' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
			'ttl'      => array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Validates a non-empty string param.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_non_empty( $value ) {
		return '' !== trim( (string) $value );
	}

	/**
	 * Validates the series param.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_series( $value ) {
		return in_array( $value, array( 'daily', 'weekly', 'monthly', 'intraday' ), true );
	}

	/**
	 * Validates the range param.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_range( $value ) {
		$value = (int) $value;
		return $value >= 1 && $value <= 365;
	}

	/**
	 * Public read permission gate.
	 *
	 * The API is read-only by design. Per-IP rate limiting is enforced
	 * inside each handler instead: permission callbacks may run more than
	 * once per request, so they must stay side-effect free.
	 *
	 * @return bool
	 */
	public static function permissions_public() {
		return true;
	}

	/**
	 * Enforces the per-IP rate limit. Call once per request from handlers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return null|WP_Error Null when allowed, 429 error when limited.
	 */
	protected static function maybe_rate_limit( $request ) {
		$settings = Settings::get();
		$limit    = isset( $settings['rest_rate_limit'] ) ? (int) $settings['rest_rate_limit'] : 60;
		if ( $limit <= 0 ) {
			return null;
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'nrstx_rl_' . md5( $ip . '|' . $request->get_route() );
		$hits = (int) get_transient( $key );
		if ( $hits >= $limit ) {
			return new WP_Error(
				'nr_stocks_rate_limited',
				'Rate limit exceeded. Try again shortly.',
				array(
					'status'      => 429,
					'retry_after' => 60,
				)
			);
		}
		set_transient( $key, $hits + 1, 60 );
		return null;
	}

	/**
	 * GET /providers — public descriptors (never keys).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function providers( $request ) {
		$limited = self::maybe_rate_limit( $request );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$items = Provider_Registry::describe();
		foreach ( $items as &$item ) {
			$item['has_key'] = '' !== Settings::get_key( $item['id'] );
		}
		unset( $item );
		return self::respond(
			array(
				'data' => $items,
				'meta' => array( 'default' => Settings::get()['default_provider'] ),
			),
			300,
			false
		);
	}

	/**
	 * GET /search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search( $request ) {
		$limited = self::maybe_rate_limit( $request );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$provider = self::resolve_provider( $request['provider'] );
		$result   = Rest_Provider::search( $provider, $request['q'] );
		if ( empty( $result['ok'] ) ) {
			return self::rest_error( $result );
		}
		$ttl = Cache::resolve_ttl( 'search', 0 );
		return self::respond(
			array(
				'data' => $result['data'],
				'meta' => array(
					'provider'   => $provider,
					'ttl'        => $ttl,
					'fetched_at' => $result['fetched_at'],
				),
			),
			$ttl,
			! empty( $result['cached'] )
		);
	}

	/**
	 * GET /series.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function series( $request ) {
		$limited = self::maybe_rate_limit( $request );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$args = self::resolve_symbol_args( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$data       = array();
		$all_cached = true;
		foreach ( $args['symbols'] as $symbol ) {
			$res = Rest_Provider::series(
				$args['provider'],
				$symbol,
				$args['series'],
				$args['range'],
				$args['interval'],
				$args['ttl']
			);
			if ( empty( $res['cached'] ) ) {
				$all_cached = false;
			}
			$data[ $symbol ] = $res;
		}
		$ttl = Cache::resolve_ttl( 'series', $args['ttl'] );
		return self::respond(
			array(
				'data' => $data,
				'meta' => array(
					'provider' => $args['provider'],
					'series'   => $args['series'],
					'ttl'      => $ttl,
				),
			),
			$ttl,
			$all_cached
		);
	}

	/**
	 * GET /quote.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function quote( $request ) {
		$limited = self::maybe_rate_limit( $request );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$args = self::resolve_symbol_args( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$data       = array();
		$all_cached = true;
		foreach ( $args['symbols'] as $symbol ) {
			$res = Rest_Provider::quote( $args['provider'], $symbol, $args['ttl'] );
			if ( empty( $res['cached'] ) ) {
				$all_cached = false;
			}
			$data[ $symbol ] = $res;
		}
		$ttl = Cache::resolve_ttl( 'quote', $args['ttl'] );
		return self::respond(
			array(
				'data' => $data,
				'meta' => array(
					'provider' => $args['provider'],
					'ttl'      => $ttl,
				),
			),
			$ttl,
			$all_cached
		);
	}

	/**
	 * Resolves + validates the shared symbol args.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	protected static function resolve_symbol_args( $request ) {
		$raw     = '' !== $request['symbols'] ? $request['symbols'] : $request['symbol'];
		$symbols = array();
		foreach ( explode( ',', (string) $raw ) as $symbol ) {
			$clean = Rest_Provider::clean_symbol( $symbol );
			if ( '' !== $clean && ! in_array( $clean, $symbols, true ) ) {
				$symbols[] = $clean;
			}
			if ( count( $symbols ) >= 5 ) {
				break;
			}
		}
		if ( empty( $symbols ) ) {
			return new WP_Error( 'nr_stocks_bad_symbol', 'Provide at least one symbol.', array( 'status' => 400 ) );
		}
		$ttl = max( 0, min( 86400, (int) $request['ttl'] ) );
		return array(
			'provider' => self::resolve_provider( $request['provider'] ),
			'symbols'  => $symbols,
			'series'   => $request['series'],
			'range'    => max( 1, min( 365, (int) $request['range'] ) ),
			'interval' => (string) $request['interval'],
			'ttl'      => $ttl,
		);
	}

	/**
	 * Resolves the provider id with fallback to the saved default.
	 *
	 * @param string $requested Requested id (may be empty).
	 * @return string
	 */
	protected static function resolve_provider( $requested ) {
		$requested = sanitize_key( $requested );
		if ( '' !== $requested && null !== Provider_Registry::get( $requested ) ) {
			return $requested;
		}
		$default = Settings::get()['default_provider'];
		return null !== Provider_Registry::get( $default ) ? $default : 'alphavantage';
	}

	/**
	 * Wraps a payload with cache headers.
	 *
	 * @param array $payload Payload.
	 * @param int   $ttl     TTL seconds.
	 * @param bool  $cached  Whether served from cache.
	 * @return WP_REST_Response
	 */
	protected static function respond( $payload, $ttl, $cached ) {
		$response = new WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d', max( 0, (int) $ttl ) ) );
		$response->header( 'ETag', '"' . md5( wp_json_encode( $payload ) ) . '"' );
		$response->header( 'X-NR-Stocks-Cache', $cached ? 'HIT' : 'MISS' );
		return $response;
	}

	/**
	 * Maps an internal error envelope to a REST error.
	 *
	 * @param array $result Internal result.
	 * @return WP_Error
	 */
	protected static function rest_error( $result ) {
		$code   = isset( $result['error'] ) ? $result['error'] : 'fetch_failed';
		$status = 502;
		if ( in_array( $code, array( 'bad_symbol', 'unknown_provider', 'unsupported_series', 'unsupported_asset', 'unsupported_pair' ), true ) ) {
			$status = 400;
		} elseif ( 'missing_key' === $code ) {
			$status = 503;
		} elseif ( 'upstream_limit' === $code ) {
			$status = 429;
		}
		return new WP_Error( 'nr_stocks_' . $code, 'Market data request failed (' . $code . ').', array( 'status' => $status ) );
	}

	/**
	 * Adds cross-origin headers for allowlisted hosts on our routes only.
	 *
	 * @param bool $served Whether the request was served.
	 * @return bool
	 */
	public static function maybe_cors( $served ) {
		if ( ! isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			return $served;
		}
		$origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		$host   = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return $served;
		}
		$settings = Settings::get();
		$allowed  = isset( $settings['rest_cors_origins'] ) && is_array( $settings['rest_cors_origins'] ) ? $settings['rest_cors_origins'] : array();
		if ( in_array( $host, $allowed, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Vary: Origin' );
		}
		return $served;
	}
}
