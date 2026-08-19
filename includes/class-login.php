<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Front-end [hoa_login] shortcode + AJAX handler.
 * Step 1: verify username/password via wp_authenticate() WITHOUT setting auth cookies.
 * Step 2: if the user has a verified phone on file, require Twilio code (handled in class-2fa-twilio.php).
 *         If no phone is enrolled yet, log them straight in and prompt them to enroll from the dashboard.
 */
class HOA_Dash_Login {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_hoa_login_step1', array( $this, 'ajax_login_step1' ) );
	}

	public function ajax_login_step1() {
		check_ajax_referer( 'hoa_dash_login_nonce', 'nonce' );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';

		$user = wp_authenticate( $username, $password );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( __( 'Invalid username or password.', 'hoa-dashboard' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_user_2fa';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND verified = 1", $user->ID ) );

		if ( $row ) {
			// Require 2FA
			wp_send_json_success( array(
				'requires_2fa' => true,
				'pending_uid'  => $user->ID,
			) );
		} else {
			// No 2FA enrolled yet — log in, but flag to prompt enrollment.
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $user->user_login, $user );
			wp_send_json_success( array(
				'requires_2fa' => false,
				'redirect'     => home_url( '/hoa-dashboard/' ),
				'prompt_2fa_enroll' => true,
			) );
		}
	}
}
