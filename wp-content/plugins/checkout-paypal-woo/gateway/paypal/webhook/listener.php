<?php
/**
 * Paypal Listener Class
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal\Webhook;

use CPPW\Gateway\Abstract_Payment_Gateway;
use CPPW\Inc\Logger;
use CPPW\Gateway\Paypal\Webhook\Webhook;
use CPPW\Gateway\Paypal\Paypal_Fee;
use CPPW\Inc\Traits\Get_Instance;
use WC_Order;
use WP_REST_Request;

/**
 * Webhook endpoints
 *
 * @since 1.0.0
 */
class Listener extends Abstract_Payment_Gateway {
	use Get_Instance;

	/**
	 * Constructor function
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
	}

	/**
	 * Registers endpoint for paypal webhook
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route(
			'cppw',
			'/sandbox/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'webhook_listener_sandbox' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'cppw',
			'/live/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'webhook_listener_live' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * This function listens webhook events from paypal for sandbox mode.
	 *
	 * @param \WP_REST_Request $request Webhook rest api response.
	 * @since 1.0.0
	 * @return void
	 */
	public function webhook_listener_sandbox( \WP_REST_Request $request ) {
		$this->webhook_listener( $request, 'sandbox' );
	}

	/**
	 * This function listens webhook events from paypal for live mode.
	 *
	 * @param \WP_REST_Request $request Webhook rest api response.
	 * @since 1.0.0
	 * @return void
	 */
	public function webhook_listener_live( \WP_REST_Request $request ) {
		$this->webhook_listener( $request, 'live' );
	}

	/**
	 * This function listens webhook events from paypal.
	 *
	 * @param \WP_REST_Request $request Webhook rest api response.
	 * @param string           $mode Paypal mode sandbox or live.
	 * @since 1.0.0
	 * @return void
	 */
	public function webhook_listener( $request, $mode ) {
		$request_get_params = $request->get_params();
		$validate_request   = $this->validate_request( $request, $mode );

		if ( ! empty( $validate_request['verification_status'] ) && 'SUCCESS' === $validate_request['verification_status'] ) {
			$this->handle_all_response( $request_get_params );
			http_response_code( 200 );
		} else {
			Logger::error( __( 'Webhook signature not valid.', 'checkout-paypal-woo' ) );
			http_response_code( 400 );
		}
	}

	/**
	 * Validate webhook requests.
	 *
	 * @param object $request Requests parameters.
	 * @param string $mode Paypal mode sandbox or live.
	 * @since 1.0.0
	 * @return array
	 */
	private function validate_request( $request, $mode ) {
		// Verify server variables.
		$check = $this->verify_http_server();
		if ( ! $check ) {
			// handle server unverified.
			return [];
		}

		$webhook_id = get_option( CPPW_WEBHOOK_ID . $mode );

		if ( empty( $webhook_id ) ) {
			return [];
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return [];
		}

		$payload = \json_decode( $request->get_body(), true );
		$params  = [
			'auth_algo'         => isset( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ) : '',
			'cert_url'          => isset( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_CERT_URL'] ) : '',
			'transmission_id'   => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ) : '',
			'transmission_sig'  => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ) : '',
			'transmission_time' => isset( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) ? sanitize_text_field( $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ) : '',
			'webhook_id'        => $webhook_id,
			'webhook_event'     => $payload,
		];

		$response = Webhook::verify_signature( $params );
		return $response;
	}

	/**
	 * Verify http variables.
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	private function verify_http_server() {
		$http_vars = [
			'HTTP_PAYPAL_TRANSMISSION_SIG',
			'HTTP_PAYPAL_AUTH_ALGO',
			'HTTP_PAYPAL_CERT_URL',
			'HTTP_PAYPAL_TRANSMISSION_ID',
			'HTTP_PAYPAL_TRANSMISSION_TIME',
		];
		$check     = true;
		foreach ( $http_vars as $var ) {
			if ( empty( $_SERVER[ $var ] ) ) {
				$check = false;
				break;
			}
		}
		return $check;
	}

	/**
	 * Handle Paypal webhook response.
	 *
	 * @param array $request_get_params Webhook post response.
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_all_response( $request_get_params ) {
		$get_order_id = intval( $this->get_paypal_txn_id_by_event( $request_get_params ) );

		if ( empty( $get_order_id ) ) {
			return;
		}

		$order = wc_get_order( $get_order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'cppw_paypal' !== $order->get_payment_method() ) {
			return;
		}

		$amount = (float) $request_get_params['resource']['amount']['value'];
		if ( 'PAYMENT.CAPTURE.REFUNDED' === $request_get_params['event_type'] ) {
			$this->handle_refund( $order, $amount, $get_order_id, $request_get_params['resource']['id'] );
		} elseif ( 'PAYMENT.CAPTURE.COMPLETED' === $request_get_params['event_type'] ) {
			if ( ! empty( $request_get_params['resource']['supplementary_data']['related_ids']['authorization_id'] ) ) {
				$this->capture_authorized_order( $order, $amount, $request_get_params );
			} else {
				$this->handle_payment_completed( $order, $request_get_params['resource']['id'] );
			}
		} elseif ( 'PAYMENT.AUTHORIZATION.VOIDED' === $request_get_params['event_type'] ) {
			$this->handle_authorize_voided( $order, $request_get_params );
		}
	}

	/**
	 * Captured authorize txn by Paypal.
	 *
	 * @param object $order Woocommerce order object.
	 * @param float  $amount Order amount.
	 * @param array  $request_get_params Webhook post response.
	 * @since 1.0.0
	 * @return void
	 */
	private function capture_authorized_order( $order, $amount, $request_get_params ) {
		if ( empty( $request_get_params['resource']['id'] ) || ! $order instanceof WC_Order ) {
			return;
		}

		$get_new_txn_id = sanitize_text_field( $request_get_params['resource']['id'] );
		$order->update_meta_data( CPPW_PAYPAL_TRANSACTION_ID, $get_new_txn_id );

		$price_breakdown = ! empty( $request_get_params['resource']['seller_receivable_breakdown'] ) ? $request_get_params['resource']['seller_receivable_breakdown'] : false;
		Paypal_Fee::add_fee_net_amount( $order, $price_breakdown );
		// Set amount to the order.
		$order_amount     = floatval( $order->get_total() );
		$authorize_amount = floatval( $amount );
		$order->payment_complete( $get_new_txn_id );

		if ( $order_amount !== $authorize_amount && $order_amount > $authorize_amount ) {
			$amount_difference = $order_amount - $authorize_amount;
			$refund_message    = sprintf(
				/* translators: %1$s captured amount, %2$s refunded %2$s $amount, %3$s currency */
				__( 'Amount captured %3$s %1$s from Paypal dashboard Amount refunded %3$s %2$s.', 'checkout-paypal-woo' ),
				$authorize_amount,
				$amount_difference,
				get_woocommerce_currency_symbol()
			);
			wc_create_refund(
				[
					'order_id' => $order->get_id(),
					'amount'   => $amount_difference,
					'reason'   => $refund_message,
				]
			);
			$refund_time = gmdate( 'Y-m-d H:i:s', time() );
			$order->add_order_note( __( 'Reason : ', 'checkout-paypal-woo' ) . $refund_message . '.<br>' . "[ {$refund_time} ]" );
		}
	}

	/**
	 * Handle id order will be cancelled from authorize.
	 *
	 * @param object $order Woocommerce order object.
	 * @param array  $request_get_params Webhook post response.
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_authorize_voided( $order, $request_get_params ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$cancelled_message = sprintf(
			/* translators: %1$s Transaction id. */
			__( 'Payment has been cancelled from Paypal dashboard. <br> Transaction Id : %1$s', 'checkout-paypal-woo' ),
			$request_get_params['resource']['id'],
		);
		$cancelled_time = gmdate( 'Y-m-d H:i:s', time() );
		$order->update_status( 'wc-cancelled' );
		$order->add_order_note( __( 'Reason : ', 'checkout-paypal-woo' ) . $cancelled_message . '.<br>' . "[ {$cancelled_time} ]" );
	}

	/**
	 * Handle payment complete process.
	 *
	 * @param object $order Woocommerce order object.
	 * @param string $txn_id Paypal txn id.
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_payment_completed( $order, $txn_id ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( $order->has_status( 'completed' ) ) {
			return;
		}
		$order->payment_complete( $txn_id );
	}

	/**
	 * Handle refund process.
	 *
	 * @param object $order Woocommerce order object.
	 * @param float  $amount Amount.
	 * @param int    $get_order_id  Order id.
	 * @param string $refund_id Paypal refund id.
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_refund( $order, $amount, $get_order_id, $refund_id ) {
		$is_refunded = $this->is_avail_refund_transient( $get_order_id, $refund_id );
		if ( $is_refunded || ! $order instanceof WC_Order ) {
			return;
		}
		$reason = __( 'Refunded via Paypal dashboard', 'checkout-paypal-woo' );
		// Create the refund.
		$refund = wc_create_refund(
			[
				'order_id' => $get_order_id,
				'amount'   => ( $amount > 0 ) ? $amount : false,
				'reason'   => $reason,
			]
		);
		if ( is_wp_error( $refund ) ) {
			Logger::error( $refund->get_error_message() );
		}
		Paypal_Fee::update_net_amount( $order, $amount );
		$order->update_meta_data( '_cppw_refund_id', $refund_id );
		$status      = __( 'Success', 'checkout-paypal-woo' );
		$refund_time = gmdate( 'Y-m-d H:i:s', time() );
		$order->add_order_note( __( 'Reason : ', 'checkout-paypal-woo' ) . $reason . '.<br>' . __( 'Amount : ', 'checkout-paypal-woo' ) . $amount . '.<br>' . __( 'Status : ', 'checkout-paypal-woo' ) . $status . ' [ ' . $refund_time . ' ] <br>' . __( 'Transaction ID : ', 'checkout-paypal-woo' ) . $refund_id );
		Logger::info( $reason . ' : ' . __( 'Amount : ', 'checkout-paypal-woo' ) . get_woocommerce_currency_symbol() . $amount . __( ' Transaction ID : ', 'checkout-paypal-woo' ) . $refund_id, true );
	}

	/**
	 * Ger WooCommerce order id from paypal response.
	 *
	 * @param array $request_get_params Webhook post response.
	 * @since 1.0.0
	 * @return string
	 */
	private function get_paypal_txn_id_by_event( $request_get_params ) {
		$txn_id = '';
		if ( 'PAYMENT.CAPTURE.REFUNDED' === $request_get_params['event_type'] && ! empty( $request_get_params['resource']['links'] ) ) {
			$txn_id = $this->get_paypal_txn_id( $request_get_params['resource']['links'] );
		} elseif ( 'PAYMENT.CAPTURE.COMPLETED' === $request_get_params['event_type'] ) {
			if ( ! empty( $request_get_params['resource']['supplementary_data']['related_ids']['authorization_id'] ) ) {
				$txn_id = $request_get_params['resource']['supplementary_data']['related_ids']['authorization_id'];
			} else {
				$txn_id = $request_get_params['resource']['id'];
			}
		} elseif ( 'PAYMENT.AUTHORIZATION.VOIDED' === $request_get_params['event_type'] ) {
			$txn_id = $request_get_params['resource']['id'];
		}

		if ( empty( $txn_id ) ) {
			Logger::error( __( 'Could not find transaction ID.', 'checkout-paypal-woo' ) );
			return '';
		}

		$get_order_id = $this->get_order_id_from_intent( $txn_id );
		if ( empty( $get_order_id ) ) {
			Logger::error( __( 'Could not find order via transaction ID : ', 'checkout-paypal-woo' ) . $txn_id );
			return '';
		}
		return $get_order_id;
	}

	/**
	 * Get paypal refund txn id.
	 *
	 * @param array $links This variable contain txn id.
	 * @since 1.0.0
	 * @return string
	 */
	private function get_paypal_txn_id( $links ) {
		if ( ! is_array( $links ) ) {
			return '';
		}

		$get_string = '';
		$links      = array_reverse( $links );
		foreach ( $links as $value ) {
			if ( empty( $value['href'] ) ) {
				continue;
			}
			if ( stripos( $value['href'], 'payments/captures/' ) ) {
				$get_string = $value['href'];
				break;
			}
		}

		if ( empty( $get_string ) ) {
			return '';
		}

		$explode_string = explode( '/payments/captures/', $get_string );
		if ( empty( $explode_string[1] ) ) {
			return '';
		}

		$find_slash = stripos( $explode_string[1], '/' );
		if ( ! $find_slash ) {
			return $explode_string[1];
		}
		return substr( $explode_string[1], 0, $find_slash );
	}

	/**
	 * Fetch WooCommerce order id from txn id
	 *
	 * @param string $transaction_id txn id received from paypal.
	 * @since 1.0.0
	 * @return string order id.
	 */
	public function get_order_id_from_intent( $transaction_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from {$wpdb->prefix}postmeta where meta_key = '_cppw_transaction_id' and meta_value like %s", '%' . $transaction_id . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return ! empty( $result ) ? $result : '';
	}
}

