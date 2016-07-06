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
 * Class handling integrations with third party plugins
 *
 * @since 1.6.0
 */
class WC_Memberships_Integrations {


	/* @var null|WC_Memberships_Integration_Bookings instance */
	private $bookings = null;

	/* @var null|WC_Memberships_Integration_Groups instance */
	private $groups = null;

	/* @var null|WC_Memberships_Integration_Subscriptions instance */
	private $subscriptions = null;

	/* @var null|WC_Memberships_Integration_User_Switching instance */
	private $user_switching = null;


	/**
	 * Load integrations
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// Bookings
		if ( $this->is_bookings_active() ) {
			$this->bookings = wc_memberships()->load_class( '/includes/integrations/bookings/class-wc-memberships-integration-bookings.php', 'WC_Memberships_Integration_Bookings' );
		}

		// Groups
		if ( $this->is_groups_active() ) {
			$this->groups = wc_memberships()->load_class( '/includes/integrations/groups/class-wc-memberships-integration-groups.php', 'WC_Memberships_Integration_Groups' );
		}

		// Subscriptions
		if ( $this->is_subscriptions_active() ) {

			// load abstract integration class
			require_once( wc_memberships()->get_plugin_path() . '/includes/integrations/subscriptions/abstract-wc-memberships-integration-subscriptions.php' );

			// load implementation specific to a Subscriptions version in use
			if ( SV_WC_Plugin_Compatibility::is_wc_subscriptions_version_gte_2_0() ) {
				$this->subscriptions = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions.php', 'WC_Memberships_Integration_Subscriptions' );
			} else {
				$this->subscriptions = wc_memberships()->load_class( '/includes/integrations/subscriptions/class-wc-memberships-integration-subscriptions-1-5.php', 'WC_Memberships_Integration_Subscriptions_1_5' );
			}
		}

		// User Switching
		if ( $this->is_user_switching_active() ) {
			$this->user_switching = wc_memberships()->load_class( '/includes/integrations/user-switching/class-wc-memberships-integration-user-switching.php', 'WC_Memberships_Integration_User_Switching' );
		}
	}


	/**
	 * Get Bookings integration instance
	 *
	 * @since 1.6.0
	 * @return null|WC_Memberships_Integration_Bookings
	 */
	public function get_bookings_instance() {
		return $this->bookings;
	}


	/**
	 * Get Groups integration instance
	 *
	 * @since 1.6.0
	 * @return null|WC_Memberships_Integration_Groups
	 */
	public function get_groups_instance() {
		return $this->groups;
	}


	/**
	 * Get Subscriptions integration instance
	 *
	 * @since 1.6.0
	 * @return null|WC_Memberships_Integration_Subscriptions
	 */
	public function get_subscriptions_instance() {
		return $this->subscriptions;
	}


	/**
	 * Get User Switching integration instance
	 *
	 * @since 1.6.0
	 * @return null|WC_Memberships_Integration_User_Switching
	 */
	public function get_user_switching_instance() {
		return $this->user_switching;
	}


	/**
	 * Checks if Bookings is active
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public function is_bookings_active() {
		return wc_memberships()->is_plugin_active( 'woocommmerce-bookings.php' );
	}


	/**
	 * Checks if Groups is active
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public function is_groups_active() {
		return wc_memberships()->is_plugin_active( 'groups.php' );
	}


	/**
	 * Checks is Subscriptions is active
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public function is_subscriptions_active() {
		return wc_memberships()->is_plugin_active( 'woocommerce-subscriptions.php' ) && class_exists( 'WC_Subscriptions' );
	}


	/**
	 * Checks if User Switching is active
	 *
	 * @since 1.6.0
	 * @return bool
	 */
	public function is_user_switching_active() {
		return wc_memberships()->is_plugin_active( 'user-switching.php' );
	}


}
