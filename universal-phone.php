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
			'defaultCountry'  => apply_filters( 'universal_phone_default_country', 'us' ),
			'overwriteInput'  => apply_filters( 'universal_phone_overwrite_input', false ), // Set to true to overwrite visible input with E.164
		);
		wp_localize_script( 'universal-iti-init', 'UniversalPhoneData', $data );
	}

}

Universal_Phone_Input::get_instance();
