<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Tickets {

	public function __construct() {
		add_action( 'wp_ajax_hoa_submit_ticket', array( $this, 'ajax_submit_ticket' ) );
		add_action( 'wp_ajax_hoa_get_my_tickets', array( $this, 'ajax_get_my_tickets' ) );
		add_action( 'wp_ajax_hoa_get_all_tickets', array( $this, 'ajax_get_all_tickets' ) );
		add_action( 'wp_ajax_hoa_ticket_respond', array( $this, 'ajax_ticket_respond' ) );
		add_action( 'wp_ajax_hoa_ticket_close', array( $this, 'ajax_ticket_close' ) );
		add_action( 'wp_ajax_hoa_escalate_ticket', array( $this, 'ajax_escalate_ticket' ) );
	}

	private static function next_ticket_number() {
		global $wpdb;
		$year = date( 'Y' );
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}hoa_tickets WHERE ticket_number LIKE %s",
			"HOA-{$year}-%"
		) );
		return sprintf( 'HOA-%s-%04d', $year, $count + 1 );
	}

	public function ajax_submit_ticket() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_submit_ticket' ) ) { wp_send_json_error( 'forbidden' ); }

		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$desc    = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$cat     = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'general' ) );
		if ( ! $subject ) { wp_send_json_error( 'missing_subject' ); }

		global $wpdb;
		$ticket_number = self::next_ticket_number();
		$wpdb->insert( $wpdb->prefix . 'hoa_tickets', array(
			'ticket_number' => $ticket_number,
			'user_id'       => get_current_user_id(),
			'subject'       => $subject,
			'description'   => $desc,
			'category'      => $cat,
			'status'        => 'open',
		) );
		$ticket_id = $wpdb->insert_id;

		self::log_event( $ticket_id, get_current_user_id(), 'created', 'Ticket submitted by member.' );

		// Notify property manager(s)
		$pm_users = get_users( array( 'role' => 'hoa_property_manager', 'fields' => array( 'user_email' ) ) );
		if ( $pm_users ) {
			$to = wp_list_pluck( $pm_users, 'user_email' );
			wp_mail( $to, "[New Ticket {$ticket_number}] {$subject}", "A new trouble ticket was submitted.\n\nTicket: {$ticket_number}\nSubject: {$subject}\n\n{$desc}" );
		}

		wp_send_json_success( array( 'ticket_number' => $ticket_number, 'ticket_id' => $ticket_id ) );
	}

	public function ajax_get_my_tickets() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( 'not_logged_in' ); }
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}hoa_tickets WHERE user_id=%d ORDER BY created_at DESC", get_current_user_id()
		), ARRAY_A );
		wp_send_json_success( $rows );
	}

	/** Board members (and PM) see all tickets, with counts/timing metrics for oversight. */
	public function ajax_get_all_tickets() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_view_all_tickets' ) ) { wp_send_json_error( 'forbidden' ); }
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT t.*, u.display_name FROM {$wpdb->prefix}hoa_tickets t LEFT JOIN {$wpdb->users} u ON u.ID=t.user_id ORDER BY t.created_at DESC", ARRAY_A );

		$summary = array(
			'total_open'      => 0,
			'total_closed'    => 0,
			'total_escalated' => 0,
		);
		foreach ( $rows as $r ) {
			if ( $r['status'] === 'closed' ) { $summary['total_closed']++; } else { $summary['total_open']++; }
			if ( $r['escalated'] ) { $summary['total_escalated']++; }
		}

		wp_send_json_success( array( 'tickets' => $rows, 'summary' => $summary ) );
	}

	/** Property manager responds to a ticket (or board member notes internally). */
	public function ajax_ticket_respond() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_respond_tickets' ) && ! current_user_can( 'hoa_view_all_tickets' ) ) {
			wp_send_json_error( 'forbidden' );
		}
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$note      = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		if ( ! $ticket_id || ! $note ) { wp_send_json_error( 'invalid_request' ); }

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_tickets',
			array( 'pm_responded_at' => current_time( 'mysql' ), 'status' => 'in_progress' ),
			array( 'id' => $ticket_id )
		);
		self::log_event( $ticket_id, get_current_user_id(), 'responded', $note );

		if ( current_user_can( 'hoa_property_manager' ) ) {
			HOA_Dash_PM_Audit::log( 'ticket_response', 'ticket', $ticket_id, null, array( 'note' => $note ) );
		}

		wp_send_json_success();
	}

	public function ajax_ticket_close() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_respond_tickets' ) && ! current_user_can( 'hoa_view_all_tickets' ) ) {
			wp_send_json_error( 'forbidden' );
		}
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		global $wpdb;
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hoa_tickets WHERE id=%d", $ticket_id ), ARRAY_A );
		if ( ! $ticket ) { wp_send_json_error( 'not_found' ); }

		$wpdb->update( $wpdb->prefix . 'hoa_tickets',
			array( 'status' => 'closed', 'closed_at' => current_time( 'mysql' ) ),
			array( 'id' => $ticket_id )
		);
		self::log_event( $ticket_id, get_current_user_id(), 'closed', 'Ticket closed.' );

		if ( current_user_can( 'hoa_property_manager' ) ) {
			HOA_Dash_PM_Audit::log( 'ticket_closed', 'ticket', $ticket_id, null, null );
		}

		// Follow-up email to the member asking them to rate PM performance on this ticket.
		$member = get_user_by( 'id', $ticket['user_id'] );
		if ( $member ) {
			$review_link = home_url( '/hoa-dashboard/?review_ticket=' . $ticket_id );
			$subject = "Your ticket {$ticket['ticket_number']} has been closed — how did we do?";
			$body = "Hi {$member->display_name},\n\nYour trouble ticket ({$ticket['ticket_number']}: {$ticket['subject']}) has been marked closed.\n\nPlease take a moment to rate the property management firm's handling of this issue: {$review_link}\n\nThank you,\nHOA Board";
			wp_mail( $member->user_email, $subject, $body );
		}

		wp_send_json_success();
	}

	/** Board escalates an unresolved/poorly-handled ticket directly to the PM firm, auditable. */
	public function ajax_escalate_ticket() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_escalate_to_pm' ) ) { wp_send_json_error( 'forbidden' ); }

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$reason    = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		if ( ! $ticket_id || ! $reason ) { wp_send_json_error( 'invalid_request' ); }

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_tickets',
			array( 'escalated' => 1, 'escalated_at' => current_time( 'mysql' ) ),
			array( 'id' => $ticket_id )
		);
		$wpdb->insert( $wpdb->prefix . 'hoa_escalations', array(
			'ticket_id' => $ticket_id,
			'raised_by' => get_current_user_id(),
			'reason'    => $reason,
			'status'    => 'open',
		) );
		self::log_event( $ticket_id, get_current_user_id(), 'escalated', $reason );

		$pm_users = get_users( array( 'role' => 'hoa_property_manager', 'fields' => array( 'user_email' ) ) );
		if ( $pm_users ) {
			$to = wp_list_pluck( $pm_users, 'user_email' );
			wp_mail( $to, "[ESCALATION] Ticket #{$ticket_id}", "The board has escalated this ticket.\n\nReason: {$reason}" );
		}

		wp_send_json_success();
	}

	public static function log_event( $ticket_id, $actor_id, $type, $note ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_ticket_events', array(
			'ticket_id'  => $ticket_id,
			'actor_id'   => $actor_id,
			'event_type' => $type,
			'note'       => $note,
		) );
	}
}
