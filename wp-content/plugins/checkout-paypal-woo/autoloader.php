<?php
/**
 * Auto Loader.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW;

use CPPW\Gateway\Paypal\Paypal_Payments;
use CPPW\Admin\Admin_Controller;
use CPPW\Admin\Ajax_Calls;
use CPPW\Admin\Order_Meta;
use CPPW\Gateway\Paypal\Webhook\Listener;
use CPPW\Gateway\Paypal\Frontend_Scripts;
use CPPW\Gateway\Paypal\Front_Route;

/**
 * CPPW_Loader
 *
 * @since 1.0.0
 */
class CPPW_Loader {

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class class name.
	 * @since 1.0.0
	 * @return void
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$class_to_load = preg_replace(
			[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
			[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
			$class
		);

		if ( empty( $class_to_load ) ) {
			return;
		}

		$filename = strtolower( $class_to_load );

		$file = CPPW_DIR . $filename . '.php';

		// if the file readable, include it.
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		// Activation hook.
		register_activation_hook( CPPW_FILE, [ $this, 'install' ] );

		spl_autoload_register( [ $this, 'autoload' ] );
		add_action( 'plugins_loaded', [ $this, 'load_classes' ] );
		add_filter( 'plugin_action_links_' . CPPW_BASE, [ $this, 'action_links' ] );
		add_action( 'woocommerce_init', [ $this, 'frontend_scripts' ] );
		add_action( 'plugins_loaded', [ $this, 'load_cppw_textdomain' ] );
	}

	/**
	 * Sets up base classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function setup_classes() {
		Admin_Controller::get_instance();
		Ajax_Calls::get_instance();
		Order_Meta::get_instance();
	}

	/**
	 * Includes frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function frontend_scripts() {
		if ( is_admin() ) {
			return;
		}

		Frontend_Scripts::get_instance();
		Front_Route::get_instance();
	}

	/**
	 * Adds links in Plugins page
	 *
	 * @param array $links existing links.
	 * @since 1.0.0
	 * @return array
	 */
	public function action_links( $links ) {
		$plugin_links = apply_filters(
			'cppw_plugin_action_links',
			[
				'cppw_settings'      => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=cppw_api_settings' ) . '">' . __( 'Settings', 'checkout-paypal-woo' ) . '</a>',
				'cppw_documentation' => '<a href="#" target="_blank" >' . __( 'Documentation', 'checkout-paypal-woo' ) . '</a>',
			]
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Loads classes on plugins_loaded hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_classes() {
		if ( ! class_exists( 'woocommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'wc_is_not_active' ] );
			return;
		}

		if ( is_admin() ) {
			$this->setup_classes();
		}
		// Initializing Gateways.

		Paypal_Payments::get_instance();
		Listener::get_instance();
	}

	/**
	 * Loads classes on plugins_loaded hook.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function wc_is_not_active() {
		$install_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'install-plugin',
					'plugin' => 'woocommerce',
				],
				admin_url( 'update.php' )
			),
			'install-plugin_woocommerce'
		);
		echo '<div class="notice notice-error is-dismissible"><p>';
		// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: link tags, takes to woocommerce plugin on wp.org, 5$-6$: opening and closing link tags, leads to plugins.php in admin.
		echo sprintf( esc_html__( '%1$sCheckout Plugins - PayPal for WooCommerce is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for Checkout Plugins - PayPal for WooCommerce to work. Please %5$s install & activate WooCommerce &raquo;%6$s', 'checkout-paypal-woo' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>', '<a href="' . esc_url( $install_url ) . '">', '</a>' );
		echo '</p></div>';
	}

	/**
	 * Checks for installation routine
	 * Loads plugins translation file
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function install() {
		update_option( 'cppw_start_onboarding', true );
	}

	/**
	 * Loads plugins translation file
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_cppw_textdomain() {
		// Default languages directory.
		$lang_dir = CPPW_DIR . 'languages/';

		// Traditional WordPress plugin locale filter.
		global $wp_version;

		$get_locale = get_locale();

		if ( $wp_version >= 4.7 ) {
			$get_locale = get_user_locale();
		}

		$locale = apply_filters( 'plugin_locale', $get_locale, 'checkout-paypal-woo' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'checkout-paypal-woo', $locale );

		// Setup paths to current locale file.
		$mofile_local  = $lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/checkout-paypal-woo/ folder.
			load_textdomain( 'checkout-paypal-woo', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/checkout-paypal-woo/languages/ folder.
			load_textdomain( 'checkout-paypal-woo', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'checkout-paypal-woo', false, $lang_dir );
		}
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
CPPW_Loader::get_instance();
