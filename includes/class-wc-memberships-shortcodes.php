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
 * Memberships shortcodes
 *
 * This class is responsible for adding and handling shortcodes
 * for Memberships
 *
 * @since 1.0.0
 */
class WC_Memberships_Shortcodes {


	/**
	 * Initialize and register the Memberships post types
	 *
	 * @since 1.0.0
	 */
	public static function initialize() {

		$shortcodes = array(
			'wcm_restrict'           => __CLASS__ . '::restrict',
			'wcm_nonmember'          => __CLASS__ . '::nonmember',
			'wcm_content_restricted' => __CLASS__ . '::content_restricted',
		);

		foreach ( $shortcodes as $shortcode => $function ) {

			/**
			 * Filter the shortcode tag
			 *
			 * @since 1.0.0
			 * @param string $shortcode Shortcode tag
			 */
			add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
		}

	}


	/**
	 * Restrict content shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @param string|null $content
	 * @return string Shortcode result
	 */
	public static function restrict( $atts, $content = null ) {

		if ( isset( $atts['plans'] ) ) {
			$atts['plans'] = array_map( 'trim', explode( ',', $atts['plans'] ) );
		}

		if ( isset( $atts['start_after_trial'] ) ) {
			$atts['start_after_trial'] = 'yes' == $atts['start_after_trial'];
		}

		$atts = shortcode_atts( array(
			'plans'             => null,
			'delay'             => null,
			'start_after_trial' => false,
		), $atts );

		ob_start();

		wc_memberships_restrict( do_shortcode( $content ), $atts['plans'], $atts['delay'], $atts['start_after_trial'] );

		return ob_get_clean();
	}


	/**
	 * Nonmember content shortcode
	 *
	 * @since 1.1.0
	 * @param array $atts Shortcode attributes
	 * @param string|null $content
	 * @return void|string Shortcode result
	 */
	public static function nonmember( $atts, $content = null ) {

		// Hide non-member messages for super users
		if ( current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {
			return;
		}

		$plans         = wc_memberships_get_membership_plans();
		$active_member = array();

		foreach ( $plans as $plan ) {
			$active = wc_memberships_is_user_active_member( get_current_user_id(), $plan );
			array_push( $active_member, $active );
		}

		ob_start();

		if ( ! in_array( true, $active_member ) ) {
			echo do_shortcode( $content );
		}

		return ob_get_clean();
	}


	/**
	 * Restricted content messages
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @param string|null $content
	 * @return string Shortcode result
	 */
	public static function content_restricted( $atts, $content = null ) {

		// Get the restricted post
		$post_id = isset( $_GET['r'] ) ? absint( $_GET['r'] ) : null;

		// Skip if post ID not provided
		if ( ! $post_id ) {
			return '';
		}

		$post = get_post( $post_id );

		// Skip if post was not found
		if ( ! $post ) {
			return '';
		}

		$output = '';

		// Special handling for products
		if ( in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ) ) ) {

			if ( 'yes' == get_option( 'wc_memberships_show_excerpts' ) ) {
				$output = apply_filters( 'woocommerce_short_description', $post->post_excerpt );
			}

			// Check if user has access to viewing restricted content
			if ( ! current_user_can( 'wc_memberships_view_restricted_product', $post->ID ) ) {
				$output .= '<div class="wc-memberships-content-restricted-message">' . wc_memberships()->frontend->get_product_viewing_restricted_message( $post->ID ) . '</div>';
			}

			// Check if user has access to delayed content
			else if ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post->ID ) ) {
				$output .= '<div class="wc-memberships-content-delayed-message">' . wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $post->ID, 'view' ) . '</div>';
			}

		// All other content
		} else {

			if ( 'yes' == get_option( 'wc_memberships_show_excerpts' ) ) {
				$output = apply_filters( 'get_the_excerpt', $post->post_excerpt );
			}

			// Check if user has access to restricted content
			if ( ! current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID ) ) {

				$output .= '<div class="wc-memberships-content-restricted-message">' . wc_memberships()->frontend->get_content_restricted_message( $post->ID ) . '</div>';

			// Check if user has access to delayed content
			} elseif ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post->ID ) ) {

				$output .= '<div class="wc-memberships-content-delayed-message">' . wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $post->ID ) . '</div>';

			}
		}

		return $output;
	}

}
