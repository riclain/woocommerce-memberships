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
 * Integration class for WooCommerce Subscriptions
 *
 * TODO When we remove support for Subscriptions 1.5 we could merge this with only remaining child class or move its methods into a Subscriptions Events Integration subclass {FN 2016-04-26}
 *
 * @since 1.6.0
 */
abstract class WC_Memberships_Integration_Subscriptions_Abstract {


	/** @var bool Checks if Subscriptions version is greater than v2.0.0 */
	protected $subscriptions_gte_2_0;

	/** @var string User Membership post meta key name to store tied Subscription */
	protected $subscription_meta_key_name = '';

	/** @var null|\WC_Memberships_Integration_Subscriptions_Lifecycle instance */
	protected $lifecycle;

	/** @var null|\WC_Memberships_Integration_Subscriptions_Admin instance */
	protected $admin;

	/** @var null|\WC_Memberships_Integration_Subscriptions_Frontend instance */
	protected $frontend;

	/** @var null|\WC_Memberships_Integration_Subscriptions_Ajax instance */
	protected $ajax;

	/** @var null|\WC_Memberships_Integration_Subscriptions_Free_Trial instance */
	protected $free_trial;

	/** @var null|\WC_Memberships_Integration_Subscriptions_Discounts instance */
	protected $discounts;

	/** @var array Subscription trial end date lazy storage */
	protected $_subscription_trial_end_date = array();

	/** @var array Membership plan subscription check lazy storage */
	protected $_has_membership_plan_subscription = array();


	/**
	 * Load integration hooks and subclasses
	 *
	 * The main class loads directly hooks handling Subscriptions events
	 * such as granting access, adjusting dates, synchronizing cron events,
	 * handling Subscription switches, expiring tied Memberships and so on
	 *
	 * Static integrations, such as admin and frontend UI modifications, or
	 * handling of specific modules of Memberships or WooCommerce, such as
	 * discounts, ajax, extending Membership properties and so on,
	 * are handled in individual subclasses loaded from this constructor
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// handle granting Membership access
		add_filter( 'wc_memberships_access_granting_purchased_product_id',               array( $this, 'adjust_access_granting_product_id' ), 10, 3 );
		add_filter( 'wc_memberships_new_membership_data',                                array( $this, 'adjust_new_membership_data' ), 10, 2 );
		add_filter( 'wc_memberships_renew_membership',                                   array( $this, 'renew_membership' ), 10, 3 );
		add_action( 'wc_memberships_grant_membership_access_from_purchase',              array( $this, 'save_subscription_data' ), 10, 2 );
		add_filter( 'wc_memberships_grant_access_from_new_purchase',                     array( $this, 'maybe_grant_access_from_new_subscription' ), 10, 2 );
		add_filter( 'wc_memberships_grant_access_from_existing_purchase',                array( $this, 'maybe_grant_access_from_existing_subscription' ), 10, 2 );
		add_filter( 'wc_memberships_grant_access_from_existing_purchase_order_statuses', array( $this, 'grant_access_from_active_subscription' ) );

		// adjust Memberships access dates
		add_filter( 'wc_memberships_access_from_time',     array( $this, 'adjust_post_access_from_time' ), 10, 3 );
		add_filter( 'wc_memberships_plan_expiration_date', array( $this, 'adjust_plan_expiration_date' ), 10, 3 );

		// handle Membership expiration by cron event
		add_filter( 'wc_memberships_expire_user_membership', array( $this, 'handle_membership_expiry_by_scheduled_event' ), 5, 2 );

		// handle Subscription switches
		add_action( 'woocommerce_subscriptions_switched_item', array( $this, 'handle_subscription_switches' ), 10, 3 );

		// handle Membership status changes
		add_action( 'wc_memberships_user_membership_status_changed', array( $this, 'handle_user_membership_status_change' ), 10, 3 );

		// filter Renew Membership URL
		add_filter( 'wc_memberships_get_renew_membership_url', array( $this, 'renew_membership_url' ), 10, 2 );

		// handle free trials for Memberships
		$this->free_trial = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-free-trial.php', 'WC_Memberships_Integration_Subscriptions_Free_Trial' );

		// handle discounts
		$this->discounts = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-discounts.php', 'WC_Memberships_Integration_Subscriptions_Discounts' );

		if ( is_admin() ) {
			// admin methods and hooks
			$this->admin = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-admin.php', 'WC_Memberships_Integration_Subscriptions_Admin' );
		} else {
			// frontend methods and hooks
			$this->frontend = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-frontend.php', 'WC_Memberships_Integration_Subscriptions_Frontend' );
		}

		// handle ajax interactions between the two extensions
		$this->ajax = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-ajax.php', 'WC_Memberships_Integration_Subscriptions_Ajax' );

		// extensions lifecycle (activation, deactivation, upgrade, etc.)
		$this->lifecycle = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-lifecycle.php', 'WC_Memberships_Integration_Subscriptions_Lifecycle' );
	}


	/**
	 * Get Subscriptions Admin integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Admin
	 */
	public function get_admin_instance() {
		return $this->admin;
	}


	/**
	 * Get Subscriptions Frontend integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Frontend
	 */
	public function get_frontend_instance() {
		return $this->frontend;
	}


	/**
	 * Get Subscriptions Ajax integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Ajax
	 */
	public function get_ajax_instance() {
		return $this->ajax;
	}


	/**
	 * Get Subscriptions Lifecycle integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Lifecycle
	 */
	public function get_lifecycle_instance() {
		return $this->lifecycle;
	}


	/**
	 * Get Subscriptions Free Trial integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Free_Trial
	 */
	public function get_free_trial_instance() {
		return $this->free_trial;
	}


	/**
	 * Get Subscriptions Discounts integration instance
	 *
	 * @since 1.6.0
	 * @return null|\WC_Memberships_Integration_Subscriptions_Discounts
	 */
	public function get_discounts_instance() {
		return $this->discounts;
	}


	/** Subscriptions events methods *****************************************/


	/**
	 * Update related membership status based on subscription status
	 *
	 * @since 1.6.0
	 * @param array|\WC_Subscription $subscription
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @param string $status Subscription status
	 * @param string|void $note Optional Membership note, if empty will be automatically set by status type
	 */
	public function update_related_membership_status( $subscription, $user_membership, $status, $note = '' ) {

		$plan_id = $user_membership->get_plan_id();

		if ( ! $plan_id || ! $this->plan_grants_access_while_subscription_active( $plan_id ) ) {
			return;
		}

		switch ( $status ) {

			case 'active':

				$trial_end = $this->get_subscription_event_time( $subscription, 'trial_end' );

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

				$this->unlink_membership( $user_membership->get_id(), $subscription );

			break;

			case 'trash':

				if ( ! $note ) {
					$note = __( 'Membership cancelled because subscription was trashed.', 'woocommerce-memberships' );
				}

				$user_membership->cancel_membership( $note );

				$this->unlink_membership( $user_membership->get_id(), $subscription );

			break;

			default:
			break;
		}
	}


	/**
	 * Handle user membership status changes
	 *
	 * @since 1.6.0
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
		if ( 'free_trial' === $new_status && $paused_date = $user_membership->get_paused_date() ) {

			$user_membership->set_end_date( $user_membership->get_end_date() );
			$user_membership->delete_paused_date();
		}
	}


	/**
	 * Check if a Subscription-tied membership should really expire
	 * by comparing it to either the Subscription's or User Membership's expiry date
	 *
	 * @since 1.5.4
	 * @param bool $maybe_expire Whether the User Membership is set to expire (true) or not (false)
	 * @param \WC_Memberships_User_Membership $user_membership The User Membership object set to expire
	 * @return bool True to confirm expiration, false to prevent it
	 */
	public function handle_membership_expiry_by_scheduled_event( $maybe_expire, $user_membership ) {

		$subscription = $this->get_subscription_from_membership( $user_membership->get_id() );

		// bail if this Membership is not tied to a Subscription
		if ( ! $subscription ) {
			return $maybe_expire;
		}

		$subscription_end_date = $this->get_subscription_event_date( $subscription, 'end' );

		// expire only if the scheduled date matches the subscription end date...
		if ( $subscription_end_date === $user_membership->get_end_date() ) {

			$today = date( 'Y-m-d', current_time( 'timestamp', true ) );

			// ...and it's scheduled to expire today
			if ( 0 === strpos( $subscription_end_date, $today ) ) {
				return true;
			}

			// ...otherwise leave it running
			return $maybe_expire;
		}

		return false;
	}


	/**
	 * Handle subscription upgrades/downgrades (switch).
	 * Hook is available since Subscriptions 2.0.6+ only.
	 *
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription The subscription object
	 * @param array $new_order_item The new order item (switching to)
	 * @param array $old_order_item The old order item (switching from)
	 */
	public function handle_subscription_switches( $subscription, $new_order_item, $old_order_item ) {

		$user_memberships = $this->get_memberships_from_subscription( $subscription->id );

		if ( ! $user_memberships ) {
			return;
		}

		$old_product_id = 0;

		// grab the variation_id for variable upgrades,
		// or the product_id for grouped product upgrades
		if ( ! empty( $old_order_item['variation_id'] ) ) {
			$old_product_id = $old_order_item['variation_id'];
		} elseif ( ! empty( $old_order_item['product_id'] ) ) {
			$old_product_id = $old_order_item['product_id'];
		}

		// loop found memberships
		foreach ( $user_memberships as $user_membership ) {

			// handle upgrades/downgrades for variable products
			if ( absint( $old_product_id ) === absint( $user_membership->get_product_id() ) ) {

				$note = __( 'Membership cancelled because subscription was switched.', 'woocommerce-memberships' );

				$user_membership->cancel_membership( $note );

				// unlink the Membership from the Subscription
				$this->unlink_membership( $user_membership->get_id(), $subscription->id );
			}
		}
	}


	/** General methods ****************************************************/


	/**
	 * Adjust user membership post scheduled content 'access from' time for subscription-based memberships
	 *
	 * @since 1.6.0
	 * @param string $from_time Access from time, as a timestamp
	 * @param \WC_Memberships_Membership_Plan_rule $rule Related rule
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @return string Modified from_time, as timestamp
	 */
	public function adjust_post_access_from_time( $from_time, WC_Memberships_Membership_Plan_Rule $rule, WC_Memberships_User_Membership $user_membership ) {

		if ( 'yes' === $rule->get_access_schedule_exclude_trial() ) {

			$has_subscription = $this->is_membership_linked_to_subscription( $user_membership->get_id() );
			$trial_end_date   = $this->get_user_membership_trial_end_date( $user_membership->get_id(), 'timestamp' );

			if ( $has_subscription && $trial_end_date ) {

				$from_time = $trial_end_date;
			}
		}

		return $from_time;
	}


	/**
	 * Adjust plan expiration date
	 *
	 * This helps align a membership expiration date to a Subscription length
	 * for installations where an access length has been set before assigning
	 * a subscription product that grants access or where multiple product types
	 * can grant access
	 *
	 * @since 1.6.0
	 * @param string $end_date Expiration date, might be empty for unlimited plans
	 * @param string|int $end_timestamp Expiration timestamp or empty string for unlimited plans
	 * @param array $args Additional arguments
	 * @return string Expiration date
	 */
	public function adjust_plan_expiration_date( $end_date, $end_timestamp, $args ) {

		if ( isset( $args['product_id'] ) ) {

			$access_product = wc_get_product( $args['product_id'] );

			if ( $access_product && $access_product->is_type( array( 'subscription', 'subscription_variation' ) ) ) {

				$product_id = $access_product->is_type( 'subscription_variation' ) ? $access_product->variation_id : $access_product->id;

				$expiration_date = WC_Subscriptions_Product::get_expiration_date( $product_id );

				// by zero Subscriptions means there is no expiry date,
				// but we use empty string for that
				if ( 0 === $expiration_date ) {
					return '';
				}

				// by this point $expiration_date should have a value,
				// but let's still do a sanity check
				return ! $expiration_date ? $end_date : $expiration_date;
			}
		}

		return $end_date;
	}


	/**
	 * Adjust renew membership URL for subscription-based memberships
	 *
	 * @since 1.6.0
	 * @param string $url Renew membership URL
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @return string Modified renew URL
	 */
	public function renew_membership_url( $url, WC_Memberships_User_Membership $user_membership ) {

		// note that we must also check if order contains a subscription
		// since users can be manually-assigned to memberships
		// and not have an associated subscription
		if ( $this->has_membership_plan_subscription( $user_membership->get_plan_id() ) && $this->order_contains_subscription( $user_membership->get_order() ) ) {

			$url = $this->get_subscription_renewal_url( $user_membership );
		}

		return $url;
	}


	/**
	 * Adjust whether a membership should be renewed or not
	 *
	 * @since 1.6.0
	 * @param bool $renew
	 * @param \WC_Memberships_Membership_Plan $plan
	 * @param array $args
	 * @return bool
	 */
	public function renew_membership( $renew, $plan, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return $renew;
		}

		// disable renewing via a re-purchase of a subscription product
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) && $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

			$renew = false;
		}

		return $renew;
	}


	/**
	 * Adjust the product ID that grants access to a membership plan on purchase
	 *
	 * Subscription products take priority over all other products
	 *
	 * @since 1.6.0
	 * @param int $product_id Product ID
	 * @param array $access_granting_product_ids Array of product IDs in the purchase order
	 * @param \WC_Memberships_Membership_Plan $plan Membership Plan to access
	 * @return int ID of the Subscription product that grants access,
	 *             if multiple IDs are in a purchase order, the one that grants longest membership access is used
	 */
	public function adjust_access_granting_product_id( $product_id, $access_granting_product_ids, WC_Memberships_Membership_Plan $plan ) {

		// check if more than one products may grant access,
		// and if the plan even allows access while subscription is active
		if ( count( $access_granting_product_ids ) > 1 && $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

			// first, find all subscription products that grant access
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

			// if there are any, decide which one actually gets to grant access
			if ( ! empty( $access_granting_subscription_product_ids ) ) {

				// only one subscription grants access, short-circuit it as the winner
				if ( 1 === count( $access_granting_subscription_product_ids ) ) {

					$product_id = $access_granting_subscription_product_ids[0];

				// multiple subscriptions grant access
				} else {

					$longest_expiration_date = 0;

					// let's select the most gracious one:
					// whichever gives access for a longer period, wins
					foreach ( $access_granting_subscription_product_ids as $_subscription_product_id ) {

						$expiration_date = WC_Subscriptions_Product::get_expiration_date( $_subscription_product_id );

						// no expiration date always means the longest period
						if ( ! $expiration_date ) {

							$product_id = $_subscription_product_id;
							break;
						}

						// the current Subscription has a longer expiration date
						// than the previous one in the loop
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
	 * @since 1.6.0
	 * @param bool $grant_access
	 * @param array $args
	 * @return bool
	 */
	public function maybe_grant_access_from_new_subscription( $grant_access, $args ) {

		if ( $this->is_subscriptions_gte_2_0() && wcs_order_contains_renewal( $args['order_id'] ) ) {

			// subscription renewals cannot grant access
			$grant_access = false;

		} elseif ( isset( $args['order_id'], $args['product_id'], $args['user_id'] ) ) {

			// reactivate a cancelled/pending cancel User Membership,
			// when re-purchasing the same Subscription that grants access

			$product = wc_get_product( $args['product_id'] );

			if ( $product && $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {

				$user_id = $args['user_id'];
				$order   = wc_get_order( $args['order_id'] );
				$plans   = wc_memberships()->get_plans_instance()->get_membership_plans();

				// loop over all available membership plans
				foreach ( $plans as $plan ) {

					// skip if no products grant access to this plan
					if ( ! $plan->has_products() ) {
						continue;
					}

					$access_granting_product_ids = wc_memberships()->get_access_granting_purchased_product_ids( $plan, $order );

					foreach ( $access_granting_product_ids as $access_granting_product_id ) {

						// sanity check: make sure the selected product ID in fact does grant access
						if ( ! $plan->has_product( $access_granting_product_id ) ) {
							continue;
						}

						if ( (int) $product->id === (int) $access_granting_product_id ) {

							$user_membership = wc_memberships_get_user_membership( $user_id, $plan );

							// check if the user purchasing is already member of a plan
							// but the membership is cancelled or pending cancellation
							if ( wc_memberships_is_user_member( $user_id, $plan ) && $user_membership->has_status( array( 'pending-cancel', 'pending', 'cancelled' ) ) ) {

								// reactivate the User Membership and leave a note

								$note = sprintf(
									/* translators: Placeholders: %1$s is the subscription product name, %2%s is the order number */
									__( 'Membership re-activated due to subscription re-purchase (%1$s, Order %2$s).', 'woocommerce-memberships' ),
									$product->get_title(),
									'<a href="' . admin_url( 'post.php?post=' . $order->id . '&action=edit' ) .'" >' . $order->id . '</a>'
								);

								$user_membership->activate_membership( $note );

								// update the User Membership with the new Subscription meta
								if ( $this->is_subscriptions_gte_2_0() ) {
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
	 * @since 1.6.0
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
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) )
		     && isset( $args['order_id'] ) && $args['order_id'] > 0 ) {

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
	 * @since 1.6.0
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
	 * @since 1.6.0
	 * @param array $data Original membership data
	 * @param array $args Array of arguments
	 * @return array Modified membership data
	 */
	public function adjust_new_membership_data( $data, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return $data;
		}

		// handle access from subscriptions
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) )
			&& isset( $args['order_id'] ) && (int) $args['order_id'] > 0
			&& $subscription = $this->get_order_product_subscription( $args['order_id'], $product->id ) ) {

			$trial_end = $this->get_subscription_event_time( $subscription, 'trial_end' );

			if ( $trial_end && $trial_end > current_time( 'timestamp', true ) ) {
				$data['post_status'] = 'wcm-free_trial';
			}
		}

		return $data;
	}


	/**
	 * Save related subscription data when a membership access is granted via a purchase
	 *
	 * Sets the end date to match subscription end date
	 *
	 * @since 1.6.0
	 * @param WC_Memberships_Membership_Plan $plan
	 * @param array $args
	 */
	public function save_subscription_data( WC_Memberships_Membership_Plan $plan, $args ) {

		$product = wc_get_product( $args['product_id'] );

		if ( ! $product ) {
			return;
		}

		// handle access from Subscriptions
		if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) && $this->has_membership_plan_subscription( $plan->get_id() ) ) {

			// note: always use the product ID (not variation ID)
			// when looking up a subscription, as Subscriptions requires it
			$subscription = $this->get_order_product_subscription( $args['order_id'], $product->id );

			if ( ! $subscription ) {
				return;
			}

			if ( $this->is_subscriptions_gte_2_0() ) {

				// Save related subscription ID
				update_post_meta( $args['user_membership_id'], '_subscription_id', $subscription->id );

			} else {

				// Save related subscription key
				$subscription_key = WC_Subscriptions_Manager::get_subscription_key( $args['order_id'], $product->id );
				update_post_meta( $args['user_membership_id'], '_subscription_key', $subscription_key );
			}

			$end_date = '';

			// set membership expiry date based on subscription expiry date
			if ( $this->plan_grants_access_while_subscription_active( $plan->get_id() ) ) {

				$end_date = $this->get_subscription_event_date( $subscription, 'end' );
			}

			update_post_meta( $args['user_membership_id'], '_end_date', $end_date );
		}
	}


	/**
	 * Get the membership subscription trial end datetime
	 *
	 * May return null if the membership is not tied to a subscription
	 *
	 * @since 1.6.0
	 * @param int $user_membership_id User Membership ID
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return string|null Returns the trial end date or null
	 */
	public function get_user_membership_trial_end_date( $user_membership_id, $format = 'mysql' ) {

		if ( ! isset( $this->_subscription_trial_end_date[ $user_membership_id ] ) ) {

			if ( ! $this->is_membership_linked_to_subscription( $user_membership_id ) ) {
				return null;
			}

			$subscription = $this->get_subscription_from_membership( $user_membership_id );

			if ( 'mysql' === $format ) {
				$trial_end = $this->get_subscription_event_date( $subscription, 'trial_end' );
			} else {
				$trial_end = $this->get_subscription_event_time( $subscription, 'trial_end' );
			}

			$this->_subscription_trial_end_date[ $user_membership_id ] = $trial_end;
		}

		return $this->_subscription_trial_end_date[ $user_membership_id ];
	}


	/**
	 * Does a membership plan allow access while subscription is active?
	 *
	 * @since 1.6.0
	 * @param int $plan_id Membership Plan ID
	 * @return bool True, if access is allowed, false otherwise
	 */
	public function plan_grants_access_while_subscription_active( $plan_id ) {

		/**
		 * Filter whether a plan grants access to a membership while subscription is active
		 *
		 * @since 1.6.0
		 * @param bool $grants_access Default: true
		 * @param int $plan_id Membership Plan ID
		 */
		return apply_filters( 'wc_memberships_plan_grants_access_while_subscription_active', true, $plan_id );
	}


	/** Internal & helper methods ******************************************/


	/**
	 * Check if Subscriptions version is >= 2.0.0
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public function is_subscriptions_gte_2_0() {

		if ( ! is_null( $this->subscriptions_gte_2_0 ) ) {
			return $this->subscriptions_gte_2_0;
		}

		return $this->subscriptions_gte_2_0 = SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0();
	}


	/**
	 * Get the User Membership post meta key name
	 * used for storing a tied Subscription
	 *
	 * @since 1.6.0
	 * @return string
	 */
	public function get_subscription_meta_key_name() {
		return $this->subscription_meta_key_name;
	}


	/**
	 * Get a Subscription object
	 *
	 * @since 1.6.0
	 * @param int|object|\WC_Subscription $subscription
	 * @return WC_Subscription
	 */
	public function get_subscription( $subscription ) {
		return wcs_get_subscription( $subscription );
	}


	/**
	 * Get a Subscription id
	 *
	 * @since 1.6.0
	 * @param int|object|\WC_Subscription $subscription
	 * @return int
	 */
	public function get_subscription_id( $subscription ) {

		$subscription_id = 0;

		if ( is_int( $subscription ) ) {
			$subscription_id = $subscription;
		} elseif ( is_object( $subscription ) && isset( $subscription->id ) ) {
			$subscription_id = $subscription->id;
		}

		return (int) $subscription_id;
	}


	/**
	 * Get an User Membership post meta storing the tied Subscription information
	 *
	 * @since 1.6.0
	 * @param int|\WC_Memberships_User_Membership $user_membership User Membership object or id
	 * @return string|int|false
	 */
	public function get_user_membership_subscription_meta( $user_membership ) {

		$user_membership_id = is_object( $user_membership ) ? $user_membership->id : $user_membership;

		return get_post_meta( (int) $user_membership_id, $this->get_subscription_meta_key_name(), true );
	}


	/**
	 * Get subscription key for a membership
	 *
	 * @since 1.6.0
	 * @param int $user_membership_id User Membership ID
	 * @return string|int|false
	 */
	public function get_user_membership_subscription_key( $user_membership_id ) {
		return get_post_meta( $user_membership_id, '_subscription_key', true );
	}


	/**
	 * Get subscription ID for a membership
	 *
	 * @since 1.6.0
	 * @param int $user_membership_id User Membership ID
	 * @return string|false
	 */
	public function get_user_membership_subscription_id( $user_membership_id ) {
		return get_post_meta( $user_membership_id, '_subscription_id', true );
	}


	/**
	 * Check if a Subscription associated to a Membership is renewable
	 *
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Memberships_User_Membership $user_membership Membership object
	 * @return bool
	 */
	abstract public function is_subscription_linked_to_membership_renewable( $subscription, $user_membership );


	/**
	 * Get a Subscription renewal link for a Subscription-tied Membership
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership A User Membership object
	 * @return string
	 */
	abstract public function get_subscription_renewal_url( $user_membership );


	/**
	 * Check if an order contains a Subscription
	 *
	 * @since 1.6.0
	 * @param WC_Order $order
	 * @return bool
	 */
	abstract protected function order_contains_subscription( $order );


	/**
	 * Get a Subscription by order_id and product_id
	 *
	 * @since 1.6.0
	 * @param int $order_id WC_Order id
	 * @param int $product_id WC_Product id
	 * @return null|array|\WC_Subscription Subscription array (1.5.x), object (2.0+) or null if not found
	 */
	abstract public function get_order_product_subscription( $order_id, $product_id );


	/**
	 * Get a Subscription from a User Membership
	 *
	 * @since 1.6.0
	 * @param int|\WC_Memberships_User_Membership $user_membership User Membership object or null if not found
	 * @return \WC_Subscription|array $subscription Subscription object (v2.0+) or array (v1.5.x)
	 */
	abstract public function get_subscription_from_membership( $user_membership );


	/**
	 * Get User Memberships from a Subscription
	 *
	 * @since 1.6.0
	 * @param string|int|\WC_Subscription $subscription A Subscription id, object or key (Subscriptions < 2.0.0)
	 * @return \WC_Memberships_User_Membership[] An array of User Membership objects or empty array if none found
	 */
	abstract public function get_memberships_from_subscription( $subscription );


	/**
	 * Get a Subscription status
	 *
	 * @since 1.6.0
	 * @param array|\WC_Subscription $subscription A subscription object or array
	 * @return string
	 */
	abstract public function get_subscription_status( $subscription );


	/**
	 * Get a Subscription event date or time
	 *
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription The Subscription to get the event for
	 * @param string $event The event to retrieve a date/time for
	 * @param string $format Output format: 'timestamp' for timestamp or 'mysql' for date (default)
	 * @return int|string
	 */
	abstract protected function get_subscription_event( $subscription, $event, $format = 'mysql' );


	/**
	 * Get the date for a Subscription event
	 *
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription The Subscription to get the event for
	 * @param string $event Type of event to retrieve a date for
	 * @return string Date in MySQL format
	 */
	public function get_subscription_event_date( $subscription, $event ) {
		return $this->get_subscription_event( $subscription, $event, 'mysql' );
	}


	/**
	 * Get the timestamp for a Subscription event
	 *
	 * @since 1.6.0
	 * @param \WC_Subscription $subscription The Subscription to get the event for
	 * @param string $event Type of event to retrieve a timestamp for
	 * @return int Timestamp
	 */
	public function get_subscription_event_time( $subscription, $event ) {
		return $this->get_subscription_event( $subscription, $event, 'timestamp' );
	}


	/**
	 * Compare a Subscription status with a Membership status
	 *
	 * Subscription statuses and Membership statuses do not have the same key names
	 * This helper method compares statuses and maps them to check if they're the same
	 *
	 * @since 1.6.0
	 * @param array|\WC_Subscription $subscription A subscription object or array
	 * @param \WC_Memberships_User_Membership $membership A user membership object
	 * @return bool True if the statuses match, false if they don't
	 */
	public function has_subscription_same_status( $subscription, $membership ) {

		$membership_status   = $membership->get_status();
		$subscription_status = $this->get_subscription_status( $subscription );

		// sanity check, although this shouldn't happen
		if ( ! $subscription_status && ! $membership_status ) {
			return true;
		}

		// subscription status name => membership status name
		$map = array(
			'active'         => 'active',
			'on-hold'        => 'paused',
			'expired'        => 'expired',
			'pending-cancel' => 'pending',
			'trash'          => 'cancelled',
		);

		if ( ! array_key_exists( $subscription_status, $map ) ) {
			return false;
		}

		return $map[ $subscription_status ] === $membership_status;
	}


	/**
	 * Check if a Membership Plan has at least one subscription product that grants access
	 *
	 * @since 1.6.0
	 * @param int $plan_id \WC_Memberships_Membership_Plan id
	 * @return bool
	 */
	public function has_membership_plan_subscription( $plan_id ) {

		if ( ! isset( $this->_has_membership_plan_subscription[ $plan_id ] ) ) {

			$plan = wc_memberships_get_membership_plan( $plan_id );

			// sanity check
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
	 * Check whether a User Membership is Subscription-based or not
	 *
	 * @since 1.6.0
	 * @param int|\WC_Memberships_User_Membership $user_membership
	 * @return bool
	 */
	public function is_membership_linked_to_subscription( $user_membership ) {

		$user_membership_id = is_object( $user_membership ) ? $user_membership->get_id() : (int) $user_membership;

		return (bool) get_post_meta( $user_membership_id, $this->get_subscription_meta_key_name(), true );
	}


	/**
	 * Decouple (unlink) a User Membership from a Subscription
	 *
	 * Removes Subscriptions information from a Membership
	 *
	 * @since 1.6.0
	 * @param int $user_membership_id The User Membership object or id
	 * @param string|int|\WC_Subscription $unlink_subscription The Subscription id or object (Subscription key for legacy 1.5.x) to unlink
	 * @return null|bool True on success, False on failure or Null if Subscription link not found
	 */
	public function unlink_membership( $user_membership_id, $unlink_subscription ) {

		$subscription_linked = get_post_meta( (int) $user_membership_id, $this->get_subscription_meta_key_name(), true );

		// sanity check: are we unlinking the right Subscription?
		if ( $this->is_subscriptions_gte_2_0() ) {

			$subscription_id = is_object( $unlink_subscription ) && isset( $unlink_subscription->id ) ? $unlink_subscription->id : $unlink_subscription;

			if ( $subscription_linked && (int) $subscription_linked !== (int) $subscription_id) {
				return null;
			}

		} elseif ( $unlink_subscription !== $subscription_linked ) {

			return null;
		}

		return delete_post_meta( (int) $user_membership_id, $this->get_subscription_meta_key_name() );
	}


	/** Deprecated methods ******************************************/


	/**
	 * Backwards compatibility handler for deprecated methods
	 *
	 * TODO Remove this when we drop support for deprecated methods {FN 2016-04-26}
	 *
	 * @since 1.6.0
	 * @param string $method Method called
	 * @param void|string|array|mixed $args Optional argument(s)
	 * @return null|void|mixed
	 */
	public function __call( $method, $args ) {

		$class = 'wc_memberships()->get_integrations()->get_subscriptions_instance()';

		$deprecated_since_1_6_0 = '1.6.0';

		switch ( $method ) {

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions::is_membership_linked_to_subscription() */
			case 'has_user_membership_subscription' :
			case 'membership_has_subscription' :
				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->is_membership_linked_to_subscription()" );
				return $this->is_membership_linked_to_subscription( $args );

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions::get_subscription_from_membership() */
			case 'get_user_membership_subscription' :
				return $this->get_subscription_from_membership( $args );

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Admin */
			case 'output_subscription_details' :
			case 'output_subscription_options' :
			case 'output_exclude_trial_option' :
			case 'user_membership_meta_box_actions' :
			case 'user_membership_post_row_actions' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_admin_instance()->{$method}()" );

				$admin = $this->get_admin_instance();

				if ( in_array( $method, array( 'output_subscription_details', 'output_subscription_options' ), true ) ) {
					return $admin->$method();
				} elseif ( isset( $args[0], $args[1] ) && in_array( $method, array( 'output_exclude_trial_option', 'user_membership_meta_box_actions', 'user_membership_post_row_actions' ), true ) ) {
					if ( 'output_exclude_trial_option' === $method ) {
						return $admin->$method( $args[0], $args[1] );
					}
					return $admin->$method( $args[0], $args[1] );
				}

				return null;

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Ajax */
			case 'ajax_plan_has_subscription' :
			case 'delete_membership_with_subscription' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_ajax_instance()->{$method}()" );

				return $this->get_ajax_instance()->$method();

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Discounts */
			case 'enable_discounts_to_sign_up_fees' :
			case 'apply_discounts_to_sign_up_fees' :
			case 'display_original_sign_up_fees' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_discounts_instance()->{$method}()" );

				$discounts = $this->get_discounts_instance();

				if ( in_array( $method, array( 'enable_discounts_to_sign_up_fees', 'display_original_sign_up_fees' ), true ) ) {
					return $discounts->$method( $args );
				} elseif ( 'apply_discounts_to_sign_up_fees' === $method && isset( $args[0], $args[1] ) ) {
					return $discounts->maybe_adjust_product_sign_up_fee( $args[0], $args[1] );
				}

				return null;

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Free_Trial */
			case 'add_free_trial_status' :
			case 'enable_cancel_for_free_trial' :
			case 'edit_user_membership_screen_status_options' :
			case 'remove_free_trial_from_bulk_edit' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_free_trial_instance()->{$method}()" );

				$free_trial = $this->get_free_trial_instance();

				if ( in_array( $method, array( 'add_free_trial_status', 'enable_cancel_for_free_trial', 'remove_free_trial_from_bulk_edit' ), true ) ) {
					return $free_trial->$method();
				} elseif ( 'edit_user_membership_screen_status_options' === $method && isset( $args[0], $args[1] ) ) {
					return $free_trial->$method( $args[0], $args[1] );
				}

				return null;

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Frontend */
			case 'output_subscription_column_headers' :
			case 'output_subscription_columns' :
			case 'my_membership_actions' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_frontend_instance()->{$method}()" );

				$frontend = $this->get_frontend_instance();

				if ( 'my_membership_actions' === $method && isset( $args[0], $args[1] ) ) {
					return $frontend->$method( $args[0], $args[1] );
				} elseif ( in_array( $method, array( 'output_subscription_column_headers', 'output_subscription_columns' ), true ) ) {
					return $frontend->$method( $args );
				}

				return null;

			/** @deprecated since 1.6.0 */
			/** @see WC_Memberships_Integration_Subscriptions_Lifecycle */
			case 'handle_activation' :
			case 'handle_deactivation' :
			case 'handle_upgrade' :
			case 'update_subscription_memberships' :

				_deprecated_function( "{$class}->{$method}()", $deprecated_since_1_6_0, "{$class}->get_lifecycle_instance()->{$method}()" );

				return $this->get_lifecycle_instance()->$method( $args );

			default :
				// you're probably doing it wrong
				trigger_error( 'Call to undefined method ' . __CLASS__ . '::' . $method, E_USER_ERROR );
				return null;

		}
 	}


}
