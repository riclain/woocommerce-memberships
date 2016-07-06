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
 * AJAX class
 *
 * @since 1.0.0
 */
class WC_Memberships_AJAX {


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Admin
		add_action( 'wp_ajax_wc_memberships_get_membership_expiration_date',  array( $this, 'get_membership_expiration_date' ) );
		add_action( 'wp_ajax_wc_memberships_json_search_posts',               array( $this, 'json_search_posts' ) );
		add_action( 'wp_ajax_wc_memberships_json_search_terms',               array( $this, 'json_search_terms' ) );
		add_action( 'wp_ajax_wc_memberships_add_user_membership_note',        array( $this, 'add_user_membership_note' ) );
		add_action( 'wp_ajax_wc_memberships_delete_user_membership_note',     array( $this, 'delete_user_membership_note' ) );
		add_action( 'wp_ajax_wc_memberships_transfer_user_membership',        array( $this, 'transfer_user_membership' ) );

		// Filter out grouped products from JSON search results
		add_filter( 'woocommerce_json_search_found_products', array( $this, 'filter_json_search_found_products' ) );
	}


	/**
	 * Get membership expiration date
	 *
	 * @since 1.3.8
	 */
	public function get_membership_expiration_date() {

		check_ajax_referer( 'get-membership-expiration', 'security' );

		if ( isset( $_POST['plan'] ) && isset( $_POST['start_date'] ) ) {

			$plan_id = intval( $_POST['plan'] );
			$plan    = wc_memberships_get_membership_plan( $plan_id );

			if ( $plan ) {

				$start_date     = strtotime( $_POST['start_date'] );
				$start_date_utc = wc_memberships()->adjust_date_by_timezone( $start_date );
				$end_date       = $plan->get_expiration_date( $start_date_utc );

				wp_send_json_success( $end_date );
			}

		}

		die();
	}


	/**
	 * Search for posts and echo json
	 *
	 * @since 1.0.0
	 */
	public function json_search_posts() {

		check_ajax_referer( 'search-posts', 'security' );

		$term      = (string) wc_clean( stripslashes( SV_WC_Helper::get_request( 'term' ) ) );
		$post_type = (string) wc_clean( SV_WC_Helper::get_request( 'post_type' ) );

		if ( empty( $term ) || empty( $post_type ) ) {
			die();
		}

		if ( is_numeric( $term ) ) {

			$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => array( 0, $term ),
				'fields'         => 'ids'
			);

		} else {

			$args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				's'              => $term,
				'fields'         => 'ids'
			);

		}

		$posts = get_posts( $args );

		$found_posts = array();

		if ( $posts ) {
			foreach ( $posts as $post ) {
				// TODO $post is an illegal array key type (WP_Post object)
				$found_posts[ $post ] = get_the_title( $post );
			}
		}

		/**
		 * Filter posts found for JSON (AJAX) search
		 *
		 * @since 1.0.0
		 * @param array $found_posts Array of the found posts
		 */
		$found_posts = apply_filters( 'wc_memberships_json_search_found_posts', $found_posts );

		wp_send_json( $found_posts );

	}


	/**
	 * Search for taxonomy terms and echo json
	 *
	 * @since 1.0.0
	 */
	public function json_search_terms() {

		check_ajax_referer( 'search-terms', 'security' );

		$term     = (string) wc_clean( stripslashes( SV_WC_Helper::get_request( 'term' ) ) );
		$taxonomy = (string) wc_clean( SV_WC_Helper::get_request( 'taxonomy' ) );

		if ( empty( $term ) || empty( $taxonomy ) ) {
			die();
		}

		if ( is_numeric( $term ) ) {

			$args = array(
				'hide_empty' => false,
				'include'    => array( 0, $term ),
			);

		} else {

			$args = array(
				'hide_empty' => false,
				'search'     => $term,
			);
		}

		$terms = get_terms( array( $taxonomy ), $args );

		$found_terms = array();

		if ( $terms ) {
			foreach ( $terms as $term ) {
				$found_terms[ $term->term_id ] = $term->name;
			}
		}

		/**
		 * Filter terms found for JSON (AJAX) search
		 *
		 * @since 1.0.0
		 * @param array $found_terms Array of the found terms
		 */
		$found_terms = apply_filters( 'wc_memberships_json_search_found_terms', $found_terms );

		wp_send_json( $found_terms );
	}


	/**
	 * Add user membership note
	 *
	 * @since 1.0.0
	 */
	public function add_user_membership_note() {

		check_ajax_referer( 'add-user-membership-note', 'security' );

		$post_id   = (int) $_POST['post_id'];
		$note_text = wp_kses_post( trim( stripslashes( $_POST['note'] ) ) );
		$notify    = isset( $_POST['notify'] ) && $_POST['notify'] == 'true';

		if ( $post_id > 0 ) {

			$user_membership = wc_memberships_get_user_membership( $post_id );
			$comment_id      = $user_membership->add_note( $note_text, $notify );
			$note            = get_comment( $comment_id );

			$plan            = $user_membership->get_plan();
			/* translators: Placeholder for plan name if a plan has been removed */
			$plan_name       = $plan ? $plan->get_name() : __( '[Plan removed]', 'woocommerce-memberships' );
			$note_classes    = get_comment_meta( $note->comment_ID, 'notified', true ) ? array( 'notified', 'note' ) : array( 'note' );

			echo '<div>';
			echo '<ul id="notes">';
			include( 'admin/meta-boxes/views/html-membership-note.php' );
			echo '</ul>';

			echo '<ul id="recent-activity">';
			include( 'admin/meta-boxes/views/html-membership-recent-activity-note.php' );
			echo '</ul>';
			echo '</div>';
		}

		exit;
	}


	/**
	 * Delete user membership note
	 *
	 * @since 1.0.0
	 */
	public function delete_user_membership_note() {

		check_ajax_referer( 'delete-user-membership-note', 'security' );

		$note_id = (int) $_POST['note_id'];

		if ( $note_id > 0 ) {
			wp_delete_comment( $note_id );
		}

		exit;
	}


	/**
	 * Remove grouped products from json search results
	 *
	 * @since 1.0.0
	 * @param array $products
	 * @return array $products
	 */
	public function filter_json_search_found_products( $products ) {

		// Remove grouped products
		if ( isset( $_REQUEST['screen'] ) && 'wc_membership_plan' == $_REQUEST['screen'] ) {
			foreach( $products as $id => $title ) {

				$product = wc_get_product( $id );

				if ( $product->is_type('grouped') ) {
					unset( $products[ $id ] );
				}
			}
		}

		return $products;
	}


	/**
	 * Transfer a membership from one user to another
	 *
	 * If successful also stores the previous users history
	 * in a membership post meta '_previous_owners'
	 *
	 * @since 1.4.0
	 */
	public function transfer_user_membership() {

		if ( isset( $_POST['prev_user'] ) && isset( $_POST['new_user'] ) && isset( $_POST['membership'] ) ) {

			$prev_user     = intval( $_POST['prev_user'] );
			$new_user      = intval( $_POST['new_user'] );
			$membership_id = intval( $_POST['membership'] );
			$membership    = wc_memberships_get_user_membership( $membership_id );

			if ( $membership && $membership->user_id == $prev_user ) {

				if ( get_user_by( 'id', $new_user ) ) {

					global $wpdb;

					$update = $wpdb->update( $wpdb->prefix . 'posts', array( 'post_author' =>  $new_user, ), array( 'id' => $membership_id, 'post_author' => $prev_user ),  array( '%d' ), array( '%d', '%d' ) );

					if ( false !== $update ) {

						$owners     = get_post_meta( $membership_id, '_previous_owners', true );
						$last_owner = array( current_time( 'timestamp', true ) => $prev_user );

						if ( ! empty( $owners ) && is_array( $owners ) ) {
							$owners = array_merge( $owners, $last_owner );
						} else {
							$owners = $last_owner;
						}

						// Store ownership history in a post meta
						update_post_meta( $membership_id, '_previous_owners', $owners );

						// Attach a membership note
						$membership->add_note(
							/* translators: Membership transferred from user A to user B */
							sprintf( __( 'Membership transferred from %1$s to %2$s.', 'woocommerce-memberships' ),
								get_user_by( 'id', $prev_user )->user_nicename,
								get_user_by( 'id', $new_user )->user_nicename
							)
						);

						wp_send_json_success( $owners );
					}
				}

			}

		}

		die();
	}


}
