<?php
/**
 * Subscriptions Trait.
 *
 * @package checkout-paypal-woo
 */

namespace CPPW\Gateway\Paypal\Subscription;

use CPPW\Gateway\Paypal\Subscription\Subscription_Helper as SH;
use CPPW\Inc\Logger;
use CPPW\Gateway\Paypal\Api\Payments;
use WC_Order;
use WP_Error;

/**
 * Trait for Subscriptions utility functions.
 */
trait Subscriptions {
	use SH;

	/**
	 * Initialize subscription support and hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_init_subscriptions() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}
		$this->supports = $this->add_subscription_filters( $this->supports );
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
		add_action( 'woocommerce_subscriptions_change_payment_after_submit', [ $this, 'add_container_to_change_payment_method' ] );
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @param object $order WooCommerce order object.
	 * @param string $billing_token Paypal billing token.
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function process_change_subscription_payment_method( $order, $billing_token ) {
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'error', __( 'Order not found.', 'checkout-paypal-woo' ) );
		}
		// Create billing agreement in paypal.
		if ( ! empty( $this->is_changing_payment_method_for_subscription() ) ) {
			return $this->proceed_change_payment_method( $order, $billing_token );
		}

		$get_billing_agreement = Payments::create_billing_agreement( $billing_token );

		if ( ! empty( $get_billing_agreement['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $get_billing_agreement['debug_id'] );
		}

		if ( empty( $get_billing_agreement['id'] ) ) {
			return new WP_Error( 'error', ! empty( $get_billing_agreement['message'] ) ? $get_billing_agreement['message'] : __( 'Unable to create Paypal billing agreement.', 'checkout-paypal-woo' ) );
		}

		// Create order.
		$paypal_order = $this->create_paypal_order_of_wc( $order );

		if ( ! empty( $paypal_order['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $paypal_order['debug_id'] );
		}

		if ( empty( $paypal_order['id'] ) ) {
			return new WP_Error( 'error', ! empty( $paypal_order['message'] ) ? $paypal_order['message'] : __( 'Unable to create Paypal order.', 'checkout-paypal-woo' ) );
		}

		$order->update_meta_data( CPPW_SUB_AGREEMENT_ID, $get_billing_agreement['id'] );
		$process_payment = $this->process_payment_agreement( $get_billing_agreement['id'], $paypal_order['id'], $order );
		if ( $process_payment ) {
			$this->update_subscription_meta( $order, $get_billing_agreement['id'] );
			$order->save();
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		} else {
			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Proceed change subscription payment method.
	 *
	 * @param object $order WooCommerce order object.
	 * @param string $billing_token Paypal billing token.
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function proceed_change_payment_method( $order, $billing_token ) {
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'error', __( 'Order not found.', 'checkout-paypal-woo' ) );
		}
		$get_exist_billing_agreement = $order->get_meta( CPPW_SUB_AGREEMENT_ID );
		if ( empty( $get_exist_billing_agreement ) ) {
			$get_billing_agreement = Payments::create_billing_agreement( $billing_token );

			if ( ! empty( $get_billing_agreement['debug_id'] ) ) {
				$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $get_billing_agreement['debug_id'] );
			}

			if ( empty( $get_billing_agreement['id'] ) ) {
				return new WP_Error( 'error', ! empty( $get_billing_agreement['message'] ) ? $get_billing_agreement['message'] : __( 'Unable to create Paypal billing agreement.', 'checkout-paypal-woo' ) );
			}
			$order->update_meta_data( CPPW_SUB_AGREEMENT_ID, $get_billing_agreement['id'] );
			$order->save();
		}
		return [
			'result'   => 'success',
			'redirect' => wc_get_page_permalink( 'myaccount' ),
		];
	}

	/**
	 * Capture payment by agreement id and paypal order.
	 *
	 * @param string $agreement_id Paypal agreement id.
	 * @param string $paypal_order_id Paypal order id.
	 * @param object $order WooCommerce order object.
	 * @since 1.0.0
	 * @return boolean
	 */
	public function process_payment_agreement( $agreement_id, $paypal_order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		$body   = $this->generate_payment_source_body( $agreement_id );
		$result = 'authorize' === $this->paypal_type ? $this->authorize_order( $paypal_order_id, $order, $body ) : $this->capture_order( $paypal_order_id, $order, $body );
		return ! empty( $result['status'] ) ? true : false;
	}

	/**
	 * Generate payment source body.
	 *
	 * @param string $agreement_id Paypal agreement id.

	 * @since 1.0.0
	 * @return array
	 */
	public function generate_payment_source_body( $agreement_id ) {
		return [
			'payment_source' => [
				'token' => [
					'id'   => $agreement_id,
					'type' => 'BILLING_AGREEMENT',
				],
			],
		];
	}

	/**
	 * Update subscription meta.
	 *
	 * @param object $order WooCommerce order object.
	 * @param string $billing_agreement_id Paypal agreement id.
	 * @since 1.0.0
	 * @return void
	 */
	public function update_subscription_meta( $order, $billing_agreement_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}
		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
				$subscription->update_meta_data( CPPW_SUB_AGREEMENT_ID, $billing_agreement_id );
				$subscription->save();
		}
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param float  $amount_to_charge float The amount to charge.
	 * @param object $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 * @since 1.0.0
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		if ( ! $renewal_order instanceof WC_Order ) {
			return;
		}
		$get_billing_agreement_id = $renewal_order->get_meta( CPPW_SUB_AGREEMENT_ID );

		if ( ! is_string( $get_billing_agreement_id ) ) {
			return;
		}

		$order_id = $renewal_order->get_id();

		// Create order.
		$paypal_order = $this->create_paypal_order_of_wc( $renewal_order );

		if ( ! empty( $paypal_order['debug_id'] ) ) {
			$renewal_order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $paypal_order['debug_id'] );
		}

		if ( empty( $paypal_order['id'] ) ) {
			Logger::error( ! empty( $paypal_order['message'] ) ? $paypal_order['message'] : __( 'Paypal order not created order id : ', 'checkout-paypal-woo' ) . $order_id, true );
		}
		$this->process_payment_agreement( $get_billing_agreement_id, $paypal_order['id'], $renewal_order );
	}

	/**
	 * Add button in change payment method form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_container_to_change_payment_method() {
		?>
		<div id="cppw-paypal-change-payment-method-container"></div>
		<?php
	}
}
