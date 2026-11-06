<?php
/**
 * Custom response parsers for shapes the generic mapper cannot express.
 *
 * @package Nikolareljin\NrStocks\Providers
 */

namespace Nikolareljin\NrStocks\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds parser callbacks referenced by provider configs.
 */
class Mappers {

	/**
	 * Parses an ECB SDMX-JSON time series into canonical rows.
	 *
	 * Observations are indexed ({"0": [value, ...]}) and dates live in
	 * structure.dimensions.observation[0].values in matching order.
	 *
	 * @param array $decoded Decoded SDMX-JSON body.
	 * @return array Array of rows with date/close keys, oldest first.
	 */
	public static function ecb_series( $decoded ) {
		$rows = array();
		if ( ! is_array( $decoded ) ) {
			return $rows;
		}
		$dates = array();
		if ( isset( $decoded['structure']['dimensions']['observation'][0]['values'] )
			&& is_array( $decoded['structure']['dimensions']['observation'][0]['values'] ) ) {
			foreach ( $decoded['structure']['dimensions']['observation'][0]['values'] as $index => $dim ) {
				if ( isset( $dim['id'] ) ) {
					$dates[ (string) $index ] = (string) $dim['id'];
				}
			}
		}
		if ( isset( $decoded['dataSets'][0]['series'] ) && is_array( $decoded['dataSets'][0]['series'] ) ) {
			foreach ( $decoded['dataSets'][0]['series'] as $series ) {
				if ( ! isset( $series['observations'] ) || ! is_array( $series['observations'] ) ) {
					continue;
				}
				foreach ( $series['observations'] as $index => $obs ) {
					if ( ! isset( $dates[ (string) $index ] ) || ! is_array( $obs ) || ! isset( $obs[0] ) ) {
						continue;
					}
					$rows[] = array(
						'date'  => $dates[ (string) $index ],
						'close' => (float) $obs[0],
					);
				}
			}
		}
		return $rows;
	}
}
