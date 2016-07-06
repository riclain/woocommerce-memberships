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
 * @package   WC-Memberships/Admin
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Settings_Memberships' ) ) :

/**
 * Settings class
 *
 * @since 1.0.0
 */
class WC_Settings_Memberships extends WC_Settings_Page {


	/**
	 * Setup settings class
	 *
	 * @since  1.0
	 */
	public function __construct() {

		$this->id    = 'memberships';
		$this->label = __( 'Memberships', 'woocommerce-memberships' );

		parent::__construct();

		// Set the endpoint slug for Members Area in My Account
		add_filter( 'woocommerce_account_settings', array( $this, 'add_my_account_endpoints_options' ) );
	}


	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			''         => __( 'General', 'woocommerce-memberships' ),
			'products' => __( 'Products', 'woocommerce-memberships' )
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}


	/**
	 * Get settings array
	 *
	 * @since 1.0.0
	 * @param string $current_section Optional. Defaults to empty string.
	 * @return array Array of settings
	 */
	public function get_settings( $current_section = '' ) {

		if ( 'products' == $current_section ) {

			/**
			 * Filter Memberships products Settings
			 *
			 * @since 1.0.0
			 * @param array $settings Array of the plugin settings
			 */
			$settings = apply_filters( 'wc_memberships_products_settings', array(

				array(
					'name' => __( 'Products', 'woocommerce-memberships' ),
					'type' => 'title',
					'desc' => '',
					'id'   => 'memberships_products_options',
				),

				array(
					'type'     => 'checkbox',
					'id'       => 'wc_memberships_allow_cumulative_access_granting_orders',
					'name'     => __( 'Allow cumulative purchases', 'woocommerce-memberships' ),
					'desc'     => __( 'Purchasing products that grant access to a membership in the same order extends the length of the membership.', 'woocommerce-memberships' ),
					'default'  => 'no',
				),

				array(
					'type'     => 'checkbox',
					'id'       => 'wc_memberships_hide_restricted_products',
					'name'     => __( 'Hide restricted products', 'woocommerce-memberships' ),
					'desc'     => __( 'If enabled, products with viewing restricted will be hidden from the shop catalog. Products will still be accessible directly, unless Content Restriction Mode is "Hide completely".', 'woocommerce-memberships' ),
					'default'  => 'no',
				),

				array(
					'type' => 'sectionend',
					'id'   => 'memberships_products_options'
				),

				array(
					'name' => __( 'Product Restriction Messages', 'woocommerce-memberships' ),
					'type' => 'title',
					'desc' =>  sprintf( __( '%s automatically inserts the product(s) needed to gain access. %s inserts the URL to my account page with the login form. HTML is allowed.', 'woocommerce-memberships' ), '<code>{products}</code>', '<code>{login_url}</code>' ),
					'id'   => 'memberships_product_messages',
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_viewing_restricted_message',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Viewing Restricted - Purchase Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays when purchase is required to view the product.', 'woocommerce-memberships' ),
					/* translators: %1$s is {products} merge tag, %2$s and %3$s are <a> tags for log in URL */
					'default'       => sprintf( __( 'This product can only be viewed by members. To view or purchase this product, sign up by purchasing %1$s, or %2$slog in%3$s if you are a member.', 'woocommerce-memberships' ), '{products}', '<a href="{login_url}">', '</a>' ),
					'desc_tip'      => __( 'Message displayed if viewing is restricted to members but access can be purchased.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_viewing_restricted_message_no_products',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Viewing Restricted - Membership Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays if viewing is restricted to a membership that cannot be purchased.', 'woocommerce-memberships' ),
					'default'       => __( 'This product can only be viewed by members.', 'woocommerce-memberships' ),
					'desc_tip'      => __( 'Message displayed if viewing is restricted to members and no products can grant access.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_purchasing_restricted_message',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Buying Restricted - Purchase Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays when purchase is required to buy the product.', 'woocommerce-memberships' ),
					/* translators: %1$s is {products} merge tag, %2$s and %3$s are <a> tags for log in URL */
					'default'       => sprintf( __( 'This product can only be purchased by members. To purchase this product, sign up by purchasing %1$s, or %2$slog in%3$s if you are a member.', 'woocommerce-memberships' ), '{products}', '<a href="{login_url}">', '</a>' ),
					'desc_tip'      => __( 'Message displayed if purchasing is restricted to members but access can be purchased.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_purchasing_restricted_message_no_products',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Buying Restricted - Membership Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays if purchasing is restricted to a membership that cannot be purchased.', 'woocommerce-memberships' ),
					'default'       => __( 'This product can only be purchased by members.', 'woocommerce-memberships' ),
					'desc_tip'      => __( 'Message displayed if purchasing is restricted to members and no products can grant access.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_discount_message',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Discounted - Purchase Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Message displayed to non-members if the product has a member discount.', 'woocommerce-memberships' ),
					/* translators: %1$s is {products} merge tag, %2$s and %3$s are <a> tags for log in URL */
					'default'       => sprintf( __( 'Want a discount? Become a member by purchasing %1$s, or %2$slog in%3$s if you are a member.', 'woocommerce-memberships' ), '{products}', '<a href="{login_url}">', '</a>' ),
					'desc_tip'      => __( 'Displays below add to cart buttons. Leave blank to disable.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_product_discount_message_no_products',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Product Discounted - Membership Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Message displayed to non-members if the product has a member discount, but no products can grant access.', 'woocommerce-memberships' ),
					'default'       => __( 'Want a discount? Become a member.', 'woocommerce-memberships' ),
					'desc_tip'      => __( 'Displays below add to cart buttons. Leave blank to disable.', 'woocommerce-memberships' ),
				),

				array(
					'type' => 'sectionend',
					'id'   => 'memberships_product_messages'
				),

			) );

		} else {

			/**
			 * Filter Memberships general Settings
			 *
			 * @since 1.0.0
			 * @param array $settings Array of the plugin settings
			 */
			$settings = apply_filters( 'wc_memberships_general_settings', array(

				array(
					'name' => __( 'General', 'woocommerce-memberships' ),
					'type' => 'title',
					'desc' => '',
					'id'   => 'memberships_options',
				),

				array(
					'type'     => 'select',
					'id'       => 'wc_memberships_restriction_mode',
					'name'     => __( 'Content Restriction Mode', 'woocommerce-memberships' ),
					'options'  => array(
						'hide'         => __( 'Hide completely', 'woocommerce-memberships' ),
						'hide_content' => __( 'Hide content only', 'woocommerce-memberships' ),
						'redirect'     => __( 'Redirect to page', 'woocommerce-memberships' ),
					),
					'class'    => 'wc-enhanced-select',
					'desc_tip' => __( 'Specifies the way content is restricted: whether to show nothing, excerpts, or send to a landing page.', 'woocommerce-memberships' ),
					'desc'     => __( '"Hide completely" removes all traces of content for non-members and search engines and 404s restricted pages.<br />"Hide content only" will show items in archives, but protect page or post content and comments.', 'woocommerce-memberships' ),
					'default'  => 'hide_content',
				),

				array(
					'title'    => __( 'Redirect Page', 'woocommerce-memberships' ),
					'desc'     => __( 'Select the page to redirect non-members to - should contain the [wcm_content_restricted] shortcode.', 'woocommerce-memberships' ),
					'id'       => 'wc_memberships_redirect_page_id',
					'type'     => 'single_select_page',
					'class'    => 'wc-enhanced-select-nostd js-redirect-page',
					'css'      => 'min-width:300px;',
					'desc_tip' => true,
				),

				array(
					'type'     => 'checkbox',
					'id'       => 'wc_memberships_show_excerpts',
					'name'     => __( 'Show Excerpts', 'woocommerce-memberships' ),
					'desc'     => __( 'If enabled, an excerpt of the protected content will be displayed to non-members & search engines.', 'woocommerce-memberships' ),
					'default'  => 'yes',
				),

				array(
					'type'     => 'select',
					'id'       => 'wc_memberships_display_member_login_notice',
					'name'     => __( 'Show Member Login Notice', 'woocommerce-memberships' ),
					'options'  => array(
						'never'    => __( 'Never', 'woocommerce-memberships' ),
						'cart'     => __( 'On Cart Page', 'woocommerce-memberships' ),
						'checkout' => __( 'On Checkout Page', 'woocommerce-memberships' ),
						'both'     => __( 'On both Cart & Checkout Page', 'woocommerce-memberships' ),
					),
					'class'    => 'wc-enhanced-select',
					'desc_tip' => __( 'Select when & where to display login reminder notice for guests if products in cart have member discounts.', 'woocommerce-memberships' ),
					'default'  => 'both',
				),

				array(
					'type'     => 'textarea',
					'id'       => 'wc_memberships_member_login_message',
					'class'    => 'input-text wide-input',
					'name'     => __( 'Member Login Message', 'woocommerce-memberships' ),
					/* translators: %s placeholder is for {login_url} merge tag */
					'desc'     => sprintf( __( '%s inserts the URL to the My Account page with the login form. HTML is allowed.', 'woocommerce-memberships' ),
						'<code>{login_url}</code>'
					),
					'desc_tip' => __( 'Message to remind members to log in to claim a discount. Leave blank to use the default log in message.', 'woocommerce-memberships' ),
				),

				array(
					'type' => 'sectionend',
					'id'   => 'memberships_options'
				),

				array(
					'title'         => __( 'Content Restricted Messages', 'woocommerce-memberships' ),
					'type'          => 'title',
					'desc'          =>  sprintf( __( '%s automatically inserts the product(s) needed to gain access. %s inserts the URL to my account page with the login form. HTML is allowed.', 'woocommerce-memberships' ), '<code>{products}</code>', '<code>{login_url}</code>' ),
					'id'            => 'memberships_restriction_messages'
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_content_restricted_message',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Content Restricted - Purchase Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays when purchase is required to view the content.', 'woocommerce-memberships' ),
					/* translators: %1$s is {products} merge tag, %2$s and %3$s are <a> tags for log in URL */
					'default'       => sprintf( __( 'To access this content, you must purchase %1$s, or %2$slog in%3$s if you are a member.', 'woocommerce-memberships' ), '{products}', '<a href="{login_url}">', '</a>' ),
					'desc_tip'      => __( 'Message displayed if visitor does not have access to content, but can purchase it.', 'woocommerce-memberships' ),
				),

				array(
					'type'          => 'textarea',
					'id'            => 'wc_memberships_content_restricted_message_no_products',
					'class'         => 'input-text wide-input',
					'name'          => __( 'Content Restricted - Membership Required', 'woocommerce-memberships' ),
					'desc'          => __( 'Displays if the content is restricted to a membership that cannot be purchased.', 'woocommerce-memberships' ),
					'default'       => __( 'This content is only available to members.', 'woocommerce-memberships' ),
					'desc_tip'      => __( 'Message displayed if visitor does not have access to content and no products can grant access.', 'woocommerce-memberships' ),
				),

				array(
					'type'  => 'sectionend',
					'id'    => 'memberships_restriction_messages'
				),

			) );
		}

		/**
		 * Filter Memberships Settings
		 *
		 * @since 1.0.0
		 * @param array $settings Array of the plugin settings
		 */
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}


	/**
	 * Output the settings
	 *
	 * @since 1.0
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}


	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}


	/**
	 * Add custom slugs for endpoints in My Account page
	 *
	 * Filter callback for woocommerce_account_settings
	 *
	 * @since 1.4.0
	 * @param array $settings
	 * @return array $settings
	 */
	public function add_my_account_endpoints_options( $settings ) {

		$new_settings = array();

		foreach ( $settings as $setting ) {

			$new_settings[] = $setting;

			if ( isset( $setting['id'] ) && 'woocommerce_logout_endpoint' === $setting['id'] ) {

				$new_settings[] = array(
						'title'    => __( 'My Membership', 'woocommerce-memberships' ),
						'desc'     => __( 'Endpoint for the My Account &rarr; My Membership', 'woocommerce-memberships' ),
						'id'       => 'woocommerce_myaccount_members_area_endpoint',
						'type'     => 'text',
						'default'  => 'members-area',
						'desc_tip' => true,
				);
			}
		}

		return $new_settings;
	}


}

endif;

return new WC_Settings_Memberships();
