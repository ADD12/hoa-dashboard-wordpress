<?php
/**
 * HOA Map — REST API controller.
 *
 * Namespace: hoa-dashboard/v1
 *
 * Routes:
 *   GET  /map/zones                 list common area zones (+ private parcels via /map/parcels)
 *   GET  /map/assets                list point/line assets, optional ?zone_code=
 *   GET  /map/parcels               list private parcel polygons
 *   GET  /map/tickets                list tickets (scoped to self for members)
 *   POST /map/tickets                file a new maintenance ticket
 *   PATCH /map/tickets/{id}          update ticket status (board/PM only)
 *   GET  /map/solar-estimate/{zone_code}  rough solar canopy sizing estimate
 *   POST /map/broadcast              geo-fenced notice to lots adjacent to a zone (board/PM only)
 *
 * Role/capability checks are centralized in HOA_Map_Roles — adjust that
 * class if your capability names differ from the placeholders here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Map_REST {

	const NAMESPACE_ = 'hoa-dashboard/v1';

	public static function register_routes() {
		register_rest_route( self::NAMESPACE_, '/map/zones', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_zones' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_view_map' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/assets', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_assets' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_view_map' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/parcels', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_parcels' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_view_map' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/tickets', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_tickets' ),
				'permission_callback' => array( 'HOA_Map_Roles', 'can_view_map' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_ticket' ),
				'permission_callback' => array( 'HOA_Map_Roles', 'can_view_map' ), // any logged-in member can file
			),
		) );

		register_rest_route( self::NAMESPACE_, '/map/tickets/(?P<id>\d+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'update_ticket' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
			'args'                => array(
				'id' => array( 'validate_callback' => function( $param ) {
					return is_numeric( $param );
				} ),
			),
		) );

		register_rest_route( self::NAMESPACE_, '/map/solar-estimate/(?P<zone_code>[a-zA-Z0-9\-_]+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'solar_estimate' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/broadcast', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'broadcast' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		// ---------- Write endpoints (board/PM only — drawing tool) ----------

		register_rest_route( self::NAMESPACE_, '/map/zones', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_zone' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/zones/(?P<zone_code>[a-zA-Z0-9\-_]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_zone' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/assets', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_asset' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/assets/(?P<asset_tag>[a-zA-Z0-9\-_]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_asset' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/parcels', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_parcel' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/parcels/(?P<parcel_code>[a-zA-Z0-9\-_]+)', array(
			'methods'             => 'DELETE',
			'callback'            => array( __CLASS__, 'delete_parcel' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );

		register_rest_route( self::NAMESPACE_, '/map/parcels/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'import_parcels' ),
			'permission_callback' => array( 'HOA_Map_Roles', 'can_manage_common_areas' ),
		) );
	}

	public static function save_zone( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		if ( empty( $p['zone_code'] ) || empty( $p['zone_name'] ) || empty( $p['zone_type'] ) || empty( $p['geojson'] ) ) {
			return new WP_Error( 'hoa_map_invalid_zone', __( 'zone_code, zone_name, zone_type, and geojson are required.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}
		$id = HOA_Map_DB::upsert_zone( array(
			'zone_code'          => sanitize_text_field( $p['zone_code'] ),
			'zone_name'          => sanitize_text_field( $p['zone_name'] ),
			'zone_type'          => sanitize_text_field( $p['zone_type'] ),
			'surface_sqft'       => isset( $p['surface_sqft'] ) ? floatval( $p['surface_sqft'] ) : null,
			'solar_potential_kw' => isset( $p['solar_potential_kw'] ) ? floatval( $p['solar_potential_kw'] ) : null,
			'geojson'            => $p['geojson'],
		) );
		return rest_ensure_response( array( 'id' => $id, 'zone_code' => $p['zone_code'] ) );
	}

	public static function delete_zone( WP_REST_Request $request ) {
		HOA_Map_DB::delete_zone( $request->get_param( 'zone_code' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function save_asset( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		if ( empty( $p['asset_tag'] ) || empty( $p['asset_type'] ) || empty( $p['geojson'] ) ) {
			return new WP_Error( 'hoa_map_invalid_asset', __( 'asset_tag, asset_type, and geojson are required.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}
		$id = HOA_Map_DB::upsert_asset( array(
			'zone_code'      => isset( $p['zone_code'] ) ? sanitize_text_field( $p['zone_code'] ) : null,
			'asset_tag'      => sanitize_text_field( $p['asset_tag'] ),
			'asset_type'     => sanitize_text_field( $p['asset_type'] ),
			'status'         => isset( $p['status'] ) ? sanitize_text_field( $p['status'] ) : 'operational',
			'last_inspected' => isset( $p['last_inspected'] ) ? sanitize_text_field( $p['last_inspected'] ) : null,
			'geojson'        => $p['geojson'],
		) );
		return rest_ensure_response( array( 'id' => $id, 'asset_tag' => $p['asset_tag'] ) );
	}

	public static function delete_asset( WP_REST_Request $request ) {
		HOA_Map_DB::delete_asset( $request->get_param( 'asset_tag' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function save_parcel( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		if ( empty( $p['parcel_code'] ) || empty( $p['geojson'] ) ) {
			return new WP_Error( 'hoa_map_invalid_parcel', __( 'parcel_code and geojson are required.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}
		$id = HOA_Map_DB::upsert_parcel( array(
			'parcel_code' => sanitize_text_field( $p['parcel_code'] ),
			'address'     => isset( $p['address'] ) ? sanitize_text_field( $p['address'] ) : null,
			'geojson'     => $p['geojson'],
		) );
		return rest_ensure_response( array( 'id' => $id, 'parcel_code' => $p['parcel_code'] ) );
	}

	public static function delete_parcel( WP_REST_Request $request ) {
		HOA_Map_DB::delete_parcel( $request->get_param( 'parcel_code' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Bulk import parcels from a pasted/uploaded GeoJSON FeatureCollection
	 * (e.g. exported from a county assessor's open GIS data portal), so
	 * parcel boundaries can match official records for audit purposes.
	 */
	public static function import_parcels( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		if ( empty( $p['geojson']['features'] ) || ! is_array( $p['geojson']['features'] ) ) {
			return new WP_Error( 'hoa_map_invalid_import', __( 'Expected a GeoJSON FeatureCollection with a features array.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}
		$result = HOA_Map_DB::bulk_import_parcels( $p['geojson']['features'] );
		return rest_ensure_response( $result );
	}

	public static function get_zones( WP_REST_Request $request ) {
		$rows = HOA_Map_DB::get_zones();
		foreach ( $rows as &$row ) {
			$row['geojson'] = json_decode( $row['geojson'] );
		}
		return rest_ensure_response( $rows );
	}

	public static function get_assets( WP_REST_Request $request ) {
		$zone_code = $request->get_param( 'zone_code' );
		$rows = HOA_Map_DB::get_assets( $zone_code ?: null );
		foreach ( $rows as &$row ) {
			$row['geojson'] = json_decode( $row['geojson'] );
		}
		return rest_ensure_response( $rows );
	}

	public static function get_parcels( WP_REST_Request $request ) {
		$rows = HOA_Map_DB::get_parcels();
		$is_manager = HOA_Map_Roles::can_manage_common_areas( $request );
		foreach ( $rows as &$row ) {
			$row['geojson'] = json_decode( $row['geojson'] );
			// Members see anonymized parcels — no owner id or address exposed.
			if ( ! $is_manager ) {
				unset( $row['owner_user_id'], $row['address'] );
			}
		}
		return rest_ensure_response( $rows );
	}

	public static function get_tickets( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$is_manager = HOA_Map_Roles::can_manage_common_areas( $request );
		$rows = $is_manager ? HOA_Map_DB::get_tickets() : HOA_Map_DB::get_tickets( $user_id );
		return rest_ensure_response( $rows );
	}

	public static function create_ticket( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$issue_type  = sanitize_text_field( $params['issue_type'] ?? '' );
		$description = sanitize_textarea_field( $params['description'] ?? '' );
		$zone_code   = sanitize_text_field( $params['zone_code'] ?? '' );
		$asset_tag   = sanitize_text_field( $params['asset_tag'] ?? '' );

		if ( empty( $issue_type ) || ( empty( $zone_code ) && empty( $asset_tag ) ) ) {
			return new WP_Error(
				'hoa_map_invalid_ticket',
				__( 'A ticket needs an issue type and either a zone or asset reference.', 'hoa-dashboard' ),
				array( 'status' => 400 )
			);
		}

		$ticket_id = HOA_Map_DB::insert_ticket( array(
			'zone_code'   => $zone_code ?: null,
			'asset_tag'   => $asset_tag ?: null,
			'reported_by' => get_current_user_id(),
			'issue_type'  => $issue_type,
			'description' => $description,
		) );

		/**
		 * Fires after a maintenance ticket is filed from the map.
		 * Hook into this to notify the board/PM (e.g. via your existing
		 * Twilio SMS integration).
		 */
		do_action( 'hoa_map_ticket_created', $ticket_id, $zone_code, $asset_tag );

		return rest_ensure_response( array( 'id' => $ticket_id, 'status' => 'open' ) );
	}

	public static function update_ticket( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$params = $request->get_json_params();
		$status = sanitize_text_field( $params['status'] ?? '' );

		$allowed = array( 'open', 'in_progress', 'resolved', 'closed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'hoa_map_invalid_status', __( 'Invalid ticket status.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}

		HOA_Map_DB::update_ticket_status( $id, $status );
		do_action( 'hoa_map_ticket_updated', $id, $status );

		return rest_ensure_response( array( 'id' => $id, 'status' => $status ) );
	}

	/**
	 * Very rough solar sizing estimate from surface square footage.
	 * Not a substitute for an actual site survey — surfaced as a
	 * planning-stage number for the board, per the source spec.
	 */
	public static function solar_estimate( WP_REST_Request $request ) {
		$zone_code = $request->get_param( 'zone_code' );
		$zone = HOA_Map_DB::get_zone( $zone_code );

		if ( ! $zone ) {
			return new WP_Error( 'hoa_map_zone_not_found', __( 'Zone not found.', 'hoa-dashboard' ), array( 'status' => 404 ) );
		}

		// Rough industry rule of thumb: ~15 W/sqft usable canopy footprint,
		// derated 75% for panel/aisle spacing. Configurable via filter.
		$watts_per_sqft = apply_filters( 'hoa_map_solar_watts_per_sqft', 15 );
		$usable_ratio   = apply_filters( 'hoa_map_solar_usable_ratio', 0.75 );

		$sqft = (float) $zone['surface_sqft'];
		$estimated_kw = round( ( $sqft * $usable_ratio * $watts_per_sqft ) / 1000, 1 );

		return rest_ensure_response( array(
			'zone_code'       => $zone_code,
			'surface_sqft'    => $sqft,
			'estimated_kw'    => $estimated_kw,
			'stored_kw'       => $zone['solar_potential_kw'],
			'note'            => __( 'Planning-stage estimate only; confirm with a site survey before capital budgeting.', 'hoa-dashboard' ),
		) );
	}

	/**
	 * Geo-fenced broadcast. This stub does the zone lookup and fires an
	 * action hook — wire your actual notification channel (email, SMS via
	 * Twilio, push) into 'hoa_map_broadcast' rather than editing this file.
	 */
	public static function broadcast( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$zone_code = sanitize_text_field( $params['zone_code'] ?? '' );
		$message   = sanitize_textarea_field( $params['message'] ?? '' );

		if ( empty( $zone_code ) || empty( $message ) ) {
			return new WP_Error( 'hoa_map_invalid_broadcast', __( 'A zone and message are required.', 'hoa-dashboard' ), array( 'status' => 400 ) );
		}

		$zone = HOA_Map_DB::get_zone( $zone_code );
		if ( ! $zone ) {
			return new WP_Error( 'hoa_map_zone_not_found', __( 'Zone not found.', 'hoa-dashboard' ), array( 'status' => 404 ) );
		}

		/**
		 * Fires when a board/PM sends a geo-fenced broadcast for a zone.
		 * Consumers should resolve "adjacent lots" (via parcel geometry
		 * proximity, or a simpler zone->parcel mapping table) and send
		 * through the plugin's existing notification channel.
		 */
		do_action( 'hoa_map_broadcast', $zone_code, $message, get_current_user_id() );

		return rest_ensure_response( array( 'sent' => true, 'zone_code' => $zone_code ) );
	}
}
