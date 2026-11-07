<?php
/**
 * Plugin Name:       NR Stocks
 * Plugin URI:        https://github.com/nikolareljin/nr-stocks
 * Description:       Stock, forex, and crypto charts for the block editor. Multiple data providers, server-side caching, shortcode, and a headless-ready REST API.
 * Author:            Nik Reljin
 * Author URI:        https://profiles.wordpress.org/nreljin/
 * Version:           1.0.3
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nr-stocks
 * Domain Path:       /languages
 *
 * @package           Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NR_STOCKS_VERSION' ) ) {
	define( 'NR_STOCKS_VERSION', '1.0.3' );
}
if ( ! defined( 'NR_STOCKS_FILE' ) ) {
	define( 'NR_STOCKS_FILE', __FILE__ );
}
if ( ! defined( 'NR_STOCKS_DIR' ) ) {
	define( 'NR_STOCKS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'NR_STOCKS_URL' ) ) {
	define( 'NR_STOCKS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'NR_STOCKS_SLUG' ) ) {
	define( 'NR_STOCKS_SLUG', 'nr-stocks' );
}
if ( ! defined( 'NR_STOCKS_REST_NAMESPACE' ) ) {
	define( 'NR_STOCKS_REST_NAMESPACE', 'nr-stocks/v1' );
}
if ( ! defined( 'NR_STOCKS_OPTION' ) ) {
	define( 'NR_STOCKS_OPTION', 'nr_stocks_settings' );
}

// PSR-4 autoloader for this plugin's own classes. Production code has no
// Composer runtime dependencies, so the .zip ships without vendor/.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Nikolareljin\\NrStocks\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file     = NR_STOCKS_DIR . 'inc/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'Nikolareljin\\NrStocks\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Nikolareljin\\NrStocks\\Plugin', 'init' ) );
