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
 * @package   WC-Memberships/Frontend/Checkout
 * @author    SkyVerge
 * @category  Frontend
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Checkout class, mainly handles forcing account creation or login when
 * purchasing a product that grants access to a membership
 *
 * Inspired from the similar checkout code in WC Subscriptions, thanks Prospress :)
 *
 * @since 1.0.0
 */
class WC_Memberships_Checkout {


	/** @var bool true when enable signup option has been changed */
	public $enable_signup_changed;

	/** @var bool true when enable guest checkout option has been changed */
	public $enable_guest_checkout_changed;


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// users must be able to register on checkout
		// note this runs at -1 priority to ensure this is set before any other hooks
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_enable_registration' ), -1 );

		// mark checkout registration fields as required
		add_action( 'woocommerce_checkout_fields', array( $this, 'maybe_require_registration_fields' ) );

		// remove guest checkout param from WC checkout JS
		add_filter( 'wc_checkout_params', array( $this, 'remove_guest_checkout_js_param' ) );

		// force registration during checkout process
		add_action( 'woocommerce_before_checkout_process', array( $this, 'maybe_force_registration_during_checkout' ) );
	}


	/**
	 * If shopping cart contains subscriptions, make sure a user can register on the checkout page
	 *
	 * @param \WC_Checkout $checkout instance
	 * @since 1.0.0
	 */
	public function maybe_enable_registration( $checkout = null ) {

		if ( $checkout && $this->force_registration() ) {

			// enable signups
			if ( false === $checkout->enable_signup ) {
				$checkout->enable_signup = true;
				$this->enable_signup_changed = true;
			}

			// disable guest checkout
			if ( true === $checkout->enable_guest_checkout ) {
				$checkout->enable_guest_checkout = false;
				$checkout->must_create_account = true;
				$this->enable_guest_checkout_changed = true;
			}

			// restore previous settings after checkout has loaded
			if ( $this->enable_signup_changed || $this->enable_guest_checkout_changed ) {
				add_action( 'woocommerce_after_checkout_form', array( $this, 'restore_registration_settings' ), 9999 );
			}
		}
	}


	/**
	 * Restore the original checkout registration settings after checkout has loaded
	 *
	 * @param \WC_Checkout $checkout instance
	 * @since 1.0.0
	 */
	public function restore_registration_settings( $checkout = null ) {

		// re-disable signups
		if ( $this->enable_signup_changed ) {
			$checkout->enable_signup = false;
		}

		// re-enable guest checkouts
		if ( $this->enable_guest_checkout_changed ) {
			$checkout->enable_guest_checkout = true;
			$checkout->must_create_account = false;
		}
	}


	/**
	 * Mark the account fields as required
	 *
	 * @since 1.0.0
	 */
	public function maybe_require_registration_fields( $fields ) {

		if ( $this->force_registration() ) {

			foreach ( array( 'account_username', 'account_password', 'account_password-2' ) as $field ) {
				if ( isset( $fields['account'][ $field ] ) ) {
					$fields['account'][ $field ]['required'] = true;
				}
			}
		}

		return $fields;
	}


	/**
	 * Remove the guest checkout param from WC checkout JS so the registration
	 * form isn't hidden
	 *
	 * @since 1.0.0
	 * @param array $params checkout JS params
	 * @return array
	 */
	public function remove_guest_checkout_js_param( $params ) {

		if ( $this->force_registration() && isset( $params['option_guest_checkout'] ) && 'yes' == $params['option_guest_checkout'] ) {
			$params['option_guest_checkout'] = 'no';
		}

		return $params;
	}


	/**
	 * Force registration during the checkout process
	 *
	 * @since 1.0.0
	 */
	public function maybe_force_registration_during_checkout() {

		if ( $this->force_registration() ) {
			$_POST['createaccount'] = 1;
		}
	}


	/**
	 * Check if registration should be forced if all of the following are true:
	 *
	 * 1) user is not logged in
	 * 2) an item in the cart contains a product that grants access to a membership
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function force_registration() {

		if ( is_user_logged_in() ) {
			return false;
		}

		// Get membership plans
		$membership_plans = wc_memberships()->plans->get_membership_plans();

		// Bail out if there are no membership plans
		if ( empty( $membership_plans ) ) {
			return false;
		}

		$force = false;

		// Loop over all available membership plans
		foreach ( $membership_plans as $plan ) {

			// Skip if no products grant access to this plan
			if ( ! $plan->has_products() ) {
				continue;
			}

			// Array to store products that grant access to this plan
			$access_granting_product_ids = array();

			// Loop over items to see if any of them grant access to any memberships
			foreach ( WC()->cart->get_cart() as $key => $item ) {

				// Product grants access to this membership
				if ( $plan->has_product( $item['product_id'] ) ) {
					$access_granting_product_ids[] = $item['product_id'];
				}

				// Variation access
				if ( isset( $item['variation_id'] ) && $item['variation_id'] && $plan->has_product( $item['variation_id'] ) ) {
					$access_granting_product_ids[] = $item['variation_id'];
				}

			}

			// No products grant access, skip further processing
			if ( empty( $access_granting_product_ids ) ) {
				continue;
			}

			/**
			 * Filter the product ID that grants access to the membership plan via purchase
			 *
			 * Multiple products from a single order can grant access to a membership plan.
			 * Default behavior is to use the first product that grants access, but this can
			 * be overriden using this filter.
			 *
			 * @since 1.0.0
			 *
			 * @param int $product_id
			 * @param array $access_granting_product_ids Array of product IDs that can grant access to this plan
			 * @param WC_Memberships_Membership_Plan $plan Membership plan access will be granted to
			 */
			$product_id = apply_filters( 'wc_memberships_access_granting_purchased_product_id', $access_granting_product_ids[0], $access_granting_product_ids, $plan );

			// Sanity check: make sure the selected product ID in fact does grant access
			if ( ! $plan->has_product( $product_id ) ) {
				continue;
			}

			$force = true;
		}

		return $force;
	}


}
