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
 * Main function for returning a user membership
 *
 * Supports getting user membership by membership ID, Post object
 * or a combination of the user ID and membership plan id/slug/Post object.
 *
 * If no $id is provided, defaults to getting the membership for the current user.
 *
 * @since 1.0.0
 * @param mixed $id Optional. Post object or post ID of the user membership, or user ID
 * @param mixed $plan Optional. Membership Plan slug, post object or related post ID
 * @return WC_Memberships_User_Membership
 */
function wc_memberships_get_user_membership( $id = null, $plan = null ) {
	return wc_memberships()->user_memberships->get_user_membership( $id, $plan );
}


/**
 * Get all user membership statuses
 *
 * @since 1.0.0
 * @return array
 */
function wc_memberships_get_user_membership_statuses() {
	return wc_memberships()->user_memberships->get_user_membership_statuses();
}


/**
 * Get the nice name for a user membership status
 *
 * @since  1.0.0
 * @param  string $status
 * @return string
 */
function wc_memberships_get_user_membership_status_name( $status ) {

	$statuses = wc_memberships_get_user_membership_statuses();
	$status   = 'wcm-' === substr( $status, 0, 4 ) ? substr( $status, 4 ) : $status;
	$status   = isset( $statuses[ 'wcm-' . $status ] ) ? $statuses[ 'wcm-' . $status ] : $status;

	return is_array( $status ) && isset( $status['label'] ) ? $status['label'] : $status;
}


/**
 * Get all memberships for a user
 *
 * @since 1.0.0
 * @param int $user_id Optional. Defaults to current user.
 * @param array $args
 * @return WC_Memberships_User_Membership[]|null array of user memberships
 */
function wc_memberships_get_user_memberships( $user_id = null, $args = array() ) {
	return wc_memberships()->user_memberships->get_user_memberships( $user_id, $args );
}


/**
 * Check if user is an active member of a particular membership plan
 *
 * @since 1.0.0
 * @param int $user_id Optional. Defaults to current user.
 * @param int|string $plan Membership Plan slug, post object or related post ID
 * @return bool True, if is an active member, false otherwise
 */
function wc_memberships_is_user_active_member( $user_id = null, $plan ) {
	return wc_memberships()->user_memberships->is_user_active_member( $user_id, $plan );
}


/**
 * Check if user is a member of a particular membership plan
 *
 * @since 1.0.0
 * @param int $user_id Optional. Defaults to current user.
 * @param int|string $plan Membership Plan slug, post object or related post ID
 * @return bool True, if is a member, false otherwise
 */
function wc_memberships_is_user_member( $user_id = null, $plan ) {
	return wc_memberships()->user_memberships->is_user_member( $user_id, $plan );
}


/**
 * Create a new user membership programmatically
 *
 * Returns a new user membership object on success which can then be used to add additional data.
 *
 * @since 1.3.0
 * @param array $args Array of arguments
 * @param string $action Action - either 'create' or 'renew'. When in doubt, use 'create'
 * @return WC_Memberships_User_Membership on success, WP_Error on failure
 */
function wc_memberships_create_user_membership( $args = array(), $action = 'create' ) {

	$defaults = array(
		'user_membership_id' => 0,
		'plan_id'            => 0,
		'user_id'            => 0,
		'product_id'         => 0,
		'order_id'           => 0,
	);

	$args = wp_parse_args( $args, $defaults );

	$data	= array(
		'post_parent'    => $args['plan_id'],
		'post_author'    => $args['user_id'],
		'post_type'      => 'wc_user_membership',
		'post_status'    => 'wcm-active',
		'comment_status' => 'open',
	);

	if ( $args['user_membership_id'] > 0 ) {
		$updating   = true;
		$data['ID'] = $args['user_membership_id'];
	} else {
		$updating = false;
	}

	/**
	 * Filter new membership data, used when a product purchase grants access
	 *
	 * @param array $data
	 * @param array $args
	 */
	$data = apply_filters( 'wc_memberships_new_membership_data', $data, array(
		'user_id'    => $args['user_id'],
		'product_id' => $args['product_id'],
		'order_id'   => $args['order_id'],
	) );

	if ( $updating ) {
		$user_membership_id = wp_update_post( $data );
	} else {
		$user_membership_id = wp_insert_post( $data );
	}

	// Bail out on error
	if ( is_wp_error( $user_membership_id ) ) {
		return $user_membership_id;
	}


	// Save/update product and order id that granted access
	if ( $args['product_id'] > 0 ) {
		update_post_meta( $user_membership_id, '_product_id', $args['product_id'] );
	}

	if ( $args['order_id'] > 0 ) {
		update_post_meta( $user_membership_id, '_order_id',   $args['order_id'] );
	}


	// Save/update the membership start date, but only if the membership
	// is not active, ie is not being renewed early.
	if ( 'renew' != $action ) {
		update_post_meta( $user_membership_id, '_start_date', current_time( 'mysql', true ) );
	}

	// Get the membership plan object so we can calculate end time
	$plan = wc_memberships_get_membership_plan( $args['plan_id'] );

	// Calculate membership end date based on membership length, optionally
	// from the existing end date, if renewing early
	$end_date = '';

	if ( $plan->get_access_length_amount() ) {

		// Early renewals add to the existing membership length, normal
		// cases calculate membership length from now (UTC)
		$now = 'renew' == $action
					 ? get_post_meta( $user_membership_id, '_end_date', true )
					 : current_time( 'mysql', true );

		$end_date = $plan->get_expiration_date( $now );
	}

	// Save/update end date
	$user_membership = wc_memberships_get_user_membership( $user_membership_id );
	$user_membership->set_end_date( $end_date );

	/**
	 * Fires after a user has been granted membership access
	 *
	 * This action hook is similar to wc_memberships_user_membership_saved
	 * but won't fire when memberships are manually created from admin
	 *
	 * @since 1.3.0
	 *
	 * @param WC_Memberships_Membership_Plan $membership_plan The plan that user was granted access to
	 * @param array $args
	 */
	do_action( 'wc_memberships_user_membership_created', $plan, array(
		'user_id'            => $args['user_id'],
		'user_membership_id' => $user_membership->get_id(),
		'is_update'          => $updating,
	) );

	return $user_membership;
}
