<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Public, unauthenticated config endpoint for client apps (iOS, etc.).
 *
 * This lets the mobile app be configured entirely from the board's existing
 * *HOA Dashboard > Settings* screen in wp-admin, instead of any of this
 * information being hardcoded in the app itself:
 *
 *   - The app only needs to know your site's URL (entered once by the
 *     member/board on first launch, or pre-set by whoever builds/distributes
 *     the app for your association).
 *   - Everything else the app needs before login — the association's name,
 *     which payment gateway is active, and that gateway's PUBLISHABLE key —
 *     it fetches from this endpoint at runtime.
 *
 * Security note: only the public/publishable payment key is ever returned
 * here. The secret key (`hoa_dash_payment_secret_key`) is never exposed by
 * any REST route — it's used exclusively server-side when a real gateway
 * charge is implemented in class-dues-payments.php / class-rest-data.php.
 *
 * GET /wp-json/hoa/v1/config
 */
class HOA_Dash_REST_Config {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'hoa/v1', '/config', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_config' ),
			'permission_callback' => '__return_true', // public: the app needs this before a user can log in
		) );
	}

	public function get_config() {
		return array(
			'hoa_name'              => HOA_Dash_Settings::get( 'hoa_dash_hoa_name', get_bloginfo( 'name' ) ),
			'pm_firm_name'          => HOA_Dash_Settings::get( 'hoa_dash_pm_firm_name' ),
			'payment_gateway'       => HOA_Dash_Settings::get( 'hoa_dash_payment_gateway', 'stripe' ),
			'payment_public_key'    => HOA_Dash_Settings::get( 'hoa_dash_payment_public_key' ),
			'card_fee_pct'          => (float) HOA_Dash_Settings::get( 'hoa_dash_card_fee_pct', 2.9 ),
			'card_fee_flat'         => (float) HOA_Dash_Settings::get( 'hoa_dash_card_fee_flat', 0.30 ),
			'ach_fee_flat'          => (float) HOA_Dash_Settings::get( 'hoa_dash_ach_fee_flat', 1.00 ),
			'state_disclosure_note' => HOA_Dash_Settings::get( 'hoa_dash_state_disclosure_note' ),
			'plugin_version'        => HOA_DASH_VERSION,
		);
	}
}
