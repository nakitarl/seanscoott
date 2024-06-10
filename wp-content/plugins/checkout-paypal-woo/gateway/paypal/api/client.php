<?php
/**
 * Paypal Client
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal\Api;

use CPPW\Inc\Logger;
use CPPW\Inc\Helper;
use WP_Error;

/**
 * Paypal api client.
 */
trait Client {

	/**
	 * Paypal success status codes.
	 * Status codes for successfully response status code for Paypal api endpoints following :
	 * Create order 201 - https://developer.paypal.com/docs/api/orders/v2/
	 * Capture order 201 - https://developer.paypal.com/docs/api/orders/v2/#orders_capture
	 * Refund order 201 - https://developer.paypal.com/docs/api/payments/v2/#captures_refund
	 * Remove webhook id 204 - https://developer.paypal.com/docs/api/webhooks/v1/#webhooks_delete
	 * Create Paypal webhook 200 - https://developer.paypal.com/docs/api/webhooks/v1/
	 * Verify webhook response signature 200 - https://developer.paypal.com/docs/api/webhooks/v1/
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static $paypal_status_codes = [ 200, 201, 204 ];

	/**
	 * Get bearer token.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public static function get_bearer() {
		$env = 'live' === Helper::get_payment_mode() ? 'live' : 'sandbox';
		if ( 'live' === $env ) {
			$client_id  = get_option( 'cppw_client_id' );
			$secret_key = get_option( 'cppw_secret_key' );
		} else {
			$client_id  = get_option( 'cppw_sandbox_client_id' );
			$secret_key = get_option( 'cppw_sandbox_secret_key' );
		}

		if ( empty( $client_id ) || empty( $secret_key ) ) {
			return;
		}

		$remote_url      = Helper::paypal_host_url() . 'v1/oauth2/token';
		$authorization   = base64_encode( $client_id . ':' . $secret_key ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$args            = [
			'headers' => [
				'Authorization'                 => 'Basic ' . $authorization,
				'Content-Type'                  => 'application/x-www-form-urlencoded',
				'PayPal-Partner-Attribution-Id' => Helper::bn_code(),
			],
			'body'    => [ 'grant_type' => 'client_credentials' ],
		];
		$remote_response = wp_remote_post( $remote_url, $args );
		if ( is_wp_error( $remote_response ) ) {
			/* translators: error message */
			Logger::error( sprintf( __( 'Error getting authorization code : %1$1s', 'checkout-paypal-woo' ), $remote_response->get_error_message() ) );
		} else {
			$retrieve_body = wp_remote_retrieve_body( $remote_response );
			$response      = json_decode( $retrieve_body, true );
			return is_array( $response ) && ! empty( $response['access_token'] ) ? $response['access_token'] : '';
		}
	}

	/**
	 * Handle request.
	 *
	 * @param string $url Paypal endpoint url.
	 * @param array  $body Api request body.
	 * @param string $method Paypal api method '' || get.
	 * @since 1.0.0
	 * @return array
	 */
	public static function request( $url, $body = [], $method = '' ) {

		$remote_url = Helper::paypal_host_url() . $url;

		$args = [
			'headers' => [
				'Authorization'                 => 'Bearer ' . self::get_bearer(),
				'Content-Type'                  => 'application/json',
				'PayPal-Partner-Attribution-Id' => Helper::bn_code(),
			],
		];

		if ( ! empty( $body ) && is_array( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		if ( 'get' === $method ) {
			$remote_response = wp_remote_get( $remote_url, $args );
		} elseif ( 'delete' === $method ) {
			$args['method']  = 'DELETE';
			$remote_response = wp_remote_request( $remote_url, $args );
		} else {
			$remote_response = wp_remote_post( $remote_url, $args );
		}

		if ( $remote_response instanceof WP_Error ) {
			$show_wp_errors = implode( "\n", $remote_response->get_error_messages() );
			/* translators: error message */
			Logger::error( sprintf( __( 'Error : %s', 'checkout-paypal-woo' ), $show_wp_errors ) );
			return [ 'message' => $show_wp_errors ];
		}

		$status_code   = absint( wp_remote_retrieve_response_code( $remote_response ) );
		$retrieve_body = wp_remote_retrieve_body( $remote_response );
		$decoded_body  = json_decode( $retrieve_body, true );

		if ( ! is_array( $decoded_body ) ) {
			return [ 'message' => __( 'Invalid request', 'checkout-paypal-woo' ) ];
		}

		// Place Paypal debug id in response.
		if ( isset( $remote_response['headers']->getAll()['paypal-debug-id'] ) ) {
			$decoded_body['paypal-debug-id'] = $remote_response['headers']->getAll()['paypal-debug-id'];
		}

		if ( ! in_array( $status_code, self::$paypal_status_codes, true ) ) {
			$error_message = self::get_error( $decoded_body );
			if ( ! empty( $error_message ) ) {
				Logger::error( $error_message );
			}

			return array_merge( $decoded_body, [ 'message' => $error_message ] );
		}

		return $decoded_body;
	}

	/**
	 * Get paypal endpoint errors.
	 *
	 * @param array $decoded_body Get response from paypal endpoint response.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_error( $decoded_body ) {
		$error_message = '';
		// Case 1 - if retrieved error and error_description.
		if ( ! empty( $decoded_body['name'] ) && ! empty( $decoded_body['message'] ) ) {
			// If detail available.
			if ( ! empty( $decoded_body['details']['issue'] ) && ! empty( $decoded_body['details']['description'] ) ) {
				$error_message = $decoded_body['details']['issue'] . ' : ' . $decoded_body['details']['description'];
			} elseif ( ! empty( $decoded_body['details']['issue'] ) ) {
				$error_message = $decoded_body['details']['issue'];
			} else {
				$error_message = $decoded_body['name'] . ' : ' . $decoded_body['message'];
			}
		}

		// Case 2 - if retrieved error name and detail.
		if ( ! empty( $decoded_body['error'] ) && ! empty( $decoded_body['error_description'] ) ) {
			$error_message = $decoded_body['error'] . ' : ' . $decoded_body['error_description'];
		}

		return $error_message;
	}
}
