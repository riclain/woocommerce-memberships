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
 * @package   WC-Memberships/Admin/Meta-Boxes
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * User Membership Member Details Meta Box
 *
 * @since 1.0.0
 */
class WC_Memberships_Meta_Box_User_Membership_Member_Details extends WC_Memberships_Meta_Box {


	/** @var string meta box id **/
	protected $id = 'wc-memberships-user-membership-member-details';

	/** @var string meta box context **/
	protected $context = 'side';

	/** @var string meta box priority **/
	protected $priority = 'high';

	/** @var array list of supported screen IDs **/
	protected $screens = array( 'wc_user_membership' );


	/**
	 * Get the meta box title
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_title() {
		return __( 'Member Details', 'woocommerce-memberships' );
	}


	/**
	 * Display the member details meta box
	 *
	 * @param WP_Post $post
	 * @since 1.0.0
	 */
	public function output( WP_Post $post ) {
		global $pagenow;

		// Prepare variables
		$user_id = 'post.php' == $pagenow
						? $post->post_author
						: ( isset( $_GET['user'] ) ? $_GET['user'] : null );

		// Bail out if no user ID
		if ( ! $user_id ) {
			return;
		}

		// Get user details
		$user = get_userdata( $user_id );

		// Get user memberships
		$user_memberships = wc_memberships_get_user_memberships( $user_id );

		// Determine the member since date. Earliest membership wins!
		$member_since = null;

		if ( ! empty( $user_memberships ) ) {
			foreach ( $user_memberships as $user_membership ) {

				if ( ! $member_since || $member_since > $user_membership->get_local_start_date( 'timestamp' ) ) {
					$member_since = $user_membership->get_local_start_date( 'timestamp' );
				}
			}
		}

		/**
		 * Fires at the beginning of the member details meta box
		 *
		 * @since 1.0.0
		 * @param int $user_id The member (user) ID
		 * @param int $user_membership_id The post id of the user membership post
		 */
		do_action( 'wc_memberships_before_user_membership_member_details', $user->ID, $post->ID );

		echo get_avatar( $user->ID, 256 ); ?>

		<h2><?php echo esc_html( $user->display_name ); ?></h2>

		<p>
			<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>" class="member-email"><?php echo esc_html( $user->user_email ); ?></a>
			<br />
			<?php if ( $member_since ) : ?>
				<span class="member-since">
					<?php printf( /* translators: %s - date */
						esc_html__( 'Member since %s', 'woocommerce-memberships' ), date_i18n( wc_date_format(), $member_since ) ); ?>
				</span>
			<?php endif; ?>
		</p>

		<address>
			<?php
				$address = apply_filters( 'woocommerce_my_account_my_address_formatted_address', array(
					'first_name'  => get_user_meta( $user->ID, 'billing_first_name', true ),
					'last_name'   => get_user_meta( $user->ID, 'billing_last_name', true ),
					'company'     => get_user_meta( $user->ID, 'billing_company', true ),
					'address_1'   => get_user_meta( $user->ID, 'billing_address_1', true ),
					'address_2'   => get_user_meta( $user->ID, 'billing_address_2', true ),
					'city'        => get_user_meta( $user->ID, 'billing_city', true ),
					'state'       => get_user_meta( $user->ID, 'billing_state', true ),
					'postcode'    => get_user_meta( $user->ID, 'billing_postcode', true ),
					'country'     => get_user_meta( $user->ID, 'billing_country', true )
				), $user->ID, 'billing' );

				$formatted_address = WC()->countries->get_formatted_address( $address );

				if ( ! $formatted_address ) {
					esc_html_e( 'User has not set up their billing address yet.', 'woocommerce-memberships' );
				} else {
					echo $formatted_address;
				}
			?>
		</address>

		<?php

		/**
		 * Fires at the end of the member detail meta box
		 *
		 * @since 1.0.0
		 * @param int $user_id The member (user) ID
		 * @param int $user_membership_id The post id of the user membership post
		 */
		do_action( 'wc_memberships_after_user_membership_member_details', $user->ID, $post->ID );
	}


}
