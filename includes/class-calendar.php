<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Calendar {

	public function __construct() {
		add_action( 'wp_ajax_hoa_get_events', array( $this, 'ajax_get_events' ) );
		add_action( 'wp_ajax_nopriv_hoa_get_events', array( $this, 'ajax_get_events' ) ); // public teaser optional; front dashboard still gates by login in template
		add_action( 'wp_ajax_hoa_save_event', array( $this, 'ajax_save_event' ) );
		add_action( 'wp_ajax_hoa_delete_event', array( $this, 'ajax_delete_event' ) );
	}

	public function ajax_get_events() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}hoa_events WHERE start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 12",
			ARRAY_A
		);
		wp_send_json_success( $rows );
	}

	public function ajax_save_event() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		$can_board = current_user_can( 'hoa_manage_calendar' );
		$can_pm    = current_user_can( 'hoa_edit_calendar_pm' );
		if ( ! $can_board && ! $can_pm ) { wp_send_json_error( 'forbidden' ); }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_events';
		$id = absint( $_POST['id'] ?? 0 );
		$before = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ) : null;

		$data = array(
			'title'          => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'event_type'     => sanitize_text_field( wp_unslash( $_POST['event_type'] ?? 'board_meeting' ) ),
			'start_datetime' => sanitize_text_field( wp_unslash( $_POST['start_datetime'] ?? '' ) ),
			'end_datetime'   => sanitize_text_field( wp_unslash( $_POST['end_datetime'] ?? '' ) ) ?: null,
			'location_text'  => sanitize_text_field( wp_unslash( $_POST['location_text'] ?? '' ) ),
			'zoom_link'      => esc_url_raw( wp_unslash( $_POST['zoom_link'] ?? '' ) ),
			'description'    => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'created_by'     => get_current_user_id(),
		);

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		if ( $can_pm && ! $can_board ) {
			HOA_Dash_PM_Audit::log( 'calendar_event_saved', 'event', $id, $before, $data );
		}

		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_delete_event() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_manage_calendar' ) ) { wp_send_json_error( 'forbidden' ); }
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'hoa_events', array( 'id' => absint( $_POST['id'] ?? 0 ) ) );
		wp_send_json_success();
	}
}
