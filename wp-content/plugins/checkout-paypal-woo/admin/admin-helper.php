<?php
/**
 * Admin helper.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Admin;

use CPPW\Inc\Helper;

/**
 * Admin helper functions.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */
class Admin_Helper {

	/**
	 * Middleware link.
	 *
	 * @var string $middleware_url
	 * @since 1.0.0
	 */
	private static $middleware_url = 'https://paypal-connect.checkoutplugins.com/';

	/**
	 * Get setting keys.
	 *
	 * @param string $mode Paypal mode.
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_setting_keys( $mode = '' ) {
		// Live setting keys.
		$live = [
			'cppw_client_id',
			'cppw_secret_key',
			'cppw_live_con_status',
			'cppw_live_account_id',
		];

		// sandbox setting keys.
		$sandbox = [
			'cppw_sandbox_client_id',
			'cppw_sandbox_secret_key',
			'cppw_sandbox_con_status',
			'cppw_sandbox_account_id',
		];

		if ( 'live' === $mode ) {
			return $live;
		} elseif ( 'sandbox' === $mode ) {
			return $sandbox;
		}

		// Extra setting keys.
		$extra = [ 'cppw_mode', 'cppw_debug_log', 'cppw_auto_connect' ];
		return array_merge( $live, $sandbox, $extra );
	}

	/**
	 * This method is used to update paypal options to the database.
	 *
	 * @param array $options settings array of the paypal.
	 * @since 1.0.0
	 * @return bool
	 */
	public static function update_options( $options ) {
		if ( ! is_array( $options ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}
		return true;
	}

	/**
	 * Generates Paypal Authorization URL for onboarding process.
	 *
	 * @param string $mode Paypal mode.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_paypal_connect_url( $mode = '' ) {
		$environment  = 'live' === $mode ? 'live' : 'sandbox';
		$tracking_id  = self::random_string( 32 );
		$seller_nonce = self::get_seller_nonce( $environment );
		$args         = [
			'body' => [
				'seller_nonce' => $seller_nonce,
				'environment'  => $environment,
				'tracking_id'  => $tracking_id,
				'return_url'   => add_query_arg(
					[
						'redirectUrl' => urlencode( //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
							add_query_arg(
								[
									'_connect_nonce' => \wp_create_nonce( 'wc-cppw-connect' ),
									'environment'    => $environment,
								],
								admin_url( 'admin.php?page=wc-settings&tab=cppw_api_settings' )
							)
						),
					],
					admin_url( 'admin.php?page=wc-settings&tab=cppw_api_settings' )
				),
			],
		];

		$response = wp_remote_post( self::$middleware_url, $args );
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );

		if ( is_array( $response ) && ! empty( $response['success'] ) && ! empty( $response['signup_link'] ) && ! empty( $response['partner_id'] ) ) {
			// Add partner id.
			update_option( CPPW_PARTNER_ID . $mode, $response['partner_id'] );
			return $response['signup_link'] . '&displayMode=minibrowser';
		} else {
			return '';
		}
	}

	/**
	 * Generate random string
	 *
	 * @param int $len Random string length.
	 * @since 1.0.0
	 * @return string
	 */
	public static function random_string( $len = 64 ) {
		$chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$max    = strlen( $chars ) - 1;
		$string = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$string .= $chars[ wp_rand( 0, $max ) ];
		}
		return $string;
	}

	/**
	 * Create and get seller nonce.
	 *
	 * @param string $mode Paypal mode live or sandbox.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_seller_nonce( $mode ) {
		$option_name = 'cppw_paypal_connect_seller_nonce_' . $mode;
		$get_option  = get_option( $option_name );
		if ( is_string( $get_option ) && ! empty( $get_option ) ) {
			return $get_option;
		} else {
			$seller_nonce = self::random_string();
			update_option( $option_name, $seller_nonce );
			return $seller_nonce;
		}
	}

	/**
	 * Checks if paypal is connected or not.
	 *
	 * @param string $mode Paypal mode live or sandbox.
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function is_paypal_connected( $mode ) {
		return 'success' === Helper::get_setting( 'cppw_' . $mode . '_con_status' ) ? true : false;
	}
}
