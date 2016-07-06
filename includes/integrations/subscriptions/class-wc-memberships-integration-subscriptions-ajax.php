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
 * Ajax integration class for WooCommerce Subscriptions
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Ajax {


	/**
	 * Add ajax callbacks
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// admin only
		add_action( 'wp_ajax_wc_memberships_membership_plan_has_subscription_product', array( $this, 'ajax_plan_has_subscription' ) );
		add_action( 'wp_ajax_wc_memberships_delete_membership_and_subscription',       array( $this, 'delete_membership_with_subscription' ) );
	}


	/**
	 * Check if a plan has a subscription product
	 *
	 * Responds with an array of subscription products, if any.
	 *
	 * @since 1.6.0
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
	 * @since 1.6.0
	 */
	public function delete_membership_with_subscription() {

		check_ajax_referer( 'delete-user-membership-with-subscription', 'security' );

		if ( isset( $_POST['user_membership_id'] ) && isset( $_POST['subscription_id'] ) ) {

			$subscription_id    = (int) $_POST['subscription_id'];
			$user_membership_id = (int) $_POST['user_membership_id'];

			if ( $user_membership = wc_memberships_get_user_membership( $user_membership_id ) ) {

				$integration  = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
				$subscription = $integration->get_subscription_from_membership( $user_membership->id );

				if ( $subscription instanceof WC_Subscription && ( $subscription_id === (int) $subscription->id ) ) {

					$results = array(
						'delete-subscription' => wp_delete_post( $subscription_id ),
						'delete-user-membership' => wp_delete_post( $user_membership_id ),
					);

					wp_send_json_success( $results );
				}
			}
		}

		die();
	}


}
