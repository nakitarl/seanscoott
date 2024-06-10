<?php
/**
 * Admin ajax calls.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Admin;

use CPPW\Inc\Traits\Get_Instance;
use CPPW\Gateway\Paypal\Webhook\Webhook;
use CPPW\Inc\Helper;
use CPPW\Admin\Admin_Helper;
use CPPW\Gateway\Paypal\Api\Client;
use CPPW\Inc\Logger;

/**
 * Ajax calls this class is for handle ajax calls.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */
class Ajax_Calls {

	use Get_Instance;

	/**
	 * Paypal mode.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $mode = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_cppw_test_paypal_connection', [ $this, 'connection_test' ] );
		add_action( 'wp_ajax_cppw_disconnect_account', [ $this, 'disconnect_account' ] );
		add_action( 'wp_ajax_cppw_paypal_account_connect', [ $this, 'paypal_account_connect' ] );
		add_action( 'wp_ajax_cppw_js_errors', [ $this, 'admin_js_errors' ] );
		add_action( 'wp_ajax_nopriv_cppw_js_errors', [ $this, 'admin_js_errors' ] );
	}

	/**
	 * Perform a connection test
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function connection_test() {
		if ( ! isset( $_GET['_security'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['_security'] ), 'cppw_admin_nonce' ) || ! isset( $_GET['mode'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: Sorry, the nonce security check didn’t pass. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: The current user doesn’t have sufficient permissions to perform this action. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		$this->mode = sanitize_text_field( $_GET['mode'] );
		$result     = $this->verify_connection();
		if ( ! $result ) {
			$error_message = 'live' === $this->mode ? __( 'Live mode is not connected ❌.', 'checkout-paypal-woo' ) : __( 'Sandbox mode is not connected ❌.', 'checkout-paypal-woo' );
			wp_send_json_error( [ 'message' => $error_message ] );
		}

		$message = 'live' === $this->mode ? __( 'Live mode is connected ✔.', 'checkout-paypal-woo' ) : __( 'Sandbox mode is connected ✔.', 'checkout-paypal-woo' );
		wp_send_json_success( [ 'message' => $message ] );
	}

	/**
	 * Checks for response after paypal onboarding process
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function disconnect_account() {
		if ( ! isset( $_GET['_security'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['_security'] ), 'cppw_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: Sorry, the nonce security check didn’t pass. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: The current user doesn’t have sufficient permissions to perform this action. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		$mode        = ! empty( $_GET['mode'] ) && 'live' === $_GET['mode'] ? 'live' : 'sandbox';
		$remove_keys = Admin_Helper::get_setting_keys( $mode );
		// Remove previous webhook id.
		Webhook::remove_exist_webhook_id( CPPW_WEBHOOK_ID . $mode );

		foreach ( $remove_keys as $key ) {
			update_option( $key, '' );
		}
		wp_send_json_success( [ 'message' => __( 'Paypal keys are reset successfully.', 'checkout-paypal-woo' ) ] );
	}

	/**
	 * Paypal onboarding process account connect
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function paypal_account_connect() {
		if ( ! isset( $_GET['_security'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['_security'] ), 'cppw_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: Sorry, the nonce security check didn’t pass. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: The current user doesn’t have sufficient permissions to perform this action. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}

		if ( ! isset( $_GET['authCode'] ) || ! isset( $_GET['sharedId'] ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Error: The current user doesn’t have sufficient permissions to perform this action. Please reload the page and try again.', 'checkout-paypal-woo' ) ] );
		}
		$auth_code  = sanitize_text_field( $_GET['authCode'] );
		$shared_id  = sanitize_text_field( $_GET['sharedId'] );
		$this->mode = ! empty( $_GET['mode'] ) && 'live' === $_GET['mode'] ? 'live' : 'sandbox';

		// This filter has modified for get Helper::paypal_host_url according to PayPal mode.
		add_filter( 'cppw_payment_mode', [ $this, 'modify_paypal_mode' ] );
		$get_access_token = $this->get_onboarding_access_token( $auth_code, $shared_id, $this->mode );

		if ( ! $get_access_token ) {
			wp_send_json_error( [ 'message' => __( 'Error: User not valid.', 'checkout-paypal-woo' ) ] );
		}

		$connection_status = $this->update_paypal_credential( $this->mode, $get_access_token );
		if ( $connection_status['status'] ) {
			do_action( 'cppw_after_paypal_connect_success', $this->mode );
			wp_send_json_success( [ 'message' => __( 'Paypal successfully connected.', 'checkout-paypal-woo' ) ] );
		} else {
			do_action( 'cppw_after_paypal_connect_fail', $this->mode );
			wp_send_json_error( [ 'message' => ! empty( $connection_status['message'] ) ? $connection_status['message'] : __( 'Paypal not connected.', 'checkout-paypal-woo' ) ] );
		}
	}

	/**
	 * Get access token for onboarding.
	 *
	 * @param string $auth_code Paypal auth code.
	 * @param string $shared_id Paypal sha red id.
	 * @param string $mode Paypal mode.
	 * @since 1.0.0
	 * @return string
	 */
	public function get_onboarding_access_token( $auth_code, $shared_id, $mode ) {
		$option_name  = 'cppw_paypal_connect_seller_nonce_' . $mode;
		$seller_nonce = get_option( $option_name );
		delete_option( $option_name );
		$args             = [
			'headers' => [
				'Authorization'                 => 'Basic ' . base64_encode( $shared_id ), //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'PayPal-Partner-Attribution-Id' => Helper::bn_code(),
			],
			'body'    => [
				'grant_type'    => 'authorization_code',
				'code'          => $auth_code,
				'code_verifier' => $seller_nonce,
			],
		];
		$create_request   = wp_remote_post( Helper::paypal_host_url() . 'v1/oauth2/token', $args );
		$request_response = wp_remote_retrieve_body( $create_request );
		$decoded_response = json_decode( $request_response );

		if ( ! is_object( $decoded_response ) ) {
			return '';
		}

		if ( ! empty( $decoded_response->access_token ) ) {
			return $decoded_response->access_token;
		} elseif ( ! empty( $decoded_response->error ) && ! empty( $decoded_response->error_description ) ) {
			Logger::error( __( 'Paypal onboarding error :', 'checkout-paypal-woo' ) . ' ' . $decoded_response->error . ' : ' . $decoded_response->error_description, true );
			set_transient( 'cppw_paypal_onboarding_error', $decoded_response->error . ' : ' . $decoded_response->error_description, 300 );
		}
		return '';
	}

	/**
	 * Add or update Paypal credential.
	 *
	 * @param string $mode Paypal mode.
	 * @param string $access_token Paypal access token.
	 * @since 1.0.0
	 * @return array
	 */
	public function update_paypal_credential( $mode, $access_token ) {
		$connect_status = [ 'status' => false ];
		$args           = [
			'headers' => [
				'Authorization'                 => 'Bearer ' . $access_token,
				'Content-Type'                  => 'application/json',
				'PayPal-Partner-Attribution-Id' => Helper::bn_code(),
			],
		];

		$partner_id = get_option( CPPW_PARTNER_ID . $mode );
		if ( ! is_string( $partner_id ) || empty( $partner_id ) ) {
			return $connect_status;
		}

		$response = wp_remote_get( Helper::paypal_host_url() . "v1/customer/partners/{$partner_id}/merchant-integrations/credentials/", $args );
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response );
		$settings = [];
		if ( is_object( $response ) && ! empty( $response->client_id ) && ! empty( $response->client_secret ) && ! empty( $response->payer_id ) ) {

			$check_merchant_onboarding_flag = $this->check_merchant_onboarding_flag( $args, $partner_id, $response->payer_id );
			if ( ! $check_merchant_onboarding_flag['status'] ) {
				return $check_merchant_onboarding_flag;
			}

			if ( 'live' === $mode ) {
				$settings['cppw_client_id']       = $response->client_id;
				$settings['cppw_secret_key']      = $response->client_secret;
				$settings['cppw_live_con_status'] = 'success';
				$settings['cppw_mode']            = 'live';
				$settings['cppw_live_account_id'] = $response->payer_id;
			} else {
				$settings['cppw_sandbox_client_id']  = $response->client_id;
				$settings['cppw_sandbox_secret_key'] = $response->client_secret;
				$settings['cppw_sandbox_con_status'] = 'success';
				$settings['cppw_mode']               = 'sandbox';
				$settings['cppw_sandbox_account_id'] = $response->payer_id;
			}
			$connect_status = [ 'status' => true ];
		} else {
			if ( 'live' === $mode ) {
				$settings['cppw_client_id']       = '';
				$settings['cppw_secret_key']      = '';
				$settings['cppw_live_con_status'] = 'failed';
				$settings['cppw_live_account_id'] = '';
			} else {
				$settings['cppw_sandbox_client_id']  = '';
				$settings['cppw_sandbox_secret_key'] = '';
				$settings['cppw_sandbox_con_status'] = 'failed';
				$settings['cppw_sandbox_account_id'] = '';
			}
		}

		$settings['cppw_auto_connect'] = 'yes';
		$settings['cppw_debug_log']    = 'yes';
		Admin_Helper::update_options( $settings );
		if ( $connect_status['status'] ) {
			Webhook::create_id( $mode );
		}
		return $connect_status;
	}

	/**
	 * Modify PayPal mode.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function modify_paypal_mode() {
		return $this->mode;
	}

	/**
	 * Verify Paypal connection.
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public function verify_connection() {
		add_filter( 'cppw_payment_mode', [ $this, 'modify_paypal_mode' ] );
		$remote_url = 'v1/identity/oauth2/userinfo?schema=paypalv1.1';
		$return     = Client::request( $remote_url, [], 'get' );
		return ! empty( $return['user_id'] ) ? true : false;
	}

	/**
	 * Log js.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_js_errors() {
		Helper::js_errors();
	}

	/**
	 * Check merchant onboarding flag.
	 *
	 * @param array  $args Header content.
	 * @param string $partner_id Paypal partner partner id.
	 * @param string $seller_payer_id Paypal seller id.
	 * @since 1.0.0
	 * @return array
	 */
	public function check_merchant_onboarding_flag( $args, $partner_id, $seller_payer_id ) {
		$response = wp_remote_get( Helper::paypal_host_url() . "v1/customer/partners/{$partner_id}/merchant-integrations/{$seller_payer_id}", $args );
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );

		if ( ! is_array( $response ) || ( ! isset( $response['payments_receivable'] ) || ! isset( $response['primary_email_confirmed'] ) ) ) {
			return [ 'status' => false ];
		}

		if ( true === $response['payments_receivable'] && true === $response['primary_email_confirmed'] ) {
			delete_option( 'cppw_payments_receivable_false' );
			delete_option( 'cppw_primary_email_confirmed_false' );
			return [ 'status' => true ];
		}

		// Check payment receivable or not.
		if ( true !== $response['payments_receivable'] ) {
			update_option( 'cppw_payments_receivable_false', true );
		} else {
			delete_option( 'cppw_payments_receivable_false' );
		}

		// Check primary email confirmed or not.
		if ( true !== $response['primary_email_confirmed'] ) {
			update_option( 'cppw_primary_email_confirmed_false', true );
		} else {
			delete_option( 'cppw_primary_email_confirmed_false' );
		}

		return [
			'status' => false,
		];
	}
}
