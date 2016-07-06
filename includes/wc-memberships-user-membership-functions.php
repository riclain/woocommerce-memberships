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
 * @return \WC_Memberships_User_Membership|false The User Membership or false if not found
 */
function wc_memberships_get_user_membership( $id = null, $plan = null ) {
	return wc_memberships()->get_user_memberships_instance()->get_user_membership( $id, $plan );
}


/**
 * Get all user membership statuses
 *
 * @since 1.0.0
 * @return array
 */
function wc_memberships_get_user_membership_statuses() {
	return wc_memberships()->get_user_memberships_instance()->get_user_membership_statuses();
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
	$status   = 0 === strpos( $status, 'wcm-' ) ? substr( $status, 4 ) : $status;
	$status   = isset( $statuses[ 'wcm-' . $status ] ) ? $statuses[ 'wcm-' . $status ] : $status;

	return is_array( $status ) && isset( $status['label'] ) ? $status['label'] : $status;
}


/**
 * Get all memberships for a user
 *
 * @since 1.0.0
 * @param int $user_id Optional, defaults to current user
 * @param array $args Optional arguments
 * @return \WC_Memberships_User_Membership[]|null array of user memberships
 */
function wc_memberships_get_user_memberships( $user_id = null, $args = array() ) {
	return wc_memberships()->get_user_memberships_instance()->get_user_memberships( $user_id, $args );
}


/**
 * Check if user is an active member of a particular membership plan
 *
 * @since 1.0.0
 * @param int $user_id Optional, defaults to current user
 * @param int|string $plan Membership Plan slug, post object or related post ID
 * @return bool
 */
function wc_memberships_is_user_active_member( $user_id = null, $plan ) {
	return wc_memberships()->get_user_memberships_instance()->is_user_active_member( $user_id, $plan );
}


/**
 * Check if user is a member of a particular membership plan
 *
 * @since 1.0.0
 * @param int $user_id Optional, defaults to current user
 * @param int|string $membership_plan Membership Plan slug, post object or related post ID
 * @return bool
 */
function wc_memberships_is_user_member( $user_id = null, $membership_plan ) {
	return wc_memberships()->get_user_memberships_instance()->is_user_member( $user_id, $membership_plan );
}


/**
 * Check if a product is accessible (viewable or purchaseable)
 *
 * TODO for now `$target` only supports a simple array like  'post' => id  or  'product' => id  - in future we could extend this to take arrays or different/multiple args {FN 2016-04-26}
 *
 * @since 1.4.0
 * @param int $user_id User to check if has access
 * @param string|array Type of capabilities: 'view', 'purchase' (products only)
 * @param array $target Associative array of content type and content id to access to
 * @param int|string UTC timestamp to compare for content access (optional, defaults to now)
 * @return bool|null
 */
function wc_memberships_user_can( $user_id, $action, $target, $when = '' ) {
	return wc_memberships()->get_capabilities_instance()->user_can( $user_id, $action, $target, $when );
}


/**
 * Create a new user membership programmatically
 *
 * Returns a new user membership object on success
 * which can then be used to add additional data,
 * but will return WP_Error on failure
 *
 * @since 1.3.0
 * @param array $args Array of arguments
 * @param string $action Action - either 'create' or 'renew' -- when in doubt, use 'create'
 * @return \WC_Memberships_User_Membership|\WP_Error
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

	$data = array(
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

	// bail out on error
	if ( is_wp_error( $user_membership_id ) ) {
		return $user_membership_id;
	}

	// save/update product and order id that granted access
	if ( $args['product_id'] > 0 ) {
		update_post_meta( $user_membership_id, '_product_id', $args['product_id'] );
	}

	if ( $args['order_id'] > 0 ) {
		update_post_meta( $user_membership_id, '_order_id',   $args['order_id'] );
	}

	// save or update the membership start date,
	// but only if the membership is not active,
	// ie is not being renewed early
	if ( 'renew' !== $action ) {
		update_post_meta( $user_membership_id, '_start_date', current_time( 'mysql', true ) );
	}

	// get the membership plan object so we can calculate end time
	$membership_plan = wc_memberships_get_membership_plan( $args['plan_id'] );

	// calculate membership end date based on membership length,
	// optionally from the existing end date, if renewing early
	$end_date = '';

	if ( $membership_plan->get_access_length_amount() ) {

		// early renewals add to the existing membership length,
		// normal cases calculate membership length from "now" (UTC)
		if ( 'renew' === $action ) {
			$now = get_post_meta( $user_membership_id, '_end_date', true );
		} else {
			$now = current_time( 'mysql', true );
		}

		$end_date = $membership_plan->get_expiration_date( $now, $args );
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
	 * @param \WC_Memberships_Membership_Plan $membership_plan The plan that user was granted access to
	 * @param array $args
	 */
	do_action( 'wc_memberships_user_membership_created', $membership_plan, array(
		'user_id'            => $args['user_id'],
		'user_membership_id' => $user_membership->get_id(),
		'is_update'          => $updating,
	) );

	return $user_membership;
}


/**
 * Get Users Memberships from a Subscription
 *
 * Returns empty array if no User Memberships are found or Subscriptions is inactive
 *
 * @since 1.5.4
 * @param int|\WP_Post $subscription A Subscription post object or id
 * @return \WC_Memberships_User_Membership[] Array of User Membership objects or empty array if none found
 */
function wc_memberships_get_memberships_from_subscription( $subscription ) {

	$integrations = wc_memberships()->get_integrations_instance();

	if ( ! $integrations || true !== $integrations->is_subscriptions_active() ) {
		return array();
	}

	$subscriptions = $integrations->get_subscriptions_instance();

	return $subscriptions ? $subscriptions->get_memberships_from_subscription( $subscription ) : array();
}
