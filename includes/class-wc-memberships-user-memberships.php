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
 * User Memberships class
 *
 * This class handles general user memberships related functionality
 *
 * @since 1.0.0
 */
class WC_Memberships_User_Memberships {


	/** @var string helper pending note for a user membership */
	private $membership_status_transition_note;


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		require_once( wc_memberships()->get_plugin_path() .'/includes/class-wc-memberships-user-membership.php' );

		add_filter( 'wp_insert_post_data', array( $this, 'user_membership_post_date' ) );
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
		add_action( 'wc_memberships_user_membership_expiry', array( $this, 'expire_user_membership' ) );

		add_action( 'save_post_wc_user_membership', array( $this, 'save_user_membership' ), 10, 3 );
		add_action( 'delete_user',  array( $this, 'delete_user_memberships' ) );
		add_action( 'trashed_post', array( $this, 'handle_order_trashed' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_order_refunded' ) );

		add_filter( 'comments_clauses', array( $this, 'exclude_membership_notes' ), 10, 1 );
		add_action( 'comment_feed_join', array( $this, 'exclude_membership_notes_from_feed_join' ) );
		add_action( 'comment_feed_where', array( $this, 'exclude_membership_notes_from_feed_where' ) );
	}


	/**
	 * Get all user memberships
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional. Defaults to current user.
	 * @param array $args
	 * @return WC_Memberships_User_Membership[]|null array of user memberships
	 */
	public function get_user_memberships( $user_id = null, $args = array() ) {

		$defaults = array(
			'status' => 'any',
		);
		$args = wp_parse_args( $args, $defaults );

		// Add the wcm- prefix for the status if it's not "any"
		foreach ( (array) $args['status'] as $index => $status ) {

			if ( 'any' !== $status ) {
				$args['status'][$index] = 'wcm-' . $status;
			}
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return null;
		}

		$posts_args = array(
			'author' => $user_id,
			'post_type'   => 'wc_user_membership',
			'post_status' => $args['status'],
			'nopaging'    => true,
		);

		$posts = get_posts( $posts_args );
		$user_memberships = array();

		if ( ! empty( $posts ) ) {

			foreach ( $posts as $post ) {
				$user_memberships[] = new WC_Memberships_User_Membership( $post );
			}
		}

		return ! empty( $user_memberships ) ? $user_memberships : null;
	}


	/**
	 * Get user's membership
	 * Supports getting user membership by membership ID, Post object
	 * or a combination of the user ID and membership plan id/slug/Post object.
	 *
	 * If no $id is provided, defaults to getting the membership for the current user.
	 *
	 * @since 1.0.0
	 * @param mixed $id Optional. Post object or post ID of the user membership, or user ID
	 * @param mixed $plan Optional. Membership Plan slug, post object or related post ID
	 * @return WC_Memberships_User_Membership|bool false on failure
	 */
	public function get_user_membership( $id = null, $plan = null ) {

		// If a plan is provided, try to find user membership using user ID + plan ID
		if ( $plan ) {

			$user_id         = $id ? $id : get_current_user_id();
			$membership_plan = wc_memberships_get_membership_plan( $plan );

			// Bail out if no user ID or membership plan
			if ( ! $user_id || ! $membership_plan ) {
				return false;
			}

			$args = array(
				'author'      => $user_id,
				'post_type'   => 'wc_user_membership',
				'post_parent' => $membership_plan->get_id(),
				'post_status' => 'any',
			);

			$user_memberships = get_posts( $args );
			$post             = ! empty( $user_memberships ) ? $user_memberships[0] : null;
		}

		// Otherwise, try to get user membership directly
		else {

			$post = $id;

			// Get from globals
			if ( false === $post ) {
				$post = $GLOBALS['post'];
			}

			// Try getting by ID
			elseif ( is_numeric( $post ) ) {
				$post = get_post( $post );
			}

			// Try getting from WC_Memberships_User_Membership instance
			elseif ( $post instanceof WC_Memberships_User_Membership ) {
				$post = get_post( $post->get_id() );
			}

			// Not a post intance? No go.
			elseif ( ! ( $post instanceof WP_Post ) ) {
				$post = false;
			}

		}

		// If no acceptable post is found, bail out
		if ( ! $post || 'wc_user_membership' !== get_post_type( $post ) ) {
			return false;
		}

		return new WC_Memberships_User_Membership( $post );
	}


	/**
	 * Get user membership by order ID
	 *
	 * @since 1.0.1
	 * @param int|WC_Order $order Order object or ID
	 * @return null|WC_Memberships_User_Membership
	 */
	public function get_user_membership_by_order_id( $order ) {
		global $wpdb;

		if ( ! $order ) {
			return;
		}

		$id = is_object( $order ) ? $order->id : $order;

		$user_membership_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT post_id FROM $wpdb->postmeta pm
			LEFT JOIN $wpdb->posts p ON p.ID = pm.post_id
			WHERE pm.meta_key = '_order_id'
			AND pm.meta_value = %d
			AND p.post_type = 'wc_user_membership'
		", $id ) );

		if ( ! $user_membership_id ) {
			return;
		}

		return $this->get_user_membership( $user_membership_id );
	}


	/**
	 * Check if user is a member of a particular membership plan
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional. Defaults to current user.
	 * @param int|string $membership_plan Optional. Membership plan ID or slug
	 * @return bool True, if is a member, false otherwise
	 */
	public function is_user_member( $user_id = null, $membership_plan ) {

		$user_membership = $this->get_user_membership( $user_id, $membership_plan );
		return (bool) $user_membership;
	}


	/**
	 * Check if user is an active member of a particular membership plan
	 *
	 * @since 1.0.0
	 * @param int $user_id Optional. Defaults to current user.
	 * @param int|string $membership_plan Optional. Membership plan ID or slug
	 * @return bool True, if is a member, false otherwise
	 */
	public function is_user_active_member( $user_id = null, $membership_plan ) {

		$user_membership = $this->get_user_membership( $user_id, $membership_plan );

		if ( ! $user_membership ) {
			return false;
		}

		return ! $user_membership->is_cancelled() && ! $user_membership->is_expired() && ! $user_membership->is_paused() && $user_membership->is_in_active_period();
	}


	/**
	 * Get all user membership statuses
	 *
	 * @since 1.0.0
	 * @return array associative array of statuses
	 */
	public function get_user_membership_statuses() {

		$statuses = array(

			'wcm-active' => array(
				'label'       => _x( 'Active', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Active Membership(s) */
				'label_count' => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-complimentary' => array(
				'label'       => _x( 'Complimentary', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Complimentary Membership(s) */
				'label_count' => _n_noop( 'Complimentary <span class="count">(%s)</span>', 'Complimentary <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-pending' => array(
				'label'       => _x( 'Pending Cancellation', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Membership(s) Pending Cancellation */
				'label_count' => _n_noop( 'Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-paused' => array(
				'label'       => _x( 'Paused', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Paused Membership(s) */
				'label_count' => _n_noop( 'Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-expired' => array(
				'label'       => _x( 'Expired', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Expired Membership(s) */
				'label_count' => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

			'wcm-cancelled' => array(
				'label'       => _x( 'Cancelled', 'Membership Status', 'woocommerce-memberships' ),
				/* translators: Cancelled Membership(s) */
				'label_count' => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woocommerce-memberships' ),
			),

		);

		/**
		 * Filter user membership statuses
		 *
		 * @since 1.0.0
		 * @param array $statuses Associative array of statuses
		 * @return array
		 */
		return apply_filters( 'wc_memberships_user_membership_statuses', $statuses );
	}


	/**
	 * Adjust new user membership post data
	 *
	 * @since 1.0.0
	 * @param array $data Original post data
	 * @return array $data Modified post data
	 */
	public function user_membership_post_date( $data ) {

		if ( 'wc_user_membership' == $data['post_type'] ) {

			// Password-protected user membership posts
			if ( ! $data['post_password'] ) {
				$data['post_password'] = uniqid( 'um_' );
			}

			// Make sure the passed in user ID is used as post author
			if ( 'auto-draft' == $data['post_status'] && isset( $_GET['user'] ) ) {
				$data['post_author'] = absint( $_GET['user'] );
			}
		}

		return $data;
	}


	/**
	 * Handle post status transitions for user memberships
	 *
	 * @since 1.0.0
	 * @param string $new_status New status slug
	 * @param string $old_status Old status slug
	 * @param WP_Post $post Related WP_Post object
	 */
	public function transition_post_status( $new_status, $old_status, WP_Post $post ) {

		if ( 'wc_user_membership' != $post->post_type || $new_status == $old_status ) {
			return;
		}

		// Skip for new posts and auto drafts
		if ( 'new' == $old_status || 'auto-draft' == $old_status ) {
			return;
		}

		$user_membership = $this->get_user_membership( $post );

		$old_status = str_replace( 'wcm-', '', $old_status );
		$new_status = str_replace( 'wcm-', '', $new_status );

		/* translators: Membership status changed from status A (%1$s) to status B (%2$s) */
		$status_note   = sprintf( __( 'Membership status changed from %1$s to %2$s.', 'woocommerce-memberships' ), wc_memberships_get_user_membership_status_name( $old_status ), wc_memberships_get_user_membership_status_name( $new_status ) );
		$optional_note = $this->get_membership_status_transition_note();

		// prepend optional note to status note, if provided
		$note = $optional_note ? $optional_note . ' ' . $status_note : $status_note;

		$user_membership->add_note( $note );

		switch ( $new_status ) {

			case 'cancelled':
				update_post_meta( $user_membership->get_id(), '_cancelled_date', current_time( 'mysql', true ) );
			break;

			case 'paused':
				update_post_meta( $user_membership->get_id(), '_paused_date', current_time( 'mysql', true ) );
			break;

			case 'active':

				// Save the new membership end date and remove the paused date.
				// This means that if the membership was paused, or, for example,
				// paused and then cancelled, and then re-activated, the time paused
				// will be added to the expiry date, so that the end date is pushed back.
				if ( $paused_date = $user_membership->get_paused_date() ) {

					$user_membership->set_end_date( $user_membership->get_end_date() );
					delete_post_meta( $user_membership->get_id(), '_paused_date' );
				}

			break;

		}

		/**
		 * Fires when user membership status is updated
		 *
		 * @since 1.0.0
		 * @param WC_Memberships_User_Membership $user_membership
		 * @param string $old_status Old status, without the wcm- prefix
		 * @param string $new_status New status, without the wcm- prefix
		 */
		do_action( 'wc_memberships_user_membership_status_changed', $user_membership, $old_status, $new_status );
	}


	/**
	 * Set membership status transition note
	 *
	 * Set a note to be saved along with the general "status changed from %s to %s" note
	 * when the status of a user membership changes.
	 *
	 * @since 1.0.0
	 * @param string $note Note
	 */
	public function set_membership_status_transition_note( $note ) {
		$this->membership_status_transition_note = $note;
	}


	/**
	 * Get membership status transition note
	 *
	 * Gets the note and resets it, so it does not interfere with
	 * any following status transitions.
	 *
	 * @since 1.0.0
	 * @return string $note Note
	 */
	public function get_membership_status_transition_note() {

		$note = $this->membership_status_transition_note;
		$this->membership_status_transition_note = null;

		return $note;
	}


	/**
	 * Expire a user membership
	 *
	 * @since 1.0.0
	 * @param int $id User membership ID
	 */
	public function expire_user_membership( $id ) {

		$user_membership = $this->get_user_membership( $id );

		if ( ! $user_membership ) {
			return;
		}

		$user_membership->update_status( 'expired', __( 'Membership expired.', 'woocommerce-memberships' ) );
	}


	/**
	 * Callback for save_post when a user membership is created or updated
	 *
	 * Fires wc_memberships_user_membership_created action
	 *
	 * @since 1.3.8
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param bool $update
	 */
	public function save_user_membership( $post_id, $post, $update ) {

		$user_membership = wc_memberships_get_user_membership( $post_id );

		if ( $user_membership ) {

			/**
			 * Fires after a user has been granted membership access
			 *
			 * This hook is similar to wc_memberships_user_membership_created
			 * but will also fire when a membership is manually created in admin
			 *
			 * @since 1.3.8
			 * @param WC_Memberships_Membership_Plan $membership_plan The plan that user was granted access to
			 * @param array $args
			 */
			do_action( 'wc_memberships_user_membership_saved', $user_membership->get_plan(), array(
				'user_id'            => $user_membership->get_user_id(),
				'user_membership_id' => $user_membership->get_id(),
				'is_update'          => $update,
			) );

		}

	}


	/**
	 * Delete user memberships if a user is deleted
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 */
	public function delete_user_memberships( $user_id ) {

		$user_memberships = $this->get_user_memberships( $user_id );

		if ( ! empty( $user_memberships ) ) {

			foreach ( $user_memberships as $membership ) {

				wp_delete_post( $membership->get_id() );
			}
		}
	}


	/**
	 * Cancel user membership when the associated order is trashed
	 *
	 * @since 1.0.1
	 * @param int $post_id
	 */
	public function handle_order_trashed( $post_id ) {
		global $wpdb;

		if ( 'shop_order' != get_post_type( $post_id ) ) {
			return;
		}

		if ( $user_membership = $this->get_user_membership_by_order_id( $post_id ) ) {
			$user_membership->cancel_membership( __( 'Membership cancelled because the associated order was trashed.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Cancel user membership when the associated order is refunded
	 *
	 * @since 1.0.1
	 * @param int $order_id
	 */
	public function handle_order_refunded( $order_id ) {
		global $wpdb;

		if ( 'shop_order' != get_post_type( $order_id ) ) {
			return;
		}

		if ( $user_membership = $this->get_user_membership_by_order_id( $order_id ) ) {
			$user_membership->cancel_membership( __( 'Membership cancelled because the associated order was refunded.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Exclude user membership notes from queries and RSS
	 *
	 * @since 1.0.0
	 * @param array $clauses
	 * @return array
	 */
	public function exclude_membership_notes( $clauses ) {
		global $wpdb, $typenow;

		if ( is_admin() && $typenow == 'wc_user_membership' && current_user_can( 'manage_woocommerce' ) ) {
			return $clauses; // Don't hide when viewing user memberships in admin
		}

		if ( ! $clauses['join'] ) {
			$clauses['join'] = '';
		}

		if ( ! strstr( $clauses['join'], "JOIN $wpdb->posts" ) ) {
			$clauses['join'] .= " LEFT JOIN $wpdb->posts ON comment_post_ID = $wpdb->posts.ID ";
		}

		if ( $clauses['where'] ) {
			$clauses['where'] .= ' AND ';
		}

		$clauses['where'] .= " $wpdb->posts.post_type <> 'wc_user_membership' ";

		return $clauses;
	}


	/**
	 * Exclude user membership notes from queries and RSS
	 *
	 * @since 1.0.0
	 * @param string $join
	 * @return string
	 */
	public function exclude_membership_notes_from_feed_join( $join ) {
		global $wpdb;

		if ( ! strstr( $join, $wpdb->posts ) ) {
			$join = " LEFT JOIN $wpdb->posts ON $wpdb->comments.comment_post_ID = $wpdb->posts.ID ";
		}

		return $join;
	}


	/**
	 * Exclude user membership notes from queries and RSS
	 *
	 * @since 1.0.0
	 * @param string $where
	 * @return string
	 */
	public function exclude_membership_notes_from_feed_where( $where ) {
		global $wpdb;

		if ( $where ) {
			$where .= ' AND ';
		}

		$where .= " $wpdb->posts.post_type <> 'wc_user_membership' ";

		return $where;
	}


}
