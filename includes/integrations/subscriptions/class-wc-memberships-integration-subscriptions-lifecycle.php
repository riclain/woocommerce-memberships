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
 * Integration class for WooCommerce Subscriptions lifecycle
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Lifecycle {


	/**
	 * Add lifecycle hooks
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// upon Memberships or Subscription activation
		add_action( 'wc_memberships_activated',              array( $this, 'handle_activation' ), 1 );
		add_action( 'woocommerce_subscriptions_activated',   array( $this, 'handle_activation' ), 1 );

		// upon Subscriptions deactivation
		add_action( 'woocommerce_subscriptions_deactivated', array( $this, 'handle_deactivation' ) );

		// handle upgrade from Subscriptions 1.5.x to 2.0+
		add_action( 'admin_init', array( $this, 'handle_upgrade' ) );
	}


	/**
	 * Handle subscriptions activation
	 *
	 * @since 1.6.0
	 */
	public function handle_activation() {
		$this->update_subscription_memberships();
	}


	/**
	 * Handle subscriptions deactivation
	 *
	 * @since 1.6.0
	 */
	public function handle_deactivation() {
		$this->pause_free_trial_subscription_memberships();
	}


	/**
	 * Pause subscription-based memberships
	 *
	 * Find any memberships that are on free trial and pause them
	 *
	 * @since 1.6.0
	 */
	public function pause_free_trial_subscription_memberships() {

		// get user memberships on free trial status
		$posts = get_posts( array(
			'post_type'   => 'wc_user_membership',
			'post_status' => 'wcm-free_trial',
			'nopaging'    => true,
		) );

		// bail out if there are no memberships on free trial
		if ( empty( $posts ) ) {
			return;
		}

		// pause the found memberships
		foreach ( $posts as $post ) {

			$user_membership = wc_memberships_get_user_membership( $post );
			$user_membership->pause_membership( __( 'Membership paused because WooCommerce Subscriptions was deactivated.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Re-activate subscription-based memberships
	 *
	 * Find any memberships tied to a subscription that are paused,
	 * which may need to be re-activated or put back on trial
	 *
	 * @since 1.6.0
	 */
	public function update_subscription_memberships() {

		// get the Subscriptions integration instance
		$integration = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();

		// sanity check
		if ( null === $integration ) {
			return;
		}

		$args = array(
			'post_type'    => 'wc_user_membership',
			'nopaging'     => true,
			'post_status'  => 'any',
			'meta_key'     => $integration->get_subscription_meta_key_name(),
			'meta_value'   => '0',
			'meta_compare' => '>',
		);

		$posts = get_posts( $args );

		// bail out if there are no memberships to work with
		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$user_membership = wc_memberships_get_user_membership( $post );

			// get the related subscription
			$subscription = $integration->get_subscription_from_membership( $user_membership->get_id() );

			if ( ! $subscription ) {
				continue;
			}

			$subscription_status = $integration->get_subscription_status( $subscription );

			// if statuses do not match, update
			if ( ! $integration->has_subscription_same_status( $subscription, $user_membership ) ) {

				// special handling for paused memberships
				if ( 'paused' === $user_membership->get_status() ) {

					// do not bother if the Subscription isn't active in the first place
					if ( 'active' !== $subscription_status ) {

						// get trial end timestamp
						$trial_end = $integration->get_subscription_event_time( $subscription, 'trial_end' );

						// if there is no trial end date and the Subscription is active, activate the membership
						if ( ! $trial_end || current_time( 'timestamp', true ) >= strtotime( $trial_end ) ) {
							$user_membership->activate_membership( __( 'Membership activated because WooCommerce Subscriptions was activated.', 'woocommerce-memberships' ) );
						// otherwise, put it on free trial
						} else {
							$user_membership->update_status( 'free_trial', __( 'Membership free trial activated because WooCommerce Subscriptions was activated.', 'woocommerce-memberships' ) );
						}
					}

				// all other statuses: simply update the membership status
				} else {

					$integration->update_related_membership_status( $subscription, $user_membership, $subscription_status );
				}
			}

			$end_date = $integration->get_subscription_event_date( $subscription, 'end' );

			// end date has changed
			if ( strtotime( $end_date ) !== $user_membership->get_end_date( 'timestamp' ) ) {
				$user_membership->set_end_date( $end_date );
			}
		}
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
	 * @since 1.6.0
	 */
	public function handle_upgrade() {

		// sanity check
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		$subscriptions_version = get_option( 'wc_memberships_subscriptions_version' );

		// versions match, we don't need to do anything
		if ( $subscriptions_version === WC_Subscriptions::$version ) {
			return;
		}

		// upgrade routine from Subscriptions pre-2.0 to 2.0
		if ( version_compare( WC_Subscriptions::$version, '2.0.0', '>=' ) && version_compare( $subscriptions_version, '2.0.0', '<' ) ) {

			global $wpdb;

			// upgrade user memberships to use Subscription IDs instead of keys
			$results = $wpdb->get_results("
				SELECT pm.post_id as ID, pm.meta_value as subscription_key
				FROM $wpdb->postmeta pm
				LEFT JOIN $wpdb->posts p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_subscription_key'
				AND p.post_type = 'wc_user_membership'
			");

			// bail out if there are no memberships with subscription keys
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

		// update our record of Subscriptions version
		update_option( 'wc_memberships_subscriptions_version', WC_Subscriptions::$version );
	}


}
