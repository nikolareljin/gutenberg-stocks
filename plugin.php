<?php
/**
 * Plugin Name: Stock Information — DJ Gutenberg Block Plugin
 * Plugin URI: https://github.com/ahmadawais/create-guten-block/
 * Description: stock-information — is a Gutenberg plugin created via create-guten-block.
 * Author: mrahmadawais, maedahbatool
 * Author URI: https://AhmadAwais.com/
 * Version: 1.0.0
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package DJ
 */

//namespace  StockInfo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require Curl library.
//require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/class-stockinfo-settings.php';

// Include Settings file.
if ( is_admin() ) {
	$settings_page = new \StockInfo\StockInfo_Settings();
}

/**
 * Block Initializer.
 */
require_once plugin_dir_path( __FILE__ ) . 'src/init.php';

/**
 * Theme update: Allow responsive embeds in Gutenberg and Align wide content.
 */
function stockinfo_theme_setup() {
	// Add support for wide images
//	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-style' );
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\stockinfo_theme_setup' );
