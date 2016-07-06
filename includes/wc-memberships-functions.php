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
 * Encode a variable into JSON via wp_json_encode() if available,
 * fall back to json_encode otherwise
 *
 * `json_encode()` may fail and return `null` in some environments,
 * especially in installations with character encoding issues
 *
 * @internal
 *
 * @since 1.6.0
 * @param mixed $data Variable (usually an array or object) to encode as JSON
 * @param int $options Optional. Options to be passed to json_encode(). Default 0
 * @param int $depth Optional. Maximum depth to walk through $data. Must be greater than 0. Default 512
 * @return bool|string The JSON encoded string, or false if it cannot be encoded
 */
function wc_memberships_json_encode( $data, $options = 0, $depth = 512 ) {

	// TODO deprecate this as part of WooCommerce 2.7 compatibility release {FN 2016-05-27}
	// _deprecated_function( 'wc_memberships_json_encode', '1.6.0', 'wp_json_encode' );

	return function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $options, $depth ) : json_encode( $data, $options, $depth );
}


/**
 * Workaround the last day of month quirk in PHP's strtotime function
 *
 * Adding +1 month to the last day of the month can yield unexpected results with strtotime()
 * For example:
 * - 30 Jan 2013 + 1 month = 3rd March 2013
 * - 28 Feb 2013 + 1 month = 28th March 2013
 *
 * What humans usually want is for the charge to continue on the last day of the month.
 *
 * Copied from WooCommerce Subscriptions
 *
 * @since 1.6.0
 * @param int|string $from_timestamp Original timestamp to add months to
 * @param int $months_to_add Number of months to add to the timestamp
 * @return int corrected timestamp
 */
function wc_memberships_add_months_to_timestamp( $from_timestamp, $months_to_add ) {

	// bail out if there aren't months to add or is a non positive integer
	if ( (int) $months_to_add < 0 || ! is_numeric( $months_to_add ) ) {
		return $from_timestamp;
	}

	$first_day_of_month = date( 'Y-m', $from_timestamp ) . '-1';
	$days_in_next_month = date( 't', strtotime( "+ {$months_to_add} month", strtotime( $first_day_of_month ) ) );
	$next_timestamp     = 0;

	// it's the last day of the month
	// OR
	// number of days in next month is less than the the day of this month
	// (i.e. current date is 30th January, next date can't be 30th February)
	if ( date( 'd', $from_timestamp ) > $days_in_next_month || date( 'd m Y', $from_timestamp ) === date( 't m Y', $from_timestamp ) ) {

		for ( $i = 1; $i <= $months_to_add; $i++ ) {

			$next_month     = strtotime( '+ 3 days', $from_timestamp ); // Add 3 days to make sure we get to the next month, even when it's the 29th day of a month with 31 days
			$next_timestamp = $from_timestamp = strtotime( date( 'Y-m-t H:i:s', $next_month ) ); // NB the "t" to get last day of next month
		}

	// it's safe to just add a month
	} else {

		$next_timestamp = strtotime( "+ {$months_to_add} month", $from_timestamp );
	}

	return $next_timestamp;
}


/**
 * Adjust dates in UTC format
 *
 * Converts a UTC date to the corresponding date in another timezone
 *
 * @since 1.6.0
 * @param int|string $date Date in string or timestamp format
 * @param string $format Format to use in output
 * @param string $timezone Timezone to convert from
 * @return int|string
 */
function wc_memberships_adjust_date_by_timezone( $date, $format = 'mysql', $timezone = 'UTC' ) {

	if ( is_int( $date ) ) {
		$src_date = date( 'Y-m-d H:i:s', $date );
	} else {
		$src_date = $date;
	}

	if ( 'mysql' === $format ) {
		$format = 'Y-m-d H:i:s';
	}

	if ( 'UTC' === $timezone ) {
		$from_timezone = 'UTC';
		$to_timezone   = wc_timezone_string();
	} else {
		$from_timezone = $timezone;
		$to_timezone   = 'UTC';
	}

	$from_date = new DateTime( $src_date, new DateTimeZone( $from_timezone ) );
	$to_date   = new DateTimeZone( $to_timezone );
	$offset    = $to_date->getOffset( $from_date );

	// getTimestamp method not used here for PHP 5.2 compatibility
	$timestamp = (int) $from_date->format( 'U' );

	return 'timestamp' === $format ? $timestamp + $offset : date( $format, $timestamp + $offset );
}


/**
 * Creates a human readable list of an array
 *
 * @since 1.6.0
 * @param string[] $items array to list items of
 * @param string|void $conjunction optional. The word to join together the penultimate and last item. Defaults to 'or'
 * @return string e.g. "item1, item2, item3 or item4"
 */
function wc_memberships_list_items( $items, $conjunction = '' ) {

	if ( ! $conjunction ) {
		$conjunction = __( 'or', 'woocommerce-memberships' );
	}

	array_splice( $items, -2, 2, implode( ' ' . $conjunction . ' ', array_slice( $items, -2, 2 ) ) );
	return implode( ', ', $items );
}


/**
 * Check if purchasing products that grant access to a membership
 * in the same order allow to extend the length of the membership
 *
 * @since 1.6.0
 * @return bool
 */
function wc_memberships_cumulative_granting_access_orders_allowed() {
	return 'yes' === get_option( 'wc_memberships_allow_cumulative_access_granting_orders' );
}


/**
 * Get the label of a post type
 *
 * e.g. 'some_post-type' becomes 'Some Post Type Name'
 *
 * @since 1.6.2
 * @param \WP_Post $post
 * @return string
 */
function wc_memberships_get_content_type_name( $post ) {

	// sanity check
	if ( ! isset( $post->post_type ) ) {
		return '';
	}

	$post_type_object = get_post_type_object( $post->post_type );

	return ucwords( $post_type_object->labels->singular_name );
}
