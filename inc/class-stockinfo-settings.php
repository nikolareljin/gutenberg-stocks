<?php
/**
 * StockInfo Adnin settings page.
 *
 * Stores the settings into WP Database.
 *
 * - API Token : ....
 * - API Host : https://www.alphavantage.co
 * - API entrypoint : /query
 *
 *
 * User: reljinn
 * Date: 5/2/18
 * Time: 11:15 AM
 */

namespace StockInfo;

// Require Curl library.
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * StockInfo settings page.
 */
class StockInfo_Settings {

	// Config options for API, Settings, Meta tags
	private $options_api;

	// slug to be shown in the menu
	private $menu_slug;

	// error logging
	public $status;
	public $error;

	function load_wp_media_files() {
		wp_enqueue_media();

		// Add the color picker css file
		wp_enqueue_style( 'wp-color-picker' );
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'stockinfo_settings_options_init' ) );

		wp_enqueue_script( 'wpc-settings-jquery', plugins_url( 'inc/js/settings.js', dirname( __FILE__ ) ), array( 'jquery', 'wp-color-picker' ), '1.0', true );

		wp_register_style( 'wpc-settings-admin-css', plugins_url( dirname( plugin_basename( __FILE__ ) ) . 'inc/css/settings.css' ) );
		wp_enqueue_style( 'wpc-settings-admin-css' );

		add_action( 'admin_enqueue_scripts', [ &$this, 'load_wp_media_files' ] );

		// Menu slug
		$this->menu_slug = 'stockinfo_settings';
	}

	/**
	 * Plugin options page
	 */
	public function add_plugin_page() {
		// SVG with StockInfo logo.
		$pathContent = 'm 20.712589,5.823271 c -0.528802,-0.21711 -0.891374,0.51682 -1.317602,0.80648 -2.495938,2.3101305 -4.991876,4.620259 -7.487814,6.930389 -1.783164,-2.14629 -3.5663288,-4.2925785 -5.3494938,-6.438869 -0.537836,-0.49837 -0.924755,0.40505 -1.315916,0.7614005 -1.264354,1.45982 -2.528706,2.9196385 -3.793059,4.3794685 -0.292915,0.59485 -0.105764,1.36182 -0.158747,2.03991 0,1.8168 0,3.6336 0,5.4504 0.0793,0.86443 0.853108,0.55943 1.320764,0.61524 5.961157,0 11.9223148,0 17.8834708,0 0.673851,-0.10173 0.436091,-1.09439 0.479598,-1.69431 0,-4.1008 0,-8.20159 0,-12.302389 0.0018,-0.22652 -0.103189,-0.44662 -0.261201,-0.54772 z M 3.3955112,19.13721 c -0.382119,0 -0.764239,0 -1.146358,0 0,-2.04795 0,-4.0959 0,-6.14386 0.382119,-0.4412 0.764239,-0.88241 1.146358,-1.32361 0,2.48916 0,4.97831 0,7.46747 z m 2.070544,0 c -0.37045,0 -0.740899,0 -1.111348,0 0,-2.8583 0,-5.7166 0,-8.5749 0.370449,-0.42772 0.740898,-0.8554385 1.111348,-1.2831585 0,3.2860185 0,6.5720385 0,9.8580585 z m 2.070484,0 c -0.37045,0 -0.740899,0 -1.111349,0 0,-3.49602 0,-6.99204 0,-10.4880685 0.37045,0.445869 0.740899,0.89174 1.111349,1.3376093 0,3.0501492 0,6.1003092 0,9.1504592 z m 2.070543,0 c -0.370449,0 -0.740899,0 -1.111348,0 0,-2.66532 0,-5.33064 0,-7.99596 0.370449,0.44589 0.740899,0.89179 1.111348,1.33769 0,2.21942 0,4.43885 0,6.65827 z m 2.0919458,0 c -0.377583,0 -0.755167,0 -1.13275,0 0,-1.83457 0,-3.66913 0,-5.5037 0.372716,0.45667 0.789582,0.93678 1.13269,1.36978 4e-5,1.37795 -6e-5,2.7561 6e-5,4.13392 z m 2.091946,0 c -0.377604,0 -0.755207,0 -1.132811,0 0,-1.58496 0,-3.16992 0,-4.75488 0.377604,-0.3495 0.755207,-0.69901 1.132811,-1.04852 0,1.93447 0,3.86893 0,5.8034 z m 2.092005,0 c -0.377603,0 -0.755206,0 -1.13281,0 0,-2.2304 0,-4.46079 0,-6.69119 0.377583,-0.34948 0.755167,-0.69896 1.13275,-1.04845 4.1e-5,2.57986 -6e-5,5.15991 6e-5,7.73964 z m 2.091946,0 c -0.377604,0 -0.755207,0 -1.13281,0 0,-2.87581 0,-5.75162 0,-8.62743 0.377603,-0.3495 0.755206,-0.6990095 1.13281,-1.0485185 0,3.2253185 0,6.4506385 0,9.6759485 z m 2.03967,0 c -0.360138,0 -0.720277,0 -1.080414,0 0,-3.52122 0,-7.04244 0,-10.5636585 0.360137,-0.33333 0.720276,-0.66667 1.080414,-1.0000005 0,3.854549 0,7.709109 0,11.563659 z';

		$viewBox = '0 0 15 15';

		$stockinfoLogo = 'data:image/svg+xml;base64,' . base64_encode( '<svg width="20" height="20" viewBox="' . $viewBox . '" xmlns="http://www.w3.org/2000/svg"><path fill="black" d="' . $pathContent . '"/></svg>' );

		add_menu_page(
			__( 'StockInfo Configuration Options', 'stock-info' ),
			__( 'StockInfo Options', 'stock-info' ),
			'update_plugins', // manage_options - updating core is only for super administrators
			$this->menu_slug,
			[ $this, 'stockinfo_settings_admin_stockinfo_settings' ],
			// plugins_url( '/images/image.png', __FILE__ )
			$stockinfoLogo
		);
	}

	/**
	 * Configuration page content.
	 */
	public function stockinfo_settings_admin_stockinfo_settings() {
		// get original values
		$this->options_api = get_option( StockInfo_Settings_Constants::API_OPTIONS );

		// grab which screen we are at.
		$api_Screen = ( isset( $_GET['action'] ) && 'api' == $_GET['action'] ) ? true : false;
		if ( ! isset( $_GET['action'] ) ) {
			$api_Screen = true;
		}

		// Display Tabs
		?>
        <div class="wrap">
            <h1><?php __( 'StockInfo Settings' ); ?></h1>
			<?php
			// Display Options block
			?>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'stockinfo_options_api' );
				do_settings_sections( 'stockinfo-options-api' );
				submit_button( 'Save API settings' );
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Configuration Fields setup.
	 */
	public function stockinfo_settings_options_init() {

		if ( ! is_admin() ) {
			return;
		}
		// args for API
		$this->options_api = get_option( 'stockinfo_options_api' );

		// API settings
		register_setting(
			'stockinfo_options_api', // Option group
			'stockinfo_options_api', // Option name
			[ $this, 'sanitize' ] // Sanitize
		);
		// Section in API settings
		add_settings_section(
			'stockinfo_api_section', // ID
			__( 'API and Publication Settings', 'stock-info' ), // Title
			[ $this, 'dump_section_info' ], // Callback
			'stockinfo-options-api' // Page
		);

		$args = $this->options_api;

		add_settings_field(
			'stockinfo_api_host',
			__( 'API Host', 'stock-info' ),
			[ $this, 'stockinfo_api_host_callback' ],
			'stockinfo-options-api',
			'stockinfo_api_section',
			$args
		);
		add_settings_field(
			'stockinfo_api_entrypoint',
			__( 'API entrypoint', 'stock-info' ),
			[ $this, 'stockinfo_api_entrypoint_callback' ],
			'stockinfo-options-api',
			'stockinfo_api_section',
			$args
		);
		add_settings_field(
			'stockinfo_api_token',
			__( 'Token', 'stock-info' ),
			[ $this, 'stockinfo_api_token_callback' ],
			'stockinfo-options-api',
			'stockinfo_api_section',
			$args
		);
		add_settings_field(
			'stockinfo_api_activate',
			__( 'Activation', 'stock-info' ),
			[ $this, 'stockinfo_api_activate_callback' ],
			'stockinfo-options-api',
			'stockinfo_api_section',
			$args
		);
		add_settings_field(
			'stockinfo_api_line_color',
			__( 'Line color', 'stock-info' ),
			[ $this, 'stockinfo_api_line_color_callback' ],
			'stockinfo-options-api',
			'stockinfo_api_section',
			$args
		);

	}

	/**
     * Color picker for the Graph fill.
	 * @param $args
	 */
	public function stockinfo_api_line_color_callback($args){
		$group_id = StockInfo_Settings_Constants::API_OPTIONS;
		$field_id = StockInfo_Settings_Constants::META_API__ID__COLOR;
		$value    = ( isset( $args[ $field_id ] ) && $args[ $field_id ] ) ? $args[ $field_id ] : '#0b163b';

		$ID = $group_id . '[' . $field_id . ']';

		echo '<input type="text" name="'.$ID.'" value="' . $value . '" class="dj-color-picker" >';
    }

	/**
	 * Activation of the plugin. Widget will not be visible till this is enabled.
	 *
	 * @param $args
	 */
	public function stockinfo_api_activate_callback( $args ) {
		$group_id = StockInfo_Settings_Constants::API_OPTIONS;
		$field_id = StockInfo_Settings_Constants::META_API__ID__ACTIVATE;
		$value    = ( isset( $args[ $field_id ] ) && $args[ $field_id ] ) ? 1 : 0;

		$ID = $group_id . '[' . $field_id . ']';

		if ( 1 != $value ) {
			print( '<div style="border:1px solid red;">' );
		} else {
			print( '<div>' );
		}
		?>
        <label class="switch" for="<?php echo $field_id; ?>">
            <input type="checkbox" id="<?php echo $field_id; ?>" name="<?php echo $ID; ?>"
				<?php
				if ( 1 == $value ) {
					echo 'checked="checked"';
				}
				?>
            >
            <span class="slider round"></span>
        </label>
        <p class="description">
			<?php
			if ( 1 != $value ) {
				print( '<div class="" style="color:red;"> ' . esc_html_e( 'You need to activate the plugin to make it available in the Admin interface. Click to Activate/Deactivate the plugin, then press "Save API Settings" to save the changes.', 'stock-info' ) . ' </div>' );
			} else {
				print( '<div>' . esc_html_e( 'Click to deactivate the plugin. Then press "Save API Settings" to save the changes.', 'stock-info' ) . '</div>' );
			}
			?>
        </p>
        </div>
		<?php
	}

	/**
	 * Insert Host value.
	 *
	 * @param $args
	 */
	public function stockinfo_api_host_callback( $args ) {
		// get the value of the setting we've registered with register_setting()
		$field_id = StockInfo_Settings_Constants::META_API__ID__HOST;
		$group_id = StockInfo_Settings_Constants::API_OPTIONS;
		$ID       = $group_id . '[' . $field_id . ']';

		printf(
			'<input size="50" type="text" id="' . $field_id . '" name="' . $ID . '" value="%s" />',
			isset( $args[ $field_id ] ) ? esc_attr( $args[ $field_id ] ) : 'https://www.alphavantage.co' );
		?>
        <p class="description">
			<?php esc_html_e( 'StockInfo plugin will use specified Host to connect and create the API calls.', 'stock-info' ); ?>
        </p>
        <p class="description">
			<?php
			esc_html_e( 'Until Host is saved, API requests will not be available.', 'stock-info' );
			esc_html_e( 'For more information please contact your administrator.', 'stock-info' );
			?>
        </p>
		<?php
	}

	/**
	 * Show API Entrypoint block.
	 *
	 * @param $args
	 */
	public function stockinfo_api_entrypoint_callback( $args ) {
		// get the value of the setting we've registered with register_setting()
		$field_id = StockInfo_Settings_Constants::META_API__ID__ENTRYPOINT;
		$group_id = StockInfo_Settings_Constants::API_OPTIONS;

		$ID = $group_id . '[' . $field_id . ']';

		printf(
			'<input size="50" type="text" id="' . $field_id . '" name="' . $ID . '" value="%s" />',
			isset( $args[ $field_id ] ) ? esc_attr( $args[ $field_id ] ) : 'query'
		);
		?>
        <p class="description">
			<?php esc_html_e( 'StockInfo plugin will use specified Entrypoint to connect and create the API calls.', 'stock-info' ); ?>
        </p>
        <p class="description">
			<?php
			esc_html_e( 'Until Entrypoint is saved, API requests will not be available.', 'stock-info' );
			esc_html_e( 'For more information please contact your administrator.', 'stock-info' );
			?>
        </p>
		<?php
	}

	/**
	 * Show API Token entry block.
	 *
	 * @param $args
	 */
	public function stockinfo_api_token_callback( $args ) {
		// get the value of the setting we've registered with register_setting()
		$field_id = StockInfo_Settings_Constants::META_API__ID__TOKEN;
		$group_id = StockInfo_Settings_Constants::API_OPTIONS;

		$ID = $group_id . '[' . $field_id . ']';

		printf(
			'<input size="50" type="password" id="' . $field_id . '" name="' . $ID . '" value="%s" />',
			isset( $args[ $field_id ] ) ? esc_attr( $args[ $field_id ] ) : ''
		);
		?>
        <p class="description">
			<?php esc_html_e( 'StockInfo plugin will use specified Token to connect and create the API calls.', 'stock-info' ); ?>
        </p>
        <p class="description">
			<?php
			esc_html_e( 'Until Token is saved, API requests will not be available.', 'stock-info' );
			esc_html_e( 'For more information please contact your administrator.', 'stock-info' );
			?>
        </p>
		<?php
	}

	/**
	 * Dumps message on saving the settings.
	 */
	public function dump_section_info() {
		// add error/update messages
		// check if the user have submitted the settings
		// WordPress will add the "settings-updated" $_GET parameter to the url
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error( 'settings_messages', 'settings_message', __( 'Settings Saved', 'stock-info' ), 'updated' );
		}
		// show error/update messages
		settings_errors( 'settings_messages' );
	}

	/**
	 * Helper field sanitizer.
	 *
	 * @param $input
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();
		foreach ( $input as $key => $val ) {
			if ( isset( $input[ $key ] ) ) {
				$new_input[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		return $new_input;
	}
}
