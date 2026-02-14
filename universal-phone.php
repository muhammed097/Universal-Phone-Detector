<?php
/**
 * Plugin Name: Universal Phone Input
 * Description: Automatically adds international phone input with auto country detection to all forms across the site.
 * Version: 1.0.0
 * Author: Mohamed Ibrahim
 * Author URI: muhammedibrim97@gmail.com
 * Text Domain: universal-phone
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Universal_Phone_Input {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance has not been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_assets() {
		// Enqueue intl-tel-input CSS from CDN
		wp_enqueue_style( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css', array(), '17.0.19' );

		// Custom CSS for flag dropdown to ensure it overlays correctly on existing forms
		$custom_css = "
			.iti { width: 100%; }
			.iti__flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/img/flags.png'); }
			@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
			  .iti__flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/img/flags@2x.png'); }
			}
		";
		wp_add_inline_style( 'intl-tel-input', $custom_css );

		// Enqueue intl-tel-input JS from CDN
		wp_enqueue_script( 'intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js', array( 'jquery' ), '17.0.19', true );
		
		// Enqueue utils script. We load it separately to ensure it is available, though intlTelInput can load it dynamically if configured.
		// However, loading via wp_enqueue_script is more reliable for dependencies. 
		// Actually, intl-tel-input usually loads utils.js via a URL option. We will pass the URL in localization.
		
		// Enqueue our custom initialization script
		wp_enqueue_script( 'universal-iti-init', plugin_dir_url( __FILE__ ) . 'assets/universal-iti-init.js', array( 'intl-tel-input', 'jquery' ), '1.0.0', true );

		// Localize script with data
		$data = array(
			'utilsScript'     => 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js',
			'geoIpUrl'        => rest_url( 'universal-phone/v1/geo' ),
			'geoIpNonce'      => wp_create_nonce( 'wp_rest' ),
			'defaultCountry'  => apply_filters( 'universal_phone_default_country', 'us' ),
			'overwriteInput'  => apply_filters( 'universal_phone_overwrite_input', false ), // Set to true to overwrite visible input with E.164
		);
		wp_localize_script( 'universal-iti-init', 'UniversalPhoneData', $data );
	}

	/**
	 * Register REST API endpoint for GeoIP.
	 */
	public function register_rest_endpoint() {
		register_rest_route( 'universal-phone/v1', '/geo', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_geo_ip' ),
			'permission_callback' => '__return_true', // Public endpoint, limit via nonce check if needed, but usually this is open for frontend
		) );
	}

	/**
	 * Get GeoIP data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_geo_ip( $request ) {
		$ip = $this->get_client_ip();
		
		// Check for localhost
		if ( '127.0.0.1' === $ip || '::1' === $ip ) {
			return new WP_REST_Response( array( 'country_code' => 'US' ), 200 ); // Default for localhost
		}
		
		// Check transient to avoid hitting external API too often for the same IP
		$transient_key = 'universal_phone_geo_' . md5( $ip );
		$country_code  = get_transient( $transient_key );

		if ( false === $country_code ) {
			// Allow custom provider via filter
			$country_code = apply_filters( 'universal_phone_custom_geo_provider', false, $ip );

			if ( empty( $country_code ) ) {
				// Call external GeoIP service (IP-API.com is free for non-commercial use, http only)
				// For production (SSL), you might need a paid key or a different provider.
				// This is a demo implementation using ipapi.co (http) or ipapi.co (https free tier).
				// We'll use ipapi.co for HTTPS compatibility, but it has rate limits.
				// A more robust solution would use a local database (MaxMind) or a paid API.
				
				// Try distinct providers to ensure reliability in demo
				$response = wp_remote_get( "https://ipapi.co/{$ip}/json/" );

				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
					$body = wp_remote_retrieve_body( $response );
					$data = json_decode( $body, true );
					if ( isset( $data['country_code'] ) ) {
						$country_code = $data['country_code'];
					}
				}

				// Fallback to another free API if first fails
				if ( empty( $country_code ) ) {
					$response = wp_remote_get( "http://ip-api.com/json/{$ip}" );
					if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
						$body = wp_remote_retrieve_body( $response );
						$data = json_decode( $body, true );
						if ( isset( $data['countryCode'] ) ) {
							$country_code = $data['countryCode'];
						}
					}
				}
				
				// Final fallback
				if ( empty( $country_code ) ) {
					$country_code = 'US'; // Default fallback
				}
			}

			// Cache for 24 hours
			set_transient( $transient_key, $country_code, DAY_IN_SECONDS );
		}

		return new WP_REST_Response( array( 'country_code' => $country_code ), 200 );
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

}

Universal_Phone_Input::get_instance();
