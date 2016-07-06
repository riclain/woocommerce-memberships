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
class WC_Memberships_Admin_User_Memberships {


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// List Table screen hooks
		add_filter( 'manage_edit-wc_user_membership_columns', array( $this, 'customize_columns' ) );
		add_filter( 'manage_edit-wc_user_membership_sortable_columns', array( $this, 'customize_sortable_columns' ) );

		add_filter( 'post_row_actions', array( $this, 'customize_row_actions' ), 10, 2 );

		add_action( 'manage_wc_user_membership_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );

		add_filter( 'request', array( $this, 'request_query' ) );
		add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );

		add_filter( 'the_title', array( $this, 'user_membership_title' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'remove_post_states' ) );

		add_action( 'restrict_manage_posts', array( $this, 'restrict_user_memberships' ) );

		add_filter( 'bulk_actions-edit-wc_user_membership', array( $this, 'customize_bulk_actions' ) );
		add_filter( 'months_dropdown_results', '__return_empty_array' );

		add_action( 'bulk_edit_custom_box', array( $this, 'bulk_edit' ) );

		// Add/Edit screen hooks
		add_action( 'post_submitbox_misc_actions', array( $this, 'normalize_edit_screen' ) );

		// Custom admin actions
		add_action( 'admin_action_pause',  array( $this, 'pause_membership' ) );
		add_action( 'admin_action_resume', array( $this, 'resume_membership' ) );
		add_action( 'admin_action_cancel', array( $this, 'cancel_membership' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// User Membership Validation
		add_action( 'wp_insert_post_empty_content', array ( $this, 'validate_user_membership' ), 1, 2 );
		add_action( 'load-post-new.php', array( $this, 'maybe_prevent_adding_user_membership' ), 1 );

	}


	/**
	 * Customize user memberships columns
	 *
	 * @since 1.0.0
	 * @param array $columns
	 * @return array
	 */
	public function customize_columns( $columns ) {

		unset( $columns['title'] );
		unset( $columns['date'] );

		$columns['title']        = __( 'Name', 'woocommerce-memberships' ); // use title column as the member name column
		$columns['email']        = __( 'Email', 'woocommerce-memberships' );
		$columns['plan']         = __( 'Plan', 'woocommerce-memberships' );
		$columns['status']       = __( 'Status', 'woocommerce-memberships' );
		$columns['member_since'] = __( 'Member since', 'woocommerce-memberships' );
		$columns['expires']      = __( 'Expires', 'woocommerce-memberships' );

		return $columns;
	}


	/**
	 * Customize user memberships sortable columns
	 *
	 * @since 1.0.0
	 * @param array $columns
	 * @return array
	 */
	public function customize_sortable_columns( $columns ) {

		$columns['name']         = 'name';
		$columns['email']        = 'email';
		$columns['status']       = 'post_status';
		$columns['member_since'] = 'start_date';
		$columns['expires']      = 'expiry_date';

		return $columns;
	}


	/**
	 * Customize user memberships row actions
	 *
	 * @since 1.0.0
	 * @param array $actions
	 * @param WP_Post $post
	 * @return array
	 */
	public function customize_row_actions( $actions, WP_Post $post ) {

		// Remove quick edit action
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['trash'] );

		$post_link       = remove_query_arg( 'action', get_edit_post_link( $post->ID, '' ) );
		$user_membership = wc_memberships_get_user_membership( $post );

		if ( $user_membership->is_paused() ) {
			$resume_link = add_query_arg( 'action', 'resume', wp_nonce_url( $post_link, 'wc-memberships-resume-membership-' . $post->ID ) );
			$actions['resume'] = '<a href="' . esc_url( $resume_link ) . '">' . esc_html__( 'Resume', 'woocommerce-memberships' ) . '</a>';
		} else {
			$pause_link = add_query_arg( 'action', 'pause', wp_nonce_url( $post_link, 'wc-memberships-pause-membership-' . $post->ID ) );
			$actions['pause']  = '<a href="' . esc_url( $pause_link ) . '">'  . esc_html__( 'Pause', 'woocommerce-memberships' )  . '</a>';
		}

		if ( ! $user_membership->is_cancelled() ) {
			$cancel_link = add_query_arg( 'action', 'cancel', wp_nonce_url( $post_link, 'wc-memberships-cancel-membership-' . $post->ID ) );
			$actions['cancel'] = '<a href="' . esc_url( $cancel_link ) . '">' . esc_html__( 'Cancel', 'woocommerce-memberships' ) . '</a>';
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			$actions['delete'] = "<a class='submitdelete delete-membership' title='" . esc_attr__( 'Delete this membership permanently', 'woocommerce-memberships' ) . "' href='" . esc_url( get_delete_post_link( $post->ID, '', true ) ) . "'>" . esc_html__( 'Delete', 'woocommerce-memberships' ) . "</a>";
		}

		return $actions;
	}


	/**
	 * Customize user memberships bulk actions
	 *
	 * @since 1.0.0
	 * @param array $actions
	 * @return array
	 */
	public function customize_bulk_actions( $actions ) {

		unset( $actions['trash'] );
		return $actions;
	}


	/**
	 * Customize bulk edit form
	 *
	 * @since 1.0.0
	 * @param string $column
	 */
	public function bulk_edit( $column ) {

		if ( 'status' !== $column ) {
			return;
		}

		// Prepare options
		$status_options = array();
		foreach ( wc_memberships_get_user_membership_statuses() as $status => $labels ) {
			$status_options[ $status ] = $labels['label'];
		}

		/**
		 * Filter the status options available in user memberships bulk edit box
		 *
		 * @since 1.0.0
		 * @param array $options Associative array of option value => label pairs
		 */
		$status_options = apply_filters( 'wc_memberships_bulk_edit_user_memberships_status_options', $status_options );
		?>

		<fieldset class="inline-edit-col-right" id="wc-memberships-fields-bulk">
			<div class="inline-edit-col">
				<div class="inline-edit-group">
					<label class="inline-edit-status alignleft">
						<span class="title"><?php esc_html_e( 'Status', 'woocommerce-memberships' ); ?></span>
						<select name="_status">
							<option value="-1"><?php echo '&mdash; ' . esc_html__( 'No Change', 'woocommerce-memberships' ) . ' &mdash;'; ?></option>
							<?php
								if ( ! empty( $status_options ) ) {
									foreach ( $status_options as $status => $label ) {
										echo "\t<option value='" . esc_attr( $status ) . "'>" . esc_html( $label ) . "</option>" . PHP_EOL;
									}
								}
							?>
						</select>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}


	/**
	 * Output custom column content
	 *
	 * @since 1.0.0
	 * @param string $column
	 * @param int $post_id
	 */
	public function custom_column_content( $column, $post_id ) {

		$user_membership = wc_memberships_get_user_membership( $post_id );
		$user            = get_userdata( $user_membership->get_user_id() );
		$date_format     = wc_date_format();
		$time_format     = wc_time_format();

		switch ( $column ) {

			case 'name':
				echo $user->display_name;
			break;

			case 'email':
				echo $user->user_email;
			break;

			case 'plan':
				echo '<a href="' . esc_url( get_edit_post_link( $user_membership->get_plan_id() ) ) . '">' . $user_membership->get_plan()->get_name() . '</a>';
			break;

			case 'status':

				$statuses = wc_memberships_get_user_membership_statuses();
				echo esc_html( $statuses[ 'wcm-' . $user_membership->get_status() ]['label'] );

			break;

			case 'member_since':

				$since_time = $user_membership->get_local_start_date( 'timestamp' );
				echo esc_html( date_i18n( $date_format, $since_time ) );
				echo '&nbsp;';
				echo esc_html( date_i18n( $time_format, $since_time ) );

			break;

			case 'expires':

				if ( $end_date = $user_membership->get_local_end_date( 'timestamp' ) ) {
					echo esc_html( date_i18n( $date_format, $end_date ) );
				} else {
					esc_html_e( 'Never', 'woocommerce-memberships' );
				}

			break;
		}
	}


	/**
	 * Hide default publishing box, etc
	 *
	 * @since 1.0.0
	 */
	public function normalize_edit_screen() {
		?>
		<style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<?php
	}


	/**
	 * Filters and sorting handler
	 *
	 * @since 1.0.0
	 * @param array $vars
	 * @return array
	 */
	public function request_query( $vars ) {
		global $typenow;

		if ( 'wc_user_membership' === $typenow ) {

			// Status
			if ( ! isset( $vars['post_status'] ) ) {
				$vars['post_status'] = array_keys( wc_memberships_get_user_membership_statuses() );
			}

			// Filter by plan ID (post parent)
			if ( isset( $_GET['post_parent'] ) ) {
				$vars['post_parent'] = $_GET['post_parent'];
			}

			// Filter by expiry date
			if ( isset( $_GET['expires'] ) ) {

				$min_date = $max_date = null;

				switch ( $_GET['expires'] ) {

					case 'today':

						$min_date = date( 'Y-m-d H:i:s', strtotime( 'today midnight' ) );
						$max_date = date( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) - 1 );

					break;

					case 'this_week':

						$min_date = date( 'Y-m-d H:i:s', strtotime( 'this week midnight' ) );
						$max_date = date( 'Y-m-d H:i:s', strtotime( 'next week midnight' ) - 1 );

					break;

					case 'this_month':

						$min_date = date( 'Y-m-d H:i:s', strtotime( 'first day of midnight' ) );
						$max_date = date( 'Y-m-d H:i:s', strtotime( 'first day of +1 month midnight' ) - 1 );

					break;

				}

				if ( $min_date && $max_date ) {

					$vars['meta_query'] = isset( $vars['meta_query'] ) ? $vars['meta_query'] : array();
					$vars['meta_query'] = array_merge( $vars['meta_query'], array( array(
						'key'   => '_end_date',
						'value' => array( $min_date, $max_date ),
						'compare' => 'BETWEEN',
						'type' => 'DATETIME',
					) ) );
				}

			}

			// Sorting order
			if ( isset( $vars['orderby'] ) ) {

				switch ( $vars['orderby'] ) {

					// Order by plan (abusing title column)
					case 'title':
						$vars['orderby'] = 'post_parent';
					break;

					// Order by start date (member since)
					case 'start_date':

						$vars['meta_key'] = '_start_date';
						$vars['orderby']  = 'meta_value';

					break;

					// Order by end date (expires)
					case 'expiry_date':

						$vars['meta_key'] = '_end_date';
						$vars['orderby']  = 'meta_value';

					break;

				}
			}
		}

		return $vars;
	}


	/**
	 * Alter posts query clauses
	 *
	 * @since 1.0.0
	 * @param array $pieces
	 * @param WP_Query $wp_query
	 * @return string
	 */
	public function posts_clauses( $pieces, WP_Query $wp_query ) {

		global $wpdb;

		// Bail out if not the correct post type
		if ( 'wc_user_membership' != $wp_query->query['post_type'] ) {
			return $pieces;
		}

		// Whether to add a join clause for users table or not
		$join_users = false;

		// Search
		if ( isset( $wp_query->query['s'] ) ) {

			$join_users = true;
			$keyword = '%' . $wp_query->query['s'] . '%';

			// Do a LIKE search in user fields
			$where_title        = $wpdb->prepare( "($wpdb->posts.post_title LIKE %s)", $keyword );
			$where_user_login   = $wpdb->prepare( " OR ($wpdb->users.user_login LIKE %s)", $keyword );
			$where_user_email   = $wpdb->prepare( " OR ($wpdb->users.user_email LIKE %s)", $keyword );
			$where_display_name = $wpdb->prepare( " OR ($wpdb->users.display_name LIKE %s)", $keyword );

			$where = $where_title . $where_user_login . $where_user_email . $where_display_name;

			$pieces['where'] = str_replace( $where_title, $where, $pieces['where'] );
		}

		// Order by
		if ( isset( $wp_query->query['orderby'] ) ) {

			switch ( $wp_query->query['orderby'] ) {

				case 'email':

					$join_users = true;
					$pieces['orderby'] = " $wpdb->users.user_email " . strtoupper( $wp_query->query['order'] ) . " ";

				break;

				case 'name';

					$join_users = true;
					$pieces['orderby'] = " $wpdb->users.display_name " . strtoupper( $wp_query->query['order'] ) . " ";

				break;

			}
		}

		// Join users table, if needed
		if ( $join_users ) {
			$pieces['join'] .= " LEFT JOIN $wpdb->users ON $wpdb->posts.post_author = $wpdb->users.ID ";
		}

		return $pieces;
	}


	/**
	 * Use membership plan name as user membership title
	 *
	 * @since 1.0.0
	 * @param string $title Original title
	 * @param int $post_id Post ID
	 * @return string Modified title
	 */
	public function user_membership_title( $title, $post_id ) {
		global $pagenow;

		if ( 'wc_user_membership' == get_post_type( $post_id ) ) {
			$user_membership = wc_memberships_get_user_membership( $post_id );

			if ( $user_membership ) {
				$user = get_userdata( $user_membership->get_user_id() );
				$title = 'edit.php' === $pagenow || ! $user_membership->get_plan() ? $user->display_name : $user_membership->get_plan()->get_name();
			}
		}

		return $title;
	}


	/**
	 * Remove post states (such as "Password protected") from list table
	 *
	 * @since 1.0.0
	 * @param string $states
	 * @return string
	 */
	public function remove_post_states( $states ) {
		return "";
	}


	/**
	 * Render dropdowns for user membership filters
	 *
	 * @since 1.0.0
	 */
	public function restrict_user_memberships() {

		global $typenow;

		if ( 'wc_user_membership' != $typenow ) {
			return;
		}

		// membership plan options
		$membership_plans = wc_memberships_get_membership_plans();
		$selected_plan    = isset( $_GET['post_parent'] ) ? $_GET['post_parent'] : null;

		$statuses        = wc_memberships_get_user_membership_statuses();
		$selected_status = isset( $_GET['post_status'] ) ? $_GET['post_status'] : null;

		/**
		 * Filter the expiry terms dropdown menu
		 *
		 * @param array $terms Associative array of expiry term keys and labels.
		 * @since 1.0.0
		 */
		$expires = apply_filters( 'wc_memberships_expiry_terms_dropdown_options', array(
			'today'      => __( 'Today', 'woocommerce-memberships' ),
			'this_week'  => __( 'This week', 'woocommerce-memberships' ),
			'this_month' => __( 'This month', 'woocommerce-memberships' ),
		) );
		$selected_expiry_term = isset( $_GET['expires'] ) ? $_GET['expires'] : null;

		?>
		<select name="post_parent">
			<option value=""><?php _e( 'All plans', 'woocommerce-memberships' ); ?></option>
			<?php
				if ( ! empty( $membership_plans ) ) {
					foreach ( $membership_plans as $membership_plan ) {
						echo "\t<option value='" . esc_attr( $membership_plan->get_id() ) . "'" . selected( $membership_plan->get_id() , $selected_plan, false ) . '>' . esc_html( $membership_plan->get_name() ) . '</option>' . PHP_EOL;
					}
				}
			?>
		</select>

		<select name="post_status">
			<option value=""><?php _e( 'All statuses', 'woocommerce-memberships' ); ?></option>
			<?php
				if ( ! empty( $statuses ) ) {
					foreach ( $statuses as $status => $labels ) {
						echo "\t<option value='" . esc_attr( $status ) . "'" . selected( $status, $selected_status, false ) . '>' . esc_html( $labels['label'] ) . '</option>' . PHP_EOL;
					}
				}
			?>
		</select>

		<select name="expires">
			<option value=""><?php _e( 'Expires', 'woocommerce-memberships' ); ?></option>
			<?php
				if ( ! empty( $expires ) ) {
					foreach ( $expires as $expiry_term => $label ) {
						echo "\t<option value='" . esc_attr( $expiry_term ) . "'" . selected( $expiry_term, $selected_expiry_term, false ) . '>' . esc_html( $label ) . '</option>' . PHP_EOL;
					}
				}
			?>
		</select>
		<?php

	}


	/**
	 * Pause a membership
	 *
	 * @since 1.0.0
	 */
	public function pause_membership() {

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		// Get the post
		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		check_admin_referer( 'wc-memberships-pause-membership-' . $id );

		$user_membership = wc_memberships_get_user_membership( $id );
		$user_membership->pause_membership();

		wp_redirect( add_query_arg( array('paused' => 1, 'ids' => $_REQUEST['post'] ), $this->get_sendback_url() ) );
		exit();
	}


	/**
	 * Resume a membership
	 *
	 * @since 1.0.0
	 */
	public function resume_membership() {

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		// Get the post
		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		check_admin_referer( 'wc-memberships-resume-membership-' . $id );

		$user_membership = wc_memberships_get_user_membership( $id );
		$user_membership->activate_membership();

		wp_redirect( add_query_arg( array('resumed' => 1, 'ids' => $_REQUEST['post'] ), $this->get_sendback_url() ) );
		exit();
	}


	/**
	 * Cancel a membership
	 *
	 * @since 1.0.0
	 */
	public function cancel_membership() {

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		// Get the post
		$id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : '';

		check_admin_referer( 'wc-memberships-cancel-membership-' . $id );

		$user_membership = wc_memberships_get_user_membership( $id );
		$user_membership->cancel_membership();

		wp_redirect( add_query_arg( array('cancelled' => 1, 'ids' => $_REQUEST['post'] ), $this->get_sendback_url() ) );
		exit();
	}


	/**
	 * Get the sendback URL
	 *
	 * Mimics the core sendback url in wp-admin/post.php
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_sendback_url() {

		if ( isset( $_GET['post'] ) ) {
			$post_id = (int) $_GET['post'];
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$post_id = (int) $_POST['post_ID'];
		} else {
			$post_id = 0;
		}

		$post = $post_type = null;

		if ( $post_id ) {
			$post = get_post( $post_id );
		}

		if ( $post ) {
			$post_type = $post->post_type;
		}

		$sendback = wp_get_referer();

		if ( ! $sendback
		     || strpos( $sendback, 'post.php' ) !== false
		     || strpos( $sendback, 'post-new.php' ) !== false ) {

			$sendback = admin_url( 'edit.php' );
			$sendback .= ( ! empty( $post_type ) ) ? '?post_type=' . $post_type : '';

		} else {

			$sendback = remove_query_arg( array(
				'trashed',
				'untrashed',
				'deleted',
				'paused',
				'resumed',
				'cancelled',
				'updated',
				'ids'
			), $sendback );

		}

		return $sendback;
	}


	/**
	 * Display custom admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {

		global $post_type, $pagenow;

		if ( 'edit.php' == $pagenow ) {

			$message = '';

			if ( isset( $_REQUEST['paused'] ) && (int) $_REQUEST['paused'] ) {
				$message = sprintf( _n( 'User membership paused.', '%s user memberships paused.', $_REQUEST['paused'] ), number_format_i18n( $_REQUEST['paused'] ) );
			}

			if ( isset( $_REQUEST['cancelled'] ) && (int) $_REQUEST['cancelled'] ) {
				$message = sprintf( _n( 'User membership cancelled.', '%s user memberships cancelled.', $_REQUEST['cancelled'] ), number_format_i18n( $_REQUEST['cancelled'] ) );
			}

			if ( isset( $_REQUEST['resumed'] ) && (int) $_REQUEST['resumed'] ) {
				$message = sprintf( _n( 'User membership resumed.', '%s user memberships resumed.', $_REQUEST['resumed'] ), number_format_i18n( $_REQUEST['resumed'] ) );
			}

			if ( $message ) {
				echo "<div class='updated'><p>{$message}</p></div>";
			}

		}
	}


	/**
	 * Validate user membership data before saving
	 *
	 * @since 1.0.0
	 * @param bool $maybe_empty Is the post empty?
	 * @param array $postarr Array of post data
	 * @return bool $maybe_empty
	 */
	public function validate_user_membership( $maybe_empty, $postarr ) {

		// Bail out if not user membership
		if ( $postarr['post_type'] != 'wc_user_membership' ) {
			return $maybe_empty;
		}

		// Bail out if doing autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $maybe_empty;
		}

		// Prevent saving memberships with no plan
		if ( ! $postarr['post_parent'] && isset( $_POST['post_ID'] ) ) {

			wc_memberships()->admin->message_handler->add_error( __( 'Please select a membership plan.', 'woocommerce-memberships' ) );
			wp_redirect( wp_get_referer() ); exit;
		}

		return $maybe_empty;

	}


	/**
	 * Prevent adding a user membership if user is already a member of all plans
	 *
	 * @since 1.0.0
	 */
	public function maybe_prevent_adding_user_membership() {
		global $pagenow;

		if ( 'post-new.php' === $pagenow ) {

			// Get user details
			$user_id = ( isset( $_GET['user'] ) ? $_GET['user'] : null );
			$user    = $user_id ? get_userdata( $user_id ) : null;

			if ( ! $user_id || ! $user ) {

				wc_memberships()->admin->message_handler->add_error( __( 'Please select a user to add as a member.', 'woocommerce-memberships' ) );
				wp_redirect( wp_get_referer() ); exit;
			}

			// All the user memberships
			$user_memberships = wc_memberships_get_user_memberships( $user->ID );
			$membership_plans = wc_memberships_get_membership_plans( array(
				'post_status' => array( 'publish', 'private', 'future', 'draft', 'pending', 'trash' )
			) );

			if ( count( $user_memberships ) == count( $membership_plans ) ) {

				wc_memberships()->admin->message_handler->add_message( __( 'This user is already a member of every plan.', 'woocommerce-memberships' ) );
				wp_redirect( wp_get_referer() ); exit;
			}
		}
	}


}
