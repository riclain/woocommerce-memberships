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
 * Integration class for WooCommerce Subscriptions < 2.0.0
 *
 * TODO Remove this class entirely when we drop support for Subscriptions 1.5 {FN 2016-04-26}
 *
 * @deprecated
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_1_5 extends WC_Memberships_Integration_Subscriptions {


	/**
	 * Constructor
	 *
	 * @deprecated
	 * @since 1.6.0
	 */
	public function __construct() {

		$this->subscription_meta_key_name = '_subscription_key';

		parent::__construct();

		// Subscriptions events
		add_action( 'subscription_put_on-hold', array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'reactivated_subscription', array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'subscription_expired',     array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'cancelled_subscription',   array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'subscription_trashed',     array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'subscription_deleted',     array( $this, 'handle_subscription_status_change' ), 10, 2 );

		add_action( 'woocommerce_subscriptions_set_expiration_date', array( $this, 'update_membership_end_date' ), 10, 3 );
	}


	/**
	 * Handle subscription status change
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param int $user_id User ID
	 * @param string $subscription_key Subscription key
	 */
	public function handle_subscription_status_change( $user_id, $subscription_key ) {

		$user_memberships = $this->get_memberships_from_subscription( $subscription_key );

		if ( ! $user_memberships ) {
			return;
		}

		$note = '';

		switch ( current_filter() ) {

			case 'subscription_trashed':
				$note = __( 'Membership cancelled because subscription was trashed.', 'woocommerce-memberships' );
			break;

			case 'subscription_deleted':
				$note = __( 'Membership cancelled because subscription was deleted.', 'woocommerce-memberships' );
			break;

			default :
			break;
		}

		$subscription = WC_Subscriptions_Manager::get_subscription( $subscription_key );

		foreach ( $user_memberships as $user_membership ) {

			$this->update_related_membership_status( $subscription, $user_membership, $subscription['status'], $note );
		}
	}


	/**
	 * Update membership end date when subscription expiration date is changed
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param bool $is_set
	 * @param string $expiration_date Expiration date, as timestamp
	 * @param string $subscription_key Subscription key
	 */
	public function update_membership_end_date( $is_set, $expiration_date, $subscription_key ) {

		$user_memberships = $this->get_memberships_from_subscription( $subscription_key );

		if ( ! $user_memberships ) {
			return;
		}

		foreach ( $user_memberships as $user_membership ) {

			$plan_id = $user_membership->get_plan_id();

			if ( $plan_id && $this->plan_grants_access_while_subscription_active( $plan_id ) ) {

				$end_date = $expiration_date ? date( 'Y-m-d H:i:s', $expiration_date ) : '';

				$user_membership->set_end_date( $end_date );
			}
		}
	}


	/** Internal & helper methods ******************************************/


	/**
	 * Get a Subscription status
	 *
	 * @deprecated
	 * @since 1.5.4
	 * @param array $subscription
	 * @return string
	 */
	public function get_subscription_status( $subscription ) {
		return is_array( $subscription ) && isset( $subscription['status'] ) ? $subscription['status'] : '';
	}


	/**
	 * Get a Subscription by order_id and product_id
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param int $order_id \WC_Order id
	 * @param int $product_id \WC_Product id
	 * @return null|array Subscription array or null if not found
	 */
	public function get_order_product_subscription( $order_id, $product_id ) {

		$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $order_id, $product_id );
		$subscription     = WC_Subscriptions_Manager::get_subscription( $subscription_key );

		return $subscription;
	}


	/**
	 * Get a Subscription from a User Membership
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param int|\WC_Memberships_User_Membership $user_membership User Membership object or id
	 * @return null|array Subscription array or null, if not found
	 */
	public function get_subscription_from_membership( $user_membership ) {

		$user_membership_id = is_object( $user_membership ) ? $user_membership->id : $user_membership;
		$subscription_key   = $this->get_user_membership_subscription_key( (int) $user_membership_id );

		if ( ! $subscription_key ) {
			return null;
		}

		$user_membership = wc_memberships_get_user_membership( $user_membership_id );

		// It seems that the order has been deleted
		if ( false === get_post_status( $user_membership->get_order_id() ) ) {
			return null;
		}

		// It seems the subscription product has been removed from the order
		if ( ! WC_Subscriptions_Order::get_item_id_by_subscription_key( $subscription_key ) ) {
			return null;
		}

		return WC_Subscriptions_Manager::get_subscription( $subscription_key );
	}


	/**
	 * Get user memberships by subscription key
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param string $subscription_key Subscription key
	 * @return \WC_Memberships_User_Membership[] Array of user membership objects or null, if none found
	 */
	public function get_memberships_from_subscription( $subscription_key ) {

		$user_memberships = array();

		$user_membership_ids = new WP_Query( array(
			'post_type'        => 'wc_user_membership',
			'post_status'      => array_keys( wc_memberships_get_user_membership_statuses() ),
			'fields'           => 'ids',
			'nopaging'         => true,
			'suppress_filters' => 1,
			'meta_query'       => array(
				array(
					'key'   => '_subscription_key',
					'value' => $subscription_key,
				),
			),
		) );

		if ( ! empty( $user_membership_ids->posts ) ) {

			$user_memberships = array();

			foreach ( $user_membership_ids->posts as $user_membership_id ) {
				$user_memberships[] = wc_memberships_get_user_membership( $user_membership_id );
			}
		}

		return $user_memberships;
	}


	/**
	 * Check if order contains a Subscription
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param \WC_Order $order
	 * @return bool
	 */
	protected function order_contains_subscription( $order ) {
		return WC_Subscriptions_Order::order_contains_subscription( $order );
	}


	/**
	 * Get a Subscription renewal url for a Subscription-tied Membership
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @return string
	 */
	public function get_subscription_renewal_url( $user_membership ) {

		$subscription_key = $this->get_user_membership_subscription_key( $user_membership->get_id() );
		$url              = WC_Subscriptions_Renewal_Order::get_users_renewal_link( $subscription_key );

		return $url;
	}


	/**
	 * Check if a Subscription associated to a Membership is renewable
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription
	 * @param \WC_Memberships_User_Membership $user_Membership
	 * @return bool
	 */
	public function is_subscription_linked_to_membership_renewable( $subscription, $user_Membership ) {
		return WC_Subscriptions_Renewal_Order::can_subscription_be_renewed( $this->get_user_membership_subscription_key( $user_Membership->get_id() ), $user_Membership->get_user_id() );
	}


	/**
	 * Get a Subscription event date or time
	 *
	 * @deprecated
	 * @since 1.6.0
	 * @param array $subscription The Subscription to get the event for
	 * @param string $event The event to retrieve a date/time for
	 * @param string $format 'timestamp' for timestamp output or 'mysql' for date (default)
	 * @return int|string
	 */
	protected function get_subscription_event( $subscription, $event, $format = 'mysql' ) {

		$date = '';

		// sanity check
		if ( ! is_array( $subscription ) || empty( $subscription ) ) {
			return $date;
		}

		switch ( $event ) {

			case 'end' :
			case 'end_date' :
			case 'expiry_date' :
				$date = isset( $subscription['expiry_date'] ) ? $subscription['expiry_date'] : '';
			break;

			case 'trial_end' :
			case 'trial_end_date' :
			case 'trial_expiry_date' :
				$date = isset( $subscription['trial_expiry_date'] ) ? $subscription['trial_expiry_date']  : '';
			break;

		}

		return 'timestamp' === $format && ! empty( $date ) ? strtotime( $date ) : $date;
	}


}
