<?php
/**
 * StockInfo configuration constants.
 *
 * User: reljinn
 * Date: 2/2/18
 * Time: 4:09 PM
 *
 * @package StockInfo
 */

namespace StockInfo;

// Require Curl library.
require_once __DIR__ . '/../vendor/autoload.php';


/**
 * Paths to Template files on StockInfo.
 * Class StockInfoTemplateFile.
 *
 * @package StockInfo
 */
class StockInfo_Settings_Constants {
	// options.
	const API_OPTIONS = 'stockinfo_options_api';

	// API.
	const META_API__ID__ACTIVATE = 'stockinfo_activate';

	const META_API__ID__HOST = 'stockinfo_api_host';

	const META_API__ID__ENTRYPOINT = 'stockinfo_api_entrypoint';

	const META_API__ID__TOKEN = 'stockinfo_api_token';

	const META_API__ID__COLOR = 'stockinfo_api_line_color';
}
