<?php
/**
 * HOA Map — module bootstrap.
 *
 * Registers:
 *  - wp-admin submenu page "HOA Map" (under your existing dashboard menu)
 *  - [hoa_map] front-end shortcode (same component, role-gated)
 *  - REST routes (see class-hoa-map-rest.php)
 *
 * INTEGRATION (in your main plugin file):
 *
 *   require_once __DIR__ . '/includes/class-hoa-map-db.php';
 *   require_once __DIR__ . '/includes/class-hoa-map-roles.php';
 *   require_once __DIR__ . '/includes/class-hoa-map-rest.php';
 *   require_once __DIR__ . '/includes/class-hoa-dashboard-map.php';
 *   HOA_Dashboard_Map::init();
 *
 *   register_activation_hook( __FILE__, array( 'HOA_Map_DB', 'install' ) );
 *
 * Adjust HOA_MAP_PARENT_SLUG below to match your existing top-level
 * admin menu slug so this appears as a tab under it rather than as its
 * own top-level menu item.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HOA_MAP_PARENT_SLUG' ) ) {
	// >>> EDIT: set this to your existing top-level menu slug, e.g. 'hoa-dashboard'
	define( 'HOA_MAP_PARENT_SLUG', 'hoa-dashboard' );
}

class HOA_Dashboard_Map {

	const SHORTCODE = 'hoa_map';
	const PAGE_SLUG = 'hoa-dashboard-map';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'rest_api_init', array( 'HOA_Map_REST', 'register_routes' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_front_end' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_admin' ) );
	}

	public static function register_admin_page() {
		add_submenu_page(
			HOA_MAP_PARENT_SLUG,
			__( 'HOA Map', 'hoa-dashboard' ),
			__( 'Map', 'hoa-dashboard' ),
			'read', // capability gate happens inside render via HOA_Map_Roles
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! HOA_Map_Roles::can_view_map() ) {
			wp_die( esc_html__( 'You do not have access to the HOA map.', 'hoa-dashboard' ) );
		}
		self::enqueue_assets();
		echo '<div class="wrap hoa-map-admin-wrap">';
		include HOA_MAP_PLUGIN_DIR . 'templates/map-page-template.php';
		echo '</div>';
	}

	public static function render_shortcode( $atts ) {
		if ( ! HOA_Map_Roles::can_view_map() ) {
			return '<p class="hoa-map-login-notice">' .
				esc_html__( 'Please log in as an HOA member to view the community map.', 'hoa-dashboard' ) .
				'</p>';
		}
		self::enqueue_assets();
		ob_start();
		include HOA_MAP_PLUGIN_DIR . 'templates/map-page-template.php';
		return ob_get_clean();
	}

	public static function maybe_enqueue_front_end() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			self::enqueue_assets();
		}
	}

	public static function maybe_enqueue_admin( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) !== false ) {
			self::enqueue_assets();
		}
	}

	public static function enqueue_assets() {
		// Leaflet core (CDN — swap for a bundled copy if you prefer no
		// external dependency at runtime).
		wp_enqueue_style( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array(), '1.9.4', true );

		wp_enqueue_style(
			'hoa-map',
			HOA_MAP_PLUGIN_URL . 'assets/css/hoa-map.css',
			array( 'leaflet' ),
			HOA_MAP_VERSION
		);

		wp_enqueue_script(
			'hoa-map',
			HOA_MAP_PLUGIN_URL . 'assets/js/hoa-map.js',
			array( 'leaflet' ),
			HOA_MAP_VERSION,
			true
		);

		wp_localize_script( 'hoa-map', 'HOA_MAP_CONFIG', array(
			'restUrl'   => esc_url_raw( rest_url( 'hoa-dashboard/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'isManager' => HOA_Map_Roles::can_manage_common_areas(),
			'userId'    => get_current_user_id(),
			// >>> EDIT: set to your community's actual map center / zoom
			'center'    => array( 33.7701, -118.1937 ),
			'zoom'      => 18,
		) );
	}
}
