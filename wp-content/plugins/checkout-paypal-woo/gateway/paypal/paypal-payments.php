<?php
/**
 * PayPal Gateway
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal;

use CPPW\Inc\Helper;
use CPPW\Inc\Logger;
use CPPW\Inc\Traits\Get_Instance;
use CPPW\Gateway\Abstract_Payment_Gateway;
use CPPW\Gateway\Paypal\Paypal_Fee;
use CPPW\Gateway\Paypal\Api\Payments;
use WP_Error;
use CPPW\Gateway\Paypal\Subscription\Subscriptions;
use WC_Order;

/**
 * Paypal_Payments
 *
 * @since 1.0.0
 */
class Paypal_Payments extends Abstract_Payment_Gateway {

	use Get_Instance;

	use Subscriptions;

	/**
	 * Gateway id
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $id = 'cppw_paypal';

	/**
	 * Payment type.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $payment_type = '';

	/**
	 * Paypal type capture or authorize.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $paypal_type = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();
		$this->method_title       = __( 'PayPal Card Processing', 'checkout-paypal-woo' );
		$this->method_description = __( 'Accepts payments via Credit/Debit Cards', 'checkout-paypal-woo' );
		$this->has_fields         = true;
		$this->init_supports();

		$this->init_form_fields();
		$this->init_settings();
		// get_option should be called after init_form_fields().
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->order_button_text = $this->get_option( 'order_button_text' );
		$this->payment_type      = $this->get_option( 'payment_type' );
		$this->paypal_type       = $this->get_option( 'paypal_type' );
		add_action( 'init', [ $this, 'verify_intent' ], 999 );
		$this->maybe_init_subscriptions();
	}

	/**
	 * Registers supported filters for payment gateway
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_supports() {
		$this->supports = apply_filters(
			'cppw_card_payment_supports',
			[
				'products',
				'refunds',
				'tokenization',
				'add_payment_method',
				'pre-orders',
			]
		);
	}

	/**
	 * Gateway form fields
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'cppw_card_payment_form_fields',
			[
				'enabled'           => [
					' label'  => ' ',
					'type'    => 'checkbox',
					'title'   => __( 'Enable PayPal Gateway', 'checkout-paypal-woo' ),
					'default' => 'no',
				],
				'title'             => [
					'title'       => __( 'Title', 'checkout-paypal-woo' ),
					'type'        => 'text',
					'description' => __( 'Title of Card Element', 'checkout-paypal-woo' ),
					'default'     => __( 'PayPal', 'checkout-paypal-woo' ),
					'desc_tip'    => true,
				],
				'description'       => [
					'title'       => __( 'Description', 'checkout-paypal-woo' ),
					'type'        => 'textarea',
					'css'         => 'width:25em',
					'description' => __( 'Description on Card Elements for Live mode', 'checkout-paypal-woo' ),
					'default'     => __( 'Pay with your credit card via PayPal', 'checkout-paypal-woo' ),
					'desc_tip'    => true,
				],
				'paypal_type'       => [
					'title'       => __( 'Payment Type', 'checkout-paypal-woo' ),
					'type'        => 'select',
					'default'     => 'capture',
					'options'     => [
						'capture'   => __( 'Capture', 'checkout-paypal-woo' ),
						'authorize' => __( 'Authorized', 'checkout-paypal-woo' ),
					],
					'id'          => 'paypal_type',
					'description' => __( 'Capture or authorized payment in capture intent payment will occur instantly and in authorized payment will be by paypal account captured.', 'checkout-paypal-woo' ),
					'desc_tip'    => true,
				],
				'payment_type'      => [
					'title'       => __( 'Payment Method', 'checkout-paypal-woo' ),
					'type'        => 'select',
					'default'     => 'standard',
					'options'     => [
						'standard' => __( 'Standard Payment', 'checkout-paypal-woo' ),
						'smart'    => __( 'Smart Payment', 'checkout-paypal-woo' ),
					],
					'description' => $this->is_subscriptions_enabled() ? __( 'This method will not work when checkout contain subscription product.', 'checkout-paypal-woo' ) : '',
					'desc_tip'    => false,
				],
				'order_button_text' => [
					'title'       => __( 'Order Button Label', 'checkout-paypal-woo' ),
					'type'        => 'text',
					'description' => __( 'Customize label for Order button', 'checkout-paypal-woo' ),
					'default'     => __( 'Pay via PayPal', 'checkout-paypal-woo' ),
					'desc_tip'    => true,
				],
			]
		);
	}

	/**
	 * Process woocommerce orders after payment is done
	 *
	 * @param int $order_id wooCommerce order id.
	 * @since 1.0.0
	 * @return array|WP_Error data to redirect after payment processing.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
		// process for subscription.
		if ( $this->is_subscriptions_enabled() && $this->has_subscription( $order_id ) || apply_filters( 'cppw_should_save_billing_agreement', false ) ) {
			if ( ! empty( $_POST['cppw_paypal_billing_token'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing -- We can ignore nonce here because this is Woocommerce action and in parent function nonce is already checked.
				return $this->process_change_subscription_payment_method( $order, sanitize_text_field( $_POST['cppw_paypal_billing_token'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing -- We can ignore nonce here because this is Woocommerce action and in parent function nonce is already checked.
			} else {
				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}
		}

		if ( ! empty( $_POST['cppw_paypal_txn_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing -- We can ignore nonce here because this is Woocommerce action and in parent function nonce is already checked.
			return $this->handle_smart_payment( $order );
		}

		$paypal_order = $this->create_paypal_order_of_wc( $order );
		/* translators: order id and get total msg */
		Logger::info( sprintf( __( 'Begin processing payment with new payment method for order %1$1s for the amount of %2$2s', 'checkout-paypal-woo' ), $order_id, $order->get_total() ) );

		if ( ! empty( $paypal_order['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $paypal_order['debug_id'] );
		}

		$return_false = [
			'result'   => 'fail',
			'redirect' => '',
		];

		if ( empty( $paypal_order['links'] ) || empty( $paypal_order['id'] ) ) {
			return $return_false;
		} else {
			$get_checkout_approval_url = array_column( $paypal_order['links'], 'href', 'rel' );
			if ( empty( $get_checkout_approval_url['approve'] ) ) {
				return $return_false;
			}
		}

		return apply_filters(
			'cppw_card_payment_return_intent_data',
			[
				'result'         => 'success',
				'redirect'       => $get_checkout_approval_url['approve'],
				'paypalorder_id' => $paypal_order['id'],
			]
		);
	}

	/**
	 * Verify intent state and redirect.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function verify_intent() {
		$token = ! empty( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : false; //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- By $_GET['token'] we will verify in $result so we can ignore nonce verification here.

		if ( ! $token ) {
			return;
		}

		if ( ! isset( $_GET['PayerID'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_msg = __( 'There was a problem make the payment. Try another way.', 'checkout-paypal-woo' );
			wc_add_notice( $error_msg, 'error' );
			/* translators: error msg */
			Logger::error( sprintf( __( 'Make Payment Error: %1$1s', 'checkout-paypal-woo' ), $error_msg ) );
			return;
		}

		$result = 'authorize' === $this->paypal_type ? $this->authorize_order( $token ) : $this->capture_order( $token );

		if ( ! empty( $result['status'] ) ) {
			$url = $this->get_return_url( $result['order'] );
		} else {
			if ( ! empty( $result['order'] ) ) {
				$result['order']->update_status( 'wc-failed' );
			}
			$fail_message = ! empty( $result['message'] ) ? $result['message'] : __( 'Payment failed.', 'checkout-paypal-woo' );
			wc_add_notice( $fail_message, 'error' );
			$url = wc_get_checkout_url();
		}

		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Get paypal activated payment cards icon.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', '<span class="cppw_paypal_icons">' . $this->payment_icons( $this->id ) . '</span>', $this->id );
	}

	/**
	 * Creates markup for payment form for card payments
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function payment_fields() {
		/**
		 * Action before payment field.
		 *
		 * @since 1.0.0
		 */
		do_action( $this->id . '_before_payment_field_checkout' );

		echo '<div class="status-box"></div>';
		echo '<div class="cppw-paypal-pay-data">';
		echo '<div class="cppw-paypal-info">';
		if ( 'standard' === $this->payment_type ) {
			echo wp_kses_post( wpautop( $this->description ) );
		} else {
			esc_html_e( 'Click the PayPal button below to process your order.', 'checkout-paypal-woo' );
		}
		echo '</div>';
		if ( 'sandbox' === Helper::get_payment_mode() ) {
			echo '<div class="cppw-test-description">';
			/* translators: %1$1s - %6$6s: HTML Markup */
			printf( esc_html__( '%1$1s Test Mode Enabled:%2$2s Use demo card for payment.', 'checkout-paypal-woo' ), '<b>', '</b>' );
			echo '</div>';
		}
		echo '</div>';

		/**
		 * Action after payment field.
		 *
		 * @since 1.0.0
		 */
		do_action( $this->id . '_after_payment_field_checkout' );
	}

	/**
	 * Handle smart payment method.
	 *
	 * @param object $order Order object.
	 * @since 1.0.0
	 * @return array|WP_Error
	 */
	public function handle_smart_payment( $order ) {
		$paypal_txn_id = isset( $_POST['cppw_paypal_txn_id'] ) ? sanitize_text_field( $_POST['cppw_paypal_txn_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $paypal_txn_id ) ) {
			return new WP_Error( 'order-error', '<div class="woocommerce-error">' . __( 'There was a problem make the payment. Try another way.', 'checkout-paypal-woo' ) . '</div>', [ 'status' => 200 ] );
		}

		$result = 'authorize' === $this->paypal_type ? $this->authorize_order( $paypal_txn_id, $order ) : $this->capture_order( $paypal_txn_id, $order );

		if ( ! empty( $result['status'] ) ) {
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $result['order'] ),
			];
		} else {
			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Create Paypal order of Woocommerce order.
	 *
	 * @param object $order Woocommerce order.
	 * @param int    $order_total Woocommerce order total.
	 * @since 1.0.0
	 * @return array
	 */
	public function create_paypal_order_of_wc( $order, $order_total = 0 ) {
		if ( ! $order instanceof WC_Order ) {
			return [];
		}

		$body = [
			'intent'              => 'authorize' === $this->paypal_type ? 'AUTHORIZE' : 'CAPTURE',
			'purchase_units'      => [
				[
					'reference_id' => $order->get_id(),
					'amount'       => [
						'value'         => ! empty( $order_total ) ? $order_total : $order->get_total(),
						'currency_code' => get_woocommerce_currency(),
					],
				],
			],
			'application_context' => [
				'cancel_url' => wc_get_checkout_url(),
				'return_url' => wc_get_checkout_url(),
			],
		];

		return Payments::create( $body );
	}

	/**
	 * Checks whether this gateway is available.
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public function is_available() {
		// Check if standard payment enabled and cart have subscriptions product then disable payment.
		if ( $this->is_subscription_item_in_cart() && 'standard' === Helper::get_setting( 'payment_type', 'cppw_paypal' ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Authorize Payment from Paypal.
	 *
	 * @param string $token Paypal order token.
	 * @param object $order Wc order.
	 * @param array  $body Paypal authorize Api body.
	 * @since 1.0.0
	 * @return array
	 */
	public function authorize_order( $token, $order = null, $body = [] ) {
		$response        = $this->authorize_order_request( $token, $body );
		$authorize_order = ! empty( $response['status'] ) ? $response : false;

		if ( ! $authorize_order ) {
			return [ 'status' => false ];
		}

		if ( empty( $order ) ) {
			$order_id = $authorize_order['purchase_units'][0]['reference_id'];
			$order    = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return [ 'status' => false ];
		}

		$transaction_id = $this->get_transaction_id( $authorize_order );

		if ( ! empty( $response['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $response['debug_id'] );
		}

		if ( $transaction_id && 'COMPLETED' === $authorize_order['status'] ) {
			$order->update_meta_data( CPPW_PAYPAL_TRANSACTION_ID, $transaction_id );
			$order->update_status( 'wc-on-hold' );
			return [
				'status' => true,
				'order'  => $order,
			];
		}
		return [
			'status'  => false,
			'order'   => $order,
			'message' => ! empty( $response['message'] ) ? $response['message'] : __( 'Payment failed.', 'checkout-paypal-woo' ),
		];
	}

	/**
	 * Capture Payment from Paypal.
	 *
	 * @param string $token Paypal order token.
	 * @param object $order Wc order.
	 * @param array  $body Paypal authorize Api body.
	 * @since 1.0.0
	 * @return array
	 */
	public function capture_order( $token, $order = null, $body = [] ) {
		$response      = $this->capture_order_request( $token, $body );
		$order_capture = ! empty( $response['status'] ) ? $response : false;

		if ( ! $order_capture ) {
			return [ 'status' => false ];
		}

		if ( empty( $order ) ) {
			$order_id = $order_capture['purchase_units'][0]['reference_id'];
			$order    = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return [ 'status' => false ];
		}

		if ( ! empty( $response['debug_id'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_DEBUG_ID, $response['debug_id'] );
		}

		$transaction_id = $this->get_transaction_id( $order_capture );

		if ( $transaction_id && 'COMPLETED' === $order_capture['status'] ) {
			$order->update_meta_data( CPPW_PAYPAL_TRANSACTION_ID, $transaction_id );
			$price_breakdown = ! empty( $order_capture['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'] ) ? $order_capture['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'] : false;
			Paypal_Fee::add_fee_net_amount( $order, $price_breakdown );
			$order->payment_complete( $transaction_id );
			return [
				'status' => true,
				'order'  => $order,
			];
		}
		return [
			'status'  => false,
			'order'   => $order,
			'message' => ! empty( $response['message'] ) ? $response['message'] : __( 'Payment failed.', 'checkout-paypal-woo' ),
		];
	}

	/**
	 * Get transaction id from response.
	 *
	 * @param array $order_capture capture data.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_transaction_id( $order_capture ) {
		$type = 'authorize' === $this->paypal_type ? 'authorizations' : 'captures';
		return ! empty( $order_capture['purchase_units'][0]['payments'][ $type ][0]['id'] ) ? $order_capture['purchase_units'][0]['payments'][ $type ][0]['id'] : '';
	}
}
