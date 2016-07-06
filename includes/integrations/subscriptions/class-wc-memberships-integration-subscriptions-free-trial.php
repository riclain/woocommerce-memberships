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
 * Free Trial integration class for WooCommerce Subscriptions
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Free_Trial {


	/**
	 * Enable Free Trial Memberships
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// add a free_trial membership status
		add_filter( 'wc_memberships_user_membership_statuses',                   array( $this, 'add_free_trial_status' ) );
		add_filter( 'wc_memberships_valid_membership_statuses_for_cancel',       array( $this, 'enable_cancel_for_free_trial' ) );
		add_filter( 'wc_memberships_edit_user_membership_screen_status_options', array( $this, 'edit_user_membership_screen_status_options' ), 10, 2 );
		add_filter( 'wc_memberships_bulk_edit_user_memberships_status_options',  array( $this, 'remove_free_trial_from_bulk_edit' ) );
	}


	/**
	 * Add free trial status to membership statuses
	 *
	 * @since 1.6.0
	 * @param array $statuses Array of statuses
	 * @return array Modified array of statuses
	 */
	public function add_free_trial_status( $statuses ) {

		$statuses = SV_WC_Helper::array_insert_after( $statuses, 'wcm-active', array(
			'wcm-free_trial' => array(
				'label'       => _x( 'Free Trial', 'Membership Status', 'woocommerce-memberships' ),
				'label_count' => _n_noop( 'Free Trial <span class="count">(%s)</span>', 'Free Trial <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			)
		) );

		return $statuses;
	}


	/**
	 * Add free trial status to valid statuses for membership cancellation
	 *
	 * @since 1.6.0
	 * @param array $statuses Array of status slugs
	 * @return array modified status slugs
	 */
	public function enable_cancel_for_free_trial( $statuses ) {

		$statuses[] = 'free_trial';
		return $statuses;
	}


	/**
	 * Remove free trial status from status options, unless the membership
	 * actually is on free trial.
	 *
	 * @since 1.6.0
	 * @param array $statuses Array of status options
	 * @param int $user_membership_id User Membership ID
	 * @return array Modified array of status options
	 */
	public function edit_user_membership_screen_status_options( $statuses, $user_membership_id ) {

		$user_membership = wc_memberships_get_user_membership( $user_membership_id );

		if ( 'free_trial' !== $user_membership->get_status() ) {
			unset( $statuses['wcm-free_trial'] );
		}

		return $statuses;
	}


	/**
	 * Remove free trial from bulk edit status options
	 *
	 * @since 1.6.0
	 * @param array $statuses Array of statuses
	 * @return array Modified array of statuses
	 */
	public function remove_free_trial_from_bulk_edit( $statuses ) {

		unset( $statuses['wcm-free_trial'] );
		return $statuses;
	}


}
