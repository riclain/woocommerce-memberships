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
 * Membership Plan Rule class
 *
 * This class represents a single membership plan rule, eg a content
 * restriction rule, purchasing discount rule, etc.
 *
 *
 * @since 1.0.0
 */
class WC_Memberships_Membership_Plan_Rule {


	/** @var array Rule data */
	private $data = array();


	/**
	 * Setup rule
	 *
	 * @since 1.0.0
	 * @param array $data Rule data
	 * @return \WC_Memberships_Membership_Plan_Rule
	 */
	public function __construct( array $data ) {

		$this->data = $data;
	}


	/**
	 * Meta-method for returning, setting, checking rule data, currently:
	 *
	 * + id
	 * + membership_plan_id
	 * + content_type
	 * + content_type_name
	 * + object_ids
	 * + access_schedule (content & product restriction rules only)
	 * + access_schedule_exclude_trial (content & product restriction rules only)
	 * + access_type (product restriction rules only)
	 * + discount_type (purchasing discount rules only)
	 * + discount_amount (purchasing discount rules only)
	 * + active (purchasing discount rules only)
	 *
	 * sample usage:
	 *
	 * `$email = $rule->get_id()`
	 *
	 * TODO Refactor this and avoid using __call, makes harder to test or navigate code in IDEs
	 *
	 * @since 1.0.0
	 * @param string $method called method
	 * @param array $args method arguments
	 * @return string|bool
	 */
	public function __call( $method, $args ) {

		// get_* method
		if ( 0 === strpos( $method, 'get_' ) ) {

			$method = str_replace( 'get_', '', $method );

			return $this->get_rule_value( $method );
		}

		// set_* method
		if ( 0 === strpos( $method, 'set_' ) ) {

			$method = str_replace( 'set_', '', $method );

			$this->data[ $method ] = $args[0];
		}

		// has_* method
		if ( 0 === strpos( $method, 'has_' ) ) {

			$method = str_replace( 'has_', '', $method );

			return isset( $this->data[ $method ] );
		}

		return null;
	}


	/**
	 * Get the specified rule value or return an empty string if
	 * the specified info does not exist
	 *
	 * @since 1.0.0
	 * @param string $key key for rule data, e.g. `id`
	 * @return mixed rule value
	 */
	private function get_rule_value( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : '';
	}


	/**
	 * Get the raw rule data
	 *
	 * @since 1.0.0
	 * @return array Raw data
	 */
	public function get_raw_data() {
		return $this->data;
	}


	/**
	 * Get access schedule amount
	 *
	 * Returns the amount part of the schedule.
	 * For example, returns '5' for the schedule '5 days'
	 *
	 * @since 1.0.0
	 * @return int|string Amount or empty string if no schedule
	 */
	public function get_access_schedule_amount() {

		$parts = explode( ' ', $this->get_access_schedule() );
		return isset( $parts[1] ) ? (int) $parts[0] : '';
	}


	/**
	 * Get access schedule period
	 *
	 * Returns the period part of the access schedule.
	 * For example, returns 'days' for the schedule '5 days'
	 *
	 * @since 1.0.0
	 * @return int|string Access schedule period
	 */
	public function get_access_schedule_period() {

		$parts = explode( ' ', $this->get_access_schedule() );
		return isset( $parts[1] ) ? $parts[1] : $parts[0];
	}


	/**
	 * Get rule access start time
	 *
	 * Returns the access start time this rule grants
	 * for a piece of content, based on the input time.
	 *
	 * @since 1.0.0
	 * @param string $from_time Timestamp for the time the access start
	 *                               time should be calculated from
	 * @return string Access start time as a timestamp
	 */
	public function get_access_start_time( $from_time ) {

		$access_time = $from_time;

		if ( ! $this->grants_immediate_access() ) {

			if ( strpos( $this->get_access_schedule(), 'month' ) !== false ) {
				$access_time = wc_memberships()->add_months( $from_time, $this->get_access_schedule_amount() );
			} else {
				$access_time = strtotime( $this->get_access_schedule(), $from_time );
			}
		}

		/**
		 * Filter rule access start time
		 *
		 * @since 1.0.0
		 * @param string $access_time Access time, as a timestamp
		 * @param string $from_time From time, as a timestamp
		 * @param WC_Memberships_Membership_Plan_Rule $rule
		 */
		$access_time = apply_filters( 'wc_memberships_rule_access_start_time', $access_time, $from_time, $this );

		// Access always starts at midnight
		return strtotime( 'midnight', $access_time );
	}


	/**
	 * Get content type key, suitable for HTML select option
	 *
	 * Combines content_type and content type name into a single
	 * key so that it can be used as a HTML select option value.
	 *
	 * @since 1.0.0
	 * @return string|null Content type key, example: "post_type|product"
	 */
	public function get_content_type_key() {

		$content_type      = $this->get_content_type();
		$content_type_name = $this->get_content_type_name();

		return ( $content_type && $content_type_name ) ? $content_type . '|' . $content_type_name : null;
	}


	/**
	 * Check if the content type exists
	 *
	 * @since 1.1.0
	 * @return bool True, if exists, false otherwise
	 */
	public function content_type_exists() {

		return 'post_type' == $this->get_content_type()
				  ? post_type_exists( $this->get_content_type_name() )
				  : taxonomy_exists( $this->get_content_type_name() );
	}


	/**
	 * Check if this rule applies to a key-value combination
	 *
	 * @since 1.0.0
	 * @param string $key Content type key
	 * @param string $value Optional. Value. Defaults to null.
	 * @return bool True if applies to the specified key-value combination, false otherwise
	 */
	public function applies_to( $key, $value = null ) {

		$has_key = 'has_' . $key;
		$get_key = 'get_' . $key;

		$applies = false;

		switch ( $key ) {

			// Special handling for object IDs
			case 'object_id':
			case 'object_ids':

				$rule_value = $this->get_object_ids();
				$rule_value = is_bool( $rule_value ) || is_null( $rule_value ) ? array() : (array) $rule_value;
				$applies    = ! empty( $rule_value ) && in_array( $value, $rule_value );

			break;

			default:
				$applies = $this->$has_key() && $this->$get_key() == $value;
			break;

		}

		return $applies;
	}


	/**
	 * Check if this rule applies to multiple object IDs
	 *
	 * @since 1.0.0
	 * @return bool True, if applies to multiple object IDs, false otherwise
	 */
	public function applies_to_multiple_objects() {
		return is_array( $this->get_object_ids() ) && count( $this->get_object_ids() ) > 1;
	}


	/**
	 * Check if this rule applies to a single object ID
	 *
	 * @since 1.0.0
	 * @param int $object_id Optional object ID to check against.
	 * @return bool True, if applies to a single (optionally provided) object ID, false otherwise
	 */
	public function applies_to_single_object( $object_id = null ) {

		return is_array( $this->get_object_ids() )
				&& count( $this->get_object_ids() ) === 1
				&& ( $object_id ? $this->applies_to( 'object_id', $object_id ) : true );
	}


	/**
	 * Check if this rule is active (purchasing discount only)
	 *
	 * @since 1.0.0
	 * @return bool True, if active, false otherwise
	 */
	public function is_active() {
		return $this->get_rule_value( 'active' ) == 'yes';
	}


	/**
	 * Check if this rule is new (has no ID)
	 *
	 * @since 1.0.0
	 * @return bool True, if new, false otherwise
	 */
	public function is_new() {
		return ! $this->get_id();
	}


	/**
	 * Check if this rule has any object IDs attached to it
	 *
	 * @since 1.0.0
	 * @return bool True, if has objects, false otherwise
	 */
	public function has_objects() {

		$object_ids = $this->get_object_ids();
		return is_array( $object_ids ) && ! empty( $object_ids );
	}


	/**
	 * Check if this rule grants immediate access to restricted content
	 *
	 * @since 1.0.0
	 * @return bool True, if immediate access, false otherwise
	 */
	public function grants_immediate_access() {
		return $this->get_access_schedule() == 'immediate';
	}


	/**
	 * Utility method for getting the label for an object ID from this rule
	 *
	 * @since 1.0.0
	 * @param int $object_id Object ID
	 * @return string|null Object label or null, if could not find object label
	 */
	public function get_object_label( $object_id ) {

		$label = null;

		if ( in_array( $object_id, $this->get_object_ids() ) ) {

			switch ( $this->get_content_type() ) {

				// Get post title
				case 'post_type':

					if ( 'product' == $this->get_content_type_name() ) {

						$product = wc_get_product( $object_id );

						if ( $product && in_array( $product->post->post_type, array( 'product', 'product_variation' ) ) ) {
							$label = strip_tags( $product->get_formatted_name() );
						}

					}	else {
						$label = get_the_title( $object_id );
					}

				break;

				// Get taxonomy name
				case 'taxonomy':

					$term = get_term( $object_id, $this->get_content_type_name() );

					if ( $term ) {
						$label = $term->name;
					}

				break;

			}
		}

		return $label;
	}


	/**
	 * Check if the current user can edit this rule
	 *
	 * Checks user's capability for the rule content type
	 *
	 * @since 1.0.0
	 * @return bool True, if can edit, false otherwise
	 */
	public function current_user_can_edit() {

		// Users can always edit a new rule that has no content type key set yet
		if ( ! $this->get_content_type_key() ) {
			return true;
		}

		return current_user_can( 'wc_memberships_edit_rule', $this->get_id() );
	}


	/**
	 * Check if the current context allows editing this rule
	 *
	 * Context allows editing if the global $post ID matches the
	 * rule membership plan ID or if the rule only applies to the
	 * global $post ID.
	 *
	 * @since 1.0.0
	 * @return bool True, if context allows editing, false otherwise
	 */
	public function current_context_allows_editing() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return $this->get_membership_plan_id() == $post->ID || $this->applies_to_single_object( $post->ID );
	}


	/**
	 * Get object search action name for admin screens
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_object_search_action_name() {

		if ( 'taxonomy' == $this->get_content_type() ) {

			$action = 'wc_memberships_json_search_terms';

		} else {

			if ( 'product' == $this->get_content_type_name() ) {
				$action = 'woocommerce_json_search_products_and_variations';
			} else {
				$action = 'wc_memberships_json_search_posts';
			}

		}

		return $action;
	}


	/**
	 * Get rule priority
	 *
	 * Priority will be determined by the type of content the rule applies to.
	 * 10 = post type
	 * 20 = taxonomy
	 * 30 = term
	 * 40 = post
	 * A higher number means a higher priority
	 *
	 * @since 1.1.0
	 * @return int
	 */
	public function get_priority() {

		$priority = 0;

		$object_ids = $this->get_object_ids();

		if ( 'post_type' == $this->get_content_type() && ! empty( $object_ids ) ) {
			$priority = 40;
		} else if ( 'taxonomy' == $this->get_content_type() && ! empty( $object_ids ) ) {
			$priority = 30;
		} else if ( 'taxonomy' == $this->get_content_type() && empty( $object_ids ) ) {
			$priority = 20;
		} else if ( 'post_type' == $this->get_content_type() && empty( $object_ids ) ) {
			$priority = 10;
		}

		/**
		 * Filter rule priority
		 *
		 * @since 1.1.0
		 * @param int $priority
		 * @param WC_Memberships_Membership_Plan_Rule $rule
		 */
		return apply_filters( 'wc_memberships_rule_priority', $priority, $this );
	}


}
