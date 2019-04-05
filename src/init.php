<?php
/**
 * Blocks Initializer
 *
 * Enqueue CSS/JS of all the blocks.
 *
 * @since   1.0.0
 * @package DJ
 */

//namespace StockInfo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Gutenberg block assets for both frontend + backend.
 *
 * @uses {wp-editor} for WP editor styles.
 * @since 1.0.0
 */
function stock_information_dj_block_assets() { // phpcs:ignore
	$js_handler = 'stock_information-dj-style-css';

	// Styles.
	wp_enqueue_style(
		'stock_information-dj-style-css', // Handle.
		plugins_url( 'dist/blocks.style.build.css', dirname( __FILE__ ) ), // Block style CSS.
		array( 'wp-editor' ) // Dependency to include the CSS after it.
		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.style.build.css' ) // Version: File modification time.
	);

}

// Hook: Frontend assets.
add_action( 'enqueue_block_assets', 'stock_information_dj_block_assets' );

/**
 * Enqueue Gutenberg block assets for backend editor.
 *
 * @uses {wp-blocks} for block type registration & related functions.
 * @uses {wp-element} for WP Element abstraction — structure of blocks.
 * @uses {wp-i18n} to internationalize the block's text.
 * @uses {wp-editor} for WP editor styles.
 * @since 1.0.0
 */
function stock_information_dj_editor_assets() { // phpcs:ignore

	$js_handler = 'stock_information-dj-block-js';

	// Scripts.
	wp_enqueue_script(
		$js_handler, // Handle.
		plugins_url( '/dist/blocks.build.js', dirname( __FILE__ ) ), // Block.build.js: We register the block here. Built with Webpack.
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ), // Dependencies, defined above.
		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: File modification time.
		true // Enqueue the script in the footer.
	);

	// Styles.
	wp_enqueue_style(
		'stock_information-dj-block-editor-css', // Handle.
		plugins_url( 'dist/blocks.editor.build.css', dirname( __FILE__ ) ), // Block editor CSS.
		array( 'wp-edit-blocks' ) // Dependency to include the CSS after it.
		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: File modification time.
	);
}

// Hook: Editor assets.
add_action( 'enqueue_block_editor_assets', 'stock_information_dj_editor_assets' );


function stockinfo_block_cgb_init() {

	$js_handler = 'stock_information-dj-block-js';

	// Scripts.
	wp_register_script(
		'stock_information-dj-block-js', // Handle.
		plugins_url( '/dist/blocks.build.js', dirname( __FILE__ ) ), // Block.build.js: We register the block here. Built with Webpack.
		array(
			'jquery',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-i18n',
			'wp-edit-post',
		), // Dependencies, defined above.
		filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: filemtime — Gets file modification time.
		true // Enqueue the script in the footer.
	);


	// Styles.
	wp_register_style(
		'stock_information-dj-block-editor-css', // Handle.
		plugins_url( 'dist/blocks.editor.build.css', dirname( __FILE__ ) ), // Block editor CSS.
		array( 'wp-edit-blocks' ) // Dependency to include the CSS after it.
		, filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: filemtime — Gets file modification time.
	);

	$stockinfo_config = get_option( \StockInfo\StockInfo_Settings_Constants::API_OPTIONS );
	$params = [
		'host'       => $stockinfo_config[\StockInfo\StockInfo_Settings_Constants::META_API__ID__HOST],
		'entrypoint' => $stockinfo_config[\StockInfo\StockInfo_Settings_Constants::META_API__ID__ENTRYPOINT],
		'token'       => $stockinfo_config[\StockInfo\StockInfo_Settings_Constants::META_API__ID__TOKEN],
		'stroke_color' => $stockinfo_config[\StockInfo\StockInfo_Settings_Constants::META_API__ID__COLOR]
	];
	wp_localize_script( $js_handler, 'stockinfo_config', $params );

	register_block_type(
		'dj/stockinfo',
		[
			'editor_script' => 'stock_information-dj-block-js',
			'editor_style'  => 'stock_information-dj-block-editor-css',
		]
	);
}

// Hook: Init block.
add_action( 'init', __NAMESPACE__ . '\stockinfo_block_cgb_init' );
