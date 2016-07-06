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
 * Admin Membership Plans class
 *
 * This class handles all the admin-related functionality
 * for membership plans, like the list screen, meta boxes, etc.
 *
 * Note: it's not necessary to check for the post type, or `$typenow`
 * in this class, as this is already handled in WC_Memberships_Admin->init()
 *
 * @since 1.0.0
 */
class WC_Memberships_Admin_Membership_Plans {


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// List Table screen hooks
		add_filter( 'manage_edit-wc_membership_plan_columns', array( $this, 'customize_columns' ) );

		add_filter( 'post_row_actions', array( $this, 'customize_row_actions' ), 10, 2 );

		add_action( 'manage_wc_membership_plan_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );

		add_filter( 'bulk_actions-edit-wc_membership_plan', '__return_empty_array' );

		add_filter( 'months_dropdown_results', '__return_empty_array' );

		// Add/Edit screen hooks
		add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );
		add_action( 'post_submitbox_start', array( $this, 'duplicate_button' ) );
		add_action( 'add_meta_boxes', array( $this, 'customize_meta_boxes' ) );

		// Custom admin actions
		add_action( 'admin_action_duplicate_plan', array( $this, 'duplicate_membership_plan' ) );
		add_action( 'admin_action_grant_access',   array( $this, 'grant_access_to_membership' ) );

	}


	/**
	 * Customize membership plan columns
	 *
	 * @since 1.0.0
	 * @param array $columns
	 * @return array
	 */
	public function customize_columns( $columns ) {

		unset( $columns['date'] );
		unset( $columns['cb'] );

		$columns['slug']    = __( 'Slug', 'woocommerce-memberships' );
		$columns['access']  = __( 'Access from', 'woocommerce-memberships' );
		$columns['members'] = __( 'Members', 'woocommerce-memberships' );

		return $columns;
	}


	/**
	 * Customize membership plan row actions
	 *
	 * TODO: add View Members actions
	 *
	 * @since 1.0.0
	 * @param array $actions
	 * @param WP_Post $post
	 * @return array
	 */
	public function customize_row_actions( $actions, WP_Post $post ) {

		// Remove quick edit action
		unset( $actions['inline hide-if-no-js'] );

		$plan = wc_memberships_get_membership_plan( $post );

		if ( $plan && $plan->has_active_memberships() && isset( $actions['trash'] ) ) {

			$tip = '';

			if ( 'trash' == $post->post_status ) {
				$tip = esc_attr__( 'This item cannot be restored because it has active members.', 'woocommerce-memberships' );
			} elseif ( EMPTY_TRASH_DAYS ) {
				$tip = esc_attr__( 'This item cannot be moved to trash because it has active members.', 'woocommerce-memberships' );
			}

			if ( 'trash' == $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$tip = esc_attr__( 'This item cannot be permanently deleted because it has active members.', 'woocommerce-memberships' );
			}

			$actions['trash'] = '<span title="' . $tip . '" style="cursor: help;">' . strip_tags( $actions['trash'] ) . '</span>';
		}

		// Add duplicate action
		$actions['duplicate'] = '<a href="' . wp_nonce_url( admin_url( 'edit.php?post_type=wc_membership_plan&action=duplicate_plan&amp;post=' . $post->ID ), 'wc-memberships-duplicate-plan_' . $post->ID ) . '" title="' . __( 'Make a duplicate from this membership plan', 'woocommerce-memberships' ) . '" rel="permalink">' .
		                        /* translators: Duplicate a Membership Plan */
		                        __( 'Duplicate', 'woocommerce-memberships' ) .
		                        '</a>';

		return $actions;
	}


	/**
	 * Output custom column content
	 *
	 * @since 1.0.0
	 * @param string $column
	 * @param int $post_id
	 */
	public function custom_column_content( $column, $post_id ) {
		global $post;

		$membership_plan = wc_memberships_get_membership_plan( $post );

		if ( $membership_plan ) {

			switch ( $column ) {

				case 'slug':
					echo $membership_plan->get_slug();
				break;

				case 'access':
					$product_ids = get_post_meta( $post_id, '_product_ids', true );

					if ( ! empty( $product_ids ) ) {

						echo '<ul class="access-from-list">';

						foreach ( $product_ids as $product_id ) {

							$product = wc_get_product( $product_id );

							if ( is_object( $product ) ) {

								$link = ( $product->is_type( 'variation' ) )
									? get_edit_post_link( $product->parent->id )
									: get_edit_post_link( $product->id );

								printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $link ), $product->get_formatted_name() );
							}
						}

						echo '</ul>';
					}

				break;

				case 'members':
					echo $membership_plan->get_memberships_count();
				break;

			}
		}
	}


	/**
	 * Membership plan submit box actions
	 *
	 * @since 1.0.0
	 */
	public function post_submitbox_misc_actions() {
		global $post;

		?>
		<div class="misc-pub-section misc-pub-grant-access">
			<span class="grant-access">
				<?php esc_html_e( 'Existing purchases:', 'woocommerce-memberships' ); ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'grant_access', get_edit_post_link( $post->ID ) ), 'wc-memberships-grant-access-plan_' . $post->ID ) ); ?>" class="button" id="grant-access"><?php esc_html_e( 'Grant Access', 'woocommerce-memberships' ); ?></a>
			</span>
		</div>

		<style type="text/css">
			#visibility { display:none }
		</style>
		<?php

		wc_enqueue_js("
			jQuery( '#grant-access' ).click( function( e ) {
				return confirm( '" . esc_html__( 'This action adds a membership for anyone who has purchased one of the products that grants access to this plan. If the customer already has this membership, the original membership status and dates are preserved.\r\n\r\nSubscriptions: Only active subscribers will gain a membership.', 'woocommerce-memberships' ) . "' );
			} );
		");
	}


	/**
	 * Add meta boxes to the membership plan edit page
	 *
	 * @since 1.0.0
	 */
	public function customize_meta_boxes() {

		// Remove the slug div
		remove_meta_box( 'slugdiv', 'wc_membership_plan', 'normal' );
	}


	/**
	 * Show the duplicate plan link in admin edit screen
	 *
	 * @since 1.0.0
	 */
	public function duplicate_button() {
		global $post;

		if ( ! is_object( $post ) ) {
			return;
		}

		if ( isset( $_GET['post'] ) ) {
			$url = wp_nonce_url( admin_url( "edit.php?post_type=wc_membership_plan&action=duplicate_plan&post=" . $post->ID ), 'wc-memberships-duplicate-plan_' . $post->ID );
			?>
			<div id="duplicate-action">
				<a class="submitduplicate duplication" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Make a copy', 'woocommerce-memberships' ); ?></a>
			</div>
			<?php
		}
	}


	/**
	 * Duplicate a membership plan
	 *
	 * @since 1.0.0
	 */
	public function duplicate_membership_plan() {

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		// Get the original post
		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		check_admin_referer( 'wc-memberships-duplicate-plan_' . $id );

		$post = $this->get_plan_to_duplicate( $id );

		// Copy the plan and insert it
		if ( ! empty( $post ) ) {

			$new_id = $this->duplicate_plan( $post );

			/**
			 * Fires after a membership plan has been duplicated
			 *
			 * If you have written a plugin which uses non-WP database tables to save
			 * information about a page you can hook this action to duplicate that data.
			 *
			 * @since 1.0.0
			 * @param int $new_id New plan ID
			 * @param object $post Original plan object
			 */
			do_action( 'wc_memberships_duplicate_membership_plan', $new_id, $post );

			wc_memberships()->admin->message_handler->add_message( __( 'Membership plan copied.', 'woocommerce-memberships' ) );

			// Redirect to the edit screen for the new draft page
			wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
			exit;

		} else {
			wp_die( __( 'Membership plan creation failed, could not find original product:', 'woocommerce-memberships' ) . ' ' . $id );
		}
	}


	/**
	 * Grant access to a membership plan
	 *
	 * @since 1.0.0
	 */
	public function grant_access_to_membership() {
		global $wpdb;

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		// Get the ID
		$plan_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		check_admin_referer( 'wc-memberships-grant-access-plan_' . $plan_id );

		$plan        = wc_memberships_get_membership_plan( $plan_id );
		$redirect_to = get_edit_post_link( $plan_id, 'redirect' );

		$product_ids = $plan->get_product_ids();
		$grant_count = 0;

		if ( ! empty( $product_ids ) ) {

			foreach ( $product_ids as $product_id ) {

				$product = wc_get_product( $product_id );

				$meta_key =  is_object( $product ) && $product->is_type( 'variation' ) ? '_variation_id' : '_product_id';

				$sql       = $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = %s AND meta_value = %d ) AND order_item_type = 'line_item'", $meta_key, $product_id );
				$order_ids = $wpdb->get_col( $sql );

				if ( empty( $order_ids ) ) {
					continue;
				}

				foreach ( $order_ids as $order_id ) {

					$order = wc_get_order( $order_id );

					if ( ! $order ) {
						continue;
					}

					/**
					 * Filter the array of valid order statuses that grant access
					 *
					 * Allows actors to include additional custom order statuses that
					 * should grant access when the admin uses the "grant previous purchases access"
					 * action
					 *
					 * @since 1.0.0
					 * @param array $valid_order_statuses_for_grant array of order statuses
					 * @param object $plan the associated membership plan object
					 */
					$valid_order_statuses_for_grant = apply_filters( 'wc_memberships_grant_access_from_existing_purchase_order_statuses', array( 'processing', 'completed' ), $plan );

					// skip if purchase doesn't have a valid status
					if ( ! $order->has_status( $valid_order_statuses_for_grant ) ) {
						continue;
					}

					$user_id = $order->get_user_id();

					// Skip if guest purchase or user is already a member
					if ( ! $user_id || wc_memberships_is_user_member( $user_id, $plan_id ) ) {
						continue;
					}

					/**
					 * Filter whether an existing purchase of the product should grant access
					 * to the membership plan or not.
					 *
					 * Allows plugins to override if a previously purchased product should
					 * retroactively grant access to a membership plan or not.
					 *
					 * @since 1.0.0
					 * @param array $args
					 */
					$grant_access = apply_filters( 'wc_memberships_grant_access_from_existing_purchase', true, array(
						'user_id'    => $user_id,
						'product_id' => $product_id,
						'order_id'   => $order_id
					) );

					if ( ! $grant_access ) {
						continue;
					}

					// Grant access
					$result = $plan->grant_access_from_purchase( $user_id, $product_id, $order_id );

					if ( $result ) {
						$grant_count++;
					}
				}
			}
		}

		// Add admin message
		if ( $grant_count ) {
			$message = sprintf( _n( '%d customer was granted access from existing purchases.', '%d customers were granted access from existing purchases', $grant_count, 'woocommerce-memberships' ), $grant_count );
		} else {
			$message = __( 'No customers were granted access from existing purchases.', 'woocommerce-memberships' );
		}

		wc_memberships()->admin->message_handler->add_message( $message );

		// Redirect back to the edit screen
		wp_safe_redirect( $redirect_to ); exit;
	}


	/**
	 * Get a membership plan from the database to duplicate
	 *
	 * @since 1.0.0
	 * @param mixed $id
	 * @return WP_Post|bool
	 */
	private function get_plan_to_duplicate( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id ) {
			return false;
		}

		$post = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID=%d", $id ) );

		if ( isset( $post->post_type ) && $post->post_type == "revision" ) {
			$id   = $post->post_parent;
			$post = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID=%d", $id ) );
		}

		return $post[0];
	}


	/**
	 * Create a duplicate membership plan
	 *
	 * @since 1.0.0
	 * @param mixed $post
	 * @param int $parent (default: 0)
	 * @param string $post_status (default: 'publish')
	 * @return int
	 */
	public function duplicate_plan( $post, $parent = 0, $post_status = 'publish' ) {
		global $wpdb;

		$new_post_author   = wp_get_current_user();
		$new_post_date     = current_time( 'mysql' );
		$new_post_date_gmt = get_gmt_from_date( $new_post_date );

		if ( $parent > 0 ) {
			$post_parent = $parent;
			$suffix      = '';
		} else {
			$post_parent = $post->post_parent;
			$suffix      = ' ' . __( '(Copy)', 'woocommerce-memberships' );
		}

		// Insert the new template in the post table
		$wpdb->insert(
			$wpdb->posts,
			array(
				'post_author'               => $new_post_author->ID,
				'post_date'                 => $new_post_date,
				'post_date_gmt'             => $new_post_date_gmt,
				'post_content'              => $post->post_content,
				'post_content_filtered'     => $post->post_content_filtered,
				'post_title'                => $post->post_title . $suffix,
				'post_excerpt'              => $post->post_excerpt,
				'post_status'               => $post_status,
				'post_type'                 => $post->post_type,
				'comment_status'            => $post->comment_status,
				'ping_status'               => $post->ping_status,
				'post_password'             => $post->post_password,
				'to_ping'                   => $post->to_ping,
				'pinged'                    => $post->pinged,
				'post_modified'             => $new_post_date,
				'post_modified_gmt'         => $new_post_date_gmt,
				'post_parent'               => $post_parent,
				'menu_order'                => $post->menu_order,
				'post_mime_type'            => $post->post_mime_type
			)
		);

		$new_post_id = $wpdb->insert_id;

		// Copy the meta information
		$this->duplicate_post_meta( $post->ID, $new_post_id );

		// Copy rules
		$this->duplicate_plan_rules( $post->ID, $new_post_id );

		return $new_post_id;
	}


	/**
	 * Copy the meta information of a plan to another plan
	 *
	 * @since 1.0.0
	 * @param mixed $id
	 * @param mixed $new_id
	 */
	private function duplicate_post_meta( $id, $new_id ) {
		global $wpdb;

		$post_meta_infos = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=%d", absint( $id ) ) );

		if ( count( $post_meta_infos ) != 0 ) {

			$sql_query_sel = array();
			$sql_query     = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";

			foreach ( $post_meta_infos as $meta_info ) {

				$meta_key        = $meta_info->meta_key;
				$meta_value      = $meta_info->meta_value;
				$sql_query_sel[] = $wpdb->prepare( "SELECT %d, '$meta_key', '$meta_value'", $new_id );
			}

			$sql_query .= implode( " UNION ALL ", $sql_query_sel );

			$wpdb->query($sql_query);
		}
	}


	/**
	 * Copy the plan rules from one plan to another
	 *
	 * @since 1.0.0
	 * @param mixed $id
	 * @param mixed $new_id
	 */
	private function duplicate_plan_rules( $id, $new_id ) {

		$rules = get_option( 'wc_memberships_rules' );
		$new_rules = array();

		foreach ( $rules as $key => $rule ) {

			// Copy rules to new plan
			if ( $rule['membership_plan_id'] == $id ) {

				$new_rule = $rule;
				$new_rule['id'] = uniqid( 'rule_' );
				$new_rule['membership_plan_id'] = $new_id;

				$new_rules[] = $new_rule;
			}
		}

		update_option( 'wc_memberships_rules', array_merge( $rules, $new_rules ) );
	}


}
