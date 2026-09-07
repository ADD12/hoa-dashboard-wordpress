<?php
/**
 * Plugin Name: HOA Dashboard
 * Plugin URI:  https://example.com/hoa-dashboard
 * Description: Member & board dashboard for HOA financial health (CA reserve study compliance), dues/autopay, ticketing, PM oversight/audit log, calendar, and newsletters.
 * Version:     0.6.0
 * Author:      Your Organization
 * License:     GPLv2 or later
 * Text Domain: hoa-dashboard
 *
 * Versioning scheme: HOA_Dashboard.V.XXX.zip  (XXX = zero-padded build number)
 * This build corresponds to HOA_Dashboard.V.001.zip
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HOA_DASH_VERSION', '0.6.0' );
define( 'HOA_DASH_BUILD', '008' );
define( 'HOA_DASH_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOA_DASH_URL', plugin_dir_url( __FILE__ ) );
define( 'HOA_DASH_DB_VERSION', '1.0.0' );

// Core includes
require_once HOA_DASH_PATH . 'includes/class-activator.php';
require_once HOA_DASH_PATH . 'includes/class-page-installer.php';
require_once HOA_DASH_PATH . 'includes/class-documentation.php';
require_once HOA_DASH_PATH . 'includes/class-rest-auth.php';
require_once HOA_DASH_PATH . 'includes/class-rest-data.php';
require_once HOA_DASH_PATH . 'includes/class-rest-config.php';
require_once HOA_DASH_PATH . 'includes/class-roles.php';
require_once HOA_DASH_PATH . 'includes/class-settings.php';
require_once HOA_DASH_PATH . 'includes/class-2fa-twilio.php';
require_once HOA_DASH_PATH . 'includes/class-reserves.php';
require_once HOA_DASH_PATH . 'includes/class-dues-payments.php';
require_once HOA_DASH_PATH . 'includes/class-tickets.php';
require_once HOA_DASH_PATH . 'includes/class-calendar.php';
require_once HOA_DASH_PATH . 'includes/class-pm-audit.php';
require_once HOA_DASH_PATH . 'includes/class-pm-reviews.php';
require_once HOA_DASH_PATH . 'includes/class-newsletter.php';
require_once HOA_DASH_PATH . 'includes/class-admin-menu.php';
require_once HOA_DASH_PATH . 'includes/class-shortcodes.php';
require_once HOA_DASH_PATH . 'includes/class-ajax.php';
require_once HOA_DASH_PATH . 'includes/class-login.php';

// HOA Map module
if ( ! defined( 'HOA_MAP_PLUGIN_DIR' ) ) {
	define( 'HOA_MAP_PLUGIN_DIR', HOA_DASH_PATH );
}
if ( ! defined( 'HOA_MAP_PLUGIN_URL' ) ) {
	define( 'HOA_MAP_PLUGIN_URL', HOA_DASH_URL );
}
if ( ! defined( 'HOA_MAP_VERSION' ) ) {
	define( 'HOA_MAP_VERSION', HOA_DASH_VERSION );
}
require_once HOA_DASH_PATH . 'includes/class-hoa-map-db.php';
require_once HOA_DASH_PATH . 'includes/class-hoa-map-roles.php';
require_once HOA_DASH_PATH . 'includes/class-hoa-map-rest.php';
require_once HOA_DASH_PATH . 'includes/class-hoa-dashboard-map.php';
HOA_Dashboard_Map::init();

require_once HOA_DASH_PATH . 'includes/class-identity.php';
HOA_Identity::init();

require_once HOA_DASH_PATH . 'includes/class-multisite.php';
HOA_Multisite::init();

register_activation_hook( __FILE__, array( 'HOA_Dash_Activator', 'activate' ) );
register_activation_hook( __FILE__, array( 'HOA_Map_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'HOA_Dash_Activator', 'deactivate' ) );

/**
 * Master bootstrap
 */
final class HOA_Dashboard_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( 'HOA_Dash_Roles', 'maybe_register_roles' ) );
		add_action( 'init', array( 'HOA_Dash_Page_Installer', 'maybe_reinstall' ) );

		// Module init
		new HOA_Dash_Settings();
		new HOA_Dash_2FA_Twilio();
		new HOA_Dash_Reserves();
		new HOA_Dash_Dues_Payments();
		new HOA_Dash_Tickets();
		new HOA_Dash_Calendar();
		new HOA_Dash_PM_Audit();
		new HOA_Dash_PM_Reviews();
		new HOA_Dash_Newsletter();
		new HOA_Dash_Admin_Menu();
		new HOA_Dash_Shortcodes();
		new HOA_Dash_Documentation();
		new HOA_Dash_Ajax();
		new HOA_Dash_Login();
		new HOA_Dash_REST_Auth();
		new HOA_Dash_REST_Data();
		new HOA_Dash_REST_Config();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'add_cors_support' ) );
	}

	/**
	 * Allows the native iOS app (and any other REST client) to call
	 * wp-json/hoa/v1/* endpoints cross-origin. Native apps don't send an
	 * Origin header the way browsers do, so this mainly matters for testing
	 * the API from a browser/Postman during development.
	 */
	public function add_cors_support() {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', function ( $value ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
			return $value;
		} );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'hoa-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function enqueue_front_assets() {
		wp_register_style( 'hoa-dash-css', HOA_DASH_URL . 'assets/css/dashboard.css', array(), HOA_DASH_VERSION );
		wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', array(), '4.4.3', true );
		wp_register_script( 'hoa-dash-js', HOA_DASH_URL . 'assets/js/dashboard.js', array( 'jquery', 'chartjs' ), HOA_DASH_VERSION, true );

		wp_localize_script( 'hoa-dash-js', 'HOA_DASH', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hoa_dash_nonce' ),
			'is_board' => current_user_can( 'hoa_view_board_tools' ),
			'is_pm'    => current_user_can( 'hoa_property_manager' ),
		) );

		wp_enqueue_style( 'hoa-dash-css' );
		wp_enqueue_script( 'hoa-dash-js' );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'hoa-dash' ) === false ) { return; }
		wp_enqueue_style( 'hoa-dash-admin-css', HOA_DASH_URL . 'assets/css/dashboard.css', array(), HOA_DASH_VERSION );
		wp_enqueue_script( 'hoa-dash-js', HOA_DASH_URL . 'assets/js/dashboard.js', array( 'jquery' ), HOA_DASH_VERSION, true );
	}
}

HOA_Dashboard_Plugin::instance();
