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
 * View for a single membership note in recent activity
 *
 * @since 1.3.0
 * @version 1.5.0
 */
?>

<li rel="<?php echo absint( $note->comment_ID ) ; ?>" class="<?php echo implode( ' ', array_map( 'sanitize_html_class', $note_classes ) ); ?>">

	<div class="note-content">
		<?php /* translators: Placeholders represent membership plan name and a note. Example "Gold: Membership cancelled" */
		echo wpautop( sprintf( __( '%1$s: %2$s', 'woocommerce-memberships' ), wp_kses_post( $plan_name ), wptexturize( wp_kses_post( $note->comment_content ) ) ) ); ?>
	</div>

	<p class="meta">
		<abbr class="exact-date" title="<?php echo esc_attr( $note->comment_date ); ?>">
			<?php printf( /* translators: Date and time when a Membership Note was published %1$s - date, %2$s - time */
				esc_html__( 'On %1$s at %2$s', 'woocommerce-memberships' ), date_i18n( wc_date_format(), strtotime( $note->comment_date ) ), date_i18n( wc_time_format(), strtotime( $note->comment_date ) ) ); ?>
		</abbr>
		<?php if ( $note->comment_author !== __( 'WooCommerce', 'woocommerce-memberships' ) ) : ?>
			<?php printf( /* translators: %s - Membership Note author */
				' ' . esc_html__( 'by %s', 'woocommerce-memberships' ), $note->comment_author ); ?>
		<?php endif; ?>
	</p>

</li>
