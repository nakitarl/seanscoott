<?php
/**
 * Frontend Rout
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal;

use CPPW\Inc\Traits\Get_Instance;
use CPPW\Gateway\Paypal\Api\Payments;
use CPPW\Inc\Helper;

/**
 * Consists frontend scripts for payment gateways
 *
 * @since 1.0.0
 */
class Front_Route {

	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'checkout_endpoint' ] );
	}

	/**
	 * Checkout page endpoint.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function checkout_endpoint() {
		register_rest_route(
			'cppw',
			'/v1/checkout',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'checkout_endpoint_handler' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Checkout endpoint handler.
	 *
	 * @param \WP_REST_Request $request WP rest request object.
	 * @since 1.0.0
	 * @return mixed
	 */
	public function checkout_endpoint_handler( \WP_REST_Request $request ) {
		$get_param = $request->get_params();

		if ( empty( $get_param['security'] ) && ! wp_verify_nonce( $get_param['security'], 'checkout_cart' ) ) {
			return;
		}

		// Create billing agreement.
		if ( isset( $get_param['create-agreement-token'] ) && 'create' === $get_param['create-agreement-token'] ) {
			return $this->create_agreement_token();
		}

		$total = ! empty( $get_param['total'] ) ? sanitize_text_field( $get_param['total'] ) : false;

		if ( ! $total ) {
			return;
		}

		$get_paypal_type = Helper::get_setting( 'paypal_type', 'cppw_paypal' );

		$body     = [
			'intent'              => 'authorize' === $get_paypal_type ? 'AUTHORIZE' : 'CAPTURE',
			'purchase_units'      => [
				[
					'amount' => [
						'value'         => $total,
						'currency_code' => get_woocommerce_currency(),
					],
				],
			],
			'application_context' => [
				'cancel_url' => wc_get_checkout_url(),
				'return_url' => wc_get_checkout_url(),
			],
		];
		$response = Payments::create( $body );
		return ! empty( $response['id'] ) ? $response['id'] : false;
	}

	/**
	 * Create billing agreement token.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	private function create_agreement_token() {
		$data        = [
			'description' => __( 'Woocommerce checkout order', 'checkout-paypal-woo' ),
			'payer'       => [
				'payment_method' => 'PAYPAL',
			],
			'plan'        => [
				'type'                 => 'MERCHANT_INITIATED_BILLING',
				'merchant_preferences' => [
					'return_url'            => wc_get_checkout_url(),
					'cancel_url'            => wc_get_checkout_url(),
					'skip_shipping_address' => true,
				],
			],
		];
		$get_billing = Payments::billing_agreement_token( $data );
		if ( ! empty( $get_billing['token_id'] ) ) {
			return $get_billing['token_id'];
		}
	}
}
