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
 * Renders the product discounts available from the membership in the my account area.
 *
 * @param WC_Memberships_User_Membership $customer_membership User Membership object
 * @param WP_Comment_Query $customer_notes Query results of customer-facing notes for the membership
 * @param string $timezone the timezone abbreviation for the site's timezone
 * @param int $user_id The current user ID
 *
 * @version 1.4.1
 * @since 1.4.0
 */
?>

<h3><?php echo esc_html( apply_filters( 'wc_memberships_members_area_my_membership_notes_title', __( 'My Membership Notes', 'woocommerce-memberships' ) ) ); ?></h3>

<?php do_action( 'wc_memberships_before_members_area', 'my-memberships-notes' ); ?>

<?php if ( empty ( $customer_notes ) ) : ?>

	<p><?php esc_html_e( 'There are no notes for this membership.', 'woocommerce-memberships' ); ?> </p>

<?php else : ?>

	<table class="shop_table shop_table_responsive my_account_orders my_account_memberships my_membership_notes">

		<thead>
		<tr>
			<?php
			/**
			 * Filter My Membership Notes table columns in Members Area
			 *
			 * @since 1.4.0
			 * @param array $my_membership_notes_columns Associative array of column ids and names
			 */
			$my_membership_notes_columns = apply_filters( 'wc_memberships_members_area_my_membership_notes_column_names', array(
				'membership-note-date'    => __( 'Date', 'woocommerce-memberships' ),
				'membership-note-time'    => __( 'Time', 'woocommerce-memberships' ),
				'membership-note-author'  => __( 'Author', 'woocommerce-memberships' ),
				'membership-note-content' => __( 'Note Content', 'woocommerce-memberships' ),
			), $user_id );
			?>
			<?php foreach ( $my_membership_notes_columns as $column_id => $column_name ) : ?>
				<th class="<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
			<?php endforeach; ?>
		</tr>
		</thead>

		<tbody>
		<?php foreach ( $customer_notes as $note ) : ?>

			<tr class="membership-note">
				<?php foreach ( $my_membership_notes_columns as $column_id => $column_name ) : ?>

					<?php if ( 'membership-note-date' === $column_id ) : ?>

						<td class="membership-note-date" data-title="<?php echo esc_attr( $column_name ); ?>">
							<time datetime="<?php echo esc_attr( date( 'Y-m-d', strtotime( $note->comment_date ) ) ); ?>" title="<?php echo esc_attr( strtotime( $note->comment_date ) ); ?>"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $note->comment_date ) ); ?></time>
						</td>

					<?php elseif ( 'membership-note-time' === $column_id ) : ?>

						<td class="membership-note-time" data-title="<?php echo esc_attr( $column_name ); ?>">
							<time title="<?php echo esc_attr( strtotime( $note->comment_date ) ); ?>"><?php echo esc_html( date( 'g:i a', strtotime( $note->comment_date ) ) . ' ' . $timezone ); ?></time>
						</td>

					<?php elseif ( 'membership-note-author' === $column_id ) : ?>

						<td class="membership-note-author" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php echo esc_html( $note->comment_author ); ?>
						</td>

					<?php elseif ( 'membership-note-content' === $column_id ) : ?>

						<td class="membership-note-content" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php echo wp_kses_post( $note->comment_content ); ?>
						</td>

					<?php else : ?>

						<td class="<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php do_action( 'wc_memberships_members_area_my_membership_notes_column_' . $column_id, $note ); ?>
						</td>

					<?php endif; ?>

				<?php endforeach; ?>
			</tr>

		<?php endforeach; ?>
		</tbody>
</table>

<?php endif; ?>

<?php do_action( 'wc_memberships_after_members_area', 'my-memberships-notes' );
