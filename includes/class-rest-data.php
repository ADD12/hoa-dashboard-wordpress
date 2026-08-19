<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST API data endpoints for the iOS app. Every route requires a valid
 * Bearer app token (enforced globally in HOA_Dash_REST_Auth::rest_auth_errors),
 * and each callback still re-checks the specific WP capability needed —
 * exactly the same capability model the WordPress dashboard/AJAX layer uses,
 * so permissions can never drift between web and app.
 *
 * Namespace: hoa/v1
 */
class HOA_Dash_REST_Data {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	private function cap_guard( $cap ) {
		return function () use ( $cap ) { return current_user_can( $cap ); };
	}

	public function register_routes() {
		$ns = 'hoa/v1';

		// Reserves
		register_rest_route( $ns, '/reserves/health', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_reserve_health' ),
			'permission_callback' => $this->cap_guard( 'hoa_view_reserves' ),
		) );
		register_rest_route( $ns, '/reserves/accounts', array(
			'methods' => 'POST', 'callback' => array( $this, 'save_reserve_account' ),
			'permission_callback' => function () { return current_user_can( 'hoa_manage_reserves' ) || current_user_can( 'hoa_edit_reserves_pm' ); },
		) );
		register_rest_route( $ns, '/reserves/thresholds', array(
			'methods' => 'POST', 'callback' => array( $this, 'save_thresholds' ),
			'permission_callback' => function () { return current_user_can( 'hoa_set_thresholds' ) || current_user_can( 'hoa_edit_reserves_pm' ); },
		) );

		// Dues & payments
		register_rest_route( $ns, '/dues/mine', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_my_dues' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
		register_rest_route( $ns, '/payments/pay-now', array(
			'methods' => 'POST', 'callback' => array( $this, 'pay_now' ),
			'permission_callback' => $this->cap_guard( 'hoa_pay_dues' ),
		) );
		register_rest_route( $ns, '/payments/autopay', array(
			'methods' => 'POST', 'callback' => array( $this, 'setup_autopay' ),
			'permission_callback' => $this->cap_guard( 'hoa_pay_dues' ),
		) );
		register_rest_route( $ns, '/payments/autopay/cancel', array(
			'methods' => 'POST', 'callback' => array( $this, 'cancel_autopay' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );

		// Tickets
		register_rest_route( $ns, '/tickets', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_my_tickets' ), 'permission_callback' => function () { return is_user_logged_in(); } ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'submit_ticket' ), 'permission_callback' => $this->cap_guard( 'hoa_submit_ticket' ) ),
		) );
		register_rest_route( $ns, '/tickets/all', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_all_tickets' ),
			'permission_callback' => $this->cap_guard( 'hoa_view_all_tickets' ),
		) );
		register_rest_route( $ns, '/tickets/(?P<id>\d+)/respond', array(
			'methods' => 'POST', 'callback' => array( $this, 'respond_ticket' ),
			'permission_callback' => function () { return current_user_can( 'hoa_respond_tickets' ) || current_user_can( 'hoa_view_all_tickets' ); },
		) );
		register_rest_route( $ns, '/tickets/(?P<id>\d+)/close', array(
			'methods' => 'POST', 'callback' => array( $this, 'close_ticket' ),
			'permission_callback' => function () { return current_user_can( 'hoa_respond_tickets' ) || current_user_can( 'hoa_view_all_tickets' ); },
		) );
		register_rest_route( $ns, '/tickets/(?P<id>\d+)/escalate', array(
			'methods' => 'POST', 'callback' => array( $this, 'escalate_ticket' ),
			'permission_callback' => $this->cap_guard( 'hoa_escalate_to_pm' ),
		) );

		// Calendar
		register_rest_route( $ns, '/events', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_events' ), 'permission_callback' => function () { return is_user_logged_in(); } ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'save_event' ), 'permission_callback' => function () { return current_user_can( 'hoa_manage_calendar' ) || current_user_can( 'hoa_edit_calendar_pm' ); } ),
		) );

		// Newsletter
		register_rest_route( $ns, '/newsletters', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_newsletters' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
		register_rest_route( $ns, '/newsletters/draft', array(
			'methods' => 'POST', 'callback' => array( $this, 'generate_draft' ),
			'permission_callback' => function () { return current_user_can( 'hoa_manage_newsletter' ) || current_user_can( 'hoa_draft_newsletter' ); },
		) );
		register_rest_route( $ns, '/newsletters/(?P<id>\d+)/send', array(
			'methods' => 'POST', 'callback' => array( $this, 'send_newsletter' ),
			'permission_callback' => $this->cap_guard( 'hoa_manage_newsletter' ),
		) );

		// PM review
		register_rest_route( $ns, '/pm-reviews', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get_pm_reviews' ), 'permission_callback' => $this->cap_guard( 'hoa_view_pm_reviews' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'submit_pm_review' ), 'permission_callback' => $this->cap_guard( 'hoa_submit_pm_review' ) ),
		) );

		// Board: PM audit log
		register_rest_route( $ns, '/pm-audit-log', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_audit_log' ),
			'permission_callback' => $this->cap_guard( 'hoa_view_pm_audit_log' ),
		) );

		// 2FA self-service enrollment (already logged in via app token)
		register_rest_route( $ns, '/2fa/enroll', array(
			'methods' => 'POST', 'callback' => array( $this, 'enroll_2fa' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
		register_rest_route( $ns, '/2fa/confirm', array(
			'methods' => 'POST', 'callback' => array( $this, 'confirm_2fa' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
	}

	/* ---------------- Reserves ---------------- */

	public function get_reserve_health() {
		return HOA_Dash_Reserves::get_health_summary();
	}

	public function save_reserve_account( WP_REST_Request $req ) {
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_reserve_accounts';
		$id = absint( $req->get_param( 'id' ) );
		$before = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ) : null;

		$data = array(
			'account_name'        => sanitize_text_field( (string) $req->get_param( 'account_name' ) ),
			'account_category'    => sanitize_text_field( (string) $req->get_param( 'account_category' ) ),
			'current_balance'     => (float) $req->get_param( 'current_balance' ),
			'fully_funded_target' => (float) $req->get_param( 'fully_funded_target' ),
			'ca_min_required'     => (float) $req->get_param( 'ca_min_required' ),
			'statement_date'      => sanitize_text_field( (string) $req->get_param( 'statement_date' ) ) ?: null,
			'source_document'     => sanitize_text_field( (string) $req->get_param( 'source_document' ) ),
			'notes'               => sanitize_textarea_field( (string) $req->get_param( 'notes' ) ),
			'updated_by'          => get_current_user_id(),
		);

		if ( $id ) { $wpdb->update( $table, $data, array( 'id' => $id ) ); }
		else { $wpdb->insert( $table, $data ); $id = $wpdb->insert_id; }

		if ( current_user_can( 'hoa_edit_reserves_pm' ) && ! current_user_can( 'hoa_manage_reserves' ) ) {
			HOA_Dash_PM_Audit::log( 'reserve_account_saved', 'reserve_account', $id, $before, $data );
		}
		return array( 'id' => $id );
	}

	public function save_thresholds( WP_REST_Request $req ) {
		global $wpdb;
		$data = array(
			'green_min_pct'  => (float) $req->get_param( 'green_min_pct' ),
			'yellow_min_pct' => (float) $req->get_param( 'yellow_min_pct' ),
			'red_below_pct'  => (float) $req->get_param( 'yellow_min_pct' ),
			'set_by'         => get_current_user_id(),
		);
		$wpdb->insert( $wpdb->prefix . 'hoa_reserve_thresholds', $data );
		if ( current_user_can( 'hoa_edit_reserves_pm' ) && ! current_user_can( 'hoa_set_thresholds' ) ) {
			HOA_Dash_PM_Audit::log( 'thresholds_updated', 'reserve_thresholds', $wpdb->insert_id, null, $data );
		}
		return array( 'saved' => true );
	}

	/* ---------------- Dues & payments ---------------- */

	public function get_my_dues() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}hoa_dues_ledger WHERE user_id=%d ORDER BY due_date DESC", get_current_user_id()
		), ARRAY_A );
		$balance = 0;
		foreach ( $rows as $r ) { $balance += ( (float) $r['amount_due'] - (float) $r['amount_paid'] ); }
		return array( 'ledger' => $rows, 'balance_owed' => round( $balance, 2 ) );
	}

	public function pay_now( WP_REST_Request $req ) {
		$amount = (float) $req->get_param( 'amount' );
		$method = sanitize_text_field( (string) $req->get_param( 'method' ) ) ?: 'card';
		$token  = sanitize_text_field( (string) $req->get_param( 'gateway_token' ) );
		$ledger_id = absint( $req->get_param( 'ledger_id' ) );
		if ( $amount <= 0 || ! $token ) { return new WP_Error( 'invalid_request', 'Amount and gateway_token are required.', array( 'status' => 400 ) ); }

		$fee = HOA_Dash_Dues_Payments::calc_fee( $amount, $method );
		$gateway = HOA_Dash_Settings::get( 'hoa_dash_payment_gateway', 'stripe' );

		// Integration point: real gateway charge call goes here using your live keys.
		$result = array( 'success' => true, 'reference' => 'sim_' . wp_generate_password( 12, false ) );

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_payments', array(
			'user_id' => get_current_user_id(), 'ledger_id' => $ledger_id ?: null,
			'amount' => $amount, 'fee_amount' => $fee, 'method' => $method,
			'gateway' => $gateway, 'gateway_ref' => $result['reference'],
			'status' => 'completed', 'is_autopay' => 0,
		) );
		if ( $ledger_id ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}hoa_dues_ledger SET amount_paid = amount_paid + %f, status = IF(amount_paid + %f >= amount_due, 'paid', 'partial') WHERE id=%d",
				$amount, $amount, $ledger_id
			) );
		}
		return array( 'reference' => $result['reference'], 'fee_charged' => $fee, 'total_charged' => $amount + $fee );
	}

	public function setup_autopay( WP_REST_Request $req ) {
		$funding_type = sanitize_text_field( (string) $req->get_param( 'funding_type' ) ) ?: 'bank';
		$token = sanitize_text_field( (string) $req->get_param( 'gateway_token' ) );
		$day = min( 28, max( 1, absint( $req->get_param( 'day_of_month' ) ) ) );
		if ( ! $token ) { return new WP_Error( 'missing_token', 'gateway_token is required.', array( 'status' => 400 ) ); }

		global $wpdb;
		$wpdb->replace( $wpdb->prefix . 'hoa_autopay', array(
			'user_id' => get_current_user_id(),
			'gateway' => HOA_Dash_Settings::get( 'hoa_dash_payment_gateway', 'stripe' ),
			'gateway_payment_method_id' => $token,
			'funding_type' => $funding_type, 'active' => 1, 'day_of_month' => $day,
		) );
		return array( 'enabled' => true );
	}

	public function cancel_autopay() {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_autopay', array( 'active' => 0 ), array( 'user_id' => get_current_user_id() ) );
		return array( 'cancelled' => true );
	}

	/* ---------------- Tickets ---------------- */

	public function get_my_tickets() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}hoa_tickets WHERE user_id=%d ORDER BY created_at DESC", get_current_user_id()
		), ARRAY_A );
	}

	public function submit_ticket( WP_REST_Request $req ) {
		$subject = sanitize_text_field( (string) $req->get_param( 'subject' ) );
		$desc    = sanitize_textarea_field( (string) $req->get_param( 'description' ) );
		$cat     = sanitize_text_field( (string) $req->get_param( 'category' ) ) ?: 'general';
		if ( ! $subject ) { return new WP_Error( 'missing_subject', 'Subject is required.', array( 'status' => 400 ) ); }

		global $wpdb;
		$year = date( 'Y' );
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}hoa_tickets WHERE ticket_number LIKE %s", "HOA-{$year}-%" ) );
		$ticket_number = sprintf( 'HOA-%s-%04d', $year, $count + 1 );

		$wpdb->insert( $wpdb->prefix . 'hoa_tickets', array(
			'ticket_number' => $ticket_number, 'user_id' => get_current_user_id(),
			'subject' => $subject, 'description' => $desc, 'category' => $cat, 'status' => 'open',
		) );
		$ticket_id = $wpdb->insert_id;
		HOA_Dash_Tickets::log_event( $ticket_id, get_current_user_id(), 'created', 'Ticket submitted via iOS app.' );

		$pm_users = get_users( array( 'role' => 'hoa_property_manager', 'fields' => array( 'user_email' ) ) );
		if ( $pm_users ) {
			wp_mail( wp_list_pluck( $pm_users, 'user_email' ), "[New Ticket {$ticket_number}] {$subject}", "A new trouble ticket was submitted via the mobile app.\n\nTicket: {$ticket_number}\nSubject: {$subject}\n\n{$desc}" );
		}
		return array( 'ticket_number' => $ticket_number, 'ticket_id' => $ticket_id );
	}

	public function get_all_tickets() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT t.*, u.display_name FROM {$wpdb->prefix}hoa_tickets t LEFT JOIN {$wpdb->users} u ON u.ID=t.user_id ORDER BY t.created_at DESC", ARRAY_A );
		$summary = array( 'total_open' => 0, 'total_closed' => 0, 'total_escalated' => 0 );
		foreach ( $rows as $r ) {
			if ( $r['status'] === 'closed' ) { $summary['total_closed']++; } else { $summary['total_open']++; }
			if ( $r['escalated'] ) { $summary['total_escalated']++; }
		}
		return array( 'tickets' => $rows, 'summary' => $summary );
	}

	public function respond_ticket( WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		$note = sanitize_textarea_field( (string) $req->get_param( 'note' ) );
		if ( ! $note ) { return new WP_Error( 'missing_note', 'note is required.', array( 'status' => 400 ) ); }
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_tickets', array( 'pm_responded_at' => current_time( 'mysql' ), 'status' => 'in_progress' ), array( 'id' => $id ) );
		HOA_Dash_Tickets::log_event( $id, get_current_user_id(), 'responded', $note );
		if ( current_user_can( 'hoa_property_manager' ) ) {
			HOA_Dash_PM_Audit::log( 'ticket_response', 'ticket', $id, null, array( 'note' => $note ) );
		}
		return array( 'responded' => true );
	}

	public function close_ticket( WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		global $wpdb;
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hoa_tickets WHERE id=%d", $id ), ARRAY_A );
		if ( ! $ticket ) { return new WP_Error( 'not_found', 'Ticket not found.', array( 'status' => 404 ) ); }

		$wpdb->update( $wpdb->prefix . 'hoa_tickets', array( 'status' => 'closed', 'closed_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		HOA_Dash_Tickets::log_event( $id, get_current_user_id(), 'closed', 'Ticket closed via iOS app.' );
		if ( current_user_can( 'hoa_property_manager' ) ) {
			HOA_Dash_PM_Audit::log( 'ticket_closed', 'ticket', $id, null, null );
		}

		$member = get_user_by( 'id', $ticket['user_id'] );
		if ( $member ) {
			$review_link = home_url( '/hoa-dashboard/?review_ticket=' . $id );
			wp_mail( $member->user_email, "Your ticket {$ticket['ticket_number']} has been closed — how did we do?",
				"Hi {$member->display_name},\n\nYour trouble ticket ({$ticket['ticket_number']}: {$ticket['subject']}) has been marked closed.\n\nPlease rate the property management firm's handling of this issue: {$review_link}\n\nThank you,\nHOA Board" );
		}
		return array( 'closed' => true );
	}

	public function escalate_ticket( WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		$reason = sanitize_textarea_field( (string) $req->get_param( 'reason' ) );
		if ( ! $reason ) { return new WP_Error( 'missing_reason', 'reason is required.', array( 'status' => 400 ) ); }
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_tickets', array( 'escalated' => 1, 'escalated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		$wpdb->insert( $wpdb->prefix . 'hoa_escalations', array( 'ticket_id' => $id, 'raised_by' => get_current_user_id(), 'reason' => $reason, 'status' => 'open' ) );
		HOA_Dash_Tickets::log_event( $id, get_current_user_id(), 'escalated', $reason );
		$pm_users = get_users( array( 'role' => 'hoa_property_manager', 'fields' => array( 'user_email' ) ) );
		if ( $pm_users ) { wp_mail( wp_list_pluck( $pm_users, 'user_email' ), "[ESCALATION] Ticket #{$id}", "The board has escalated this ticket via the mobile app.\n\nReason: {$reason}" ); }
		return array( 'escalated' => true );
	}

	/* ---------------- Calendar ---------------- */

	public function get_events() {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hoa_events WHERE start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 25", ARRAY_A );
	}

	public function save_event( WP_REST_Request $req ) {
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_events';
		$id = absint( $req->get_param( 'id' ) );
		$before = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A ) : null;

		$data = array(
			'title' => sanitize_text_field( (string) $req->get_param( 'title' ) ),
			'event_type' => sanitize_text_field( (string) $req->get_param( 'event_type' ) ) ?: 'board_meeting',
			'start_datetime' => sanitize_text_field( (string) $req->get_param( 'start_datetime' ) ),
			'end_datetime' => sanitize_text_field( (string) $req->get_param( 'end_datetime' ) ) ?: null,
			'location_text' => sanitize_text_field( (string) $req->get_param( 'location_text' ) ),
			'zoom_link' => esc_url_raw( (string) $req->get_param( 'zoom_link' ) ),
			'description' => sanitize_textarea_field( (string) $req->get_param( 'description' ) ),
			'created_by' => get_current_user_id(),
		);
		if ( $id ) { $wpdb->update( $table, $data, array( 'id' => $id ) ); }
		else { $wpdb->insert( $table, $data ); $id = $wpdb->insert_id; }

		if ( current_user_can( 'hoa_edit_calendar_pm' ) && ! current_user_can( 'hoa_manage_calendar' ) ) {
			HOA_Dash_PM_Audit::log( 'calendar_event_saved', 'event', $id, $before, $data );
		}
		return array( 'id' => $id );
	}

	/* ---------------- Newsletter ---------------- */

	public function get_newsletters() {
		global $wpdb;
		if ( current_user_can( 'hoa_manage_newsletter' ) || current_user_can( 'hoa_draft_newsletter' ) ) {
			return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hoa_newsletters ORDER BY period_month DESC", ARRAY_A );
		}
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hoa_newsletters WHERE status='sent' ORDER BY period_month DESC", ARRAY_A );
	}

	public function generate_draft() {
		return array( 'body_html' => HOA_Dash_Newsletter::build_auto_summary() );
	}

	public function send_newsletter( WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_newsletters';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row ) { return new WP_Error( 'not_found', 'Newsletter not found.', array( 'status' => 404 ) ); }

		$members = get_users( array( 'role__in' => array( 'hoa_member', 'hoa_board_member' ), 'fields' => array( 'user_email' ) ) );
		$from = HOA_Dash_Settings::get( 'hoa_dash_newsletter_from_email', get_bloginfo( 'admin_email' ) );
		add_filter( 'wp_mail_content_type', function () { return 'text/html'; } );
		foreach ( $members as $m ) { wp_mail( $m->user_email, $row['subject'], $row['body_html'], array( "From: {$from}" ) ); }
		$wpdb->update( $table, array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		return array( 'sent_to' => count( $members ) );
	}

	/* ---------------- PM reviews ---------------- */

	public function get_pm_reviews() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT r.*, u.display_name, t.ticket_number FROM {$wpdb->prefix}hoa_pm_reviews r
			 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
			 LEFT JOIN {$wpdb->prefix}hoa_tickets t ON t.id = r.ticket_id
			 ORDER BY r.created_at DESC", ARRAY_A
		);
		$avg = $wpdb->get_var( "SELECT AVG(rating) FROM {$wpdb->prefix}hoa_pm_reviews" );
		return array( 'reviews' => $rows, 'average_rating' => $avg ? round( (float) $avg, 2 ) : null );
	}

	public function submit_pm_review( WP_REST_Request $req ) {
		$rating = min( 5, max( 1, absint( $req->get_param( 'rating' ) ) ) );
		if ( ! $rating ) { return new WP_Error( 'invalid_rating', 'rating (1-5) is required.', array( 'status' => 400 ) ); }
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_pm_reviews', array(
			'user_id' => get_current_user_id(),
			'ticket_id' => absint( $req->get_param( 'ticket_id' ) ) ?: null,
			'rating' => $rating,
			'comments' => sanitize_textarea_field( (string) $req->get_param( 'comments' ) ),
		) );
		return array( 'submitted' => true );
	}

	/* ---------------- Board: PM audit log ---------------- */

	public function get_audit_log() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT l.*, u.display_name FROM {$wpdb->prefix}hoa_pm_audit_log l
			 LEFT JOIN {$wpdb->users} u ON u.ID = l.actor_id
			 ORDER BY l.created_at DESC LIMIT 500", ARRAY_A
		);
	}

	/* ---------------- 2FA self-service ---------------- */

	public function enroll_2fa( WP_REST_Request $req ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'phone' ) );
		if ( strlen( $phone ) < 10 ) { return new WP_Error( 'invalid_phone', 'A valid phone number is required.', array( 'status' => 400 ) ); }
		if ( $phone[0] !== '+' ) { $phone = '+1' . ltrim( $phone, '0' ); }

		global $wpdb;
		$wpdb->replace( $wpdb->prefix . 'hoa_user_2fa', array( 'user_id' => get_current_user_id(), 'phone_number' => $phone, 'verified' => 0 ) );

		$twilio = new HOA_Dash_2FA_Twilio();
		$sent = $twilio->send_verification( $phone );
		if ( is_wp_error( $sent ) ) { return new WP_Error( 'sms_failed', $sent->get_error_message(), array( 'status' => 500 ) ); }
		return array( 'sent' => true );
	}

	public function confirm_2fa( WP_REST_Request $req ) {
		$code = sanitize_text_field( (string) $req->get_param( 'code' ) );
		if ( ! $code ) { return new WP_Error( 'missing_code', 'code is required.', array( 'status' => 400 ) ); }
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hoa_user_2fa WHERE user_id=%d", get_current_user_id() ) );
		if ( ! $row ) { return new WP_Error( 'no_phone', 'No phone on file.', array( 'status' => 404 ) ); }

		$twilio = new HOA_Dash_2FA_Twilio();
		$check = $twilio->check_verification( $row->phone_number, $code );
		if ( is_wp_error( $check ) ) { return new WP_Error( 'invalid_code', $check->get_error_message(), array( 'status' => 401 ) ); }

		$wpdb->update( $wpdb->prefix . 'hoa_user_2fa', array( 'verified' => 1 ), array( 'user_id' => get_current_user_id() ) );
		return array( 'verified' => true );
	}
}
