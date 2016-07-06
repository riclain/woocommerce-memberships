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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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

		add_filter( 'booking_form_calculated_booking_cost', array( $this, 'adjust_booking_cost' ), 10, 3 );
	}


	/**
	 * Adjust booking cost
	 *
	 * @since 1.3.0
	 * @param int $cost
	 * @param WC_Booking_Form $form
	 * @param array $posted
	 * @return int
	 */
	function adjust_booking_cost( $cost, WC_Booking_Form $form, $posted ) {

		// Don't discount the price when adding a booking to the cart
		if ( doing_action( 'woocommerce_add_cart_item_data' ) ) {
			return $cost;
		}

		if ( wc_memberships()->member_discounts->has_discounted_price( $cost, $form->product ) ) {
			$cost = wc_memberships()->member_discounts->get_discounted_price( $cost, $form->product );
		}

		return $cost;
	}

}

new WC_Memberships_Integration_Bookings();
