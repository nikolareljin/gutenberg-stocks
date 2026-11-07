<?php
/**
 * Settings page and option handling.
 *
 * @package Nikolareljin\NrStocks
 */

namespace Nikolareljin\NrStocks;

use Nikolareljin\NrStocks\Providers\Provider_Registry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Settings -> NR Stocks page and sanitizes the option array.
 */
class Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'nr-stocks';

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'default_provider'  => 'alphavantage',
			'keys'              => array(
				'alphavantage' => '',
				'twelvedata'   => '',
				'coingecko'    => '',
			),
			'ttls'              => array(
				'series' => 300,
				'quote'  => 60,
				'search' => 3600,
			),
			'line_color'        => '#0b163b',
			'rest_rate_limit'   => 60,
			'rest_miss_limit'   => 30,
			'rest_cors_origins' => array(),
			'av_host'           => '',
			'av_entrypoint'     => '',
		);
	}

	/**
	 * Reads the full settings array, merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( NR_STOCKS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$settings = array_merge( self::defaults(), $saved );
		if ( ! isset( $settings['keys'] ) || ! is_array( $settings['keys'] ) ) {
			$settings['keys'] = self::defaults()['keys'];
		} else {
			$settings['keys'] = array_merge( self::defaults()['keys'], $settings['keys'] );
		}
		if ( ! isset( $settings['ttls'] ) || ! is_array( $settings['ttls'] ) ) {
			$settings['ttls'] = self::defaults()['ttls'];
		} else {
			$settings['ttls'] = array_merge( self::defaults()['ttls'], $settings['ttls'] );
		}
		return $settings;
	}

	/**
	 * Returns the API key for a provider id, or an empty string.
	 *
	 * @param string $provider_id Provider id.
	 * @return string
	 */
	public static function get_key( $provider_id ) {
		$settings = self::get();
		$config   = Provider_Registry::get( $provider_id );
		if ( null === $config || empty( $config['key_setting'] ) ) {
			return '';
		}
		$key_setting = $config['key_setting'];
		return isset( $settings['keys'][ $key_setting ] ) ? (string) $settings['keys'][ $key_setting ] : '';
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nr_stocks_purge_cache', array( $this, 'handle_purge' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NR_STOCKS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the Settings -> NR Stocks page.
	 */
	public function add_page() {
		add_options_page(
			__( 'NR Stocks', 'nr-stocks' ),
			__( 'NR Stocks', 'nr-stocks' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues the color picker on our settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery( function( $ ) { $( ".nr-stocks-color" ).wpColorPicker(); } );'
		);
	}

	/**
	 * Registers the setting, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'nr_stocks',
			NR_STOCKS_OPTION,
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'nr_stocks_providers',
			__( 'Data Providers', 'nr-stocks' ),
			array( $this, 'render_providers_blurb' ),
			self::PAGE_SLUG
		);
		add_settings_field(
			'nr_stocks_default_provider',
			__( 'Default provider', 'nr-stocks' ),
			array( $this, 'render_default_provider' ),
			self::PAGE_SLUG,
			'nr_stocks_providers'
		);
		foreach ( Provider_Registry::all() as $id => $config ) {
			if ( empty( $config['needs_key'] ) ) {
				continue;
			}
			add_settings_field(
				'nr_stocks_key_' . $id,
				sprintf(
					/* translators: %s: provider label. */
					__( '%s API key', 'nr-stocks' ),
					$config['label']
				),
				array( $this, 'render_key_field' ),
				self::PAGE_SLUG,
				'nr_stocks_providers',
				array(
					'provider_id' => $id,
					'label'       => $config['label'],
				)
			);
		}
		add_settings_field(
			'nr_stocks_av_host',
			__( 'Alpha Vantage host override', 'nr-stocks' ),
			array( $this, 'render_av_host' ),
			self::PAGE_SLUG,
			'nr_stocks_providers'
		);

		add_settings_section(
			'nr_stocks_cache',
			__( 'Caching', 'nr-stocks' ),
			array( $this, 'render_cache_blurb' ),
			self::PAGE_SLUG
		);
		foreach ( array( 'series', 'quote', 'search' ) as $endpoint ) {
			add_settings_field(
				'nr_stocks_ttl_' . $endpoint,
				sprintf(
					/* translators: %s: endpoint name. */
					__( '%s cache (seconds)', 'nr-stocks' ),
					ucfirst( $endpoint )
				),
				array( $this, 'render_ttl_field' ),
				self::PAGE_SLUG,
				'nr_stocks_cache',
				array( 'endpoint' => $endpoint )
			);
		}
		add_settings_section(
			'nr_stocks_display',
			__( 'Display', 'nr-stocks' ),
			'__return_false',
			self::PAGE_SLUG
		);
		add_settings_field(
			'nr_stocks_line_color',
			__( 'Chart line color', 'nr-stocks' ),
			array( $this, 'render_line_color' ),
			self::PAGE_SLUG,
			'nr_stocks_display'
		);

		add_settings_section(
			'nr_stocks_headless',
			__( 'REST API (headless use)', 'nr-stocks' ),
			array( $this, 'render_headless_blurb' ),
			self::PAGE_SLUG
		);
		add_settings_field(
			'nr_stocks_rest_rate_limit',
			__( 'Rate limit (requests/minute/IP)', 'nr-stocks' ),
			array( $this, 'render_rate_limit' ),
			self::PAGE_SLUG,
			'nr_stocks_headless'
		);
		add_settings_field(
			'nr_stocks_rest_miss_limit',
			__( 'Anonymous upstream fetches (per minute)', 'nr-stocks' ),
			array( $this, 'render_miss_limit' ),
			self::PAGE_SLUG,
			'nr_stocks_headless'
		);
		add_settings_field(
			'nr_stocks_rest_cors_origins',
			__( 'Allowed cross-origin hosts', 'nr-stocks' ),
			array( $this, 'render_cors_origins' ),
			self::PAGE_SLUG,
			'nr_stocks_headless'
		);
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page() {
		$settings = self::get();
		$default  = $settings['default_provider'];
		$config   = Provider_Registry::get( $default );
		$missing  = null !== $config && ! empty( $config['needs_key'] ) && '' === self::get_key( $default );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by handle_purge().
		$purged = isset( $_GET['nr-stocks-purged'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NR Stocks Settings', 'nr-stocks' ); ?></h1>
			<?php if ( $missing ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: provider label. */
							esc_html__( 'The default provider (%s) needs an API key. Enter one below, or pick a keyless provider such as Frankfurter.', 'nr-stocks' ),
							esc_html( $config['label'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( $purged ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'The stock cache was purged.', 'nr-stocks' ); ?></p>
				</div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'nr_stocks' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Purge cache', 'nr-stocks' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'nr_stocks_purge_cache' ); ?>
				<input type="hidden" name="action" value="nr_stocks_purge_cache" />
				<?php submit_button( __( 'Purge Stock Cache', 'nr-stocks' ), 'secondary', 'nr_stocks_purge', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Section blurb: providers.
	 */
	public function render_providers_blurb() {
		echo '<p>';
		esc_html_e( 'Pick the default data source and enter API keys. Keys stay on the server and are never sent to the browser.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Field: default provider select.
	 */
	public function render_default_provider() {
		$settings = self::get();
		$current  = $settings['default_provider'];
		echo '<select name="' . esc_attr( NR_STOCKS_OPTION ) . '[default_provider]">';
		foreach ( Provider_Registry::all() as $id => $config ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $id ),
				selected( $current, $id, false ),
				esc_html( $config['label'] )
			);
		}
		echo '</select>';
	}

	/**
	 * Field: provider API key.
	 *
	 * @param array $args Field args with provider_id.
	 */
	public function render_key_field( $args ) {
		$id       = $args['provider_id'];
		$settings = self::get();
		$config   = Provider_Registry::get( $id );
		$key_name = $config['key_setting'];
		$is_saved = ! empty( $settings['keys'][ $key_name ] );
		// The saved value is never printed: an empty field keeps it on save.
		printf(
			'<input size="50" type="password" autocomplete="new-password" name="%s[keys][%s]" value="" placeholder="%s" />',
			esc_attr( NR_STOCKS_OPTION ),
			esc_attr( $key_name ),
			esc_attr( $is_saved ? __( 'Saved. Enter a new key to replace it.', 'nr-stocks' ) : '' )
		);
		if ( $is_saved ) {
			printf(
				'<p><label><input type="checkbox" name="%s[keys_clear][%s]" value="1" /> %s</label></p>',
				esc_attr( NR_STOCKS_OPTION ),
				esc_attr( $key_name ),
				esc_html__( 'Remove the saved key', 'nr-stocks' )
			);
		}
		if ( ! empty( $config['key_url'] ) ) {
			printf(
				'<p class="description"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( $config['key_url'] ),
				esc_html__( 'Get a free API key', 'nr-stocks' )
			);
		}
	}

	/**
	 * Field: Alpha Vantage host/entrypoint overrides (advanced).
	 */
	public function render_av_host() {
		$settings = self::get();
		printf(
			'<input size="50" type="text" name="%s[av_host]" value="%s" placeholder="https://www.alphavantage.co" /> ',
			esc_attr( NR_STOCKS_OPTION ),
			esc_attr( $settings['av_host'] )
		);
		printf(
			'<input size="12" type="text" name="%s[av_entrypoint]" value="%s" placeholder="query" />',
			esc_attr( NR_STOCKS_OPTION ),
			esc_attr( $settings['av_entrypoint'] )
		);
		echo '<p class="description">';
		esc_html_e( 'Optional proxy mirror for Alpha Vantage. Leave empty for the official host.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Section blurb: cache.
	 */
	public function render_cache_blurb() {
		echo '<p>';
		esc_html_e( 'Upstream responses are cached per provider, endpoint, and parameters. Use 0 to disable caching for an endpoint (not recommended on busy sites).', 'nr-stocks' );
		echo '</p><p>';
		if ( wp_using_ext_object_cache() ) {
			esc_html_e( 'A persistent object cache was detected: cached data survives across requests and scales well.', 'nr-stocks' );
		} else {
			esc_html_e( 'No persistent object cache detected: entries live in the options table. High-traffic sites should add Redis or Memcached.', 'nr-stocks' );
		}
		echo '</p>';
	}

	/**
	 * Field: TTL input.
	 *
	 * @param array $args Field args with endpoint.
	 */
	public function render_ttl_field( $args ) {
		$endpoint = $args['endpoint'];
		$settings = self::get();
		$value    = isset( $settings['ttls'][ $endpoint ] ) ? (int) $settings['ttls'][ $endpoint ] : 0;
		printf(
			'<input size="10" type="number" min="0" max="86400" step="1" name="%s[ttls][%s]" value="%d" />',
			esc_attr( NR_STOCKS_OPTION ),
			esc_attr( $endpoint ),
			esc_attr( $value )
		);
		echo '<p class="description">';
		esc_html_e( 'Presets: 60 (1 min), 300 (5 min), 900 (15 min), 3600 (1 hour).', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Field: line color.
	 */
	public function render_line_color() {
		$settings = self::get();
		printf(
			'<input type="text" name="%s[line_color]" value="%s" class="nr-stocks-color" />',
			esc_attr( NR_STOCKS_OPTION ),
			esc_attr( $settings['line_color'] )
		);
	}

	/**
	 * Section blurb: headless REST use.
	 */
	public function render_headless_blurb() {
		echo '<p>';
		esc_html_e( 'Read-only JSON endpoints under nr-stocks/v1 for decoupled frontends. Same-origin requests always work.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Field: REST rate limit.
	 */
	public function render_rate_limit() {
		$settings = self::get();
		printf(
			'<input size="10" type="number" min="0" max="3600" step="1" name="%s[rest_rate_limit]" value="%d" />',
			esc_attr( NR_STOCKS_OPTION ),
			(int) $settings['rest_rate_limit']
		);
		echo '<p class="description">';
		esc_html_e( '0 means no limit. Applies per IP address and route.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Field: site-wide budget for anonymous upstream fetches.
	 */
	public function render_miss_limit() {
		$settings = self::get();
		printf(
			'<input size="10" type="number" min="0" max="3600" step="1" name="%s[rest_miss_limit]" value="%d" />',
			esc_attr( NR_STOCKS_OPTION ),
			(int) $settings['rest_miss_limit']
		);
		echo '<p class="description">';
		esc_html_e( 'Cache misses that logged-out REST requests may send upstream, across all visitors. 0 means no limit.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Field: CORS origins.
	 */
	public function render_cors_origins() {
		$settings = self::get();
		$value    = is_array( $settings['rest_cors_origins'] ) ? implode( "\n", $settings['rest_cors_origins'] ) : '';
		printf(
			'<textarea rows="3" cols="50" name="%s[rest_cors_origins]" placeholder="app.example.com">%s</textarea>',
			esc_attr( NR_STOCKS_OPTION ),
			esc_textarea( $value )
		);
		echo '<p class="description">';
		esc_html_e( 'One host per line. Empty means browsers on other origins are refused.', 'nr-stocks' );
		echo '</p>';
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$clean                     = self::defaults();
		$provider                  = isset( $input['default_provider'] ) ? sanitize_key( $input['default_provider'] ) : '';
		$clean['default_provider'] = null !== Provider_Registry::get( $provider ) ? $provider : 'alphavantage';

		$saved      = get_option( NR_STOCKS_OPTION, array() );
		$saved_keys = is_array( $saved ) && isset( $saved['keys'] ) && is_array( $saved['keys'] ) ? $saved['keys'] : array();
		$new_keys   = isset( $input['keys'] ) && is_array( $input['keys'] ) ? $input['keys'] : array();
		$to_clear   = isset( $input['keys_clear'] ) && is_array( $input['keys_clear'] ) ? $input['keys_clear'] : array();
		// Saved keys are kept even while the add-on that uses them is inactive.
		foreach ( array_keys( $saved_keys ) as $name ) {
			if ( ! isset( $clean['keys'][ $name ] ) ) {
				$clean['keys'][ $name ] = '';
			}
		}
		// Providers added through the nr_stocks_providers filter have keys too.
		foreach ( Provider_Registry::all() as $config ) {
			if ( ! empty( $config['key_setting'] ) && ! isset( $clean['keys'][ $config['key_setting'] ] ) ) {
				$clean['keys'][ $config['key_setting'] ] = '';
			}
		}
		foreach ( $clean['keys'] as $name => $unused ) {
			$new_key = isset( $new_keys[ $name ] ) ? sanitize_text_field( $new_keys[ $name ] ) : '';
			if ( '' !== $new_key ) {
				$clean['keys'][ $name ] = $new_key;
			} elseif ( empty( $to_clear[ $name ] ) && isset( $saved_keys[ $name ] ) ) {
				// Saved keys are not sent to the form, so an empty field keeps them.
				$clean['keys'][ $name ] = (string) $saved_keys[ $name ];
			}
		}

		if ( isset( $input['ttls'] ) && is_array( $input['ttls'] ) ) {
			foreach ( $clean['ttls'] as $endpoint => $unused ) {
				if ( isset( $input['ttls'][ $endpoint ] ) ) {
					$clean['ttls'][ $endpoint ] = max( 0, min( 86400, absint( $input['ttls'][ $endpoint ] ) ) );
				}
			}
		}

		$color               = isset( $input['line_color'] ) ? sanitize_hex_color( $input['line_color'] ) : '';
		$clean['line_color'] = $color ? $color : self::defaults()['line_color'];

		$clean['rest_rate_limit'] = isset( $input['rest_rate_limit'] ) ? max( 0, min( 3600, absint( $input['rest_rate_limit'] ) ) ) : 60;

		$clean['rest_miss_limit'] = isset( $input['rest_miss_limit'] ) ? max( 0, min( 3600, absint( $input['rest_miss_limit'] ) ) ) : $clean['rest_miss_limit'];

		$origins = array();
		if ( isset( $input['rest_cors_origins'] ) ) {
			// WordPress sanitizes twice when the option does not exist yet, so
			// the already-clean array form has to round-trip here.
			$raw   = is_array( $input['rest_cors_origins'] ) ? implode( "\n", array_filter( $input['rest_cors_origins'], 'is_string' ) ) : (string) $input['rest_cors_origins'];
			$lines = preg_split( '/[\r\n,]+/', $raw );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( false === strpos( $line, '://' ) ) {
					$line = 'https://' . $line;
				}
				$host = strtolower( (string) wp_parse_url( $line, PHP_URL_HOST ) );
				if ( '' !== $host ) {
					$origins[] = $host;
				}
			}
		}
		$clean['rest_cors_origins'] = array_values( array_unique( $origins ) );

		$clean['av_host']       = isset( $input['av_host'] ) ? esc_url_raw( trim( (string) $input['av_host'] ) ) : '';
		$clean['av_entrypoint'] = isset( $input['av_entrypoint'] ) ? sanitize_key( trim( (string) $input['av_entrypoint'] ) ) : '';

		if ( function_exists( 'add_settings_error' ) && empty( get_settings_errors( 'nr_stocks_messages' ) ) ) {
			add_settings_error(
				'nr_stocks_messages',
				'settings_updated',
				__( 'Settings saved. The stock cache was purged.', 'nr-stocks' ),
				'updated'
			);
		}
		Cache::purge_all();

		return $clean;
	}

	/**
	 * Handles the purge-cache button.
	 */
	public function handle_purge() {
		check_admin_referer( 'nr_stocks_purge_cache' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'nr-stocks' ), 403 );
		}
		Cache::purge_all();
		wp_safe_redirect(
			add_query_arg(
				'nr-stocks-purged',
				'1',
				admin_url( 'options-general.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	/**
	 * Adds a Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'nr-stocks' )
		);
		return $links;
	}
}
