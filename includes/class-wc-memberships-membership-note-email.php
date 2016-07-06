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

if ( ! class_exists( 'WC_Memberships_Membership_Note_Email' ) ) :

/**
 * Membership Note Order Email
 *
 * Membership note emails are sent when you add a membership note and notify the member.
 */
class WC_Memberships_Membership_Note_Email extends WC_Email {


	/** @private object Membership note */
	private $membership_note;


	/**
	 * Constructor
	 */
	function __construct() {

		$this->id             = 'wc_memberships_membership_note';
		$this->title          = __( 'Membership note', 'woocommerce-memberships' );
		$this->description    = __( 'Membership note emails are sent when you add a membership note and notify member.', 'woocommerce-memberships' );

		$this->template_html  = 'emails/membership-note.php';
		$this->template_plain = 'emails/plain/membership-note.php';

		$this->subject        = __( 'Note added to your {site_title} membership', 'woocommerce-memberships');
		$this->heading        = __( 'A note has been added about your membership', 'woocommerce-memberships');

		// Triggers
		add_action( 'wc_memberships_new_user_membership_note_notification', array( $this, 'trigger' ) );

		// Call parent constructor
		parent::__construct();
	}


	/**
	 * Is customer email
	 *
	 * @since 1.5.0
	 * @return true
	 */
	public function is_customer_email() {
		return true;
	}


	/**
	 * Trigger the membership note email
	 *
	 * @param array $args Optional
	 */
	function trigger( $args ) {

		if ( $args && isset( $args['notify'] ) && $args['notify'] ) {

			$defaults = array(
				'user_membership_id' => '',
				'membership_note'    => ''
			);

			$args = wp_parse_args( $args, $defaults );

			// TODO refactor to remove usage of `extract` here
			extract( $args );

			if ( $user_membership_id && ( $this->object = wc_memberships_get_user_membership( $user_membership_id ) ) ) {

				$user = get_userdata( $this->object->get_user_id() );

				$this->recipient       = $user->user_email;
				$this->membership_note = $membership_note;

			} else {
				return;
			}
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	/**
	 * Get email HTML content
	 *
	 * @return string HTML content
	 */
	function get_content_html() {

		ob_start();

		wc_get_template( $this->template_html, array(
			'user_membership' => $this->object,
			'email_heading'   => $this->get_heading(),
			'membership_note' => $this->membership_note,
			'sent_to_admin'   => false,
			'plain_text'      => false
		) );

		return ob_get_clean();
	}


	/**
	 * Get email plain text content
	 *
	 * @return string Plain text content
	 */
	function get_content_plain() {

		ob_start();

		wc_get_template( $this->template_plain, array(
			'user_membership' => $this->object,
			'email_heading'   => $this->get_heading(),
			'membership_note' => $this->membership_note,
			'sent_to_admin'   => false,
			'plain_text'      => true
		) );

		return ob_get_clean();
	}


}

endif;

return new WC_Memberships_Membership_Note_Email();
