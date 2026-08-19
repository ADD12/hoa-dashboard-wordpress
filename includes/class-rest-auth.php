<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST API authentication for the companion iOS app.
 *
 * WordPress cookie auth doesn't work for native apps, so this issues a
 * long-lived, revocable "app token" (a random string, stored hashed, tied
 * to a user + device) after a successful username/password + Twilio 2FA
 * check. The app then sends that token as a Bearer header on every request;
 * `authenticate_app_token()` maps it back to a WP user for the duration of
 * the REST request only (no cookies, no nonces).
 *
 * Namespace: hoa/v1
 *   POST /wp-json/hoa/v1/auth/login          { username, password }
 *   POST /wp-json/hoa/v1/auth/2fa/verify     { pending_uid, code }
 *   POST /wp-json/hoa/v1/auth/logout         (revokes the current token)
 */
class HOA_Dash_REST_Auth {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'determine_current_user', array( $this, 'authenticate_app_token' ), 20 );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_auth_errors' ) );
	}

	public function register_routes() {
		register_rest_route( 'hoa/v1', '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'login' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'hoa/v1', '/auth/2fa/verify', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'verify_2fa' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'hoa/v1', '/auth/2fa/resend', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'resend_2fa' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'hoa/v1', '/auth/logout', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'logout' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
		register_rest_route( 'hoa/v1', '/auth/me', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'me' ),
			'permission_callback' => function () { return is_user_logged_in(); },
		) );
	}

	/** Step 1: verify credentials. If 2FA is enrolled, hand back a pending_uid instead of a token. */
	public function login( WP_REST_Request $req ) {
		$username = sanitize_user( $req->get_param( 'username' ) );
		$password = (string) $req->get_param( 'password' );
		$device_name = sanitize_text_field( (string) $req->get_param( 'device_name' ) );

		$user = wp_authenticate( $username, $password );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'invalid_credentials', 'Invalid username or password.', array( 'status' => 401 ) );
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}hoa_user_2fa WHERE user_id=%d AND verified=1", $user->ID
		) );

		if ( $row ) {
			$twilio = new HOA_Dash_2FA_Twilio();
			$sent = $twilio->send_verification( $row->phone_number );
			if ( is_wp_error( $sent ) ) {
				return new WP_Error( 'sms_failed', $sent->get_error_message(), array( 'status' => 500 ) );
			}
			return array( 'requires_2fa' => true, 'pending_uid' => $user->ID );
		}

		$token = self::issue_token( $user->ID, $device_name );
		return array( 'requires_2fa' => false, 'token' => $token, 'user' => self::user_payload( $user ) );
	}

	public function resend_2fa( WP_REST_Request $req ) {
		$user_id = absint( $req->get_param( 'pending_uid' ) );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hoa_user_2fa WHERE user_id=%d", $user_id ) );
		if ( ! $row ) { return new WP_Error( 'no_phone', 'No phone on file.', array( 'status' => 404 ) ); }
		$twilio = new HOA_Dash_2FA_Twilio();
		$sent = $twilio->send_verification( $row->phone_number );
		if ( is_wp_error( $sent ) ) { return new WP_Error( 'sms_failed', $sent->get_error_message(), array( 'status' => 500 ) ); }
		return array( 'sent' => true );
	}

	/** Step 2: verify the SMS code, then issue the app token. */
	public function verify_2fa( WP_REST_Request $req ) {
		$user_id = absint( $req->get_param( 'pending_uid' ) );
		$code    = sanitize_text_field( (string) $req->get_param( 'code' ) );
		$device_name = sanitize_text_field( (string) $req->get_param( 'device_name' ) );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hoa_user_2fa WHERE user_id=%d", $user_id ) );
		if ( ! $row ) { return new WP_Error( 'no_phone', 'No phone on file.', array( 'status' => 404 ) ); }

		$twilio = new HOA_Dash_2FA_Twilio();
		$check = $twilio->check_verification( $row->phone_number, $code );
		if ( is_wp_error( $check ) ) {
			return new WP_Error( 'invalid_code', $check->get_error_message(), array( 'status' => 401 ) );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) { return new WP_Error( 'no_user', 'User not found.', array( 'status' => 404 ) ); }

		$token = self::issue_token( $user_id, $device_name );
		return array( 'token' => $token, 'user' => self::user_payload( $user ) );
	}

	public function logout( WP_REST_Request $req ) {
		$token = self::bearer_token_from_request( $req );
		if ( $token ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'hoa_app_tokens', array( 'token_hash' => hash( 'sha256', $token ) ) );
		}
		return array( 'logged_out' => true );
	}

	public function me( WP_REST_Request $req ) {
		$user = wp_get_current_user();
		return self::user_payload( $user );
	}

	public static function user_payload( $user ) {
		return array(
			'id'           => $user->ID,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => $user->roles,
			'capabilities' => array(
				'is_board' => user_can( $user, 'hoa_view_board_tools' ),
				'is_pm'    => user_can( $user, 'hoa_property_manager' ),
			),
		);
	}

	private static function issue_token( $user_id, $device_name ) {
		global $wpdb;
		$token = wp_generate_password( 48, false, false );
		$wpdb->insert( $wpdb->prefix . 'hoa_app_tokens', array(
			'user_id'     => $user_id,
			'token_hash'  => hash( 'sha256', $token ),
			'device_name' => $device_name ?: 'iOS device',
			'created_at'  => current_time( 'mysql' ),
			'last_used'   => current_time( 'mysql' ),
		) );
		return $token;
	}

	private static function bearer_token_from_request( $req ) {
		$header = $req->get_header( 'authorization' );
		if ( $header && stripos( $header, 'Bearer ' ) === 0 ) {
			return trim( substr( $header, 7 ) );
		}
		return null;
	}

	/** Maps a valid Bearer token to a WP user for this REST request only. */
	public function authenticate_app_token( $user_id ) {
		if ( $user_id ) { return $user_id; } // cookie auth already resolved it (e.g. wp-admin)
		if ( empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) { return $user_id; }

		$header = $_SERVER['HTTP_AUTHORIZATION'];
		if ( stripos( $header, 'Bearer ' ) !== 0 ) { return $user_id; }
		$token = trim( substr( $header, 7 ) );
		if ( ! $token ) { return $user_id; }

		global $wpdb;
		$table = $wpdb->prefix . 'hoa_app_tokens';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE token_hash=%s", hash( 'sha256', $token )
		) );
		if ( ! $row ) { return $user_id; }

		$wpdb->update( $table, array( 'last_used' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
		return (int) $row->user_id;
	}

	/** Let unauthenticated requests through only for the public auth endpoints; everything else needs a valid token. */
	public function rest_auth_errors( $result ) {
		if ( ! empty( $result ) ) { return $result; }
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( strpos( $route, '/hoa/v1/auth/login' ) === 0
			|| strpos( $route, '/hoa/v1/auth/2fa' ) === 0
			|| strpos( $route, '/hoa/v1/' ) !== 0 ) {
			return $result; // not our namespace, or a public auth route — leave alone
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', 'A valid app token is required.', array( 'status' => 401 ) );
		}
		return $result;
	}
}
