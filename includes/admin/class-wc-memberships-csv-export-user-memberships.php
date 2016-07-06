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
 * Export Members CSV
 *
 * @since 1.6.0
 */
class WC_Memberships_CSV_Export_User_Memberships extends WC_Memberships_Import_Export {


	/** @var array User Memberhips to export, used in bulk actions */
	public $export_ids = array();

	/** @var bool Whether to export User Memberships additional meta data */
	public $export_meta_data = false;

	/** @var bool Export dates in UTC format or adjusted by WordPress timezone */
	public $export_dates_in_utc = false;

	/** @var int Counter for number of exported User Memberships in a batch */
	public $exported = 0;

	/** @var string The CSV file name to export */
	public $file_name = '';

	/** @var array Associative array with CSV header fields */
	public $headers = array();

	/** @var resource Output stream containing CSV data */
	private $stream;


	/**
	 * Export admin page setup
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		$this->action       = 'csv_export_user_memberships';
		$this->action_label = __( 'Export', 'woocommerce-memberships' );

		// csv file output settings
		$this->delimiter_field_name = 'wc_memberships_members_csv_export_fields_delimiter';

		// csv file headers
		$this->headers = $this->get_headers();

		/**
		 * Filter the CSV export enclosure
		 *
		 * @since 1.6.0
		 * @param string $enclosure Default double quote `"`
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance Instance of the export class
		 */
		$this->enclosure = apply_filters( 'wc_memberships_csv_export_enclosure', '"', $this );

		/**
		 * Filter exporting dates in UTC
		 *
		 * @since 1.6.0
		 * @param bool $dates_in_utc Default false
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance Instance of the export class
		 */
		$this->export_dates_in_utc = apply_filters( 'wc_memberships_csv_export_user_memberships_dates_in_utc', false, $this );

		// file name default: blog_name_user_memberships_YYYY_MM_DD.csv
		$file_name = str_replace( '-', '_', sanitize_file_name( strtolower( get_bloginfo( 'name' ) . '_user_memberships_' . date_i18n( 'Y_m_d', time() ) . '.csv' ) ) );

		/**
		 * Filter the User Memberships CSV export file name
		 *
		 * @since 1.6.0
		 * @param string $file_name
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance Instance of the export class
		 */
		$this->file_name = apply_filters( 'wc_memberships_csv_export_user_memberships_file_name', $file_name, $this );

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
			return __( 'Export Members', 'woocommerce-memberships' ) . ' ' . $admin_title;
		}

		return $admin_title;
	}


	/**
	 * Set User Membership IDs to export
	 *
	 * @see WC_Memberships_Admin_Import_Export_Handler::process_bulk_export()
	 * @see WC_Memberships_CSV_Export_User_Memberships::get_user_memberships_ids()
	 *
	 * @since 1.6.0
	 * @param int[] $user_membership_ids Array of \WC_Memberships_User_Membership IDs
	 */
	public function set_export_ids( $user_membership_ids ) {
		$this->export_ids = array_map( 'absint', $user_membership_ids );
	}


	/**
	 * Get CSV file headers
	 *
	 * @since 1.6.0
	 * @return array()
	 */
	private function get_headers() {

		$headers = array(
			'user_membership_id'    => 'user_membership_id',
			'user_id'               => 'user_id',
			'user_name'             => 'user_name',
			'member_first_name'     => 'member_first_name',
			'member_last_name'      => 'member_last_name',
			'member_email'          => 'member_email',
			'membership_plan_id'    => 'membership_plan_id',
			'membership_plan'       => 'membership_plan',
			'membership_plan_slug'  => 'membership_plan_slug',
			'membership_status'     => 'membership_status',
			'product_id'            => 'product_id',
			'order_id'              => 'order_id',
			'member_since'          => 'member_since',
			'membership_expiration' => 'membership_expiration',
		);

		if ( wc_memberships()->get_integrations_instance()->is_subscriptions_active() ) {

			$headers = SV_WC_Helper::array_insert_after( $headers, 'product_id', array(
				'subscription_id' => 'subscription_id',
			) );
		}

		/**
		 * Filter the User Memberships CSV export file row headers
		 *
		 * @since 1.6.0
		 * @param array $csv_headers Associative array
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance Instance of the export class
		 */
		return (array) apply_filters( 'wc_memberships_csv_export_user_memberships_headers', $headers, $this );
	}


	/**
	 * Get Membership Plans for exporting
	 *
	 * @since 1.6.0
	 * @return array
	 */
	private function get_plans() {

		$plan_objects = wc_memberships_get_membership_plans();
		$plans        = array();

		if ( ! empty( $plan_objects ) ) {

			foreach ( $plan_objects as $plan ) {

				$plans[ $plan->get_id() ] = $plan->get_name();
			}
		}

		return $plans;
	}


	/**
	 * Get User Membership statuses for exporting
	 *
	 * @since 1.6.0
	 * @return array
	 */
	private function get_statuses() {

		$statuses_array = wc_memberships_get_user_membership_statuses();
		$statuses       = array();

		if ( ! empty( $statuses_array ) ) {

			foreach ( $statuses_array as $id => $status ) {

				if ( isset( $status['label'] ) ) {

					$statuses[ $id ] = $status['label'];
				}
			}
		}

		return $statuses;
	}


	/**
	 * Get export options input fields
	 *
	 * @since 1.6.0
	 * @return array
	 */
	protected function get_fields() {

		/**
		 * Filter CSV Export User Memberships options
		 *
		 * @since 1.6.0
		 * @para array $options Associative array
		 */
		return apply_filters( 'wc_memberships_csv_export_user_memberships_options', array(

			// section start
			array(
				'title' => __( 'Export Members', 'woocommerce-memberships' ),
				'type'  => 'title',
			),

			// select plans to export from
			array(
				'id'                => 'wc_memberships_members_csv_export_plan',
				'title'             => __( 'Plan', 'woocommerce-memberships' ),
				'desc_tip'          => __( 'Choose which plan(s) to export members from. Leave blank to export members from every plan.', 'woocommerce-memberships' ),
				'type'              => 'multiselect',
				'options'           => $this->get_plans(),
				'default'           => '',
				'class'             => 'wc-enhanced-select',
				'css'               => 'min-width: 250px',
				'custom_attributes' => array(
					'data-placeholder' => __( 'Leave blank to export members of any plan.', 'woocommerce-memberships' ),
				)
			),

			// select membership statuses to export
			array(
				'id'                => 'wc_memberships_members_csv_export_status',
				'title'             => __( 'Status', 'woocommerce-memberships' ),
				'desc_tip'          => __( 'Choose to export user memberships with specific status(es) only. Leave blank to export user memberships of any status.', 'woocommerce-memberships' ),
				'type'              => 'multiselect',
				'options'           => $this->get_statuses(),
				'default'           => '',
				'class'             => 'wc-enhanced-select',
				'css'               => 'min-width: 250px',
				'custom_attributes' => array(
					'data-placeholder' => __( 'Leave blank to export members with any status.', 'woocommerce-memberships' ),
				)
			),

			// set memberships minimum start date
			array(
				'id'    => 'wc_memberships_members_csv_export_start_date',
				'title' => __( 'Start Date', 'woocommerce-memberships' ),
				/* translators: Placeholder: %s - date format */
				'desc'  => sprintf(
					__( 'Start date of memberships to include in the exported file, in the format %s.', 'woocommerce-memberships' ) . '<br>' .
					__( 'You can optionally specify a date range, or leave one of the fields blank for open-ended ranges.', 'woocommerce-memberships' ),
					'<code>YYYY-MM-DD</code>'
				),
				'type'  => 'wc-memberships-date-range',
				'class' => 'js-user-membership-date',
			),

			// set memberships maximum end date
			array(
				'id'    => 'wc_memberships_members_csv_export_end_date',
				'title' => __( 'End Date', 'woocommerce-memberships' ),
				/* translators: Placeholder: %s - date format */
				'desc'  => sprintf(
					__( 'Expiration date of memberships to include in the exported file, in the format %s.', 'woocommerce-memberships' ) . '<br>' .
					__( 'You can optionally specify a date range, or leave one of the fields blank for open-ended ranges.', 'woocommerce-memberships' ),
					'<code>YYYY-MM-DD</code>'
				),
				'type'  => 'wc-memberships-date-range',
				'class' => 'js-user-membership-date',
			),

			// export all post meta
			array(
				'id'       => 'wc_memberships_members_export_meta_data',
				'name'     => __( 'Meta data', 'woocommerce-memberships' ),
				'type'     => 'checkbox',
				'desc'     => __( 'Include additional meta data', 'woocommerce-memberships' ),
				'desc_tip' => __( 'Add an extra column to the CSV file with all post meta of each membership in JSON format.', 'woocommerce-memberships' ),
				'default'  => 'no'
			),

			// entries are going to be separated by comma or tab?
			array(
				'id'       => $this->delimiter_field_name,
				'name'     => __( 'Separate fields by', 'woocommerce-memberships' ),
				'type'     => 'select',
				'desc_tip' => __( 'Change the delimiter based on your desired output format.', 'woocommerce-memberships' ),
				'options'  => array(
					'comma' => __( 'Comma', 'woocommerce-memberships' ),
					'tab'   => __( 'Tab space', 'woocommerce-memberships' ),
				),
			),

			// limit records of queried User Membership ids to export
			array(
				'id'                => 'wc_memberships_members_csv_export_limit',
				'name'              => __( 'Limit Records', 'woocommerce-memberships' ),
				'type'              => 'number',
				'desc'              => __( 'Limit the number of rows to be exported. Use this option when exporting very large files that are unable to complete in a single attempt.', 'woocommerce-memberships' ),
				'class'             => 'small-text',
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
			),

			// offset queried User Memberships for very large databases
			array(
				'id'                => 'wc_memberships_members_csv_export_offset',
				'name'              => __( 'Offset Records', 'woocommerce-memberships' ),
				'type'              => 'number',
				'desc'              => __( 'Set the number of records to be skipped in this export. Use this option when exporting very large files that are unable to complete in a single attempt.', 'woocommerce-memberships' ),
				'class'             => 'small-text',
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
			),

			// section end
			array( 'type' => 'sectionend' ),

		) );
	}


	/**
	 * Get User Memberships to export
	 *
	 * A wrapper for `get_posts()` that uses the export form
	 * $_POST inputs to handle query arguments
	 *
	 * @since 1.6.0
	 * @return int[] User Memberships ids or empty array if none found
	 */
	private function get_user_memberships_ids() {

		$query_args = array(
			'post_type'      => 'wc_user_membership',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => empty( $_POST['wc_memberships_members_csv_export_limit'] ) ? -1 : absint( $_POST['wc_memberships_members_csv_export_limit'] ),
			'offset'         => empty( $_POST['wc_memberships_members_csv_export_offset'] ) ? 0 : absint( $_POST['wc_memberships_members_csv_export_offset'] ),
		);

		if ( ! empty( $this->export_ids ) ) {
			$query_args['post__in'] = (array) $this->export_ids;
		}

		$start_from_date = ! empty( $_POST['wc_memberships_members_csv_export_start_date_from'] ) ? $_POST['wc_memberships_members_csv_export_start_date_from'] : false;
		$start_to_date   = ! empty( $_POST['wc_memberships_members_csv_export_start_date_to'] )   ? $_POST['wc_memberships_members_csv_export_start_date_to']   : false;
		$end_from_date   = ! empty( $_POST['wc_memberships_members_csv_export_end_date_from'] )   ? $_POST['wc_memberships_members_csv_export_end_date_from']   : false;
		$end_to_date     = ! empty( $_POST['wc_memberships_members_csv_export_end_date_to'] )     ? $_POST['wc_memberships_members_csv_export_end_date_to']     : false;

		// perhaps add meta query args for dates if there's at least one date set
		if ( $start_from_date || $start_to_date || $end_from_date || $end_to_date ) {

			$query_args['meta_query'] = array();

			// query for User Memberships created within some date
			if ( $start_from_date || $start_to_date ) {
				$query_args['meta_query'][] = $this->get_date_range_meta_query_args( '_start_date', $start_from_date, $start_to_date );
			}

			// query for User Memberships expiring within some date
			if ( $end_from_date || $end_to_date ) {
				$query_args['meta_query'][] = $this->get_date_range_meta_query_args( '_end_date', $end_from_date, $end_to_date );
			}
		}

		// query User Memberships with specific plans only
		if ( ! empty( $_POST['wc_memberships_members_csv_export_plan'] ) ) {
			$query_args['post_parent__in'] = array_map( 'intval', (array) $_POST['wc_memberships_members_csv_export_plan'] );
		}

		// query User Memberships that have specific statuses (defaults to 'any' otherwise)
		if ( ! empty( $_POST['wc_memberships_members_csv_export_status'] ) ) {
			$query_args['post_status'] = (array) $_POST['wc_memberships_members_csv_export_status'];
		}

		/**
		 * Filter CSV Export User Memberships query args
		 *
		 * @since 1.6.0
		 * @param array $query_args
		 */
		$query_args = apply_filters( 'wc_memberships_csv_export_user_memberships_query_args', $query_args );

		return get_posts( $query_args );
	}


	/**
	 * Get date range arguments for a WordPress meta query
	 *
	 * Converts user input dates into UTC to compare with DB values
	 * If at least one of the dates is set but invalid it will return empty array
	 *
	 * @since 1.6.0
	 * @param string $meta_key Meta key to look for datetime values
	 * @param string|bool $from_date Start date in YYYY-MM-DD format or false to ignore range end
	 * @param string|bool $to_date End date in YYYY-MM-DD format or false to ignore range end
	 * @return array
	 */
	protected function get_date_range_meta_query_args( $meta_key, $from_date = false, $to_date = false ) {

		$args = array();

		if ( empty( $meta_key ) || ( ! $from_date && ! $to_date ) ) {
			return $args;
		}

		$errors  = 0;
		$value   = '';
		$compare = '=';

		// set args based on range ends content
		if ( $from_date && ! $to_date ) {

			$errors += (int) ! $this->is_date( $from_date );

			if ( 0 === $errors ) {
				$value   = $this->parse_date_mysql( $this->adjust_query_date( $from_date, 'start' ) );
				$compare = '>=';
			}

		} elseif ( ! $from_date && $to_date ) {

			$errors += (int) ! $this->is_date( $to_date );

			if ( 0 === $errors ) {
				$value   = $this->parse_date_mysql( $this->adjust_query_date( $to_date, 'end' ) );
				$compare = '<=';
			}

		} else {

			$errors += (int) ! $this->is_date( $from_date );
			$errors += (int) ! $this->is_date( $to_date );

			if ( 0 === $errors ) {

				$start_date = $this->parse_date_mysql( $this->adjust_query_date( $from_date, 'start' ) );
				$end_date   = $this->parse_date_mysql( $this->adjust_query_date( $to_date, 'end' ) );
				$value      = array( $start_date, $end_date );
				$compare    = 'BETWEEN';
			}
		}

		if ( 0 === $errors ) {

			$args = array(
				'key'     => $meta_key,
				'type'    => 'DATETIME',
				'value'   => $value,
				'compare' => $compare,
			);
		}

		return $args;
	}


	/**
	 * Bump a date to the beginning or the end of a day
	 * for dates strings with unspecified time (e.g. just YYYY-MM-DD)
	 * (useful in datetime queries, when querying between dates)
	 *
	 * @since 1.6.0
	 * @param string $date A date in YYYY-MM-DD format without time
	 * @param string $edge Beginning of end of the day (start or end)
	 * @return string YYYY-MM-DD HH:MM:SS
	 */
	protected function adjust_query_date( $date, $edge = 'start' ) {

		switch ( $edge ) {
			case 'start' :
				return $date . ' 00:00:00';
			case 'end' :
				return $date . ' 23:59:59';
			default :
				return $date;
		}
	}


	/**
	 * Process input form submission to export a CSV file
	 *
	 * @since 1.6.0
	 */
	public function process_export() {

		// first get the User Memberships according to form criteria
		$user_memberships_ids = $this->get_user_memberships_ids();

		// export options
		$this->export_meta_data = isset( $_POST['wc_memberships_members_export_meta_data'] ) ? 1 === (int) $_POST['wc_memberships_members_export_meta_data'] : $this->export_meta_data;

		if ( ! empty( $user_memberships_ids ) ) {
			// try to set unlimited script timeout and generate file for download
			@set_time_limit( 0 );
			$this->download( $this->file_name, $this->get_csv( $user_memberships_ids ) );
		} else {
			// tell the user there were no User Memberships to export
			wc_memberships()->get_message_handler()->add_error( __( 'No User Memberships found matching the criteria to export.', 'woocommerce-memberships' ) );
		}
	}


	/**
	 * Downloads the CSV via the browser
	 *
	 * @since 1.6.0
	 * @param string $filename The file name
	 * @param string $csv The CSV data to download as a file
	 */
	protected function download( $filename, $csv ) {

		// set headers for download
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
		header( sprintf( 'Content-Disposition: attachment; filename="%s"', $filename ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// clear the output buffer
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
		@ini_set( 'output_handler', '' );

		// open the output buffer for writing
		$fp = fopen( 'php://output', 'w' );

		// write the generated CSV to the output buffer
		fwrite( $fp, $csv );

		// close the output buffer
		fclose( $fp );
		exit;
	}


	/**
	 * Get the CSV data
	 *
	 * @since 1.6.0
	 * @param int[] $user_membership_ids Array of \WC_Memberships_User_Membership post object ids
	 * @return string
	 */
	private function get_csv( $user_membership_ids ) {

		// open output buffer to write CSV to
		$this->stream = fopen( 'php://output', 'w' );
		ob_start();

		/**
		 * Add CSV BOM (Byte order mark)
		 *
		 * Enable adding a BOM to the exported CSV
		 *
		 * @since 1.6.0
		 * @param bool $enable_bom true to add the BOM, false otherwise (default)
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance An instance of the export class
		 */
		if ( true === apply_filters( 'wc_memberships_csv_export_enable_bom', false, $this ) ) {

			fwrite( $this->stream, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		}

		$headers = $this->headers;

		if ( true === $this->export_meta_data ) {
			$headers['user_membership_meta'] = 'user_membership_meta';
		}

		$this->write( $headers, $headers );

		$exported = 0;

		foreach ( $user_membership_ids as $user_membership_id ) {

			$user_membership = new WC_Memberships_User_Membership( $user_membership_id );

			if ( $user_membership->get_id() > 0 ) {

				/**
				 * Filter run before exporting a User Membership as a CSV row
				 *
				 * @since 1.6.0
				 * @param \WC_Memberships_User_Membership $user_membership User Membership being exported
				 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance The instance of the export class
				 */
				$user_membership = apply_filters( 'wc_memberships_before_csv_export_user_membership', $user_membership, $this );

				$row = $this->get_user_membership_csv_row( $user_membership );

				if ( ! empty ( $row ) ) {

					$this->write( $headers, $row );

					$exported++;
				}

				/**
				 * Action run after exporting a User Membership as a CSV row
				 *
				 * @since 1.6.0
				 * @param \WC_Memberships_User_Membership $user_membership User Membership being exported
				 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance The instance of the export class
				 */
				 do_action( 'wc_memberships_after_csv_export_user_membership', $user_membership, $this );
			}
		}

		$csv = ob_get_clean();

		fclose( $this->stream );

		$this->exported = $exported;

		return $csv;
	}


	/**
	 * Get User Membership CSV row data
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership $user_membership User Membership object
	 * @return array
	 */
	private function get_user_membership_csv_row( $user_membership ) {

		$user_membership_id = $user_membership->get_id();
		$member_id          = $user_membership->get_user_id();
		$user               = get_user_by( 'id', $member_id );
		$membership_plan    = $user_membership->get_plan();

		$row     = array();
		$columns = array_keys( $this->headers );

		if ( ! empty( $columns ) ) {

			if ( true === $this->export_meta_data ) {
				$columns[] = 'user_membership_meta';
			}

			foreach ( $columns as $column_name ) {

				switch ( $column_name ) {

					case 'user_membership_id' :
						$value = $user_membership_id;
					break;

					case 'user_id' :
						$value = $member_id;
					break;

					case 'user_name' :
						$value = $user instanceof WP_User ? $user->user_login : '';
					break;

					case 'member_first_name' :
						$value = $user instanceof WP_User ? $user->first_name : '';
					break;

					case 'member_last_name' :
						$value = $user instanceof WP_User ? $user->last_name : '';
					break;

					case 'member_email' :
						$value = $user instanceof WP_User ? $user->user_email : '';
					break;

					case 'membership_plan_id' :
						$value = $membership_plan->get_id();
					break;

					case 'membership_plan' :
						$value = $membership_plan->get_name();
					break;

					case 'membership_plan_slug' :
						$value = $membership_plan->get_slug();
						break;

					case 'membership_status' :
						$value = $user_membership->get_status();
					break;

					case 'product_id' :
						$value = $user_membership->get_product_id();
					break;

					case 'subscription_id' :

						$subscriptions = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();

						$value = null !== $subscriptions ? $subscriptions->get_user_membership_subscription_id( $user_membership_id ) : '';

					break;

					case 'order_id' :
						$value = $user_membership->get_order_id();
					break;

					case 'member_since' :
						$value = true === $this->export_dates_in_utc ? $user_membership->get_start_date() : $user_membership->get_local_start_date();
					break;

					case 'membership_expiration' :
						$value = true === $this->export_dates_in_utc ? $user_membership->get_end_date() : $user_membership->get_local_end_date();
					break;

					case 'user_membership_meta' :

						$meta  = get_post_meta( $user_membership_id );
						$value = is_array( $meta ) ? wc_memberships_json_encode( $meta ) : '';

					break;

					default :

						/**
						 * Filter a User Membership CSV data custom column
						 *
						 * @since 1.6.0
						 *
						 * @param string $value The value that should be returned for this column, default empty string
						 * @param string $key The matching key of this column
						 * @param \WC_Memberships_User_Membership $user_membership User Membership object
						 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance An instance of the export class
						 */
						 $value = apply_filters( "wc_memberships_csv_export_user_memberships_{$column_name}_column", '', $column_name, $user_membership, $this );

					break;

				}

				$row[ $column_name ] = $value;
			}
		}

		/**
		 * Filter a User Membership CSV row data
		 *
		 * @since 1.6.0
		 * @param array $row User Membership data in associative array format for CSV output
		 * @param \WC_Memberships_User_Membership $user_membership User Membership object
		 * @param \WC_Memberships_CSV_Export_User_Memberships $export_instance An instance of the export class
		 */
		return apply_filters( 'wc_memberships_csv_export_user_memberships_row', $row, $user_membership, $this );
	}


	/**
	 * Write the given row to the CSV
	 *
	 * @since 1.6.0
	 * @param array $headers
	 * @param array $row
	 */
	private function write( $headers, $row ) {

		$data = array();

		foreach ( $headers as $header_key ) {

			if ( ! isset( $row[ $header_key ] ) ) {
				$row[ $header_key ] = '';
			}

			$value = '';

			// strict string comparison, as values like '0' are valid
			if ( '' !== $row[ $header_key ]  ) {
				$value = $row[ $header_key ];
			}

			// escape spreadsheet sensitive characters with a single quote
			// to prevent CSV injections, by prepending a single quote `'`
			// see: http://www.contextis.com/resources/blog/comma-separated-vulnerabilities/
			$first_char = isset( $value[0] ) ? $value[0] : '';

			if ( in_array( $first_char, array( '=', '+', '-', '@' ) ) ) {
				$value = "'" . $value;
			}

			$data[] = $value;
		}

		fputcsv( $this->stream, $data, $this->get_fields_delimiter(), $this->enclosure );
	}


}
