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
 * Abstract class for Import / Export pages
 *
 * @since 1.6.0
 */
abstract class WC_Memberships_Import_Export {


	/** @var string Action performed */
	public $action = '';

	/** @var string Action label */
	public $action_label = '';

	/** @var string CSV fields delimiter option field name */
	protected $delimiter_field_name = '';

	/** @var string The enclosure used to process a CSV file */
	protected $enclosure = '';


	/**
	 * Render admin page
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// add admin page content
		add_action( 'wc_memberships_render_import_export_page_section', array( $this, 'render_section' ) );

		// add csv file input field handler
		add_action( 'woocommerce_admin_field_wc-memberships-import-file', array( $this, 'render_file_upload_field' ) );
		// add date range input field handler
		add_action( 'woocommerce_admin_field_wc-memberships-date-range',  array( $this, 'render_date_range_field' ) );

		// remove WooCommerce footer and use default WordPress instead
		add_action( 'woocommerce_display_admin_footer_text', '__return_false' );

		// set the admin page title
		add_filter( 'admin_title', array( $this, 'set_admin_page_title' ), 10, 2 );
	}


	/**
	 * Set the admin page title
	 *
	 * @since 1.6.2
	 * @param string $admin_title The page title, with extra context added
	 * @param string $title The original page title
	 * @return string
	 */
	abstract public function set_admin_page_title( $admin_title, $title );


	/**
	 * Conditionally output HTML if the section is displayed
	 *
	 * @since 1.6.0
	 * @param string $current_section
	 */
	public function render_section( $current_section ) {

		if ( $this->action === $current_section ) {

			$this->render_content();
		}
	}


	/**
	 * Output HTML
	 *
	 * @since 1.6.0
	 */
	protected function render_content() {

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php woocommerce_admin_fields( $this->get_fields() ); ?>
			<input type="hidden" name="action" value="<?php echo 'wc_memberships_' . esc_attr( $this->action ); ?>">
			<?php wp_nonce_field( 'wc_memberships_' . $this->action ); ?>
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_html( $this->action_label ); ?>">
			</p>
		</form>
		<?php
	}


	/**
	 * Output a file input field
	 *
	 * @since 1.6.0
	 * @param array $field Field settings
	 */
	public function render_file_upload_field( $field ) {

		$field = wp_parse_args( $field, array(
			'id'       => '',
			'title'    => __( 'Choose a file from your computer', 'woocommerce-memberships' ),
			'desc'     => '',
			'desc_tip' => '',
			'type'     => 'wc-memberships-import-file',
			'class'    => '',
			'css'      => '',
			'value'    => '',
		) );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
			</th>
			<td class="forminp forminp-<?php echo sanitize_html_class( $field['type'] ) ?>">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>" />
				<input
					name="<?php echo esc_attr( $field['id'] ); ?>"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					type="file"
					style="<?php echo esc_attr( $field['css'] ); ?>"
					value="<?php echo esc_attr( $field['value'] ); ?>"
					class="<?php echo esc_attr( $field['class'] ); ?>"
				/><br><span class="description"><?php echo $field['desc_tip']; ?></span>
			</td>
		</tr>
		<?php
	}


	/**
	 * Output a date range input field
	 *
	 * @since 1.6.0
	 * @param array $field Field settings
	 */
	public function render_date_range_field( $field ) {

		$field = wp_parse_args( $field, array(
			'id'         => '',
			'title'      => __( 'Date Range', 'woocommerce-memberships' ),
			'desc'       => '',
			'desc_tip'   => '',
			'type'       => 'wc-memberships-date-range',
			'class'      => '',
			'css'        => '',
		) );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for=""><?php echo esc_html( $field['title'] ); ?></label>
			</th>
			<td class="forminp forminp-<?php echo sanitize_html_class( $field['type'] ) ?>">
				<span class="label">
					<?php esc_html_e( 'From:', 'woocommerce-memberships' ); ?>
					<input
						name="<?php echo esc_attr( $field['id'] ) . '_from'; ?>"
						id="<?php echo esc_attr( $field['id'] ) . '_from'; ?>"
						type="text"
						style="<?php echo esc_attr( $field['css'] ); ?>"
						value=""
						class="<?php echo esc_attr( $field['class'] ); ?>"
					/>
				</span>
				&nbsp;&nbsp;
				<span class="label">
					<?php esc_html_e( 'To:', 'woocommerce-memberships' ); ?>
					<input
						name="<?php echo esc_attr( $field['id'] . '_to' ); ?>"
						id="<?php echo esc_attr( $field['id'] . '_to' ); ?>"
						type="text"
						style="<?php echo esc_attr( $field['css'] ); ?>"
						value=""
						class="<?php echo esc_attr( $field['class'] ); ?>"
					/>
				</span>
				<br><span class="description"><?php echo $field['desc']; ?></span>
			</td>
		</tr>
		<?php
	}


	/**
	 * Get settings configuration for input fields to be displayed
	 *
	 * @since 1.6.0
	 * @return array
	 */
	abstract protected function get_fields();


	/**
	 * Get fields delimiter for CSV import or export file
	 *
	 * @since 1.6.0
	 * @return string Tab space or comma (default)
	 */
	protected function get_fields_delimiter() {

		// get the delimiter from form submission, defaults to comma otherwise
		$delimiter = ! empty( $this->delimiter_field_name ) && isset( $_POST[ $this->delimiter_field_name ] ) ? $_POST[ $this->delimiter_field_name ] : 'comma';

		switch ( $delimiter ) {
			case 'tab' :
				return "\t";
			case 'comma' :
			default :
				return ',';
		}
	}


	/**
	 * Check if a string is a valid User Membership status
	 *
	 * @since 1.6.0
	 * @param string $status Perhaps a User Membership status
	 * @return bool
	 */
	protected function is_status( $status ) {

		if ( ! empty( $status ) && $statuses = wc_memberships_get_user_membership_statuses() ) {

			// maybe add a 'wcm-' prefix
			$status = SV_WC_Helper::str_starts_with( $status, 'wcm-' ) ? $status : 'wcm-' . $status;

			return array_key_exists( $status, $statuses );
		}

		return false;
	}


	/**
	 * Loose check if a date is valid
	 *
	 * @since 1.6.0
	 * @param string|int $date Date as timestamp or string format
	 * @return bool
	 */
	protected function is_date( $date ) {

		if ( empty( $date ) ) {
			return false;
		}

		// if it's a timestamp, must be greater than 0
		if ( is_numeric( $date ) ) {
			return (int) $date > 0;
		}

		// if it's not a timestamp, must be a string
		if ( is_string( $date ) ) {
			return (int) strtotime( $date ) > 0;
		}

		return false;
	}


	/**
	 * Check if a timezone is a valid timezone string
	 *
	 * @since 1.6.0
	 * @param string $timezone
	 * @return bool
	 */
	protected function is_timezone( $timezone ) {
		return in_array( $timezone, timezone_identifiers_list(), true );
	}


	/**
	 * Ensure a date is returned in mysql format
	 *
	 * @since 1.6.0
	 * @param string|int $date Date as timestamp or string format
	 * @param string $timezone Timezone to use to convert the date from, defaults to site timezone
	 * @return string Datetime string in UTC
	 */
	protected function parse_date_mysql( $date, $timezone = '' ) {

		// fallback to site timezone
		if ( empty( $timezone ) || ! $this->is_timezone( $timezone ) ) {
			$timezone = wc_timezone_string();
		}

		// no need to adjust date, it's already in UTC
		if ( 'UTC' === $timezone ) {

			$utc_date = is_numeric( $date ) ? date( 'Y-m-d H:i:s', $date ) : date( 'Y-m-d H:i:s', strtotime( $date ) );
			$utc_date = new DateTime( $utc_date, new DateTimeZone( $timezone ) );

			return date( 'Y-m-d H:i:s', $utc_date->format( 'U' ) );
		}

		return wc_memberships_adjust_date_by_timezone( $date, 'mysql', $timezone );
	}


}
