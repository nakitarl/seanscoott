<?php
/**
 * Paypal Gateway webhook.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Inc;

/**
 * Paypal Webhook.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Default global values
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $global_defaults = [
		'cppw_sandbox_client_id'  => '',
		'cppw_pub_key'            => '',
		'cppw_sandbox_secret_key' => '',
		'cppw_secret_key'         => '',
		'cppw_sandbox_con_status' => '',
		'cppw_live_con_status'    => '',
		'cppw_mode'               => 'sandbox',
		'cppw_sandbox_account_id' => '',
		'cppw_live_account_id'    => '',
		'cppw_client_id'          => '',
		'cppw_debug_log'          => 'yes',
	];

	/**
	 * Paypal get all settings
	 *
	 * @since 1.0.0
	 * @return array $global_settings It returns all paypal settings in an array.
	 */
	public static function get_gateway_defaults() {
		return apply_filters(
			'cppw_paypal_gateway_defaults_settings',
			[
				'woocommerce_cppw_paypal_settings' => [
					'enabled'      => 'no',
					'payment_type' => 'smart',
					'charge_type'  => 'automatic',
				],
			]
		);
	}

	/**
	 * Get all settings of a particular gateway
	 *
	 * @param string $gateway gateway id.
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_gateway_settings( $gateway = 'cppw_paypal' ) {
		$default_settings = [];
		$setting_name     = 'woocommerce_' . $gateway . '_settings';
		$saved_settings   = is_array( get_option( $setting_name, [] ) ) ? get_option( $setting_name, [] ) : [];
		$gateway_defaults = self::get_gateway_defaults();

		if ( isset( $gateway_defaults[ $setting_name ] ) ) {
			$default_settings = $gateway_defaults[ $setting_name ];
		}

		$settings = array_merge( $default_settings, $saved_settings );

		return apply_filters( 'cppw_gateway_settings', $settings );
	}

	/**
	 * Get value of gateway option parameter
	 *
	 * @param string $key key name.
	 * @param string $gateway gateway id.
	 * @since 1.0.0
	 * @return mixed
	 */
	public static function get_gateway_setting( $key = '', $gateway = 'cppw_paypal' ) {
		$settings = self::get_gateway_settings( $gateway );
		$value    = false;

		if ( isset( $settings[ $key ] ) ) {
			$value = $settings[ $key ];
		}

		return $value;
	}

	/**
	 * Get value of global option
	 *
	 * @param string $key value of global setting.
	 * @since 1.0.0
	 * @return string|array
	 */
	public static function get_global_setting( $key ) {
		$db_data = get_option( $key );
		$default = isset( self::$global_defaults[ $key ] ) ? self::$global_defaults[ $key ] : '';
		return $db_data ? $db_data : $default;
	}

	/**
	 * Paypal get settings value by key.
	 *
	 * @param string $key Name of the key to get the value.
	 * @param string $gateway Name of the payment gateway to get options from the database.
	 *
	 * @return array|string $global_settings It returns all paypal settings in an array.
	 */
	public static function get_setting( $key = '', $gateway = '' ) {
		$result = '';
		if ( '' !== $gateway ) {
			$result = self::get_gateway_setting( $key, $gateway );
		} else {
			$result = self::get_global_setting( $key );
		}
		return ! empty( $result ) ? apply_filters( $key, $result ) : '';
	}

	/**
	 * Paypal get current mode
	 *
	 * @return string $mode It returns current mode of the paypal payment gateway.
	 */
	public static function get_payment_mode() {
		return apply_filters( 'cppw_payment_mode', self::get_setting( 'cppw_mode' ) );
	}

	/**
	 * Get webhook secret key.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public static function get_webhook_secret() {
		$endpoint_secret = '';
		$get_mode        = self::get_payment_mode();

		if ( 'live' === $get_mode || 'sandbox' === $get_mode ) {
			$endpoint_secret = self::get_webhook_id( $get_mode );
		}

		return empty( trim( $endpoint_secret ) ) ? false : $endpoint_secret;
	}

	/**
	 * Logs js errors
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function js_errors() {
		if ( ! isset( $_POST['_security'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['_security'] ), 'cppw_js_error_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Nonce', 'checkout-paypal-woo' ) ] );
		}

		if ( isset( $_POST['error'] ) ) {
			$error = sanitize_text_field( $_POST['error'] );
			Logger::error( $error, true );
			wp_send_json_success( [ 'message' => $error ] );
		}
	}

	/**
	 * Get secret webhook id.
	 *
	 * @param string $mode Paypal mode.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_webhook_id( $mode ) {
		$get_option = get_option( CPPW_WEBHOOK_ID . $mode );
		return ! empty( $get_option ) && is_string( $get_option ) ? $get_option : '';
	}

	/**
	 * Get Paypal host url.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function paypal_host_url() {
		return 'live' === self::get_payment_mode() ? CPPW_PAYPAL_API_URL : CPPW_PAYPAL_SANDBOX_API_URL;
	}

	/**
	 * Get client id.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_client_id() {
		return apply_filters( 'cppw_get_client_id', ( 'live' === self::get_payment_mode() ) ? get_option( 'cppw_client_id' ) : get_option( 'cppw_sandbox_client_id' ) );
	}

	/**
	 * Paypal bn code.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function bn_code() {
		return 'BrainstormForceInc_Cart_PPCPBran';
	}
}
