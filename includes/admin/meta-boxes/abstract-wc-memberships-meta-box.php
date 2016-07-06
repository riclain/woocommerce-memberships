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
 * @package   WC-Memberships/Admin/Meta-Boxes
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Abstract Meta Box for Memberships
 *
 * Serves as a base meta box class for different meta boxes. One of the goals
 * is to keep meta box classes as self-contained as possible, removing any
 * external setup or configuration.
 *
 * @since 1.0.0
 */
abstract class WC_Memberships_Meta_Box {


	/** @var string meta box id **/
	protected $id;

	/** @var string meta box context **/
	protected $context = 'normal';

	/** @var string meta box priority **/
	protected $priority = 'default';

	/** @var array list of supported screen IDs **/
	protected $screens = array();

	/** @var array list of additional postbox classes for this meta box **/
	protected $postbox_classes = array( 'wc-memberships', 'woocommerce' );


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Add/Edit screen hooks
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Enqueue meta box scripts and styles, but only if the
		// meta box has scripts or styles
		if ( method_exists( $this, 'enqueue_scripts_and_styles' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_scripts_and_styles' ) );
		}

		// Update meta box data when saving post, but only if the
		// meta box supports data updates
		if ( method_exists( $this, 'update_data' ) ) {
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		}
	}


	/**
	 * Get the meta box title
	 *
	 * @since 1.0.0
	 * @return string
	 */
	abstract function get_title();


	/**
	 * Get the meta box ID
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Get the meta box ID, with underscores instead of dashes
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_id_underscored() {
		return str_replace( '-', '_', $this->id );
	}


	/**
	 * Get the nonce name for this meta box
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_nonce_name() {
		return '_' . $this->get_id_underscored() . '_nonce';
	}


	/**
	 * Get the nonce action for this meta box
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_nonce_action() {
		return 'update-' . $this->id;
	}


	/**
	 * Enqueue scripts & styles for this meta box, if conditions are met
	 *
	 * @since 1.0.0
	 */
	public function maybe_enqueue_scripts_and_styles() {

		$screen = get_current_screen();

		if ( ! in_array( $screen->id, $this->screens ) ) {
			return;
		}

		$this->enqueue_scripts_and_styles();
	}


	/**
	 * Enqueue scripts and styles for this meta box
	 *
	 * Default implementation is a no-op.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts_and_styles() {
		// No-op, implement in subclass
	}


	/**
	 * Add meta box to the supported screen(s)
	 *
	 * @since 1.0.0
	 */
	public function add_meta_box() {

		$screen = get_current_screen();

		if ( ! in_array( $screen->id, $this->screens ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce_membership_plans' ) ) {
			return;
		}

		// Membership Plan data meta box
		add_meta_box(
			$this->id,
			$this->get_title(),
			array( $this, 'do_output' ),
			$screen->id,
			$this->context,
			$this->priority
		);

		add_filter( "postbox_classes_{$screen->id}_{$this->id}", array( $this, 'postbox_classes' ) );
	}


	/**
	 * Add wc-memberships class to meta box
	 *
	 * @since 1.0.0
	 * @param array $classes
	 * @return array
	 */
	public function postbox_classes( $classes ) {
		return array_merge( $classes, $this->postbox_classes );
	}


	/**
	 * Output basic meta box contents
	 *
	 * @since 1.0.0
	 */
	public function do_output() {
		global $post;

		// Add a nonce field
		if ( method_exists( $this, 'update_data' ) ) {
			wp_nonce_field( $this->get_nonce_action(), $this->get_nonce_name() );
		}

		// Output implementation-specific HTML
		$this->output( $post );
	}


	/**
	 * Output meta box contents
	 *
	 * @param WP_Post $post
	 * @since 1.0.0
	 */
	abstract function output( WP_Post $post );


	/**
	 * Process and save meta box data
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, WP_Post $post ) {

		// Check nonce
		if ( ! isset( $_POST[ $this->get_nonce_name() ] ) || ! wp_verify_nonce( $_POST[ $this->get_nonce_name() ], $this->get_nonce_action() ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bail out if not a supported post type
		if ( ! in_array( $post->post_type, $this->screens ) ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		if ( ! current_user_can( 'manage_woocommerce_membership_plans' ) ) {
			return;
		}

		// Implementation-specific meta box data update
		$this->update_data( $post_id, $post );
	}


}
