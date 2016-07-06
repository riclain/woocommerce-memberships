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
 * Import Members CSV
 *
 * @since 1.6.0
 */
class WC_Memberships_CSV_Import_User_Memberships extends WC_Memberships_Import_Export {


	/** @var bool Whether to create new User Memberships when a record is not found */
	public $create_new_memberships = false;

	/** @var bool Whether to merge existing User Memberships when a matching membership is found */
	public $merge_existing_memberships = false;

	/** @var bool Whether to allow transferring a User Membership to another user when there is a user conflict in update */
	public $allow_memberships_transfer = false;

	/** @var bool Whether to create new users to associate to a new User Membership if no user is found */
	public $create_new_users = false;

	/** @var string Default User Membership start date to user when creating a new membership and no date is found in import */
	public $default_start_date = '';

	/** @var string Timezone to use to handle dates in import, defaults to site timezone */
	public $timezone = '';


	/**
	 * Import admin page setup
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		$this->action       = 'csv_import_user_memberships';
		$this->action_label = __( 'Upload File and Import', 'woocommerce-memberships' );

		$this->default_start_date   = date( 'Y-m-d', current_time( 'timestamp' ) );
		$this->delimiter_field_name = 'wc_memberships_members_csv_import_fields_delimiter';

		/**
		 * Filter the CSV import enclosure
		 *
		 * @since 1.6.0
		 * @param string $enclosure Default double quote `"`
		 * @param \WC_Memberships_CSV_Import_User_Memberships $export_instance Instance of the import class
		 */
		$this->enclosure = apply_filters( 'wc_memberships_csv_import_enclosure', '"', $this );

		$docs_button = '<p><a class="button" href="https://docs.woothemes.com/document/woocommerce-memberships-import-and-export/">' . esc_html__( 'See Documentation', 'woocommerce-memberships' ). '</a>';

		wc_memberships()->get_admin_notice_handler()->add_admin_notice(
			'<p>' . __( '<strong>Members CSV Import</strong> - Importing members will create or update automatically User Memberships in bulk. Importing members <strong>does not</strong> create any associated billing, subscription or order records.', 'woocommerce-memberships' ) . '</p>' . $docs_button,
			'wc-memberships-csv-import-user-memberships-docs'
		);

		parent::__construct();
	}


	/**
	 * Set the admin page title
	 *
	 * @since 1.6.2
	 * @param string $admin_title The page title, with extra context added
	 * @param string $title The original page title
	 * @return string
	 */
	public function set_admin_page_title( $admin_title, $title ) {

		if ( isset( $_GET['page'] ) && 'wc_memberships_import_export' === $_GET['page'] ) {
			return __( 'Import Members', 'woocommerce-memberships' ) . ' ' . $admin_title;
		}

		return $title;
	}


	/**
	 * Get import options input fields
	 *
	 * @since 1.6.0
	 * @return array
	 */
	protected function get_fields() {

		$documentation_url = 'https://docs.woothemes.com/document/woocommerce-memberships-import-and-export/';
		$max_upload_size   = size_format( wc_let_to_num( ini_get( 'post_max_size' ) ) );

		if ( ! $site_timezone = wc_timezone_string() ) {
			$site_timezone = 'UTC';
		}

		/**
		 * Filter the CSV Import User Memberships options
		 *
		 * @since 1.6.0
		 * @param array $options Associative array
		 */
		return apply_filters( 'wc_memberships_csv_import_user_memberships_options', array(

			// section start
			array(
				'title' => __( 'Import Members', 'woocommerce-memberships' ),
				/* translators: Placeholders: %1$s - opening <a> link HTML tag, $2$s - closing </a> link HTML tag */
				'desc'  => sprintf(
					__( 'Your CSV file must be formatted with the correct column names and cell data. Please %1$ssee the documentation%2$s for more information and a sample CSV file.', 'woocommerce-memberships' ),
					'<a href="' . $documentation_url . '">',
					'</a>'
				),
 				'type'  => 'title',
			),

			// csv file to upload
			array(
				'id'       => 'wc_memberships_members_csv_import_file',
				'title'    => __( 'Choose a file from your computer', 'woocommerce-memberships' ),
				/* translators: Placeholder: %s - maximum uploadable file size (e.g. 8M, 20M, 100M...)  */
				'desc_tip' => sprintf(
					__( 'Acceptable file types: CSV or tab-delimited text files. Maximum file size: %s', 'woocommerce-memberships' ),
					empty( $max_upload_size ) ? '<em>' . __( 'Undetermined', 'woocommerce-memberships' ) . '</em>' : $max_upload_size
				),
				'type'     => 'wc-memberships-import-file',
			),

			// update existing user memberships?
			array(
				'id'            => 'wc_memberships_members_csv_import_merge_existing_user_memberships',
				'title'         => __( 'Import Options', 'woocommerce-memberships' ),
				'desc'          => __( 'Update existing records if a matching user membership is found (by User Membership ID)', 'woocommerce-memberships' ),
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),

			// allow transferring memberships in case of user conflict?
			array(
				'id'            => 'wc_memberships_members_csv_import_allow_memberships_transfer',
				'desc'          => __( 'Allow membership transfer between users if the imported user differs from the existing user for the membership (skips conflicting rows when disabled)', 'woocommerce-memberships' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
			),

			// create new memberships?
			array(
				'id'            => 'wc_memberships_members_csv_import_create_new_user_memberships',
				'desc'          => __( 'Create new user memberships if a matching User Membership ID is not found (skips rows when disabled)', 'woocommerce-memberships' ),
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => '',
			),

			// create new users?
			array(
				'id'            => 'wc_memberships_members_csv_import_create_new_users',
				'desc'          => __( 'Create a new user if no matching user is found (skips rows when disabled)', 'woocommerce-memberships' ),
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => 'end',
			),

			// default start date when unspecified
			array(
				'id'          => 'wc_memberships_members_csv_import_default_start_date',
				'title'       => __( 'Default Start Date', 'woocommerce-memberships' ),
				'desc'        => __( "When creating new memberships, you can specify a default date to set a membership start date if not defined in the import data. Leave this blank to use today's date otherwise.", 'woocommerce-memberships' ),
				'default'     => '',
				'placeholder' => date( 'Y-m-d' ),
				'type'        => 'text',
				'class'       => 'js-user-membership-date',
			),

			// timezone
			array(
				'id'       => 'wc_memberships_members_csv_import_timezone',
				'title'    => __( 'Dates timezone', 'woocommerce-memberships' ),
				'type'     => 'select',
				'desc_tip' => __( 'Choose the timezone the dates in the import are from.', 'woocommerce-memberships' ),
				'options'  => array(
					 $site_timezone => __( 'Site timezone', 'woocommerce-memberships' ),
					'UTC'           => __( 'UTC', 'woocommerce-memberships' ),
				),
			),

			// entries are separated by comma or tab?
			array(
				'id'       => $this->delimiter_field_name,
				'title'    => __( 'Fields are separated by', 'woocommerce-memberships' ),
				'type'     => 'select',
				'desc_tip' => __( 'Change the delimiter based on your input file format.', 'woocommerce-memberships' ),
				'options'  => array(
					'comma' => __( 'Comma', 'woocommerce-memberships' ),
					'tab'   => __( 'Tab space', 'woocommerce-memberships' ),
				),
			),

			// end of section
			array( 'type' => 'sectionend' ),

		) );
	}


	/**
	 * Process input form submission to import
	 *
	 * @see WC_Memberships_CSV_Import_User_Memberships::import_user_memberships()
	 * for details on the import process and required fields
	 *
	 * @since 1.6.0
	 */
	public function process_import() {

		// bail out and return an error notice if no file was added for upload
		if ( empty( $_FILES['wc_memberships_members_csv_import_file'] ) || empty( $_FILES['wc_memberships_members_csv_import_file']['name'] ) ) {

			wc_memberships()->get_admin_instance()->get_message_handler()->add_error(
				__( 'You must upload a file to import User Memberships from.', 'woocommerce-memberships' )
			);

		// bail out if an upload error occurred (most likely a server issue)
		} elseif ( isset( $_FILES['wc_memberships_members_csv_import_file']['error'] ) && $_FILES['wc_memberships_members_csv_import_file']['error'] > 0 ) {

			wc_memberships()->get_admin_instance()->get_message_handler()->add_error(
				/* translators: Placeholder: %s - error message */
				sprintf( __( 'There was a problem uploading the file: %s', 'woocommerce-memberships' ), '<em>' . $this->get_file_upload_error( $_FILES['wc_memberships_members_csv_import_file']['error'] ) . '</em>' )
			);

		// process the file once uploaded
		} else {

			// get csv data from file
			if ( isset( $_FILES['wc_memberships_members_csv_import_file']['tmp_name'] ) ) {
				$csv_data = $this->parse_file_csv( $_FILES['wc_memberships_members_csv_import_file']['tmp_name'] );
			}

			// bail out if the file can't be parsed or there are only headers
			if ( empty( $csv_data ) || count( $csv_data ) <= 1 ) {

				wc_memberships()->get_admin_instance()->get_message_handler()->add_error(
					__( 'Could not find User Memberships to import from uploaded file.', 'woocommerce-memberships' )
				);

			} else {

				// set up importing options
				$this->create_new_memberships        = isset( $_POST['wc_memberships_members_csv_import_create_new_user_memberships'] )     ? 1 === (int) $_POST['wc_memberships_members_csv_import_create_new_user_memberships']     : $this->create_new_memberships;
				$this->merge_existing_memberships    = isset( $_POST['wc_memberships_members_csv_import_merge_existing_user_memberships'] ) ? 1 === (int) $_POST['wc_memberships_members_csv_import_merge_existing_user_memberships'] : $this->merge_existing_memberships;
				$this->create_new_users              = isset( $_POST['wc_memberships_members_csv_import_create_new_users'] )                ? 1 === (int) $_POST['wc_memberships_members_csv_import_create_new_users']                : $this->create_new_users;
				$this->allow_memberships_transfer    = isset( $_POST['wc_memberships_members_csv_import_allow_memberships_transfer'] )      ? 1 === (int) $_POST['wc_memberships_members_csv_import_allow_memberships_transfer']      : $this->allow_memberships_transfer;
				$this->default_start_date            = ! empty( $_POST['wc_memberships_members_csv_import_default_start_date'] )            ? $_POST['wc_memberships_members_csv_import_default_start_date']                          : $this->default_start_date;

				/**
				 * Filter the import timezone
				 *
				 * @since 1.6.0
				 * @param string $timezone A valid timezone
				 * @param \WC_Memberships_CSV_Import_User_Memberships $export_instance Instance of the export class
				 */
				$this->timezone = apply_filters( 'wc_memberships_csv_import_timezone', $_POST['wc_memberships_members_csv_import_timezone'], $this );

				// process rows to import
				$this->import_user_memberships( $csv_data );
			}
		}
	}


	/**
	 * Parse a file with CSV data into an array
	 *
	 * @since 1.6.0
	 * @param resource $file_handle File to process as a resource
	 * @return null|array Array data or null on read error
	 */
	protected function parse_file_csv( $file_handle ) {

		if ( is_readable( $file_handle ) ) {

			$csv_data = array();

			// get the data from file
			$file_contents = fopen( $file_handle, 'r' );

			// this helps with files from some spreadsheet/csv editors,
			// such as Excel on Mac computers which seem to handle line breaks differently
			@ini_set( 'auto_detect_line_endings', true );

			// handle character encoding
			if ( $enc = mb_detect_encoding( $file_handle, 'UTF-8, ISO-8859-1', true ) ) {
				setlocale( LC_ALL, 'en_US.' . $enc );
			}

			while ( ( $row = fgetcsv( $file_contents, 0, $this->get_fields_delimiter(), $this->enclosure ) ) !== false ) {
				$csv_data[] = $row;
			}

			fclose( $file_contents );

			return $csv_data;
		}

		return null;
	}


	/**
	 * Import User Memberships from CSV data
	 *
	 * When creating new memberships, the only required field is either
	 * `membership_plan_id` or `membership_plan_slug`, in order to determine
	 * a Membership Plan to assign to a User Membership (if the id is unspecified
	 * or not found among the plans available, it will try to look for one using
	 * the plan's post slug).
	 *
	 * A `user_membership_id` field is required only if we want to update
	 * an existing User Membership.
	 *
	 * A `user_id` needs to exist if we are not allowing to create new users;
	 * if updating an existing User Membership, the `user_id` has to match
	 * the user connected to that membership; if `user_id` is not specified,
	 * there is an option to attempt retrieving a WP user from `user_name`
	 * (WP login name) or `member_email` email address fields. When creating
	 * new users, an email must be specified or the row will be skipped;
	 * the `user_name` is used to create a login name, if conflicts
	 * with an existing one, the import script will use the first piece of
	 * the email address, perhaps with a random numerical suffix.
	 *
	 * @since 1.6.0
	 * @param array $rows CSV import data parsed into an array format, with headers in the first key
	 */
	protected function import_user_memberships( array $rows ) {

		$created = 0;
		$merged  = 0;

		// get the column keys and remove them from the data set
		$columns = array_flip( $rows[0] );
		unset( $rows[0] );

		$total = count( $rows );

		if ( ! empty( $columns ) && ! empty( $rows ) ) {

			foreach ( $rows as $row ) {

				// try to get a plan from id or slug
				$membership_plan_id   = isset( $columns['membership_plan_id'] )   && ! empty( $row[ $columns['membership_plan_id'] ] )   ? (int) $row[ $columns['membership_plan_id'] ] : null;
				$membership_plan_slug = isset( $columns['membership_plan_slug'] ) && ! empty( $row[ $columns['membership_plan_slug'] ] ) ? $row[ $columns['membership_plan_slug'] ]     : null;
				$membership_plan      = null;

				if ( is_int( $membership_plan_id ) ) {
					$membership_plan = wc_memberships_get_membership_plan( $membership_plan_id );
				}

				if ( ! $membership_plan && ! empty( $membership_plan_slug ) ) {
					$membership_plan = wc_memberships_get_membership_plan( $membership_plan_slug );
				}

				// try to get an existing user membership from an id
				$user_membership_id       = isset( $columns['user_membership_id'] ) && ! empty( $row[ $columns['user_membership_id'] ] ) ? (int) $row[ $columns['user_membership_id'] ] : null;
				$existing_user_membership = is_int( $user_membership_id ) ? wc_memberships_get_user_membership( $user_membership_id ) : null;

				if ( ! $membership_plan && ! $existing_user_membership ) {
					// bail out if we can't process a plan or a user membership to begin with
					continue;
				} elseif ( ! $existing_user_membership && false === $this->create_new_memberships ) {
					// bail if no User Membership is found and we do not create new memberships
					continue;
				} elseif ( $existing_user_membership && false === $this->merge_existing_memberships ) {
					// bail if there is already a User Membership and we are not supposed to merge
					continue;
				}

				// prepare variables
				$import_data['membership_plan_id']    = $membership_plan_id;
				$import_data['membership_plan_slug']  = $membership_plan_slug;
				$import_data['membership_plan_name']  = isset( $columns['membership_plan'] )       && ! empty( $row[ $columns['membership_plan'] ] )       ? $row[ $columns['membership_plan'] ]       : null;
				$import_data['membership_plan']       = $membership_plan;
				$import_data['user_membership_id']    = $user_membership_id;
				$import_data['user_membership']       = $existing_user_membership;
				$import_data['user_id']               = isset( $columns['user_id'] )               && ! empty( $row[ $columns['user_id'] ] )               ? $row[ $columns['user_id'] ]               : null;
				$import_data['user_name']             = isset( $columns['user_name'] )             && ! empty( $row[ $columns['user_name'] ] )             ? $row[ $columns['user_name'] ]             : null;
				$import_data['product_id']            = isset( $columns['product_id'] )            && ! empty( $row[ $columns['product_id'] ] )            ? $row[ $columns['product_id'] ]            : null;
				$impost_data['order_id']              = isset( $columns['order_id'] )              && ! empty( $row[ $columns['order_id'] ] )              ? $row[ $columns['order_id'] ]              : null;
				$import_data['member_email']          = isset( $columns['member_email'] )          && ! empty( $row[ $columns['member_email'] ] )          ? $row[ $columns['member_email'] ]          : null;
				$import_data['member_first_name']     = isset( $columns['member_first_name'] )     && ! empty( $row[ $columns['member_first_name'] ] )     ? $row[ $columns['member_first_name'] ]     : null;
				$import_data['member_last_name']      = isset( $columns['member_last_name'] )      && ! empty( $row[ $columns['member_last_name'] ] )      ? $row[ $columns['member_last_name'] ]      : null;
				$import_data['membership_status']     = isset( $columns['membership_status'] )     && ! empty( $row[ $columns['membership_status'] ] )     ? $row[ $columns['membership_status'] ]     : null;
				$import_data['member_since']          = isset( $columns['member_since'] )          && ! empty( $row[ $columns['member_since'] ] )          ? $row[ $columns['member_since'] ]          : null;
				$import_data['membership_expiration'] = isset( $columns['membership_expiration'] ) && isset( $row[ $columns['membership_expiration'] ] )   ? $row[ $columns['membership_expiration'] ] : null;

				// create or update a User Membership and bump counters
				if ( ! $existing_user_membership && true === $this->create_new_memberships ) {
					$created += (int) $this->import_user_membership( 'create', $import_data );
				} elseif ( $existing_user_membership && true === $this->merge_existing_memberships ) {
					$merged  += (int) $this->import_user_membership( 'merge', $import_data );
				}
			}
		}

		// output results in notice
		$this->show_results_notice( $total, $created, $merged );
	}


	/**
	 * Creates or updates a User Membership according to import data
	 *
	 * @see \WC_Memberships_CSV_Import_User_Memberships::import_user_memberships()
	 *
	 * @since 1.6.0
	 * @param string $action Either 'create' or 'renew' (for updating/merging)
	 * @param array $import_data User Membership import data
	 * @return null|bool
	 */
	private function import_user_membership( $action = '', array $import_data ) {

		// bail out if no valid action is specified
		if ( ! in_array( $action, array( 'create', 'merge' ), true ) ) {
			return null;
		}

		/**
		 * Filter CSV User Membership import data
		 * before processing an import
		 *
		 * @since 1.6.0
		 * @param array $import_data The imported data as associative array
		 * @param string $action Either 'create' or 'merge' (update) a User Membership
		 */
		$data = apply_filters( 'wc_memberships_csv_import_user_memberships_data', $import_data, $action );

		$user_id = $this->import_user_id( $action, $data );

		// bail out if a user couldn't be determined
		if ( 0 === $user_id ) {
			return false;
		}

		$user_membership = null;

		if ( 'merge' === $action
		     && isset( $data['user_membership'] )
		     && $data['user_membership'] instanceof WC_Memberships_User_Membership ) {

			// update an existing User Membership
			$user_membership = $this->update_user_membership( $user_id, $data );

		} elseif ( 'create' === $action
		           && isset( $data['membership_plan'] )
		           && $data['membership_plan'] instanceof WC_Memberships_Membership_Plan ) {

			// sanity check: bail out if user is already member
			if ( wc_memberships_is_user_member( $user_id, $data['membership_plan'] ) ) {
				return false;
			}

			$order_id = 0;

			if ( ! empty( $data['order_id'] ) && is_numeric( $data['order_id'] ) ) {

				$order    = wc_get_order( (int) $data['order_id'] );
				$order_id = $order instanceof WC_Order ? $order->id : $order_id;
			}

			$product_id = 0;

			if ( ! empty( $data['product_id'] ) && is_numeric( $data['product_id'] ) ) {

				$product    = wc_get_product( (int) $data['product_id'] );
				$product_id = $product instanceof WC_Product ? (int) $data['product_id'] : $product_id;
			}

			// create or update the User Membership
			$user_membership = wc_memberships_create_user_membership( array(
				'user_membership_id' => 0,
				'plan_id'            => $data['membership_plan']->get_id(),
				'user_id'            => $user_id,
				'product_id'         => $product_id,
				'order_id'           => $order_id,
			), 'create' );

		} else {

			return false;
		}

		if ( ! $user_membership instanceof WC_Memberships_User_Membership ) {

			// bail out if an error occurred
			return false;

		} elseif ( 'create' === $action ) {

			$user_membership->add_note(
				/* translators: Placeholder: %s - User display name */
				sprintf( __( "Membership created from %s's import.", 'woocommerce-memberships' ), wp_get_current_user()->display_name )
			);
		}

		// update meta upon create or merge
		if ( 'create' === $action || true === $this->merge_existing_memberships ) {
			$this->update_user_membership_meta( $user_membership, $action, $import_data );
		}

		/**
		 * Upon creating or updating a User Membership from import data
		 *
		 * @since 1.6.0
		 * @param \WC_Memberships_User_Membership $user_membership User Membership object
		 * @param string $action Either 'create' or 'merge' (update) a User Membership
		 * @param array $data Import data
		 */
		do_action( 'wc_memberships_csv_import_user_membership', $user_membership, $action, $data );

		return true;
	}


	/**
	 * Obtain a user ID from an existing user or a newly created one
	 *
	 * @since 1.6.0
	 * @param string $action Either 'merge' or 'create
	 * @param array $data Import data
	 * @return int A valid user ID or 0 on unsuccessful import
	 */
	private function import_user_id( $action, $data )  {

		// try to get a user from passed data, by id or other fields
		$user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : $this->get_user( $data );
		$user_id = $user instanceof WP_User ? $user->ID : 0;

		// if can't determine a valid user, try to create one
		if ( 0 === $user_id
		     && true === $this->create_new_users
		     && ( 'create' === $action || ( $this->allow_memberships_transfer && isset( $data['member_email'] ) ) ) ) {

			$user    = $this->create_user( $data );
			$user_id = $user ? $user->ID : $user_id;
		}

		return (int) $user_id;
	}


	/**
	 * Update a User Membership
	 *
	 * @since 1.6.0
	 * @param int $user_id User ID to update Membership for
	 * @param array $data User Membership data to update
	 * @return false|\WC_Memberships_User_Membership
	 */
	private function update_user_membership( $user_id, array $data ) {

		$user_membership    = $data['user_membership'];
		$membership_plan    = isset( $data['membership_plan'] ) && $data['membership_plan'] instanceof WC_Memberships_Membership_Plan ? $data['membership_plan'] : null;
		$transfer_ownership = false;
		$previous_owner     = $user_membership->get_user_id();
		$update_args        = array();

		// check for users conflict
		if ( (int) $user_id !== (int) $previous_owner ) {

			if ( true === $this->allow_memberships_transfer ) {
				$transfer_ownership = true;
			} else {
				return false;
			}
		}

		// check for plans conflict
		if ( null !== $membership_plan && (int) $user_membership->get_plan_id() !== (int) $membership_plan->get_id() ) {

			// bail out if the user is already an active member of the plan we're transferring to
			if ( wc_memberships_is_user_active_member( $user_id, $membership_plan->get_id() ) ) {
				return false;
			}

			$update_args = array_merge( $update_args, array(
				'ID'          => $user_membership->get_id(),
				'post_parent' => $membership_plan->get_id(),
				'post_type'   => 'wc_user_membership',
			) );
		}

		// maybe update the post object first
		if ( ! empty( $update_args ) ) {

			$update = wp_update_post( $update_args );

			// ...so we can bail out in case of errors
			if ( 0 === $update || is_wp_error( $update ) ) {
				return false;
			}
		}

		// maybe transfer this membership
		if ( true === $transfer_ownership ) {
			$user_membership->transfer_ownership( $user_id );
		}

		return $user_membership;
	}


	/**
	 * Update a User Membership meta data
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership $user_membership
	 * @param string $action Either 'create' or 'merge' a User Membership
	 * @param array $data Import data
	 */
	private function update_user_membership_meta( WC_Memberships_User_Membership $user_membership, $action, array $data ) {

		// maybe update start date
		if ( ! empty( $data['member_since'] ) ) {

			if ( $this->is_date( $data['member_since'] ) ) {
				$user_membership->set_start_date( $this->parse_date_mysql( $data['member_since'], $this->timezone ) );
			}

		} elseif ( 'create' === $action && $this->is_date( $this->default_start_date ) ) {

			$user_membership->set_start_date( $this->parse_date_mysql( $this->default_start_date, wc_timezone_string() ) );
		}

		// maybe update status
		if ( $this->is_status( trim( $data['membership_status'] ) ) ) {

			$user_membership->update_status( trim( $data['membership_status'] ) );

		} elseif ( 'create' === $action ) {

			/**
			 * Filter the default User Membership status
			 * to be applied during an import, when not specified
			 *
			 * @since 1.6.0
			 * @param string $default_status Default 'active'
			 * @param \WC_Memberships_User_Membership $user_membership The current User Membership object
			 * @param array $data Import data for the current User Membership
			 */
			$default_membership_status = apply_filters( 'wc_memberships_csv_import_default_user_membership_status', 'active', $user_membership, $data );

			if ( 'active' !== $default_membership_status && $this->is_status( $default_membership_status ) ) {

				$user_membership->update_status( $default_membership_status );
			}
		}

		// maybe update end date (this could affect status)
		if ( $this->is_date( $data['membership_expiration'] ) ) {
			$user_membership->set_end_date( $this->parse_date_mysql( $data['membership_expiration'], $this->timezone ) );
		} elseif ( is_string( $data['membership_expiration'] ) && '' === trim( $data['membership_expiration'] ) ) {
			$user_membership->set_end_date( '' );
		}

		$expiry_date = $user_membership->get_end_date( 'timestamp' );

		// if expiry date is in the past (with 1 minute buffer), set the membership as expired
		if ( $expiry_date && (int) $expiry_date + 60 <= current_time( 'timestamp', true ) && ! $user_membership->is_expired() ) {
			$user_membership->expire_membership();
		}

		// maybe update the product that grants access
		if ( ! empty( $data['product_id'] ) && is_numeric( $data['product_id'] ) ) {

			$product = wc_get_product( (int) $data['product_id'] );

			if ( $product instanceof WC_Product ) {
				update_post_meta( $user_membership->get_id(), '_product_id', (int) $data['product_id'] );
			}
		}

		// maybe update the order that granted access
		if ( ! empty( $data['order_id'] ) && is_numeric( $data['order_id'] ) ) {

			$order = wc_get_order( (int) $data['order_id'] );

			if ( $order instanceof WC_Order ) {
				update_post_meta( $user_membership->get_id(), '_order_id', (int) $data['order_id'] );
			}
		}
	}


	/**
	 * Get a user from import data
	 *
	 * @since 1.6.0
	 * @param $user_data array Imported user information
	 * @return false|\WP_User
	 */
	protected function get_user( $user_data ) {

		$user = false;

		if ( isset( $user_data['user_id'] ) && is_numeric( $user_data['user_id'] ) ) {
			$user = get_user_by( 'id', (int) $user_data['user_id'] );
		}

		// look for a user using alternative fields other than id
		if ( ! $user ) {

			// try first to get user by login name
			if ( ! empty( $user_data['user_name'] ) ) {
				$user = get_user_by( 'login', $user_data['user_name'] );
			}

			// if it fails, try to get user by email
			if ( ! $user && isset( $user_data['member_email'] ) && is_email( $user_data['member_email'] ) ) {
				$user = get_user_by( 'email', $user_data['member_email'] );
			}
		}

		return $user;
	}


	/**
	 * Create a user from import data
	 *
	 * An email is required, then attempts to create a login name
	 * from the 'user_name' field; if not found, tries to make one
	 * from the 'member_email' field using the string piece before "@";
	 * however, if a user already exists with this name, it appends
	 * to this piece a random string as suffix.
	 *
	 * @since 1.6.0
	 * @param array $user_data Arguments to create a user, must contain at least a 'member_email' key
	 * @return false|\WP_User
	 */
	protected function create_user( $user_data ) {

		// we need at least a valid email
		if ( empty( $user_data['member_email'] ) || ! is_email( $user_data['member_email'] ) )  {
			return false;
		}

		$email    = $user_data['member_email'];
		$username = null;

		if ( ! empty( $user_data['user_name'] ) && ! get_user_by( 'login', $user_data['user_name'] ) ) {

			$username = $user_data['user_name'];
		}

		if ( ! $username ) {

			$email_name = explode( '@', $email );

			if ( ! get_user_by( 'login', $email_name[0] ) ) {
				$username = $email_name[0];
			} else {
				$username = uniqid( $email_name[0], false );
			}
		}

		$data = array(
			'user_login' => wp_slash( $username ),
			'user_pass'  => wp_generate_password(),
			'user_email' => wp_slash( $email ),
			'first_name' => ! empty( $user_data['member_first_name'] ) ? $user_data['member_first_name'] : '',
			'last_name'  => ! empty( $user_data['member_last_name'] ) ? $user_data['member_last_name'] : '',
			'role'       => 'customer',
		);

		$user_id = wp_insert_user( $data );

		return is_wp_error( $user_id ) ? false : get_user_by( 'id', $user_id );
	}


	/**
	 * Show a notice with import results
	 *
	 * @since 1.6.0
	 * @param int $total_rows Total rows in CSV file
	 * @param int $created User Memberships created
	 * @param int $merged User Memberships merged/updated
	 */
	private function show_results_notice( $total_rows = 0, $created = 0, $merged = 0 ) {

		$message_handler =  wc_memberships()->get_admin_instance()->get_message_handler();
		$rows_processed  = $created + $merged;
		$skipped_rows    = $total_rows - $rows_processed;

		if ( 0 === $total_rows ) {

			$notice_type = 'error';
			$message     = __( 'Could not find User Memberships to import from uploaded file.', 'woocommerce-memberships' );

		} else {

			/* translators: Placeholder: %s - User Memberships to import found in uploaded file */
			$message = sprintf( _n( '%s record found in file.', '%s records found in file.', $total_rows, 'woocommerce-memberships' ), $total_rows ) . '<br>';

			if ( $rows_processed > 0 ) {

				$notice_type = 'message';

				/* translators: Placeholder: %s - User Memberships processed during import from file */
				$message .= ' ' . sprintf( _n( '%s row processed for import.', '%s rows processed for import.', $rows_processed, 'woocommerce-memberships' ), $rows_processed );

				if ( $created > 0 ) {
					/* translators: Placeholder: %s - User Memberships created in import */
					$message .= ' ' . sprintf( _n( '%s new User Membership created.', '%s new User Memberships created.', $created, 'woocommerce-memberships' ), $created );
				}

				if ( $merged > 0 ) {
					/* translators: Placeholder: %s - User Memberships updated during import */
					$message .= ' ' . sprintf( _n( '%s existing User Membership updated.', '%s existing User Memberships updated.', $merged, 'woocommerce-memberships' ), $merged );
				}

				if ( $skipped_rows > 0 ) {
					/* translators: Placeholder: %s - skipped User Memberships to import from file */
					$message .= ' ' . sprintf( _n( '%s row skipped.', '%s rows skipped.', $skipped_rows, 'woocommerce-memberships' ), $skipped_rows );
				}

			} else {

				$notice_type  = 'error';
				$message     .=  __( 'However, no User Memberships were created or updated with the given options.', 'woocommerce-memberships' );
			}
		}

		$method = "add_{$notice_type}";

		if ( is_callable( array( $message_handler, $method ) ) ) {

			$message_handler->$method( $message );
		}
	}


	/**
	 * Get an error message for file upload failure
	 *
	 * @see http://php.net/manual/en/features.file-upload.errors.php
	 *
	 * @since 1.6.0
	 * @param int $error_code A PHP error code
	 * @return string
	 */
	private function get_file_upload_error( $error_code ) {

		switch ( $error_code ) {
			case 1 :
			case 2 :
				return __( 'The file uploaded exceeds the maximum file size allowed.', 'woocommerce-memberships' );
			case 3 :
				return __( 'The file was only partially uploaded. Please try again.', 'woocommerce-memberships' );
			case 4 :
				return __( 'No file was uploaded.', 'woocommerce-memberships' );
			case 6 :
				return __( 'Missing a temporary folder to store the file. Please contact your host.', 'woocommerce-memberships' );
			case 7 :
				return __( 'Failed to write file to disk. Perhaps a permissions error, please contact your host.', 'woocommerce-memberships' );
			case 8 :
				return __( 'A PHP Extension stopped the file upload. Please contact your host.', 'woocommerce-memberships' );
			default :
				return __( 'Unknown error.', 'woocommerce-memberships' );
		}
	}


}
