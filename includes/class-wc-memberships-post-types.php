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
 * Memberships Post Types class
 *
 * This class is responsible for registering the custom post types & taxonomy
 * required for Memberships.
 *
 * @since 1.0.0
 */
class WC_Memberships_Post_Types {


	/**
	 * Initialize and register the Memberships post types
	 *
	 * @since 1.0.0
	 */
	public static function initialize() {

		self::init_post_types();
		self::init_user_roles();
		self::init_post_statuses();

		add_filter( 'post_updated_messages',      array( __CLASS__, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( __CLASS__, 'bulk_updated_messages' ), 10, 2 );

		// maybe remove overzealous 3rd-party meta boxes
		add_action( 'add_meta_boxes', array( __CLASS__, 'maybe_remove_meta_boxes' ), 30 );
	}


	/**
	 * Init WooCommerce Memberships user roles
	 *
	 * @since 1.0.0
	 */
	private static function init_user_roles() {
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		// Allow shop managers and admins to manage membership plans and user memberships
		if ( is_object( $wp_roles ) ) {

			foreach ( array( 'membership_plan', 'user_membership' ) as $post_type ) {

				$args = new stdClass();
				$args->map_meta_cap = true;
				$args->capability_type = $post_type;
				$args->capabilities = array();

				foreach ( get_post_type_capabilities( $args ) as $builtin => $mapped ) {

					$wp_roles->add_cap( 'shop_manager', $mapped );
					$wp_roles->add_cap( 'administrator', $mapped );
				}
			}

			$wp_roles->add_cap( 'shop_manager',  'manage_woocommerce_membership_plans' );
			$wp_roles->add_cap( 'administrator', 'manage_woocommerce_membership_plans' );

			$wp_roles->add_cap( 'shop_manager',  'manage_woocommerce_user_memberships' );
			$wp_roles->add_cap( 'administrator', 'manage_woocommerce_user_memberships' );
		}
	}


	/**
	 * Init WooCommerce Memberships post types
	 *
	 * @since 1.0.0
	 */
	private static function init_post_types() {

		if ( current_user_can( 'manage_woocommerce' ) ) {
			$show_in_menu = 'woocommerce';
		} else {
			$show_in_menu = true;
		}

		register_post_type( 'wc_membership_plan',
			array(
				'labels' => array(
						'name'               => __( 'Membership Plans', 'woocommerce-memberships' ),
						'singular_name'      => __( 'Membership Plan', 'woocommerce-memberships' ),
						'menu_name'          => _x( 'Memberships', 'Admin menu name', 'woocommerce-memberships' ),
						'add_new'            => __( 'Add Membership Plan', 'woocommerce-memberships' ),
						'add_new_item'       => __( 'Add New Membership Plan', 'woocommerce-memberships' ),
						'edit'               => __( 'Edit', 'woocommerce-memberships' ),
						'edit_item'          => __( 'Edit Membership Plan', 'woocommerce-memberships' ),
						'new_item'           => __( 'New Membership Plan', 'woocommerce-memberships' ),
						'view'               => __( 'View Membership Plans', 'woocommerce-memberships' ),
						'view_item'          => __( 'View Membership Plan', 'woocommerce-memberships' ),
						'search_items'       => __( 'Search Membership Plans', 'woocommerce-memberships' ),
						'not_found'          => __( 'No Membership Plans found', 'woocommerce-memberships' ),
						'not_found_in_trash' => __( 'No Membership Plans found in trash', 'woocommerce-memberships' ),
					),
				'description'     => __( 'This is where you can add new Membership Plans.', 'woocommerce-memberships' ),
				'public'          => false,
				'show_ui'         => true,
				'capability_type' => 'membership_plan',
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_menu'        => $show_in_menu,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'show_in_nav_menus'   => false,
			)
		);

		register_post_type( 'wc_user_membership',
			array(
				'labels' => array(
						'name'               => __( 'Members', 'woocommerce-memberships' ),
						'singular_name'      => __( 'User Membership', 'woocommerce-memberships' ),
						'menu_name'          => _x( 'Memberships', 'Admin menu name', 'woocommerce-memberships' ),
						'add_new'            => __( 'Add Member', 'woocommerce-memberships' ),
						'add_new_item'       => __( 'Add New User Membership', 'woocommerce-memberships' ),
						'edit'               => __( 'Edit', 'woocommerce-memberships' ),
						'edit_item'          => __( 'Edit User Membership', 'woocommerce-memberships' ),
						'new_item'           => __( 'New User Membership', 'woocommerce-memberships' ),
						'view'               => __( 'View User Memberships', 'woocommerce-memberships' ),
						'view_item'          => __( 'View User Membership', 'woocommerce-memberships' ),
						'search_items'       => __( 'Search Members', 'woocommerce-memberships' ),
						'not_found'          => __( 'No User Memberships found', 'woocommerce-memberships' ),
						'not_found_in_trash' => __( 'No User Memberships found in trash', 'woocommerce-memberships' ),
					),
				'description'     => __( 'This is where you can add new User Memberships.', 'woocommerce-memberships' ),
				'public'          => false,
				'show_ui'         => true,
				'capability_type' => 'user_membership',
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_menu'        => $show_in_menu,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( null ),
				'show_in_nav_menus'   => false,
			)
		);

	}


	/**
	 * Init WooCommerce Memberships post statuses
	 *
	 * @since 1.0.0
	 */
	private static function init_post_statuses() {

		$statuses = wc_memberships_get_user_membership_statuses();

		foreach ( $statuses as $status => $labels ) {

			register_post_status( $status, array(
				'label'                     => $labels['label'],
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => $labels['label_count'],
			) );
		}
	}


	/**
	 * Customize updated messages for custom post types
	 *
	 * @since 1.0.0
	 * @param array $messages Original messages
	 * @return array $messages Modified messages
	 */
	public static function updated_messages( $messages ) {

		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		$messages['wc_membership_plan'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Membership Plan saved.', 'woocommerce-memberships' ),
			2  => __( 'Custom field updated.', 'woocommerce-memberships' ),
			3  => __( 'Custom field deleted.', 'woocommerce-memberships' ),
			4  => __( 'Membership Plan saved.', 'woocommerce-memberships' ),
			5  => '', // Unused for membership plans
			6  => __( 'Membership Plan saved.', 'woocommerce-memberships' ), // Original: Post published
			7  => __( 'Membership Plan saved.', 'woocommerce-memberships' ),
			8  => '', // Unused for membership plans
			9  => '', // Unused for membership plans
			10 => __( 'Membership Plan draft updated.', 'woocommerce-memberships' ), // Original: Post draft updated
		);

		$messages['wc_user_membership'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'User Membership saved.', 'woocommerce-memberships' ),
			2  => __( 'Custom field updated.', 'woocommerce-memberships' ),
			3  => __( 'Custom field deleted.', 'woocommerce-memberships' ),
			4  => __( 'User Membership saved.', 'woocommerce-memberships' ),
			5  => '', // Unused for User Memberships
			6  => __( 'User Membership saved.', 'woocommerce-memberships' ), // Original: Post published
			7  => __( 'User Membership saved.', 'woocommerce-memberships' ),
			8  => '', // Unused for User Memberships
			9  => '', // Unused for User Memberships
			10 => __( 'User Membership saved.', 'woocommerce-memberships' ), // Original: Post draft updated
		);

		return $messages;
	}


	/**
	 * Customize updated messages for custom post types
	 *
	 * @since 1.0.0
	 * @param array $messages Original messages
	 * @param array $bulk_counts
	 * @return array $messages Modified messages
	 */
	public static function bulk_updated_messages( $messages, $bulk_counts ) {

		$messages['wc_membership_plan'] = array(
			'updated'   => _n( '%s membership plan updated.', '%s membership plans updated.', $bulk_counts['updated'], 'woocommerce-memberships' ),
			'locked'    => _n( '%s membership plan not updated, somebody is editing it.', '%s membership plans not updated, somebody is editing them.', $bulk_counts['locked'], 'woocommerce-memberships' ),
			'deleted'   => _n( '%s membership plan permanently deleted.', '%s membership plans permanently deleted.', $bulk_counts['deleted'], 'woocommerce-memberships' ),
			'trashed'   => _n( '%s membership plan moved to the Trash.', '%s membership plans moved to the Trash.', $bulk_counts['trashed'], 'woocommerce-memberships' ),
			'untrashed' => _n( '%s membership plan restored from the Trash.', '%s membership plans restored from the Trash.', $bulk_counts['untrashed'], 'woocommerce-memberships' ),
		);

		$messages['wc_user_membership'] = array(
			'updated'   => _n( '%s user membership updated.', '%s user memberships updated.', $bulk_counts['updated'], 'woocommerce-memberships' ),
			'locked'    => _n( '%s user membership not updated, somebody is editing it.', '%s user memberships not updated, somebody is editing them.', $bulk_counts['locked'], 'woocommerce-memberships' ),
			'deleted'   => _n( '%s user membership permanently deleted.', '%s user memberships permanently deleted.', $bulk_counts['deleted'], 'woocommerce-memberships' ),
			'trashed'   => _n( '%s user membership moved to the Trash.', '%s user memberships moved to the Trash.', $bulk_counts['trashed'], 'woocommerce-memberships' ),
			'untrashed' => _n( '%s user membership restored from the Trash.', '%s user memberships restored from the Trash.', $bulk_counts['untrashed'], 'woocommerce-memberships' ),
		);

		return $messages;
	}


	/**
	 * Remove third party meta boxes from our CPT screens unless they're on the
	 * whitelist
	 *
	 * @since 1.0.0
	 * @param string $post_type
	 */
	public static function maybe_remove_meta_boxes( $post_type ) {

		if ( ! in_array( $post_type, array( 'wc_membership_plan', 'wc_user_membership' ) ) ) {
			return;
		}

		$allowed_meta_box_ids = apply_filters( 'wc_memberships_allowed_meta_box_ids', array_merge( array( 'submitdiv' ), wc_memberships()->admin->get_meta_box_ids() ) );

		$screen = get_current_screen();

		foreach ( $GLOBALS['wp_meta_boxes'][ $screen->id ] as $context => $meta_boxes_by_context ) {

			foreach ( $meta_boxes_by_context as $subcontext => $meta_boxes_by_subcontext ) {

				foreach ( $meta_boxes_by_subcontext as $meta_box_id => $meta_box ) {

					if ( ! in_array( $meta_box_id, $allowed_meta_box_ids ) ) {
						remove_meta_box( $meta_box_id, $post_type, $context );
					}
				}
			}
		}
	}


}
