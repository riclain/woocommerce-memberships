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
 * Discounts integration class for WooCommerce Subscriptions
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Discounts {


	/** @var bool Whether to enable discounting for subscriptions sign up fees or not */
	private $enable_subscriptions_sign_up_fees_discount = false;


	/**
	 * Add hooks
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		$this->enable_subscriptions_sign_up_fees_discount = 'yes' === get_option( 'wc_memberships_enable_subscriptions_sign_up_fees_discounts', 'no' );

		// create an option in settings to enable sign up fees discounts
		add_filter( 'wc_memberships_products_settings', array( $this, 'enable_discounts_to_sign_up_fees' ) );

		// make sure the price of subscription renewal cart items is honoured (i.e. not discounted)
		add_action( 'woocommerce_before_calculate_totals',                     array( $this, 'disable_price_adjustments_for_renewal' ), 11 );
		add_action( 'wc_memberships_discounts_enable_price_adjustments',       array( $this, 'disable_price_adjustments_for_renewal' ), 11 );
		add_action( 'wc_memberships_discounts_enable_price_html_adjustments',  array( $this, 'disable_price_adjustments_for_renewal' ), 11 );

		if ( true === $this->enable_subscriptions_sign_up_fees_discount ) {

			// maybe adjust sign up fee amount at product level (when viewing product)
			add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'maybe_adjust_product_sign_up_fee' ), 20, 2 );
			// maybe adjust sign up fee amount at cart level (when adding product to cart or checkout)
			add_filter( 'woocommerce_subscriptions_cart_sign_up_fee',    array( $this, 'maybe_adjust_cart_sign_up_fee' ) );

			// handle before/after sign up fee displayed amount
			add_action( 'wc_memberships_discounts_enable_price_adjustments',  array( $this, 'while_enabling_price_adjustments' ) );
			add_action( 'wc_memberships_discounts_disable_price_adjustments', array( $this, 'while_disabling_price_adjustments' ) );
		}
	}


	/**
	 * Do not discount the price of subscription renewal items in the cart
	 *
	 * If the cart contains a renewal (which will be the entire contents of the cart,
	 * because it can only contain a renewal), disable the discounts applied
	 * by @see WC_Memberships_Member_Discounts::enable_price_adjustments() because
	 * we want to honour the renewal price.
	 *
	 * However, we also only want to disable prices for the renewal cart items only,
	 * not other products which should be discounted which may be displayed outside
	 * the cart, so we need to be selective about when we disable the price adjustments
	 * by checking a mix of cart/checkout constants and hooks to see if we're in
	 * something relating to the cart or not.
	 *
	 * @since 1.6.1
	 */
	public function disable_price_adjustments_for_renewal() {

		if ( false !== wcs_cart_contains_renewal() ) {

			$disable_price_adjustments = false;

			if ( is_checkout() || is_cart() || defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				$disable_price_adjustments = true;
			} elseif ( did_action( 'woocommerce_before_mini_cart' ) > did_action( 'woocommerce_after_mini_cart' ) ) {
				$disable_price_adjustments = true;
			}

			if ( $disable_price_adjustments ) {
				do_action( 'wc_memberships_discounts_disable_price_adjustments' );
				do_action( 'wc_memberships_discounts_disable_price_html_adjustments' );
			}
		}
	}


	/**
	 * Add option to product settings
	 *
	 * Filters product settings fields and add a checkbox
	 * to let user choose to enable discounts for subscriptions sign up fees
	 *
	 * @since 1.6.0
	 * @param $product_settings
	 * @return array $product_settings
	 */
	public function enable_discounts_to_sign_up_fees( $product_settings ) {

		$new_option = array(
			array(
				'type'     => 'checkbox',
				'id'       => 'wc_memberships_enable_subscriptions_sign_up_fees_discounts',
				'name'     => __( 'Discounts apply to subscriptions sign up fees', 'woocommerce-memberships' ),
				'desc'     => __( 'If enabled, membership discounts will also apply to sign up fees of subscription products.', 'woocommerce-memberships' ),
				'default'  => 'no',
			),
		);

		array_splice( $product_settings, 2, 0, $new_option );

		return $product_settings;
	}


	/**
	 * While enabling membership discount price adjustments
	 *
	 * @see WC_Memberships_Member_Discounts::enable_price_html_adjustments()
	 *
	 * @since 1.6.0
	 */
	public function while_enabling_price_adjustments() {

		// show the discounted sign up fee price amount
		remove_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'display_original_sign_up_fees' ) );
	}


	/**
	 * While disabling membership discount price adjustments
	 *
	 * @see WC_Memberships_Member_Discounts::disable_price_html_adjustments()
	 *
	 * @since 1.6.0
	 */
	public function while_disabling_price_adjustments() {

		// show the original sign up fee price amount
		add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'display_original_sign_up_fees' ), 10, 1 );
	}


	/**
	 * Display the original sign up fee amount before discount
	 *
	 * Utility action callback to prevent discounting the original sign up fee price
	 *
	 * @since 1.6.0
	 * @param float $original_sign_up_fee
	 * @return float
	 */
	public function display_original_sign_up_fees( $original_sign_up_fee ) {
		return (float) $original_sign_up_fee;
	}


	/**
	 * Apply member discounts to subscription product sign up fee as well
	 * at product level (i.e. when viewing product)
	 *
	 * @see enable_discounts_to_sign_up_fees
	 * @see display_original_sign_up_fees
	 *
	 * @since 1.6.0
	 * @param float $subscription_sign_up_fee Sign up fee
	 * @param false|\WC_Product $subscription_product A Subscription product
	 * @return float Sign up fee (perhaps discounted) value
	 */
	public function maybe_adjust_product_sign_up_fee( $subscription_sign_up_fee, $subscription_product ) {

		$discounted_fee = null;

		// bail out on any of the following conditions:
		if ( ! $subscription_product                                         // not a subscription product
		     || ! $this->enable_subscriptions_sign_up_fees_discount          // sign up fee discounting is disabled
		     || ! isset( $subscription_product->subscription_sign_up_fee )   // no sign up fee is set
		     || 0 === (int) $subscription_sign_up_fee                        // the sign up fee is 0
		     || has_filter( 'woocommerce_subscriptions_product_sign_up_fee', // running Memberships filtering to show the price before discount
				array( $this, 'display_original_sign_up_fees' ) ) ) {

			$discounted_fee = $subscription_sign_up_fee;

		} elseif ( $discounts_instance = wc_memberships()->get_member_discounts_instance() ) {

			$product = wc_get_product( $subscription_product );

			if ( $product ) {

				$discounted_fee = $discounts_instance->get_discounted_price( (float) $subscription_sign_up_fee, $product );
			}
		}

		return is_numeric( $discounted_fee ) ? (float) $discounted_fee : (float) $subscription_sign_up_fee;;
	}


	/**
	 * Apply member discounts to subscription product sign up fee
	 * at cart level (i.e. when product is added to cart)
	 *
	 * @since 1.6.0
	 * @param int|float $sign_up_fee
	 * @return int|float
	 */
	public function maybe_adjust_cart_sign_up_fee( $sign_up_fee ) {

		$cart = WC()->cart;

		// bail out if there's no Subscription in cart or it's a renewal (no sign up fee)
		if ( $cart->is_empty() || wcs_cart_contains_renewal() || ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return $sign_up_fee;
		}

		$sign_up_fee = 0;
		$discounts   = wc_memberships()->get_member_discounts_instance();

		foreach ( $cart->cart_contents as $cart_item ) {

			if ( isset( $cart_item['data']->subscription_sign_up_fee ) ) {

				$discounted_sign_up_fee = $discounts->get_discounted_price( $cart_item['data']->subscription_sign_up_fee, $cart_item['data'] );

				$sign_up_fee += is_numeric( $discounted_sign_up_fee ) ? (float) $discounted_sign_up_fee : $cart_item['data']->subscription_sign_up_fee;

			} elseif ( WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) ) {

				$product_sign_up_fee    = WC_Subscriptions_Product::get_sign_up_fee( $cart_item['data'] );
				$discounted_sign_up_fee = $discounts->get_discounted_price( $product_sign_up_fee, $cart_item['data'] );

				$sign_up_fee += is_numeric( $discounted_sign_up_fee ) ? (float) $discounted_sign_up_fee : $product_sign_up_fee;

			}
		}

		return (float) $sign_up_fee;
	}


}
