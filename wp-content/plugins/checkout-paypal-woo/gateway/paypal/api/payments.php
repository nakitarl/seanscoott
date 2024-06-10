<?php
/**
 * Paypal payments
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal\Api;

use CPPW\Gateway\Paypal\Api\Client;

/**
 * Paypal api client.
 */
class Payments {
	/**
	 * Paypal client
	 */
	use Client;

	/**
	 * Create payment on paypal.
	 *
	 * @param array $body Api request body.
	 * @since 1.0.0
	 * @return array
	 */
	public static function create( $body ) {
		$remote_url = 'v2/checkout/orders';
		return Client::request( $remote_url, $body );
	}

	/**
	 * Capture order payment.
	 *
	 * @param string $token Token create by create payment.
	 * @param array  $body Api request body params.
	 * @since 1.0.0
	 * @return array
	 */
	public static function capture( $token, $body = [] ) {
		$remote_url = "v2/checkout/orders/{$token}/capture";
		return Client::request( $remote_url, $body );
	}

	/**
	 * Capture order payment.
	 *
	 * @param string $token Token create by create payment.
	 * @param array  $body Api request body params.
	 * @since 1.0.0
	 * @return array
	 */
	public static function authorize( $token, $body = [] ) {
		$remote_url = "v2/checkout/orders/{$token}/authorize";
		return Client::request( $remote_url, $body );
	}

	/**
	 * Create payment refund.
	 *
	 * @param array  $body Api request body.
	 * @param string $paypal_txn_id Paypal transaction id.
	 * @since 1.0.0
	 * @return array
	 */
	public static function refund( $body, $paypal_txn_id ) {
		$remote_url = "v2/payments/captures/{$paypal_txn_id}/refund";
		return Client::request( $remote_url, $body );
	}

	/**
	 * Create billing agreement token.
	 *
	 * @param array $body Api request body params.
	 * @since 1.0.0
	 * @return array
	 */
	public static function billing_agreement_token( $body ) {
		$remote_url = 'v1/billing-agreements/agreement-tokens';
		return Client::request( $remote_url, $body );
	}

	/**
	 * Create billing agreement.
	 *
	 * @param string $billing_token Billing token.
	 * @since 1.0.0
	 * @return array
	 */
	public static function create_billing_agreement( $billing_token ) {
		$remote_url = 'v1/billing-agreements/agreements';
		return Client::request( $remote_url, [ 'token_id' => $billing_token ] );
	}
}
