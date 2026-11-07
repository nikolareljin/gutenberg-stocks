<?php
/**
 * Built-in provider configurations.
 *
 * Each provider is pure configuration consumed by Rest_Provider. Adding a new
 * JSON/CSV API later means adding one array here (or via the
 * nr_stocks_providers filter) — no new classes. See docs/adding-a-provider.md.
 *
 * Op schema:
 *   assets      string[]  Asset kinds served: equity, forex, crypto.
 *   url         string    Template; {placeholders} are substituted + encoded.
 *   format      string    json|csv.
 *   rows        string|null JSON key holding the row list; null = body is rows.
 *   order       string    asc|desc row order as returned (normalized to asc).
 *   map         array     Canonical field => source key ({placeholders} allowed).
 *   keyed_by    string    Param whose value selects the row from a keyed object.
 *   pairs       array     For pair-array rows: path, ts (ms|s), value field.
 *   local_list  array     Static rows filtered locally (no upstream search).
 *   parser      callable  Full custom parse, for shapes the generic mapper
 *                         cannot express (e.g. ECB SDMX).
 *   error_paths string[]  JSON keys signalling an upstream error/note.
 *
 * @package Nikolareljin\NrStocks\Providers
 */

namespace Nikolareljin\NrStocks\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the built-in provider configurations.
 */
class Configs {

	/**
	 * All built-in providers, keyed by id.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'alphavantage' => self::alphavantage(),
			'twelvedata'   => self::twelvedata(),
			'frankfurter'  => self::frankfurter(),
			'coingecko'    => self::coingecko(),
			'ecb'          => self::ecb(),
		);
	}

	/**
	 * Alpha Vantage: stocks/ETF/forex/crypto, keyed, CSV-first.
	 *
	 * @return array
	 */
	protected static function alphavantage() {
		$csv_rows = array(
			'date'   => 'timestamp',
			'open'   => 'open',
			'high'   => 'high',
			'low'    => 'low',
			'close'  => 'close',
			'volume' => 'volume',
		);
		return array(
			'id'          => 'alphavantage',
			'label'       => 'Alpha Vantage',
			'description' => 'Stocks, ETFs, forex, and crypto. Free key, 25 requests/day.',
			'needs_key'   => true,
			'key_setting' => 'alphavantage',
			'key_url'     => 'https://www.alphavantage.co/support/#api-key',
			'hosts'       => array( 'https://www.alphavantage.co' ),
			'series'      => array(
				'daily'    => array(
					array(
						'assets'      => array( 'equity' ),
						'url'         => '{host}/query?function=TIME_SERIES_DAILY&symbol={symbol}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
					array(
						'assets'      => array( 'forex' ),
						'url'         => '{host}/query?function=FX_DAILY&from_symbol={from}&to_symbol={to}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
					array(
						'assets'      => array( 'crypto' ),
						'url'         => '{host}/query?function=DIGITAL_CURRENCY_DAILY&symbol={from}&market={market}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
				),
				'weekly'   => array(
					array(
						'assets'      => array( 'equity' ),
						'url'         => '{host}/query?function=TIME_SERIES_WEEKLY&symbol={symbol}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
				),
				'monthly'  => array(
					array(
						'assets'      => array( 'equity' ),
						'url'         => '{host}/query?function=TIME_SERIES_MONTHLY&symbol={symbol}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
				),
				'intraday' => array(
					array(
						'assets'      => array( 'equity' ),
						'url'         => '{host}/query?function=TIME_SERIES_INTRADAY&symbol={symbol}&interval={interval}&apikey={key}&datatype=csv',
						'format'      => 'csv',
						'order'       => 'desc',
						'map'         => $csv_rows,
						'error_paths' => array( 'Note', 'Information', 'Error Message' ),
					),
				),
			),
			'quote'       => array(
				'assets'      => array( 'equity' ),
				'url'         => '{host}/query?function=GLOBAL_QUOTE&symbol={symbol}&apikey={key}&datatype=csv',
				'format'      => 'csv',
				'rows'        => null,
				'map'         => array(
					'symbol'         => 'symbol',
					'price'          => 'price',
					'open'           => 'open',
					'high'           => 'high',
					'low'            => 'low',
					'volume'         => 'volume',
					'previous_close' => 'previousClose',
					'change'         => 'change',
					'change_percent' => 'changePercent',
					'date'           => 'latestDay',
				),
				'transforms'  => array( 'change_percent' => 'percent' ),
				'error_paths' => array( 'Note', 'Information', 'Error Message' ),
			),
			'search'      => array(
				'url'         => '{host}/query?function=SYMBOL_SEARCH&keywords={q}&apikey={key}',
				'format'      => 'json',
				'rows'        => 'bestMatches',
				'error_paths' => array( 'Note', 'Information', 'Error Message' ),
				'map'         => array(
					'symbol'   => '1. symbol',
					'name'     => '2. name',
					'type'     => '3. type',
					'currency' => '8. currency',
				),
			),
			'rate_note'   => 'Free: 25 requests/day and the latest 100 points per series. Unlimited for verified open-source projects.',
		);
	}

	/**
	 * Twelve Data: unified stocks/forex/crypto endpoint, keyed, JSON.
	 *
	 * @return array
	 */
	protected static function twelvedata() {
		$ohlcv = array(
			'date'   => 'datetime',
			'open'   => 'open',
			'high'   => 'high',
			'low'    => 'low',
			'close'  => 'close',
			'volume' => 'volume',
		);
		return array(
			'id'          => 'twelvedata',
			'label'       => 'Twelve Data',
			'description' => 'Stocks, ETFs, forex, and crypto from one endpoint.',
			'needs_key'   => true,
			'key_setting' => 'twelvedata',
			'key_url'     => 'https://twelvedata.com/pricing',
			'hosts'       => array( 'https://api.twelvedata.com' ),
			// Only the verified 1day interval ships; weekly/monthly/intraday
			// intervals can be added here once verified against a live key.
			'series'      => array(
				'daily' => array(
					array(
						'assets'      => array( 'equity', 'forex', 'crypto' ),
						'url'         => '{host}/time_series?symbol={symbol}&interval=1day&outputsize={range}&apikey={key}',
						'format'      => 'json',
						'rows'        => 'values',
						'order'       => 'desc',
						'map'         => $ohlcv,
						'meta_paths'  => array(
							'currency' => 'meta.currency',
							'exchange' => 'meta.exchange',
						),
						'error_paths' => array( 'message' ),
					),
				),
			),
			'quote'       => array(
				'assets'      => array( 'equity', 'forex', 'crypto' ),
				'url'         => '{host}/quote?symbol={symbol}&apikey={key}',
				'format'      => 'json',
				'rows'        => null,
				'map'         => array(
					'symbol'         => 'symbol',
					'price'          => 'close',
					'open'           => 'open',
					'high'           => 'high',
					'low'            => 'low',
					'volume'         => 'volume',
					'previous_close' => 'previous_close',
					'change'         => 'change',
					'change_percent' => 'percent_change',
					'date'           => 'datetime',
					'currency'       => 'currency',
					'name'           => 'name',
				),
				'error_paths' => array( 'message' ),
			),
			'search'      => array(
				'url'         => '{host}/symbol_search?symbol={q}&apikey={key}',
				'format'      => 'json',
				'rows'        => 'data',
				'error_paths' => array( 'message' ),
				'map'         => array(
					'symbol'   => 'symbol',
					'name'     => 'instrument_name',
					'exchange' => 'exchange',
					'type'     => 'instrument_type',
					'currency' => 'currency',
				),
			),
			'rate_note'   => 'Free: 8 credits/min, 800/day, internal non-display use.',
		);
	}

	/**
	 * Frankfurter: keyless daily forex reference rates.
	 *
	 * @return array
	 */
	protected static function frankfurter() {
		return array(
			'id'          => 'frankfurter',
			'label'       => 'Frankfurter (forex, no key)',
			'description' => 'Daily ECB-anchored forex reference rates. No API key.',
			'needs_key'   => false,
			'key_setting' => null,
			'key_url'     => '',
			'hosts'       => array( 'https://api.frankfurter.dev' ),
			'series'      => array(
				'daily'   => array(
					array(
						'assets' => array( 'forex' ),
						'url'    => '{host}/v2/rates?from={from_date}&to={to_date}&base={from}&quotes={to}',
						'format' => 'json',
						'rows'   => null,
						'order'  => 'asc',
						'map'    => array(
							'date'  => 'date',
							'close' => 'rate',
						),
					),
				),
				'weekly'  => array(
					array(
						'assets' => array( 'forex' ),
						'url'    => '{host}/v2/rates?from={from_date}&to={to_date}&base={from}&quotes={to}&group=week',
						'format' => 'json',
						'rows'   => null,
						'order'  => 'asc',
						'map'    => array(
							'date'  => 'date',
							'close' => 'rate',
						),
					),
				),
				'monthly' => array(
					array(
						'assets' => array( 'forex' ),
						'url'    => '{host}/v2/rates?from={from_date}&to={to_date}&base={from}&quotes={to}&group=month',
						'format' => 'json',
						'rows'   => null,
						'order'  => 'asc',
						'map'    => array(
							'date'  => 'date',
							'close' => 'rate',
						),
					),
				),
			),
			// No quote endpoint: quotes are synthesized from the latest series point.
			'quote'       => null,
			'search'      => array(
				'url'          => '{host}/v2/currencies',
				'format'       => 'json',
				'local_filter' => true,
				// Currency codes alone are not chartable: results are offered as pairs.
				'pair_with'    => array( 'USD', 'EUR' ),
				'map'          => array(
					'symbol' => 'code',
					'name'   => 'name',
				),
			),
			'rate_note'   => 'Free, no key. Daily cadence only.',
		);
	}

	/**
	 * CoinGecko: keyless crypto spot prices and history (rate-limited).
	 *
	 * @return array
	 */
	protected static function coingecko() {
		return array(
			'id'          => 'coingecko',
			'label'       => 'CoinGecko (crypto, no key)',
			'description' => 'Crypto spot prices and history. Keyless, with rate limits.',
			'needs_key'   => false,
			'key_setting' => 'coingecko',
			'key_url'     => 'https://docs.coingecko.com/',
			'hosts'       => array( 'https://api.coingecko.com' ),
			'series'      => array(
				'daily' => array(
					array(
						'assets' => array( 'crypto' ),
						'url'    => '{host}/api/v3/coins/{cg_id}/market_chart?vs_currency={cur}&days={range}',
						'format' => 'json',
						'pairs'  => array(
							'path'    => 'prices',
							'ts'      => 'ms',
							'value'   => 'close',
							// Up to 90 days the API answers with hourly rows.
							'per_day' => true,
						),
						'order'  => 'asc',
					),
				),
			),
			'quote'       => array(
				'assets'   => array( 'crypto' ),
				'url'      => '{host}/api/v3/simple/price?ids={cg_id}&vs_currencies={cur}&include_24hr_change=true',
				'format'   => 'json',
				'keyed_by' => 'cg_id',
				'map'      => array(
					'price'          => '{cur}',
					'change_percent' => '{cur}_24h_change',
				),
			),
			// Local majors list keeps search working without extra endpoints.
			// A full /search config can be added later (see adding-a-provider.md).
			'search'      => array(
				'local_filter' => true,
				'local_list'   => self::coingecko_majors(),
				'map'          => array(
					'symbol' => 'symbol',
					'name'   => 'name',
				),
			),
			'rate_note'   => 'Free demo with rate limits.',
		);
	}

	/**
	 * ECB Data Portal: official keyless EUR reference rates (advanced).
	 *
	 * SDMX-JSON needs a dedicated parser, which is the documented escape hatch
	 * for shapes the generic mapper cannot express.
	 *
	 * @return array
	 */
	protected static function ecb() {
		return array(
			'id'          => 'ecb',
			'label'       => 'ECB reference rates (advanced)',
			'description' => 'Official ECB euro reference rates. No key. EUR-base pairs only.',
			'needs_key'   => false,
			'key_setting' => null,
			'key_url'     => '',
			'hosts'       => array( 'https://data-api.ecb.europa.eu' ),
			'series'      => array(
				'daily' => array(
					array(
						'assets'        => array( 'forex' ),
						'base_currency' => 'EUR',
						'url'           => '{host}/service/data/EXR/D.{to}.EUR.SP00.A?format=jsondata&lastNObservations={range}',
						'format'        => 'json',
						'parser'        => array( 'Nikolareljin\\NrStocks\\Providers\\Mappers', 'ecb_series' ),
						'order'         => 'asc',
					),
				),
			),
			'quote'       => null,
			'search'      => null,
			'rate_note'   => 'Free, no key. EUR-base pairs only.',
		);
	}

	/**
	 * Ticker to CoinGecko id map for common coins.
	 *
	 * @return array
	 */
	public static function coingecko_ids() {
		return array(
			'BTC'  => 'bitcoin',
			'ETH'  => 'ethereum',
			'USDT' => 'tether',
			'BNB'  => 'binancecoin',
			'SOL'  => 'solana',
			'XRP'  => 'ripple',
			'DOGE' => 'dogecoin',
			'ADA'  => 'cardano',
			'TRX'  => 'tron',
			'AVAX' => 'avalanche-2',
			'LINK' => 'chainlink',
			'DOT'  => 'polkadot',
			'LTC'  => 'litecoin',
			'BCH'  => 'bitcoin-cash',
			'XLM'  => 'stellar',
			'ATOM' => 'cosmos',
			'ARB'  => 'arbitrum',
			'OP'   => 'optimism',
		);
	}

	/**
	 * Searchable majors list derived from the id map.
	 *
	 * @return array
	 */
	protected static function coingecko_majors() {
		$names = array(
			'BTC'  => 'Bitcoin',
			'ETH'  => 'Ethereum',
			'USDT' => 'Tether',
			'BNB'  => 'BNB',
			'SOL'  => 'Solana',
			'XRP'  => 'XRP',
			'DOGE' => 'Dogecoin',
			'ADA'  => 'Cardano',
			'TRX'  => 'TRON',
			'AVAX' => 'Avalanche',
			'LINK' => 'Chainlink',
			'DOT'  => 'Polkadot',
			'LTC'  => 'Litecoin',
			'BCH'  => 'Bitcoin Cash',
			'XLM'  => 'Stellar',
			'ATOM' => 'Cosmos',
			'ARB'  => 'Arbitrum',
			'OP'   => 'Optimism',
		);
		$rows  = array();
		foreach ( self::coingecko_ids() as $symbol => $id ) {
			$rows[] = array(
				'symbol' => $symbol . '/USD',
				'name'   => ( isset( $names[ $symbol ] ) ? $names[ $symbol ] : $symbol ) . ' (CoinGecko)',
				'id'     => $id,
			);
		}
		return $rows;
	}
}
