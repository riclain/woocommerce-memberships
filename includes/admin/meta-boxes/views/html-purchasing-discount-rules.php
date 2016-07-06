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
 * @package   WC-Memberships/Admin/Views
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * View for purchasing discount rules table
 *
 * @since 1.0.0
 * @version 1.0.0
 */
?>
<table class="widefat rules purchasing-discount-rules js-rules">

	<thead>
		<tr>

			<th class="check-column">
				<input type="checkbox" id="product-discount-rules-select-all">
				<label for="product-discount-rules-select-all"> <?php esc_html_e( 'Select all', 'woocommerce-memberships' ); ?></label>
			</th>

			<?php if ( 'wc_membership_plan' == $post->post_type ) : ?>

				<th class="purchasing-discount-content-type content-type-column">
					<?php esc_html_e( 'Discount', 'woocommerce-memberships' ); ?>
				</th>

				<th class="purchasing-discount-objects objects-column">
					<?php esc_html_e( 'Title', 'woocommerce-memberships' ); ?>
					<?php echo SV_WC_Plugin_Compatibility::wc_help_tip( __( 'Search&hellip; or leave blank to apply to all', 'woocommerce-memberships' ) ); ?>
				</th>

			<?php else : ?>

				<th class="purchasing-discount-membership-plan membership-plan-column">
					<?php esc_html_e( 'Plan', 'woocommerce-memberships' ); ?>
				</th>

			<?php endif; ?>

			<th class="purchasing-discount-discount-type discount-type-column">
				<?php esc_html_e( 'Type', 'woocommerce-memberships' ); ?>
			</th>

			<th class="purchasing-discount-discount-amount amount-column">
				<?php esc_html_e( 'Amount', 'woocommerce-memberships' ); ?>
			</th>

			<th class="purchasing-discount-active active-column">
				<?php esc_html_e( 'Active', 'woocommerce-memberships' ); ?>
			</th>

		</tr>
	</thead>

	<?php foreach ( $purchasing_discount_rules as $index => $rule ) : ?>
		<?php require( wc_memberships()->get_plugin_path() . '/includes/admin/meta-boxes/views/html-purchasing-discount-rule.php' ); ?>
	<?php endforeach; ?>

	<tbody class="norules <?php if ( count( $purchasing_discount_rules ) > 1 ) : ?>hide<?php endif; ?>">
		<tr>
			<td colspan="<?php echo ( 'wc_membership_plan' == $post->post_type ) ? 6 : 5; ?>">

				<?php if ( 'wc_membership_plan' == $post->post_type || ! empty( $membership_plans ) ) : ?>
					<?php esc_html_e( 'There are no discounts yet. Click below to add one.', 'woocommerce-memberships' ); ?>
				<?php else: ?>
					<?php /* translators: %s - "Add a membership plan" link */
						printf( __( 'To create member discounts, please %s', 'woocommerce-memberships' ),
							'<a target="_blank" href="' . esc_url( admin_url( 'post-new.php?post_type=wc_membership_plan' ) ) . '">' .
							esc_html_e( 'Add a Membership Plan', 'woocommerce-memberships' ) .
							'</a>.'
						);
					?>
				<?php endif; ?>

			</td>
		</tr>
	</tbody>

	<?php if ( 'wc_membership_plan' == $post->post_type || ! empty( $membership_plans ) ) : ?>

		<tfoot>
			<tr>
				<th colspan="<?php echo ( 'wc_membership_plan' == $post->post_type ) ? 6 : 5; ?>">
					<button type="button"
					        class="button button-primary add-rule js-add-rule">
						<?php esc_html_e( 'Add New Discount', 'woocommerce-memberships' ); ?>
					</button>
					<button type="button"
					        class="button button-secondary remove-rules js-remove-rules
					        <?php if ( count( $purchasing_discount_rules ) < 2 ) : ?>hide<?php endif; ?>">
						<?php esc_html_e( 'Delete Selected', 'woocommerce-memberships' ); ?>
					</button>
				</th>
			</tr>
		</tfoot>

	<?php endif; ?>

</table>
