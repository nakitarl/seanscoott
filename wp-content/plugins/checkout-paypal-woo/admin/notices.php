<?php
/**
 * Order meta
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Admin;

use CPPW\Inc\Helper;

/**
 * Notices.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */
class Notices {

	/**
	 * Paypal keys.
	 *
	 * @var array Paypal setting keys.
	 * @since 1.0.0
	 */
	public $settings = [];

	/**
	 * Setup variables.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $show_errors = [];

	/**
	 * Constructor function.
	 *
	 * @param array $settings Paypal settings.
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct( $settings = [] ) {
		$this->settings = $settings;
		add_action( 'admin_init', [ $this, 'initialize_warnings' ] );
	}

	/**
	 * WooCommerce Init
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_warnings() {
		// If no SSL bail.
		if ( 'live' === Helper::get_payment_mode() && ! is_ssl() ) {
			add_action( 'admin_notices', [ $this, 'ssl_not_connect' ] );
		}

		// IF paypal connection established successfully .
		if ( isset( $_GET['cppw_call'] ) && ! empty( $_GET['cppw_call'] ) && 'success' === $_GET['cppw_call'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific value by $_GET['cppw_call'] so we can ignore nonce here.
			add_action( 'admin_notices', [ $this, 'connect_success_notice' ] );
		}

		// IF paypal connection not established successfully.
		if ( isset( $_GET['cppw_call'] ) && ! empty( $_GET['cppw_call'] ) && 'failed' === $_GET['cppw_call'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific value by $_GET['cppw_call'] so we can ignore nonce here.
			add_action( 'admin_notices', [ $this, 'connect_failed_notice' ] );
		}

		// Redirect if Paypal connect and account detail not appeared.
		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'cppw_api_settings' === $_GET['tab'] && isset( $_GET['redirectUrl'] ) && isset( $_GET['merchantId'] ) && isset( $_GET['merchantIdInPayPal'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are checking specific value's from $_GET so we can ignore nonce here.
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=cppw_api_settings' ) );
			exit();
		}

		if ( isset( $_GET['tab'] ) && 'cppw_api_settings' === $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific value by $_GET['tab'] so we can ignore nonce here.
			// Check payment receivable or not.
			if ( get_option( ' cppw_payments_receivable_false' ) ) {
				add_action( 'admin_notices', [ $this, 'payments_receivable_false' ] );
			}
			// Check primary email confirmed or not.
			if ( get_option( 'cppw_primary_email_confirmed_false' ) ) {
				add_action( 'admin_notices', [ $this, 'primary_email_not_confirmed' ] );
			}

			$onboarding_errors = get_transient( 'cppw_paypal_onboarding_error' );
			if ( ! empty( $onboarding_errors ) ) {
				$this->show_errors['onboarding_error_msg'] = $onboarding_errors;
				add_action( 'admin_notices', [ $this, 'onboarding_error_msg' ] );
				delete_transient( 'cppw_paypal_onboarding_error' );
			}
		}
	}

	/**
	 * Checks for response after paypal onboarding process
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	public function are_keys_set() {
		if ( ( 'live' === $this->settings['cppw_mode']
				&& empty( $this->settings['cppw_client_id'] )
				&& empty( $this->settings['cppw_secret_key'] ) )
			|| ( 'sandbox' === $this->settings['cppw_mode']
				&& empty( $this->settings['cppw_sandbox_client_id'] )
				&& empty( $this->settings['cppw_sandbox_secret_key'] )
			)
			|| ( empty( $this->settings['cppw_mode'] )
				&& empty( $this->settings['cppw_secret_key'] )
				&& empty( $this->settings['cppw_sandbox_secret_key'] )
			)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Check for SSL and show warning.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ssl_not_connect() {
		echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'No SSL was detected, Paypal live mode requires SSL.', 'checkout-paypal-woo' ) . '</p></div>' );
	}

	/**
	 * Connection success notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function connect_success_notice() {
		echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>' . __( 'Your Paypal account has been connected to your WooCommerce store. You may now accept payments in live and test mode.', 'checkout-paypal-woo' ) . '</p></div>' );
	}

	/**
	 * Connection failed notice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function connect_failed_notice() {
		echo wp_kses_post( '<div class="notice notice-error is-dismissible"><p>' . __( 'We were not able to connect your Paypal account. Please try again. ', 'checkout-paypal-woo' ) . '</p></div>' );
	}

	/**
	 * If primary email not confirmed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function primary_email_not_confirmed() {
		echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Your Account has been limited by PayPal. Please verify your email address.', 'checkout-paypal-woo' ) . '</p></div>' );
	}

	/**
	 * If payment receivable false.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function payments_receivable_false() {
		echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Your Account has been limited by PayPal. Please check your PayPal account inbox for an email from PayPal to determine the next steps for this.', 'checkout-paypal-woo' ) . '</p></div>' );
	}

	/**
	 * Show onboarding error message.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function onboarding_error_msg() {
		if ( ! empty( $this->show_errors['onboarding_error_msg'] ) ) {
			echo wp_kses_post( '<div class="notice notice-error"><p>' . $this->show_errors['onboarding_error_msg'] . '</p></div>' );
		}
	}
}
