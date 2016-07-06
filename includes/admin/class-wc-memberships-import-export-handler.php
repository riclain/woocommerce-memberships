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

defined( 'ABSPATH' ) or exit;

/**
 * Import / Export Handler class
 *
 * @since 1.6.0
 */
class WC_Memberships_Admin_Import_Export_Handler {


	/** @var string The location of this page */
	private $url = '';

	/** @var array Sections of the Import / Export admin page */
	private $sections = array();

	/** @var string The current section of the Import / Export admin page */
	private $current_section = '';

	/** @var \WC_Memberships_CSV_Import_User_Memberships instance */
	protected $csv_import_user_memberships;

	/** @var \WC_Memberships_CSV_Export_User_Memberships instance */
	protected $csv_export_user_memberships;


	/**
	 * Constructor
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		$this->url = admin_url( 'admin.php?page=wc_memberships_import_export' );

		/**
		 * Filter the Memberships Import / Export admin sections
		 *
		 * @since 1.6.0
		 * @param $sections array Associative array with section ids and labels
		 */
		$this->sections = apply_filters( 'wc_memberships_admin_import_export_sections', array(
			'csv_export_user_memberships' => __( 'Export to CSV', 'woocommerce-memberships' ),
			'csv_import_user_memberships' => __( 'Import from CSV', 'woocommerce-memberships' ),
		) );

		// auto determine the section based on current page
		$this->current_section = $this->get_admin_page_current_section();
		$this->load_section();

		// output page content
		add_action( 'wc_memberships_render_import_export_page', array( $this, 'render_admin_page' ) );

		// add a bulk action to export User Memberships
		add_action( 'admin_footer-edit.php', array( $this, 'add_bulk_export' ) );
		add_action( 'load-edit.php',         array( $this, 'process_bulk_export' ) );

		// makes sure that the Memberships menu item is set to currently active
		add_filter( 'parent_file', array( $this, 'set_current_admin_menu_item' ) );
	}


	/**
	 * Set the Memberships admin menu item as active
	 * while viewing the Import / Export tab page
	 *
	 * @since 1.6.2
	 * @param string $parent_file
	 * @return string
	 */
	public function set_current_admin_menu_item( $parent_file ) {
		global $menu, $submenu_file;

		if ( isset( $_GET['page'] ) && 'wc_memberships_import_export' === $_GET['page'] ) {

			$submenu_file = 'edit.php?post_type=wc_user_membership';

			if ( ! empty( $menu ) ) {

				foreach ( $menu as $key => $value ) {

					if ( isset( $value[2], $menu[ $key ][4] ) && 'woocommerce' === $value[2] ) {
						$menu[ $key ][4] .= ' wp-has-current-submenu wp-menu-open';
					}
				}
			}
		}

		return $parent_file;
	}


	/**
	 * Set the current action
	 *
	 * @since 1.6.0
	 * @param string $action One valid section or will attempt to auto-determine
	 */
	public function set_action( $action = '' ) {

		$this->current_section = array_key_exists( $action, $this->sections ) ? $action : $this->get_admin_page_current_section();
		$this->load_section();
	}


	/**
	 * Load section
	 *
	 * @since 1.6.0
	 */
	private function load_section() {

		require_once( wc_memberships()->get_plugin_path() . '/includes/admin/abstract-wc-memberships-import-export.php' );

		// load the class according to section to be displayed
		if ( 'csv_import_user_memberships' === $this->current_section ) {
			$this->csv_import_user_memberships = wc_memberships()->load_class( '/includes/admin/class-wc-memberships-csv-import-user-memberships.php', 'WC_Memberships_CSV_Import_User_Memberships' );
		} elseif ( 'csv_export_user_memberships' === $this->current_section ) {
			$this->csv_export_user_memberships = wc_memberships()->load_class( '/includes/admin/class-wc-memberships-csv-export-user-memberships.php', 'WC_Memberships_CSV_Export_User_Memberships' );
		}
	}


	/**
	 * Get the Import instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_CSV_Import_User_Memberships
	 */
	public function get_csv_import_user_memberships_instance() {
		return $this->csv_import_user_memberships;
	}


	/**
	 * Get the Export instance
	 *
	 * @since 1.6.0
	 * @return \WC_Memberships_CSV_Export_User_Memberships
	 */
	public function get_csv_export_user_memberships_instance() {
		return $this->csv_export_user_memberships;
	}


	/**
	 * Add bulk User Memberships export action
	 *
	 * @since 1.6.0
	 */
	public function add_bulk_export() {
		global $post_type;

		if( $post_type === 'wc_user_membership' && current_user_can( 'manage_woocommerce' ) ) :

			?>
			<script type="text/javascript">
				jQuery( document ).ready(function() {
					var label = '<?php esc_html_e( 'Export to CSV', 'woocommerce-memberships' ); ?>';
					jQuery( '<option>' ).val( 'export' ).text( label ).appendTo( 'select[name="action"]' );
					jQuery( '<option>' ).val( 'export' ).text( label ).appendTo( 'select[name="action2"]' );
				} );
			</script>
			<?php

		endif;
	}


	/**
	 * Process bulk User Memberships export action
	 *
	 * @since 1.6.0
	 */
	public function process_bulk_export() {

		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		if ( 'export' === $action ) {

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'You are not allowed to perform this action.', 'woocommerce-memberships' ) );
			}

			$post_ids = isset( $_GET['post'] ) ? array_map( 'absint', $_GET['post'] ) : array();

			$this->set_action( 'csv_export_user_memberships' );

			if ( $export_instance = $this->get_csv_export_user_memberships_instance() ) {

				$export_instance->set_export_ids( $post_ids );
				$export_instance->process_export();
			}
		}
	}


	/**
	 * Render the page within Memberships admin page tabs
	 *
	 * @since 1.6.0
	 */
	public function render_admin_page() {

		$current_section = $this->current_section;
		$section_class   = ! empty( $current_section ) ? sanitize_html_class( 'woocommerce-memberships-' . $current_section ) : '';

		echo '<div class="wrap woocommerce woocommerce-memberships woocoomerce-memberships-import-export ' . $section_class .'">';

		$this->render_admin_page_sections_navigation_links( $current_section );

		echo '<br class="clear">';

		/**
		 * Render the current section in the Import / Export admin page
		 *
		 * @since 1.6.0
		 * @param string $current_section The section that should be displayed
		 */
		do_action( 'wc_memberships_render_import_export_page_section', $current_section );

		echo '</div>';
	}


	/**
	 * Get the import / export admin screen url
	 *
	 * @since 1.6.0
	 * @param string $section Optional, defaults to current section
	 * @return string
	 */
	public function get_admin_page_url( $section = '' ) {

		$section = empty( $section ) ? $this->get_admin_page_current_section() : $section;

		return add_query_arg( array( 'section' => $section ), $this->url );
	}


	/**
	 * Get the admin page sections
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public function get_admin_page_sections() {
		return $this->sections;
	}


	/**
	 * Get the admin page current section
	 *
	 * @since 1.6.0
	 * @return string
	 */
	public function get_admin_page_current_section() {

		$current_section = '';

		if ( ! empty( $this->sections ) ) {

			$sections        = array_keys( $this->sections );
			$current_section = current( $sections );

			if ( isset( $_GET['section'] ) && in_array( $_GET['section'], $sections, true ) ) {

				$current_section = $_GET['section'];
			}
		}

		return $current_section;
	}


	/**
	 * Generates sections navigation items
	 *
	 * @since 1.6.0
	 * @param string $current_section Optional, if empty will determine the current section
	 */
	private function render_admin_page_sections_navigation_links( $current_section = '' ) {

		if ( ! empty ( $this->sections ) ) {

			if ( '' === $current_section ) {
				$current_section = $this->get_admin_page_current_section();
			}

			$links = array();

			foreach ( $this->sections as $id => $label ) {

				$url   = add_query_arg( 'section', $id, $this->get_admin_page_url() );
				$class = $id === $current_section ? 'class="current"' : '';

				$links[] = '<li><a href="' . esc_url( $url ) . '" ' . $class . '>' . esc_html( $label ) . '</a></li>';
			}

			echo '<ul class="subsubsub">' . implode( ' | ', $links ) . '</ul>';
		}
	}


	/**
	 * Process the submission form
	 *
	 * @see WC_Memberships_Admin::process_import_export_form()
	 *
	 * @since 1.6.0
	 */
	public function process_form() {

		if ( 'csv_export_user_memberships' === $this->current_section ) {
			$this->get_csv_export_user_memberships_instance()->process_export();
		} elseif ( 'csv_import_user_memberships' === $this->current_section ) {
			$this->get_csv_import_user_memberships_instance()->process_import();
		}
	}


}
