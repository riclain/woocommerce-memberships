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
 * @package   WC-Memberships/Templates
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Renders the content restricted to the membership in the my account area.
 *
 * @param WC_Memberships_User_Membership $customer_membership User Membership object
 * @param WP_Query $restricted_content Query results of posts and custom post types restricted to the membership
 * @param int $user_id The current user ID
 *
 * @version 1.5.0
 * @since 1.4.0
 */
?>

<h3><?php echo esc_html( apply_filters( 'wc_memberships_members_area_my_membership_content_title', __( 'My Membership Content', 'woocommerce-memberships' ) ) ); ?></h3>

<?php do_action( 'wc_memberships_before_members_area', 'my-membership-content' ); ?>

<?php if ( empty ( $restricted_content->posts ) ) : ?>

	<p><?php esc_html_e( 'There is no content assigned to this membership.', 'woocommerce-memberships' ); ?></p>

<?php else : ?>

	<?php echo wc_memberships_get_members_area_page_links( 'my-membership-content', $customer_membership->get_plan(), $restricted_content ); ?>

	<table class="shop_table shop_table_responsive my_account_orders my_account_memberships my_membership_content">

		<thead>
		<tr>
			<?php
			/**
			 * Filter My Membership Content table columns in Members Area
			 *
			 * @since 1.4.0
			 * @param array $my_membership_content_columns Associative array of column ids and names
			 */
			$my_membership_content_columns = apply_filters( 'wc_memberships_members_area_my_membership_content_column_names', array(
				'membership-content-title'      => __( 'Title', 'woocommerce-memberships' ),
				'membership-content-type'       => __( 'Type', 'woocommerce-memberships' ),
				'membership-content-accessible' => __( 'Accessible', 'woocommerce-memberships' ),
				'membership-content-excerpt'    => __( 'Excerpt', 'woocommerce-memberships' ),
				'membership-content-actions'    => '&nbsp;'
			), $user_id );
			?>
			<?php foreach ( $my_membership_content_columns as $column_id => $column_name ) : ?>
				<th class="<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
			<?php endforeach; ?>
		</tr>
		</thead>

		<tbody>
		<?php foreach ( $restricted_content->posts as $member_post ) : ?>

			<?php

			if ( ! $member_post instanceof WP_Post ) {
				continue;
			}

			// Determine if the content is currently accessible or not
			$can_view_content = wc_memberships_user_can( $user_id, 'view', array( 'post' => $member_post->ID ) );
			$view_start_time  = wc_memberships_get_user_access_start_time( $user_id, 'view', array( 'post' => $member_post->ID ) );
			?>

			<tr class="membership-content">
				<?php foreach ( $my_membership_content_columns as $column_id => $column_name ) : ?>

					<?php if ( 'membership-content-title' === $column_id ) : ?>

						<td class="membership-content-title" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( $can_view_content ) : ?>
								<a href="<?php echo esc_url( get_permalink( $member_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $member_post->ID ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( get_the_title( $member_post->ID ) ); ?>
							<?php endif; ?>
						</td>

					<?php elseif ( 'membership-content-type' === $column_id ) : ?>

						<td class="membership-content-type" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php echo esc_html( ucwords( $member_post->post_type ) ); ?>
						</td>

					<?php elseif ( 'membership-content-accessible' === $column_id ) : ?>

						<td class="membership-content-accessible" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( $can_view_content ) : ?>
								<?php esc_html_e( 'Now', 'woocommerce-memberships' ); ?>
							<?php else : ?>
								<time datetime="<?php echo date( 'Y-m-d', $view_start_time ); ?>" title="<?php echo esc_attr( $view_start_time ); ?>"><?php echo date_i18n( get_option( 'date_format' ), $view_start_time ); ?></time>
							<?php endif; ?>
						</td>

					<?php elseif ( 'membership-content-excerpt' === $column_id ) : ?>

						<td class="membership-content-excerpt" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( empty( $member_post->post_excerpt ) ) : ?>
								<?php echo wp_kses_post( wp_trim_words( strip_shortcodes( $member_post->post_content ), 20 ) ); ?>
							<?php else : ?>
								<?php echo wp_kses_post( wp_trim_words( $member_post->post_excerpt, 20 ) ); ?>
							<?php endif; ?>
						</td>

					<?php elseif ( 'membership-content-actions' === $column_id ) : ?>

						<td class="membership-content-actions order-actions" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php echo wc_memberships_get_members_area_action_links( 'my-membership-content', $customer_membership, $member_post ); ?>
						</td>

					<?php else : ?>

						<td class="<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php do_action( 'wc_memberships_members_area_my_membership_content_column_' . $column_id, $member_post ); ?>
						</td>

					<?php endif; ?>

				<?php endforeach; ?>
			</tr>

		<?php endforeach; ?>
		</tbody>

	</table>

	<?php echo wc_memberships_get_members_area_page_links( 'my-membership-content', $customer_membership->get_plan(), $restricted_content ); ?>

<?php endif; ?>

<?php do_action( 'wc_memberships_after_members_area', 'my-membership-content' ); ?>
