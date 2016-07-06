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
 * Membership Plans class
 *
 * This class handles general membership plans related functionality
 *
 * @since 1.0.0
 */
class WC_Memberships_Membership_Plans {


	/** @var array helper for lazy membership plans getter */
	private $membership_plans = array();


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		require_once( wc_memberships()->get_plugin_path() . '/includes/class-wc-memberships-membership-plan.php' );

		add_action( 'delete_post', array( $this, 'delete_related_data' ) );
	}

	/**
	 * Get a single membership plan
	 *
	 * @since 1.0.0
	 * @param mixed $post Post object or post ID of the membership plan.
	 * @return WC_Memberships_Membership_Plan|bool false on failure
	 */
	public function get_membership_plan( $post = false ) {

		// Get from globals
		if ( false === $post ) {
			$post = $GLOBALS['post'];
		}

		// Try getting by ID
		elseif ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		// Try getting from WC_Memberships_Membership_Plan instance
		elseif ( $post instanceof WC_Memberships_Membership_Plan ) {
			$post = get_post( $post->get_id() );
		}

		// Try getting by slug
		elseif ( is_string( $post ) ) {

			$posts = get_posts( array(
				'name'           => $post,
				'post_type'      => 'wc_membership_plan',
				'posts_per_page' => 1,
			));

			if ( ! empty( $posts ) ) {
				$post = $posts[0];
			}
		}

		// Not a post intance? No go.
		elseif ( ! ( $post instanceof WP_Post ) ) {
			$post = false;
		}

		// If no acceptable post is found, bail out
		if ( ! $post || 'wc_membership_plan' !== get_post_type( $post ) ) {
			return false;
		}

		return new WC_Memberships_Membership_Plan( $post );
	}


	/**
	 * Get all membership plans
	 *
	 * @since 1.0.0
	 * @param array $args Optional array of arguments. Same as for get_posts.
	 * @return WC_Memberships_Membership_Plan[] $plans Array of membership plans
	 */
	public function get_membership_plans( $args = array() ) {

		$defaults = array(
			'posts_per_page' => -1,
		);

		$args = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'wc_membership_plan';

		// Unique key for caching the applied rule results
		$cache_key = http_build_query( $args );

		if ( ! isset( $this->membership_plans[ $cache_key ] ) ) {

			$membership_plan_posts = get_posts( $args );

			$this->membership_plans[ $cache_key ] = array();

			if ( ! empty( $membership_plan_posts ) ) {

				foreach ( $membership_plan_posts as $post ) {
					$this->membership_plans[ $cache_key ][] = wc_memberships_get_membership_plan( $post );
				}
			}
		}

		return $this->membership_plans[ $cache_key ];
	}


	/**
	 * Delete any related data if membership plan is deleted
	 *
	 * Deletes any related user memberships and plan rules
	 *
	 * @since 1.0.0
	 * @param int $post_id Deleted post ID
	 */
	public function delete_related_data( $post_id ) {
		global $wpdb;

		// Bail out if the post being deleted is not a membership plan
		if ( 'wc_membership_plan' !== get_post_type( $post_id ) ) {
			return;
		}

		// Find related membership IDs
		$membership_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $post_id ) );

		// Delete each membership plan
		if ( ! empty( $membership_ids ) ) {
			foreach ($membership_ids as $membership_id) {

				wp_delete_post( $membership_id, true );
			}
		}

		// Find related restriction rules and delete them
		$rules = (array) get_option( 'wc_memberships_rules' );

		foreach ( $rules as $key => $rule ) {

			// Remove related rule
			if ( $rule['membership_plan_id'] == $post_id ) {
				unset( $rules[ $key ] );
			}
		}

		update_option( 'wc_memberships_rules', array_values( $rules ) );
	}

}
