<?php
/**
 * HOA Map — module bootstrap.
 *
 * Registers:
 *  - [hoa_map] front-end shortcode (role-gated)
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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Dashboard_Map {

	const SHORTCODE = 'hoa_map';

	public static function init() {
		add_action( 'rest_api_init', array( 'HOA_Map_REST', 'register_routes' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_front_end' ) );
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

	public static function enqueue_assets() {
		// Leaflet core (CDN — swap for a bundled copy if you prefer no
		// external dependency at runtime).
		wp_enqueue_style( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', array(), '1.9.4', true );

		// Leaflet.draw — polygon/point drawing tools for board/PM to trace
		// the HOA boundary, common areas, and assets by hand.
		wp_enqueue_style( 'leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css', array( 'leaflet' ), '1.0.4' );
		wp_enqueue_script( 'leaflet-draw', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js', array( 'leaflet' ), '1.0.4', true );

		wp_enqueue_style(
			'hoa-map',
			HOA_MAP_PLUGIN_URL . 'assets/css/hoa-map.css',
			array( 'leaflet', 'leaflet-draw' ),
			HOA_MAP_VERSION
		);

		wp_enqueue_script(
			'hoa-map',
			HOA_MAP_PLUGIN_URL . 'assets/js/hoa-map.js',
			array( 'leaflet', 'leaflet-draw' ),
			HOA_MAP_VERSION,
			true
		);

		$lat  = get_option( 'hoa_dash_map_lat', '33.7701' );
		$lng  = get_option( 'hoa_dash_map_lng', '-118.1937' );
		$zoom = get_option( 'hoa_dash_map_zoom', '18' );

		wp_localize_script( 'hoa-map', 'HOA_MAP_CONFIG', array(
			'restUrl'   => esc_url_raw( rest_url( 'hoa-dashboard/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'isManager' => HOA_Map_Roles::can_manage_common_areas(),
			'userId'    => get_current_user_id(),
			'center'    => array( floatval( $lat ), floatval( $lng ) ),
			'zoom'      => intval( $zoom ),
		) );
	}
}
