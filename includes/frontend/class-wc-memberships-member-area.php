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
 * @package   WC-Memberships/Frontend
 * @author    SkyVerge
 * @category  Frontend
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * My Account Member Area
 *
 * @since 1.6.0
 */
class WC_Memberships_Member_Area {

	
	/**
	 * Member Area
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// show memberships on My Account dashboard
		add_action( 'woocommerce_before_my_account', array( $this, 'my_account_memberships' ) );

		// render My Account -> My Membership member area page
		add_filter( 'woocommerce_get_breadcrumb', array( $this, 'filter_breadcrumbs' ), 100 );
		add_filter( 'the_content',                array( $this, 'render_member_area_content' ), 100 );
	}


	/**
	 * Output memberships table in My Account
	 *
	 * @since 1.6.0
	 */
	public function my_account_memberships() {

		$customer_memberships = wc_memberships_get_user_memberships();

		if ( ! empty( $customer_memberships ) ) {

			wc_get_template( 'myaccount/my-memberships.php', array(
				'customer_memberships' => $customer_memberships,
				'user_id'              => get_current_user_id(),
			) );
		}
	}


	/**
	 * Filter WooCommerce My Account area breadcrumbs
	 *
	 * @since 1.6.0
	 * @param array $crumbs WooCommerce My Account breadcrumbs
	 * @return array
	 */
	public function filter_breadcrumbs( $crumbs ) {
		global $wp_query;

		// sanity check to see if we're at the right endpoint
		if ( isset( $wp_query->query_vars['members_area'] ) && is_account_page() && ( count( $crumbs ) > 0 ) ) {

			// Membership data
			$current_user_id = (int) get_current_user_id();
			$user_membership = wc_memberships_get_user_membership( $current_user_id, (int) $wp_query->query_vars['members_area'] );

			// check if membership exists and the current logged in user is an active member
			if ( $user_membership && ( $current_user_id === (int) $user_membership->get_user_id() ) && wc_memberships_is_user_active_member( $current_user_id, $user_membership->get_plan() ) ) {

				array_push( $crumbs, array(
					$user_membership->get_plan()->get_name(),
					wc_memberships_get_members_area_url( $user_membership->get_plan() ),
				) );
			}
		}

		return $crumbs;
	}


	/**
	 * Filter content for the Members Area page
	 *
	 * @since 1.6.0
	 * @param string $the_content Page HTML content
	 * @return string HTML
	 */
	public function render_member_area_content( $the_content ) {
		global $wp_query;

		// Display the members area by replacing content in My Account page if we're at the right endpoint
		if ( isset( $wp_query->query_vars['members_area'] ) && is_account_page() ) {

			$user_id         = (int) get_current_user_id();
			$user_membership = wc_memberships_get_user_membership( $user_id, (int) $wp_query->query_vars['members_area'] );

			// check if membership exists and the current logged in user is an active member
			if ( $user_membership && ( $user_id === (int) $user_membership->get_user_id() ) && wc_memberships_is_user_active_member( $user_id, $user_membership->get_plan() ) ) {

				// sections for this membership defined in admin
				$sections     = (array) $user_membership->get_plan()->get_members_area_sections();
				$members_area = array_intersect_key( wc_memberships_get_members_area_sections(), array_flip( $sections ) );

				// Member Area should have at least one section enabled
				if ( ! empty( $members_area ) ) {

					// load My Account navigation
					do_action( 'woocommerce_account_navigation' );
					// prevents to load twice the navigation
					// TODO this probably has to be removed when the Member Area gets overhauled {FN 2016-06-06}
					remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );

					// get the first section to be used as fallback
					$section = $sections[0];

					// get the queried member area section if set
					if ( isset( $wp_query->query_vars['members_area_section'] ) && array_key_exists( $wp_query->query_vars['members_area_section'], $members_area ) ) {
						$section = $wp_query->query_vars['members_area_section'];
					}

					// get a paged request
					$paged = isset( $wp_query->query_vars['members_area_section_page'] ) ? absint( $wp_query->query_vars['members_area_section_page'] ) : 1;

					ob_start();

					?>
					<div id="wc-memberships-members-area" class="woocommerce-MyAccount-content woocommerce my-membership member-<?php echo esc_attr( $user_id ); ?>" data-member="<?php echo esc_attr( $user_id ); ?>" data-membership="<?php echo esc_attr( $user_membership->get_plan()->get_id() ); ?>">
						<?php
							// Members Area navigation tabs
							wc_get_template( 'myaccount/my-membership-tabs.php', array(
								'members_area_sections' => $members_area,
								'current_section'       => $section,
								'customer_membership'   => $user_membership,
							) );
						?>
						<div id="wc-memberships-members-area-section" class="my-membership-section <?php echo sanitize_html_class( $section ); ?>" data-section="<?php echo esc_attr( $section ); ?>"  data-page="1">
							<?php
							// Members Area current section
							$this->get_template( $section, array(
								'user_membership' => $user_membership,
								'user_id'         => $user_id,
								'paged'           => $paged,
							) );
							?>
						</div>
					</div>
					<?php

					$the_content = ob_get_clean();
				}
			}
		}

		return $the_content;
	}


	/**
	 * Load members area templates
	 *
	 * @since 1.6.0
	 * @param string $section
	 * @param array $args {
	 *      @type \WC_Memberships_User_Membership $user_membership User Membership object
	 *      @type int $user_id Member id
	 *      @type int $paged Optional, pagination
	 * }
	 */
	public function get_template( $section, $args ) {

		// bail out: no args, no party
		if ( empty( $args['user_membership'] ) && empty( $args['user_id'] ) && ( ! $args['user_membership'] instanceof WC_Memberships_User_Membership ) ) {
			return;
		}

		// optional pagination
		$paged = isset( $args['paged'] ) ? (int) $args['paged'] : 1;

		if ( 'my-membership-content' === $section ) {

			wc_get_template( 'myaccount/my-membership-content.php', array(
				'customer_membership' => $args['user_membership'],
				'restricted_content'  => $args['user_membership']->get_plan()->get_restricted_content( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-products' === $section ) {

			wc_get_template( 'myaccount/my-membership-products.php', array(
				'customer_membership' => $args['user_membership'],
				'restricted_products' => $args['user_membership']->get_plan()->get_restricted_products( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-discounts' === $section ) {

			wc_get_template( 'myaccount/my-membership-discounts.php', array(
				'customer_membership' => $args['user_membership'],
				'discounted_products' => $args['user_membership']->get_plan()->get_discounted_products( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-notes' === $section ) {

			$dateTime = new DateTime();
			$dateTime->setTimezone( new DateTimeZone( wc_timezone_string() ) );

			wc_get_template( 'myaccount/my-membership-notes.php', array(
				'customer_membership' => $args['user_membership'],
				'customer_notes'      => $args['user_membership']->get_notes( 'customer', $paged ),
				'timezone'            => $dateTime->format( 'T' ),
				'user_id'             => $args['user_id'],
			) );

		} else {

			// allow custom sections if wc_membership_plan_members_area_sections is filtered
			$located = wc_locate_template( "myaccount/{$section}.php" );

			if ( is_readable( $located ) ) {
				wc_get_template( "myaccount/{$section}.php", $args );
			}
		}
	}


}
