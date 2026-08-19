<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Monthly newsletter: auto-drafts a financial + ticket summary of the prior
 * month, which the board (or PM, as a draft only) can supplement with news
 * from the property management firm before sending to all members.
 */
class HOA_Dash_Newsletter {

	public function __construct() {
		add_action( 'wp_ajax_hoa_generate_newsletter_draft', array( $this, 'ajax_generate_draft' ) );
		add_action( 'wp_ajax_hoa_save_newsletter', array( $this, 'ajax_save_newsletter' ) );
		add_action( 'wp_ajax_hoa_send_newsletter', array( $this, 'ajax_send_newsletter' ) );
		add_action( 'wp_ajax_hoa_get_newsletters', array( $this, 'ajax_get_newsletters' ) );
	}

	/** Auto-compose a draft body summarizing the previous month. */
	public static function build_auto_summary() {
		global $wpdb;
		$month_start = date( 'Y-m-01', strtotime( 'first day of last month' ) );
		$month_end   = date( 'Y-m-t', strtotime( 'last day of last month' ) );

		$health = HOA_Dash_Reserves::get_health_summary();

		$dues_collected = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM {$wpdb->prefix}hoa_payments WHERE status='completed' AND created_at BETWEEN %s AND %s",
			$month_start . ' 00:00:00', $month_end . ' 23:59:59'
		) );

		$tickets_opened = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}hoa_tickets WHERE created_at BETWEEN %s AND %s",
			$month_start . ' 00:00:00', $month_end . ' 23:59:59'
		) );
		$tickets_closed = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}hoa_tickets WHERE closed_at BETWEEN %s AND %s",
			$month_start . ' 00:00:00', $month_end . ' 23:59:59'
		) );

		$hoa_name = HOA_Dash_Settings::get( 'hoa_dash_hoa_name', get_bloginfo( 'name' ) );
		$pm_name  = HOA_Dash_Settings::get( 'hoa_dash_pm_firm_name', '' );

		$status_label = strtoupper( $health['overall_status'] );

		$html  = "<h2>{$hoa_name} — Monthly Newsletter</h2>";
		$html .= '<p>Summary for ' . date( 'F Y', strtotime( $month_start ) ) . '</p>';
		$html .= '<h3>Reserve Fund Health: ' . esc_html( $status_label ) . '</h3>';
		$html .= '<p>Overall reserve funding: ' . $health['overall_percent_funded'] . '% of fully-funded target ($' . number_format( $health['total_balance'], 2 ) . ' of $' . number_format( $health['total_target'], 2 ) . ').</p>';
		$html .= '<h3>Dues Collected</h3><p>$' . number_format( (float) $dues_collected, 2 ) . ' collected this period.</p>';
		$html .= '<h3>Member Support Tickets</h3><p>' . intval( $tickets_opened ) . ' opened, ' . intval( $tickets_closed ) . ' closed.</p>';
		if ( $pm_name ) {
			$html .= '<h3>News from ' . esc_html( $pm_name ) . '</h3><p><em>[Property management firm: add your update here before sending]</em></p>';
		}

		return $html;
	}

	public function ajax_generate_draft() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_manage_newsletter' ) && ! current_user_can( 'hoa_draft_newsletter' ) ) {
			wp_send_json_error( 'forbidden' );
		}
		wp_send_json_success( array( 'body_html' => self::build_auto_summary() ) );
	}

	public function ajax_save_newsletter() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_manage_newsletter' ) && ! current_user_can( 'hoa_draft_newsletter' ) ) {
			wp_send_json_error( 'forbidden' );
		}
		global $wpdb;
		$id = absint( $_POST['id'] ?? 0 );
		$data = array(
			'period_month' => sanitize_text_field( wp_unslash( $_POST['period_month'] ?? date( 'Y-m-01', strtotime( 'first day of last month' ) ) ) ),
			'subject'      => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
			'body_html'    => wp_kses_post( wp_unslash( $_POST['body_html'] ?? '' ) ),
			'created_by'   => get_current_user_id(),
		);
		if ( $id ) {
			$wpdb->update( $wpdb->prefix . 'hoa_newsletters', $data, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $wpdb->prefix . 'hoa_newsletters', $data );
			$id = $wpdb->insert_id;
		}
		wp_send_json_success( array( 'id' => $id ) );
	}

	/** Only board members may actually send to all members. */
	public function ajax_send_newsletter() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_manage_newsletter' ) ) { wp_send_json_error( 'forbidden' ); }

		$id = absint( $_POST['id'] ?? 0 );
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_newsletters';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
		if ( ! $row ) { wp_send_json_error( 'not_found' ); }

		$members = get_users( array( 'role__in' => array( 'hoa_member', 'hoa_board_member' ), 'fields' => array( 'user_email' ) ) );
		$from = HOA_Dash_Settings::get( 'hoa_dash_newsletter_from_email', get_bloginfo( 'admin_email' ) );
		add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );

		foreach ( $members as $m ) {
			wp_mail( $m->user_email, $row['subject'], $row['body_html'], array( "From: {$from}" ) );
		}

		$wpdb->update( $table, array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		wp_send_json_success( array( 'sent_to' => count( $members ) ) );
	}

	public function ajax_get_newsletters() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( 'not_logged_in' ); }
		global $wpdb;
		if ( current_user_can( 'hoa_manage_newsletter' ) || current_user_can( 'hoa_draft_newsletter' ) ) {
			$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hoa_newsletters ORDER BY period_month DESC", ARRAY_A );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hoa_newsletters WHERE status='sent' ORDER BY period_month DESC", ARRAY_A );
		}
		wp_send_json_success( $rows );
	}
}
