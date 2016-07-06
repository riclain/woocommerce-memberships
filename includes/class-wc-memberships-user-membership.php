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
 * User Membership class
 *
 * This class represents a single user's membership, ie. a user belonging
 * to a User Membership. A single user can have multiple memberships.
 *
 * Technically, it's a wrapper around an instance of WP_Post with the
 * 'wc_user_membership' custom post type, similar to how \WC_Product
 * or \WC_Order are implemented.
 *
 * @since 1.0.0
 */
class WC_Memberships_User_Membership {


	/** @public int User Membership (post) ID */
	public $id;

	/** @public string User Membership plan id */
	public $plan_id;

	/** @public \WC_Memberships_Membership_Plan User Membership plan */
	public $plan;

	/** @public string User Membership user (author) id */
	public $user_id;

	/** @public string User Membership (post) status */
	public $status;

	/** @public \WP_Post User Membership post object */
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
	 * @return \WC_Memberships_Membership_Plan
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
		return 0 === strpos( $this->status, 'wcm-' ) ? substr( $this->status, 4 ) : $this->status;
	}


	/**
	 * Format date
	 *
	 * @since 1.0.0
	 * @param string $date Date string, in 'mysql' format
	 * @param string $format Optional. Format to use. Defaults to 'mysql'
	 * @return int|string Formatted date
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
	 * @return null|int|string Start date
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
	 * @return null|int|string
	 */
	public function get_local_start_date( $format = 'mysql' ) {

		// get the date timestamp
		$date = $this->get_start_date( 'timestamp' );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships_adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Set the membership start datetime
	 *
	 * @since 1.6.2
	 * @param string $date Date in MySQL format
	 */
	public function set_start_date( $date ) {

		update_post_meta( $this->get_id(), '_start_date', $date );
	}


	/**
	 * Get the membership end datetime
	 *
	 * @since 1.0.0
	 * @param string $format Optional. Defaults to 'mysql'
	 * @param bool $include_paused Optional. Whether to include the time this membership
	 *                             has been paused. Defaults to true.
	 * @return null|int|string
	 */
	public function get_end_date( $format = 'mysql', $include_paused = true ) {

		$date = get_post_meta( $this->get_id(), '_end_date', true );

		// adjust end/expiry date if paused date exists
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
	 * @return null|int|string
	 */
	public function get_local_end_date( $format = 'mysql', $include_paused = true ) {

		// get the date timestamp
		$date = $this->get_end_date( 'timestamp', $include_paused );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships_adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Set the membership end datetime
	 *
	 * @since 1.0.0
	 * @param string $date End date either as a unix timestamp or mysql datetime string. Defaults to empty string
	 */
	public function set_end_date( $date = '' ) {

		$end_timestamp = ! empty( $date ) ? strtotime( $date ) : '';
		$end_date      = '';

		if ( ! empty( $end_timestamp ) ) {
			$end_date = date( 'Y-m-d H:i:s', $end_timestamp );
		}

		// update end date in post meta
		update_post_meta( $this->get_id(), '_end_date', $end_date );

		$hook_args = array( $this->get_id() );

		// unschedule any previous expiry hooks
		if ( $scheduled = wp_next_scheduled( 'wc_memberships_user_membership_expiry', $hook_args  ) ) {
			wp_unschedule_event( $scheduled, 'wc_memberships_user_membership_expiry', $hook_args );
		}

		// schedule the expiry hook, if there is an end date
		if ( $end_timestamp && $end_timestamp > current_time( 'timestamp', true ) ) {
			wp_schedule_single_event( $end_timestamp, 'wc_memberships_user_membership_expiry', $hook_args );
		}
	}


	/**
	 * Get the membership cancelled datetime
	 *
	 * @since 1.6.2
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return null|int|string
	 */
	public function get_cancelled_date( $format = 'mysql' ) {

		$date = get_post_meta( $this->get_id(), '_cancelled_date', true );

		return $date ? $this->format_date( $date, $format ) : null;
	}


	/**
	 * Get the membership cancelled local datetime
	 *
	 * @since 1.6.2
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return null|int|string
	 */
	public function get_local_cancelled_date( $format = 'mysql' ) {

		// get the date timestamp
		$date = $this->get_cancelled_date( 'timestamp' );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships_adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Set the membership cancelled datetime
	 *
	 * @since 1.6.2
	 * @param string $date Date in MySQL format
	 */
	public function set_cancelled_date( $date ) {

		update_post_meta( $this->get_id(), '_cancelled_date', $date );
	}


	/**
	 * Get the membership paused datetime
	 *
	 * @since 1.0.0
	 * @param string $format Optional. Defaults to 'mysql'
	 * @return null|int|string Paused date
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
	 * @return null|int|string
	 */
	public function get_local_paused_date( $format = 'mysql' ) {

		// get the date timestamp
		$date = $this->get_paused_date( 'timestamp' );

		// adjust the date to the site's local timezone
		return $date ? wc_memberships_adjust_date_by_timezone( $date, $format ) : null;
	}


	/**
	 * Set the membership paused datetime
	 *
	 * @since 1.6.2
	 * @param string $date Date in MySQL format
	 */
	public function set_paused_date( $date ) {

		update_post_meta( $this->get_id(), '_paused_date', $date );
	}


	/**
	 * Removes the membership paused datetime information
	 *
	 * @since 1.6.2
	 */
	public function delete_paused_date() {

		delete_post_meta( $this->get_id(), '_paused_date' );
	}


	/**
	 * Get the memberships paused periods as an associative array of timestamps
	 *
	 * @since 1.6.2
	 * @return array Associative array of start => end ranges of paused intervals
	 */
	public function get_paused_intervals() {

		$intervals = get_post_meta( $this->get_id(), '_paused_intervals', true );

		return is_array( $intervals ) ? $intervals : array();
	}


	/**
	 * Add a record to the membership pausing registry
	 *
	 * @since 1.6.2
	 * @param string $interval Either 'start' or 'end'
	 * @param int $time A valid timestamp in UTC
	 */
	public function set_paused_interval( $interval, $time ) {

		if ( ! is_numeric( $time ) || (int) $time <= 0 ) {
			return;
		}

		$intervals = $this->get_paused_intervals();

		if ( 'start' === $interval ) {

			// sanity check to avoid overwriting an existing key
			if ( ! array_key_exists( $time, $intervals ) ) {
				$intervals[ (int) $time ] = '';
			}

		} elseif ( 'end' === $interval ) {

			if ( ! empty( $intervals ) ) {

				// get the last timestamp when the membership was paused
				end( $intervals );
				$last = key( $intervals );

				// sanity check to avoid overwriting an existing value
				if ( is_numeric( $last ) && empty( $intervals[ $last ] ) ) {
					$intervals[ (int) $last ] = (int) $time;
				}

			// this might be the case where a paused membership didn't have interval tracking yet
			} elseif ( $this->is_paused() && $paused_date = $this->get_paused_date( 'timestamp' ) ) {

				$intervals[ (int) $paused_date ] = (int) $time;
			}
		}

		update_post_meta( $this->get_id(), '_paused_intervals', $intervals );
	}


	/**
	 * Get the total active or inactive time of a membership
	 *
	 * @since 1.6.2
	 * @param string $type Either 'active' or 'inactive'
	 * @param string $format Optional, can be either 'timestamp' (default) or 'human'
	 * @return null|int|string
	 */
	private function get_total_time( $type, $format = 'timestamp' ) {

		$total  = null;
		$time   = 0; // time as 0 seconds
		$start  = $this->get_start_date( 'timestamp' );
		$pauses = $this->get_paused_intervals();

		// set 'time' as now or the most recent time when the membership was active
		if ( 'active' === $type ) {

			if ( $this->is_expired() ) {
				$time = $this->get_end_date( 'timestamp' );
			} elseif ( $this->is_cancelled() ) {
				$time = $this->get_cancelled_date( 'timestamp' );
			}

			if ( empty( $total ) ) {
				$time = current_time( 'timestamp', true );
			}
		}

		if ( ! empty( $pauses ) ) {

			end( $pauses );
			$last = key( $pauses );

			// if the membership is currently paused, add the time until now
			if ( isset( $pauses[ $last ] ) && '' === $pauses[ $last ] && $this->is_paused() ) {
				$pauses[ $last ] = current_time( 'timestamp', true );
			}

			reset( $pauses );

			$previous_start = (int) $start;

			foreach ( $pauses as $pause_start => $pause_end ) {

				// sanity check, see if there is a previous interval without an end record
				// or if the start record in the key is invalid
				if ( empty( $pause_end ) || $pause_start < $previous_start ) {
					continue;
				}

				if ( 'active' === $type ) {
					// subtract from the most recent active time paused intervals
					$time -= max( 0, (int) $pause_end - (int) $pause_start );
				} elseif ( 'inactive' === $type ) {
					// add up from 0s the time this membership has been inactive
					$time += max( 0, (int) $pause_end - (int) $pause_start );
				}

				$previous_start = (int) $pause_start;
			}
		}

		// get the total as a difference
		if ( 'active' === $type ) {
			$total = max( 0, $time - $start );
		} elseif ( 'inactive' === $type ) {
			$total = max( 0, $time );
		}

		// maybe humanize the output
		if ( is_int( $total ) && 'human' === $format ) {

			$time_diff = max( $start, $start + $total );
			$total     = $time_diff !== $start && $time_diff > 0 ? human_time_diff( $start, $time_diff ) : 0;
		}

		return $total;
	}


	/**
	 * Get the total amount of time the membership has been active
	 * since its start date
	 *
	 * @since 1.6.2
	 * @param string $format Optional, can be either 'timestamp' (default) or 'human'
	 *                       for a human readable span relative to the start date
	 * @return int|string
	 */
	public function get_total_active_time( $format = 'timestamp' ) {
		return $this->get_total_time( 'active', $format );
	}


	/**
	 * Get the total amount of time the membership has been inactive
	 * since its start date
	 *
	 * @since 1.6.2
	 * @param string $format Optional, can be either 'timestamp' (default) or 'human'
	 *                       for a human readable inactive time span
	 * @return int|string
	 */
	public function get_total_inactive_time( $format = 'timestamp' ) {
		return $this->get_total_time( 'inactive', $format );
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
	 * @return \WC_Order Instance of \WC_Order
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
	 * @return \WC_Product|null Instance of WC_Product
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
		return 'cancelled' === $this->get_status();
	}


	/**
	 * Check if membership is expired
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is expired, false otherwise
	 */
	public function is_expired() {
		return 'expired' === $this->get_status();
	}


	/**
	 * Check if membership is paused
	 *
	 * @since 1.0.0
	 * @return bool True, if membership is paused, false otherwise
	 */
	public function is_paused() {
		return 'paused' === $this->get_status();
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @return \WP_Comment[] Array of comment (membership notes) objects
	 */
	public function get_notes( $filter = 'all', $paged = 1 ) {

		$args = array(
			'post_id' => $this->get_id(),
			'approve' => 'approve',
			'type'    => 'user_membership_note',
			'paged'   => (int) $paged,
		);

		remove_filter( 'comments_clauses', array( wc_memberships()->get_user_memberships_instance(), 'exclude_membership_notes' ), 10 );

		$comments = get_comments( $args );
		$notes    = array();

		if ( in_array( $filter, array( 'customer', 'private' ), true ) ) {

			foreach ( $comments as $note ) {

				$notified = get_comment_meta( $note->comment_ID, 'notified', true );

				if ( $notified && 'customer' === $filter )  {
					$notes[] = $note;
				} elseif ( ! $notified && 'private' === $filter ) {
					$notes[] = $note;
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
		$this->set_paused_date( current_time( 'mysql', true ) );
	}


	/**
	 * Cancel membership
	 *
	 * @since 1.0.0
	 * @param string $note Optional note to add
	 */
	public function cancel_membership( $note = null ) {

		$this->update_status( 'cancelled', $note ? $note : __( 'Membership cancelled.', 'woocommerce-memberships' ) );
		$this->set_cancelled_date( current_time( 'mysql', true ) );
	}


	/**
	 * Expire membership
	 *
	 * @since 1.6.2
	 */
	public function expire_membership() {

		/**
		 * Confirm expire User Membership
		 *
		 * @since 1.5.4
		 * @param bool $expire True: expire this membership, False: retain, Default: true, expire it
		 * @param \WC_Memberships_User_Membership $user_membership The User Membership object
		 */
		if ( true === apply_filters( 'wc_memberships_expire_user_membership', true, $this ) ) {

			$this->update_status( 'expired', __( 'Membership expired.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Activate membership
	 *
	 * @since 1.0.0
	 * @param null|string $note Optional note to add
	 */
	public function activate_membership( $note = null ) {

		if ( empty( $note ) ) {

			if ( $this->is_paused() ) {

				$note = __( 'Membership resumed.', 'woocommerce-memberships' );

				$this->set_paused_interval( 'end', current_time( 'timestamp', true ) );

			} else {

				$note = __( 'Membership activated.', 'woocommerce-memberships' );
			}
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

		$has_status = ( ( is_array( $status ) && in_array( $this->get_status(), $status, true ) ) || $this->get_status() === $status );

		/**
		 * Filter if User Membership has a status
		 *
		 * @since 1.0.0
		 * @param bool $has_status Whether the User Membership has a certain status
		 * @param \WC_Memberships_User_Membership $user_membership Instance of the User Membership object
		 * @param array|string $status One (string) status or any statuses (array) to check
		 */
		return apply_filters( 'woocommerce_memberships_membership_has_status', $has_status, $this, $status );
	}


	/**
	 * Updates status of membership
	 *
	 * @since 1.0.0
	 * @param string $new_status Status to change the order to. No internal wcm- prefix is required.
	 * @param string $note (default: '') Optional note to add
	 */
	public function update_status( $new_status, $note = '' ) {

		if ( ! $this->get_id() ) {
			return;
		}

		// standardise status names
		$new_status = 0 === strpos( $new_status, 'wcm-' ) ? substr( $new_status, 4 ) : $new_status;
		$old_status = $this->get_status();

		// get valid statuses
		$valid_statuses = wc_memberships_get_user_membership_statuses();

		// only update if they differ - and ensure post_status is a 'wcm' status.
		if ( $new_status !== $old_status && array_key_exists( 'wcm-' . $new_status, $valid_statuses ) ) {

			// note will be added to the membership by the general User_Memberships utility class,
			// so that we add only 1 note instead of 2 when updating the status
			wc_memberships()->get_user_memberships_instance()->set_membership_status_transition_note( $note );

			// update the order
			wp_update_post( array(
				'ID'          => $this->get_id(),
				'post_status' => 'wcm-' . $new_status,
			) );

			$this->status = 'wcm-' . $new_status;
		}
	}


	/**
	 * Transfer the User Membership to another user
	 *
	 * If a transfer is successful it will also record
	 * the ownership passage in a post meta
	 *
	 * @since 1.6.0
	 * @param \WP_User|int $to_user User to transfer membership to
	 * @return bool Whether the transfer was successful
	 */
	public function transfer_ownership( $to_user ) {

		if ( is_numeric( $to_user ) ) {
			$to_user = get_user_by( 'id', (int) $to_user );
		}

		$user_membership_id = (int) $this->get_id();
		$previous_owner     = (int) $this->get_user_id();
		$new_owner          = $to_user;

		if ( ! $new_owner instanceof WP_User || ! $previous_owner || ! $user_membership_id ) {
			return false;
		}

		$updated = wp_update_post( array(
			'ID'          => $user_membership_id,
			'post_type'   => 'wc_user_membership',
			'post_author' => $new_owner->ID,
		) );

		if ( (int) $this->get_id() !== (int) $updated ) {
			return false;
		}

		$owners     = $this->get_previous_owners();
		$last_owner = array( current_time( 'timestamp', true ) => $previous_owner );

		$previous_owners = ! empty( $owners ) && is_array( $owners ) ? array_merge( $owners, $last_owner ) : $last_owner;

		update_post_meta( $user_membership_id, '_previous_owners', $previous_owners );

		$this->add_note(
			/* translators: Membership transferred from user A to user B */
			sprintf( __( 'Membership transferred from %1$s to %2$s.', 'woocommerce-memberships' ),
				get_user_by( 'id', $previous_owner )->user_nicename,
				$new_owner->user_nicename
			)
		);

		return true;
	}


	/**
	 * Get User Membership previous owners
	 *
	 * If the User Membership has been previously transferred
	 * from an user to another, this method will return its
	 * ownership history as an associative array of
	 * timestamps (time of transfer) and user ids
	 *
	 * @since 1.6.0
	 * @return false|array
	 */
	public function get_previous_owners() {
		return get_post_meta( $this->get_id(), '_previous_owners', true );
	}


}
