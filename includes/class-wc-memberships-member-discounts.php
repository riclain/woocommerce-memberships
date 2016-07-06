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
 * Member Discounts class
 *
 * This class handles all purchasing discounts for members
 *
 * @since 1.3.0
 */
class WC_Memberships_Member_Discounts {


	/** @var bool Whether the current user is logged in or not */
	private $is_member_logged_in = false;

	/** @var string Tax display shop setting (incl or excl) */
	private $tax_display_mode = '';


	/**
	 * Set up member discounts
	 *
	 * We follow here a pattern common in many price-affecting extensions,
	 * due to the need to produce a "price before/after discount" type of HTML output,
	 * so shop customers can easily understand the deal they're being offered.
	 *
	 * To do so we need to juggle WooCommerce prices, we start off by instantiating
	 * this class with our discounts active, so we can be sure to always pass those
	 * to other extensions if a member is logged in. Then, when we want to show prices
	 * in front end we need to deactivate price modifications, compare the original
	 * price with the price resulting from discount calculations and if a discount is
	 * found (price difference) we strikethrough the original price to show what it was
	 * like before discount, so we reactivate price modifiers, and finally show prices
	 * after modifications.
	 *
	 * Extensions and third party code that need to know if Memberships price modifiers
	 * are being applied or not in these two phases, can use doing_action and hook into
	 * 'wc_memberships_discounts_enable_price_adjustments' and
	 * 'wc_memberships_discounts_disable_price_adjustments' (and their html counterparts)
	 * or call directly the callbacks found in this class, which we use to add and remove
	 * price modifier filters. Or, if there's need to deactivate or activate Memberships
	 * price modifiers directly, the public callback methods that these actions use could
	 * also be invoked for this purpose.
	 *
	 * @see WC_Memberships_Member_Discounts::enable_price_adjustments()
	 * @see WC_Memberships_Member_Discounts::disable_price_adjustments()
	 *
	 * @since 1.3.0
	 */
	public function __construct() {

		$this->is_member_logged_in = is_user_logged_in();
		$this->tax_display_mode    = get_option( 'woocommerce_tax_display_shop' );

		// initialize discount actions
		// that will be called in this class methods
		add_action( 'wc_memberships_discounts_enable_price_adjustments',       array( $this, 'enable_price_adjustments' ) );
		add_action( 'wc_memberships_discounts_enable_price_html_adjustments',  array( $this, 'enable_price_html_adjustments' ) );
		add_action( 'wc_memberships_discounts_disable_price_adjustments',      array( $this, 'disable_price_adjustments' ) );
		add_action( 'wc_memberships_discounts_disable_price_html_adjustments', array( $this, 'disable_price_html_adjustments' ) );
		// start off by activating discounts for members
		do_action( 'wc_memberships_discounts_enable_price_adjustments' );
		do_action( 'wc_memberships_discounts_enable_price_html_adjustments' );

		// force calculations in cart
		add_filter( 'woocommerce_update_cart_action_cart_updated', '__return_true' );

		// price modifiers that will show before/after discount
		add_filter( 'woocommerce_cart_item_price',     array( $this, 'on_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_get_variation_price', array( $this, 'on_get_variation_price' ), 10, 4 );

		// member discount badges
		add_action( 'woocommerce_before_shop_loop_item_title',   'wc_memberships_show_product_loop_member_discount_badge' );
		add_action( 'woocommerce_before_single_product_summary', 'wc_memberships_show_product_member_discount_badge' );

		// make sure that the display of the "On Sale" badge is honoured
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'product_is_on_sale' ), 1, 2 );
	}


	/**
	 * Handle sale status of products
	 *
	 * @since 1.6.2
	 * @param bool $on_sale Whether the product is on sale
	 * @param \WC_Product $product The product object
	 * @return bool
	 */
	public function product_is_on_sale( $on_sale, $product ) {

		if ( ! is_admin() && ( ! $product instanceof WC_Product || ( $this->is_member_logged_in && wc_memberships()->get_rules_instance()->product_has_member_discount( SV_WC_Plugin_Compatibility::product_get_id( $product ) ) ) ) ) {
			return $on_sale;
		}

		// disable Memberships member discount adjustments
		do_action( 'wc_memberships_discounts_disable_price_adjustments' );

		/** @see WC_Product_Variable::is_on_sale() */
		if ( $product->is_type( array( 'variable', 'variable-subscription' ) ) ) {

			$prices  = $product->get_variation_prices();

			if ( $prices['regular_price'] !== $prices['sale_price'] && $prices['sale_price'] === $prices['price'] ) {
				$on_sale = true;
			}

		/** @see WC_Product::is_on_sale() */
		} else {

			$on_sale = $product->get_sale_price() !== $product->get_regular_price() && $product->get_sale_price() === $product->get_price();
		}

		// re-enable Memberships member discount adjustments
		do_action( 'wc_memberships_discounts_enable_price_adjustments' );

		return $on_sale;
	}


	/**
	 * Get price inclusive or exclusive of tax, according to tax setting
	 *
	 * @since 1.6.0
	 * @param \WC_Product|\WC_Product_Variation $product Product or variation
	 * @return float
	 */
	private function get_price_with_tax( $product ) {

		$price = $product->get_price();

		if ( 'incl' === $this->tax_display_mode ) {
			$price = $product->get_price_including_tax();
		} elseif ( 'excl' === $this->tax_display_mode ) {
			$price = $product->get_price_excluding_tax();
		}

		return (float) $price;
	}


	/**
	 * Apply purchasing discounts to product price
	 *
	 * @since 1.0.0
	 * @param string|float $price Price to discount (normally a float, maybe a string number)
	 * @param \WC_Product $product The product object
	 * @return float
	 */
	public function on_get_price( $price, $product ) {

		// we need a logged in user to know if it's a member with discounts
		if ( ! $this->is_member_logged_in ) {
			$discounted_price = $price;
		// see if we have a discount for this user and this product
		} else {
			$discounted_price = $this->get_discounted_price( $price, $product );
		}

		return is_numeric( $discounted_price ) ? (float) $discounted_price : (float) $price;
	}


	/**
	 * Adjust discounted product price HTML
	 *
	 * @since 1.3.0
	 * @param string $html The price HTML maybe after discount
	 * @param \WC_Product $product The product object for which we may have discounts
	 * @return string The original price HTML if no discount or a new formatted string showing before/after discount
	 */
	public function on_price_html( $html, $product ) {

		/**
		 * Controls whether or not member prices should use discount format when displayed
		 *
		 * @since 1.3.0
		 * @param bool $use_discount_format Defaults to true
		 */
		if ( ! apply_filters( 'wc_memberships_member_prices_use_discount_format', true ) ) {
			return $html;
		}

		// temporarily disable price adjustments
		do_action( 'wc_memberships_discounts_disable_price_adjustments' );

		// get the base price without discounts
		$base_price = $this->get_price_with_tax( $product );

		// re-enable price adjustments
		do_action( 'wc_memberships_discounts_enable_price_adjustments' );

		if ( ! $this->has_discounted_price( $base_price, SV_WC_Plugin_Compatibility::product_get_id( $product ) ) ) {
			return $html;
		}

		$html_after_discount = $html;

		// for variable products, manually calculate the original price
		if ( $product->is_type( 'variable' ) ) {

			$regular_min = $product->get_variation_regular_price( 'min', true );
			$regular_max = $product->get_variation_regular_price( 'max', true );

			$html_before_discount = $regular_min !== $regular_max ? sprintf( _x( '%1$s&ndash;%2$s', 'Price range: from-to', 'woocommerce-memberships' ), wc_price( $regular_min ), wc_price( $regular_max ) ) : wc_price( $regular_min );

		} else {

			/**
			 * Controls whether or not member prices should display sale prices as well
			 *
			 * @since 1.3.0
			 * @param bool $display_sale_price Defaults to false
			 */
			$display_sale_price = apply_filters( 'wc_memberships_member_prices_display_sale_price', false );

			if ( ! $display_sale_price ) {
				add_filter( 'woocommerce_product_is_on_sale', array( $this, 'disable_sale_price' ) );
			}

			// temporarily disable membership price adjustments
			do_action( 'wc_memberships_discounts_disable_price_adjustments' );
			do_action( 'wc_memberships_discounts_disable_price_html_adjustments' );

			// grab the standard price html
			$html_before_discount = $product->get_price_html();

			// re-enable membership price adjustments
			do_action( 'wc_memberships_discounts_enable_price_adjustments' );
			do_action( 'wc_memberships_discounts_enable_price_html_adjustments' );

			if ( ! $display_sale_price ) {
				remove_filter( 'woocommerce_product_is_on_sale', array( $this, 'disable_sale_price' ) );
			}
		}

		// string prices do not match, we have a discount
		if ( $html_after_discount !== $html_before_discount ) {
			$html = '<del>' . $html_before_discount . '</del> <ins> ' . $html_after_discount . '</ins>';
		}

		// add a "Member Discount" badge for single variation prices
		if ( $product->is_type( 'variation' ) ) {

			$badge = sprintf( /* translators: %1$s - opening HTML <span> tag, %2$s - closing HTML </span> tag */
				__( '%1$sMember discount!%2$s', 'woocommerce-memberships' ),
				'<span class="wc-memberships-variation-member-discount">',
				'</span>'
			);

			/**
			 * Filter the variation member discount badge.
			 *
			 * @since 1.3.2
			 * @param string $badge The badge HTML.
			 * @param \WC_Product|\WC_Product_Variation $variation The product variation.
			 */
			$badge = apply_filters( 'wc_memberships_variation_member_discount_badge', $badge, $product );

			$html .= " {$badge}";
		}

		return $html;
	}


	/**
	 * Adjust discounted cart item price HTML
	 *
	 * @since 1.3.0
	 * @param string $html Price HTML
	 * @param array $cart_item The cart item data
	 * @param string $cart_item_key Cart item key
	 * @return string
	 */
	public function on_cart_item_price( $html, $cart_item, $cart_item_key ) {

		// get the product
		$product = $cart_item['data'];

		// temporarily disable our price adjustments
		do_action( 'wc_memberships_discounts_disable_price_adjustments' );

		// so we can get the base price without member discounts
		// also, in cart we need to account for tax display
		$price = $this->get_price_with_tax( $product );

		// re-enable disable our price adjustments
		do_action( 'wc_memberships_discounts_enable_price_adjustments' );

		if ( $this->has_discounted_price( $price, $product ) ) {

			// in cart, we need to account for tax display
			$discounted_price = $this->get_price_with_tax( $product );

			/** This filter is documented in class-wc-memberships-member-discounts.php **/
			$use_discount_format = apply_filters( 'wc_memberships_use_discount_format', true );

			// output html price before/after discount
			if ( $discounted_price < $price && $use_discount_format ) {
				$html = '<del>' . wc_price( $price ) . '</del><ins> ' . wc_price( $discounted_price ) . '</ins>';
			}
		}

		return $html;
	}


	/**
	 * Adjust variation price
	 *
	 * @since 1.3.0
	 * @param float $price The product price, maybe discounted
	 * @param \WC_Product $product The product object
	 * @param string $min_or_max Min-max prices of variations
	 * @param bool $display If to be displayed
	 * @return float
	 */
	public function on_get_variation_price( $price, $product, $min_or_max, $display ) {

		// defaults
		$calc_price = $price;
		$min_price  = $price;
		$max_price  = $price;

		// get variation ids
		$children = $product->get_children();

		if ( ! empty( $children ) ) {

			foreach ( $children as $variation_id ) {

				if ( $display ) {

					if ( $variation = $product->get_child( $variation_id ) ) {

						// make sure we start from the normal un-discounted price
						do_action( 'wc_memberships_discounts_disable_price_adjustments' );

						// in display mode, we need to account for taxes
						$base_price = $this->get_price_with_tax( $variation );
						$calc_price = $base_price;

						// try getting the discounted price for the variation
						$discounted_price = $this->get_discounted_price( $base_price, $variation->id );

						// if there's a difference, grab discounted price
						if ( is_numeric( $discounted_price ) && $base_price !== $discounted_price ) {
							$calc_price = $discounted_price;
						}

						// re-enable discounts in pricing flow
						do_action( 'wc_memberships_discounts_enable_price_adjustments' );
					}

				} else {
					$calc_price = (float) get_post_meta( $variation_id, '_price', true );
				}

				if ( $min_price === null || $calc_price < $min_price ) {
					$min_price = $calc_price;
				}

				if ( $max_price === null || $calc_price > $max_price ) {
					$max_price = $calc_price;
				}
			}
		}

		if ( $min_or_max === 'min' ) {
			return (float) $min_price;
		} elseif ( $min_or_max === 'max' ) {
			return (float) $max_price;
		}

		return (float) $price;
	}


	/**
	 * Add the current user ID to the variation prices hash for caching.
	 *
	 * @since 1.3.2
	 * @param array $data The existing hash data
	 * @param \WC_Product $product The current product variation
	 * @param bool $display Whether the prices are for display
	 * @return array $data The hash data with a user ID added if applicable
	 */
	public function set_user_variation_prices_hash( $data, $product, $display ) {

		if ( $this->is_member_logged_in ) {
			$data[] = get_current_user_id();
		}

		return $data;
	}


	/**
	 * Get product discounted price for member
	 *
	 * @since 1.3.0
	 * @param float $base_price Original price
	 * @param int|\WC_Product $product Product ID or product object
	 * @param int|null $user_id Optional, defaults to current user id
	 * @return float|null The discounted price or null if no discount applies
	 */
	public function get_discounted_price( $base_price, $product, $user_id = null ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
		}

		$price          = null;
		$product_id     = SV_WC_Plugin_Compatibility::product_get_id( $product );
		$discount_rules = wc_memberships()->get_rules_instance()->get_user_product_purchasing_discount_rules( $user_id, $product_id );

		if ( ! empty( $discount_rules ) ) {

			$price = (float) $base_price;

			// find out the discounted price for the current user
			foreach ( $discount_rules as $rule ) {

				switch ( $rule->get_discount_type() ) {

					case 'percentage':
						$discounted_price = $price * ( 100 - $rule->get_discount_amount() ) / 100;
					break;

					case 'amount':
						$discounted_price = $price - $rule->get_discount_amount();
					break;
				}

				// make sure that the lowest price gets applied and doesn't become negative
				if ( isset( $discounted_price ) && $discounted_price < $price ) {
					$price = max( $discounted_price, 0 );
				}
			}

			// sanity check
			if ( $price >= $base_price ) {
				$price = null;
			}
		}

		return $price;
	}


	/**
	 * Check if the product is discounted for the user
	 *
	 * @since 1.3.0
	 * @param float $base_price Original price
	 * @param int|\WC_product $product Product ID or object
	 * @param null|int $user_id Optional, defaults to current user id
	 * @return bool
	 */
	public function has_discounted_price( $base_price, $product, $user_id = null ) {
		return is_numeric( $this->get_discounted_price( $base_price, $product, $user_id ) );
	}


	/**
	 * Disable 'on sale' for a product
	 *
	 * @see WC_Memberships_Member_Discounts::on_price_html()
	 *
	 * @since 1.3.0
	 * @return false
	 */
	public function disable_sale_price() {
		return false;
	}


	/**
	 * Enable price adjustments
	 *
	 * Calling this method will **enable** Membership adjustments
	 * for product prices that have member discounts for logged in members
	 *
	 * @see WC_Memberships_Member_Discounts::__construct() docblock for additional notes
	 * @see WC_Memberships_Member_Discounts::enable_price_html_adjustments() which you'll probably want to use too
	 *
	 * @since 1.3.0
	 */
	public function enable_price_adjustments() {

		// apply membership discount to product price
		add_filter( 'woocommerce_get_price',                 array( $this, 'on_get_price' ), 10, 2 );
		add_filter( 'woocommerce_variation_prices_price',    array( $this, 'on_get_price' ), 10, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'set_user_variation_prices_hash' ), 200, 3 );
	}


	/**
	 * Disable price adjustments
	 *
	 * Calling this method will **disable** Membership adjustments
	 * for product prices that have member discounts for logged in members
	 *
	 * @see WC_Memberships_Member_Discounts::__construct() docblock for additional notes
	 * @see WC_Memberships_Member_Discounts::disable_price_html_adjustments() which you'll probably want to use too
	 *
	 * @since 1.3.0
	 */
	public function disable_price_adjustments() {

		// restore price to original amount before membership discount
		remove_filter( 'woocommerce_get_price',                 array( $this, 'on_get_price' ) );
		remove_filter( 'woocommerce_variation_prices_price',    array( $this, 'on_get_price' ) );
		remove_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'set_user_variation_prices_hash' ) );
	}


	/**
	 * Enable price HTML adjustments
	 *
	 * @see WC_Memberships_Member_Discounts::__construct() docblock for additional notes
	 * @see WC_Memberships_Member_Discounts::enable_price_adjustments() which you'll probably want to use too
	 *
	 * @since 1.3.0
	 */
	public function enable_price_html_adjustments() {

		// filter the prices to apply member discounts
		add_filter( 'woocommerce_variation_price_html', array( $this, 'on_price_html' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html',       array( $this, 'on_price_html' ), 10, 2 );
	}


	/**
	 * Disable price HTML adjustments
	 *
	 * @see WC_Memberships_Member_Discounts::__construct() docblock for additional notes
	 * @see WC_Memberships_Member_Discounts::disable_price_adjustments() which you'll probably want to use too
	 *
	 * @since 1.3.0
	 */
	public function disable_price_html_adjustments() {

		// so we can display prices before discount
		remove_filter( 'woocommerce_get_price_html',       array( $this, 'on_price_html' ) );
		remove_filter( 'woocommerce_variation_price_html', array( $this, 'on_price_html' ) );
	}


}
