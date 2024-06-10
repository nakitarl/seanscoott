<?php
/**
 * PayPal Fee
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Gateway\Paypal;

use WC_Order;
/**
 * Paypal_Fee
 *
 * @since 1.0.0
 */
class Paypal_Fee {

	/**
	 * Add paypal fee
	 *
	 * @param object  $order Add order paypal fee.
	 * @param array   $price_breakdown Fee amount's breakdown.
	 * @param boolean $save Should save order meta.
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_fee_net_amount( $order, $price_breakdown, $save = true ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( ! empty( $price_breakdown['paypal_fee']['value'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_FEE, $price_breakdown['paypal_fee']['value'] );
		}
		if ( ! empty( $price_breakdown['net_amount']['value'] ) ) {
			$order->update_meta_data( CPPW_PAYPAL_NET, $price_breakdown['net_amount']['value'] );
		}
		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Get paypal fee amount
	 *
	 * @param object $order Add order paypal fee.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_fee_amount( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		$fee = $order->get_meta( CPPW_PAYPAL_FEE );
		$fee = floatval( $fee );
		return $fee ? wc_price( $fee, [ 'currency' => $order->get_currency() ] ) : '';
	}

	/**
	 * Get net amount
	 *
	 * @param object $order Add order paypal fee.
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_net_amount( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		$net = $order->get_meta( CPPW_PAYPAL_NET );
		$net = floatval( $net );
		return $net ? wc_price( $net, [ 'currency' => $order->get_currency() ] ) : '';
	}

	/**
	 * Update net amount
	 *
	 * @param object     $order Add order paypal fee.
	 * @param float|null $refund_amount Refund amount.
	 * @param boolean    $save Should save order meta.
	 * @since 1.0.0
	 * @return void
	 */
	public static function update_net_amount( $order, $refund_amount, $save = true ) {
		if ( ! $refund_amount || ! $order instanceof WC_Order ) {
			return;
		}
		$fee = $order->get_meta( CPPW_PAYPAL_FEE );
		$fee = is_numeric( $fee ) ? $fee : 0;
		$net = $order->get_total() - $fee - $refund_amount;
		$order->update_meta_data( CPPW_PAYPAL_NET, strval( $net ) );
		if ( $save ) {
			$order->save();
		}
	}
}
