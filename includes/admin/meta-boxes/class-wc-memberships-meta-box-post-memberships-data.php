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
 * Memberships Data Meta Box for all supported post types
 *
 * @since 1.0.0
 */
class WC_Memberships_Meta_Box_Post_Memberships_Data extends WC_Memberships_Meta_Box {


	/** @var string meta box id **/
	protected $id = 'wc-memberships-post-memberships-data';


	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->screens = array_keys( wc_memberships()->admin->get_valid_post_types_for_content_restriction() );

		parent::__construct();
	}


	/**
	 * Get the meta box title
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_title() {
		return __( 'Memberships', 'woocommerce-memberships' );
	}


	/**
	 * Display the restrictions meta box
	 *
	 * @param WP_Post $post
	 * @since 1.0.0
	 */
	public function output( WP_Post $post ) {

		// Prepare membership plan options
		$membership_plan_options = array();
		$membership_plans = wc_memberships_get_membership_plans( array(
			'post_status' => array( 'publish', 'private', 'future', 'draft', 'pending', 'trash' )
		) );

		if ( ! empty( $membership_plans ) ) {

			foreach ( $membership_plans as $membership_plan ) {

				$state = '';

				if ( 'publish' != $membership_plan->post->post_status ) {
					$state = ' ' . __( '(inactive)', 'woocommerce-memberships' );
				}

				$membership_plan_options[ $membership_plan->get_id() ] = $membership_plan->get_name() . $state;
			}
		}

		// Prepare period options
		$access_schedule_period_toggler_options = array(
			'immediate' => __( 'immediately', 'woocommerce-memberships' ),
			'specific'  => __( 'specify a time', 'woocommerce-memberships' ),
		);

		$period_options = array(
			'days'      => __( 'day(s)', 'woocommerce-memberships' ),
			'weeks'     => __( 'week(s)', 'woocommerce-memberships' ),
			'months'    => __( 'month(s)', 'woocommerce-memberships' ),
			'years'     => __( 'year(s)', 'woocommerce-memberships' ),
		);

		// Get applied restriction rules
		$content_restriction_rules = wc_memberships()->rules->get_rules( array(
			'rule_type'         => 'content_restriction',
			'object_id'         => $post->ID,
			'content_type'      => 'post_type',
			'content_type_name' => $post->post_type,
			'exclude_inherited' => false,
			'plan_status'       => 'any',
		) );

		// Add empty option to create a HTML template for new rules
		$membership_plan_ids = array_keys( $membership_plan_options );
		$content_restriction_rules['__INDEX__'] = new WC_Memberships_Membership_Plan_Rule( array(
			'rule_type'          => 'content_restriction',
			'object_ids'         => array( $post->ID ),
			'id'                 => '',
			'membership_plan_id' => array_shift( $membership_plan_ids ),
			'access_schedule'    => 'immediate',
			'access_type'        => '',
		) );

		?>
		<h4><?php esc_html_e( 'Content Restriction' ); ?></h4>

		<?php woocommerce_wp_checkbox( array(
			'id'          => '_wc_memberships_force_public',
			'class'       => 'js-toggle-rules',
			'label'       => __( 'Disable restrictions', 'woocommerce-memberships' ),
			'description' => __( 'Check this box if you want to force the content to be public regardless of any restriction rules that may apply now or in the future.', 'woocommerce-memberships' ),
		) ); ?>

		<div class="js-restrictions <?php if ( get_post_meta( $post->ID, '_wc_memberships_force_public', true ) == 'yes' ) : ?>hide<?php endif; ?>">

			<?php require( wc_memberships()->get_plugin_path() . '/includes/admin/meta-boxes/views/html-content-restriction-rules.php' ); ?>

			<?php if ( ! empty( $membership_plans ) ) : ?>
				<p>
					<em><?php esc_html_e( 'Need to add or edit a plan?', 'woocommerce-memberships' ); ?></em> <a target="_blank" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wc_membership_plan' ) ); ?>"><?php esc_html_e( 'Manage Membership Plans', 'woocommerce-memberships' ); ?></a>
				</p>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Custom Restriction Message' ); ?></h4>

			<?php woocommerce_wp_checkbox( array(
				'id'          => '_wc_memberships_use_custom_content_restricted_message',
				'class'       => 'js-toggle-custom-message',
				'label'       => __( 'Use custom message', 'woocommerce-memberships' ),
				'description' => __( 'Check this box if you want to customize the content restricted message for this content.', 'woocommerce-memberships' ),
			) ); ?>

			<div class="js-custom-message-editor-container <?php if ( get_post_meta( $post->ID, '_wc_memberships_use_custom_content_restricted_message', true ) !== 'yes' ) : ?>hide<?php endif; ?>">
				<?php $message = get_post_meta( $post->ID, '_wc_memberships_content_restricted_message', true ); ?>
				<p>
					<?php
						printf( /* translators: %1$s and %2$s placeholders are meant for {products} and {login_url} merge tags */
							__( '<code>%1$s</code> automatically inserts the product(s) needed to gain access. <code>%2$s</code> inserts the URL to my account page. HTML is allowed.', 'woocommerce-memberships' ),
							'{products}',
							'{login_url}'
						);
					?>
				</p>
				<?php
					wp_editor( $message, '_wc_memberships_content_restricted_message', array(
						'textarea_rows' => 5,
						'teeny'         => true,
					) );
				?>
			</div>

		</div>

		<?php
	}


	/**
	 * Process and save restriction rules
	 *
	 * @since 1.0.0
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function update_data( $post_id, WP_Post $post ) {

		// Update restriction rules
		wc_memberships()->admin->update_rules( $post_id, array( 'content_restriction' ), 'post' );
		wc_memberships()->admin->update_custom_message( $post_id, array( 'content_restricted' ) );

		update_post_meta( $post_id, '_wc_memberships_force_public', isset( $_POST[ '_wc_memberships_force_public' ] ) ? 'yes' : 'no' );
	}


}
