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
 * Get valid restriction message types
 *
 * @since 1.0.0
 * @return array
 */
function wc_memberships_get_valid_restriction_message_types() {

	/**
	 * Filter valid restriction message types
	 *
	 * @since 1.0.0
	 * @param array
	 */
	return apply_filters( 'wc_memberships_valid_restriction_message_types', array(
		'content_restricted',
		'product_viewing_restricted',
		'product_purchasing_restricted'
	) );
}


if ( ! function_exists( 'wc_memberships_restrict' ) ) {

	/**
	 * Restrict content to specified membership plans
	 *
	 * @since 1.0.0
	 * @param string $content
	 * @param string|int|array $plans Optional. The membership plan or plans to check against.
	 *                                Accepts a plan slug, ID, or an array of slugs or IDs. Default: all plans.
	 * @param string $delay
	 * @param bool $exclude_trial
	 */
	function wc_memberships_restrict( $content, $plans = null, $delay = null, $exclude_trial = false ) {

		$has_access   = false;
		$member_since = null;
		$access_time  = null;

		// grant access to super users
		if ( current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {
			$has_access = true;
		}

		// Convert to an array in all cases
		$plans = (array) $plans;

		// default to use all plans if no plan is specified
		if ( empty( $plans ) ) {
			$plans = wc_memberships_get_membership_plans();
		}

		foreach ( $plans as $plan_id_or_slug ) {
			$membership_plan = wc_memberships_get_membership_plan( $plan_id_or_slug );

			if ( $membership_plan && wc_memberships_is_user_active_member( get_current_user_id(), $membership_plan->get_id() ) ) {

				$has_access = true;

				if ( ! $delay && ! $exclude_trial ) {
					break;
				}

				// Determine the earliest membership for the user
				$user_membership = wc_memberships()->user_memberships->get_user_membership( get_current_user_id(), $membership_plan->get_id() );

				// Create a pseudo-rule to help applying filters
				$rule = new WC_Memberships_Membership_Plan_Rule( array(
					'access_schedule_exclude_trial' => $exclude_trial ? 'yes' : 'no'
				) );

				/** This filter is documented in includes/class-wc-memberships-capabilities.php **/
				$from_time = apply_filters( 'wc_memberships_access_from_time', $user_membership->get_start_date( 'timestamp' ), $rule, $user_membership );

				// If there is no time to calculate the access time from, simply
				// use the current time as access start time
				if ( ! $from_time ) {
					$from_time = current_time( 'timestamp', true );
				}

				if ( is_null( $member_since ) || $from_time < $member_since ) {
					$member_since = $from_time;
				}
			}
		}

		// Add delay
		if ( $has_access && ( $delay || $exclude_trial ) && $member_since ) {

			$access_time = $member_since;

			// Determine access time
			if ( strpos( $delay, 'month' ) !== false ) {

				$parts  = explode( ' ', $delay );
				$amount = isset( $parts[1] ) ? (int) $parts[0] : '';

				$access_time = wc_memberships()->add_months( $member_since, $amount );

			} else if ( $delay ) {

				$access_time = strtotime( $delay, $member_since );

			}

			// Output or show delayed access message
			if ( $access_time <= current_time( 'timestamp', true ) ) {

				echo $content;

			} else {

				$message = __( 'This content is part of your membership, but not yet! You will gain access on {date}', 'woocommerce-memberships' );

				// Apply the deprecated filter
				if ( has_filter( 'get_content_delayed_message' ) ) {
					/** This filter is documented in includes/frontend/class-wc-memberships-frontend.php **/
					$message = apply_filters( 'get_content_delayed_message', $message, null, $access_time );
					// Notify developers that this filter is deprecated
					_deprecated_function( 'The get_content_delayed_message filter', '1.3.1', 'wc_memberships_get_content_delayed_message' );
				}

				/** This filter is documented in includes/frontend/class-wc-memberships-frontend.php **/
				$message = apply_filters( 'wc_memberships_get_content_delayed_message', $message, null, $access_time );
				$message = str_replace( '{date}', date_i18n( wc_date_format(), $access_time ), $message );
				$output  = '<div class="wc-memberships-content-delayed-message">' . $message . '</div>';

				echo $output;

			}

		} elseif ( $has_access ) {

			echo $content;

		}
	}
}


if ( ! function_exists( 'wc_memberships_is_post_content_restricted' ) ) {

	/**
	 * Check if a post/page content is restricted
	 *
	 * @since 1.0.0
	 * @param int $post_id Optional. Defaults to current post
	 * @return bool True, if content has restriction rules, false otherwise
	 */
	function wc_memberships_is_post_content_restricted( $post_id = null ) {

		if ( ! $post_id ) {
			global $post;
			$post_id = isset( $post->ID ) ? $post->ID : false;
		}

		$rules = $post_id ? wc_memberships()->rules->get_post_content_restriction_rules( $post_id ) : '';

		return ! empty( $rules );
	}

}


if ( ! function_exists( 'wc_memberships_is_product_viewing_restricted' ) ) {

	/**
	 * Check if viewing a product is restricted
	 *
	 * @since 1.0.0
	 * @param int $post_id Optional. Defaults to current post
	 * @return bool True, if product viewing is restricted, false otherwise
	 */
	function wc_memberships_is_product_viewing_restricted( $post_id = null ) {

		if ( ! $post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$rules = wc_memberships()->rules->get_the_product_restriction_rules( $post_id );
		$is_restricted = false;

		if ( ! empty( $rules ) ) {

			foreach ( $rules as $rule ) {

				if ( 'view' == $rule->get_access_type() ) {
					$is_restricted = true;
				}
			}
		}

		return $is_restricted;
	}

}


if ( ! function_exists( 'wc_memberships_is_product_purchasing_restricted' ) ) {

	/**
	 * Check if purchasing a product is restricted
	 *
	 * @since 1.0.0
	 * @param int $post_id Optional. Defaults to current post
	 * @return bool True, if product purchasing is restricted, false otherwise
	 */
	function wc_memberships_is_product_purchasing_restricted( $post_id = null ) {

		if ( ! $post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$rules = wc_memberships()->rules->get_the_product_restriction_rules( $post_id );

		$is_resticted = false;

		if ( ! empty( $rules ) ) {

			foreach ( $rules as $rule ) {

				if ( 'purchase' == $rule->get_access_type() ) {
					$is_resticted = true;
				}
			}
		}

		return $is_resticted;
	}

}


if ( ! function_exists( 'wc_memberships_product_has_member_discount' ) ) {

	/**
	 * Check if the product (or current product) has any member discounts
	 *
	 * @since 1.0.0
	 * @param int $product_id Product ID. Optional, defaults to current product.
	 * @return boolean True, if is elgibile for discount, false otherwise
	 */
	function wc_memberships_product_has_member_discount( $product_id = null ) {

		if ( ! $product_id ) {

			global $product;
			$product_id = $product->id;
		}

		return wc_memberships()->rules->product_has_member_discount( $product_id );
	}
}


if ( ! function_exists( 'wc_memberships_user_has_member_discount' ) ) {

	/**
	 * Check if the current user is eligible for member discount for the current product
	 *
	 * @since 1.0.0
	 * @param int $product_id Product ID. Optional, defaults to current product.
	 * @return boolean True, if is elgibile for discount, false otherwise
	 */
	function wc_memberships_user_has_member_discount( $product_id = null ) {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $product_id ) {

			global $product;
			$product_id = $product->id;
		}

		$product      = wc_get_product( $product_id );
		$user_id      = get_current_user_id();
		$has_discount = wc_memberships()->rules->user_has_product_member_discount( $user_id, $product_id );

		if ( ! $has_discount && $product->has_child() ) {
			foreach ( $product->get_children( true ) as $child_id ) {

				$has_discount = wc_memberships()->rules->user_has_product_member_discount( $user_id, $child_id );

				if ( $has_discount ) {
					break;
				}
			}
		}

		return $has_discount;
	}
}


if ( ! function_exists( 'wc_memberships_user_can') ) {

	/**
	 * Check if a product is accessible (viewable or purchaseable)
	 *
	 * TODO for now $target only supports 'post' => id or 'product' => id
	 *
	 * @since 1.4.0
	 * @param int $user_id User to check if has access
	 * @param string|array Type of capabilities: 'view', 'purchase' (products only)
	 * @param array $target Associative array of content type and content id to access to
	 * @param int|string UTC timestamp to compare for content access (optional, defaults to now)
	 * @return bool|null
	 */
	function wc_memberships_user_can( $user_id, $action, $target, $when = '' ) {
		return wc_memberships()->capabilities->user_can( $user_id, $action, $target, $when );
	}

}


if ( ! function_exists( 'wc_memberships_get_user_access_time' ) ) {

	/**
	 * Get user access start timestamp for a content or product
	 *
	 * Returns the time in local time (according to site timezone)
	 *
	 * TODO for now $target only supports 'post' => id or 'product' => id
	 *
	 * @since 1.4.0
	 * @param int $user_id User to get access time for
	 * @param array $target Associative array of content type and content id to access to
	 * @param string $action Type of access, 'view' or 'purchase' (products only)
	 * @param bool $gmt Whether to return a UTC timestamp (default false, uses site timezone)
	 * @return int|null Timestamp of start access time
	 */
	function wc_memberships_get_user_access_start_time( $user_id, $action, $target, $gmt = false ) {

		$access_time = wc_memberships()->capabilities->get_user_access_start_time_for_post( $user_id, reset( $target ), $action );

		if ( ! is_null( $access_time ) ) {
			return ! $gmt ? wc_memberships()->adjust_date_by_timezone( $access_time, 'timestamp' ) : $access_time;
		}

		return null;
	}

}


if ( ! function_exists( 'wc_memberships_show_product_loop_member_discount_badge' ) ) {

	/**
	 * Get the member discount badge for the loop.
	 *
	 * @since 1.0.0
	 */
	function wc_memberships_show_product_loop_member_discount_badge() {
		wc_get_template( 'loop/member-discount-badge.php' );
	}
}


if ( ! function_exists( 'wc_memberships_show_product_member_discount_badge' ) ) {

	/**
	 * Get the member discount badge for the single product page.
	 *
	 * @since 1.0.0
	 */
	function wc_memberships_show_product_member_discount_badge() {
		wc_get_template( 'single-product/member-discount-badge.php' );
	}
}


if ( ! function_exists( 'wc_memberships_get_member_product_discount' ) ) {

	/**
	 * Get member product discount
	 *
	 * @since 1.4.0
	 * @param int|WC_Product $product
	 * @param WC_Memberships_User_Membership $customer_membership
	 * @return string Member discount
	 */
	function wc_memberships_get_member_product_discount( $customer_membership, $product ) {
		return $customer_membership->get_plan()->get_product_discount( $product );
	}

}


if ( ! function_exists( 'wc_memberships_get_members_area_url' ) ) {

	/**
	 * Get members area URL
	 *
	 * @since 1.4.0
	 * @param int|WC_Memberships_Membership_Plan $membership Object or id
	 * @param string $members_area_section Optional, which section of the members area to point to
	 * @param int|string $paged Optional, for paged sections
	 * @return string Unescaped URL
	 */
	function wc_memberships_get_members_area_url( $membership, $members_area_section = '', $paged = '' ) {

		$page_id       = wc_get_page_id( 'myaccount' );
		$membership_id = is_object( $membership ) ? $membership->get_id() : (int) $membership;

		if ( ! $page_id || ! $membership_id || 0 === $membership_id ) {
			return '';
		}

		// If unspecified, will get the first tab as set in membership plan in admin
		if ( empty( $members_area_section ) ) {

			$membership_plan    = is_int( $membership ) ? wc_memberships_get_membership_plan( $membership_id ) : $membership;

			if ( ! $membership_plan ) {
				return '';
			}

			$plan_sections        = (array) $membership_plan->get_members_area_sections();
			$available_sections   = array_intersect_key( wc_memberships_get_members_area_sections(), array_flip( $plan_sections ) );
			$members_area_section = key( $available_sections );
		}

		if ( ! empty( $paged ) ) {
			$paged = max( absint( $paged ), 1 );
		}

		if ( get_option( 'permalink_structure' ) ) {

			$myaccount_url = wc_get_page_permalink( 'myaccount' );
			$endpoint      = get_option( 'woocommerce_myaccount_members_area_endpoint', 'members-area' );

			// e.g. /my-account/members-area/123/my-membership-content/2
			return $myaccount_url . $endpoint . '/' . $membership_id . '/' . $members_area_section . '/' . $paged;
		}

		// e.g. /?page_id=123&members_area=456&members_area_section=my-membership-content&members_area_section_page=2
		return add_query_arg( array(
				'page_id'                   => $page_id,
				'members_area'              => $membership_id,
				'members_area_section'      => $members_area_section,
				'members_area_section_page' => $paged,
			),
			get_home_url()
		);
	}

}


if ( ! function_exists( 'wc_memberships_get_members_area_action_links' ) ) {

	/**
	 * Get Members Area action links
	 *
	 * @since 1.4.0
	 * @param string $section Members area section to display actions for
	 * @param WC_Memberships_User_Membership $user_membership
	 * @param WC_Product|WP_Post|object $object An object to pass to a filter hook (optional)
	 * @return string Action links HTML
	 */
	function wc_memberships_get_members_area_action_links( $section, $user_membership, $object ) {

		$default_actions = array();

		switch ( $section ) {

			case 'my-memberships' :

				$members_area = $user_membership->get_plan()->get_members_area_sections();

				// Renew: Show only for expired memberships that can be renewed
				if ( $user_membership->is_expired() && $user_membership->get_plan()->has_products() ) {
					$default_actions['renew'] = array(
						'url'  => $user_membership->get_renew_membership_url(),
						'name' => __( 'Renew', 'woocommerce-memberships' ),
					);
				}

				// Cancel: Do not show for cancelled, expired or pending cancellation
				if ( ( ! $user_membership->is_cancelled() && 'pending' !== $user_membership->get_status() ) && ! $user_membership->is_expired() && current_user_can( 'wc_memberships_cancel_membership', $user_membership->get_id() ) ) {
					$default_actions['cancel'] = array(
						'url'  => $user_membership->get_cancel_membership_url(),
						'name' => __( 'Cancel', 'woocommerce-memberships' ),
					);
				}

				// View: Do not show for cancelled, paused memberships or memberships without a Members Area
				if ( ! $user_membership->is_paused() && ! $user_membership->is_cancelled() && ! empty ( $members_area ) && is_array( $members_area ) ) {
					$default_actions['view'] = array(
						'url' => wc_memberships_get_members_area_url( $user_membership->get_plan_id(), $members_area[0] ),
						'name' => __( 'View', 'woocommerce-memberships' ),
					);
				}

			break;

			case 'my-membership-content'   :

				if ( wc_memberships_user_can( $user_membership->get_user_id(), 'view', array( 'post' => $object->ID ) ) ) {
					$default_actions['view'] = array(
						'url'  => get_permalink( $object->ID ),
						'name' => __( 'View', 'woocommerce-memberships' ),
					);
				}

			break;

			case 'my-membership-products'  :
			case 'my-membership-discounts' :

				$can_view_product     = wc_memberships_user_can( $user_membership->get_user_id(), 'view', array( 'product' => $object->id ) );
				$can_purchase_product = wc_memberships_user_can( $user_membership->get_user_id(), 'purchase', array( 'product' => $object->id ) );

				if ( $can_view_product ) {
					$default_actions['view'] = array(
						'url'  => get_permalink( $object->id ),
						'name' => __( 'View', 'woocommerce-memberships' ),
					);
				}

				if ( $can_view_product && $can_purchase_product ) {
					$default_actions['add-to-cart'] = array(
						'url'	=> $object->add_to_cart_url(),
						'name'	=> $object->add_to_cart_text(),
					);
				}

			break;

		}

		/**
		 * Filter membership actions on My Account and Members Area pages
		 *
		 * @since 1.4.0
		 * @param array $default_actions Associative array of actions
		 * @param WC_Memberships_User_Membership $user_membership User Membership object
		 * @param WC_Product|WP_Post|object $object Current object where the action is run (optional)
		 */
		$actions = apply_filters( "wc_memberships_members_area_{$section}_actions", $default_actions, $user_membership, $object );

		// Can be removed once we no longer support the hook below
		if ( 'my-memberships' === $section ) {

			/**
			 * Filter membership actions on my account page
			 *
			 * @since 1.0.0
			 * @deprecated since 1.4.0
			 * @param array $actions
			 * @param WC_Memberships_User_Membership $membership
			 */
			$actions = apply_filters( 'wc_memberships_my_account_my_memberships_actions', $actions, $user_membership );
		}

		$links = '';

		if ( ! empty( $actions ) ) {
			foreach ( $actions as $key => $action ) {
				$links .= '<a href="' . esc_url( $action['url'] ) . '" class="button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a> ';
			}
		}

		return $links;
	}

}


if ( ! function_exists( 'wc_memberships_get_members_area_page_links' ) ) {

	/**
	 * Get Members Area pagination links
	 *
	 * @since 1.4.0
	 * @param string $section Members Area section
	 * @param int|WC_Memberships_Membership_Plan $membership Membership
	 * @param WP_Query|WP_Comment_Query $query Current query
	 * @return string HTML or empty output if query is not paged
	 */
	function wc_memberships_get_members_area_page_links( $membership, $section, $query ) {

		$links = '';

		if ( $max_pages = $query->max_num_pages > 1 ) {

			$current_page = $query->get( 'paged' );

			if ( is_int( $membership ) ) {
				$membership = wc_memberships_get_membership_plan( $membership );
			}

			if ( $membership ) {

				// l10n for rtl text direction
				$left  = is_rtl() ? 'right' : 'left';
				$right = is_rtl() ? 'left' : 'right';

				if ( 1 === $current_page ) {
					// First page, show next
					$links .= '<a href="' . esc_url( wc_memberships_get_members_area_url( $membership, $section, 2 ) ). '" class="wc-memberships-members-area-page-link ' . $right . '">' . esc_html__( 'Next &rarr;', 'woocommerce-memberships' ) . '</a>';
				} elseif ( $max_pages == $current_page ) {
					// Last page, show prev
					$links .= '<a href="' . esc_url( wc_memberships_get_members_area_url( $membership, $section, $current_page - 1 ) ) . '" class="wc-memberships-members-area-page-link ' . $left . '">' . esc_html__( '&larr; Previous', 'woocommerce-memberships' ) . '</a>';
				} else {
					// In the middle of pages, show both
					$links .= '<a href="' . esc_url( wc_memberships_get_members_area_url( $membership, $section, $current_page - 1 ) ) . '" class="wc-memberships-members-area-page-link ' . $left . '">' . esc_html__( '&larr; Previous', 'woocommerce-memberships' ) . '</a>';
					$links .= '<a href="' . esc_url( wc_memberships_get_members_area_url( $membership, $section, $current_page + 1 ) ) . '" class="wc-memberships-members-area-page-link ' . $right . '">' . esc_html__( 'Next &rarr;', 'woocommerce-memberships' ) . '</a>';
				}

			}
		}

		return $links;
	}

}
