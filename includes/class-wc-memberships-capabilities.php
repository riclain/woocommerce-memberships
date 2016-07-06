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
 * Membership Capabilities class
 *
 * This class handles all capability-related functionality, as well as providing
 * start times for when a user can access a specific piece of content
 *
 * @since 1.0.0
 */
class WC_Memberships_Capabilities {


	/** @var array helper for user post access start time results */
	private $_user_access_start_time = array();


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Adjust user capabilities
		add_filter( 'user_has_cap', array( $this, 'user_has_cap' ), 9, 3 );
	}


	/**
	 * Check if the passed in caps contain a positive 'manage_woocommerce' capability
	 *
	 * @since 1.0.0
	 * @param array $caps
	 * @return bool
	 */
	private function can_manage_woocommerce( $caps ) {
		return isset( $caps['manage_woocommerce'] ) && $caps['manage_woocommerce'];
	}


	/**
	 * Checks if a user has a certain capability
	 *
	 * @since 1.0.0
	 * @param array $allcaps
	 * @param array $caps
	 * @param array $args
	 * @return array
	 */
	public function user_has_cap( $allcaps, $caps, $args ) {
		global $pagenow, $typenow;

		if ( isset( $caps[0] ) ) {

			switch ( $caps[0] ) {

				case 'wc_memberships_access_all_restricted_content':

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

				break;

				case 'wc_memberships_view_restricted_post_content' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id = $args[1];
					$post_id = $args[2];

					if ( $this->post_is_public( $post_id ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$rules               = wc_memberships()->rules->get_post_content_restriction_rules( $post_id );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_content_access_from_rules( $user_id, $rules, $post_id );

				break;

				case 'wc_memberships_view_restricted_product' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id = $args[1];
					$post_id = $args[2];

					if ( $this->post_is_public( $post_id ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$rules               = wc_memberships()->rules->get_the_product_restriction_rules( $post_id );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_product_view_access_from_rules( $user_id, $rules, $post_id );

				break;

				case 'wc_memberships_purchase_restricted_product' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id = $args[1];
					$post_id = $args[2];

					if ( $this->post_is_public( $post_id ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$rules               = wc_memberships()->rules->get_the_product_restriction_rules( $post_id );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_product_purchase_access_from_rules( $user_id, $rules, $post_id );

				break;

				case 'wc_memberships_view_restricted_product_taxonomy_term':

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id  = $args[1];
					$taxonomy = $args[2];
					$term_id  = $args[3];

					$rules = wc_memberships()->rules->get_taxonomy_term_product_restriction_rules( $taxonomy, $term_id );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_content_access_from_rules( $user_id, $rules, $term_id );

				break;

				case 'wc_memberships_view_delayed_product_taxonomy_term';

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$taxonomy   = $args[2];
					$term       = $args[3];
					$has_access = false;

					$access_time = $this->get_user_access_start_time_for_taxonomy_term( $user_id, $taxonomy, $term );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;

				break;

				case 'wc_memberships_view_restricted_taxonomy_term' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id  = $args[1];
					$taxonomy = $args[2];
					$term_id  = $args[3];

					$rules               = wc_memberships()->rules->get_taxonomy_term_content_restriction_rules( $taxonomy, $term_id );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_content_access_from_rules( $user_id, $rules, $term_id );

				break;

				case 'wc_memberships_view_restricted_taxonomy' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id  = $args[1];
					$taxonomy = $args[2];

					$rules               = wc_memberships()->rules->get_taxonomy_content_restriction_rules( $taxonomy );
					$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_content_access_from_rules( $user_id, $rules );

				break;

				case 'wc_memberships_view_restricted_post_type' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id   = $args[1];
					$post_type = $args[2];

					if ( in_array( $post_type, array( 'product', 'product_variation' ) ) ) {

						$rules = wc_memberships()->rules->get_product_restriction_rules( array(
							'content_type'      => 'post_type',
							'content_type_name' => 'product',
						) );

						$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_product_view_access_from_rules( $user_id, $rules );

					} else {

						$rules = wc_memberships()->rules->get_post_type_content_restriction_rules( $post_type );
						$allcaps[ $caps[0] ] = wc_memberships()->rules->user_has_content_access_from_rules( $user_id, $rules );
					}

				break;

				case 'wc_memberships_view_delayed_post_type';

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$post_type  = $args[2];
					$has_access = false;

					$access_time = $this->get_user_access_start_time_for_post_type( $user_id, $post_type );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;

					break;

				case 'wc_memberships_view_delayed_taxonomy';

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$taxonomy   = $args[2];
					$has_access = false;

					$access_time = $this->get_user_access_start_time_for_taxonomy( $user_id, $taxonomy );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;
					break;

				case 'wc_memberships_view_delayed_taxonomy_term';

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$taxonomy   = $args[2];
					$term       = $args[3];
					$has_access = false;

					$access_time = $this->get_user_access_start_time_for_taxonomy_term( $user_id, $taxonomy, $term );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;
					break;

				case 'wc_memberships_view_delayed_post_content' :
				case 'wc_memberships_view_delayed_product' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$post_id    = $args[2];
					$has_access = false;

					if ( $this->post_is_public( $post_id ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$access_time = $this->get_user_access_start_time_for_post( $user_id, $post_id, 'view' );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;

				break;

				case 'wc_memberships_purchase_delayed_product' :

					if ( $this->can_manage_woocommerce( $allcaps ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$user_id    = $args[1];
					$post_id    = $args[2];
					$has_access = false;

					if ( $this->post_is_public( $post_id ) ) {
						$allcaps[ $caps[0] ] = true;
						break;
					}

					$access_time = $this->get_user_access_start_time_for_post( $user_id, $post_id, 'purchase' );

					if ( $access_time && current_time( 'timestamp', true ) >= $access_time ) {
						$has_access = true;
					}

					$allcaps[ $caps[0] ] = $has_access;

				break;

				// Editing a rule depends on the rule's content type and related capabilities
				case 'wc_memberships_edit_rule' :

					$user_id  = $args[1];
					$rule_id  = $args[2];
					$can_edit = false;

					$rule = wc_memberships()->rules->get_rule( $rule_id );

					if ( $rule ) {

						switch ( $rule->get_content_type() ) {

							case 'post_type':

								$post_type = get_post_type_object( $rule->get_content_type_name() );

								if ( ! $post_type ) {
									return false;
								}

								$can_edit = current_user_can( $post_type->cap->edit_posts ) && current_user_can( $post_type->cap->edit_others_posts );

							break;

							case 'taxonomy':

								$taxonomy = get_taxonomy( $rule->get_content_type_name() );

								if ( ! $taxonomy ) {
									return false;
								}

								$can_edit = current_user_can( $taxonomy->cap->manage_terms ) && current_user_can( $taxonomy->cap->edit_terms );

							break;

						}

					}

					$allcaps[ $caps[0] ] = $can_edit;

				break;

				case 'wc_memberships_cancel_membership' :
				case 'wc_memberships_renew_membership' :

					$user_id            = $args[1];
					$user_membership_id = $args[2];

					$user_membership = wc_memberships_get_user_membership( $user_membership_id );

					// complimentary memberships cannot be cancelled or renewed by the user
					$allcaps[ $caps[0] ] = $user_membership && $user_membership->get_user_id() == $user_id && ! $user_membership->has_status( 'complimentary' );

				break;

				// Prevent deleting membership plans with active memberships
				case 'delete_published_membership_plan' :
				case 'delete_published_membership_plans' :

					// This workaround (*hack*, *cough*) allows displaying the trash/delete
					// link on membership plans list table even if the plan has active members
					if ( is_admin() && 'edit.php' == $pagenow && 'wc_membership_plan' == $typenow && empty( $_POST ) ) {
						break;
					}

					$post_id = $args[2];

					$plan = wc_memberships_get_membership_plan( $post_id );

					if ( $plan->has_active_memberships() ) {
						$allcaps[ $caps[0] ] = false;
					}

				break;

			}
		}

		return $allcaps;
	}


	/**
	 * Returns true if the given post has been forced public and thus not subject
	 * to restrictions
	 *
	 * @since 1.3.0
	 * @param int|string $post_id post or product (parent/variation) ID
	 * @return bool true if the post is public, false otherwise
	 */
	protected function post_is_public( $post_id ) {

		$post_type = get_post_type( $post_id );

		// products can only be forced public at the parent product level
		if ( 'product_variation' === $post_type ) {
			$post_id = wp_get_post_parent_id( $post_id );
		}

		return 'yes' === get_post_meta( $post_id, '_wc_memberships_force_public', true );
	}


	/**
	 * Get user access date for a post
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 * @param int $post_id
	 * @param string $access_type Optional. Defaults to "view". Applies only to products.
	 * @return int|null Timestamp of start time or null if no access
	 */
	public function get_user_access_start_time_for_post( $user_id, $post_id, $access_type = 'view' ) {

		$post_type = get_post_type( $post_id );

		if ( 'product_variation' == $post_type ) {
			$post_type = 'product';
		}

		$rule_type = 'product' == $post_type ? 'product_restriction' : 'content_restriction';

		return $this->get_user_access_start_time( array(
			'rule_type'         => $rule_type,
			'user_id'           => $user_id,
			'content_type'      => 'post_type',
			'content_type_name' => $post_type,
			'object_id'         => $post_id,
			'access_type'       => $access_type,
		) );
	}


	/**
	 * Get user access date for a post type
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 * @param string $post_type
	 * @param string $access_type Optional. Defaults to "view". Applies only to products and variations.
	 * @return int|null Timestamp of start time or null if no access
	 */
	public function get_user_access_start_time_for_post_type( $user_id, $post_type, $access_type = 'view' ) {

		if ( 'product_variation' == $post_type ) {
			$post_type = 'product';
		}

		$rule_type = 'product' == $post_type ? 'product_restriction' : 'content_restriction';

		return $this->get_user_access_start_time( array(
			'rule_type'         => $rule_type,
			'user_id'           => $user_id,
			'content_type'      => 'post_type',
			'content_type_name' => $post_type,
			'access_type'       => $access_type,
		) );
	}


	/**
	 * Get user access date for a taxonomy
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 * @param string $taxonomy
	 * @param string $access_type Optional. Defaults to "view". Applies only to product taxonomies.
	 * @return int|null Timestamp of start time or null if no access
	 */
	public function get_user_access_start_time_for_taxonomy( $user_id, $taxonomy, $access_type = 'view' ) {

		return $this->get_user_access_start_time( array(
			'user_id'           => $user_id,
			'content_type'      => 'taxonomy',
			'content_type_name' => $taxonomy,
			'access_type'       => $access_type,
		) );
	}


	/**
	 * Get user access date for a taxonomy term
	 *
	 * @since 1.0.0
	 * @param int $user_id
	 * @param string $taxonomy
	 * @param string|int $term
	 * @param string $access_type Optional. Defaults to "view". Applies only to product taxonomy terms.
	 * @return int|null Timestamp of start time or null if no access
	 */
	public function get_user_access_start_time_for_taxonomy_term( $user_id, $taxonomy, $term, $access_type = 'view' ) {

		return $this->get_user_access_start_time( array(
			'user_id'           => $user_id,
			'content_type'      => 'taxonomy',
			'content_type_name' => $taxonomy,
			'object_id'         => $term,
			'access_type'       => $access_type,
		) );
	}


	/**
	 * Get user access date for a piece of content
	 *
	 * @since 1.0.0
	 * @param array $args {
	 *   Optional. An array of arguments.
	 *
	 *   @type string|array $rule_type Optional. Content type. One or more of 'content_restriction' or 'product_restriction'
	 *   @type string $content_type Optional. Content type. One of 'post_type' or 'taxonomy'
	 *   @type string $content_type_name Optional. Content type name. A valid post type or taxonomy name.
	 *   @type string|int $object_id Optional. Post or taxonomy term ID/slug
	 *   @type string $access_type Optional. Defaults to "view". Applies only to products and product taxonomies/terms.
	 * }
	 * @return int|null Timestamp of start time or null if no access
	 */
	public function get_user_access_start_time( $args = array() ) {

		$defaults = array(
			'rule_type'          => array( 'content_restriction', 'product_restriction' ),
			'user_id'            => get_current_user_id(),
			'content_type'       => null,
			'content_type_name'  => null,
			'object_id'          => null,
			'access_type'        => 'view',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( is_string( $args['rule_type'] ) ) {
			$args['rule_type'] = (array) $args['rule_type'];
		}

		$cache_key = http_build_query( $args );

		$user_id = $args['user_id'];

		// Only calculate access time if not cached before, speeds up subsequent checks
		if ( ! isset( $this->_user_access_start_time[ $cache_key ] ) ) {

			$access_time = current_time( 'timestamp', true ); // by default, access is immediate
			$access_type = $args['access_type'];

			$rules_args = $args;
			unset( $rules_args['access_type'] );
			unset( $rules_args['user_id'] );

			$rules = wc_memberships()->rules->get_rules( $rules_args );

			// If rules apply, process them
			if ( ! empty( $rules ) ) {

				// If there are no product restriction rules, then we can safely say that
				// access is restricted
				if ( ! in_array( 'product_restriction', $rules_args['rule_type'] ) ) {
					$access_time = null;
				}

				// Otherwise, we need to check if there are any content restriction rules
				// or any product restriction rules that restrict the queried access type
				else {

					foreach ( $rules as $rule ) {

						// Check if the product restriction rule applies to the correct access type
						if ( 'product_restriction' == $rule->get_rule_type() ) {

							if ( $access_type === $rule->get_access_type() ) {

								$access_time = null;
								break;
							}

						// Content restriction rules indicate that access is restricted
						} else {

							$access_time = null;
							break;
						}
					}

				}


				// If access is restricted, determine if user has access and if, then when
				if ( ! $access_time ) {

					$last_priority = 0;

					foreach ( $rules as $rule ) {

						$rule_applies = true;

						// Check if rule applies to this piece of content, based on the access type
						// This affects products only.
						if ( 'product_restriction' == $rule->get_rule_type() ) {

							$rule_applies = ( 'view' == $access_type )
								? in_array( $rule->get_access_type(), array( 'view', 'purchase' ) )
								: $access_type == $rule->get_access_type();
						}

						if ( $rule_applies && wc_memberships()->user_memberships->is_user_active_member( $user_id, $rule->get_membership_plan_id() ) ) {

							$user_membership = wc_memberships()->user_memberships->get_user_membership( $user_id, $rule->get_membership_plan_id() );

							/**
							 * Filter the rule's content 'access from' time for a user membership
							 *
							 * The 'access from' time is used as the base time for calculating the access
							 * start time for scheduled content.
							 *
							 * @since 1.0.0
							 * @param string $from_time Access from time, as a timestamp
							 * @param WC_Memberships_Membership_Plan_Rule $rule
							 * @param WC_Memberships_User_Membership $user_membership
							 */
							$from_time = apply_filters( 'wc_memberships_access_from_time', $user_membership->get_start_date( 'timestamp' ), $rule, $user_membership );

							// If there is no time to calculate the access time from, simply
							// use the current time as access start time
							if ( ! $from_time ) {
								$access_time = current_time( 'timestamp', true );
								break; // Can't get any earlier, break the loop
							}

							$rule_access_time = $rule->get_access_start_time( $from_time );
							$rule_priority    = $rule->get_priority();

							// If this rule has higher priority than last rule,
							// override the previous access time.
							// If this has the same priority as the last rule,
							// and grants earlier access, override previous access time.
							if ( $rule_priority > $last_priority || $rule_priority == $last_priority && ( ! $access_time || $rule_access_time < $access_time ) ) {

								$access_time   = $rule_access_time;
								$last_priority = $rule_priority;
							}

						}
					}
				}

			}

			/**
			 * Filter user's access start time to a piece of content
			 *
			 * @since 1.0.0
			 * @param string $access_time Access start timestamp
			 * @param array $args {
			 *   An array of arguments.
			 *
			 *   @type string $content_type Content type. One of 'post_type' or 'taxonomy'
			 *   @type string $content_type_name Content type name. A valid post type or taxonomy name.
			 *   @type string|int $object_id Optional. Post or taxonomy term ID/slug
			 *   @type string $access_type
			 * }
			 */
			$access_time = apply_filters( 'wc_memberships_user_object_access_start_time', $access_time, $args );

			$this->_user_access_start_time[ $cache_key ] = $access_time;

		}

		return $this->_user_access_start_time[ $cache_key ];
	}


	/**
	 * Check if a post (post type or product) is accessible (viewable or purchaseable)
	 *
	 * TODO for now $target only supports 'post' => id or 'product' => id
	 * having an array can be more future proof compatible if we decide to check for other content types
	 * such as taxonomies, terms, etc.
	 *
	 * @since 1.4.0
	 * @param int $user_id The user id to check for access
	 * @param string|array $action Type of action(s): 'view', 'purchase' (products only)
	 * @param array $target Associative array of content type and content id to access to
	 * @param int|string $access_time UTC timestamp to compare for content access (optional, defaults to now)
	 * @return bool
	 */
	public function user_can( $user_id, $action, $target, $access_time = '' ) {

		if ( empty( $access_time ) ) {
			$access_time = current_time( 'timestamp', true );
		}

		if ( is_array( $action ) ) {

			$conditions = array();

			foreach ( $action as $capability ) {
				$conditions[] = $access_time >= $this->get_user_access_start_time_for_post( $user_id, reset( $target ), $capability );
			}

			return in_array( true, $conditions );
		}

		$user_start_time = $this->get_user_access_start_time_for_post( $user_id, reset( $target ), $action );

		return null === $user_start_time ? false : $access_time >= $user_start_time;
	}


}
