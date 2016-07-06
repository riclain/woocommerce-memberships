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
 * Renders the tab sections on My Account page for a customer membership
 *
 * @param array $members_area_sections Associative array of members area sections to put in tabs
 * @param WC_Memberships_User_membership $customer_membership Object
 * @param string $current_section The current section displayed
 *
 * @version 1.4.0
 * @since 1.4.0
 */
?>

<?php if ( ! empty( $members_area_sections ) && is_array( $members_area_sections ) ) : ?>

	<h2><?php echo esc_html( $customer_membership->get_plan()->get_name() ); ?></h2>

	<div class="my-membership-tabs my-membership-tabs-wrapper">
		<ul class="my-membership-tabs">
			<?php foreach ( $members_area_sections as $section => $name ) : ?>
				<li class="my-membership-tab <?php echo esc_attr( $section ); ?>">
					<?php if ( $section === $current_section ) : ?>
						<span><?php echo esc_html( $name ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( wc_memberships_get_members_area_url( $customer_membership->get_plan_id(), $section ) ) ?>"><?php echo esc_html( $name ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

<?php endif; ?>
