<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_hoa_get_reserve_health', array( $this, 'ajax_get_reserve_health' ) );
	}

	public function ajax_get_reserve_health() {
		check_ajax_referer( 'hoa_dash_nonce', 'nonce' );
		if ( ! current_user_can( 'hoa_view_reserves' ) ) { wp_send_json_error( 'forbidden' ); }
		wp_send_json_success( HOA_Dash_Reserves::get_health_summary() );
	}
}
