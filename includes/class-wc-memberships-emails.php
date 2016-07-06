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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Membership Emails class
 *
 * This class handles all email-related functionality in Memberships.
 *
 * @since 1.0.0
 */
class WC_Memberships_Emails {


	/**
	 * Constructor
	 *
	 * Set up membership emails
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_filter( 'woocommerce_email_classes', array( $this, 'memberships_emails' ) );

		add_action( 'wc_memberships_new_user_membership_note', array( 'WC_Emails', 'send_transactional_email' ), 10, 10 );
	}


	/**
	 * Add custom memberships emails to WC emails
	 *
	 * @since 1.0.0
	 * @param array $emails
	 * @return array $emails
	 */
	public function memberships_emails( $emails ) {

		$emails['wc_memberships_membership_note'] = require_once( wc_memberships()->get_plugin_path() . '/includes/class-wc-memberships-membership-note-email.php' );

		return $emails;
	}

}
