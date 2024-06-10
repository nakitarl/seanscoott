<?php
/**
 * Plugin Name: Checkout Plugins - PayPal for WooCommerce
 * Plugin URI: https://www.checkoutplugins.com/
 * Description: PayPal for WooCommerce delivers a simple, secure way to accept credit card payments in your WooCommerce store. Reduce payment friction and boost conversions using this free plugin!
 * Version: 1.0.0
 * Author: Checkout Plugins
 * Author URI: https://checkoutplugins.com/
 * License: GPLv2 or later
 * Text Domain: checkout-paypal-woo
 *
 * @package checkout-paypal-woo
 */

/**
 * Set constants
 */
define( 'CPPW_FILE', __FILE__ );
define( 'CPPW_BASE', plugin_basename( CPPW_FILE ) );
define( 'CPPW_DIR', plugin_dir_path( CPPW_FILE ) );
define( 'CPPW_URL', plugins_url( '/', CPPW_FILE ) );
define( 'CPPW_WEBHOOK_ID', 'cppw_webhook_id' );
define( 'CPPW_PAYPAL_FEE', '_cppw_paypal_fee' );
define( 'CPPW_PAYPAL_NET', '_cppw_paypal_net' );
define( 'CPPW_SUB_AGREEMENT_ID', '_cppw_agreement_id' );
define( 'CPPW_PAYPAL_TRANSACTION_ID', '_cppw_transaction_id' );
define( 'CPPW_VERSION', '1.0.0' );
define( 'CPPW_PAYPAL_API_URL', 'https://api.paypal.com/' );
define( 'CPPW_PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com/' );
define( 'CPPW_PAYPAL_LIGHTBOX_URL', 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js' );
define( 'CPPW_PARTNER_ID', '_cppw_partner_id' );
define( 'CPPW_PAYPAL_DEBUG_ID', 'cppw_paypal_debug_id' );

require_once 'autoloader.php';
