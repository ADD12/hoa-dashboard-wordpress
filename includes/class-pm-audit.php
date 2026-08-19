<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central audit logger. Any module that lets a Property Manager write data
 * (reserves, calendar, tickets) calls HOA_Dash_PM_Audit::log(...) so the board
 * has a full, tamper-evident trail of PM edits, separate from board's own edits.
 */
class HOA_Dash_PM_Audit {

	public function __construct() {
		add_action( 'wp_ajax_hoa_get_pm_audit_log', array( $this, 'ajax_get_log' ) );
	}

	public static function log( $action, $object_type, $object_id, $before = null, $after = null ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_pm_audit_log', array(
			'actor_id'     => get_current_user_id(),
			'action'       => $action,
			'object_type'  => $object_type,
			'object_id'    => $object_id,
			'before_value' => $before !== null ? wp_json_encode( $before ) : null,
			'after_value'  => $after !== null ? wp_json_encode( $after ) : null,
			'ip_address'   => self::client_ip(),
		) );
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/** Board-only: view the complete PM audit trail. */
	public function ajax_get_log() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_view_pm_audit_log' ) ) { wp_send_json_error( 'forbidden' ); }

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT l.*, u.display_name FROM {$wpdb->prefix}hoa_pm_audit_log l
			 LEFT JOIN {$wpdb->users} u ON u.ID = l.actor_id
			 ORDER BY l.created_at DESC LIMIT 500",
			ARRAY_A
		);
		wp_send_json_success( $rows );
	}
}
