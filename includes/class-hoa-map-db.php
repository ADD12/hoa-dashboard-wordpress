<?php
/**
 * HOA Map — Database layer.
 *
 * Creates and manages custom tables for common areas, common assets,
 * private parcels, and maintenance tickets.
 *
 * NOTE: No PostGIS assumed. Geometry is stored as GeoJSON text and
 * rendered client-side via Leaflet's L.geoJSON(). If you later add
 * MySQL 8 spatial columns / functions, this is the file to extend.
 *
 * Call HOA_Map_DB::install() from your main plugin's activation hook.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HOA_Map_DB {

	const DB_VERSION = '1.0.0';
	const DB_VERSION_OPTION = 'hoa_map_db_version';

	public static function table_common_areas() {
		global $wpdb;
		return $wpdb->prefix . 'hoa_common_areas';
	}

	public static function table_common_assets() {
		global $wpdb;
		return $wpdb->prefix . 'hoa_common_assets';
	}

	public static function table_parcels() {
		global $wpdb;
		return $wpdb->prefix . 'hoa_parcels';
	}

	public static function table_tickets() {
		global $wpdb;
		return $wpdb->prefix . 'hoa_maintenance_tickets';
	}

	/**
	 * Create/upgrade tables. Idempotent — safe to call on every activation.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$common_areas = self::table_common_areas();
		$common_assets = self::table_common_assets();
		$parcels = self::table_parcels();
		$tickets = self::table_tickets();

		$sql = "CREATE TABLE {$common_areas} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			zone_code VARCHAR(30) NOT NULL,
			zone_name VARCHAR(100) NOT NULL,
			zone_type VARCHAR(50) NOT NULL,
			surface_sqft DECIMAL(10,2) DEFAULT NULL,
			solar_potential_kw DECIMAL(6,2) DEFAULT NULL,
			geojson LONGTEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY zone_code (zone_code)
		) {$charset_collate};

		CREATE TABLE {$common_assets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			zone_code VARCHAR(30) DEFAULT NULL,
			asset_tag VARCHAR(50) NOT NULL,
			asset_type VARCHAR(50) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'operational',
			last_inspected DATE DEFAULT NULL,
			geojson LONGTEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY asset_tag (asset_tag),
			KEY zone_code (zone_code)
		) {$charset_collate};

		CREATE TABLE {$parcels} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			parcel_code VARCHAR(30) NOT NULL,
			owner_user_id BIGINT UNSIGNED DEFAULT NULL,
			address VARCHAR(255) DEFAULT NULL,
			geojson LONGTEXT NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY parcel_code (parcel_code),
			KEY owner_user_id (owner_user_id)
		) {$charset_collate};

		CREATE TABLE {$tickets} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			zone_code VARCHAR(30) DEFAULT NULL,
			asset_tag VARCHAR(50) DEFAULT NULL,
			reported_by BIGINT UNSIGNED DEFAULT NULL,
			issue_type VARCHAR(50) DEFAULT NULL,
			description TEXT,
			status VARCHAR(30) NOT NULL DEFAULT 'open',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY zone_code (zone_code),
			KEY asset_tag (asset_tag),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/** Fetch all common area zones as an array of assoc rows. */
	public static function get_zones() {
		global $wpdb;
		$table = self::table_common_areas();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY zone_name ASC", ARRAY_A );
	}

	public static function get_zone( $zone_code ) {
		global $wpdb;
		$table = self::table_common_areas();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE zone_code = %s", $zone_code ),
			ARRAY_A
		);
	}

	public static function get_assets( $zone_code = null ) {
		global $wpdb;
		$table = self::table_common_assets();
		if ( $zone_code ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE zone_code = %s ORDER BY asset_tag ASC", $zone_code ),
				ARRAY_A
			);
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY asset_tag ASC", ARRAY_A );
	}

	public static function get_parcels() {
		global $wpdb;
		$table = self::table_parcels();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY parcel_code ASC", ARRAY_A );
	}

	/**
	 * Tickets. $scope_user_id = null returns all (board/PM use); pass a
	 * user id to restrict to that reporter (member self-view).
	 */
	public static function get_tickets( $scope_user_id = null ) {
		global $wpdb;
		$table = self::table_tickets();
		if ( $scope_user_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE reported_by = %d ORDER BY created_at DESC", $scope_user_id ),
				ARRAY_A
			);
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
	}

	public static function insert_ticket( array $data ) {
		global $wpdb;
		$table = self::table_tickets();
		$wpdb->insert(
			$table,
			array(
				'zone_code'   => $data['zone_code'] ?? null,
				'asset_tag'   => $data['asset_tag'] ?? null,
				'reported_by' => $data['reported_by'] ?? null,
				'issue_type'  => $data['issue_type'] ?? null,
				'description' => $data['description'] ?? '',
				'status'      => 'open',
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return $wpdb->insert_id;
	}

	public static function update_ticket_status( $ticket_id, $status ) {
		global $wpdb;
		$table = self::table_tickets();
		return $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $ticket_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// ---------- Zones (common areas + HOA boundary) ----------

	public static function upsert_zone( array $data ) {
		global $wpdb;
		$table = self::table_common_areas();
		$existing = self::get_zone( $data['zone_code'] );

		$row = array(
			'zone_code'          => $data['zone_code'],
			'zone_name'          => $data['zone_name'],
			'zone_type'          => $data['zone_type'],
			'surface_sqft'       => isset( $data['surface_sqft'] ) ? $data['surface_sqft'] : null,
			'solar_potential_kw' => isset( $data['solar_potential_kw'] ) ? $data['solar_potential_kw'] : null,
			'geojson'            => wp_json_encode( $data['geojson'] ),
		);
		$fmt = array( '%s', '%s', '%s', '%f', '%f', '%s' );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'zone_code' => $data['zone_code'] ), $fmt, array( '%s' ) );
			return $existing['id'];
		}
		$wpdb->insert( $table, $row, $fmt );
		return $wpdb->insert_id;
	}

	public static function delete_zone( $zone_code ) {
		global $wpdb;
		return $wpdb->delete( self::table_common_areas(), array( 'zone_code' => $zone_code ), array( '%s' ) );
	}

	// ---------- Assets ----------

	public static function upsert_asset( array $data ) {
		global $wpdb;
		$table = self::table_common_assets();
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE asset_tag = %s", $data['asset_tag'] ),
			ARRAY_A
		);

		$row = array(
			'zone_code'      => isset( $data['zone_code'] ) ? $data['zone_code'] : null,
			'asset_tag'      => $data['asset_tag'],
			'asset_type'     => $data['asset_type'],
			'status'         => isset( $data['status'] ) ? $data['status'] : 'operational',
			'last_inspected' => isset( $data['last_inspected'] ) ? $data['last_inspected'] : null,
			'geojson'        => wp_json_encode( $data['geojson'] ),
		);
		$fmt = array( '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'asset_tag' => $data['asset_tag'] ), $fmt, array( '%s' ) );
			return $existing['id'];
		}
		$wpdb->insert( $table, $row, $fmt );
		return $wpdb->insert_id;
	}

	public static function delete_asset( $asset_tag ) {
		global $wpdb;
		return $wpdb->delete( self::table_common_assets(), array( 'asset_tag' => $asset_tag ), array( '%s' ) );
	}

	// ---------- Parcels ----------

	public static function upsert_parcel( array $data ) {
		global $wpdb;
		$table = self::table_parcels();
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE parcel_code = %s", $data['parcel_code'] ),
			ARRAY_A
		);

		$row = array(
			'parcel_code'   => $data['parcel_code'],
			'owner_user_id' => isset( $data['owner_user_id'] ) ? $data['owner_user_id'] : null,
			'address'       => isset( $data['address'] ) ? $data['address'] : null,
			'geojson'       => wp_json_encode( $data['geojson'] ),
		);
		$fmt = array( '%s', '%d', '%s', '%s' );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'parcel_code' => $data['parcel_code'] ), $fmt, array( '%s' ) );
			return $existing['id'];
		}
		$wpdb->insert( $table, $row, $fmt );
		return $wpdb->insert_id;
	}

	public static function delete_parcel( $parcel_code ) {
		global $wpdb;
		return $wpdb->delete( self::table_parcels(), array( 'parcel_code' => $parcel_code ), array( '%s' ) );
	}

	/**
	 * Bulk-import parcels from a GeoJSON FeatureCollection, e.g. exported
	 * from a county assessor's open-data GIS portal. Each feature's
	 * properties should include a parcel identifier under one of:
	 * 'parcel_code', 'APN', 'apn', or 'PARCEL_ID' — first match wins.
	 * Returns counts so the UI can report results.
	 */
	public static function bulk_import_parcels( array $features ) {
		$imported = 0;
		$skipped  = 0;
		foreach ( $features as $feature ) {
			if ( empty( $feature['geometry'] ) ) {
				$skipped++;
				continue;
			}
			$props = isset( $feature['properties'] ) ? $feature['properties'] : array();
			$code  = null;
			foreach ( array( 'parcel_code', 'APN', 'apn', 'PARCEL_ID' ) as $key ) {
				if ( ! empty( $props[ $key ] ) ) {
					$code = sanitize_text_field( (string) $props[ $key ] );
					break;
				}
			}
			if ( ! $code ) {
				$skipped++;
				continue;
			}
			self::upsert_parcel( array(
				'parcel_code' => $code,
				'address'     => isset( $props['address'] ) ? sanitize_text_field( $props['address'] ) : ( isset( $props['SITUS_ADDR'] ) ? sanitize_text_field( $props['SITUS_ADDR'] ) : null ),
				'geojson'     => $feature['geometry'],
			) );
			$imported++;
		}
		return array( 'imported' => $imported, 'skipped' => $skipped );
	}
}
