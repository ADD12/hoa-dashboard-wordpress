<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Two-factor auth using Twilio Verify API.
 * Requires: hoa_dash_twilio_sid, hoa_dash_twilio_auth_token, hoa_dash_twilio_verify_service_sid
 * set under Settings > HOA Dashboard.
 *
 * Flow:
 *  1. User logs in with WP username/password (wp_authenticate).
 *  2. If a verified phone is on file, we block full auth, send an SMS/call code via Twilio Verify,
 *     and present a code-entry form (see class-login.php).
 *  3. Code is checked against Twilio Verify's /VerificationCheck endpoint. On success, session is finalized.
 */
class HOA_Dash_2FA_Twilio {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_hoa_2fa_send_code', array( $this, 'ajax_send_code' ) );
		add_action( 'wp_ajax_nopriv_hoa_2fa_verify_code', array( $this, 'ajax_verify_code' ) );
		add_action( 'wp_ajax_hoa_2fa_enroll_phone', array( $this, 'ajax_enroll_phone' ) );
		add_action( 'wp_ajax_hoa_2fa_confirm_enroll', array( $this, 'ajax_confirm_enroll' ) );
	}

	private function creds_ready() {
		return HOA_Dash_Settings::get( 'hoa_dash_twilio_sid' )
			&& HOA_Dash_Settings::get( 'hoa_dash_twilio_auth_token' )
			&& HOA_Dash_Settings::get( 'hoa_dash_twilio_verify_service_sid' );
	}

	/**
	 * Kick off a Twilio Verify "start" request (SMS channel).
	 */
	public function send_verification( $phone_e164 ) {
		if ( ! $this->creds_ready() ) {
			return new WP_Error( 'hoa_2fa_not_configured', __( 'SMS verification is not configured yet. Contact your property manager.', 'hoa-dashboard' ) );
		}
		$sid   = HOA_Dash_Settings::get( 'hoa_dash_twilio_sid' );
		$token = HOA_Dash_Settings::get( 'hoa_dash_twilio_auth_token' );
		$svc   = HOA_Dash_Settings::get( 'hoa_dash_twilio_verify_service_sid' );

		$url = "https://verify.twilio.com/v2/Services/{$svc}/Verifications";
		$res = wp_remote_post( $url, array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$token}" ) ),
			'body'    => array( 'To' => $phone_e164, 'Channel' => 'sms' ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code >= 400 ) {
			return new WP_Error( 'hoa_2fa_twilio_error', __( 'Could not send verification code. Please try again.', 'hoa-dashboard' ) );
		}
		return true;
	}

	/**
	 * Check a submitted code against Twilio Verify.
	 */
	public function check_verification( $phone_e164, $code ) {
		if ( ! $this->creds_ready() ) {
			return new WP_Error( 'hoa_2fa_not_configured', __( 'SMS verification is not configured.', 'hoa-dashboard' ) );
		}
		$sid   = HOA_Dash_Settings::get( 'hoa_dash_twilio_sid' );
		$token = HOA_Dash_Settings::get( 'hoa_dash_twilio_auth_token' );
		$svc   = HOA_Dash_Settings::get( 'hoa_dash_twilio_verify_service_sid' );

		$url = "https://verify.twilio.com/v2/Services/{$svc}/VerificationCheck";
		$res = wp_remote_post( $url, array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( "{$sid}:{$token}" ) ),
			'body'    => array( 'To' => $phone_e164, 'Code' => $code ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $res ) ) { return $res; }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['status'] ) && $body['status'] === 'approved' ) {
			return true;
		}
		return new WP_Error( 'hoa_2fa_invalid_code', __( 'That code was incorrect or expired.', 'hoa-dashboard' ) );
	}

	/** Member enrolls / updates their phone number for 2FA (must already be logged in). */
	public function ajax_enroll_phone() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( 'not_logged_in' ); }

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( strlen( $phone ) < 10 ) { wp_send_json_error( 'invalid_phone' ); }
		if ( $phone[0] !== '+' ) { $phone = '+1' . ltrim( $phone, '0' ); } // default US country code

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_user_2fa';
		$wpdb->replace( $table, array(
			'user_id'      => get_current_user_id(),
			'phone_number' => $phone,
			'verified'     => 0,
		) );

		$sent = $this->send_verification( $phone );
		if ( is_wp_error( $sent ) ) { wp_send_json_error( $sent->get_error_message() ); }
		wp_send_json_success( array( 'message' => 'Code sent.' ) );
	}

	/** Confirms the code sent during self-service enrollment (user already logged in). */
	public function ajax_confirm_enroll() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) { wp_send_json_error( 'not_logged_in' ); }

		$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		if ( ! $code ) { wp_send_json_error( 'missing_code' ); }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_user_2fa';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", get_current_user_id() ) );
		if ( ! $row ) { wp_send_json_error( 'no_phone_on_file' ); }

		$check = $this->check_verification( $row->phone_number, $code );
		if ( is_wp_error( $check ) ) { wp_send_json_error( $check->get_error_message() ); }

		$wpdb->update( $table, array( 'verified' => 1 ), array( 'user_id' => get_current_user_id() ) );
		wp_send_json_success( array( 'message' => 'Phone verified. 2FA is now required at login.' ) );
	}

	/** During login flow, before session finalized. */
	public function ajax_send_code() {
		check_ajax_referer( 'hoa_dash_login_nonce', 'nonce' );
		$user_id = isset( $_POST['pending_uid'] ) ? absint( $_POST['pending_uid'] ) : 0;
		if ( ! $user_id ) { wp_send_json_error( 'missing_user' ); }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_user_2fa';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
		if ( ! $row ) { wp_send_json_error( 'no_phone_on_file' ); }

		$sent = $this->send_verification( $row->phone_number );
		if ( is_wp_error( $sent ) ) { wp_send_json_error( $sent->get_error_message() ); }
		wp_send_json_success( array( 'message' => 'Code sent to phone on file.' ) );
	}

	public function ajax_verify_code() {
		check_ajax_referer( 'hoa_dash_login_nonce', 'nonce' );
		$user_id = isset( $_POST['pending_uid'] ) ? absint( $_POST['pending_uid'] ) : 0;
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		if ( ! $user_id || ! $code ) { wp_send_json_error( 'missing_fields' ); }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_user_2fa';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
		if ( ! $row ) { wp_send_json_error( 'no_phone_on_file' ); }

		$check = $this->check_verification( $row->phone_number, $code );
		if ( is_wp_error( $check ) ) { wp_send_json_error( $check->get_error_message() ); }

		$wpdb->update( $table, array( 'verified' => 1 ), array( 'user_id' => $user_id ) );

		// Finalize WP session now that 2FA passed.
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true );
			do_action( 'wp_login', $user->user_login, $user );
		}
		wp_send_json_success( array( 'redirect' => home_url( '/hoa-dashboard/' ) ) );
	}
}
