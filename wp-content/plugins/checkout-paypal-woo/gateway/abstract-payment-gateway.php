<?php
/**
 * Abstract Payment Gateway
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway;

use WC_Payment_Gateway;
use CPPW\Inc\Helper;
use CPPW\Inc\Logger;
use WP_Error;
use CPPW\Gateway\Paypal\Paypal_Fee;
use CPPW\Gateway\Paypal\Api\Payments;
use WC_Order;

/**
 * Abstract Payment Gateway
 *
 * @since 1.0.0
 */
abstract class Abstract_Payment_Gateway extends WC_Payment_Gateway {
	/**
	 * Url of assets directory
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $assets_url = CPPW_URL . 'assets/';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway_class' ], 999 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Adds transaction url in order details page
	 *
	 * @param WC_Order $order current order.
	 * @since 1.0.0
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://www.paypal.com/activity/payment/%s';
		if ( 'sandbox' === Helper::get_payment_mode() ) {
			$this->view_transaction_url = 'https://www.sandbox.paypal.com/activity/payment/%s';
		}
		return parent::get_transaction_url( $order );
	}

	/**
	 * Get Order description string
	 *
	 * @param WC_Order $order current order.
	 * @since 1.0.0
	 * @return string
	 */
	public function get_order_description( $order ) {
		return apply_filters( 'cppw_get_order_description', get_bloginfo( 'name' ) . ' - ' . __( 'Order ', 'checkout-paypal-woo' ) . $order->get_id() );
	}

	/**
	 * Registering Gateway to WooCommerce
	 *
	 * @param array $methods List of registered gateways.
	 * @since 1.0.0
	 * @return array
	 */
	public function add_gateway_class( $methods ) {
		array_unshift( $methods, $this );
		return $methods;
	}

	/**
	 * Get billing countries for gateways
	 *
	 * @since 1.0.0
	 * @return string $billing_country
	 */
	public function get_billing_country() {
		global $wp;

		if ( isset( $wp->query_vars['order-pay'] ) ) {
			$order           = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			$billing_country = $order instanceof WC_Order ? $order->get_billing_country() : '';
		} else {
			$customer        = WC()->customer;
			$billing_country = $customer->get_billing_country();

			if ( ! $billing_country ) {
				$billing_country = WC()->countries->get_base_country();
			}
		}

		return $billing_country;
	}

	/**
	 * Checks whether this gateway is available.
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( ! Helper::get_payment_mode() && is_checkout() ) {
			return false;
		}

		if ( 'sandbox' === Helper::get_payment_mode() ) {
			if ( empty( Helper::get_setting( 'cppw_sandbox_client_id' ) ) || empty( Helper::get_setting( 'cppw_sandbox_secret_key' ) ) ) {
				return false;
			}
		} else {
			if ( empty( Helper::get_setting( 'cppw_client_id' ) ) || empty( Helper::get_setting( 'cppw_secret_key' ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Refunds amount from paypal and return true/false as result
	 *
	 * @param int    $order_id order id.
	 * @param float  $amount refund amount.
	 * @param string $reason reason of refund.
	 * @since 1.0.0
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		if ( $amount && 0 >= $amount ) {
			return false;
		}
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$paypal_txn_id = $order->get_meta( CPPW_PAYPAL_TRANSACTION_ID, true );

		if ( empty( $paypal_txn_id ) || ! is_string( $paypal_txn_id ) ) {
			return new WP_Error( 'error', __( 'Transaction id did not found.', 'checkout-paypal-woo' ) );
		}

		$body               = [
			'amount' => [
				'value'         => $amount,
				'currency_code' => $order->get_currency(),
			],
		];
		$response           = Payments::refund( $body, $paypal_txn_id );
		$refund_response_id = ! empty( $response['id'] ) ? $response['id'] : '';

		if ( ! empty( $response['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $response['debug_id'] );
		}

		if ( $refund_response_id ) {
			$is_refunded = $this->is_avail_refund_transient( $order_id, $refund_response_id );

			if ( $is_refunded ) {
				return new WP_Error( 'error', __( 'Refund process successfully', 'checkout-paypal-woo' ) );
			}

			Paypal_Fee::update_net_amount( $order, $amount );

			$refund_time = gmdate( 'Y-m-d H:i:s', time() );
			$order->update_meta_data( '_cppw_refund_id', $refund_response_id );
			$order->add_order_note( __( 'Reason : ', 'checkout-paypal-woo' ) . $reason . '.<br>' . __( 'Amount : ', 'checkout-paypal-woo' ) . get_woocommerce_currency_symbol() . $amount . '.<br>' . __( 'Status : Refunded', 'checkout-paypal-woo' ) . ' [ ' . $refund_time . ' ] <br>' . __( 'Transaction ID : ', 'checkout-paypal-woo' ) . $refund_response_id );

			Logger::info( __( 'Refund initiated: ', 'checkout-paypal-woo' ) . __( 'Reason : ', 'checkout-paypal-woo' ) . $reason . __( 'Amount : ', 'checkout-paypal-woo' ) . get_woocommerce_currency_symbol() . $amount . __( 'Status : Refunded', 'checkout-paypal-woo' ) . ' [ ' . $refund_time . ' ] ' . __( 'Transaction ID : ', 'checkout-paypal-woo' ) . $refund_response_id, true );
			return true;

		} else {
			$fail_reason = ! empty( $response['message'] ) ? $response['message'] : __( 'Unable to process refund request.', 'checkout-paypal-woo' );
			$order->add_order_note( __( 'Reason : ', 'checkout-paypal-woo' ) . $reason . '.<br>' . __( 'Amount : ', 'checkout-paypal-woo' ) . get_woocommerce_currency_symbol() . $amount . '.<br>' . __( ' Status : Failed ', 'checkout-paypal-woo' ) );
			return new WP_Error( 'error', $fail_reason );
		}
	}

	/**
	 * All payment icons that work with Paypal
	 *
	 * @param string $gateway_id gateway id to fetch icon.
	 * @since 1.0.0
	 * @return string
	 */
	public function payment_icons( $gateway_id ) {
		$icons = [
			'cppw_paypal' => '<img src="' . $this->assets_url . 'icon/cppw_paypal.png" width="50px" />',
		];

		return apply_filters(
			'cppw_payment_icons',
			isset( $icons[ $gateway_id ] ) ? $icons[ $gateway_id ] : ''
		);
	}

	/**
	 * Check refund transient.
	 *
	 * @param int    $order_id Woocommerce.
	 * @param string $txn_id Paypal txn_id.
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_avail_refund_transient( $order_id, $txn_id ) {
		$transient_id  = '_cppw_refund_id_' . $txn_id;
		$get_transient = get_transient( $transient_id );
		if ( ! empty( $get_transient ) ) {
			delete_transient( $transient_id );
			return true;
		}
		set_transient( $transient_id, $order_id, 300 );
		return false;
	}

	/**
	 * Handle capture request.
	 * It is used by third party integration like CartFlows.
	 *
	 * @param string $token Paypal order token.
	 * @param array  $body body.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function capture_order_request( $token, $body ) {
		return Payments::capture( $token, $body );
	}

	/**
	 * Handle authorize request.
	 * It is used by third party integration like CartFlows.
	 *
	 * @param string $token Paypal order token.
	 * @param array  $body body.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function authorize_order_request( $token, $body ) {
		return Payments::authorize( $token, $body );
	}
}
