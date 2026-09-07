<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central settings registration. All secrets (Twilio, Stripe/payment gateway)
 * are stored as WP options and should be entered by a site administrator in
 * wp-admin. Nothing here ships with live credentials.
 */
class HOA_Dash_Settings {

	const OPT_GROUP = 'hoa_dash_settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting( self::OPT_GROUP, 'hoa_dash_twilio_sid' );
		register_setting( self::OPT_GROUP, 'hoa_dash_twilio_auth_token' );
		register_setting( self::OPT_GROUP, 'hoa_dash_twilio_verify_service_sid' );

		register_setting( self::OPT_GROUP, 'hoa_dash_payment_gateway' ); // 'stripe' | 'authorizenet' | 'other'
		register_setting( self::OPT_GROUP, 'hoa_dash_payment_public_key' );
		register_setting( self::OPT_GROUP, 'hoa_dash_payment_secret_key' );
		register_setting( self::OPT_GROUP, 'hoa_dash_card_fee_pct' );     // e.g. 2.9
		register_setting( self::OPT_GROUP, 'hoa_dash_card_fee_flat' );    // e.g. 0.30
		register_setting( self::OPT_GROUP, 'hoa_dash_ach_fee_flat' );     // e.g. 1.00

		register_setting( self::OPT_GROUP, 'hoa_dash_hoa_name' );
		register_setting( self::OPT_GROUP, 'hoa_dash_pm_firm_name' );
		register_setting( self::OPT_GROUP, 'hoa_dash_state_disclosure_note' );
		register_setting( self::OPT_GROUP, 'hoa_dash_newsletter_from_email' );

		register_setting( self::OPT_GROUP, 'hoa_dash_map_address' );
		register_setting( self::OPT_GROUP, 'hoa_dash_map_lat' );
		register_setting( self::OPT_GROUP, 'hoa_dash_map_lng' );
		register_setting( self::OPT_GROUP, 'hoa_dash_map_zoom' );
	}

	public static function get( $key, $default = '' ) {
		return get_option( $key, $default );
	}
}
