<?php
/**
 * WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Memberships to newer
 * versions in the future. If you wish to customize WooCommerce Memberships for your
 * needs please refer to http://docs.woothemes.com/document/woocommerce-memberships/ for more information.
 *
 * @package   WC-Memberships/Classes
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Integration class for Bookings plugin
 *
 * @since 1.0.0
 */
class WC_Memberships_Integration_Bookings {


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// adjusts discounts
		add_filter( 'booking_form_calculated_booking_cost', array( $this, 'adjust_booking_cost' ), 10, 3 );

		if ( ! is_admin() ) {

			// ensures a purchaseable booking product can be added to cart
			add_action( 'woocommerce_booking_add_to_cart', array( $this, 'add_purchaseable_product_to_cart' ), 1 );
		}
	}


	/**
	 * Adjust booking cost
	 *
	 * @since 1.3.0
	 * @param float $cost
	 * @param \WC_Booking_Form $form
	 * @param array $posted
	 * @return float
	 */
	public function adjust_booking_cost( $cost, WC_Booking_Form $form, $posted ) {

		// don't discount the price when adding a booking to the cart
		if ( doing_action( 'woocommerce_add_cart_item_data' ) ) {
			$discounted_cost = $cost;
		} else {
			$discounted_cost = wc_memberships()->get_member_discounts_instance()->get_discounted_price( $cost, $form->product );
		}

		return is_numeric( $discounted_cost ) ? (float) $discounted_cost : (float) $cost;
	}
	

	/**
	 * Remove add to cart button for nun-purchasable booking products
	 *
	 * TODO: remove this once WC Bookings fixes their is_purchasable implementation {FN 2016-06-20}
	 *
	 * @since 1.6.2
	 */
	public function add_purchaseable_product_to_cart() {
		global $wp_filter, $product;

		// get the restrictions class
		$restrictions = wc_memberships()->get_frontend_instance()->get_restrictions_instance();

		if ( $restrictions && ! $restrictions->product_is_purchasable( true, $product ) ) {

			$tag = 'woocommerce_booking_add_to_cart';

			if ( isset( $wp_filter[ $tag ] ) && ! empty( $wp_filter[ $tag ] ) ) {

				foreach ( $wp_filter[ $tag ] as $priority => $filters ) {

					foreach ( $filters as $key => $filter ) {

						if ( is_array( $filter['function'] ) && is_a( $filter['function'][0], 'WC_Booking_Cart_Manager' ) && 'add_to_cart' === $filter['function'][1] ) {

							unset( $wp_filter[ $tag ][ $priority ][ $key ] );
							unset( $GLOBALS['merged_filters'][ $tag ] );
						}
					}
				}
			}
		}
	}


}
