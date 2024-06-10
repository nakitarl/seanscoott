<?php
/**
 * Paypal Webhook Class
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal\Webhook;

use CPPW\Gateway\Paypal\Api\Client;
use CPPW\Inc\Logger;

/**
 * Webhook api requests.
 *
 * @since 1.0.0
 */
class Webhook {
	/**
	 * Create webhook id.
	 *
	 * @param string $mode Paypal mode sandbox or live.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function create_id( $mode ) {
		$remote_url     = 'v1/notifications/webhooks';
		$webhook_id_key = CPPW_WEBHOOK_ID . $mode;
		/**
		 * For testing localhost put url online url like
		 * $url = https://qn0eqcjccvyffobjkenm4o.hooks.webhookrelay.com
		 */

		$url    = \rest_url( 'cppw/' . $mode . '/webhook' );
		$params = [
			'url'         => $url,
			'event_types' => [
				[
					'name' => 'PAYMENT.CAPTURE.COMPLETED',
				],
				[
					'name' => 'PAYMENT.CAPTURE.REFUNDED',
				],
				[
					'name' => 'PAYMENT.AUTHORIZATION.VOIDED',
				],
				[
					'name' => 'PAYMENT.CAPTURE.DENIED',
				],
			],
		];

		$response = Client::request( $remote_url, $params );
		if ( ! empty( $response['id'] ) ) {
			update_option( $webhook_id_key, $response['id'] );
		} else {
			update_option( $webhook_id_key, '' );
			Logger::error( __( 'Error creating webhook id.', 'checkout-paypal-woo' ) );
		}
	}

	/**
	 * Verify webhook requests.
	 *
	 * @param array $params Webhook verification parameters.
	 * @since 1.0.0
	 * @return array
	 */
	public static function verify_signature( $params ) {
		$remote_url = 'v1/notifications/verify-webhook-signature';
		return Client::request( $remote_url, $params );
	}

	/**
	 * Remove Paypal webhook id from Paypal.
	 *
	 * @param string $webhook_id_key Webhook id.
	 * @since 1.0.0
	 * @return void
	 */
	public static function remove_exist_webhook_id( $webhook_id_key ) {
		$get_webhook_id = get_option( $webhook_id_key );
		if ( $get_webhook_id ) {
			// Remove webhook id from Paypal.
			$remote_url = 'v1/notifications/webhooks/' . $get_webhook_id;
			$response   = Client::request( $remote_url, [], 'delete' );
			if ( ! isset( $response['message'] ) ) {
				delete_option( $webhook_id_key );
			}
		}
	}
}
