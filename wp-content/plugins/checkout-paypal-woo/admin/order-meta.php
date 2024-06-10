<?php
/**
 * Order meta
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */

namespace CPPW\Admin;

use CPPW\Inc\Traits\Get_Instance;
use CPPW\Gateway\Paypal\Paypal_Fee;
use WC_Order;
/**
 * Order meta - This class is used to show oder meta like paypal fee.
 *
 * @package checkout-paypal-woo
 * @since 1.0.0
 */
class Order_Meta {

	use Get_Instance;

	/**
	 * Constructor function.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'paypal_fee_order_summary' ] );
	}

	/**
	 * Show paypal fee
	 *
	 * @param int $order_id Woocommerce order id.
	 * @since 1.0.0
	 * @return void
	 */
	public function paypal_fee_order_summary( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'cppw_paypal' === $order->get_payment_method() ) {
			$fee = Paypal_Fee::get_fee_amount( $order );
			$net = Paypal_Fee::get_net_amount( $order );
			if ( $fee && $net ) {
				?>
				<tr>
					<td class="label wc-ppcp-fee"><?php esc_html_e( 'PayPal Fee', 'checkout-paypal-woo' ); ?>:</td>
					<td width="1%"></td>
					<td><?php echo wp_kses_post( $fee ); ?></td>
				</tr>
				<tr>
					<td class="label wc-ppcp-net"><?php esc_html_e( 'Net payout', 'checkout-paypal-woo' ); ?></td>
					<td width="1%"></td>
					<td class="total"><?php echo wp_kses_post( $net ); ?></td>
				</tr>
				<?php
			}
		}
	}
}
