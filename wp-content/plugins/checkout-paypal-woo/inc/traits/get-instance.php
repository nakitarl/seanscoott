<?php
/**
 * Trait.
 *
 * @package checkout-paypal-woo
 */

namespace CPPW\Inc\Traits;

/**
 * Trait Get_Instance.
 */
trait Get_Instance {

	/**
	 * Instance object.
	 *
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
}
