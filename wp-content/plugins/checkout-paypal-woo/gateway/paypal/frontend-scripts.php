<?php
/**
 * PayPal Frontend Scripts
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal;

use CPPW\Inc\Traits\Get_Instance;
use CPPW\Inc\Helper;
use CPPW\Gateway\Paypal\Subscription\Subscription_Helper as SH;

/**
 * Consists frontend scripts for payment gateways
 *
 * @since 1.0.0
 */
class Frontend_Scripts {

	use Get_Instance;
	use SH;

	/**
	 * Prefix
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $prefix = 'cppw-';

	/**
	 * Version
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $version = '';

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
		$this->version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? strval( time() ) : CPPW_VERSION;
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_paypal_buttons' ] );
		add_action( 'wp_ajax_cppw_js_errors', [ $this, 'front_js_errors' ] );
		add_action( 'wp_ajax_nopriv_cppw_js_errors', [ $this, 'front_js_errors' ] );
	}

	/**
	 * Enqueue scripts
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_script_enqueue() ) {
			return;
		};
		$client_id = Helper::get_client_id();
		$this->enqueue_paypal_script( $client_id );

		if ( 'yes' === Helper::get_setting( 'enabled', 'cppw_paypal' ) ) {
			$this->enqueue_payment_scripts( $client_id );
		}
	}

	/**
	 * Render paypal buttons
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_paypal_buttons() {
		if ( 'smart' === Helper::get_setting( 'payment_type', 'cppw_paypal' ) ) {
			echo '<div id="cppw-paypal-button-container"></div>';
		}
	}

	/**
	 * Enqueue card payments scripts
	 *
	 * @param string $client_id PayPal public key.
	 * @since 1.0.0
	 * @return void
	 */
	private function enqueue_payment_scripts( $client_id ) {
		wp_register_script( $this->prefix . 'front-script', $this->assets_url . 'js/front-script.js', [ 'jquery', $this->prefix . 'paypal-external' ], $this->version, true );
		wp_enqueue_script( $this->prefix . 'front-script' );

		wp_register_style( $this->prefix . 'front-script', $this->assets_url . 'css/front-script.css', [], $this->version );
		wp_enqueue_style( $this->prefix . 'front-script' );

		wp_localize_script(
			$this->prefix . 'front-script',
			'cppw_global_settings',
			[
				'client_id'               => $client_id ? true : '',
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'checkout_endpoint'       => \rest_url( 'cppw/v1/checkout' ),
				'js_error_nonce'          => wp_create_nonce( 'cppw_js_error_nonce' ),
				'cart_total'              => isset( WC()->cart->total ) ? WC()->cart->total : '',
				'checkout_cart_nonce'     => wp_create_nonce( 'checkout_cart' ),
				'check_has_subscription'  => $this->check_order_has_subscription_or_change_payment_method(),
				'is_enable_billing_token' => $this->should_enable_billing_token_on_checkout(),
			]
		);
	}

	/**
	 * Log js.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function front_js_errors() {
		Helper::js_errors();
	}

	/**
	 * Check order has subscription or changing subscription order changing payment method.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function check_order_has_subscription_or_change_payment_method() {
		$subscription_type = '';
		if ( $this->is_changing_payment_method_for_subscription() ) {
			$subscription_type = 'change_subscription';
		} elseif ( $this->is_subscription_item_in_cart() ) {
			$subscription_type = 'subscription';
		}
		return $subscription_type;
	}

	/**
	 * Should front end script enqueue called or not.
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public function should_script_enqueue() {
		return apply_filters( 'cppw_frontend_scripts', $this->is_changing_payment_method_for_subscription() || is_checkout() );
	}

	/**
	 * Enqueue Paypal script.
	 *
	 * @param string $client_id Paypal client id.
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_paypal_script( $client_id ) {
		$query_args = [
			'client-id'       => $client_id,
			'components'      => 'buttons',
			'disable-funding' => 'credit,card',
		];

		$has_subscription = $this->should_enable_billing_token_on_checkout();
		if ( $has_subscription ) {
			$query_args['vault'] = 'true';
		}

		$get_paypal_type      = Helper::get_setting( 'paypal_type', 'cppw_paypal' );
		$query_args['intent'] = 'authorize' === $get_paypal_type && ! $has_subscription ? 'authorize' : 'capture';

		$src = add_query_arg( $query_args, 'https://www.paypal.com/sdk/js' );
		wp_register_script($this->prefix . 'paypal-external', $src, [], null, true); //phpcs:ignore
		wp_enqueue_script( $this->prefix . 'paypal-external' );
	}

	/**
	 * Check order has subscription or changing subscription order changing payment method.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function should_enable_billing_token_on_checkout() {
		return apply_filters( 'cppw_should_enable_billing_token_on_checkout', $this->is_changing_payment_method_for_subscription() || $this->is_subscription_item_in_cart() );
	}
}
