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
 * Main function for returning a membership plan
 *
 * @since 1.0.0
 * @param int|string|\WC_Memberships_Membership_Plan $membership_plan Post object, ID or slug of the membership plan
 * @return \WC_Memberships_Membership_Plan|false Returns the plan object or false on failure
 */
function wc_memberships_get_membership_plan( $membership_plan = null ) {
	return wc_memberships()->get_plans_instance()->get_membership_plan( $membership_plan );
}


/**
 * Main function for returning all available membership plans
 *
 * @since 1.0.0
 * @param array $args Optional array of arguments, same as for get_posts()
 * @return \WC_Memberships_Membership_Plan[]
 */
function wc_memberships_get_membership_plans( $args = null ) {
	return wc_memberships()->get_plans_instance()->get_membership_plans( $args );
}


/**
 * Get member area sections
 *
 * @since 1.4.0
 * @param int|string $membership_plan Optional: membership plan id for filtering purposes
 * @return array
 */
function wc_memberships_get_members_area_sections( $membership_plan = '' ) {

	/**
	 * Filters the available choices for the member area sections of a membership plan
	 *
	 * @since 1.4.0
	 * @param array $member_area_sections Associative array with member area id and label of each section
	 * @param int|string $membership_plan Optional, the current membership plan, might be empty
	 */
	return apply_filters( 'wc_membership_plan_members_area_sections', array(
		'my-membership-content'   => __( 'My Content', 'woocommerce-memberships' ),
		'my-membership-products'  => __( 'My Products', 'woocommerce-memberships' ),
		'my-membership-discounts' => __( 'My Discounts', 'woocommerce-memberships' ),
		'my-membership-notes'     => __( 'Membership Notes', 'woocommerce-memberships' ),
	), $membership_plan );
}
