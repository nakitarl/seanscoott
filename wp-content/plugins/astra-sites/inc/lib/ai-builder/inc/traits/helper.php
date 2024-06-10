<?php
/**
 * Trait.
 *
 * @package {{package}}
 * @since 0.0.1
 */

namespace AiBuilder\Inc\Traits;

use AiBuilder\Inc\Traits\Instance;

/**
 * Trait Instance.
 */
class Helper {

	use Instance;

	/**
	 * Get an option from the database.
	 *
	 * @param string  $key              The option key.
	 * @param mixed   $default          The option default value if option is not available.
	 * @param boolean $network_override Whether to allow the network admin setting to be overridden on subsites.
	 * @since 1.0.0
	 * @return mixed  The option value.
	 */
	public static function get_admin_settings_option( $key, $default = false, $network_override = false ) {
		// Get the site-wide option if we're in the network admin.
		return $network_override && is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default );
	}

	/**
	 * Delete an option from the database for.
	 *
	 * @param string  $key              The option key.
	 * @param boolean $network_override Whether to allow the network admin setting to be overridden on subsites.
	 * @since 1.0.0
	 * @return void
	 */
	public static function delete_admin_settings_option( $key, $network_override = false ) {
		// Delete the site-wide option if we're in the network admin.
		if ( $network_override && is_multisite() ) {
			delete_site_option( $key );
		} else {
			delete_option( $key );
		}
	}

	/**
	 * Get image placeholder array.
	 *
	 * @since 4.0.9
	 * @return array<string, array<string, string>>
	 */
	public static function get_image_placeholders() {

		return array(
			array(
				'auther_name'   => 'Placeholder',
				'id'            => 'placeholder-landscape',
				'orientation'   => 'landscape',
				'optimized_url' => 'https://websitedemos.net/wp-content/uploads/2024/02/placeholder-landscape.png',
				'url'           => 'https://websitedemos.net/wp-content/uploads/2024/02/placeholder-landscape.png',
			),
			array(
				'auther_name'   => 'Placeholder',
				'id'            => 'placeholder-portrait',
				'orientation'   => 'portrait',
				'optimized_url' => 'https://websitedemos.net/wp-content/uploads/2024/02/placeholder-portrait.png',
				'url'           => 'https://websitedemos.net/wp-content/uploads/2024/02/placeholder-portrait.png',
			),
		);
	}

	/**
	 * Get Saved Token.
	 *
	 * @since 4.0.0
	 * @return string
	 */
	public static function get_token() {
		$token_details = get_option(
			'zip_ai_settings',
			array(
				'auth_token' => '',
				'zip_token'  => '',
				'email'      => '',
			)
		);
		return isset( $token_details['zip_token'] ) ? self::decrypt( $token_details['zip_token'] ) : '';
	}

		/**
		 * Decrypt data using base64.
		 *
		 * @param string $input The input string which needs to be decrypted.
		 * @since 4.0.0
		 * @return string The decrypted string.
		 */
	public static function decrypt( $input ) {
		// If the input is empty or not a string, then abandon ship.
		if ( empty( $input ) || ! is_string( $input ) ) {
			return '';
		}

		// Decrypt the input and return it.
		$base_64 = $input . str_repeat( '=', strlen( $input ) % 4 );
		$decode  = base64_decode( $base_64 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return $decode;
	}

	/**
	 * Get installed PHP version.
	 *
	 * @return float PHP version.
	 * @since 3.0.16
	 */
	public static function get_php_version() {
		if ( defined( 'PHP_MAJOR_VERSION' ) && defined( 'PHP_MINOR_VERSION' ) && defined( 'PHP_RELEASE_VERSION' ) ) { // phpcs:ignore
			return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
		}

		return phpversion();
	}

	/**
	 * Has Pro Version Support?
	 * And
	 * Is Pro Version Installed?
	 *
	 * Check Pro plugin version exist of requested plugin lite version.
	 *
	 * Eg. If plugin 'BB Lite Version' required to import demo. Then we check the 'BB Agency Version' is exist?
	 * If yes then we only 'Activate' Agency Version. [We couldn't install agency version.]
	 * Else we 'Activate' or 'Install' Lite Version.
	 *
	 * @since 1.0.1
	 *
	 * @param  string $lite_version Lite version init file.
	 * @return mixed               Return false if not installed or not supported by us
	 *                                    else return 'Pro' version details.
	 */
	public static function pro_plugin_exist( $lite_version = '' ) {

		// Lite init => Pro init.
		$plugins = apply_filters(
			'astra_sites_pro_plugin_exist',
			array(
				'beaver-builder-lite-version/fl-builder.php' => array(
					'slug' => 'bb-plugin',
					'init' => 'bb-plugin/fl-builder.php',
					'name' => 'Beaver Builder Plugin',
				),
				'ultimate-addons-for-beaver-builder-lite/bb-ultimate-addon.php' => array(
					'slug' => 'bb-ultimate-addon',
					'init' => 'bb-ultimate-addon/bb-ultimate-addon.php',
					'name' => 'Ultimate Addon for Beaver Builder',
				),
				'wpforms-lite/wpforms.php' => array(
					'slug' => 'wpforms',
					'init' => 'wpforms/wpforms.php',
					'name' => 'WPForms',
				),
			),
			$lite_version
		);

		if ( isset( $plugins[ $lite_version ] ) ) {

			// Pro plugin directory exist?
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugins[ $lite_version ]['init'] ) ) {
				return $plugins[ $lite_version ];
			}
		}

		return false;
	}
}

