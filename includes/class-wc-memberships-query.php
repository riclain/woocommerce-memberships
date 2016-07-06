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
 * Class for handling main query and rewrite rules
 *
 * @since 1.6.0
 */
class WC_Memberships_Query {


	/**
	 * Constructor
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// add rewrite rules
		add_action( 'init', array( $this, 'add_endpoints' ), 1 );
	}


	/**
	 * Add endpoints for the Member Area
	 *
	 * @see WC_Memberships_Member_Area
	 *
	 * @since 1.6.0
	 */
	public function add_endpoints() {
		
		// Membership Plan id (numeric)
		add_rewrite_tag( '%members_area%', '([^&]+)' );
		// Members Area section (string)
		add_rewrite_tag( '%members_area_section%', '([^&]+)' );
		// Members Area section page (numeric, optional)
		add_rewrite_tag( '%members_area_section_page%', '([^&]+)' );

		$page_id = wc_get_page_id( 'myaccount' );
		$page    = get_post( $page_id );

		// sanity check
		if ( ! $page instanceof WP_Post ) {
			return;
		}

		$page_slug = $page->post_name;
		$endpoint  = get_option( 'woocommerce_myaccount_members_area_endpoint', 'members-area' );

		// e.g. domain.tld/*/my-account/members-area/123/my-membership-discounts/
		add_rewrite_rule(
			"(.+/)*{$page_slug}/{$endpoint}/([0-9]{1,})/([^/]*)/?$",
			'index.php?page_id=' . $page_id . '&members_area=$matches[2]&members_area_section=$matches[3]&members_area_section_page=1',
			'top'
		);

		// paged, e.g. domain.tld/*/my-account/members-area/123/my-membership-discounts/2
		add_rewrite_rule(
			"(.+/)*{$page_slug}/{$endpoint}/([0-9]{1,})/([^/]*)/([0-9]{1,})/?$",
			'index.php?page_id=' . $page_id . '&members_area=$matches[2]&members_area_section=$matches[3]&members_area_section_page=$matches[4]',
			'top'
		);
	}


}
