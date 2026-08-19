<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class HOA_Dash_Shortcodes {

	public function __construct() {
		add_shortcode( 'hoa_login', array( $this, 'render_login' ) );
		add_shortcode( 'hoa_dashboard', array( $this, 'render_dashboard' ) );
	}

	public function render_login( $atts ) {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'hoa-dashboard' ) . ' <a href="' . esc_url( home_url( '/hoa-dashboard/' ) ) . '">' . esc_html__( 'Go to Dashboard', 'hoa-dashboard' ) . '</a></p>';
		}
		ob_start();
		include HOA_DASH_PATH . 'templates/login.php';
		return ob_get_clean();
	}

	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view the HOA Dashboard.', 'hoa-dashboard' ) . '</p>';
		}
		ob_start();
		include HOA_DASH_PATH . 'templates/dashboard-shell.php';
		return ob_get_clean();
	}
}
