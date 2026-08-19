<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Reserve fund accounts sourced from the association's audited financial
 * statements / reserve study (Civil Code §5300, §5550-§5570 disclosure regime).
 *
 * Health status is computed from "percent funded" = current_balance / fully_funded_target,
 * compared against board/PM-configurable thresholds. The `ca_min_required` field lets the
 * board record the statutory/reserve-study minimum separately from the "fully funded" ideal,
 * so a red flag can specifically mean "below what our reserve study says is required."
 */
class HOA_Dash_Reserves {

	public function __construct() {
		add_action( 'wp_ajax_hoa_save_reserve_account', array( $this, 'ajax_save_account' ) );
		add_action( 'wp_ajax_hoa_delete_reserve_account', array( $this, 'ajax_delete_account' ) );
		add_action( 'wp_ajax_hoa_save_thresholds', array( $this, 'ajax_save_thresholds' ) );
	}

	public static function get_thresholds() {
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_thresholds';
		$row = $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A );
		if ( ! $row ) {
			$row = array( 'green_min_pct' => 70, 'yellow_min_pct' => 40, 'red_below_pct' => 40 );
		}
		return $row;
	}

	public static function get_accounts() {
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_accounts';
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY account_category, account_name", ARRAY_A );
	}

	/**
	 * Returns overall funding % and status ('green'|'yellow'|'red') plus per-account detail.
	 */
	public static function get_health_summary() {
		$accounts   = self::get_accounts();
		$thresholds = self::get_thresholds();

		$total_balance = 0;
		$total_target  = 0;
		$total_min     = 0;
		$detail = array();

		foreach ( $accounts as $a ) {
			$total_balance += (float) $a['current_balance'];
			$total_target  += (float) $a['fully_funded_target'];
			$total_min     += (float) $a['ca_min_required'];

			$pct = $a['fully_funded_target'] > 0
				? ( (float) $a['current_balance'] / (float) $a['fully_funded_target'] ) * 100
				: 0;

			$detail[] = array_merge( $a, array(
				'percent_funded' => round( $pct, 1 ),
				'status'         => self::status_for_pct( $pct, $thresholds ),
				'below_ca_min'   => (float) $a['current_balance'] < (float) $a['ca_min_required'],
			) );
		}

		$overall_pct = $total_target > 0 ? ( $total_balance / $total_target ) * 100 : 0;

		return array(
			'overall_percent_funded' => round( $overall_pct, 1 ),
			'overall_status'         => self::status_for_pct( $overall_pct, $thresholds ),
			'total_balance'          => $total_balance,
			'total_target'           => $total_target,
			'total_ca_min_required'  => $total_min,
			'below_ca_min_overall'   => $total_balance < $total_min,
			'thresholds'             => $thresholds,
			'accounts'               => $detail,
		);
	}

	private static function status_for_pct( $pct, $thresholds ) {
		if ( $pct >= (float) $thresholds['green_min_pct'] ) { return 'green'; }
		if ( $pct >= (float) $thresholds['yellow_min_pct'] ) { return 'yellow'; }
		return 'red';
	}

	/** Board or Property Manager can create/update reserve accounts. PM edits are audited. */
	public function ajax_save_account() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		$can_board = current_user_can( 'hoa_manage_reserves' );
		$can_pm    = current_user_can( 'hoa_edit_reserves_pm' );
		if ( ! $can_board && ! $can_pm ) { wp_send_json_error( 'forbidden' ); }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_accounts';

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$before = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ) : null;

		$data = array(
			'account_name'         => sanitize_text_field( wp_unslash( $_POST['account_name'] ?? '' ) ),
			'account_category'     => sanitize_text_field( wp_unslash( $_POST['account_category'] ?? '' ) ),
			'current_balance'      => (float) ( $_POST['current_balance'] ?? 0 ),
			'fully_funded_target'  => (float) ( $_POST['fully_funded_target'] ?? 0 ),
			'ca_min_required'      => (float) ( $_POST['ca_min_required'] ?? 0 ),
			'statement_date'       => sanitize_text_field( wp_unslash( $_POST['statement_date'] ?? '' ) ) ?: null,
			'source_document'      => sanitize_text_field( wp_unslash( $_POST['source_document'] ?? '' ) ),
			'notes'                => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			'updated_by'           => get_current_user_id(),
		);

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		if ( $can_pm && ! current_user_can( 'hoa_manage_reserves' ) ) {
			HOA_Dash_PM_Audit::log( 'reserve_account_saved', 'reserve_account', $id, $before, $data );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_delete_account() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_manage_reserves' ) ) { wp_send_json_error( 'forbidden' ); } // PM cannot delete, board only
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_accounts';
		$id = absint( $_POST['id'] ?? 0 );
		$wpdb->delete( $table, array( 'id' => $id ) );
		wp_send_json_success();
	}

	/** Only board members (or PM with explicit reserve-edit cap) may set health thresholds. */
	public function ajax_save_thresholds() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_set_thresholds' ) && ! current_user_can( 'hoa_edit_reserves_pm' ) ) {
			wp_send_json_error( 'forbidden' );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_thresholds';

		$data = array(
			'green_min_pct'  => (float) ( $_POST['green_min_pct'] ?? 70 ),
			'yellow_min_pct' => (float) ( $_POST['yellow_min_pct'] ?? 40 ),
			'red_below_pct'  => (float) ( $_POST['yellow_min_pct'] ?? 40 ),
			'set_by'         => get_current_user_id(),
		);
		$wpdb->insert( $table, $data );

		if ( current_user_can( 'hoa_edit_reserves_pm' ) && ! current_user_can( 'hoa_set_thresholds' ) ) {
			HOA_Dash_PM_Audit::log( 'thresholds_updated', 'reserve_thresholds', $wpdb->insert_id, null, $data );
		}

		wp_send_json_success();
	}
}
