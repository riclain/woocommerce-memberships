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

/**
 * Integration class for the Product Add-ons plugin
 *
 * @since 1.3.4
 */
class WC_Memberships_Integration_Product_Addons {

	/**
	 * Construct the class.
	 *
	 * @since 1.3.4
	 */
	public function __construct() {

		// Disable discounted price caching if dealing with add-on products.
		add_filter( 'wc_memberships_cache_discounted_price', array( $this, 'disable_discount_caching' ), 10, 3 );
	}


	/**
	 * Disable discounted price caching if dealing with add-on products.
	 *
	 * @since 1.3.4
	 * @param bool $cache_results Whether the discounted price should be cached.
	 * @param int $user_id The cache user ID.
	 * @param int $product_id The cache product ID.
	 * @return bool
	 */
	function disable_discount_caching( $cache_results, $user_id, $product_id ) {

		// Disable discount price caching on the cart or checkout pages.
		if ( is_cart() || is_checkout() ) {
			$cache_results = false;
		}

		return $cache_results;
	}
}
