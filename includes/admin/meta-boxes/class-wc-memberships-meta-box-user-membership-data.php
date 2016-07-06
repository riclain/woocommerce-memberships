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
 * User Membership Data Meta Box
 *
 * @since 1.0.0
 */
class WC_Memberships_Meta_Box_User_Membership_Data extends WC_Memberships_Meta_Box {


	/** @var string meta box id **/
	protected $id = 'wc-memberships-user-membership-data';

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
		return __( 'User Membership Data', 'woocommerce-memberships' );
	}


	/**
	 * Display the membership data meta box
	 *
	 * @param WP_Post $post
	 * @since 1.0.0
	 */
	public function output( WP_Post $post ) {
		global $pagenow;

		// Prepare variables
		$user_membership = wc_memberships_get_user_membership( $post->ID );
		$user_id         = 'post.php' == $pagenow
								? $user_membership->get_user_id()
								: ( isset( $_GET['user'] ) ? $_GET['user'] : null );

		// Bail out if no user ID
		if ( ! $user_id ) {
			return;
		}

		// Get user details
		$user = get_userdata( $user_id );

		// All the user memberships
		$user_memberships = wc_memberships_get_user_memberships( $user->ID );

		// Prepare options
		$status_options = array();
		foreach ( wc_memberships_get_user_membership_statuses() as $status => $labels ) {
			$status_options[ $status ] = $labels['label'];
		}

		/**
		 * Filter status options that appear in the edit user membership screen
		 *
		 * @since 1.0.0
		 * @param array $options Associative array of option value => label pairs
		 * @param int $user_membership_id User membership ID
		 */
		$status_options = apply_filters( 'wc_memberships_edit_user_membership_screen_status_options', $status_options, $post->ID );

		// prepare membership plan options
		$membership_plan_options = array();
		$membership_plans = wc_memberships_get_membership_plans( array(
			'post_status' => array( 'publish', 'private', 'future', 'draft', 'pending', 'trash' )
		) );

		if ( ! empty( $membership_plans ) ) {

			foreach ( $membership_plans as $membership_plan ) {
				$exists = false;

				// Each user can only have 1 membership per plan.
				// Check if user already has a membership for this plan.
				if ( ! empty( $user_memberships ) ) {

					foreach ( $user_memberships as $membership ) {

						if ( $membership->get_plan_id() == $membership_plan->get_id() ) {
							$exists = true;
							break;
						}
					}
				}

				// Only add plan to options if user is not a member of this plan or
				// if the current membership has this plan.
				// TODO: instead of removing, disable the option once
				// https://github.com/woothemes/woocommerce/pull/8024 lands in stable
				if ( ! $exists || $user_membership->get_plan_id() == $membership_plan->get_id() ) {
					$membership_plan_options[ $membership_plan->get_id() ] = $membership_plan->get_name();
				}
			}
		}

		$current_membership = null;
		$order   = $user_membership->get_order();
		$product = $user_membership->get_product();
		?>

		<h3 class="membership-plans">
			<ul class="sections">

				<?php if ( ! empty( $user_memberships ) ) : ?>

						<?php foreach ( $user_memberships as $membership ) : ?>

							<?php if ( $membership->get_plan() ): ?>
								<li <?php if ( $membership->get_id() == $post->ID ) : $current_membership = $membership->get_id(); ?>class="active"<?php endif; ?>>
									<a href="<?php echo esc_url( get_edit_post_link( $membership->get_id() ) ); ?>"><?php echo wp_kses_post( $membership->get_plan()->get_name() ); ?></a>
								</li>
							<?php endif; ?>

						<?php endforeach; ?>

					<?php endif; ?>

				<?php if ( count( $user_memberships ) != count( $membership_plans ) ) : ?>

					<li <?php if ( ! $current_membership ) : ?>class="active"<?php endif; ?>>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wc_user_membership&user=' . $user->ID ) ); ?>"><?php esc_html_e( 'Add a plan...', 'woocommerce-memberships' ); ?></a>
					</li>

				<?php endif; ?>

			</ul>
		</h3>

		<div class="plan-details">
			<h4><?php esc_html_e( 'Membership Details', 'woocommerce-memberships' ); ?></h4>

			<div class="woocommerce_options_panel">

				<?php
					/**
					 * Fires before the membership details in edit user membership screen
					 *
					 * @since 1.0.0
					 * @param WC_Memberships_User_Membership
					 */
					do_action( 'wc_memberships_before_user_membership_details', $user_membership );
				?>

				<?php woocommerce_wp_select( array(
					'id'      => 'post_parent',
					'label'   => __( 'Plan:', 'woocommerce-memberships' ),
					'options' => $membership_plan_options,
					'value'   => $user_membership->get_plan_id(),
					'class'   => 'wide',
					'wrapper_class' => 'js-membership-plan',
				) ); ?>

				<?php woocommerce_wp_select( array(
					'id'      => 'post_status',
					'label'   => __( 'Status:', 'woocommerce-memberships' ),
					'options' => $status_options,
					'value'   => 'wcm-' . $user_membership->get_status(),
					'class'   => 'wide',
				) ); ?>

				<?php woocommerce_wp_text_input( array(
					'id'          => '_start_date',
					'label'       => __( 'Member since:', 'woocommerce-memberships' ),
					'class'       => 'js-user-membership-date',
					'description' => __( 'YYYY-MM-DD', 'woocommerce-memberships' ),
					'value'       => 'post.php' == $pagenow ? $user_membership->get_local_start_date( 'Y-m-d' ) : current_time( 'Y-m-d' ),
				) ); ?>

				<?php woocommerce_wp_text_input( array(
					'id'          => '_end_date',
					'label'       => __( 'Expires:', 'woocommerce-memberships' ),
					'class'       => 'js-user-membership-date',
					'description' => __( 'YYYY-MM-DD', 'woocommerce-memberships' ) . (
						empty( $membership_plan_options )
							? ''
							:  ' - <a href="#" class="js-calc-expiration">' . esc_html__( 'Update expiration date to plan length', 'woocommerce-memberships' ) . '</a>'
						),
					'value'       => ! is_null( $user_membership->get_local_end_date( 'Y-m-d', false ) ) ? $user_membership->get_local_end_date( 'Y-m-d', false ) : '',
				) ); ?>

				<?php if ( $paused_date = $user_membership->get_local_paused_date( 'timestamp' ) ) : ?>

					<p class="form-field">
						<span class="description"><?php /* translators: %s - date */
							printf( __( 'Paused since %s', 'woocommerce-memberships' ), date_i18n( wc_date_format(), $paused_date ) ); ?>
						</span>
					</p>

				<?php endif; ?>

				<?php
					/**
					 * Fires after the membership details in edit user membership screen
					 *
					 * @since 1.0.0
					 * @param WC_Memberships_User_Membership
					 */
					do_action( 'wc_memberships_after_user_membership_details', $user_membership );
				?>

			</div>

		</div>

		<div class="billing-details">
			<h4><?php esc_html_e( 'Billing Details', 'woocommerce-memberships' ); ?></h4>

			<?php
				/**
				 * Fires before the billing details in edit user membership screen
				 *
				 * @since 1.0.0
				 * @param WC_Memberships_User_Membership
				 */
				do_action( 'wc_memberships_before_user_membership_billing_details', $user_membership );
			?>

			<?php if ( $order ) : ?>

				<table>
					<tr>
						<td><?php esc_html_e( 'Purchased in:', 'woocommerce-memberships' ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $order->id ) ); ?>">
								<?php printf( /* translators: %s - order number */
									esc_html__( 'Order %s', 'woocommerce-memberships' ), $order->get_order_number() ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Order Date:', 'woocommerce-memberships' ); ?></td>
						<td><?php echo date_i18n( wc_date_format(), strtotime( $order->order_date ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Order Total:', 'woocommerce-memberships' ); ?></td>
						<td><?php echo $order->get_formatted_order_total(); ?></td>
					</tr>
				</table>

			<?php else : ?>

				<p><?php esc_html_e( 'No billing details - this membership was created manually.', 'woocommerce-memberships' ); ?></p>

			<?php endif; ?>

			<?php
				/**
				 * Fires after the billing details in edit user membership screen
				 *
				 * @since 1.0.0
				 * @param WC_Memberships_User_Membership
				 */
				do_action( 'wc_memberships_after_user_membership_billing_details', $user_membership );
			?>

		</div>

		<div class="clear"></div>

		<ul class="user_membership_actions submitbox">

			<?php
				/**
				 * Fires at the start of the user membership actions meta box
				 *
				 * @since 1.0.0
				 * @param int $post_id The post id of the wc_user_membership post
				 */
				do_action( 'wc_memberships_user_membership_actions_start', $post->ID );
			?>

			<li class="wide">

				<?php

					$user_membership_actions = array();

					if ( current_user_can( 'delete_post', $post->ID ) ) {

						$user_membership_actions['delete-action'] = array(
							'class' => 'submitdelete deletion delete-membership',
							'link'  => get_delete_post_link( $post->ID, '', true ),
							'text'  => __( 'Delete User Membership', 'woocommerce-memberships' ),
						);

						// Can't transfer a newly created post
						if ( 'auto-draft' != $post->post_status ) {

							$user_membership_actions['transfer-action'] = array(
								'class'             => 'button transfer_user_membership',
								'link'              => '#',
								'text'              => __( 'Transfer', 'woocommerce-memberships' ),
								'custom_attributes' => array(
									'data-user-id'       => $user_membership->user_id,
									'data-membership-id' => $post->ID,
								),
							);

						}

					}

					/**
					 * Actions for user membership actions meta box
					 *
					 * @since 1.4.0
					 * @param int $post_id The post id of the wc_user_membership post
					 */
					$user_membership_actions = apply_filters( 'wc_memberships_user_membership_actions', $user_membership_actions, $post->ID );

				?>

				<?php if ( ! empty( $user_membership_actions ) ) : ?>

					<?php foreach( $user_membership_actions as $id => $action ) : ?>

						<div id="<?php echo $id ?>" class="user-membership-action">
							<?php
								$custom_attr = '';

								if ( ! empty( $action['custom_attributes'] ) ) {

									foreach( $action['custom_attributes'] as $k => $v ) {
										$custom_attr .= esc_attr( $k ) . '="' . esc_attr( $v ) . '" ';
									}
								}
							?>
							<a href="<?php echo esc_url( $action['link'] ); ?>" class="<?php echo esc_attr( $action['class'] ); ?>" <?php echo $custom_attr; ?>>
								<?php echo esc_html( $action['text'] ); ?>
							</a>
						</div>

					<?php endforeach; ?>

				<?php endif; ?>

				<input type="submit"
				       class="button save_user_membership save_action button-primary tips"
				       value="<?php esc_attr_e( 'Save', 'woocommerce-memberships' ); ?>"
				       data-tip="<?php esc_attr_e( 'Save/update the membership', 'woocommerce-memberships' ); ?>" />

			</li>

			<?php
				/**
				* Fires at the end of the user membership actions meta box
				*
				* @since 1.0.0
				* @param int $post_id The post id of the wc_user_membership post
				*/
				do_action( 'wc_memberships_user_membership_actions_end', $post->ID );
			?>

		</ul>

		<?php

	}


	/**
	 * Save user membership data
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function update_data( $post_id, WP_Post $post ) {

		$user_membership = wc_memberships_get_user_membership( $post );
		$date_format     = 'Y-m-d H:i:s';

		// Update start date
		$start_date = isset( $_POST['_start_date'] ) && $_POST['_start_date']
								? date( $date_format, strtotime( $_POST['_start_date'] ) )
								: '';

		// convert start date to UTC
		$start_date = wc_memberships()->adjust_date_by_timezone( $start_date, $date_format, wc_timezone_string() );

		update_post_meta( $post_id, '_start_date', $start_date );

		// Update end date - assume it was entered in the site's timezone
		$end_date = isset( $_POST['_end_date'] ) && $_POST['_end_date']
								? date( $date_format, strtotime( $_POST['_end_date'] ) )
								: '';

		// convert end date to UTC
		$end_date = ! empty( $end_date ) ? wc_memberships()->adjust_date_by_timezone( $end_date, $date_format, wc_timezone_string() ) : '';

		// get previous end date (UTC)
		$previous_end_date = $user_membership->get_end_date( $date_format );

		// If end date was set to a past date, automatically set status to expired
		if ( ! empty( $end_date ) && $previous_end_date != $end_date && strtotime( $end_date ) <= current_time( 'timestamp', true ) ) {
			$user_membership->update_status( 'expired' );
		}
		// If the end date has not changed, but status has been changed to one of the active statuses,
		// remove the end date, so that it does not conflict with the status
		else if ( $previous_end_date == $end_date && strtotime( $end_date ) <= current_time( 'timestamp', true ) ) {

			if ( in_array( $user_membership->get_status(), array( 'active', 'free_trial', 'complimentary' ) ) ) {
				$end_date = '';
			}
		}
		// If the status was set to expired, make sure that end date is in the past or at least now
		else if ( strtotime( $end_date ) > current_time( 'timestamp' ) && 'expired' == $user_membership->get_status() ) {
			$end_date = date( $date_format, strtotime( 'midnight', current_time( 'timestamp', true ) ) );
		}

		// set the end date (UTC)
		$user_membership->set_end_date( $end_date );
	}


}
