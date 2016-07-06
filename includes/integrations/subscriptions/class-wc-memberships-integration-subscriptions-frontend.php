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
 * Frontend integration class for WooCommerce Subscriptions
 *
 * @since 1.6.0
 */
class WC_Memberships_Integration_Subscriptions_Frontend {


	/**
	 * Add frontend hooks
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		// Frontend UI hooks (2.0 & backwards compatible, Memberships < 1.4)
		// TODO when dropping support for Memberships templates < 1.4.0 these can be removed {FN 2016-04-26}
		add_action( 'wc_memberships_my_memberships_column_headers',     array( $this, 'output_subscription_column_headers' ) );
		add_action( 'wc_memberships_my_memberships_columns',            array( $this, 'output_subscription_columns' ), 20 );
		add_action( 'wc_memberships_my_account_my_memberships_actions', array( $this, 'my_membership_actions' ), 10, 2 );

		// Frontend UI hooks (Memberships 1.4+ & Subscriptions 2.0)
		add_filter( 'wc_memberships_members_area_my_memberships_actions', array( $this, 'my_membership_actions' ), 10, 2 );
		// TODO when dropping support for Memberships templates < 1.4.0 these can be uncommented {FN 2016-04-26}
		// add_filter( 'wc_memberships_my_memberships_column_names',                   array( $this, 'my_memberships_subscriptions_columns' ), 20 );
		// add_action( 'wc_memberships_my_memberships_column_membership-next-bill-on', array( $this, 'output_subscription_columns' ), 20 );
	}


	/**
	 * Remove cancel action from memberships tied to a subscription
	 *
	 * @since 1.6.0
	 * @param array $actions
	 * @param \WC_Memberships_User_Membership $user_membership Post object
	 * @return array
	 */
	public function my_membership_actions( $actions, WC_Memberships_User_Membership $user_membership ) {

		$integration = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();

		if ( $integration->is_membership_linked_to_subscription( $user_membership ) ) {

			// a Memberships tied to a subscription can only be cancelled
			// by cancelling the associated Subscription
			unset( $actions['cancel'] );

			$subscription = $integration->get_subscription_from_membership( $user_membership->get_id() );
			$is_renewable = $integration->is_subscription_linked_to_membership_renewable( $subscription, $user_membership );

			if ( ! $is_renewable ) {
				unset( $actions['renew'] );
			}
		}

		return $actions;
	}


	/**
	 * Display Subscriptions column headers
	 * in My Memberships section for Memberships < 1.4.0
	 *
	 * @deprecated 
	 * @since 1.6.0
	 */
	public function output_subscription_column_headers() {
		?>
		<th class="membership-next-bill-on">
			<span class="nobr"><?php esc_html_e( 'Next Bill On', 'woocommerce-memberships' ); ?></span>
		</th>
		<?php
	}


	/**
	 * Add subscription column headers in My Memberships
	 * on My Account page for Memberships 1.4.0+
	 *
	 * @since 1.6.0
	 * @param array $columns
	 * @return array
	 */
	public function my_memberships_subscriptions_columns( $columns ) {

		// insert before the 'Actions' column
		array_splice( $columns, -1, 0, array(
			'membership-next-bill-on' => __( 'Next Bill On', 'woocommerce-memberships' ),
		) );

		return $columns;
	}


	/**
	 * Display subscription columns in My Memberships section
	 *
	 * @since 1.6.0
	 * @param \WC_Memberships_User_Membership $user_membership Post object
	 */
	public function output_subscription_columns( WC_Memberships_User_Membership $user_membership ) {

		$integration  = wc_memberships()->get_integrations_instance()->get_subscriptions_instance();
		$subscription = $integration->get_subscription_from_membership( $user_membership->get_id() );

		if ( $subscription && in_array( $user_membership->get_status(), array( 'active', 'free_trial' ), true ) ) {
			if ( $integration->is_subscriptions_gte_2_0() ) {
				$next_payment = $subscription->get_time( 'next_payment' );
			} else {
				$subscription_key = $integration->get_user_membership_subscription_key( $user_membership->get_id() );
				$next_payment     = WC_Subscriptions_Manager::get_next_payment_date( $subscription_key, $user_membership->get_user_id(), 'timestamp' );
			}
		}

		?>
		<td class="membership-membership-next-bill-on" data-title="<?php esc_attr_e( 'Next Bill On', 'woocommerce-memberships' ); ?>">
			<?php if ( $subscription && ! empty( $next_payment ) ) : ?>
				<?php echo date_i18n( wc_date_format(), $next_payment ) ?>
			<?php else : ?>
				<?php esc_html_e( 'N/A', 'woocommerce-memberships' ); ?>
			<?php endif; ?>
		</td>
		<?php
	}

}
