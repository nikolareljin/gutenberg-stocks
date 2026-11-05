<?php
/**
 * Provider registry: built-in configs plus the nr_stocks_providers filter.
 *
 * @package Nikolareljin\NrStocks\Providers
 */

namespace Nikolareljin\NrStocks\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves provider configurations, letting themes/plugins register more.
 */
class Provider_Registry {

	/**
	 * All providers: built-ins filtered through nr_stocks_providers.
	 *
	 * @return array Config arrays keyed by id.
	 */
	public static function all() {
		$providers = Configs::all();
		/**
		 * Filters the market-data provider registry.
		 *
		 * Add a config array (see Configs::all() for the schema) to support a
		 * new API without touching this plugin's classes.
		 *
		 * @param array $providers Provider configs keyed by id.
		 */
		$providers = apply_filters( 'nr_stocks_providers', $providers );
		return is_array( $providers ) ? $providers : array();
	}

	/**
	 * Returns one provider config, with its per-provider override applied.
	 *
	 * @param string $id Provider id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) || ! is_array( $all[ $id ] ) ) {
			return null;
		}
		/**
		 * Filters a single provider config (hosts, TTLs, endpoints).
		 *
		 * The dynamic portion is the provider id, e.g.
		 * nr_stocks_provider_config_alphavantage.
		 *
		 * @param array $config Provider config.
		 */
		$config = apply_filters( "nr_stocks_provider_config_{$id}", $all[ $id ] );
		return is_array( $config ) ? $config : null;
	}

	/**
	 * All provider ids.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::all() );
	}

	/**
	 * Public descriptors for the settings UI and REST /providers endpoint.
	 * Never includes keys or internal hosts.
	 *
	 * @return array
	 */
	public static function describe() {
		$out = array();
		foreach ( self::all() as $id => $config ) {
			$series = array();
			if ( isset( $config['series'] ) && is_array( $config['series'] ) ) {
				foreach ( $config['series'] as $series_id => $ops ) {
					$assets = array();
					foreach ( (array) $ops as $op ) {
						if ( isset( $op['assets'] ) && is_array( $op['assets'] ) ) {
							$assets = array_merge( $assets, $op['assets'] );
						}
					}
					$series[ $series_id ] = array_values( array_unique( $assets ) );
				}
			}
			$out[] = array(
				'id'        => $id,
				'label'     => isset( $config['label'] ) ? $config['label'] : $id,
				'needs_key' => ! empty( $config['needs_key'] ),
				'series'    => $series,
				'supports'  => array(
					'quote'  => ! empty( $config['quote'] ),
					'search' => ! empty( $config['search'] ),
				),
				'rate_note' => isset( $config['rate_note'] ) ? $config['rate_note'] : '',
			);
		}
		return $out;
	}
}
