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
 * User Membership class
 *
 * This class represents a single user's membership, ie. a user belonging
 * to a User Membership. A single user can have multiple memberships.
 *
 * Technically, it's a wrapper around an instance of WP_Post with the
 * 'wc_user_membership' custom post type, similar to how WC_Product or
 * WC_Order are implemented.
 *
 * @since 1.0.0
 */
class WC_Memberships_User_Membership {


	/** @public int User Membership (post) ID */
	public $id;

	/** @public string User Membership plan id */
	public $plan_id;

	/** @public string User Membership plan */
	public $plan;

	/** @public string User Membership user (author) id */
	public $user_id;

	/** @public string User Membership (post) status */
	public $status;

	/** @public object User Membership post object */
	public $post;


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param mixed $id User Membership ID or post object
	 * @param int $user_id Optional. User / Member ID, used only for new memberships.
	 */
	public function __construct( $id, $user_id = null ) {

		if ( ! $id ) {
			return;
		}

		// Get user membership post object by ID
		if ( is_numeric( $id ) ) {
			$this->post = get_post( $id );
		}

		// Initialize from post object
		else if ( is_object( $id ) ) {
			$this->post = $id;
		}

		// Load in post data
		if ( $this->post ) {

			$this->id      = $this->post->ID;
			$this->user_id = $this->post->post_author;
			$this->plan_id = $this->post->post_parent;
			$this->status  = $this->post->post_status;
		}

		// Or at least user ID, if provided
		else if ( $user_id ) {
			$this->user_id = $user_id;
		}

	}


	/**
	 * Get the ID
	 *
	 * @since 1.0.0
	 * @return int User Membership ID
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Get the user ID
	 *
	 * @since 1.0.0
	 * @return int User ID
	 */
	public function get_user_id() {
		return $this->user_id;
	}


	/**
	 * Get the plan ID
	 *
	 * @since 1.0.0
	 * @return int Membership Plan Id
	 */
	public function get_plan_id() {
		return $this->plan_id;
	}


	/**
	 * Get the plan object
	 *
	 * @since 1.0.0
	 * @return WC_Memberships_Membership_Plan
	 */
	public function get_plan() {

		if ( ! $this->plan ) {
			$this->plan = wc_memberships_get_membership_plan( $this->get_plan_id() );
		}

		return $this->plan;
	}


	/**
	 * Return the membership status without wc- internal prefix
	 *
	 * @since 1.0.0
	 * @return string Status slug
	 */
	public function get_status() {
		return 'wcm-' === substr( $this->status, 0, 4 ) ? substr( $this->status, 4 ) : $this->status;
	}


	/**
	 * Format date
	 *
	 * @since 1.0.0
	 * @param string $date Date string, in 'mysql' format
	 * @param string $format Optional. Format to use. Defaults to 'mysql'
	 * @return string Formatted date
	 */
	private function format_date( $date, $format = 'mysql' ) {

		switch ( $format ) {

			case 'mysql':
				$value = $date;
			break;

			case 'timestamp':
				$value = strtotime( $date );
			break;

			default:
				$value = date( $format, strtotime( $date ) );
			break;

		}

		return $value;
	}


	/**
	 * Get the membership start datetime
	 *
	 * @since 1.0.0
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return string Start date
	 */
	public function get_start_date( $format = 'mysql' ) {

		$date = get_post_meta( $this->get_id(), '_start_date', true );
		return $date ? $this->format_date( $date, $format ) : null;
	}


	/**
	 * Get the membership start local datetime
	 *
	 * @since 1.3.8
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return int|string
	 */
	public function get_local_start_date( $format = 'mysql' ) {

		// get the date timestamp
		$date = $this->get_start_date( 'timestamp' );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships()->adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Get the membership end datetime
	 *
	 * @since 1.0.0
	 * @param string $format Optional. Defaults to 'mysql'
	 * @param bool $include_paused Optional. Whether to include the time this membership
	 *                             has been paused. Defaults to true.
	 * @return string End date
	 */
	public function get_end_date( $format = 'mysql', $include_paused = true ) {

		$date = get_post_meta( $this->get_id(), '_end_date', true );

		// Adjust end/expiry date if paused date exists
		if ( $date && $include_paused && $paused_date = $this->get_paused_date( 'timestamp' ) ) {

			$difference    = current_time( 'timestamp', true ) - $paused_date;
			$end_timestamp = strtotime( $date ) + $difference;

			$date = date( 'Y-m-d H:i:s', $end_timestamp );
		}

		return $date ? $this->format_date( $date, $format ) : null;
	}


	/**
	 * Get the membership end local datetime
	 *
	 * @since 1.3.8
	 * @param string $format Optional. Defaults to 'mysql'
	 * @param bool $include_paused Optional. Whether to include the time this membership
	 *                             has been paused. Defaults to true.
	 * @return int|string
	 */
	public function get_local_end_date( $format = 'mysql', $include_paused = true ) {

		// get the date timestamp
		$date = $this->get_end_date( 'timestamp', $include_paused );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships()->adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Get the membership paused datetime
	 *
	 * @since 1.0.0
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return string Paused date
	 */
	public function get_paused_date( $format = 'mysql' ) {

		$date = get_post_meta( $this->get_id(), '_paused_date', true );
		return $date ? $this->format_date( $date, $format ) : null;
	}


	/**
	 * Get the membership end local datetime
	 *
	 * @since 1.3.8
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return int|string
	 */
	public function get_local_paused_date( $format = 'mysql' ) {

		// get the date timestamp
		$date = $this->get_paused_date( 'timestamp' );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships()->adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Set the membership end datetime
	 *
	 * @since 1.0.0
	 * @param string $date End date either as a unix timestamp or mysql datetime string. Defaults to empty string.
	 */
	public function set_end_date( $date = '' ) {

		$end_timestamp = $date ? strtotime( $date ) : '';

		// Update end date in post meta
		update_post_meta( $this->get_id(), '_end_date', $end_timestamp ? date( 'Y-m-d H:i:s', $end_timestamp ) : '' );

		$hook_args = array( $this->get_id() );

		// Unschedule any previous expiry hooks
		if ( $scheduled = wp_next_scheduled( 'wc_memberships_user_membership_expiry', $hook_args  ) ) {
			wp_unschedule_event( $scheduled, 'wc_memberships_user_membership_expiry', $hook_args );
		}

		// Schedule the expiry hook, if there is an end date
		if ( $end_timestamp && $end_timestamp > current_time( 'timestamp', true ) ) {
			wp_schedule_single_event( $end_timestamp, 'wc_memberships_user_membership_expiry', $hook_args );
		}
	}


	/**
	 * Get the order ID that granted access
	 *
	 * @since 1.0.0
	 * @return int Order ID
	 */
	public function get_order_id() {
		return get_post_meta( $this->get_id(), '_order_id', true );
	}


	/**
	 * Get the order that granted access
	 *
	 * @since 1.0.0
	 * @return WC_Order Instance of WC_Order
	 */
	public function get_order() {

		if ( ! $this->get_order_id() ) {
			return null;
		}

		return wc_get_order( $this->get_order_id() );
	}


	/**
	 * Get the product ID that granted access
	 *
	 * @since 1.0.0
	 * @return int Product ID
	 */
	public function get_product_id() {
		return get_post_meta( $this->get_id(), '_product_id', true );
	}


	/**
	 * Get the product that granted access
	 *
	 * @since 1.0.0
	 * @return WC_Product|null Instance of WC_Product
	 */
	public function get_product() {

		if ( ! $this->get_product_id() ) {
			return null;
		}

		return wc_get_product( $this->get_product_id() );
	}


	/**
	 * Check if membership has been cancelled
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is cancelled, false otherwise
	 */
	public function is_cancelled() {
		return 'cancelled' == $this->get_status();
	}


	/**
	 * Check if membership is expired
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is expired, false otherwise
	 */
	public function is_expired() {
		return 'expired' == $this->get_status();
	}


	/**
	 * Check if membership is paused
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is paused, false otherwise
	 */
	public function is_paused() {
		return 'paused' == $this->get_status();
	}


	/**
	 * Check if membership has started, but not expired
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is in active period, false otherwise
	 */
	public function is_in_active_period() {

		$start = $this->get_start_date( 'timestamp' );
		$now   = current_time( 'timestamp', true );
		$end   = $this->get_end_date( 'timestamp' );

		return ( $start ? $start <= $now : true ) && ( $end ? $now <= $end : true );
	}


	/**
	 * Get cancel membership URL for frontend
	 *
	 * @since 1.0
	 * @return string Cancel URL
	 */
	public function get_cancel_membership_url() {

		$cancel_endpoint = wc_get_page_permalink( 'myaccount' );

		if ( false === strpos( $cancel_endpoint, '?' ) ) {
			$cancel_endpoint = trailingslashit( $cancel_endpoint );
		}

		/**
		 * Filter the cancel membership URL
		 *
		 * @since 1.0.0
		 * @param string $url
		 * @param WC_Memberships_User_Membership $user_membership
		 */
		return apply_filters( 'wc_memberships_get_cancel_membership_url', wp_nonce_url( add_query_arg( array( 'cancel_membership' => 'true', 'user_membership_id' => $this->get_id() ), $cancel_endpoint ), 'wc_memberships-cancel_membership' ), $this );
	}


	/**
	 * Get renew membership URL for frontend
	 *
	 * @since 1.0
	 * @return string Renew URL
	 */
	public function get_renew_membership_url() {

		$renew_endpoint = wc_get_page_permalink( 'myaccount' );

		if ( false === strpos( $renew_endpoint, '?' ) ) {
			$renew_endpoint = trailingslashit( $renew_endpoint );
		}

		/**
		 * Filter the renew membership URL
		 *
		 * @since 1.0.0
		 * @param string $url
		 * @param WC_Memberships_User_Membership $user_membership
		 */
		return apply_filters( 'wc_memberships_get_renew_membership_url', wp_nonce_url( add_query_arg( array( 'renew_membership' => 'true', 'user_membership_id' => $this->get_id() ), $renew_endpoint ), 'wc_memberships-renew_membership' ), $this );
	}


	/**
	 * Get notes
	 *
	 * @since 1.0.0
	 * @param string $filter Optional: 'customer' or 'private', default 'all'
	 * @param int $paged Optional: pagination
	 * @return WP_Comment[] Array of comment (membership notes) objects
	 */
	public function get_notes( $filter = 'all', $paged = 1 ) {

		$args = array(
			'post_id' => $this->get_id(),
			'approve' => 'approve',
			'type'    => 'user_membership_note',
			'paged'   => intval( $paged ),
		);

		remove_filter( 'comments_clauses', array( wc_memberships()->user_memberships, 'exclude_membership_notes' ), 10 );

		$comments = get_comments( $args );
		$notes    = array();

		if ( in_array( $filter, array( 'customer', 'private' ) ) ) {

			foreach ( $comments as $note ) {

				$notified = get_comment_meta( $note->comment_ID, 'notified', true );

				if ( $notified && 'customer' == $filter )  {
					array_push( $notes, $note );
				} elseif ( ! $notified && 'private' == $filter ) {
					array_push( $notes, $note );
				}
			}

		} else {

			$notes = $comments;
		}

		return $notes;
	}


	/**
	 * Add note
	 *
	 * @since 1.0.0
	 * @param string $note Note to add
	 * @param bool $notify Optional. Whether to notify member or not. Defaults to false
	 * @return int Note (comment) ID
	 */
	public function add_note( $note, $notify = false ) {

		$note = trim( $note );

		if ( is_user_logged_in() && current_user_can( 'edit_post', $this->get_id() ) ) {

			$user                 = get_user_by( 'id', get_current_user_id() );
			$comment_author       = $user->display_name;
			$comment_author_email = $user->user_email;

		} else {

			$comment_author       = __( 'WooCommerce', 'woocommerce-memberships' );

			$comment_author_email = strtolower( __( 'WooCommerce', 'woocommerce-memberships' ) ) . '@';
			$comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', $_SERVER['HTTP_HOST'] ) : 'noreply.com';

			$comment_author_email = sanitize_email( $comment_author_email );
		}

		$comment_post_ID    = $this->get_id();
		$comment_author_url = '';
		$comment_content    = $note;
		$comment_agent      = 'WooCommerce';
		$comment_type       = 'user_membership_note';
		$comment_parent     = 0;
		$comment_approved   = 1;

		/**
		 * Filter new user membership note data
		 *
		 * @since 1.0.0
		 * @param array $data
		 * @param array $extra
		 */
		$commentdata = apply_filters( 'wc_memberships_new_user_membership_note_data', compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_agent', 'comment_type', 'comment_parent', 'comment_approved' ), array( 'user_membership_id' => $this->get_id(), 'notify' => $notify ) );

		$comment_id = wp_insert_comment( $commentdata );

		add_comment_meta( $comment_id, 'notified', $notify );

		/**
		 * Fires after a new membership note is added
		 *
		 * @since 1.0.0
		 * @param array $data
		 */
		do_action( 'wc_memberships_new_user_membership_note', array(
			'user_membership_id' => $this->get_id(),
			'membership_note'    => $note,
			'notify'             => $notify,
		) );

		return $comment_id;
	}


	/**
	 * Pause membership
	 *
	 * @since 1.0.0
	 * @param string $note Optional note to add
	 */
	public function pause_membership( $note = null ) {

		$this->update_status( 'paused', $note ? $note : __( 'Membership paused.', 'woocommerce-memberships' ) );
		update_post_meta( $this->get_id(), '_paused_date', current_time( 'mysql', true ) );
	}


	/**
	 * Cancel membership
	 *
	 * @since 1.0.0
	 * @param string $note Optional note to add
	 */
	public function cancel_membership( $note = null ) {
		$this->update_status( 'cancelled', $note ? $note : __( 'Membership cancelled.', 'woocommerce-memberships' ) );
	}


	/**
	 * Activate membership
	 *
	 * @since 1.0.0
	 * @param string $note Optional note to add
	 */
	public function activate_membership( $note = null ) {

		if ( ! $note ) {
			$note = $this->is_paused() ?
							__( 'Membership resumed.', 'woocommerce-memberships' ) :
							__( 'Membership activated.', 'woocommerce-memberships' );
		}

		$this->update_status( 'active', $note );
	}


	/**
	 * Returns true if the membership has the given status
	 *
	 * @since 1.0.0
	 * @param string|array $status single status or array of statuses
	 * @return bool
	 */
	public function has_status( $status ) {
		return apply_filters( 'woocommerce_memberships_membership_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
	}


	/**
	 * Updates status of membership
	 *
	 * @param string $new_status Status to change the order to. No internal wcm- prefix is required.
	 * @param string $note (default: '') Optional note to add
	 */
	public function update_status( $new_status, $note = '' ) {

		if ( ! $this->get_id() ) {
			return;
		}

		// Standardise status names.
		$new_status = 'wcm-' === substr( $new_status, 0, 4 ) ? substr( $new_status, 4 ) : $new_status;
		$old_status = $this->get_status();

		// Get valid statuses
		$valid_statuses = wc_memberships_get_user_membership_statuses();

		// Only update if they differ - and ensure post_status is a 'wcm' status.
		if ( $new_status !== $old_status && in_array( 'wcm-' . $new_status, array_keys( $valid_statuses ) ) ) {

			// Note will be added to the membership by the general User_Memberships utility class,
			// so that we add only 1 note instead of 2 when updating the status.
			wc_memberships()->user_memberships->set_membership_status_transition_note( $note );

			// Update the order
			wp_update_post( array( 'ID' => $this->get_id(), 'post_status' => 'wcm-' . $new_status ) );

			$this->status = 'wcm-' . $new_status;
		}
	}


}
