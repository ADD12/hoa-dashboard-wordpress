<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Defines three custom roles on top of WP's default roles:
 *  - hoa_member            : standard homeowner
 *  - hoa_board_member      : elevated view, threshold-setting, escalation, review visibility
 *  - hoa_property_manager  : separate, narrower "edit" privileges, all writes audited
 *
 * Administrators (site admins) retain hoa_admin capability automatically.
 */
class HOA_Dash_Roles {

	public static function register_roles() {

		add_role( 'hoa_member', 'HOA Member', array(
			'read'                    => true,
			'hoa_view_dashboard'      => true,
			'hoa_view_own_dues'       => true,
			'hoa_pay_dues'            => true,
			'hoa_submit_ticket'       => true,
			'hoa_view_own_tickets'    => true,
			'hoa_view_calendar'       => true,
			'hoa_view_reserves'       => true,
			'hoa_submit_pm_review'    => true,
		) );

		add_role( 'hoa_board_member', 'HOA Board Member', array(
			'read'                      => true,
			'hoa_view_dashboard'        => true,
			'hoa_view_own_dues'         => true,
			'hoa_pay_dues'              => true,
			'hoa_submit_ticket'         => true,
			'hoa_view_own_tickets'      => true,
			'hoa_view_calendar'         => true,
			'hoa_view_reserves'         => true,
			'hoa_submit_pm_review'      => true,
			'hoa_view_board_tools'      => true,
			'hoa_set_thresholds'        => true,
			'hoa_manage_reserves'       => true,
			'hoa_manage_calendar'       => true,
			'hoa_view_all_tickets'      => true,
			'hoa_escalate_to_pm'        => true,
			'hoa_view_pm_audit_log'     => true,
			'hoa_view_pm_reviews'       => true,
			'hoa_manage_newsletter'     => true,
			'hoa_manage_dues'           => true,
		) );

		add_role( 'hoa_property_manager', 'Property Manager', array(
			'read'                      => true,
			'hoa_view_dashboard'        => true,
			'hoa_property_manager'      => true,
			'hoa_edit_reserves_pm'      => true, // edits logged to pm_audit_log
			'hoa_edit_calendar_pm'      => true,
			'hoa_respond_tickets'       => true,
			'hoa_view_all_tickets'      => true,
			'hoa_view_escalations'      => true,
			'hoa_draft_newsletter'      => true,
		) );

		// Ensure site administrators can access everything via a master cap.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$all_caps = array(
				'hoa_view_dashboard','hoa_view_own_dues','hoa_pay_dues','hoa_submit_ticket',
				'hoa_view_own_tickets','hoa_view_calendar','hoa_view_reserves','hoa_submit_pm_review',
				'hoa_view_board_tools','hoa_set_thresholds','hoa_manage_reserves','hoa_manage_calendar',
				'hoa_view_all_tickets','hoa_escalate_to_pm','hoa_view_pm_audit_log','hoa_view_pm_reviews',
				'hoa_manage_newsletter','hoa_manage_dues','hoa_admin',
			);
			foreach ( $all_caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Idempotent safety net: if plugin was updated and roles/caps drifted,
	 * re-register on init only once per version.
	 */
	public static function maybe_register_roles() {
		if ( get_option( 'hoa_dash_roles_version' ) !== HOA_DASH_VERSION ) {
			self::register_roles();
			update_option( 'hoa_dash_roles_version', HOA_DASH_VERSION );
		}
	}

	public static function unregister_roles() {
		remove_role( 'hoa_member' );
		remove_role( 'hoa_board_member' );
		remove_role( 'hoa_property_manager' );
	}
}
