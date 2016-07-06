<?php
/**
 * Plugin Name: WooCommerce Memberships
 * Plugin URI: http://www.woothemes.com/products/woocommerce-memberships/
 * Description: Sell memberships that provide access to restricted content, products, discounts, and more!
 * Author: WooThemes / SkyVerge
 * Author URI: http://www.woothemes.com/
 * Version: 1.5.2
 * Text Domain: woocommerce-memberships
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2014-2016 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   Memberships
 * @author    SkyVerge
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// Plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '9288e7609ad0b487b81ef6232efa5cfc', '958589' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.2.0', __( 'WooCommerce Memberships', 'woocommerce-memberships' ), __FILE__, 'init_woocommerce_memberships', array( 'minimum_wc_version' => '2.3.6', 'minimum_wp_version' => '3.9', 'backwards_compatible' => '4.2.0' ) );

function init_woocommerce_memberships() {


/**
 * WooCommerce Memberships Main Plugin Class
 *
 * @since 1.0.0
 */
class WC_Memberships extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '1.5.2';

	/** @var WC_Memberships single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'memberships';

	/** plugin text domain, DEPRECATED as of 1.5.0 */
	const TEXT_DOMAIN = 'woocommerce-memberships';

	/** @var \WC_Memberships_Admin instance */
	public $admin;

	/** @var \WC_Memberships_Frontend instance */
	public $frontend;

	/** @var \WC_Memberships_Checkout instance */
	public $checkout;

	/** @var \WC_Memberships_Restrictions instance */
	public $restrictions;

	/** @var \WC_Memberships_Emails instance */
	public $emails;

	/** @var \WC_Memberships_Capabilities instance */
	public $capabilities;

	/** @var \WC_Memberships_Member_Discounts instance */
	public $member_discounts;

	/** @var \WC_Memberships_AJAX instance */
	public $ajax;

	/** @var \WC_Memberships_Rules instance */
	public $rules;

	/** @var \WC_Memberships_Membership_Plans instance */
	public $plans;

	/** @var \WC_Memberships_User_Memberships instance */
	public $user_memberships;

	/** @var array Query vars for custom rewrite endpoints */
	private $query_vars = array();

	/** @var WC_Memberships_Integration_Subscriptions instance */
	protected $subscriptions;

	/** @var bool helper for lazy subscriptions active check */
	private $subscriptions_active;

	/** @var bool helper for lazy user switching active check */
	private $user_switching_active;

	/** @var bool helper for lazy groups active check */
	private $groups_active;

	/** @var bool helper for lazy bookings active check */
	private $bookings_active;

	/** @var bool helper for lazy product add-ons active check */
	private $product_addons_active;


	/**
	 * Initializes the plugin
	 *
	 * @since 1.0.0
	 * @return \WC_Memberships
	 */
	public function __construct() {

		parent::__construct( self::PLUGIN_ID, self::VERSION );

		// Include required files
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ) );

		add_action( 'init', array( $this, 'init' ) );

		// Add custom endpoints
		$this->init_endpoints();

		// Make sure template files are searched for in our plugin
		add_filter( 'woocommerce_locate_template',      array( $this, 'locate_template' ), 20, 3 );
		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 20, 3 );

		add_action( 'woocommerce_order_status_completed',  array( $this, 'grant_membership_access' ), 11 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'grant_membership_access' ), 11 );

		// Lifecycle
		add_action( 'admin_init', array ( $this, 'maybe_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}


	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-post-types.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-emails.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-rules.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-membership-plans.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-user-memberships.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-capabilities.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-member-discounts.php' );

		// Global functions
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-membership-plan-functions.php' );
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-user-membership-functions.php' );

		$this->emails           = new WC_Memberships_Emails();
		$this->rules            = new WC_Memberships_Rules();
		$this->plans            = new WC_Memberships_Membership_Plans();
		$this->user_memberships = new WC_Memberships_User_Memberships();
		$this->capabilities     = new WC_Memberships_Capabilities();
		$this->member_discounts = new WC_Memberships_Member_Discounts();

		// Frontend includes
		if ( ! is_admin() ) {
			$this->frontend_includes();
		}

		// Admin includes
		if ( is_admin() && ! is_ajax() ) {
			$this->admin_includes();
		}

		// AJAX includes
		if ( is_ajax() ) {
			$this->ajax_includes();
		}

		// Integrations
		$this->integration_includes();
	}


	/**
	 * Include required frontend files
	 *
	 * @since 1.0.0
	 */
	private function frontend_includes() {

		require_once( $this->get_plugin_path() . '/includes/wc-memberships-template-functions.php' );
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-shortcodes.php' );

		WC_Memberships_Shortcodes::initialize();

		require_once( $this->get_plugin_path() . '/includes/frontend/class-wc-memberships-frontend.php' );
		require_once( $this->get_plugin_path() . '/includes/frontend/class-wc-memberships-checkout.php' );
		require_once( $this->get_plugin_path() . '/includes/frontend/class-wc-memberships-restrictions.php' );

		$this->frontend     = new WC_Memberships_Frontend();
		$this->checkout     = new WC_Memberships_Checkout();
		$this->restrictions = new WC_Memberships_Restrictions();
	}


	/**
	 * Include required admin files
	 *
	 * @since 1.0.0
	 */
	private function admin_includes() {

		require_once( $this->get_plugin_path() . '/includes/admin/class-wc-memberships-admin.php' );
		$this->admin = new WC_Memberships_Admin();

		// message handler
		$this->admin->message_handler = $this->get_message_handler();
	}


	/**
	 * Include required AJAX files
	 *
	 * @since 1.0.0
	 */
	private function ajax_includes() {

		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-ajax.php' );
		$this->ajax = new WC_Memberships_AJAX();

		// checkout processes during Ajax request
		if ( empty( $this->checkout ) ) {
			require_once( $this->get_plugin_path() . '/includes/frontend/class-wc-memberships-checkout.php' );
			$this->checkout = new WC_Memberships_Checkout();
		}
	}


	/**
	 * Include required integration files
	 *
	 * @since 1.0.0
	 */
	private function integration_includes() {

		if ( $this->is_subscriptions_active() ) {
			require_once( $this->get_plugin_path() . '/includes/integrations/class-wc-memberships-integration-subscriptions.php' );
			$this->subscriptions = new WC_Memberships_Integration_Subscriptions();
		}

		if ( $this->is_user_switching_active() ) {
			require_once( $this->get_plugin_path() . '/includes/integrations/class-wc-memberships-integration-user-switching.php' );
		}

		if ( $this->is_groups_active() ) {
			require_once( $this->get_plugin_path() . '/includes/integrations/class-wc-memberships-integration-groups.php' );
		}

		if ( $this->is_bookings_active() ) {
			require_once( $this->get_plugin_path() . '/includes/integrations/class-wc-memberships-integration-bookings.php' );
		}

		if ( $this->is_product_addons_active() ) {
			require_once( $this->get_plugin_path() . '/includes/integrations/class-wc-memberships-integration-product-addons.php' );
			new WC_Memberships_Integration_Product_Addons();
		}
	}


	/**
	 * Initialize post types
	 *
	 * @since 1.0.0
	 */
	public function init() {
		WC_Memberships_Post_Types::initialize();
	}


	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-memberships', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/**
	 * Locates the WooCommerce template files from our templates directory
	 *
	 * @since 1.0.0
	 * @param string $template Already found template
	 * @param string $template_name Searchable template name
	 * @param string $template_path Template path
	 * @return string Search result for the template
	 */
	public function locate_template( $template, $template_name, $template_path ) {

		// Only keep looking if no custom theme template was found or if
		// a default WooCommerce template was found.
		if ( ! $template || SV_WC_Helper::str_starts_with( $template, WC()->plugin_path() ) ) {

			// Set the path to our templates directory
			$plugin_path = $this->get_plugin_path() . '/templates/';

			// If a template is found, make it so
			if ( is_readable( $plugin_path . $template_name ) ) {
				$template = $plugin_path . $template_name;
			}
		}

		return $template;
	}


	/**
	 * Load members area templates
	 *
	 * @since 1.4.0
	 * @param string $section
	 * @param array $args {
	 *      WC_Memberships_User_Membership $user_membership User Membership object
	 *      int $user_id Member id
	 *      int $paged Optional, pagination
	 * }
	 */
	public function get_members_area_template( $section, $args ) {

		if ( empty( $args['user_membership'] ) && empty( $args['user_id'] ) && ( ! $args['user_membership'] instanceof WC_Memberships_User_Membership ) ) {
			return;
		}

		// Pagination
		$paged = isset( $args['paged'] ) ? intval( $args['paged'] ) : 1;

		if ( 'my-membership-content' == $section ) {

			wc_get_template( 'myaccount/my-membership-content.php', array(
				'customer_membership' => $args['user_membership'],
				'restricted_content'  => $args['user_membership']->get_plan()->get_restricted_content( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-products' == $section ) {

			wc_get_template( 'myaccount/my-membership-products.php', array(
				'customer_membership' => $args['user_membership'],
				'restricted_products' => $args['user_membership']->get_plan()->get_restricted_products( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-discounts' == $section ) {

			wc_get_template( 'myaccount/my-membership-discounts.php', array(
				'customer_membership' => $args['user_membership'],
				'discounted_products' => $args['user_membership']->get_plan()->get_discounted_products( $paged ),
				'user_id'             => $args['user_id'],
			) );

		} elseif ( 'my-membership-notes' == $section ) {

			$dateTime = new DateTime();
			$dateTime->setTimeZone( new DateTimeZone( wc_timezone_string() ) );

			wc_get_template( 'myaccount/my-membership-notes.php', array(
				'customer_membership' => $args['user_membership'],
				'customer_notes'      => $args['user_membership']->get_notes( 'customer', $paged ),
				'timezone'            => $dateTime->format( 'T' ),
				'user_id'             => $args['user_id'],
			) );

		} else {

			// Allow custom sections if wc_membership_plan_members_area_sections is filtered
			$located = wc_locate_template( 'myaccount/' . $section . '.php' );
			if ( is_readable( $located ) ) {
				wc_get_template( 'myaccount/' . $section . '.php', $args );
			}

		}
	}


	/**
	 * Init custom endpoints
	 *
	 * @since 1.4.0
	 */
	public function init_endpoints() {

		// TODO the day WooCommerce simplifies adding custom endpoints to myaccount page endpoint, this could change
		add_action( 'init', array( $this, 'add_endpoints' ), 1 );
	}


	/**
	 * Add custom endpoints
	 *
	 * @since 1.4.0
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

		if ( ! $page instanceof WP_Post ) {
			return;
		}

		$page_slug = $page->post_name;
		$endpoint  = get_option( 'woocommerce_myaccount_members_area_endpoint', 'members-area' );

		// e.g. domain.tld/my-account/members-area/123/my-membership-discounts/
		add_rewrite_rule(
			"{$page_slug}/{$endpoint}/([0-9]{1,})/([^/]*)/?$",
			'index.php?page_id=' . $page_id . '&members_area=$matches[1]&members_area_section=$matches[2]&members_area_section_page=1',
			'top'
		);

		// paged, e.g. domain.tld/my-account/members-area/123/my-membership-discounts/2
		add_rewrite_rule(
			"{$page_slug}/{$endpoint}/([0-9]{1,})/([^/]*)/([0-9]{1,})/?$",
			'index.php?page_id=' . $page_id . '&members_area=$matches[1]&members_area_section=$matches[2]&members_area_section_page=$matches[3]',
			'top'
		);
	}


	/** Plugin functionality methods ***************************************/


	/**
	 * Grant customer access to membership when making a purchase
	 *
	 * This method is run also when an order is made manually in WC admin
	 *
	 * @since 1.0.0
	 * @param int $order_id WC_Order id
	 */
	public function grant_membership_access( $order_id ) {

		// Get the order and its items to check
		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();
		$user_id     = $order->get_user_id();

		// Skip if there is no user associated with this order or there are no items
		if ( ! $user_id || empty( $order_items ) ) {
			return;
		}

		// Get membership plans
		$membership_plans = $this->plans->get_membership_plans();

		// Bail out if there are no membership plans
		if ( empty( $membership_plans ) ) {
			return;
		}

		// Loop over all available membership plans
		foreach ( $membership_plans as $plan ) {

			// Skip if no products grant access to this plan
			if ( ! $plan->has_products() ) {
				continue;
			}

			$access_granting_product_ids = $this->get_access_granting_purchased_product_ids( $plan, $order, $order_items );

			foreach( $access_granting_product_ids as $product_id ) {

				// Sanity check: make sure the selected product ID in fact does grant access
				if ( ! $plan->has_product( $product_id ) ) {
					continue;
				}

				/**
				 * Grant Access from New Purchase Filter
				 *
				 * Allows actors to override if a new order should grant access
				 * to a membership plan or not
				 *
				 * @since 1.3.5
				 *
				 * @param bool $grant_access true by default
				 * @param array $args {
				 *      @type int|string $user_id user ID for order
				 *      @type int|string $product_id product ID that grants access
				 *      @type int|string $order_id order ID
				 * }
				 */
				$grant_access = apply_filters( 'wc_memberships_grant_access_from_new_purchase', true, array(
					'user_id'    => $user_id,
					'product_id' => $product_id,
					'order_id'   => $order_id
				) );


				if ( $grant_access ) {

					// delegate granting access to the membership plan instance
					$plan->grant_access_from_purchase( $user_id, $product_id, $order_id );
				}
			}
		}
	}


	/**
	 * Check if purchasing products that grant access to a membership
	 * in the same order allow to extend the length of the membership
	 *
	 * @since 1.4.0
	 * @return bool
	 */
	public function allow_cumulative_granting_access_orders() {
		return 'yes' == get_option( 'wc_memberships_allow_cumulative_access_granting_orders' );
	}


	/**
	 * Get order products granting access to a membership plan
	 *
	 * @since 1.4.0
	 * @param WC_Memberships_Membership_Plan $plan Membership plan to check for access
	 * @param int|WC_Order|string $order WC_Order instance or id. Can be empty string if $order_items are provided
	 * @param array $order_items Array of order items, if empty will try to get those from $order
	 * @return array|null Array of products granting access, null if $order is not valid
	 */
	public function get_access_granting_purchased_product_ids( $plan, $order, $order_items = array() ) {

		if ( empty( $order_items ) ) {

			$order = is_int( $order ) ? wc_get_order( $order ) : $order;

			if ( ! $order instanceof WC_Order ) {
				return null;
			}

			$order_items = $order->get_items();
		}

		$access_granting_product_ids = array();

		// Loop over items to see if any of them grant access to any memberships
		foreach ( $order_items as $key => $item ) {

			// Product grants access to this membership
			if ( $plan->has_product( $item['product_id'] ) ) {
				$access_granting_product_ids[] = $item['product_id'];
			}

			// Variation access
			if ( isset( $item['variation_id'] ) && $item['variation_id'] && $plan->has_product( $item['variation_id'] ) ) {
				$access_granting_product_ids[] = $item['variation_id'];
			}
		}

		if ( ! empty( $access_granting_product_ids ) ) {

			$product_ids = $this->allow_cumulative_granting_access_orders()
				? $access_granting_product_ids
				: $access_granting_product_ids[0];

			/**
			 * Filter the product ID that grants access to the membership plan via purchase
			 *
			 * Multiple products from a single order can grant access to a membership plan
			 * Default behavior is to use the first product that grants access,
			 * unless overridden by option in settings and/or using this filter
			 *
			 * @since 1.0.0
			 * @param int|array $product_ids
			 * @param array $access_granting_product_ids Array of product IDs that can grant access to this plan
			 * @param WC_Memberships_Membership_Plan $plan Membership plan access will be granted to
			 */
			$access_granting_product_ids = (array) apply_filters( 'wc_memberships_access_granting_purchased_product_id', $product_ids, $access_granting_product_ids, $plan );
		}

		return $access_granting_product_ids;
	}


	/** Admin methods ******************************************************/


	/**
	 * Render a notice for the user to read the docs before adding add-ons
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::add_admin_notices()
	 */
	public function add_admin_notices() {

		// show any dependency notices
		parent::add_admin_notices();

		$screen = get_current_screen();

		// only render on plugins or settings screen
		if ( 'plugins' === $screen->id || $this->is_plugin_settings() ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				/* translators: the %s placeholders are meant for pairs of opening <a> and closing </a> link tags */
				sprintf( __( 'Thanks for installing Memberships! To get started, take a minute to %1$sread the documentation%2$s and then %3$ssetup a membership plan%4$s :)', 'woocommerce-memberships' ),
					'<a href="http://docs.woothemes.com/document/woocommerce-memberships/" target="_blank">',
					'</a>',
					'<a href="' . admin_url( 'edit.php?post_type=wc_membership_plan' ) . '">',
					'</a>' ),
				'get-started-notice',
				array( 'always_show_on_settings' => false, 'notice_class' => 'updated' )
			);
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Memberships Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wc_memberships()
	 * @return WC_Memberships
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {

			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Gets the plugin documentation URL
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/woocommerce-memberships/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'http://support.woothemes.com/';
	}


	/**
	 * Search an array of arrays by key-value
	 *
	 * If a match is found in the array more than once,
	 * only the first matching key is returned.
	 *
	 * @since 1.0.0
	 * @param array $array Array of arrays
	 * @param string $key The key to search for
	 * @param string $value The value to search for
	 * @return array|boolean Found results, or false if none found
	 */
	public function array_search_key_value( $array, $key, $value ) {

		if ( ! is_array( $array ) ) {
			return null;
		}

		if ( empty( $array ) ) {
			return false;
		}

		$found_key = false;

		foreach ( $array as $element_key => $element ) {

			if ( isset( $element[ $key ] ) && $value == $element[ $key ] ) {

				$found_key = $element_key;
				break;
			}
		}

		return $found_key;
	}


	/**
	 * Workaround the last day of month quirk in PHP's strtotime function.
	 *
	 * Adding +1 month to the last day of the month can yield unexpected results with strtotime()
	 * For example,
	 * - 30 Jan 2013 + 1 month = 3rd March 2013
	 * - 28 Feb 2013 + 1 month = 28th March 2013
	 *
	 * What humans usually want is for the charge to continue on the last day of the month.
	 *
	 * Copied from WooCommerce Subscriptions
	 *
	 * @since 1.0.0
	 * @param string $from_timestamp Original timestamp to add months to
	 * @param int $months_to_add Number of months to add to the timestamp
	 * @return int corrected timestamp
	 */
	public function add_months( $from_timestamp, $months_to_add ) {

		$first_day_of_month = date( 'Y-m', $from_timestamp ) . '-1';
		$days_in_next_month = date( 't', strtotime( "+ {$months_to_add} month", strtotime( $first_day_of_month ) ) );

		// It's the last day of the month OR number of days in next month is less than the the day of this month (i.e. current date is 30th January, next date can't be 30th February)
		if ( date( 'd m Y', $from_timestamp ) === date( 't m Y', $from_timestamp ) || date( 'd', $from_timestamp ) > $days_in_next_month ) {

			for ( $i = 1; $i <= $months_to_add; $i++ ) {

				$next_month = strtotime( '+ 3 days', $from_timestamp ); // Add 3 days to make sure we get to the next month, even when it's the 29th day of a month with 31 days
				$next_timestamp = $from_timestamp = strtotime( date( 'Y-m-t H:i:s', $next_month ) ); // NB the "t" to get last day of next month
			}
		}

		// It's safe to just add a month
		else {
			$next_timestamp = strtotime( "+ {$months_to_add} month", $from_timestamp );
		}

		return $next_timestamp;
	}


	/**
	 * Adjust dates in UTC format
	 *
	 * Converts a UTC date to the corresponding date in another timezone
	 *
	 * @since 1.3.8
	 * @param int|string $date Date in string or timestamp format
	 * @param string $format Format to use in output
	 * @param string $timezone Timezone to convert from
	 * @return int|string
	 */
	public function adjust_date_by_timezone( $date, $format = 'mysql', $timezone = 'UTC' ) {

		if ( is_int( $date ) ) {
			$src_date = date( 'Y-m-d H:i:s', $date );
		} else {
			$src_date = $date;
		}

		if ( 'mysql' == $format ) {
			$format = 'Y-m-d H:i:s';
		}

		if ( 'UTC' == $timezone ) {
			$from_timezone = 'UTC';
			$to_timezone   = wc_timezone_string();
		} else {
			$from_timezone = $timezone;
			$to_timezone   = 'UTC';
		}

		$from_date = new DateTime( $src_date, new DateTimeZone( $from_timezone ) );
		$to_date   = new DateTimeZone( $to_timezone );
		$offset    = $to_date->getOffset( $from_date );

		// getTimestamp method not used here for PHP 5.2 compatibility
		$timestamp = intval( $from_date->format( 'U' ) );

		return 'timestamp' == $format ? $timestamp + $offset : date( $format, $timestamp + $offset );
	}


	/**
	 * Creates a human readable list of an array
	 *
	 * @since 1.0.0
	 * @param string[] $items array to list items of
	 * @param string $conjunction optional. The word to join together the penultimate and last item. Defaults to 'or'
	 * @return string 'item1, item2, item3 or item4'
	 */
	public function list_items( $items, $conjunction = null ) {

		if ( ! $conjunction ) {
			$conjunction = __( 'or', 'woocommerce-memberships' );
		}

		array_splice( $items, -2, 2, implode( ' ' . $conjunction . ' ', array_slice( $items, -2, 2 ) ) );
		return implode( ', ', $items );
	}


	/**
	 * Return a list of edit post links for the provided posts
	 *
	 * @since 1.1.0
	 * @param array $posts Array of post objects
	 * @return string
	 */
	public function admin_list_post_links( $posts ) {

		if ( empty( $posts ) ) {
			return '';
		}

		$items = array();

		foreach ( $posts as $post ) {
			$items[] = '<a href="' . get_edit_post_link( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a>';
		}

		return $this->list_items( $items, __( 'and', 'woocommerce-memberships' ) );
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Memberships', 'woocommerce-memberships' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Returns true if on the memberships settings page
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {
		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] && isset( $_GET['tab'] ) && 'memberships' == $_GET['tab'];
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return admin_url( 'admin.php?page=wc-settings&tab=memberships' );
	}


	/**
	 * Get the Subscriptions Integration instance
	 *
	 * @since 1.4.0
	 * @return null|WC_Memberships_Integration_Subscriptions
	 */
	public function get_subscriptions_integration() {
		return $this->subscriptions instanceof WC_Memberships_Integration_Subscriptions ? $this->subscriptions : null;
	}


	/**
	 * Checks is WooCommerce Subscriptions is active
	 *
	 * @since 1.0.0
	 * @return bool true if the WooCommerce Subscriptions plugin is active, false if not active
	 */
	public function is_subscriptions_active() {

		if ( is_bool( $this->subscriptions_active ) ) {
			return $this->subscriptions_active;
		}

		return $this->subscriptions_active = $this->is_plugin_active( 'woocommerce-subscriptions.php' );
	}


	/**
	 * Checks is User Switching is active
	 *
	 * @since 1.0.0
	 * @return bool true if the User Switching plugin is active, false if not active
	 */
	public function is_user_switching_active() {

		if ( is_bool( $this->user_switching_active ) ) {
			return $this->user_switching_active;
		}

		return $this->user_switching_active = $this->is_plugin_active( 'user-switching.php' );
	}


	/**
	 * Checks is Groups is active
	 *
	 * @since 1.0.0
	 * @return bool true if the Groups plugin is active, false if not active
	 */
	public function is_groups_active() {

		if ( is_bool( $this->groups_active ) ) {
			return $this->groups_active;
		}

		return $this->groups_active = $this->is_plugin_active( 'groups.php' );
	}


	/**
	 * Checks is Bookings is active
	 *
	 * @since 1.3.0
	 * @return bool true if the WooCommerce Bookings plugin is active, false if not active
	 */
	public function is_bookings_active() {

		if ( is_bool( $this->bookings_active ) ) {
			return $this->bookings_active;
		}

		return $this->bookings_active = $this->is_plugin_active( 'woocommmerce-bookings.php' );
	}


	/**
	 * Checks if Product Add-ons is active.
	 *
	 * @since 1.3.4
	 * @return bool $product_addons_active Whether Product Add-ons is active.
	 */
	public function is_product_addons_active() {

		if ( is_bool( $this->product_addons_active ) ) {
			return $this->product_addons_active;
		}

		return $this->product_addons_active = $this->is_plugin_active( 'woocommerce-product-addons.php' );
	}


	/**
	 * Encode a variable into JSON via wp_json_encode() if available, fall back
 	 * to json_encode otherwise.
	 *
	 * json_encode() may fail and return `null` in some environments (esp. with
	 * character encoding issues)
	 *
	 * @since 1.2.0
	 * @param mixed $data    Variable (usually an array or object) to encode as JSON.
	 * @param int   $options Optional. Options to be passed to json_encode(). Default 0.
	 * @param int   $depth   Optional. Maximum depth to walk through $data. Must be greater than 0. Default 512.
	 * @return bool|string   The JSON encoded string, or false if it cannot be encoded.
	 */
	public function wp_json_encode( $data, $options = 0, $depth = 512 ) {

		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $options, $depth ) : json_encode( $data, $options, $depth );
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Install default settings & pages
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		// install default "content restricted" page
		$title   = _x( 'Content restricted', 'Page title', 'woocommerce-memberships' );
		$slug    = _x( 'content-restricted', 'Page slug', 'woocommerce-memberships' );
		$content = '[wcm_content_restricted]';

		wc_create_page( esc_sql( $slug ), 'wc_memberships_redirect_page_id', $title, $content );

		// include settings so we can install defaults
		include_once( WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php' );
		$settings = require_once( $this->get_plugin_path() . '/includes/admin/class-wc-memberships-settings.php' );

		// install default settings for each section
		foreach ( $settings->get_sections() as $section => $label ) {

			foreach ( $settings->get_settings( $section ) as $setting ) {

				if ( isset( $setting['default'] ) ) {

					update_option( $setting['id'], $setting['default'] );
				}
			}
		}

	}


	/**
	 * Upgrade
	 *
	 * @since 1.1.0
	 * @see SV_WC_Plugin::install()
	 * @param string $installed_version
	 */
	protected function upgrade( $installed_version ) {

		// upgrade to version 1.1.0
		if ( version_compare( $installed_version, '1.1.0', '<' ) ) {

			$all_rules = array();

			// Merge rules from different options into a single option
			foreach ( array( 'content_restriction', 'product_restriction', 'purchasing_discount' ) as $rule_type ) {
				$rules = get_option( "wc_memberships_{$rule_type}_rules" );

				if ( is_array( $rules ) && ! empty( $rules ) ) {

					foreach ( $rules as $rule ) {

						// Skip empty/corrupt rules
						if ( empty( $rule ) || isset( $rule[0] ) && ! $rule[0] ) {
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

		if ( version_compare( $installed_version, '1.4.0', '<' ) ) {
			// Product category custom restriction messages in settings options
			update_option( 'wc_memberships_product_category_viewing_restricted_message', __( 'This product category can only be viewed by members. To view this category, sign up by purchasing {products}.', 'woocommerce-memberships' ) );
			update_option( 'wc_memberships_product_category_viewing_restricted_message_no_products', __( 'Displays if viewing a product category is restricted to a membership that cannot be purchased.', 'woocommerce-memberships' ) );
		}

		flush_rewrite_rules();
	}


	/**
	 * Handle plugin activation
	 *
	 * @since 1.0.0
	 */
	public function maybe_activate() {

		$is_active = get_option( 'wc_memberships_is_active', false );

		if ( ! $is_active ) {

			update_option( 'wc_memberships_is_active', true );

			/**
			 * Run when Memberships is activated
			 *
			 * @since 1.0.0
			 */
			do_action( 'wc_memberships_activated' );

			flush_rewrite_rules();
		}
	}


	/**
	 * Handle plugin deactivation
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {

		delete_option( 'wc_memberships_is_active' );

		/**
		 * Run when Memberships is deactivated
		 *
		 * @since 1.0.0
		 */
		do_action( 'wc_memberships_deactivated' );

		flush_rewrite_rules();
	}


} // end WC_Memberships class


/**
 * Returns the One True Instance of Memberships
 *
 * @since 1.0.0
 * @return WC_Memberships
 */
function wc_memberships() {
	return WC_Memberships::instance();
}

// fire it up!
wc_memberships();

} // init_woocommerce_memberships()
