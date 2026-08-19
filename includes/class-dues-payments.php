<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dues owed + payment handling. This class is gateway-agnostic: it builds the
 * charge request and fee math, then calls out to whichever gateway is configured
 * (Stripe, Authorize.Net, etc.) via a thin adapter. No raw card/bank numbers ever
 * touch this server — client-side tokenization (e.g. Stripe Elements / Stripe.js,
 * or Plaid for ACH) must be used in assets/js/dashboard.js, and only the resulting
 * token/payment-method-id is sent here.
 */
class HOA_Dash_Dues_Payments {

	public function __construct() {
		add_action( 'wp_ajax_hoa_get_my_dues', array( $this, 'ajax_get_my_dues' ) );
		add_action( 'wp_ajax_hoa_pay_now', array( $this, 'ajax_pay_now' ) );
		add_action( 'wp_ajax_hoa_setup_autopay', array( $this, 'ajax_setup_autopay' ) );
		add_action( 'wp_ajax_hoa_cancel_autopay', array( $this, 'ajax_cancel_autopay' ) );
		add_action( 'hoa_dash_daily_cron', array( $this, 'run_autopay_charges' ) );

		if ( ! wp_next_scheduled( 'hoa_dash_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'hoa_dash_daily_cron' );
		}
	}

	public static function calc_fee( $amount, $method ) {
		if ( $method === 'card' ) {
			$pct  = (float) HOA_Dash_Settings::get( 'hoa_dash_card_fee_pct', 2.9 );
			$flat = (float) HOA_Dash_Settings::get( 'hoa_dash_card_fee_flat', 0.30 );
			return round( $amount * ( $pct / 100 ) + $flat, 2 );
		}
		if ( $method === 'bank' ) {
			$flat = (float) HOA_Dash_Settings::get( 'hoa_dash_ach_fee_flat', 1.00 );
			return round( $flat, 2 );
		}
		return 0.00;
	}

	public function ajax_get_my_dues() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( 'not_logged_in' ); }
		global $wpdb;
		$table = $wpdb->prefix . 'hoa_dues_ledger';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id=%d ORDER BY due_date DESC", get_current_user_id()
		), ARRAY_A );

		$balance = 0;
		foreach ( $rows as $r ) {
			$balance += ( (float) $r['amount_due'] - (float) $r['amount_paid'] );
		}
		wp_send_json_success( array( 'ledger' => $rows, 'balance_owed' => round( $balance, 2 ) ) );
	}

	/**
	 * One-time "Pay Now". $_POST['gateway_token'] must be a tokenized payment
	 * method id created client-side (never raw card/account numbers).
	 */
	public function ajax_pay_now() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_pay_dues' ) ) { wp_send_json_error( 'forbidden' ); }

		$amount   = (float) ( $_POST['amount'] ?? 0 );
		$method   = sanitize_text_field( wp_unslash( $_POST['method'] ?? 'card' ) ); // card|bank
		$token    = sanitize_text_field( wp_unslash( $_POST['gateway_token'] ?? '' ) );
		$ledger_id = absint( $_POST['ledger_id'] ?? 0 );

		if ( $amount <= 0 || ! $token ) { wp_send_json_error( 'invalid_request' ); }

		$fee = self::calc_fee( $amount, $method );
		$gateway = HOA_Dash_Settings::get( 'hoa_dash_payment_gateway', 'stripe' );

		// --- Gateway call placeholder -------------------------------------------------
		// $result = HOA_Dash_Gateway_Adapter::charge( $gateway, $token, $amount + $fee );
		// Real implementation: POST to Stripe PaymentIntents (or your processor) using
		// hoa_dash_payment_secret_key from Settings. Left as an integration point since
		// it requires your live/test API keys.
		$result = array( 'success' => true, 'reference' => 'sim_' . wp_generate_password( 12, false ) );
		// --------------------------------------------------------------------------------

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hoa_payments', array(
			'user_id'     => get_current_user_id(),
			'ledger_id'   => $ledger_id ?: null,
			'amount'      => $amount,
			'fee_amount'  => $fee,
			'method'      => $method,
			'gateway'     => $gateway,
			'gateway_ref' => $result['reference'],
			'status'      => $result['success'] ? 'completed' : 'failed',
			'is_autopay'  => 0,
		) );

		if ( $result['success'] && $ledger_id ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}hoa_dues_ledger SET amount_paid = amount_paid + %f, status = IF(amount_paid + %f >= amount_due, 'paid', 'partial') WHERE id=%d",
				$amount, $amount, $ledger_id
			) );
		}

		wp_send_json_success( array( 'reference' => $result['reference'], 'fee_charged' => $fee, 'total_charged' => $amount + $fee ) );
	}

	/** Set up recurring autopay from bank (ACH) or card. */
	public function ajax_setup_autopay() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_pay_dues' ) ) { wp_send_json_error( 'forbidden' ); }

		$funding_type = sanitize_text_field( wp_unslash( $_POST['funding_type'] ?? 'bank' ) );
		$token        = sanitize_text_field( wp_unslash( $_POST['gateway_token'] ?? '' ) );
		$day          = min( 28, max( 1, absint( $_POST['day_of_month'] ?? 1 ) ) );
		if ( ! $token ) { wp_send_json_error( 'missing_token' ); }

		$gateway = HOA_Dash_Settings::get( 'hoa_dash_payment_gateway', 'stripe' );

		global $wpdb;
		$wpdb->replace( $wpdb->prefix . 'hoa_autopay', array(
			'user_id'                    => get_current_user_id(),
			'gateway'                    => $gateway,
			'gateway_payment_method_id'  => $token,
			'funding_type'               => $funding_type,
			'active'                     => 1,
			'day_of_month'               => $day,
		) );

		wp_send_json_success( array( 'message' => 'Autopay enabled.' ) );
	}

	public function ajax_cancel_autopay() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hoa_autopay', array( 'active' => 0 ), array( 'user_id' => get_current_user_id() ) );
		wp_send_json_success();
	}

	/** Daily cron: charge anyone whose autopay day matches today and who has a balance. */
	public function run_autopay_charges() {
		global $wpdb;
		$today = (int) date( 'j' );
		$autopays = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}hoa_autopay WHERE active=1 AND day_of_month=%d", $today
		), ARRAY_A );

		foreach ( $autopays as $ap ) {
			$owed = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount_due-amount_paid) FROM {$wpdb->prefix}hoa_dues_ledger WHERE user_id=%d AND status!='paid'",
				$ap['user_id']
			) );
			if ( ! $owed || $owed <= 0 ) { continue; }

			$method = $ap['funding_type'] === 'card' ? 'card' : 'bank';
			$fee = self::calc_fee( (float) $owed, $method );

			// Real gateway charge would occur here using $ap['gateway_payment_method_id'].
			$wpdb->insert( $wpdb->prefix . 'hoa_payments', array(
				'user_id'    => $ap['user_id'],
				'amount'     => $owed,
				'fee_amount' => $fee,
				'method'     => $method,
				'gateway'    => $ap['gateway'],
				'gateway_ref'=> 'autopay_' . wp_generate_password( 10, false ),
				'status'     => 'completed',
				'is_autopay' => 1,
			) );

			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}hoa_dues_ledger SET amount_paid = amount_due, status='paid' WHERE user_id=%d AND status!='paid'",
				$ap['user_id']
			) );
		}
	}
}
