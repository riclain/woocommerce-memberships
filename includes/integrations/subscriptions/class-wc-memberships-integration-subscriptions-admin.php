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

defined( 'ABSPATH' ) or exit;

/**
 * Admin integration class for WooCommerce Subscriptions
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Admin {


	/**
	 * Add admin hooks
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		add_action( 'wc_memberships_after_user_membership_billing_details',    array( $this, 'output_subscription_details' ) );
		add_action( 'wc_membership_plan_options_membership_plan_data_general', array( $this, 'output_subscription_options' ) );
		add_action( 'wc_memberships_restriction_rule_access_schedule_field',   array( $this, 'output_exclude_trial_option' ), 10, 2 );
		add_action( 'wc_memberships_user_membership_actions',                  array( $this, 'user_membership_meta_box_actions' ), 1, 2 );
		add_filter( 'post_row_actions',                                        array( $this, 'user_membership_post_row_actions' ), 20, 2 );
	}


	/**
	 * Display subscription details in edit membership screen
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership $user_membership Post object
	 */
	public function output_subscription_details( WC_Memberships_User_Membership $user_membership ) {

		$integration  = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
		$subscription = $integration->get_subscription_from_membership( $user_membership->get_id() );

		if ( ! $subscription ) {
			return;
		}

		$subscription_key = '';

		if ( ! $integration->is_subscriptions_gte_2_0() ) {
			$subscription_key = $integration->get_user_membership_subscription_key( $user_membership->get_id() );
		}

		if ( in_array( $user_membership->get_status(), array( 'free_trial', 'active' ), true ) ) {
			if ( $integration->is_subscriptions_gte_2_0() ) {
				$next_payment = $subscription->get_time( 'next_payment' );
			} else {
				$next_payment = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_membership->get_user_id(), 'timestamp' );
			}
		} else {
			$next_payment = null;
		}

		if ( $integration->is_subscriptions_gte_2_0() ) {
			$subscription_link      = get_edit_post_link( $subscription->id );
			$subscription_link_text = $subscription->id;
			$subscription_expires   = $subscription->get_date_to_display( 'end' );
		} else {
			$subscription_link      = esc_url( admin_url( 'admin.php?page=subscriptions&s=' . $subscription['order_id'] ) );
			$subscription_link_text = $subscription_key;
			// note: subs 1.5.x doesn't account for the site timezone
			$subscription_expires   = $subscription['expiry_date']
				? date_i18n( wc_date_format(), strtotime( $subscription['expiry_date'] ) )
				: __( 'Subscription not yet ended', 'woocommerce-memberships' );
		}

		?>
		<table>
			<tr>
				<td><?php esc_html_e( 'Subscription:', 'woocommerce-memberships' ); ?></td>
				<td><a href="<?php echo $subscription_link ?>"><?php echo $subscription_link_text; ?></a></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Next Bill On:', 'woocommerce-memberships' ); ?></td>
				<td><?php echo $next_payment ? date_i18n( wc_date_format(), $next_payment ) : esc_html__( 'N/A', 'woocommerce-memberships' ); ?></td>
			</tr>
		</table>
		<?php

		$plan_id = $user_membership->get_plan_id();

		if ( ! $plan_id || ! $integration->plan_grants_access_while_subscription_active( $plan_id ) ) {
			return;
		}

		// replace the expiration date input
		wc_enqueue_js( '
			$( "._end_date_field" ).find( ".js-user-membership-date, .ui-datepicker-trigger, .description" ).hide();
			$( "._end_date_field" ).append( "<span>' . esc_html( $subscription_expires ) . '</span>" );
		' );
	}


	/**
	 * Display subscriptions options and JS in the membership plan edit screen
	 *
	 * @since 1.6.0
	 */
	public function output_subscription_options() {
		global $post;

		$integration      = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
		$has_subscription = $integration->has_membership_plan_subscription( $post->ID );

		?>

		<?php if ( $integration->plan_grants_access_while_subscription_active( $post->ID ) ) : ?>

			<p class="subscription-access-notice <?php if ( ! $has_subscription ) : ?>hide<?php endif; ?> js-show-if-has-subscription">
				<span class="description"><?php esc_html_e( 'Membership will be active while the purchased subscription is active.', 'woocommerce-memberships' ); ?></span>
				<?php echo SV_WC_Plugin_Compatibility::wc_help_tip( __( 'If membership access is granted via the purchase of a subscription, then membership length will be automatically equal to the length of the subscription, regardless of the membership length setting above.', 'woocommerce-memberships' ) ); ?>
			</p>

			<style type="text/css">
				.subscription-access-notice .description {
					margin-left: 150px;
				}
			</style>

		<?php endif; ?>

		<?php

		// check if a membership plan has subscription(s):
		// if the current membership plan has at least one subscription product
		// that grants access, enable the subscription-specific controls
		wc_enqueue_js('
			var checkIfPlanHasSubscription = function() {

				var product_ids = $( "#_product_ids" ).val() || [];
				    product_ids = $.isArray( product_ids ) ? product_ids : product_ids.split( "," );

				$.get( wc_memberships_admin.ajax_url, {
					action:      "wc_memberships_membership_plan_has_subscription_product",
					security:    "' . wp_create_nonce( "check-subscriptions" ) . '",
					product_ids: product_ids,
				}, function (subscription_products) {

					var action = subscription_products && subscription_products.length ? "removeClass" : "addClass";
					
					$( ".js-show-if-has-subscription")[ action ]( "hide" );

					if ( subscription_products && subscription_products.length === product_ids.length ) {
						$( "#_access_length_period" ).closest( ".form-field" ).hide();
					} else {
						$( "#_access_length_period" ).closest( ".form-field" ).show();
					}

				} );
			}

			checkIfPlanHasSubscription();

			// purely cosmetic improvement
			$( ".subscription-access-notice" ).appendTo( $( "#_access_length_period" ).closest( ".options_group" ) );

			$( "#_product_ids" ).on( "change", function() {
				checkIfPlanHasSubscription();
			} );
		');
	}


	/**
	 * Display subscriptions options for a restriction rule
	 *
	 * This method will be called both in the membership plan screen
	 * as well as on any individual product screens.
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_Membership_Plan_Rule $rule Rule object
	 * @param string $index
	 */
	public function output_exclude_trial_option( $rule, $index ) {

		$integration      = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
		$has_subscription = $rule->get_membership_plan_id() ? $integration->has_membership_plan_subscription( $rule->get_membership_plan_id() ): false;
		$type             = $rule->get_rule_type();

		?>
		<span class="rule-control-group rule-control-group-access-schedule-trial <?php if ( ! $has_subscription ) : ?>hide<?php endif; ?> js-show-if-has-subscription">

			<input type="checkbox"
				   name="_<?php echo esc_attr( $type ); ?>_rules[<?php echo $index; ?>][access_schedule_exclude_trial]"
				   id="_<?php echo esc_attr( $type ); ?>_rules_<?php echo $index; ?>_access_schedule_exclude_trial"
				   value="yes" <?php checked( $rule->get_access_schedule_exclude_trial(), 'yes' ); ?>
				   class="access_schedule-exclude-trial"
				   <?php if ( ! $rule->current_user_can_edit() ) : ?>disabled<?php endif; ?> />

			<label for="_<?php echo esc_attr( $type ); ?>_rules_<?php echo $index; ?>_access_schedule_exclude_trial" class="label-checkbox">
				<?php esc_html_e( 'Start after trial', 'woocommerce-memberships' ); ?>
			</label>

		</span>
		<?php
	}


	/**
	 * User membership admin post row actions
	 *
	 * Filters the post row actions in the user memberships edit screen
	 *
	 * @since 1.6.0
	 * @param array $actions
	 * @param \WP_Post $post \WC_Memberships_User_Membership post object
	 * @return array
	 */
	public function user_membership_post_row_actions( $actions, $post ) {

		if ( current_user_can( 'delete_post', $post ) ) {

			$integration     = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
			$user_membership = wc_memberships_get_user_membership( $post );

			if ( $integration->is_membership_linked_to_subscription( $user_membership ) ) {

				$subscription = $integration->get_subscription_from_membership( $user_membership->get_id() );

				if ( $subscription instanceof WC_Subscription ) {

					$actions['delete-with-subscription'] = '<a class="delete-membership-and-subscription" title="' . esc_attr__( 'Delete this membership permanently and the subscription associated with it', 'woocommerce-memberships' ) . '" href="#" data-user-membership-id="' . esc_attr( $user_membership->get_id() ) . '" data-subscription-id="' . esc_attr( $subscription->id ) . '">' . esc_html__( 'Delete with subscription', 'woocommerce-memberships' ) . '</a>';
				}
			}
		}

		return $actions;
	}


	/**
	 * User membership meta box actions
	 *
	 * Filters the user membership meta box actions in admin
	 *
	 * @since 1.6.0
	 * @param array $actions
	 * @param int $user_membership_id \WC_Membership_User_Membership post id
	 * @return array
	 */
	public function user_membership_meta_box_actions( $actions, $user_membership_id ) {

		if ( current_user_can( 'delete_post', $user_membership_id ) ) {

			$integration  = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
			$subscription = $integration->get_subscription_from_membership( $user_membership_id );

			if ( $subscription instanceof WC_Subscription ) {

				$actions = array_merge( array(
					'delete-with-subscription' => array(
						'class'             => 'submitdelete delete-membership-and-subscription',
						'link'              => '#',
						'text'              => __( 'Delete User Membership with Subscription', 'woocommerce-memberships' ),
						'custom_attributes' => array(
							'data-user-membership-id' => $user_membership_id,
							'data-subscription-id'    => $subscription->id,
							'data-tip'                => __( 'Delete this membership permanently and the subscription associated with it', 'woocommerce-memberships' ),
						),
					),
				), $actions );
			}
		}

		return $actions;
	}


}
