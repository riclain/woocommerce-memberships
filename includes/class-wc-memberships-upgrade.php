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

defined( 'ABSPATH' ) or exit;

/**
 * Memberships upgrades
 *
 * This class handles actions triggered upon plugin updates
 * from an earlier to the current latest version
 *
 * @since 1.6.2
 */
class WC_Memberships_Upgrade {


	/**
	 * Run updates
	 *
	 * @since 1.6.2
	 * @param string $installed_version
	 */
	public static function run_update_scripts( $installed_version ) {

		if ( ! empty( $installed_version ) ) {

			$update_path = array(
				'1.1.0' => 'update_to_1_1_0',
				'1.4.0' => 'update_to_1_4_0',
			);

			foreach ( $update_path as $update_to_version => $update_script ) {

				if ( version_compare ( $installed_version, $update_to_version, '<' ) ) {

					self::$update_script();
				}
			}
		}
	}


	/**
	 * Update to v1.1.0
	 *
	 * @since 1.6.2
	 */
	private static function update_to_1_1_0() {

		$all_rules = array();

		// merge rules from different options into a single option
		foreach ( array( 'content_restriction', 'product_restriction', 'purchasing_discount' ) as $rule_type ) {

			$rules = get_option( "wc_memberships_{$rule_type}_rules" );

			if ( is_array( $rules ) && ! empty( $rules ) ) {

				foreach ( $rules as $rule ) {

					// skip empty/corrupt rules
					if ( empty( $rule ) || ( isset( $rule[0] ) && ! $rule[0] ) ) {
						continue;
					}

					$rule['rule_type'] = $rule_type;
					$all_rules[] = $rule;
				}
			}

			delete_option( "wc_memberships_{$rule_type}_rules" );
		}

		update_option( 'wc_memberships_rules', $all_rules );
	}


	/**
	 * Update to v1.4.0
	 *
	 * @since 1.6.2
	 */
	private static function update_to_1_4_0() {

		// product category custom restriction messages in settings options
		update_option( 'wc_memberships_product_category_viewing_restricted_message', __( 'This product category can only be viewed by members. To view this category, sign up by purchasing {products}.', 'woocommerce-memberships' ) );
		update_option( 'wc_memberships_product_category_viewing_restricted_message_no_products', __( 'Displays if viewing a product category is restricted to a membership that cannot be purchased.', 'woocommerce-memberships' ) );
	}


}
