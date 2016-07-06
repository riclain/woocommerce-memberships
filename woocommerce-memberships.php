<?php
/**
 * Plugin Name: WooCommerce Memberships
 * Plugin URI: http://www.woothemes.com/products/woocommerce-memberships/
 * Description: Sell memberships that provide access to restricted content, products, discounts, and more!
 * Author: WooThemes / SkyVerge
 * Author URI: http://www.woothemes.com/
 * Version: 1.6.2
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

defined( 'ABSPATH' ) or exit;

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

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.4.0', __( 'WooCommerce Memberships', 'woocommerce-memberships' ), __FILE__, 'init_woocommerce_memberships', array(
	'minimum_wc_version'   => '2.4.13',
	'minimum_wp_version'   => '4.1',
	'backwards_compatible' => '4.4.0',
) );

function init_woocommerce_memberships() {


/**
 * WooCommerce Memberships Main Plugin Class
 *
 * @since 1.0.0
 */
class WC_Memberships extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '1.6.2';

	/** @var WC_Memberships single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'memberships';

	/** plugin text domain, DEPRECATED as of 1.5.0 */
	const TEXT_DOMAIN = 'woocommerce-memberships';

	/** @var \WC_Memberships_Admin instance */
	protected $admin;

	/** @var \WC_Memberships_AJAX instance */
	protected $ajax;

	/** @var \WC_Memberships_Capabilities instance */
	protected $capabilities;

	/** @var \WC_Memberships_Emails instance */
	protected $emails;

	/** @var \WC_Memberships_Frontend instance */
	protected $frontend;

	/** @var WC_Memberships_Integrations instance */
	protected $integrations;

	/** @var \WC_Memberships_Member_Discounts instance */
	protected $member_discounts;

	/** @var \WC_Memberships_Membership_Plans instance */
	protected $plans;

	/** @var \WC_Memberships_Query instance */
	protected $query;

	/** @var \WC_Memberships_Rules instance */
	protected $rules;

	/** @var \WC_Memberships_User_Memberships instance */
	protected $user_memberships;


	/**
	 * Initializes the plugin
	 *
	 * @since 1.0.0
	 * @return \WC_Memberships
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'dependencies' => array( 'mbstring' ),
			)
		);

		// include required files
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ) );

		// initialize
		add_action( 'init', array( $this, 'init' ) );

		// make sure template files are searched for in our plugin
		// TODO since this seems to affect front end templates only, why not move into front end class? {FN 2016-04-26}
		add_filter( 'woocommerce_locate_template',      array( $this, 'locate_template' ), 20, 3 );
		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 20, 3 );

		// TODO move these methods out of the main class {FN 2016-04-26}
		add_action( 'woocommerce_order_status_completed',  array( $this, 'grant_membership_access' ), 11 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'grant_membership_access' ), 11 );

		// lifecycle
		add_action( 'admin_init', array ( $this, 'maybe_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}


	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		// load post types
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-post-types.php' );

		// global helper functions
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-functions.php' );
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-membership-plan-functions.php' );
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-user-membership-functions.php' );

		// init general classes
		$this->query            = $this->load_class( '/includes/class-wc-memberships-query.php',            'WC_Memberships_Query' );
		$this->emails           = $this->load_class( '/includes/class-wc-memberships-emails.php',           'WC_Memberships_Emails' );
		$this->rules            = $this->load_class( '/includes/class-wc-memberships-rules.php',            'WC_Memberships_Rules' );
		$this->plans            = $this->load_class( '/includes/class-wc-memberships-membership-plans.php', 'WC_Memberships_Membership_Plans' );
		$this->user_memberships = $this->load_class( '/includes/class-wc-memberships-user-memberships.php', 'WC_Memberships_User_Memberships' );
		$this->capabilities     = $this->load_class( '/includes/class-wc-memberships-capabilities.php',     'WC_Memberships_Capabilities' );
		$this->member_discounts = $this->load_class( '/includes/class-wc-memberships-member-discounts.php', 'WC_Memberships_Member_Discounts' );

		// frontend includes
		if ( ! is_admin() ) {
			$this->frontend_includes();
		}

		// admin includes
		if ( is_admin() && ! is_ajax() ) {
			$this->admin_includes();
		}

		// AJAX includes
		if ( is_ajax() ) {
			$this->ajax_includes();
		}

		// load integrations
		$this->integrations = $this->load_class( '/includes/integrations/class-wc-memberships-integrations.php', 'WC_Memberships_Integrations' );
	}


	/**
	 * Include required admin classes
	 *
	 * @since 1.0.0
	 */
	private function admin_includes() {

		$this->admin = $this->load_class( '/includes/admin/class-wc-memberships-admin.php', 'WC_Memberships_Admin' );

		// message handler
		$this->admin->message_handler = $this->get_message_handler();
	}


	/**
	 * Include required AJAX classes
	 *
	 * @since 1.0.0
	 */
	private function ajax_includes() {
		$this->ajax = $this->load_class( '/includes/class-wc-memberships-ajax.php', 'WC_Memberships_AJAX' );
	}


	/**
	 * Include required frontend classes
	 *
	 * @since 1.0.0
	 */
	private function frontend_includes() {

		// helper functions
		require_once( $this->get_plugin_path() . '/includes/wc-memberships-template-functions.php' );

		// init shortcodes
		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-shortcodes.php' );
		WC_Memberships_Shortcodes::initialize();

		// load front end
		$this->frontend = $this->load_class( '/includes/frontend/class-wc-memberships-frontend.php', 'WC_Memberships_Frontend' );
	}


	/**
	 * Get the Admin instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Admin
	 */
	public function get_admin_instance() {
		return $this->admin;
	}


	/**
	 * Get the Ajax instance
	 * @since 1.6.0
	 * @return \WC_Memberships_AJAX
	 */
	public function get_ajax_instance() {
		return $this->ajax;
	}


	/**
	 * Get the Capabilities instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Capabilities
	 */
	public function get_capabilities_instance() {
		return $this->capabilities;
	}


	/**
	 * Get the Frontend instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Frontend
	 */
	public function get_frontend_instance() {
		return $this->frontend;
	}


	/**
	 * Get the Emails instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Emails
	 */
	public function get_emails_instance() {
		return $this->emails;
	}


	/**
	 * Get the Integrations instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Integrations
	 */
	public function get_integrations_instance() {
		return $this->integrations;
	}


	/**
	 * Get the Member Discounts instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Member_Discounts
	 */
	public function get_member_discounts_instance() {
		return $this->member_discounts;
	}


	/**
	 * Get the Membership Plans instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Membership_Plans
	 */
	public function get_plans_instance() {
		return $this->plans;
	}


	/**
	 * Get Memberships Query instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Query
	 */
	public function get_query_instance() {
		return $this->query;
	}


	/**
	 * Get the Rules instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_Rules
	 */
	public function get_rules_instance() {
		return $this->rules;
	}


	/**
	 * Get the User Memberships instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_User_Memberships
	 */
	public function get_user_memberships_instance() {
		return $this->user_memberships;
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
	 * @see \SV_WC_Plugin::load_translation()
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

		// only keep looking if no custom theme template was found
		// or if a default WooCommerce template was found
		if ( ! $template || SV_WC_Helper::str_starts_with( $template, WC()->plugin_path() ) ) {

			// set the path to our templates directory
			$plugin_path = $this->get_plugin_path() . '/templates/';

			// if a template is found, make it so
			if ( is_readable( $plugin_path . $template_name ) ) {
				$template = $plugin_path . $template_name;
			}
		}

		return $template;
	}


	/** Admin methods ******************************************************/


	/**
	 * Render a notice for the user to read the docs before adding add-ons
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::add_admin_notices()
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


	/**
	 * Return a list of edit post links for the provided posts
	 *
	 * @since 1.1.0
	 * @param \WP_Post[] $posts Array of post objects
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

		return wc_memberships_list_items( $items, __( 'and', 'woocommerce-memberships' ) );
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Memberships Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wc_memberships()
	 * @return \WC_Memberships
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
	 * @see \SV_WC_Plugin::get_documentation_url()
	 * @return string
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/woocommerce-memberships/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.2.0
	 * @see \SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'http://support.woothemes.com/';
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Memberships', 'woocommerce-memberships' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Returns true if on the memberships settings page
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {
		return isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'memberships' === $_GET['tab'];
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return admin_url( 'admin.php?page=wc-settings&tab=memberships' );
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Install default settings & pages
	 *
	 * @since 1.0.0
	 * @see \SV_WC_Plugin::install()
	 */
	protected function install() {

		// install default "content restricted" page
		$title   = _x( 'Content restricted', 'Page title', 'woocommerce-memberships' );
		$slug    = _x( 'content-restricted', 'Page slug', 'woocommerce-memberships' );
		$content = '[wcm_content_restricted]';

		wc_create_page( esc_sql( $slug ), 'wc_memberships_redirect_page_id', $title, $content );

		// include settings so we can install defaults
		include_once( WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php' );
		$settings = $this->load_class( '/includes/admin/class-wc-memberships-settings.php', 'WC_Settings_Memberships' );

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
	 * @see \SV_WC_Plugin::install()
	 * @param string $installed_version
	 */
	protected function upgrade( $installed_version ) {

		require_once( $this->get_plugin_path() . '/includes/class-wc-memberships-upgrade.php' );
		WC_Memberships_Upgrade::run_update_scripts( $installed_version );

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


	/** Plugin functionality methods ***************************************/


	/**
	 * Grant customer access to membership when making a purchase
	 *
	 * This method is run also when an order is made manually in WC admin
	 *
	 * TODO move this callback and actions in constructor out of the main class {FN 2016-04-26}
	 *
	 * @since 1.0.0
	 * @param int $order_id \WC_Order id
	 */
	public function grant_membership_access( $order_id ) {

		// get the order and its items to check
		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();
		$user_id     = $order->get_user_id();

		// skip if there is no user associated with this order or there are no items
		if ( ! $user_id || empty( $order_items ) ) {
			return;
		}

		// get membership plans
		$membership_plans = $this->plans->get_membership_plans();

		// bail out if there are no membership plans
		if ( empty( $membership_plans ) ) {
			return;
		}

		// loop over all available membership plans
		foreach ( $membership_plans as $plan ) {

			// skip if no products grant access to this plan
			if ( ! $plan->has_products() ) {
				continue;
			}

			$access_granting_product_ids = $this->get_access_granting_purchased_product_ids( $plan, $order, $order_items );

			foreach( $access_granting_product_ids as $product_id ) {

				// sanity check: make sure the selected product ID in fact does grant access
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
	 * Get order products granting access to a membership plan
	 *
	 * @since 1.4.0
	 * @param \WC_Memberships_Membership_Plan $plan Membership plan to check for access
	 * @param int|\WC_Order|string $order \WC_Order instance or id. Can be empty string if $order_items are provided
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

		// loop over items to see if any of them grant access to any memberships
		foreach ( $order_items as $key => $item ) {

			// product grants access to this membership
			if ( $plan->has_product( $item['product_id'] ) ) {
				$access_granting_product_ids[] = $item['product_id'];
			}

			// variation access
			if ( isset( $item['variation_id'] ) && $item['variation_id'] && $plan->has_product( $item['variation_id'] ) ) {
				$access_granting_product_ids[] = $item['variation_id'];
			}
		}

		if ( ! empty( $access_granting_product_ids ) ) {

			// by default we get the first product that grant access...
			$product_ids = $access_granting_product_ids[0];

			// ...unless option is set, which might trigger a Memberships access length extension
			if ( wc_memberships_cumulative_granting_access_orders_allowed() ) {
				$product_ids = $access_granting_product_ids;
			}

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
			 * @param \WC_Memberships_Membership_Plan $plan Membership plan access will be granted to
			 */
			$access_granting_product_ids = (array) apply_filters( 'wc_memberships_access_granting_purchased_product_id', $product_ids, $access_granting_product_ids, $plan );
		}

		return $access_granting_product_ids;
	}


	/** Deprecated methods ******************************************************/


	/**
	 * Backwards compatibility handler for deprecated properties
	 *
	 * TODO by version 2.0.0 many of these backward compatibility calls could be removed {FN 2016-04-26}
	 *
	 * @since 1.6.0
	 * @param string $property
	 * @return null|mixed
	 */
	public function __get( $property ) {

		$deprecated_since_1_6_0 = '1.6.0';

		switch ( $property ) {

			/** @deprecated since 1.6.0 */
			case 'admin':
				_deprecated_function( 'wc_memberships()->admin', $deprecated_since_1_6_0, 'wc_memberships()->get_admin_instance()' );
				return $this->get_admin_instance();

			/** @deprecated since 1.6.0 */
			case 'ajax':
				_deprecated_function( 'wc_memberships()->ajax', $deprecated_since_1_6_0, 'wc_memberships()->get_ajax_instance()' );
				return $this->get_ajax_instance();

			/** @deprecated since 1.6.0 */
			case 'capabilities':
				_deprecated_function( 'wc_memberships()->capabilities', $deprecated_since_1_6_0, 'wc_memberships()->get_capabilities_instance()' );
				return $this->get_capabilities_instance();

			/** @deprecated since 1.6.0 */
			case 'checkout':
				_deprecated_function( 'wc_memberships()->checkout', $deprecated_since_1_6_0, 'wc_memberships()->get_frontend_instance()->get_checkout_instance()' );
				return $this->get_frontend_instance()->get_checkout_instance();

			/** @deprecated since 1.6.0 */
			case 'emails':
				_deprecated_function( 'wc_memberships()->emails', $deprecated_since_1_6_0, 'wc_memberships()->get_emails_instance()' );
				return $this->get_emails_instance();
			/** @deprecated since 1.6.0 */
			case 'frontend':
				_deprecated_function( 'wc_memberships()->frontend', $deprecated_since_1_6_0, 'wc_memberships()->get_frontend_instance()' );
				return $this->get_frontend_instance();

			/** @deprecated since 1.6.0 */
			case 'integrations':
				_deprecated_function( 'wc_memberships()->integrations', $deprecated_since_1_6_0, 'wc_memberships()->get_integrations_instance()' );
				return $this->get_integrations_instance();

			/** @deprecated since 1.6.0 */
			case 'member_discounts':
				_deprecated_function( 'wc_memberships()->member_discounts', $deprecated_since_1_6_0, 'wc_memberships()->get_member_discounts_instance()' );
				return $this->get_member_discounts_instance();

			/** @deprecated since 1.6.0 */
			case 'plans':
				_deprecated_function( 'wc_memberships()->plans', $deprecated_since_1_6_0, 'wc_memberships()->get_plans_instance()' );
				return $this->get_plans_instance();

			/** @deprecated since 1.6.0 */
			case 'restrictions':
				_deprecated_function( 'wc_memberships()->restrictions', $deprecated_since_1_6_0, 'wc_memberships()->get_frontend_instance()->get_restrictions_instance()' );
				return $this->get_frontend_instance()->get_restrictions_instance();
			/** @deprecated since 1.6.0 */
			case 'rules':
				_deprecated_function( 'wc_memberships()->rules', $deprecated_since_1_6_0, 'wc_memberships()->get_rules_instance()' );
				return $this->get_rules_instance();

			/** @deprecated since 1.6.0 */
			case 'subscriptions':
				_deprecated_function( 'wc_memberships()->subscriptions', $deprecated_since_1_6_0, 'wc_memberships()->get_integrations()->get_subscriptions_instance()' );
				return $this->get_integrations_instance()->get_subscriptions_instance();

			/** @deprecated since 1.6.0 */
			case 'user_memberships':
				_deprecated_function( 'wc_memberships()->user_memberships', $deprecated_since_1_6_0, 'wc_memberships()->get_user_memberships_instance()' );
				return $this->get_user_memberships_instance();

			default :
				// you're probably doing it wrong
				trigger_error( 'Call to undefined property ' . __CLASS__ . '::' . $property, E_USER_ERROR );
				return null;

		}
	}


	/**
	 * Backwards compatibility handler for deprecated methods
	 *
	 * TODO by version 2.0.0 many of these backward compatibility calls could be removed {FN 2016-04-06}
	 *
	 * @since 1.6.0
	 * @param string $method Method called
	 * @param void|string|array|mixed $args Optional argument(s)
	 * @return null|void|mixed
	 */
	public function __call( $method, $args ) {

		$deprecated_since_1_6_0 = '1.6.0';

		switch ( $method ) {

			/** @deprecated since 1.6.0 */
			case 'add_endpoints' :
				_deprecated_function( 'wc_memberships()->add_endpoints()', $deprecated_since_1_6_0, 'wc_memberships()->get_member_area_instance()->add_endpoints()' );
				$this->get_query_instance()->add_endpoints();
				return null;

			/** @deprecated since 1.6.0 */
			case 'add_months' :
				_deprecated_function( 'wc_memberships()->add_months()', $deprecated_since_1_6_0, 'wc_memberships_add_months_to_timestamp()' );
				$from_timestamp = isset( $args[0] ) ? $args[0] : '';
				$months_to_add  = isset( $args[1] ) ? (int) $args[1] : 0;
				return wc_memberships_add_months_to_timestamp( $from_timestamp, $months_to_add );

			/** @deprecated since 1.6.0 */
			case 'adjust_date_by_timezone' :
				_deprecated_function( 'wc_memberships()->adjust_date_by_timezone()', $deprecated_since_1_6_0, 'wc_memberships_adjust_date_by_timezone()' );
				$date     = isset( $args[0] ) ? $args[0] : $args;
				$format   = isset( $args[1] ) ? $args[1] : 'mysql';
				$timezone = isset( $args[2] ) ? $args[2] : 'UTC';
				return wc_memberships_adjust_date_by_timezone( $date, $format, $timezone );

			/** @deprecated since 1.6.0 */
			case 'allow_cumulative_granting_access_orders' :
				_deprecated_function( 'wc_memberships()->allow_cumulative_granting_access_orders()', $deprecated_since_1_6_0, 'wc_memberships_cumulative_granting_access_orders_allowed' );
				return wc_memberships_cumulative_granting_access_orders_allowed();

			/** @deprecated since 1.6.0 */
			case 'array_search_key_value' :
				_deprecated_function( 'wc_memberships()->array_search_key_value()', $deprecated_since_1_6_0 );
				return false;

			/** @deprecated since 1.6.0 */
			case 'init_endpoints' :
				_deprecated_function( 'wc_memberships()->init_endpoints()', $deprecated_since_1_6_0, 'wc_memberships()->get_member_area_instance()' );
				// this method was removed altogether but it just did the following equivalent,
				// which now we do in WC_Memberships_Query::__construct(),
				add_action( 'init', array( $this->get_query_instance(), 'add_endpoints' ), 1 );
				return null;

			/** @deprecated since 1.6.0 */
			case 'get_members_area_template' :
				_deprecated_function( 'wc_memberships()->get_members_area_template()', $deprecated_since_1_6_0, 'wc_memberships()->get_frontend_instance()->get_member_area_instance()->get_template()' );
				$section     = isset( $args[0] ) ? $args[0] : 'my-membership-content';
				$method_args = isset( $args[1] ) ? $args[1] : array();
				$this->get_frontend_instance()->get_member_area_instance()->get_template( $section, $method_args );
				return null;

			/** @deprecated since 1.6.0 */
			case 'get_subscriptions_integration' :
				_deprecated_function( 'wc_memberships()->get_subscriptions_integration()', $deprecated_since_1_6_0, 'wc_memberships()->get_integrations_instance()->get_subscriptions_instance()' );
				return $this->get_integrations_instance()->get_subscriptions_instance();

			/** @deprecated since 1.6.0 */
			case 'list_items' :
				_deprecated_function( 'wc_memberships()->list_items()', $deprecated_since_1_6_0, 'wc_memberships_list_items()' );
				$items = isset( $args[0] ) ? $args[0] : $args;
				$glue  = isset( $args[1] ) ? $args[1] : '';
				return wc_memberships_list_items( $items, $glue );

			/** @deprecated since 1.6.0 */
			case 'wp_json_encode' :
				_deprecated_function( 'wc_memberships()->wp_json_encode()', $deprecated_since_1_6_0, 'wp_json_encode()' );
				$data    = isset( $args[0] ) ? $args[0] : $args;
				$options = isset( $args[1] ) ? $args[1] : 0;
				$depth   = isset( $args[2] ) ? $args[2] : 512;
				return wc_memberships_json_encode( $data, $options, $depth );

			default :

				// check if calling for is_<plugin>_active() method
				if ( 0 === strpos( $method, 'is_' ) && strpos( $method, 'active' ) ) {

					/** @deprecated since 1.6.0 */
					if ( 'is_product_addons_active' === $method ) {
						_deprecated_function( 'wc_memberships()->is_product_addons_active()', $deprecated_since_1_6_0, "wc_memberships()->is_plugin_active( 'woocommerce-product-addons.php' )" );
						// the whole Product Add Ons integration was deprecated
						return $this->is_plugin_active( 'woocommerce-product-addons.php' );
					/** @deprecated since 1.6.0 */
					} else {
						_deprecated_function( "wc_memberships()->{$method}", $deprecated_since_1_6_0, "wc_memberships()->get_integrations()->{$method}" );
						// for example: `is_subscriptions_active()`
						return $this->get_integrations_instance()->$method();
					}

				} else {

					// you're probably doing it wrong
					trigger_error( 'Call to undefined method ' . __CLASS__ . '::' . $method, E_USER_ERROR );
					return null;
				}

		}
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
