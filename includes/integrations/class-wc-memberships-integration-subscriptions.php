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
 * Integration class for WooCommerce Subscriptions
 *
 * @since 1.0.0
 */
class WC_Memberships_Integration_Subscriptions {


	/** @private array Subscription trial end date lazy storage */
	private $_subscription_trial_end_date = array();

	/** @private array Membership plan subscription check lazy storage */
	private $_has_membership_plan_subscription = array();


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Subscription events (pre 2.0)
		if ( ! SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

			add_action( 'subscription_put_on-hold', array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );
			add_action( 'reactivated_subscription', array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );
			add_action( 'subscription_expired',     array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );
			add_action( 'cancelled_subscription',   array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );
			add_action( 'subscription_trashed',     array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );
			add_action( 'subscription_deleted',     array( $this, 'handle_subscription_status_change_1_5' ), 10, 2 );

			add_action( 'woocommerce_subscriptions_set_expiration_date', array( $this, 'update_membership_end_date' ), 10, 3 );
		}

		// 2.0 Subscription events
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'handle_subscription_status_change' ), 10, 2 );
		add_action( 'woocommerce_subscription_date_updated',   array( $this, 'update_related_membership_dates' ), 10, 3 );
		add_action( 'trashed_post',                            array( $this, 'cancel_related_membership' ) );
		add_action( 'delete_post',                             array( $this, 'cancel_related_membership' ) );

		// Handle membership status change
		add_action( 'wc_memberships_user_membership_status_changed', array( $this, 'handle_user_membership_status_change' ), 10, 3 );

		// Handle subscription switches
		add_action( 'woocommerce_subscriptions_switched_item', array( $this, 'handle_subscription_switches' ), 10, 3 );

		// Access dates etc (2.0 & backwards compatible)
		add_filter( 'wc_memberships_access_from_time',  array( $this, 'adjust_post_access_from_time' ), 10, 3 );

		// Renew membership URL (2.0 & backwards compatible)
		add_filter( 'wc_memberships_get_renew_membership_url', array( $this, 'renew_membership_url' ), 10, 2 );

		// Grant membership access (2.0 & backwards compatible)
		add_filter( 'wc_memberships_renew_membership',                                   array( $this, 'renew_membership' ), 10, 3 );
		add_filter( 'wc_memberships_access_granting_purchased_product_id',               array( $this, 'adjust_access_granting_product_id' ), 10, 3 );
		add_filter( 'wc_memberships_grant_access_from_new_purchase',                     array( $this, 'maybe_grant_access_from_new_subscription' ), 10, 2 );
		add_filter( 'wc_memberships_grant_access_from_existing_purchase',                array( $this, 'maybe_grant_access_from_existing_subscription' ), 10, 2 );
		add_filter( 'wc_memberships_grant_access_from_existing_purchase_order_statuses', array( $this, 'grant_access_from_active_subscription' ) );
		add_filter( 'wc_memberships_new_membership_data',                                array( $this, 'adjust_new_membership_data' ), 10, 2 );
		add_action( 'wc_memberships_grant_membership_access_from_purchase',              array( $this, 'save_subscription_data' ), 10, 2 );

		// Add a free_trial membership status (2.0 & backwards compatible)
		add_filter( 'wc_memberships_user_membership_statuses',                   array( $this, 'add_free_trial_status' ) );
		add_filter( 'wc_memberships_valid_membership_statuses_for_cancel',       array( $this, 'enable_cancel_for_free_trial' ) );
		add_filter( 'wc_memberships_edit_user_membership_screen_status_options', array( $this, 'edit_user_membership_screen_status_options' ), 10, 2 );
		add_filter( 'wc_memberships_bulk_edit_user_memberships_status_options',  array( $this, 'remove_free_trial_from_bulk_edit' ) );

		// Apply discounts to sign up fees (2.0 & backwards compatible)
		add_filter( 'wc_memberships_products_settings',              array( $this, 'enable_discounts_to_sign_up_fees' ) );
		add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'apply_discounts_to_sign_up_fees' ), 20, 2 );

		// Frontend UI hooks (2.0 & backwards compatible, Memberships < 1.4)
		// TODO when dropping support for Memberships templates < 1.4.0 these can be removed
		add_action( 'wc_memberships_my_memberships_column_headers',     array( $this, 'output_subscription_column_headers' ) );
		add_action( 'wc_memberships_my_memberships_columns',            array( $this, 'output_subscription_columns' ), 20 );
		add_action( 'wc_memberships_my_account_my_memberships_actions', array( $this, 'my_membership_actions' ), 10, 2 );
		// Frontend UI hooks (Memberships 1.4+ & Subscriptions 2.0)
		add_filter( 'wc_memberships_members_area_my_memberships_actions',           array( $this, 'my_membership_actions' ), 10, 2 );
		// TODO when dropping support for Memberships templates < 1.4.0 these can be uncommented
		// add_filter( 'wc_memberships_my_memberships_column_names',                   array( $this, 'my_memberships_subscriptions_columns' ), 20 );
		// add_action( 'wc_memberships_my_memberships_column_membership-next-bill-on', array( $this, 'output_subscription_columns' ), 20 );

		// Admin UI hooks (2.0 & backwards compatible)
		add_action( 'wc_memberships_after_user_membership_billing_details',    array( $this, 'output_subscription_details' ) );
		add_action( 'wc_membership_plan_options_membership_plan_data_general', array( $this, 'output_subscription_options' ) );
		add_action( 'wc_memberships_restriction_rule_access_schedule_field',   array( $this, 'output_exclude_trial_option' ), 10, 2 );
		add_action( 'wc_memberships_user_membership_actions',                  array( $this, 'user_membership_meta_box_actions' ), 1, 2 );
		add_filter( 'post_row_actions',                                        array( $this, 'user_membership_post_row_actions' ), 20, 2 );

		// AJAX actions (2.0 & backwards compatible)
		add_action( 'wp_ajax_wc_memberships_membership_plan_has_subscription_product', array( $this, 'ajax_plan_has_subscription' ) );
		add_action( 'wp_ajax_wc_memberships_delete_membership_and_subscription',       array( $this, 'delete_membership_with_subscription' ) );

		// Activation/deactivation (2.0 & backwards compatible)
		add_action( 'wc_memberships_activated',              array( $this, 'handle_activation' ), 1 );
		add_action( 'woocommerce_subscriptions_activated',   array( $this, 'handle_activation' ), 1 );
		add_action( 'woocommerce_subscriptions_deactivated', array( $this, 'handle_deactivation' ) );

		add_action( 'admin_init', array( $this, 'handle_upgrade' ) );

	}


	/** Internal & helper methods ******************************************/


	/**
	 * Get subscription by order_id and product_id
	 *
	 * Compatibility method for supporting both Subscriptions 2.0
	 * and earlier
	 *
	 * @since 1.0.0
	 * @param int $order_id
	 * @param int $product_id
	 * @return array|WC_Subscription|null Subscription array (pre 2.0), object (2.0 onwards) or null if not found
	 */
	private function get_order_product_subscription( $order_id, $product_id ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array(
				'product_id' => $product_id,
			) );

			$subscription = reset( $subscriptions );

			// Find a subscription created from admin (no attached order, $order_id is from a WC_Subscription)
			if ( ! $subscription ) {
				$subscription = wcs_get_subscription( $order_id );
			}

		} else {

			$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $order_id, $product_id );
			$subscription     = WC_Subscriptions_Manager::get_subscription( $subscription_key );

		}

		return $subscription;
	}


	/**
	 * Get user memberships by subscription ID
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID
	 * @return null|WC_Memberships_User_Membership[] Array of user membership objects or null, if none found
	 */
	private function get_user_memberships_by_subscription_id( $subscription_id ) {

		$user_memberships = null;
		$subscription_key = '';

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
			$subscription_key = wcs_get_old_subscription_key( wcs_get_subscription( $subscription_id ) );
		}

		$user_membership_ids = new WP_Query( array(
			'post_type'        => 'wc_user_membership',
			'post_status'      => array_keys( wc_memberships_get_user_membership_statuses() ),
			'fields'           => 'ids',
			'nopaging'         => true,
			'suppress_filters' => 1,
			'meta_query'       => array(
				'relation' => 'OR',
				array(
					'key'   => '_subscription_id',
					'value' => $subscription_id,
					'type' => 'numeric',
				),
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

				// ensure the _subscription_id meta exists
				if ( ! metadata_exists( 'post', $user_membership_id, '_subscription_id' ) ) {
					update_post_meta( $user_membership_id, '_subscription_id', $subscription_id );
				}

				// delete the _subscription_key meta
				if ( metadata_exists( 'post', $user_membership_id, '_subscription_key' ) ) {
					delete_post_meta( $user_membership_id, '_subscription_key' );
				}
			}
		}

		return $user_memberships;
	}


	/**
	 * Get user memberships by subscription key
	 *
	 * @since 1.0.0
	 * @param string @subscription_key Subscription key
	 * @return null|WC_Memberships_User_Membership[] Array of user membership objects or null, if none found
	 */
	private function get_user_memberships_by_subscription_key( $subscription_key ) {

		$user_memberships = null;

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


	/** Subscription event methods *****************************************/


	/**
	 * Handle subscription status change (pre 2.0)
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @param string $subscription_key Subscription key
	 */
	public function handle_subscription_status_change_1_5( $user_id, $subscription_key ) {

		$user_memberships = $this->get_user_memberships_by_subscription_key( $subscription_key );

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

		}

		foreach ( $user_memberships as $user_membership ) {

			$subscription = WC_Subscriptions_Manager::get_subscription( $subscription_key );
			$this->update_related_membership_status( $subscription, $user_membership, $subscription['status'], $note );
		}

	}


	/**
	 * Handle subscription status change (2.0 and onwards)
	 *
	 * @since 1.0.0
	 * @param \WC_Subscription $subscription subscription being changed
	 * @param string $new_status statue changing to
	 */
	public function handle_subscription_status_change( WC_Subscription $subscription, $new_status ) {

		$user_memberships = $this->get_user_memberships_by_subscription_id( $subscription->id );

		if ( ! $user_memberships ) {
			return;
		}

		foreach ( $user_memberships as $user_membership ) {
			$this->update_related_membership_status( $subscription, $user_membership, $new_status );
		}
	}


	/**
	 * Update related membership status based on subscription status
	 *
	 * @since 1.0.0
	 * @param WC_Subscription $subscription
	 * @param WC_Memberships_User_Membership $user_membership
	 * @param string $status
	 * @param string $note Optional
	 */
	public function update_related_membership_status( $subscription, $user_membership, $status, $note = '' ) {

		$plan_id = $user_membership->get_plan_id();

		if ( ! $plan_id || ! $this->plan_grants_access_while_subscription_active( $plan_id ) ) {
			return;
		}

		// Uncommenting the following lines would prevent orphaned memberships from switched subscriptions
		// to have their status changed altogether with the new subscriptions switched to;
		// however it would disallow re-purchasing subscriptions to reactivate a cancelled membership
		// TODO possible fix: decouple the membership if a subscription is cancelled first
		//if ( $user_membership->has_status( 'cancelled' ) ) {
		//  return;
		//}

		switch ( $status ) {

			case 'active':

				$trial_end = '';

				if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() && $subscription instanceof WC_Subscription ) {
					$trial_end = $subscription->get_time( 'trial_end' );
				} elseif ( is_array( $subscription ) && ! empty( $subscription['trial_expiry_date'] ) ) {
					$trial_end = strtotime( $subscription['trial_expiry_date'] );
				}

				if ( $trial_end && $trial_end > current_time( 'timestamp', true ) ) {

					if ( ! $note ) {
						$note = __( 'Membership free trial activated because subscription was re-activated.', 'woocommerce-memberships' );
					}

					$user_membership->update_status( 'free_trial', $note );

				} else {

					if ( ! $note ) {
						$note = __( 'Membership activated because subscription was re-activated.', 'woocommerce-memberships' );
					}

					$user_membership->activate_membership( $note );

				}

			break;

			case 'on-hold':

				if ( ! $note ) {
					$note = __( 'Membership paused because subscription was put on-hold.', 'woocommerce-memberships' );
				}

				$user_membership->pause_membership( $note );

			break;

			case 'expired':

				if ( ! $note ) {
					$note = __( 'Membership expired because subscription expired.', 'woocommerce-memberships' );
				}

				$user_membership->update_status( 'expired', $note );

			break;

			case 'pending-cancel':

				if ( ! $note ) {
					$note = __( 'Membership marked as pending cancellation because subscription is pending cancellation.', 'woocommerce-memberships' );
				}

				$user_membership->update_status( 'pending', $note );

			break;

			case 'cancelled':

				if ( ! $note ) {
					$note = __( 'Membership cancelled because subscription was cancelled.', 'woocommerce-memberships' );
				}

				$user_membership->cancel_membership( $note );

			break;

			case 'trash':

				if ( ! $note ) {
					$note = __( 'Membership cancelled because subscription was trashed.', 'woocommerce-memberships' );
				}

				$user_membership->cancel_membership( $note );

			break;

			default:
			break;

		}
	}


	/**
	 * Update related membership upon subscription date change
	 *
	 * @since 1.0.0
	 * @param WC_Subscription $subscription
	 * @param string $date_type
	 * @param string $datetime
	 */
	public function update_related_membership_dates( WC_Subscription $subscription, $date_type, $datetime ) {

		if ( 'end' == $date_type ) {

			$user_memberships = $this->get_user_memberships_by_subscription_id( $subscription->id );

			if ( ! $user_memberships ) {
				return;
			}

			foreach ( $user_memberships as $user_membership ) {

				$plan_id = $user_membership->get_plan_id();

				if ( $plan_id && $this->plan_grants_access_while_subscription_active( $plan_id ) ) {

					update_post_meta( $user_membership->get_id(), '_end_date', $datetime ? $datetime : '' );
				}
			}

		}
	}


	/**
	 * Cancel user membership when subscription is deleted
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 */
	public function cancel_related_membership( $post_id ) {

		// Bail out if the post being deleted is not a subscription
		if ( 'shop_subscription' !== get_post_type( $post_id ) ) {
			return;
		}

		$user_memberships = $this->get_user_memberships_by_subscription_id( $post_id );

		if ( ! $user_memberships ) {
			return;
		}

		switch ( current_filter() ) {

			case 'trashed_post':
				$note = __( 'Membership cancelled because subscription was trashed.', 'woocommerce-memberships' );
			break;

			case 'delete_post':
				$note = __( 'Membership cancelled because subscription was deleted.', 'woocommerce-memberships' );
			break;

			default:
				$note = null;
			break;

		}

		foreach ( $user_memberships as $user_membership ) {
			$user_membership->cancel_membership( $note );
		}
	}


	/**
	 * Update membership end date when subscription expiration date is changed
	 *
	 * @since 1.0.0
	 * @param bool $is_set
	 * @param string $expiration_date Expiration date, as timestamp
	 * @param string $subscription_key Subscription key
	 */
	public function update_membership_end_date( $is_set, $expiration_date, $subscription_key ) {

		$user_memberships = $this->get_user_memberships_by_subscription_key( $subscription_key );

		if ( ! $user_memberships ) {
			return;
		}

		foreach ( $user_memberships as $user_membership ) {

			$plan_id = $user_membership->get_plan_id();

			if ( $plan_id && $this->plan_grants_access_while_subscription_active( $plan_id ) ) {

				update_post_meta( $user_membership->get_id(), '_end_date', $expiration_date ? date( 'Y-m-d H:i:s', $expiration_date ) : '' );
			}
		}

	}


	/**
	 * Handle user membership status changes
	 *
	 * @since 1.0.0
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @param string $old_status
	 * @param string $new_status
	 */
	public function handle_user_membership_status_change( $user_membership, $old_status, $new_status ) {

		// Save the new membership end date and remove the paused date.
		// This means that if the membership was paused, or, for example,
		// paused and then cancelled, and then re-activated, the time paused
		// will be added to the expiry date, so that the end date is pushed back.
		//
		// Note: this duplicates the behavior in core, when status is changed to 'active'
		if ( 'free_trial' == $new_status && $paused_date = $user_membership->get_paused_date() ) {

			$user_membership->set_end_date( $user_membership->get_end_date() );
			delete_post_meta( $user_membership->get_id(), '_paused_date' );
		}
	}


	/**
	 * Handle subscription upgrades/downgrades (switch).
	 * Hook is available since Subscriptions 2.0.6+ only.
	 *
	 * @since 1.3.8
	 * @param \WC_Subscription $subscription The subscription object
	 * @param array $new_order_item The new order item (switching to)
	 * @param array $old_order_item The old order item (switching from)
	 */
	public function handle_subscription_switches( $subscription, $new_order_item, $old_order_item ) {

		$user_memberships = $this->get_user_memberships_by_subscription_id( $subscription->id );

		if ( ! $user_memberships ) {
			return;
		}

		foreach ( $user_memberships as $user_membership ) {

			$old_product_id = 0;

			// grab the variation_id for variable upgrades, the product_id for grouped product upgrades
			if ( ! empty( $old_order_item['variation_id'] ) ) {

				$old_product_id = $old_order_item['variation_id'];

			} elseif ( ! empty( $old_order_item['product_id'] ) ) {

				$old_product_id = $old_order_item['product_id'];
			}

			// handle upgrades/downgrades for variable products
			if ( absint( $old_product_id ) === absint( $user_membership->get_product_id() ) ) {

				$note = __( 'Membership cancelled because subscription was switched.', 'woocommerce-memberships' );

				$user_membership->cancel_membership( $note );
			}
		}
	}


	/** General methods ****************************************************/


	/**
	 * Adjust user membership post scheduled content 'access from' time for subscription-based memberships
	 *
	 * @since 1.0.0
	 * @param string $from_time Access from time, as a timestamp
	 * @param WC_Memberships_Membership_Plan_rule $rule Related rule
	 * @param WC_Memberships_User_Membership $user_membership
	 * @return string Modified from_time, as timestamp
	 */
	public function adjust_post_access_from_time( $from_time, WC_Memberships_Membership_Plan_Rule $rule, WC_Memberships_User_Membership $user_membership ) {

		if ( 'yes' == $rule->get_access_schedule_exclude_trial() ) {

			$has_subscription = $this->has_user_membership_subscription( $user_membership->get_id() );
			$trial_end_date   = $this->get_user_membership_trial_end_date( $user_membership->get_id(), 'timestamp' );

			if ( $has_subscription && $trial_end_date ) {

				$from_time = $trial_end_date;
			}
		}

		return $from_time;
	}


	/**
	 * Adjust renew membership URL for subscription-based memberships
	 *
	 * @since 1.0.0
	 * @param string $url Renew membership URL
	 * @param WC_Memberships_User_Membership $user_membership
	 * @return string Modified renew URL
	 */
	public function renew_membership_url( $url, WC_Memberships_User_Membership $user_membership ) {

		if ( $this->has_membership_plan_subscription( $user_membership->get_plan_id() ) ) {

			// note that we must also check if order contains a subscription since users
			// can be manually-assigned to memberships and not have an associated subscription

			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

				if ( wcs_order_contains_subscription( $user_membership->get_order() ) ) {
					$url = wcs_get_users_resubscribe_link( $this->get_user_membership_subscription_id( $user_membership->get_id() ) );
				}

			} else {

				if ( WC_Subscriptions_Order::order_contains_subscription( $user_membership->get_order() ) ) {
					$subscription_key = $this->get_user_membership_subscription_key( $user_membership->get_id() );
					$url              = WC_Subscriptions_Renewal_Order::get_users_renewal_link( $subscription_key );
				}
			}
		}

		return $url;
	}


	/**
	 * Adjust whether a membership should be renewed or not
	 *
	 * @since 1.0.0
	 * @param bool $renew
	 * @param WC_Memberships_Membership_Plan $plan
	 * @param array $args
	 * @return bool
	 */
	public function renew_membership( $renew, $plan, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return $renew;
		}

		// Disable renewing via a re-purchase of a subscription product
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

			if ( $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

				$renew = false;
			}
		}

		return $renew;
	}


	/**
	 * Adjust the product ID that grants access to a membership plan on purchase
	 *
	 * Subscription products take priority over all other products
	 *
	 * @since 1.0.0
	 * @param int $product_id Product ID
	 * @param array $access_granting_product_ids Array of product IDs in the purchase order
	 * @param WC_Memberships_Membership_Plan $plan Membership Plan to access
	 * @return int ID of the Subscription product that grants access,
	 *             if multiple IDs are in a purchase order, the one that grants longest membership access is used
	 */
	public function adjust_access_granting_product_id( $product_id, $access_granting_product_ids, WC_Memberships_Membership_Plan $plan ) {

		// Check if more than one products may grant access, and if the plan even
		// allows access while subscription is active
		if ( count( $access_granting_product_ids ) > 1 && $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

			// First, find all subscription products that grant access
			$access_granting_subscription_product_ids = array();

			foreach ( $access_granting_product_ids as $_product_id ) {

				$product = wc_get_product( $_product_id );

				if ( ! $product ) {
					continue;
				}

				if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {
					$access_granting_subscription_product_ids[] = $_product_id;
				}
			}

			// If there are any, decide which one actually gets to grant access
			if ( ! empty( $access_granting_subscription_product_ids ) ) {

				if ( count( $access_granting_subscription_product_ids ) == 1 ) {

					// Only one subscription grants access, short-circuit it as the winner
					$product_id = $access_granting_subscription_product_ids[0];

				} else {

					// Multiple subscriptions grant access, let's select the most
					// gracious one - whichever gives access for a longer period, wins.
					$longest_expiration_date = 0;

					foreach ( $access_granting_subscription_product_ids as $_subscription_product_id ) {

						$expiration_date = WC_Subscriptions_Product::get_expiration_date( $_subscription_product_id );

						// No expiration date always means the longest period
						if ( ! $expiration_date ) {
							$product_id = $_subscription_product_id;
							break;
						}

						// The current Subscription has a longer expiration date than the previous one in the loop
						if ( strtotime( $expiration_date ) > $longest_expiration_date ) {
							$product_id              = $_subscription_product_id;
							$longest_expiration_date = strtotime( $expiration_date );
						}
					}
				}

			}
		}

		return $product_id;
	}


	/**
	 * Only grant access to new subscriptions
	 * if they're not a subscription renewal
	 *
	 * @since 1.3.5
	 * @param bool $grant_access
	 * @param array $args
	 * @return bool
	 */
	public function maybe_grant_access_from_new_subscription( $grant_access, $args ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() && wcs_order_contains_renewal( $args['order_id'] ) ) {

			// Subscription renewals cannot grant access
			$grant_access = false;

		} elseif( isset( $args['order_id'] ) && isset( $args['product_id'] ) && isset( $args['user_id'] ) ) {

			// Reactivate a cancelled/pending cancel User Membership
			// when re-purchasing the same Subscription that grants access

			$product = wc_get_product( $args['product_id'] );

			if ( $product && $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

				$user_id = $args['user_id'];
				$order   = wc_get_order( $args['order_id'] );
				$plans   = wc_memberships()->plans->get_membership_plans();

				// Loop over all available membership plans
				foreach ( $plans as $plan ) {

					// Skip if no products grant access to this plan
					if ( ! $plan->has_products() ) {
						continue;
					}

					$access_granting_product_ids = wc_memberships()->get_access_granting_purchased_product_ids( $plan, $order );

					foreach ( $access_granting_product_ids as $access_granting_product_id ) {

						// Sanity check: make sure the selected product ID in fact does grant access
						if ( ! $plan->has_product( $access_granting_product_id ) ) {
							continue;
						}

						if ( $product->id == $access_granting_product_id ) {

							$user_membership = wc_memberships_get_user_membership( $user_id, $plan );

							// Check if the user purchasing is already member of a plan but the membership is cancelled or pending cancellation

							if ( wc_memberships_is_user_member( $user_id, $plan ) && $user_membership->has_status( array( 'pending-cancel', 'pending', 'cancelled' ) ) ) {

								// Reactivate the User Membership

								$note = sprintf( /* translators: Placeholders: %1$s is the subscription product name, %2%s is the order number. */
									__( 'Membership re-activated due to subscription re-purchase (%1$s, Order %2$s).', 'woocommerce-memberships' ),
									$product->get_title(),
									'<a href="' . admin_url( 'post.php?post=' . $order->id . '&action=edit' ) .'" >' . $order->id . '</a>'
								);
								$user_membership->activate_membership( $note );

								// Update the User Membership with the new Subscription meta

								if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
									$subscription = $this->get_order_product_subscription( $order->id, $product->id );
									update_post_meta( $user_membership->id, '_subscription_id', $subscription->id );
								} else {
									$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $args['order_id'], $product->id );
									update_post_meta( $user_membership->id, '_subscription_key', $subscription_key );
								}

							}
						}
					}
				}
			}
		}

		return $grant_access;
	}


	/**
	 * Only grant access from existing subscription if it's active
	 *
	 * @since 1.0.0
	 * @param bool $grant_access
	 * @param array $args
	 * @return bool
	 */
	public function maybe_grant_access_from_existing_subscription( $grant_access, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return $grant_access;
		}

		// handle access from subscriptions
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

			$subscription = $this->get_order_product_subscription( $args['order_id'], $product->id );

			// handle deleted subscriptions
			if ( ! is_array( $subscription ) && ! $subscription instanceof WC_Subscription ) {
				return false;
			}

			$status = is_array( $subscription ) ? $subscription['status'] : $subscription->get_status();

			if ( 'active' !== $status ) {
				$grant_access = false;
			}
		}

		return $grant_access;
	}


	/**
	 * Add 'active' to valid order statuses for granting membership access
	 *
	 * Filters wc_memberships_grant_access_from_existing_purchase_order_statuses
	 *
	 * @since 1.3.8
	 * @param array $statuses
	 * @return array
	 */
	public function grant_access_from_active_subscription( $statuses ) {
		return array_merge( $statuses, array( 'active' ) );
	}


	/**
	 * Adjust new membership data
	 *
	 * Sets the end date to match subscription end date
	 *
	 * @since 1.0.0
	 * @param array $data Original membership data
	 * @param array $args Array of arguments
	 * @return array Modified membership data
	 */
	public function adjust_new_membership_data( $data, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return $data;
		}

		// Handle access from subscriptions
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

			if ( $subscription = $this->get_order_product_subscription( $args['order_id'], $product->id ) ) {

				$trial_end = '';

				if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
					$trial_end = $subscription->get_time( 'trial_end' );
				} elseif ( is_array( $subscription ) && ! empty( $subscription['trial_expiry_date'] ) )  {
					$trial_end = strtotime( $subscription['trial_expiry_date'] );
				}

				if ( $trial_end && $trial_end > current_time( 'timestamp', true ) ) {
					$data['post_status'] = 'wcm-free_trial';
				}
			}
		}

		return $data;
	}


	/**
	 * Save related subscription data when a membership access is granted via a purchase
	 *
	 * Sets the end date to match subscription end date
	 *
	 * @since 1.0.0
	 * @param WC_Memberships_Membership_Plan $plan
	 * @param array $args
	 */
	public function save_subscription_data( WC_Memberships_Membership_Plan $plan, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return;
		}

		// Handle access from subscriptions
		if ( $this->has_membership_plan_subscription( $plan->get_id() ) && $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

			// note: always use the product ID (not variation ID) when looking up a subscription
			// as Subs requires it
			$subscription = $this->get_order_product_subscription( $args['order_id'], $product->id );

			if ( ! $subscription ) {
				return;
			}

			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

				// Save related subscription ID
				update_post_meta( $args['user_membership_id'], '_subscription_id', $subscription->id );

			} else {

				// Save related subscription key
				$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $args['order_id'], $product->id );
				update_post_meta( $args['user_membership_id'], '_subscription_key', $subscription_key );
			}

			$end_date = '';

			// Set membership expiry date based on subscription expiry date
			if ( $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

				if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() && $get_date = $subscription->get_date( 'end' ) ) {
					$end_date = ! empty( $get_date ) ? $get_date : '';
				} elseif ( is_array( $subscription ) && ! empty( $subscription['expiry_date'] ) ) {
					$end_date = $subscription['expiry_date'];
				}
			}

			update_post_meta( $args['user_membership_id'], '_end_date', $end_date );
		}
	}


	/**
	 * Get subscription key for a membership
	 *
	 * @since 1.0.0
	 * @param int $user_membership_id User Membership ID
	 * @return string|null Subscription key
	 */
	public function get_user_membership_subscription_key( $user_membership_id ) {
		return get_post_meta( $user_membership_id, '_subscription_key', true );
	}


	/**
	 * Get subscription ID for a membership
	 *
	 * @since 1.0.0
	 * @param int $user_membership_id User Membership ID
	 * @return string|null Subscription key
	 */
	public function get_user_membership_subscription_id( $user_membership_id ) {
		return get_post_meta( $user_membership_id, '_subscription_id', true );
	}


	/**
	 * Get the subscription for a membership
	 *
	 * @since 1.0.0
	 * @param int $user_membership_id User Membership ID
	 * @return null|WC_Subscription Subscription or null, if not found
	 */
	public function get_user_membership_subscription( $user_membership_id ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

			$subscription_id = $this->get_user_membership_subscription_id( $user_membership_id );

			if ( ! $subscription_id ) {
				return null;
			}

			return wcs_get_subscription( $subscription_id );

		} else {

			$subscription_key = $this->get_user_membership_subscription_key( $user_membership_id );

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
	}


	/**
	 * Check if membership is tied to a subscription
	 *
	 * @since 1.0.0
	 * @param int $user_membership_id User Membership ID
	 * @return bool True, if has subscription, false otherwise
	 */
	public function has_user_membership_subscription( $user_membership_id ) {

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
			return (bool) $this->get_user_membership_subscription_id( $user_membership_id );
		} else {
			return (bool) $this->get_user_membership_subscription_key( $user_membership_id );
		}
	}


	/**
	 * Get the membership subscription trial end datetime
	 *
	 * May return null if the membership is not tied to a subscription
	 *
	 * @since 1.0.0
	 * @param int $user_membership_id User Membership ID
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return string|null Returns the trial end date or null
	 */
	public function get_user_membership_trial_end_date( $user_membership_id, $format = 'mysql' ) {

		if ( ! isset( $this->_subscription_trial_end_date[ $user_membership_id ] ) ) {

			if ( ! $this->has_user_membership_subscription( $user_membership_id ) ) {
				return null;
			}

			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {

				$subscription = $this->get_user_membership_subscription( $user_membership_id );
				$this->_subscription_trial_end_date[ $user_membership_id ] = 'mysql' == $format
					? $subscription->get_date( 'trial_end' )
					: $subscription->get_time( 'trial_end' );

			} else {

				$subscription_key = $this->get_user_membership_subscription_key( $user_membership_id );
				$this->_subscription_trial_end_date[ $user_membership_id ] = WC_Subscriptions_Manager::get_trial_expiration_date( $subscription_key, null, $format );

			}
		}

		return $this->_subscription_trial_end_date[ $user_membership_id ];
	}


	/**
	 * Check if the membership plan has at least one subscription product that grants access
	 *
	 * @since 1.0.0
	 * @param int $plan_id Membership Plan ID
	 * @return bool True, if has a subscription product, false otherwise
	 */
	public function has_membership_plan_subscription( $plan_id ) {

		if ( ! isset( $this->_has_membership_plan_subscription[ $plan_id ] ) ) {

			$plan = wc_memberships_get_membership_plan( $plan_id );

			// Sanity check
			if ( ! $plan ) {
				return false;
			}

			$product_ids = $plan->get_product_ids();
			$product_ids = ! empty( $product_ids ) ? array_map( 'absint',  $product_ids ) : null;

			$this->_has_membership_plan_subscription[ $plan_id ] = false;

			if ( ! empty( $product_ids ) ) {

				foreach ( $product_ids as $product_id ) {

					if ( ! is_numeric( $product_id ) || ! $product_id ) {
						continue;
					}

					$product = wc_get_product( $product_id );

					if ( ! $product ) {
						continue;
					}

					if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {
						$this->_has_membership_plan_subscription[ $plan_id ] = true;
						break;
					}
				}
			}
		}

		return $this->_has_membership_plan_subscription[ $plan_id ];
	}


	/**
	 * Does a membership plan allow access while subscription is active?
	 *
	 * @since 1.0.0
	 * @param int $plan_id Membership Plan ID
	 * @return bool True, if access is allowed, false otherwise
	 */
	public function plan_grants_access_while_subscription_active( $plan_id ) {

		/**
		 * Filter whether a plan grants access to a membership while subscription is active
		 *
		 * @since 1.0.0
		 * @param bool $grants_access Default: true
		 * @param int $plan_id Membership Plan ID
		 */
		return apply_filters( 'wc_memberships_plan_grants_access_while_subscription_active', true, $plan_id );
	}


	/** Membership status hooks ********************************************/


	/**
	 * Add free trial status to membership statuses
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @param array $statuses Array of status options
	 * @param int $user_membership_id User Membership ID
	 * @return array Modified array of status options
	 */
	public function edit_user_membership_screen_status_options( $statuses, $user_membership_id ) {

		$user_membership = wc_memberships_get_user_membership( $user_membership_id );

		if ( 'free_trial' != $user_membership->get_status() ) {
			unset( $statuses['wcm-free_trial'] );
		}

		return $statuses;
	}


	/**
	 * Remove free trial from bulk edit status options
	 *
	 * @since 1.0.0
	 * @param array $statuses Array of statuses
	 * @return array Modified array of statuses
	 */
	public function remove_free_trial_from_bulk_edit( $statuses ) {

		unset( $statuses['wcm-free_trial'] );
		return $statuses;
	}


	/** UI-affecting methods ***********************************************/


	/**
	 * Display subscription details in edit membership screen
	 *
	 * @since 1.0.0
	 * @param WC_Memberships_User_Membership $user_membership
	 */
	public function output_subscription_details( WC_Memberships_User_Membership $user_membership ) {

		$subscription = $this->get_user_membership_subscription( $user_membership->get_id() );

		if ( ! $subscription ) {
			return;
		}

		if ( ! SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
			$subscription_key = $this->get_user_membership_subscription_key( $user_membership->get_id() );
		}

		if ( in_array( $user_membership->get_status(), array( 'free_trial', 'active' ) ) ) {
			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				$next_payment = $subscription->get_time( 'next_payment' );
			} else {
				$next_payment = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_membership->get_user_id(), 'timestamp' );
			}
		} else {
			$next_payment = null;
		}

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
			$subscription_link      = get_edit_post_link( $subscription->id );
			$subscription_link_text = $subscription->id;
			$subscription_expires   = $subscription->get_date_to_display( 'end' );
		} else {
			$subscription_link      = esc_url( admin_url( 'admin.php?page=subscriptions&s=' . $subscription['order_id'] ) );
			$subscription_link_text = $subscription_key;
			// note: subs 1.5.x doesn't account for the site timezone
			$subscription_expires   = $subscription['expiry_date']
				? date_i18n( wc_date_format(), strtotime( $subscription['expiry_date'] ) )
				: __( 'Subscription not yet ended', 'woocommerce-memberships' );
		}

		?>
		<table>
			<tr>
				<td><?php esc_html_e( 'Subscription:', 'woocommerce-memberships' ); ?></td>
				<td><a href="<?php echo $subscription_link ?>"><?php echo $subscription_link_text; ?></a></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Next Bill On:', 'woocommerce-memberships' ); ?></td>
				<td><?php echo $next_payment ? date_i18n( wc_date_format(), $next_payment ) : esc_html__( 'N/A', 'woocommerce-memberships' ); ?></td>
			</tr>
		</table>
		<?php

		$plan_id = $user_membership->get_plan_id();

		if ( ! $plan_id || ! $this->plan_grants_access_while_subscription_active( $plan_id ) ) {
			return;
		}

		// replace the expiration date input
		wc_enqueue_js( '
			$( "._end_date_field" ).find( ".js-user-membership-date, .ui-datepicker-trigger, .description" ).hide();
			$( "._end_date_field" ).append( "<span>' . esc_html( $subscription_expires ) . '</span>" );
		' );
	}


	/**
	 * Display subscription column headers in my memberships section
	 * Memberships < 1.4.0
	 *
	 * @since 1.0.0
	 */
	public function output_subscription_column_headers() {

		?>
		<th class="membership-next-bill-on">
			<span class="nobr"><?php esc_html_e( 'Next Bill On', 'woocommerce-memberships' ); ?></span>
		</th>
		<?php
	}


	/**
	 * Add subscription column headers in My Memberships on My Account page
	 * Memberships 1.4.0+
	 *
	 * @since 1.4.1
	 * @param array $columns
	 * @return array
	 */
	public function my_memberships_subscriptions_columns( $columns ) {

		// Insert before the 'Actions' column
		array_splice( $columns, -1, 0, array(
			'membership-next-bill-on' => __( 'Next Bill On', 'woocommerce-memberships' ),
		) );

		return $columns;
	}


	/**
	 * Display subscription columns in my memberships section
	 *
	 * @since 1.0.0
	 * @param WC_Memberships_User_Membership $user_membership
	 */
	public function output_subscription_columns( WC_Memberships_User_Membership $user_membership ) {

		$subscription = $this->get_user_membership_subscription( $user_membership->get_id() );

		if ( $subscription && in_array( $user_membership->get_status(), array( 'active', 'free_trial' ) ) ) {
			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				$next_payment = $subscription->get_time( 'next_payment' );
			} else {
				$subscription_key = $this->get_user_membership_subscription_key( $user_membership->get_id() );
				$next_payment     = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_membership->get_user_id(), 'timestamp' );
			}
		}

		?>
		<td class="membership-membership-next-bill-on" data-title="<?php esc_attr_e( 'Next Bill On', 'woocommerce-memberships' ); ?>">
			<?php if ( $subscription && ! empty( $next_payment ) ) : ?>
				<?php echo date_i18n( wc_date_format(), $next_payment ) ?>
			<?php else : ?>
				<?php esc_html_e( 'N/A', 'woocommerce-memberships' ); ?>
			<?php endif; ?>
		</td>
		<?php
	}


	/**
	 * Remove cancel action from memberships tied to a subscription
	 *
	 * @since 1.3.0
	 * @param array $actions
	 * @param WC_Memberships_User_Membership $membership
	 * @return array
	 */
	public function my_membership_actions( $actions, WC_Memberships_User_Membership $membership ) {

		if ( $this->membership_has_subscription( $membership ) ) {

			// memberships tied to a subscription can only be canceled by canceling the associated subscription
			unset( $actions['cancel'] );

			$subscription = $this->get_user_membership_subscription( $membership->get_id() );

			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				$renewable = wcs_can_user_resubscribe_to( $subscription, $membership->get_user_id() );
			} else {
				$renewable = WC_Subscriptions_Renewal_Order::can_subscription_be_renewed( $this->get_user_membership_subscription_key( $membership->get_id() ), $membership->get_user_id() );
			}

			if ( ! $renewable ) {
				unset( $actions['renew'] );
			}

		}

		return $actions;
	}


	/**
	 * User membership admin post row actions
	 *
	 * Filters the post row actions in the user memberships edit screen
	 *
	 * @since 1.4.0
	 * @param array $actions
	 * @param WP_Post $post
	 * @return array $actions
	 */
	public function user_membership_post_row_actions( $actions, $post ) {

		if ( current_user_can( 'delete_post', $post ) ) {

			$user_membership = wc_memberships_get_user_membership( $post );

			if ( $this->membership_has_subscription( $user_membership ) ) {

				$subscription = $this->get_user_membership_subscription( $user_membership->get_id() );

				if ( $subscription instanceof WC_Subscription ) {

					$actions['delete-with-subscription'] = '<a class="delete-membership-and-subscription" title="' . esc_attr__( 'Delete this membership permanently and the subscription associated with it', 'woocommerce-memberships' ) . '" href="#" data-user-membership-id="' . esc_attr( $user_membership->get_id() ) . '" data-subscription-id="' . esc_attr( $subscription->id ) . '">' . esc_html__( 'Delete with subscription', 'woocommerce-memberships' ) . '</a>';
				}
			}
		}

		return $actions;
	}


	/**
	 * User membership meta box actions
	 *
	 * Filters the user membership meta box actions in admin
	 *
	 * @since 1.4.0
	 * @param array $actions
	 * @param int $user_membership_id
	 * @return array $actions
	 */
	public function user_membership_meta_box_actions( $actions, $user_membership_id ) {

		if ( current_user_can( 'delete_post', $user_membership_id ) ) {

			$subscription = $this->get_user_membership_subscription( $user_membership_id );

			if ( $subscription instanceof WC_Subscription ) {

				$actions = array_merge( array(
					'delete-with-subscription' => array(
						'class'             => 'submitdelete delete-membership-and-subscription',
						'link'              => '#',
						'text'              => __( 'Delete User Membership with Subscription', 'woocommerce-memberships' ),
						'custom_attributes' => array(
							'data-user-membership-id' => $user_membership_id,
							'data-subscription-id'    => $subscription->id,
							'data-tip'                => __( 'Delete this membership permanently and the subscription associated with it', 'woocommerce-memberships' ),
						),
					),
				), $actions );

			}
		}

		return $actions;
	}


	/**
	 * Display subscriptions options and JS in the membership plan edit screen
	 *
	 * @since 1.0.0
	 */
	public function output_subscription_options() {
		global $post;

		$has_subscription = $this->has_membership_plan_subscription( $post->ID );

		?>

		<?php if ( $this->plan_grants_access_while_subscription_active( $post->ID ) ) : ?>

			<p class="subscription-access-notice <?php if ( ! $has_subscription ) : ?>hide<?php endif; ?> js-show-if-has-subscription">
				<span class="description"><?php esc_html_e( 'Membership will be active while the purchased subscription is active.', 'woocommerce-memberships' ); ?></span>
				<?php echo SV_WC_Plugin_Compatibility::wc_help_tip( __( 'If membership access is granted via the purchase of a subscription, then membership length will be automatically equal to the length of the subscription, regardless of the membership length setting above.', 'woocommerce-memberships' ) ); ?>
			</p>

			<style type="text/css">
				.subscription-access-notice .description {
					margin-left: 150px;
				}
			</style>

		<?php endif; ?>

		<?php
		/**
		 * Check if a membership plan has subscription(s)
		 *
		 * Check if the current membership plan has at least one subscription product that grants access.
		 * If so, enable the subscription-specific controls.
		 *
		 * @since 1.0.0
		 */
		wc_enqueue_js('
			var checkIfPlanHasSubscription = function() {

				var product_ids = $("#_product_ids").val() || [];
				product_ids = $.isArray( product_ids ) ? product_ids : product_ids.split(",");

				$.get( wc_memberships_admin.ajax_url, {
					action:      "wc_memberships_membership_plan_has_subscription_product",
					security:    "' . wp_create_nonce( "check-subscriptions" ) . '",
					product_ids: product_ids,
				}, function (subscription_products) {

					var action = subscription_products && subscription_products.length ? "removeClass" : "addClass"
					$(".js-show-if-has-subscription")[ action ]("hide");

					if ( subscription_products && subscription_products.length === product_ids.length ) {
						$("#_access_length_period").closest(".form-field").hide();
					} else {
						$("#_access_length_period").closest(".form-field").show();
					}

				});
			}

			checkIfPlanHasSubscription();

			// Purely cosmetic improvement
			$(".subscription-access-notice").appendTo( $( "#_access_length_period" ).closest( ".options_group" ) );

			$("#_product_ids").on( "change", function() {
				checkIfPlanHasSubscription();
			});
		');

	}


	/**
	 * Display subscriptions options for a restriction rule
	 *
	 * This method will be called both in the membership plan screen
	 * as well as on any individual product screens.
	 *
	 * @since 1.0.0
	 * @param \WC_Memberships_Membership_Plan_Rule $rule
	 * @param string $index
	 */
	public function output_exclude_trial_option( $rule, $index ) {

		$has_subscription = $rule->get_membership_plan_id() ? $this->has_membership_plan_subscription( $rule->get_membership_plan_id() ): false;
		$type             = $rule->get_rule_type();
		?>

		<span class="rule-control-group rule-control-group-access-schedule-trial <?php if ( ! $has_subscription ) : ?>hide<?php endif; ?> js-show-if-has-subscription">

			<input type="checkbox"
			       name="_<?php echo esc_attr( $type ); ?>_rules[<?php echo $index; ?>][access_schedule_exclude_trial]"
			       id="_<?php echo esc_attr( $type ); ?>_rules_<?php echo $index; ?>_access_schedule_exclude_trial"
			       value="yes" <?php checked( $rule->get_access_schedule_exclude_trial(), 'yes' ); ?>
			       class="access_schedule-exclude-trial"
			       <?php if ( ! $rule->current_user_can_edit() ) : ?>disabled<?php endif; ?> />

			<label for="_<?php echo esc_attr( $type ); ?>_rules_<?php echo $index; ?>_access_schedule_exclude_trial" class="label-checkbox">
				<?php esc_html_e( 'Start after trial', 'woocommerce-memberships' ); ?>
			</label>

		</span>
		<?php
	}


	/**
	 * Add option to product settings
	 *
	 * Filters product settings fields and add a checkbox
	 * to let user choose to enable discounts for subscriptions sign up fees
	 *
	 * @since 1.4.0
	 * @param $product_settings
	 * @return array $product_settings
	 */
	public function enable_discounts_to_sign_up_fees( $product_settings ) {

		$new_option = array(
			array(
				'type'     => 'checkbox',
				'id'       => 'wc_memberships_enable_subscriptions_sign_up_fees_discounts',
				'name'     => __( 'Discounts apply to subscriptions sign up fees', 'woocommerce-memberships' ),
				'desc'     => __( 'If enabled, membership discounts will also apply to sign up fees of subscription products.', 'woocommerce-memberships' ),
				'default'  => 'no',
			),
		);

		array_splice( $product_settings, 2, 0, $new_option );

		return $product_settings;
	}


	/**
	 * Display the original sign up fee amount before discount
	 *
	 * Utility method to prevent discounting the original sign up fee price
	 *
	 * @see apply_discounts_to_sign_up_fees
	 * @see WC_Memberships_Member_Discounts::enable_price_adjustments
	 * @see WC_Memberships_Member_Discounts::disable_price_adjustments
	 *
	 * @since 1.4.0
	 * @param float|string $original_sign_up_fee
	 * @return float|string Unfiltered sign up fee
	 */
	public function display_original_sign_up_fees( $original_sign_up_fee ) {
		return $original_sign_up_fee;
	}


	/**
	 * Apply member discounts to subscription product sign up fee as well
	 *
	 * @see enable_discounts_to_sign_up_fees
	 * @see display_original_sign_up_fees
	 *
	 * @since 1.4.0
	 * @param float|string $subscription_sign_up_fee Sign up fee
	 * @param false|WC_Product $subscription_product A Subscription product
	 * @return float|string Sign up fee (perhaps discounted) value
	 */
	public function apply_discounts_to_sign_up_fees( $subscription_sign_up_fee, $subscription_product ) {

		// Not a subscription or no sign up fee
		if ( ! isset( $subscription_product->subscription_sign_up_fee ) || 0 === $subscription_sign_up_fee ) {

			return $subscription_sign_up_fee;
		}

		// Apply discounts to sign up fees option enabled
		if ( 'yes' == get_option( 'wc_memberships_enable_subscriptions_sign_up_fees_discounts' ) ) {

			$product = wc_get_product( $subscription_product );

			if ( $product && ! has_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'display_original_sign_up_fees' ) ) ) {

				// get_discounted_price method would otherwise return the Subscription product discounted price
				add_filter( 'wc_memberships_cache_discounted_price', '__return_false' );

				$discounted_fee = wc_memberships()->member_discounts->get_discounted_price( $subscription_sign_up_fee, $product );

				return ! is_null( $discounted_fee ) ? $discounted_fee : $subscription_sign_up_fee;
			}
		}

		return $subscription_sign_up_fee;
	}


	/** AJAX methods *******************************************************/


	/**
	 * Check if a plan has a subscription product
	 *
	 * Responds with an array of subscription products, if any.
	 *
	 * @since 1.0.0
	 */
	public function ajax_plan_has_subscription() {

		check_ajax_referer( 'check-subscriptions', 'security' );

		$product_ids = isset( $_REQUEST['product_ids'] ) && is_array( $_REQUEST['product_ids'] ) ? array_map( 'absint', $_REQUEST['product_ids'] ) : null;

		if ( empty( $product_ids ) ) {
			die();
		}

		$subscription_products = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( array( 'subscription', 'variable-subscription', 'subscription_variation' ) ) ) {
				$subscription_products[] = $product->id;
			}
		}

		wp_send_json( $subscription_products );
	}


	/**
	 * Delete a membership with its associated subscription
	 *
	 * Ajax callback to delete both a membership and a subscription
	 * from the user memberships admin edit screen
	 *
	 * @see my_membership_actions
	 */
	public function delete_membership_with_subscription() {

		check_ajax_referer( 'delete-user-membership-with-subscription', 'security' );

		if ( isset( $_POST['user_membership_id'] ) && isset( $_POST['subscription_id'] ) ) {

			$user_membership_id = intval( $_POST['user_membership_id'] );
			$user_membership    = wc_memberships_get_user_membership( $user_membership_id );
			$subscription_id    = intval( $_POST['subscription_id'] );
			$subscription       = $this->get_user_membership_subscription( $user_membership->id );

			if ( $subscription instanceof WC_Subscription && ( $subscription_id === intval( $subscription->id ) ) ) {

				$results = array(
					'delete-subscription'    => wp_delete_post( $subscription_id ),
					'delete-user-membership' => wp_delete_post( $user_membership_id ),
				);

				wp_send_json_success( $results );
			}
		}

		die();
	}


	/** Lifecycle methods **************************************************/


	/**
	 * Re-activate subscription-based memberships
	 *
	 * Find any memberships that are paused, and may need to be
	 * re-activated/put back on trial
	 *
	 * @since 1.0.0
	 */
	public function update_subscription_memberships() {

		$args = array(
			'post_type'    => 'wc_user_membership',
			'nopaging'     => true,
			'post_status'  => 'any',
			'meta_key'     => SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0()
				? '_subscription_id'
				: '_subscription_key',
			'meta_value'   => '0',
			'meta_compare' => '>',
		);

		$posts = get_posts( $args );

		// Bail out if there are no memberships to work with
		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$user_membership = wc_memberships_get_user_membership( $post );

			// Get the related subscription
			$subscription = $this->get_user_membership_subscription( $user_membership->get_id() );

			if ( ! $subscription ) {
				continue;
			}

			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				$subscription_status = $subscription->get_status();
			} else {
				$subscription_status = $subscription['status'];
			}

			// Statuses do not match, update
			if ( $subscription_status != $user_membership->get_status() ) {

				// Special handling for paused memberships
				if ( 'paused' == $user_membership->get_status() ) {

					$trial_end = '';

					// Get trial end date
					if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
						$trial_end = $subscription->get_time( 'trial_end' );
					} elseif ( is_array( $subscription ) && ! empty( $subscription['trial_expiry_date'] ) ) {
						$trial_end = strtotime( $subscription['trial_expiry_date'] );
					}

					// If there is no trial end date, activate the membership
					if ( ! $trial_end || current_time( 'timestamp', true ) >= strtotime( $trial_end ) ) {
						$user_membership->activate_membership( __( 'Membership activated because WooCommerce Subscriptions was activated.', 'woocommerce-memberships' ) );
					// Otherwise, put it on free trial
					} else {
						$user_membership->update_status( 'free_trial', __( 'Membership free trial activated because WooCommerce Subscriptions was activated.', 'woocommerce-memberships' ) );
					}

				// All other statuses: simply update the membership status
				} else {

					$this->update_related_membership_status( $subscription, $user_membership, $subscription_status );
				}
			}

			$end_date = '';

			// Get the subscription end date
			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				if ( $end_date_meta = $subscription->get_date( 'end' ) ) {
					$end_date = $end_date_meta;
				}
			} elseif ( is_array( $subscription ) && ! empty( $subscription['expiry_date'] ) ) {
				$end_date = $subscription['expiry_date'];
			}

			// End date has changed
			if ( strtotime( $end_date ) != $user_membership->get_end_date( 'timestamp' ) ) {
				update_post_meta( $user_membership->get_id(), '_end_date', $end_date );
			}
		}
	}


	/**
	 * Pause subscription-based memberships
	 *
	 * Find any memberships that are on free trial and pause them
	 *
	 * @since 1.0.0
	 */
	public function pause_free_trial_subscription_memberships() {

		$args = array(
			'post_type'   => 'wc_user_membership',
			'post_status' => 'wcm-free_trial',
			'nopaging'    => true,
		);

		$posts = get_posts( $args );

		// Bail out if there are no memberships on free trial
		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$user_membership = wc_memberships_get_user_membership( $post );
			$user_membership->pause_membership( __( 'Membership paused because WooCommerce Subscriptions was deactivated.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Handle subscriptions activation
	 *
	 * @since 1.0.0
	 */
	public function handle_activation() {
		$this->update_subscription_memberships();
	}


	/**
	 * Handle subscriptions deactivation
	 *
	 * @since 1.0.0
	 */
	public function handle_deactivation() {
		$this->pause_free_trial_subscription_memberships();
	}


	/**
	 * Handle subscriptions upgrade
	 *
	 * This method runs on each admin page load and checks the current
	 * Subscriptions version against our record of Subscriptions version.
	 * We can't rely on the `woocommerce_subscriptions_upgraded` hook because
	 * that cannot be caught when Memberships is deactivated during upgrade.
	 *
	 * This solution catches all upgrades, even if they were done while Memberships
	 * was not active.
	 *
	 * @since 1.0.0
	 */
	public function handle_upgrade() {

		$subscriptions_version = get_option( 'wc_memberships_subscriptions_version' );

		// Versions match, we don't need to do anything
		if ( $subscriptions_version == WC_Subscriptions::$version ) {
			return;
		}

		// Upgrade routine from Subscriptions pre-2.0 to 2.0
		if ( version_compare( WC_Subscriptions::$version, '2.0.0', '>=' ) && version_compare( $subscriptions_version, '2.0.0', '<' ) ) {

			global $wpdb;

			// Upgrade user memberships to use Subscription IDs instead of keys.
			$results = $wpdb->get_results("
				SELECT pm.post_id as ID, pm.meta_value as subscription_key
				FROM $wpdb->postmeta pm
				LEFT JOIN $wpdb->posts p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_subscription_key'
				AND p.post_type = 'wc_user_membership'
			");

			// Bail out if there are no memberships with subscription keys
			if ( empty( $results ) ) {
				return;
			}

			foreach ( $results as $result ) {
				$subscription_id = wcs_get_subscription_id_from_key( $result->subscription_key );

				if ( $subscription_id ) {
					update_post_meta( $result->ID, '_subscription_id', $subscription_id );
					delete_post_meta( $result->ID, '_subscription_key' );
				}
			}
		}

		// Update our record of Subscriptions version
		update_option( 'wc_memberships_subscriptions_version', WC_Subscriptions::$version );
	}


	/**
	 * Check whether a membership is subscription-based or not
	 *
	 * @since 1.0.1
	 * @param int|WC_Memberships_User_Membership $user_membership
	 * @return bool True, if memberships is subscription-based, false otherwise
	 */
	public function membership_has_subscription( $user_membership ) {

		$id = is_object( $user_membership ) ? $user_membership->get_id() : $user_membership;

		if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
			$subscription = get_post_meta( $id, '_subscription_id', true );
		} else {
			$subscription = get_post_meta( $id, '_subscription_key', true );
		}

		return (bool) $subscription;
	}


}
