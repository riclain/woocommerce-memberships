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

/**
 * Admin class
 *
 * @since 1.0.0
 */
class WC_Memberships_Admin {


	/** @var array tab URLs / titles */
	public $tabs;

	/** @var SV_WP_Admin_Message_Handler instance */
	public $message_handler;

	/** @var array Array of valid post types for content restriction rules */
	private $valid_post_types_for_content_restriction;

	/** @var array Array of valid taxonomies for rule types */
	private $valid_rule_type_taxonomies = array();

	/** @var stdClass meta box class instances */
	public $meta_boxes;


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->tabs = array(
			'members' => array(
				'title' => __( 'Members', 'woocommerce-memberships' ),
				'url'   => admin_url( 'edit.php?post_type=wc_user_membership' ),
			),
			'memberships' => array(
				'title' => __( 'Membership Plans', 'woocommerce-memberships' ),
				'url'   => admin_url( 'edit.php?post_type=wc_membership_plan' ),
			),
		);

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );

		// Init admin functionality
		add_action( 'current_screen', array( $this, 'init' ) );

		// enqueue styles & scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

		// Load WC styles / scripts
		add_filter( 'woocommerce_screen_ids', array( $this, 'load_wc_scripts' ) );

		// conditionally remove duplicate submenu link
		add_action( 'admin_menu', array( $this, 'remove_submenu_link' ) );

		// Set current tab for memberships admin pages
		add_filter( 'wc_memberships_admin_current_tab', array( $this, 'set_current_tab' ) );

		// Render memberships tabs for our CPTs
		add_action( 'all_admin_notices', array( $this, 'render_tabs_for_cpts' ), 5 );

		// Show user memberships on "edit order" screen
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_order_data' ) );

		// Show user memberships in Users list
		add_filter( 'manage_users_columns',       array( $this, 'add_user_columns' ), 11 );
		add_filter( 'manage_users_custom_column', array( $this, 'user_column_values' ), 11, 3 );

		// Show user memberships in profile page
		add_action( 'show_user_profile', array( $this, 'show_user_memberships' ) );
		add_action( 'edit_user_profile', array( $this, 'show_user_memberships' ) );

		// Display admin messages
		add_action( 'admin_notices', array( $this, 'show_admin_messages' ) );

		// Remove User Membership from Admin Bar -> New...
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 9999 );

		// Duplicate memberships settings for products
		add_action( 'woocommerce_duplicate_product', array( $this, 'duplicate_product_memberships_data' ), 10, 2 );
	}


	/**
	 * Add memberships settings page
	 *
	 * @since 1.0
	 * @param array $settings
	 * @return array
	 */
	public function add_settings_page( $settings ) {

		$settings[] = require_once( wc_memberships()->get_plugin_path() . '/includes/admin/class-wc-memberships-settings.php' );
		return $settings;
	}


	/**
	 * Initialize the admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$screen = get_current_screen();

		switch ( $screen->id ) {

			case 'wc_membership_plan':
			case 'edit-wc_membership_plan':

				require_once( wc_memberships()->get_plugin_path() .'/includes/admin/class-wc-memberships-admin-membership-plans.php' );
				$this->membership_plans = new WC_Memberships_Admin_Membership_Plans();

			break;

			case 'wc_user_membership':
			case 'edit-wc_user_membership':

				require_once( wc_memberships()->get_plugin_path() .'/includes/admin/class-wc-memberships-admin-user-memberships.php' );
				$this->membership_plans = new WC_Memberships_Admin_User_Memberships();

			break;

		}

		$this->load_meta_boxes();
	}


	/**
	 * Load meta boxes
	 *
	 * @since 1.0.0
	 */
	public function load_meta_boxes() {
		global $pagenow;

		// Bail out if not on a new post / edit post screen
		if ( 'post-new.php' != $pagenow && 'post.php' != $pagenow ) {
			return;
		}

		require_once( wc_memberships()->get_plugin_path() . '/includes/admin/meta-boxes/abstract-wc-memberships-meta-box.php' );

		$screen = get_current_screen();

		$this->meta_boxes = new stdClass();

		// Load restriction meta boxes on post screen only
		$meta_box_classes = array( 'WC_Memberships_Meta_Box_Post_Memberships_Data' );

		// Product-specific meta boxes
		if ( 'product' == $screen->id ) {
			$meta_box_classes[] = 'WC_Memberships_Meta_Box_Product_Memberships_Data';
		}

		// Load user membership meta boxes on user membership screen only
		if ( 'wc_membership_plan' == $screen->id ) {
			$meta_box_classes[] = 'WC_Memberships_Meta_Box_Membership_Plan_Data';
		}

		// Load user membership meta boxes on user membership screen only
		if ( 'wc_user_membership' == $screen->id ) {

			$meta_box_classes = array_merge( $meta_box_classes, array(
				'WC_Memberships_Meta_Box_User_Membership_Data',
				'WC_Memberships_Meta_Box_User_Membership_Notes',
				'WC_Memberships_Meta_Box_User_Membership_Member_Details',
				'WC_Memberships_Meta_Box_User_Membership_Recent_Activity',
			) );
		}

		// load and instantiate
		foreach ( $meta_box_classes as $class ) {

			$file_name = 'class-'. strtolower( str_replace( '_', '-', $class ) ) . '.php';
			$file_path = wc_memberships()->get_plugin_path() . '/includes/admin/meta-boxes/' . $file_name;

			if ( is_readable( $file_path ) ) {

				require_once( $file_path );

				if ( class_exists( $class ) ) {

					$instance_name = strtolower( str_replace( 'WC_Memberships_Meta_Box_', '', $class ) );
					$this->meta_boxes->$instance_name = new $class();
				}
			}
		}
	}


	/**
	 * Get the admin meta boxes
	 *
	 * @since 1.0.0
	 * @return object
	 */
	public function get_meta_boxes() {
		return $this->meta_boxes;
	}


	/**
	 * Get the admin meta box IDs
	 *
	 * @since 1.0.0
	 * @return array meta box IDs
	 */
	public function get_meta_box_ids() {

		$ids = array();

		foreach ( $this->get_meta_boxes() as $meta_box ) {
			$ids[] = $meta_box->get_id();
		}

		return $ids;
	}


	/**
	 * Enqueue admin scripts & styles
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current URL filename, ie edit.php, post.php, etc
	 */
	public function enqueue_scripts_and_styles( $hook_suffix ) {
		global $typenow, $pagenow;

		$load_scripts = false;

		// Post types valid for restriction (and thus, loading out JS/CSS)
		$restrictable_post_types   = array_keys( $this->get_valid_post_types_for_content_restriction() );
		$restrictable_post_types[] = 'product';

		// Only load scripts on appropriate screens
		if ( ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) && in_array( $typenow, $restrictable_post_types ) ) {
			$load_scripts = true;
		} elseif ( in_array( $typenow, array( 'wc_user_membership', 'wc_membership_plan' ) ) ) {
			$load_scripts = true;
		} elseif ( wc_memberships()->is_plugin_settings() ) {
			$load_scripts = true;
		}

		// Bail out if not a memberships screen (or a post type valid for restriction)
		if ( ! $load_scripts ) {
			return;
		}

		$screen = get_current_screen();

		if ( 'edit-wc_user_membership' == $screen->id || 'wc_user_membership' == $screen->id ) {
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );
		}

		// enqueue admin styles
		wp_enqueue_style( 'wc-memberships-admin', wc_memberships()->get_plugin_url() . '/assets/css/admin/wc-memberships-admin.min.css', WC_Memberships::VERSION );

		// enqueue admin scripts
		$dependencies = array( 'jquery' );

		if ( 'wc_user_membership' == $typenow && ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) ) {
			$dependencies[] = 'jquery-ui-datepicker';
		}

		wp_enqueue_script( 'wc-memberships-admin', wc_memberships()->get_plugin_url() . '/assets/js/admin/wc-memberships-admin.min.js', $dependencies, WC_Memberships::VERSION );

		wp_localize_script( 'wc-memberships-admin', 'wc_memberships_admin', array(

			// Add any config/state properties here, for example:
			// 'is_user_logged_in' => is_user_logged_in()

			'ajax_url'                                  => admin_url('admin-ajax.php'),
			'search_products_nonce'                     => wp_create_nonce( "search-products" ),
			'search_posts_nonce'                        => wp_create_nonce( "search-posts" ),
			'search_terms_nonce'                        => wp_create_nonce( "search-terms" ),
			'wc_plugin_url'                             => WC()->plugin_url(),
			'calendar_image'                            => WC()->plugin_url() . '/assets/images/calendar.png',
			'user_membership_url'                       => admin_url( 'edit.php?post_type=wc_user_membership' ),
			'new_user_membership_url'                   => admin_url( 'post-new.php?post_type=wc_user_membership' ),
			'get_membership_expiration_nonce'           => wp_create_nonce( 'get-membership-expiration' ),
			'search_customers_nonce'                    => wp_create_nonce( 'search-customers' ),
			'add_user_membership_note_nonce'            => wp_create_nonce( 'add-user-membership-note' ),
			'delete_user_membership_note_nonce'         => wp_create_nonce( 'delete-user-membership-note' ),
			'transfer_user_membership_nonce'            => wp_create_nonce( 'transfer-user-membership' ),
			'delete_user_membership_subscription_nonce' => wp_create_nonce( 'delete-user-membership-with-subscription' ),
			'restrictable_post_types'                   => $restrictable_post_types,

			'i18n' => array(

				// Add i18n strings here, for example:
				// 'log_in' => __( 'Log In', 'woocommerce-memberships' )

				'select_user'               => __( 'Select user', 'woocommerce-memberships' ),
				'add_member'                => __( 'Add Member', 'woocommerce-memberships' ),
				'delete_membership_confirm' => __( 'Are you sure that you want to permanently delete this membership?', 'woocommerce-memberships' ),
				'transfer_membership'       => __( 'Transfer Membership', 'woocommerce-memberships' ),
				'cancel'                    => __( 'Cancel', 'woocommerce-memberships' ),
				'search_for_user'           => __( 'Search for a user&hellip;', 'woocommerce-memberships' ),

			),

		) );

	}


	/**
	 * Add settings/export screen ID to the list of pages for WC to load its JS on
	 *
	 * @since 1.0.0
	 * @param array $screen_ids
	 * @return array
	 */
	public function load_wc_scripts( $screen_ids ) {
		return array_merge( $screen_ids, $this->get_screen_ids() );
	}


	/**
	 * Remove the duplicate submenu link for Memberships custom post type that
	 * is not being viewed. It's easier to add both submenu links via register_post_type()
	 * and conditionally remove them here than it is try to add them both
	 * correctly.
	 *
	 * @since 1.2.0
	 */
	public function remove_submenu_link() {
		global $pagenow, $typenow;

		$submenu_slug = 'edit.php?post_type=wc_membership_plan';

		// remove user membership submenu page when viewing or editing membership plans
		if ( ( 'edit.php' === $pagenow && 'wc_membership_plan' === $typenow )
		     || ( 'post.php' === $pagenow && isset( $_GET['post'] ) && 'wc_membership_plan' === get_post_type( $_GET['post'] ) ) ) {

			$submenu_slug = 'edit.php?post_type=wc_user_membership';
		}

		remove_submenu_page( 'woocommerce', $submenu_slug );
	}


	/**
	 * Set the current tab
	 *
	 * @since 1.0.0
	 * @param string $current_tab Current tab slug
	 * @return string
	 */
	public function set_current_tab( $current_tab ) {
		global $typenow;

		if ( 'wc_membership_plan' === $typenow ) {
			$current_tab = 'memberships';
		} elseif ( 'wc_user_membership' === $typenow ) {
			$current_tab = 'members';
		}

		return $current_tab;
	}


	/**
	 * Render Memberships tabs
	 *
	 * @since 1.0.0
	 */
	public function render_tabs() {

		/**
		 * Filter the current Memberships Admin tab
		 *
		 * @since 1.0.0
		 * @param string $current_tab
		 */
		$current_tab = apply_filters( 'wc_memberships_admin_current_tab', '' );

		?>
		<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
			<?php foreach ( $this->tabs as $tab_id => $tab ) : ?>
				<?php $class = ( $tab_id == $current_tab ) ? array( 'nav-tab', 'nav-tab-active' ) : array( 'nav-tab' ); ?>
				<?php printf( '<a href="%1$s" class="%2$s">%3$s</a>', esc_url( $tab['url'] ), implode( ' ', array_map( 'sanitize_html_class', $class ) ), esc_html( $tab['title'] ) ); ?>
			<?php endforeach; ?>
		</h2>
		<?php

	}


	/**
	 * Render tabs on our custom post types pages
	 *
	 * @since 1.0.0
	 */
	public function render_tabs_for_cpts() {
		global $typenow;

		?>
		<?php if ( is_string( $typenow ) &&  in_array( $typenow, array( 'wc_user_membership', 'wc_membership_plan' ) ) ) : ?>
			<div class="wrap woocommerce">
				<?php $this->render_tabs(); ?>
			</div>
		<?php endif; ?>
		<?php

	}


	/**
	 * Get valid post types for content restriction rules
	 *
	 * @since 1.0.0
	 * @return array Associative array of post type names and labels
	 */
	public function get_valid_post_types_for_content_restriction() {

		if ( ! isset( $this->valid_post_types_for_content_restriction ) ) {

			/**
			 * Exclude (blacklist) post types from content restriction content type options
			 *
			 * @since 1.0.0
			 * @param array $post_types List of post types to exclude
			 */
			$excluded_post_types = apply_filters( 'wc_memberships_content_restriction_excluded_post_types', array(
				'attachment',
				'wc_product_tab',
				'wooframework',
			) );

			$this->valid_post_types_for_content_restriction = array();

			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {

				// Skip products - they have their own restriction rules
				if ( in_array( $post_type->name, array( 'product', 'product_variation' ) ) ) {
					continue;
				}

				// Skip excluded CPTs
				if ( ! empty( $excluded_post_types ) && in_array( $post_type->name, $excluded_post_types ) ) {
					continue;
				}

				$this->valid_post_types_for_content_restriction[ $post_type->name ] = $post_type;
			}

		}

		return $this->valid_post_types_for_content_restriction;
	}


	/**
	 * Get valid taxonomies for a rule type
	 *
	 * @since 1.0.0
	 * @param string $rule_type Rule type. One of 'content_restriction', 'product_restriction' or 'purchasing_discount'
	 * @return array Associative array of taxonomy names and labels
	 */
	public function get_valid_taxonomies_for_rule_type( $rule_type ) {

		if ( ! isset( $this->valid_rule_type_taxonomies[ $rule_type ] ) ) {

			$excluded_taxonomies = array( 'product_shipping_class' );

			switch ( $rule_type ) {

				case 'content_restriction':
					$excluded_taxonomies = array_merge( $excluded_taxonomies, array( 'post_format', 'product_cat' ) );
				break;

				case 'product_restriction':
				case 'purchasing_discount':
					$excluded_taxonomies = array_merge( $excluded_taxonomies, array( 'product_tag' ) );
				break;

			}

			/**
			 * Exclude taxonomies from a rule type
			 *
			 * This filter allows excluding taxonomies from content & product restriction and
			 * purchasing discount rules.
			 *
			 * @since 1.0.0
			 * @param array $taxonomies List of taxonomies to exclude
			 */
			$excluded_taxonomies = apply_filters( "wc_memberships_{$rule_type}_excluded_taxonomies", $excluded_taxonomies );

			$this->valid_rule_type_taxonomies[ $rule_type ] = array();

			// $wp_taxonomy global used as some post types (product add-ons) attach
			// themselves to certain product-related taxonomies (like product_cat)
			// and get_taxonomies() provides no way to do an in_array() on the object
			// types. they either must match exactly or the taxonomy isn't returned
			foreach ( $GLOBALS['wp_taxonomies'] as $taxonomy ) {

				// skip non-public or excluded taxonomies
				if ( ! $taxonomy->public || ( ! empty( $excluded_taxonomies ) && in_array( $taxonomy->name, $excluded_taxonomies ) ) ) {
					continue;
				}

				if ( 'content_restriction' == $rule_type ) {

					// skip product-only taxonomies, they are listed in product restriction rules
					if ( count( $taxonomy->object_type ) == 1 && in_array( 'product', $taxonomy->object_type ) ) {
						continue;
					}
				}

				if ( in_array( $rule_type, array( 'product_restriction', 'purchasing_discount' ) ) ) {

					// skip taxonomies not registered for products
					if ( ! in_array( 'product', (array) $taxonomy->object_type ) ) {
						continue;
					}

					// skip product attributes
					if ( strpos( $taxonomy->name, 'pa_' ) === 0 ) {
						continue;
					}
				}

				$this->valid_rule_type_taxonomies[ $rule_type ][ $taxonomy->name ] = $taxonomy;
			}
		}

		return $this->valid_rule_type_taxonomies[ $rule_type ];
	}


	/**
	 * Get valid taxonomies for content restriction rules
	 *
	 * @since 1.0.0
	 * @return array Associative array of taxonomy names and labels
	 */
	public function get_valid_taxonomies_for_content_restriction() {
		return $this->get_valid_taxonomies_for_rule_type( 'content_restriction' );
	}


	/**
	 * Get valid taxonomies for product restriction rules
	 *
	 * @since 1.0.0
	 * @return array Associative array of taxonomy names and labels
	 */
	public function get_valid_taxonomies_for_product_restriction() {
		return $this->get_valid_taxonomies_for_rule_type( 'product_restriction' );
	}


	/**
	 * Get valid taxonomies for purchasing discount rules
	 *
	 * @since 1.0.0
	 * @return array Associative array of taxonomy names and labels
	 */
	public function get_valid_taxonomies_for_purchasing_discounts() {
		return $this->get_valid_taxonomies_for_rule_type( 'purchasing_discount' );
	}


	/**
	 * Get an array of memberships screen IDs
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_screen_ids() {

		return array(
			'wc_membership_plan',
			'wc_user_membership',
			'edit-wc_user_membership',
			'admin_page_wc-memberships-settings',
		);
	}


	/**
	 * Adds Memberships to "Edit Order" screen
	 *
	 * @since 1.3.8
	 * @param \WC_Order $order the WooCommerce order
	 */
	public function render_order_data( $order ) {

		if ( empty( $order->customer_user ) ) {
			return;
		}

		?>
		<p class="form-field form-field-wide wc-customer-memberships">
			<label for="customer_memberships"><?php esc_html_e( 'Active Memberships:', 'woocommerce-memberships' ); ?></label>
			<?php

				$user_id = absint( $order->customer_user );

				// Get all active memberships
				$memberships = wc_memberships()->user_memberships->get_user_memberships( $user_id );

				// count the memberships displayed
				$count = 0;

				if ( ! empty( $memberships ) ) {

					foreach ( $memberships as $membership ) {

						$plan = $membership->get_plan();

						if ( $plan && wc_memberships()->user_memberships->is_user_active_member( $user_id, $plan ) ) {

							edit_post_link( esc_html( $plan->name ), '', '<br />', $membership->id );
							$count++;
						}
					}
				}

	            if ( empty( $memberships ) || ! $count ) {
					esc_html_e( 'none', 'woocommerce-memberships' );
				}

			?>
		</p>
		<?php

	}


	/**
	 * Add the "Memberships" column to the User's admin table
	 *
	 * @since 1.3.8
	 * @param array $columns the array of Users columns
	 * @return array $columns the updated column layout
	 */
	public function add_user_columns( $columns ) {

		if ( current_user_can( 'manage_woocommerce' ) ) {

			// Move Memberships before Orders for aesthetics
			$last_column = array_slice( $columns, -1, 1, true );
			array_pop( $columns );
			$columns['wc_memberships_user_memberships'] = __( 'Active Memberships', 'woocommerce-memberships' );
			$columns += $last_column;
		}

		return $columns;
	}


	/**
	 * Display membership plan name(s) if a given user has a membership for the plan.
	 *
	 * @since 1.3.8
	 * @param string $output The string to output in the column specified with $column_name
	 * @param string $column_name The string key for the current column in an admin table
	 * @param int $user_id The ID of the user to which this row relates
	 * @return string $output Links to active user memberships
	 */
	public function user_column_values( $output, $column_name, $user_id ) {

		if ( 'wc_memberships_user_memberships' == $column_name ) {

			// Get all active memberships
			$memberships = wc_memberships()->user_memberships->get_user_memberships( $user_id );

			if ( empty( $memberships ) ) {
				return '-';
			}

			$membership_links = array();

			foreach ( $memberships as $membership ) {

				$plan = $membership->get_plan();

				if ( $plan && wc_memberships()->user_memberships->is_user_active_member( $user_id, $plan ) ) {
					$membership_links[] = '<a href="' . esc_url( get_edit_post_link( $membership->id ) ) . '">' . esc_html( $plan->name ) . '</a>';
				}
			}

			$output = implode( '<br />', $membership_links );
		}

		return $output;
	}


	/**
	 * Show user memberships on user profile page
	 *
	 * @since 1.0.0
	 * @param WP_User $user
	 */
	public function show_user_memberships( WP_User $user ) {

		$user_memberships          = wc_memberships()->user_memberships->get_user_memberships( $user->ID );
		$can_edit_user_memberships = current_user_can( 'manage_woocommerce' );

		echo '<h3>' . esc_html__( 'User memberships', 'woocommerce-memberships' ) . '</h3>';

		if ( ! empty( $user_memberships ) ) {

			$plan_links = array();

			foreach ( $user_memberships as $membership ) {

				if ( $membership->get_plan() ) {
					$plan_links[] = true === $can_edit_user_memberships ? '<a href="' . esc_url( get_edit_post_link( $membership->get_id() ) ) . '">' . wp_kses_post( $membership->get_plan()->get_name() ) . '</a>' : wp_kses_post( $membership->get_plan()->get_name() );
				}
			}

			if ( ! empty( $plan_links ) ) {

				printf( /* translators: Placeholders: %1$s - Membership Plan(s), %2$s Link to add more memberships manually */
					__( 'This user is a member of %1$s. %2$s', 'woocommerce-memberships' ),
					wc_memberships()->list_items( $plan_links, __( 'and', 'woocommerce-memberships' ) ),
					true === $can_edit_user_memberships ? '<a href="' . esc_url( admin_url( 'post-new.php?post_type=wc_user_membership&user=' . $user->ID ) ) . '"><strong>' . esc_html__( 'Add another membership.', 'woocommerce-memberships' ) . '</strong></a>' : ''
				);

			} else {

				_e( 'This user is already a member of every plan.', 'woocommerce-memberships' );

			}

		} else {

			printf( /* translators: Placeholder: %s - link to add a membership manually */
				__( 'This user has no memberships yet. %s', 'woocommerce-memberships' ),
				true === $can_edit_user_memberships ? '<a href="' . esc_url( admin_url( 'post-new.php?post_type=wc_user_membership&user=' . $user->ID ) ) . '"><strong>' . esc_html__( 'Add a membership manually.', 'woocommerce-memberships' ) . '</strong></a>' : ''
			);

		}

	}


	/**
	 * Display admin messages
	 *
	 * @since 1.0.0
	 */
	public function show_admin_messages() {
		$this->message_handler->show_messages();
	}


	/**
	 * Update rules for each provided rule type
	 *
	 * This method should be used by individual meta boxes that are updating rules
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 * @param array $rule_types Array of rule types to update
	 * @param string $target Optional. Indicates the context we are updating rules in. One of 'plan' or 'post'
	 */
	public function update_rules( $post_id, $rule_types, $target = 'plan' ) {

		$rules = get_option( 'wc_memberships_rules' );

		foreach ( $rule_types as $rule_type ) {

			$rule_type_post_key = '_' . $rule_type . '_rules';

			if ( ! isset( $_POST[ $rule_type_post_key ] ) ) {
				continue;
			}

			// Save rule type
			$posted_rules = $_POST[ $rule_type_post_key ];

			// Remove template rule
			if ( isset( $posted_rules['__INDEX__'] ) ) {
				unset( $posted_rules['__INDEX__'] );
			}

			// Stop processing rule type if no rules left
			if ( empty( $posted_rules ) ) {
				continue;
			}

			// Pre-process rules before saving
			foreach ( $posted_rules as $key => $rule ) {

				// If not updating rules for a plan, but rather a single post,
				// do not process or update inherited rules or rules that apply to multiple objects
				if ( 'post' === $target && isset( $rule['object_ids'] ) && is_array( $rule['object_ids'] ) && isset( $rule['object_ids'][0] ) && $rule['object_ids'][0] != $post_id ) {
					unset( $posted_rules[ $key ] );
					continue;
				}

				// Make sure each rule has an ID
				if ( ! isset( $rule['id'] ) || ! $rule['id'] ) {
					$rule['id'] = uniqid( 'rule_' );
				}

				// Make sure each rule has the rule type set
				$rule['rule_type'] = $rule_type;

				// If updating rules for a single plan, set the plan ID
				// and content type fields on the rule
				if ( 'plan' == $target ) {

					// Make sure each rule has correct membership plan ID
					$rule['membership_plan_id'] = $post_id;

					// Normalize content type: break content_type_key into parts
					$content_type_parts        = explode( '|', $rule['content_type_key'] );
					$rule['content_type']      = $content_type_parts[0];
					$rule['content_type_name'] = $content_type_parts[1];

					unset( $rule['content_type_key'] );

					// Normalize object IDs
					if ( isset( $rule['object_ids'] ) && $rule['object_ids'] && ! is_array( $rule['object_ids'] ) ) {
						$rule['object_ids'] = explode( ',', $rule['object_ids'] );
					}
				}

				// If updating rules for a single post, rather than a plan,
				// set the object ID and content type explicitly to match
				// the current post
				else {

					// Ensure that the correct object ID is set
					if ( ! isset( $rule['object_ids'] ) || empty( $rule['object_ids'] ) ) {
						$rule['object_ids'] = array( $post_id );
					}

					// Ensure correct content type & name is set
					$rule['content_type']      = 'post_type';
					$rule['content_type_name'] = get_post_type( $post_id );
				}

				// Content restriction & product restricion:
				if ( in_array( $rule_type, array( 'content_restriction', 'product_restriction' ) ) ) {

					// Make sure access_schedule_exclude_trial is set, even if it's a no
					if ( ! isset( $rule['access_schedule_exclude_trial'] ) ) {
						$rule['access_schedule_exclude_trial'] = 'no';
					}

					// If no access schedule is set, set it to immediate by default
					if ( ! isset( $rule['access_schedule'] ) ) {
						$rule['access_schedule'] = 'immediate';
					}

					// Normalize access schedule
					if ( 'specific' == $rule['access_schedule'] ) {

						if ( ! $rule['access_schedule_amount'] ) {
							$rule['access_schedule'] = 'immediate';
						} else {
							// Create textual (human-readable) representation of the access schedule
							$rule['access_schedule'] = sprintf( '%d %s', $rule['access_schedule_amount'], $rule['access_schedule_period'] );
						}
					}

					unset( $rule['access_schedule_amount'] );
					unset( $rule['access_schedule_period'] );
				}

				// Purchasing discounts:
				else if ( 'purchasing_discount' == $rule_type  ) {

					// Make sure active is set, even if it's a no
					$rule['active'] = isset( $rule['active'] ) && $rule['active'] ? 'yes' : 'no';
				}

				// Update rule properties
				$posted_rules[ $key ] = $rule;

			} // end pre-processing rules


			// Process posted rules
			foreach ( $posted_rules as $key => $posted ) {

				$existing_rule_key = wc_memberships()->array_search_key_value( $rules, 'id', $posted['id'] );

				// This is an existing rule
				if ( is_numeric( $existing_rule_key ) ) {

					$rule = new WC_Memberships_Membership_Plan_Rule( $rules[ $existing_rule_key ] );

					// Check capabilities
					if ( $rule->content_type_exists() && ! $rule->current_user_can_edit() ) {
						continue;
					}

					// Check if current context allows editing
					if ( ! $rule->current_context_allows_editing() ) {
						continue;
					}

					if ( isset( $posted['remove'] ) && $posted['remove'] ) {
						unset( $rules[ $existing_rule_key ] );
						continue;
					}

					// Remove unnecessary keys
					unset( $posted['remove'] );

					// Update existing rule
					$rules[ $existing_rule_key ] = $posted;

				}

				// This is a new rule, so simply append it to existing rules
				else {

					// Remove unnecessary keys
					unset( $posted['remove'] );

					// Check capabilities
					switch ( $posted['content_type'] ) {

						case 'post_type':
							$post_type = get_post_type_object( $posted['content_type_name'] );

							// Skip if user has no capabilities to edit the associated post type
							if ( ! ( current_user_can( $post_type->cap->edit_posts ) && current_user_can( $post_type->cap->edit_others_posts ) ) ) {
								continue;
							}

						break;

						case 'taxonomy':
							$taxonomy = get_taxonomy( $posted['content_type_name'] );

							// Skip if user has no capabilities to edit the associated taxonomy
							if ( ! ( current_user_can( $taxonomy->cap->manage_terms ) && current_user_can( $taxonomy->cap->edit_terms ) ) ) {
								continue;
							}

						break;
					}

					$rules[] = $posted;
				}
			}

		}

		update_option( 'wc_memberships_rules', ! empty( $rules ) ? array_values( $rules ) : array() );
	}


	/**
	 * Update a custom message for a post
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 * @param array $message_types
	 */
	public function update_custom_message( $post_id, $message_types ) {

		foreach ( $message_types as $message_type ) {

			$message = isset( $_POST["_wc_memberships_{$message_type}_message"] )
				? wp_unslash( sanitize_post_field( 'post_content', $_POST["_wc_memberships_{$message_type}_message"], 0, 'db' ) )
				: null;

			$use_custom = ( isset( $_POST["_wc_memberships_use_custom_{$message_type}_message"] ) && 'yes' == $_POST["_wc_memberships_use_custom_{$message_type}_message"] ) ? 'yes' : 'no';

			update_post_meta( $post_id, "_wc_memberships_{$message_type}_message", $message );
			update_post_meta( $post_id, "_wc_memberships_use_custom_{$message_type}_message", $use_custom );
		}
	}


	/**
	 * Remove New User Membership menu option from Admin Bar
	 *
	 * @since 1.3.0
	 * @param WP_Admin_Bar $admin_bar WP_Admin_Bar instance, passed by reference
	 */
	public function admin_bar_menu( $admin_bar ) {
		$admin_bar->remove_menu( 'new-wc_user_membership' );
	}


	/**
	 * Duplicate memberships data for a product
	 *
	 * @since 1.3.0
	 * @param int $new_id
	 * @param object $post
	 */
	public function duplicate_product_memberships_data( $new_id, $post ) {


		// Get product restriction rules
		$product_restriction_rules = wc_memberships()->rules->get_rules( array(
			'rule_type'         => 'product_restriction',
			'object_id'         => $post->ID,
			'content_type'      => 'post_type',
			'content_type_name' => $post->post_type,
			'exclude_inherited' => true,
			'plan_status'       => 'any',
		) );

		// Get purchasing discount rules
		$purchasing_discount_rules = wc_memberships()->rules->get_rules( array(
			'rule_type'         => 'purchasing_discount',
			'object_id'         => $post->ID,
			'content_type'      => 'post_type',
			'content_type_name' => $post->post_type,
			'exclude_inherited' => true,
			'plan_status'       => 'any',
		) );

		$product_rules = array_merge( $product_restriction_rules, $purchasing_discount_rules );

		// Duplicate rules
		if ( ! empty( $product_rules ) ) {

			$all_rules = get_option( 'wc_memberships_rules' );

			foreach ( $product_rules as $rule ) {

				$new_rule               = $rule->get_raw_data();
				$new_rule['object_ids'] = array( $new_id );
				$all_rules[]            = $new_rule;
			}

			update_option( 'wc_memberships_rules', $all_rules );
		}


		// Duplicate custom messages
		foreach ( array( 'product_viewing_restricted', 'product_purchasing_restricted' ) as $message_type ) {

			$message    = get_post_meta( $post->ID, "_wc_memberships_{$message_type}_message", true );
			$use_custom = get_post_meta( $post->ID, "_wc_memberships_use_custom_{$message_type}_message", true );

			if ( $message ) {
				update_post_meta( $new_id, "_wc_memberships_{$message_type}_message", $message );
			}

			if ( $use_custom ) {
				update_post_meta( $new_id, "_wc_memberships_use_custom_{$message_type}_message", $use_custom );
			}
		}


		// Duplicate 'grants access to'
		foreach ( wc_memberships_get_membership_plans() as $plan ) {

			if ( $plan->has_product( $post->ID ) ) {

				$product_ids   = get_post_meta( $plan->get_id(), '_product_ids', true );
				$product_ids[] = $new_id;

				update_post_meta( $plan->get_id(), '_product_ids', $product_ids );
			}
		}


		// Duplicate other settings
		update_post_meta( $new_id, '_wc_memberships_force_public', get_post_meta( $post->ID, '_wc_memberships_force_public', true ) );

	}


}
