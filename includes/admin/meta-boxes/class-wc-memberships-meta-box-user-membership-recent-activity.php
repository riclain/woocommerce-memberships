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
 * User Membership Member Recent Activity Meta Box
 *
 * @since 1.0.0
 */
class WC_Memberships_Meta_Box_User_Membership_Recent_Activity extends WC_Memberships_Meta_Box {


	/** @var string meta box id **/
	protected $id = 'wc-memberships-user-membership-recent-activity';

	/** @var string meta box context **/
	protected $context = 'side';

	/** @var array list of supported screen IDs **/
	protected $screens = array( 'wc_user_membership' );


	/**
	 * Get the meta box title
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_title() {
		return __( 'Recent Activity', 'woocommerce-memberships' );
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

		// User memberships
		$user_memberships = wc_memberships_get_user_memberships( $user->ID );
		$memberships      = array();
		$notes            = null;

		if ( ! empty( $user_memberships ) ) {

			foreach ( $user_memberships as $user_membership ) {
				$memberships[ $user_membership->get_id() ] = $user_membership;
			}

			$args = array(
				'post__in' => array_keys( $memberships ),
				'approve'  => 'approve',
				'type'     => 'user_membership_note',
				'number'   => 5,
			);

			$notes = get_comments( $args );
		}

		?>
		<ul class="wc-user-membership-recent-activity">

			<?php if ( ! empty( $notes ) ) : ?>

				<?php foreach ( $notes as $note ) : ?>

					<?php

						$membership   = $memberships[ $note->comment_post_ID ];
						$plan         = $membership->get_plan();

						/* translators: Placeholder for plan name if a plan has been removed */
						$plan_name    = $plan ? $plan->get_name() : __( '[Plan removed]', 'woocommerce-memberships' );
						$note_classes = get_comment_meta( $note->comment_ID, 'notified', true ) ? array( 'notified', 'note' ) : array( 'note' );

						include( 'views/html-membership-recent-activity-note.php' );
					?>

				<?php endforeach; ?>

			<?php else : ?>

				<li><?php esc_html_e( "It's been quiet here. No activity yet.", 'woocommerce-memberships' ); ?></li>
			<?php endif; ?>

		</ul>
		<?php

	}


}
