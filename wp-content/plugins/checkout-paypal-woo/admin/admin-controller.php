<?php
/**
 * Paypal Gateway
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Admin;

use CPPW\Inc\Traits\Get_Instance;
use CPPW\Inc\Helper;
use CPPW\Admin\Notices;
use CPPW\Admin\Admin_Helper;

use WC_Admin_Settings;

/**
 * Admin Controller - This class is used to update or delete paypal settings.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */
class Admin_Controller {

	use Get_Instance;

	/**
	 * Navigation links for the payment method pages.
	 *
	 * @var array $navigation
	 * @since 1.0.0
	 */
	public $navigation = [];

	/**
	 * Paypal settings are stored in this array.
	 *
	 * @var array $settings
	 * @since 1.0.0
	 */
	private $settings = [];

	/**
	 * Paypal connect button sandbox.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $paypal_connect_url_sandbox = '';

	/**
	 * Paypal connect button live.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $paypal_connect_url_live = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		$this->init();

		foreach ( Admin_Helper::get_setting_keys() as $key ) {
			$this->settings[ $key ] = get_option( $key );
		}
		new Notices( $this->settings );

		$this->navigation = apply_filters(
			'cppw_settings_navigation',
			[
				'cppw_api_settings' => __( 'API Settings', 'checkout-paypal-woo' ),
				'cppw_paypal'       => __( 'PayPal Settings', 'checkout-paypal-woo' ),
			]
		);
	}

	/**
	 * Init
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		if ( isset( $_GET['tab'] ) && 'cppw_api_settings' === $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific tab by $_GET['tab'] so we can ignore nonce here.
			$this->add_paypal_connect_url( 'live' );
			$this->add_paypal_connect_url( 'sandbox' );
		}
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_cppw_api_settings', [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_cppw_api_settings', [ $this, 'update_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_action( 'woocommerce_admin_field_cppw_paypal_connect_sandbox', [ $this, 'paypal_connect_button' ] );
		add_action( 'woocommerce_admin_field_cppw_sandbox_account_id', [ $this, 'account_id' ] );
		add_action( 'woocommerce_admin_field_cppw_paypal_connect_live', [ $this, 'paypal_connect_button' ] );
		add_action( 'woocommerce_admin_field_cppw_live_account_id', [ $this, 'account_id' ] );
		add_action( 'woocommerce_admin_field_cppw_webhook_url_sandbox', [ $this, 'webhook_url' ] );
		add_action( 'woocommerce_admin_field_cppw_webhook_url_live', [ $this, 'webhook_url' ] );
		add_filter( 'cppw_settings', [ $this, 'filter_settings_fields' ], 1 );
		add_action( 'admin_head', [ $this, 'add_custom_css' ] );
		add_action( 'woocommerce_sections_cppw_api_settings', [ $this, 'add_breadcrumb' ] );
		add_filter( 'admin_footer_text', [ $this, 'add_manual_connect_link' ] );

		if ( isset( $_GET['section'] ) && 0 === strpos( sanitize_text_field( $_GET['section'] ), 'cppw_' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific section by $_GET['section'] so we can ignore nonce here.
			add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_settings_links' ] );
			add_filter( 'woocommerce_get_sections_cppw_api_settings', [ $this, 'add_settings_links' ] );
		}
	}

	/**
	 * Enqueue Scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		$version               = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? strval( time() ) : CPPW_VERSION;
		$allow_scripts_methods = apply_filters(
			'cppw_allow_admin_scripts_methods',
			[
				'cppw_paypal',
			]
		);

		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && ( 'cppw_api_settings' === $_GET['tab'] || isset( $_GET['section'] ) && ( in_array( sanitize_text_field( $_GET['section'] ), $allow_scripts_methods, true ) ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific tab and section by $_GET['tab'] and $_GET['section'] so we can ignore nonce here.
			wp_register_style( 'cppw-admin-style', plugins_url( 'assets/css/admin.css', __FILE__ ), [], $version, 'all' );
			wp_enqueue_style( 'cppw-admin-style' );

			wp_register_script( 'cppw-admin-js', plugins_url( 'assets/js/admin.js', __FILE__ ), [ 'jquery' ], $version, true );
			wp_enqueue_script( 'cppw-admin-js' );

			wp_register_script( 'paypal-js', CPPW_PAYPAL_LIGHTBOX_URL, [], $version, true );
			wp_enqueue_script( 'paypal-js' );

			wp_localize_script(
				'cppw-admin-js',
				'cppw_ajax_object',
				apply_filters(
					'cppw_admin_localize_script_args',
					[
						'site_url'          => get_site_url() . '/wp-admin/admin.php?page=wc-settings',
						'ajax_url'          => admin_url( 'admin-ajax.php' ),
						'cppw_mode'         => Helper::get_payment_mode(),
						'admin_nonce'       => current_user_can( 'manage_woocommerce' ) ? wp_create_nonce( 'cppw_admin_nonce' ) : '',
						'js_error_nonce'    => wp_create_nonce( 'cppw_js_error_nonce' ),
						'dashboard_url'     => admin_url( 'admin.php?page=wc-settings&tab=cppw_api_settings' ),
						'generic_error'     => __( 'Something went wrong! Please reload the page and try again.', 'checkout-paypal-woo' ),
						'paypal_key_error'  => __( 'You must enter your API keys or connect the plugin before performing a connection test. Mode:', 'checkout-paypal-woo' ),
						'paypal_disconnect' => __( 'Your Paypal account has been disconnected.', 'checkout-paypal-woo' ),
						'has_subscription'  => class_exists( 'WC_Subscriptions' ) && version_compare( \WC_Subscriptions::$version, '2.2.0', '>=' ) ? 1 : 0,
					]
				)
			);
		}
	}

	/**
	 * This method is used to paypal connect button.
	 *
	 * @param array $value Field array.
	 * @since 1.0.0
	 * @return void
	 */
	public function paypal_connect_button( $value ) {
		if ( isset( $value['custom_attributes']['setting-section'] ) && 'live' === $value['custom_attributes']['setting-section'] ) {
			$mode = 'live';
			$url  = $this->paypal_connect_url_live;
		} else {
			$mode = 'sandbox';
			$url  = $this->paypal_connect_url_sandbox;
		}

		if ( true === Admin_Helper::is_paypal_connected( $mode ) ) {
			return;
		}

		$label = __( 'Connect with Paypal', 'checkout-paypal-woo' );
		do_action( 'cppw_before_connection_with_paypal' );

		if ( ! $url ) {
			esc_html_e( 'There are some problem with PayPal auto connect.', 'checkout-paypal-woo' );
			echo ' <a href="javascript:void(0)" class="cppw_connect_mn_btn cppw_show">';
			esc_html_e( 'Manage API keys manually', 'checkout-paypal-woo' );
			echo '</a>';
			return;
		}
		?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
				<fieldset>
				<!-- data-paypal-onboard-complete="cppwOnboardedCallback" Attr will be added by js. -->
					<a setting-section="<?php echo esc_attr( $mode ); ?>" target="_blank" data-mode="<?php echo esc_attr( $mode ); ?>" class="cppw_connect_btn cppw-connect-primary-button-<?php echo esc_attr( $mode ); ?>" data-paypal-button="true" href="<?php echo esc_url( $url ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</a>
					<div class="wc-connect-paypal-help">
						<?php
						/* translators: %1$1s, %2$2s: HTML Markup */
						echo wp_kses_post( sprintf( __( 'Have questions about connecting with Paypal? Read %1$s document. %2$s', 'checkout-paypal-woo' ), '<a href="https://checkoutplugins.com/docs/paypal-api-settings/" target="_blank">', '</a>' ) );
						?>
					</div>
				</fieldset>
			</td>
		</tr>
		<?php
		do_action( 'cppw_after_connection_with_paypal' );
	}

	/**
	 * This method is used to display paypal account ID block.
	 *
	 * @param array $value Field array.
	 * @since 1.0.0
	 * @return void
	 */
	public function account_id( $value ) {
		$mode = isset( $value['custom_attributes']['setting-section'] ) && 'live' === $value['custom_attributes']['setting-section'] ? 'live' : 'sandbox';
		if ( false === Admin_Helper::is_paypal_connected( $mode ) ) {
			return;
		}

		$option_value = Helper::get_setting( 'cppw_' . $mode . '_account_id' );
		$option_value = ! empty( $option_value ) && is_string( $option_value ) ? $option_value : '';
		do_action( 'cppw_before_connected_with_paypal' );
		?>
		<tr valign="top">
			<th scope="row">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td setting-section='<?php echo esc_attr( $mode ); ?>' class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
				<fieldset>
					<div class="account_status">
						<p>
							<?php
							if ( empty( $option_value ) ) {
								/* translators: %1$1s %2$2s %3$3s: HTML Markup */
								esc_html_e( 'Your manually managed API keys are valid.', 'checkout-paypal-woo' );
								echo '<span style="color:green;font-weight:bold;font-size:20px;margin-left:5px;">&#10004;</span>';

								echo '<div class="notice inline notice-success">';
								echo '<p>' . esc_html__( 'It is highly recommended to Connect with Paypal for easier setup and improved security.', 'checkout-paypal-woo' ) . '</p>';
								echo '</div>';
							} else {
								?>
								<?php
								/* translators: $1s Account name, $2s html markup, $3s account id, $4s html markup */
								echo wp_kses_post( sprintf( __( 'Account (%1$1s %2$2s %3$3s) is connected.', 'checkout-paypal-woo' ), '<strong>', esc_attr( $option_value ), '</strong>' ) );
								echo '<span style="color:green;font-weight:bold;font-size:20px;margin-left:5px;">&#10004;</span>';
								?>
						</p>
								<?php
							}
							?>
					<p>
						<a href="#" id="cppw_disconnect_acc"><?php esc_html_e( 'Disconnect &amp; connect other account?', 'checkout-paypal-woo' ); ?></a>|
						<a href="#" id="cppw_test_connection"><?php esc_html_e( 'Test Connection', 'checkout-paypal-woo' ); ?></a>
					</p>
					</div>
				</fieldset>
			</td>
		</tr>
		<?php
		do_action( 'cppw_after_connected_with_paypal' );
	}

	/**
	 * This method is used to display block for Paypal webhook url.
	 *
	 * @param array $value Field array.
	 * @since 1.0.0
	 * @return void
	 */
	public function webhook_url( $value ) {
		$data         = WC_Admin_Settings::get_field_description( $value );
		$tooltip_html = is_string( $data['tooltip_html'] ) ? wc_help_tip( $data['tooltip_html'] ) : '';
		?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
			</th>
			<td setting-section='<?php echo esc_attr( $value['value'] ); ?>' class="form-wc form-wc-<?php echo esc_attr( $value['class'] ); ?>">
				<fieldset>
					<strong><?php echo esc_url( get_home_url() . "/wp-json/cppw/{$value['value']}/webhook" ); ?></strong>
				</fieldset>
				<fieldset>
					<?php echo wp_kses_post( $value['desc'] ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * This method is used to initialize the Paypal settings tab inside the WooCommerce settings.
	 *
	 * @param array $settings_tabs Adding settings tab to existing WooCommerce tabs array.
	 * @since 1.0.0
	 * @return array
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['cppw_api_settings'] = __( 'Paypal', 'checkout-paypal-woo' );
		return $settings_tabs;
	}

	/**
	 * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
	 *
	 * @uses woocommerce_admin_fields()
	 * @uses $this->get_settings()
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function update_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * This method is used to initialize all paypal configuration fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {
		$settings = [
			'section_title'               => [
				'name' => __( 'Paypal API Settings', 'checkout-paypal-woo' ),
				'type' => 'title',
				'id'   => 'cppw_title',
			],
			'cppw_mode_section'           => [
				'name'     => __( 'Mode', 'checkout-paypal-woo' ),
				'type'     => 'select',
				'options'  => [
					'sandbox' => __( 'SandBox', 'checkout-paypal-woo' ),
					'live'    => __( 'Live', 'checkout-paypal-woo' ),
				],
				'desc'     => __( 'No live transactions are processed in sandbox mode. To fully use sandbox mode, you must have a sandbox account for the payment gateway you are testing.', 'checkout-paypal-woo' ),
				'id'       => 'cppw_mode',
				'desc_tip' => true,
			],
			'cppw_paypal_connect_sandbox' => [
				'name'              => __( 'Paypal Connect', 'checkout-paypal-woo' ),
				'type'              => 'cppw_paypal_connect_sandbox',
				'value'             => '--',
				'class'             => 'wc_cppw_connect_btn',
				'id'                => 'cppw_paypal_connect_sandbox',
				'custom_attributes' => [
					'setting-section' => 'sandbox',
				],
			],
			'sandbox_account_id'          => [
				'name'              => __( 'Connection Status', 'checkout-paypal-woo' ),
				'type'              => 'cppw_sandbox_account_id',
				'value'             => '--',
				'class'             => 'account_id',
				'desc_tip'          => __( 'This is your Paypal Connect ID and serves as a unique identifier.', 'checkout-paypal-woo' ),
				'desc'              => __( 'This is your Paypal Connect ID and serves as a unique identifier.', 'checkout-paypal-woo' ),
				'id'                => 'cppw_sandbox_account_id',
				'custom_attributes' => [
					'setting-section' => 'sandbox',
				],
			],
			'webhook_url_sandbox'         => [
				'name'  => __( 'Webhook URL', 'checkout-paypal-woo' ),
				'type'  => 'cppw_webhook_url_sandbox',
				'class' => 'wc_cppw_webhook_url',
				/* translators: %1$1s - %2$2s HTML markup */
				'desc'  => sprintf( __( 'Important: the webhook URL is called by Paypal when events occur in your account, like a source becomes chargeable. %1$1sWebhook Guide%2$2s or create webhook on %3$3spaypal dashboard%4$4s', 'checkout-paypal-woo' ), '<a href="https://checkoutplugins.com/docs/paypal-card-payments/#webhook" target="_blank">', '</a>', '<a href="https://dashboard.paypal.com/webhooks/create" target="_blank">', '</a>' ),
				'id'    => 'cppw_webhook_url_sandbox',
				'value' => 'sandbox',
			],
			'cppw_paypal_connect_live'    => [
				'name'              => __( 'Paypal Connect Live', 'checkout-paypal-woo' ),
				'type'              => 'cppw_paypal_connect_live',
				'value'             => '--',
				'class'             => 'wc_cppw_connect_btn',
				'id'                => 'cppw_paypal_connect_live',
				'custom_attributes' => [
					'setting-section' => 'live',
				],
			],
			'live_account_id'             => [
				'name'              => __( 'Connection Status', 'checkout-paypal-woo' ),
				'type'              => 'cppw_live_account_id',
				'value'             => '--',
				'class'             => 'live_account_id',
				'desc_tip'          => __( 'This is your Paypal Connect ID and serves as a unique identifier.', 'checkout-paypal-woo' ),
				'desc'              => __( 'This is your Paypal Connect ID and serves as a unique identifier.', 'checkout-paypal-woo' ),
				'id'                => 'cppw_live_account_id',
				'custom_attributes' => [
					'setting-section' => 'live',
				],
			],
			'webhook_url_live'            => [
				'name'  => __( 'Webhook URL', 'checkout-paypal-woo' ),
				'type'  => 'cppw_webhook_url_live',
				'class' => 'wc_cppw_webhook_url',
				/* translators: %1$1s - %2$2s HTML markup */
				'desc'  => sprintf( __( 'Important: the webhook URL is called by Paypal when events occur in your account, like a source becomes chargeable. %1$1s Webhook Guide %2$2s or create webhook on %3$3s paypal dashboard %4$4s', 'checkout-paypal-woo' ), '<a href="https://checkoutplugins.com/docs/paypal-card-payments/#webhook" target="_blank">', '</a>', '<a href="https://dashboard.paypal.com/webhooks/create" target="_blank">', '</a>' ),
				'id'    => 'cppw_webhook_url_live',
				'value' => 'live',
			],
			'debug_log'                   => [
				'name'              => __( 'Debug Log', 'checkout-paypal-woo' ),
				'type'              => 'checkbox',
				'desc'              => __( 'Log debug messages', 'checkout-paypal-woo' ),
				'description'       => __( 'Your publishable key is used to initialize Paypal assets.', 'checkout-paypal-woo' ),
				'id'                => 'cppw_debug_log',
				'custom_attributes' => [
					'setting-section' => 'sandbox',
				],
			],
			'section_end'                 => [
				'type' => 'sectionend',
				'id'   => 'cppw_api_settings_section_end',
			],
		];
		$settings = apply_filters( 'cppw_settings', $settings );

		return $settings;
	}

	/**
	 * Apply filters on cppw_settings var to filter settings fields.
	 *
	 * @param array $array cppw_settings values array.
	 *
	 * @since 1.0.0
	 * @return array $array  It returns cppw_settings array.
	 */
	public function filter_settings_fields( $array = [] ) {
		if ( 'success' !== Helper::get_setting( 'cppw_live_con_status' ) || 'success' !== Helper::get_setting( 'cppw_sandbox_con_status' ) ) {
			// for live connection status.
			if ( 'success' !== Helper::get_setting( 'cppw_live_con_status' ) ) {
				unset( $array['live_account_id'] );
				unset( $array['webhook_url_live'] );
			}

			// for sandbox connection status.
			if ( 'success' !== Helper::get_setting( 'cppw_sandbox_con_status' ) ) {
				unset( $array['sandbox_account_id'] );
				unset( $array['webhook_url_sandbox'] );
			}
		}
		return $array;
	}

	/**
	 * Adds custom css to hide navigation menu item.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_custom_css() {
		?>
		<style type="text/css">
			a[href='<?php echo esc_url( get_site_url() ); ?>/wp-admin/admin.php?page=wc-settings&tab=cppw_api_settings'].nav-tab {
				display: none
			}
		</style>
		<?php
	}

	/**
	 * Adds custom breadcrumb on payment method's pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_breadcrumb() {
		if ( ! empty( $this->navigation ) ) {
			?>
			<ul class="subsubsub">
				<?php
				foreach ( $this->navigation as $key => $value ) {
					$current_class = '';
					$separator     = '';
					if ( isset( $_GET['tab'] ) && 'cppw_api_settings' === $_GET['tab'] && 'cppw_api_settings' === $key ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific tab by $_GET['tab'] so we can ignore nonce here.
						$current_class = 'current';
						echo wp_kses_post( '<li> <a href="' . get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=cppw_api_settings" class="' . $current_class . '">' . $value . '</a> | </li>' );
					} else {
						if ( end( $this->navigation ) !== $value ) {
							$separator = ' | ';
						}
						echo wp_kses_post( '<li> <a href="' . get_site_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=' . $key . '" class="' . $current_class . '">' . $value . '</a> ' . $separator . ' </li>' );
					}
				}
				?>
			</ul>
			<br class="clear" />
			<?php
		}
	}

	/**
	 * Adds settings link to the checkout section.
	 *
	 * @param array $settings_tab Settings tabs array.
	 * @since 1.0.0
	 * @return array $settings_tab Settings tabs array returned.
	 */
	public function add_settings_links( $settings_tab ) {
		if ( ! empty( $this->navigation ) && is_array( $this->navigation ) ) {
			$settings_tab = array_merge( $settings_tab, $this->navigation );
			array_shift( $settings_tab );
		}
		return apply_filters( 'cppw_setting_tabs', $settings_tab );
	}

	/**
	 * Adds manual api keys links.
	 *
	 * @param string $links default copyright link with text.
	 * @since 1.0.0
	 * @return string $links Return customized copyright text with link.
	 */
	public function add_manual_connect_link( $links ) {
		if ( ! isset( $_GET['page'] ) || ! isset( $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'cppw_api_settings' !== $_GET['tab'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific tab and page by $_GET['tab'] and $_GET['page'] so we can ignore nonce here.
			return $links;
		}

		if ( 'yes' === $this->settings['cppw_auto_connect'] || 'no' === $this->settings['cppw_auto_connect'] ) {
			return $links;
		}

		if ( ! isset( $_GET['connect'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=cppw_api_settings&connect=manually" class="cppw_connect_mn_btn">' . __( 'Manage API keys manually', 'checkout-paypal-woo' ) . '</a>';
		}

		if ( isset( $_GET['connect'] ) && 'manually' === $_GET['connect'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We are only checking specific connect by $_GET['connect'] so we can ignore nonce here.
			return '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=cppw_api_settings" class="cppw_connect_hide_btn">' . __( 'Hide API keys', 'checkout-paypal-woo' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Initialize paypal connect url live and sandbox both.
	 *
	 * @param string $mode Paypal mode.
	 * @since 1.0.0
	 * @return void
	 */
	public function add_paypal_connect_url( $mode ) {
		if ( 'success' !== Helper::get_setting( 'cppw_' . $mode . '_con_status' ) ) {
			$sandbox_live_mode = Admin_Helper::get_paypal_connect_url( $mode );

			if ( 'live' === $mode ) {
				$this->paypal_connect_url_live = $sandbox_live_mode;
			} elseif ( 'sandbox' === $mode ) {
				$this->paypal_connect_url_sandbox = $sandbox_live_mode;
			}
		}
	}
}
