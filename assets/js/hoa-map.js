/**
 * HOA Map — front-end controller.
 *
 * Requires Leaflet + Leaflet.draw (enqueued as dependencies) and
 * HOA_MAP_CONFIG, localized from PHP (rest URL, nonce, role flags,
 * map center/zoom).
 */
( function () {
	'use strict';

	if ( typeof L === 'undefined' || typeof HOA_MAP_CONFIG === 'undefined' ) {
		return;
	}

	var cfg = HOA_MAP_CONFIG;
	var map, drawer, drawerTitle, drawerSubtitle, drawerBody;
	var parcelLayer, parkingLayer, turfLayer, poolCourtLayer, assetPointLayer, boundaryLayer;

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		var mapEl = document.getElementById( 'hoa-map' );
		if ( ! mapEl ) {
			return;
		}

		drawer = document.getElementById( 'hoa-map-drawer' );
		drawerTitle = document.getElementById( 'hoa-map-drawer-title' );
		drawerSubtitle = document.getElementById( 'hoa-map-drawer-subtitle' );
		drawerBody = document.getElementById( 'hoa-map-drawer-body' );

		document.getElementById( 'hoa-map-drawer-close' ).addEventListener( 'click', closeDrawer );

		map = L.map( mapEl ).setView( cfg.center, cfg.zoom );

		var osmBaseLayer = L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 21,
		} ).addTo( map );

		var satelliteBaseLayer = L.tileLayer( 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
			attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
			maxZoom: 21,
		} );

		boundaryLayer   = L.geoJSON( null, { style: styleFor( 'hoa_boundary' ), onEachFeature: bindZone } );
		parcelLayer     = L.geoJSON( null, { style: styleFor( 'parcel' ), onEachFeature: bindParcel } );
		parkingLayer    = L.geoJSON( null, { style: styleFor( 'paved_parking' ), onEachFeature: bindZone } );
		turfLayer       = L.geoJSON( null, { style: styleFor( 'turf' ), onEachFeature: bindZone } );
		poolCourtLayer  = L.geoJSON( null, { style: styleFor( 'recreation' ), onEachFeature: bindZone } );
		assetPointLayer = L.geoJSON( null, { pointToLayer: assetToMarker, onEachFeature: bindAsset } );

		boundaryLayer.addTo( map );
		parkingLayer.addTo( map );
		turfLayer.addTo( map );
		poolCourtLayer.addTo( map );
		assetPointLayer.addTo( map );
		parcelLayer.addTo( map );

		L.control.layers(
			{ 'OpenStreetMap': osmBaseLayer, 'Satellite': satelliteBaseLayer },
			{
				'HOA Boundary': boundaryLayer,
				'Private Lots': parcelLayer,
				'Parking & Solar Areas': parkingLayer,
				'Common Greenbelts': turfLayer,
				'Pool, Spa & Courts': poolCourtLayer,
				'Pathway Lights & Trees': assetPointLayer,
			},
			{ collapsed: false }
		).addTo( map );

		loadZones();
		loadAssets();
		loadParcels();

		if ( cfg.isManager ) {
			initDrawingTools();
		}
	}

	// ---------- Drawing tools (board/PM only) ----------

	function initDrawingTools() {
		var editableLayer = new L.FeatureGroup().addTo( map );

		var drawControl = new L.Control.Draw( {
			position: 'topleft',
			draw: {
				polygon: { allowIntersection: false, showArea: true },
				marker: true,
				polyline: false,
				rectangle: false,
				circle: false,
				circlemarker: false,
			},
			edit: false, // reshaping existing boundaries is a future enhancement
		} );
		map.addControl( drawControl );

		map.on( L.Draw.Event.CREATED, function ( e ) {
			var layer = e.layer;
			var geojson = layer.toGeoJSON().geometry;
			editableLayer.addLayer( layer );
			openNewFeatureForm( e.layerType, geojson, function () {
				editableLayer.removeLayer( layer );
			} );
		} );

		addImportButton();
	}

	function openNewFeatureForm( layerType, geojson, onSavedOrCancelled ) {
		if ( layerType === 'marker' ) {
			openDrawer( 'New Asset', 'Point on the map' );
			drawerBody.innerHTML =
				'<div class="hoa-field"><label>Asset Type</label>' +
				'<select id="hoa-new-asset-type">' +
				'<option value="bollard_light">Pathway / Bollard Light</option>' +
				'<option value="tree">Tree</option>' +
				'<option value="ev_charger">EV Charger</option>' +
				'<option value="inverter">Solar Inverter</option>' +
				'</select></div>' +
				'<div class="hoa-field"><label>Asset Tag / ID</label>' +
				'<input type="text" id="hoa-new-asset-tag" placeholder="e.g. LIGHT-014"></div>' +
				'<div class="hoa-field"><label>Zone Code (optional)</label>' +
				'<input type="text" id="hoa-new-asset-zone" placeholder="e.g. PARK-A"></div>' +
				'<button class="hoa-btn" id="hoa-new-save">Save Asset</button> ' +
				'<button class="hoa-btn hoa-btn-secondary" id="hoa-new-cancel">Cancel</button>' +
				'<p id="hoa-new-status"></p>';

			document.getElementById( 'hoa-new-save' ).addEventListener( 'click', function () {
				var tag = document.getElementById( 'hoa-new-asset-tag' ).value.trim();
				if ( ! tag ) { setStatus( 'Asset tag is required.' ); return; }
				apiPost( '/map/assets', {
					asset_tag: tag,
					asset_type: document.getElementById( 'hoa-new-asset-type' ).value,
					zone_code: document.getElementById( 'hoa-new-asset-zone' ).value.trim() || null,
					geojson: geojson,
				} ).then( function () {
					assetPointLayer.addData( { type: 'Feature', geometry: geojson, properties: { asset_tag: tag, asset_type: document.getElementById( 'hoa-new-asset-type' ).value } } );
					onSavedOrCancelled();
					closeDrawer();
				} ).catch( function ( err ) { setStatus( errMsg( err ) ); } );
			} );

		} else { // polygon
			openDrawer( 'New Area', 'Trace the boundary you drew' );
			drawerBody.innerHTML =
				'<div class="hoa-field"><label>Area Type</label>' +
				'<select id="hoa-new-zone-type">' +
				'<option value="hoa_boundary">HOA Property Boundary</option>' +
				'<option value="paved_parking">Parking / Solar-Candidate Area</option>' +
				'<option value="turf">Common Greenbelt / Turf</option>' +
				'<option value="recreation">Pool, Spa, or Sport Court</option>' +
				'<option value="parcel">Private Lot (Parcel)</option>' +
				'</select></div>' +
				'<div class="hoa-field" id="hoa-new-zone-name-wrap"><label>Name</label>' +
				'<input type="text" id="hoa-new-zone-name" placeholder="e.g. North Parking Lot"></div>' +
				'<div class="hoa-field" id="hoa-new-zone-code-wrap"><label>Zone Code</label>' +
				'<input type="text" id="hoa-new-zone-code" placeholder="e.g. PARK-A"></div>' +
				'<div class="hoa-field hidden" id="hoa-new-parcel-fields">' +
				'<label>Parcel Code (APN)</label><input type="text" id="hoa-new-parcel-code" placeholder="e.g. 123-456-789">' +
				'<label>Address</label><input type="text" id="hoa-new-parcel-address" placeholder="Street address"></div>' +
				'<button class="hoa-btn" id="hoa-new-save">Save Area</button> ' +
				'<button class="hoa-btn hoa-btn-secondary" id="hoa-new-cancel">Cancel</button>' +
				'<p id="hoa-new-status"></p>';

			var typeSelect = document.getElementById( 'hoa-new-zone-type' );
			typeSelect.addEventListener( 'change', toggleParcelFields );
			toggleParcelFields();

			function toggleParcelFields() {
				var isParcel = typeSelect.value === 'parcel';
				document.getElementById( 'hoa-new-parcel-fields' ).classList.toggle( 'hidden', ! isParcel );
				document.getElementById( 'hoa-new-zone-name-wrap' ).classList.toggle( 'hidden', isParcel );
				document.getElementById( 'hoa-new-zone-code-wrap' ).classList.toggle( 'hidden', isParcel );
			}

			document.getElementById( 'hoa-new-save' ).addEventListener( 'click', function () {
				var type = typeSelect.value;

				if ( type === 'parcel' ) {
					var pCode = document.getElementById( 'hoa-new-parcel-code' ).value.trim();
					if ( ! pCode ) { setStatus( 'Parcel code (APN) is required.' ); return; }
					apiPost( '/map/parcels', {
						parcel_code: pCode,
						address: document.getElementById( 'hoa-new-parcel-address' ).value.trim() || null,
						geojson: geojson,
					} ).then( function () {
						parcelLayer.addData( { type: 'Feature', geometry: geojson, properties: { parcel_code: pCode } } );
						onSavedOrCancelled();
						closeDrawer();
					} ).catch( function ( err ) { setStatus( errMsg( err ) ); } );
					return;
				}

				var code = document.getElementById( 'hoa-new-zone-code' ).value.trim();
				var name = document.getElementById( 'hoa-new-zone-name' ).value.trim();
				if ( ! code || ! name ) { setStatus( 'Name and zone code are required.' ); return; }

				apiPost( '/map/zones', {
					zone_code: code,
					zone_name: name,
					zone_type: type,
					geojson: geojson,
				} ).then( function () {
					var feature = { type: 'Feature', geometry: geojson, properties: { zone_code: code, zone_name: name, zone_type: type, surface_sqft: 0 } };
					targetLayerFor( type ).addData( feature );
					onSavedOrCancelled();
					closeDrawer();
				} ).catch( function ( err ) { setStatus( errMsg( err ) ); } );
			} );
		}

		document.getElementById( 'hoa-new-cancel' ).addEventListener( 'click', function () {
			onSavedOrCancelled();
			closeDrawer();
		} );

		function setStatus( msg ) {
			var el = document.getElementById( 'hoa-new-status' );
			if ( el ) { el.textContent = msg; }
		}
	}

	function targetLayerFor( zoneType ) {
		if ( zoneType === 'hoa_boundary' ) { return boundaryLayer; }
		if ( zoneType === 'paved_parking' ) { return parkingLayer; }
		if ( zoneType === 'recreation' ) { return poolCourtLayer; }
		return turfLayer;
	}

	function addImportButton() {
		var ImportControl = L.Control.extend( {
			options: { position: 'topleft' },
			onAdd: function () {
				var container = L.DomUtil.create( 'div', 'leaflet-bar hoa-import-control' );
				var btn = L.DomUtil.create( 'a', '', container );
				btn.href = '#';
				btn.title = 'Import parcels from GeoJSON (e.g. county assessor data)';
				btn.innerHTML = '⤓';
				L.DomEvent.on( btn, 'click', function ( e ) {
					L.DomEvent.stop( e );
					openImportForm();
				} );
				return container;
			},
		} );
		map.addControl( new ImportControl() );
	}

	function openImportForm() {
		openDrawer( 'Import Parcels', 'Paste a GeoJSON FeatureCollection (e.g. exported from your county assessor\u2019s open GIS data portal)' );
		drawerBody.innerHTML =
			'<div class="hoa-field"><textarea id="hoa-import-geojson" rows="10" placeholder=\'{"type":"FeatureCollection","features":[...]}\'></textarea></div>' +
			'<p class="hoa-field-note">Each feature should have a parcel identifier property named <code>parcel_code</code>, <code>APN</code>, or <code>PARCEL_ID</code>, and an <code>address</code> or <code>SITUS_ADDR</code> property if available.</p>' +
			'<button class="hoa-btn" id="hoa-import-run">Import</button>' +
			'<p id="hoa-import-status"></p>';

		document.getElementById( 'hoa-import-run' ).addEventListener( 'click', function () {
			var status = document.getElementById( 'hoa-import-status' );
			var raw = document.getElementById( 'hoa-import-geojson' ).value.trim();
			var parsed;
			try {
				parsed = JSON.parse( raw );
			} catch ( e ) {
				status.textContent = 'That\u2019s not valid JSON — check the pasted text.';
				return;
			}
			status.textContent = 'Importing…';
			apiPost( '/map/parcels/import', { geojson: parsed } ).then( function ( result ) {
				status.textContent = 'Imported ' + result.imported + ' parcel(s), skipped ' + result.skipped + '.';
				loadParcels();
			} ).catch( function ( err ) {
				status.textContent = errMsg( err );
			} );
		} );
	}

	// ---------- Data loading ----------

	function apiGet( path ) {
		return fetch( cfg.restUrl + path, {
			headers: { 'X-WP-Nonce': cfg.nonce },
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'Request failed: ' + path );
			}
			return res.json();
		} );
	}

	function apiPost( path, body, method ) {
		return fetch( cfg.restUrl + path, {
			method: method || 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				return res.json().then( function ( err ) { throw err; } );
			}
			return res.json();
		} );
	}

	function errMsg( err ) {
		return ( err && err.message ) ? err.message : 'Something went wrong, please try again.';
	}

	function loadZones() {
		parkingLayer.clearLayers();
		turfLayer.clearLayers();
		poolCourtLayer.clearLayers();
		boundaryLayer.clearLayers();
		apiGet( '/map/zones' ).then( function ( zones ) {
			zones.forEach( function ( zone ) {
				var feature = {
					type: 'Feature',
					geometry: zone.geojson,
					properties: zone,
				};
				targetLayerFor( zone.zone_type ).addData( feature );
			} );
		} ).catch( console.error );
	}

	function loadAssets() {
		assetPointLayer.clearLayers();
		apiGet( '/map/assets' ).then( function ( assets ) {
			assets.forEach( function ( asset ) {
				assetPointLayer.addData( {
					type: 'Feature',
					geometry: asset.geojson,
					properties: asset,
				} );
			} );
		} ).catch( console.error );
	}

	function loadParcels() {
		parcelLayer.clearLayers();
		apiGet( '/map/parcels' ).then( function ( parcels ) {
			parcels.forEach( function ( parcel ) {
				parcelLayer.addData( {
					type: 'Feature',
					geometry: parcel.geojson,
					properties: parcel,
				} );
			} );
		} ).catch( console.error );
	}

	// ---------- Styling (per the doc's Asset Class table) ----------

	function styleFor( zoneType ) {
		var styles = {
			hoa_boundary:  { color: '#dc2626', fillOpacity: 0, weight: 3, dashArray: '8, 6' },
			parcel:        { color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: 0.25, weight: 1 },
			paved_parking: { color: '#2f4f4f', fillColor: '#2f4f4f', fillOpacity: 0.55, weight: 1 },
			turf:          { color: '#228b22', fillColor: '#228b22', fillOpacity: 0.45, weight: 1 },
			recreation:    { color: '#06b6d4', fillColor: '#06b6d4', fillOpacity: 0.45, weight: 1 },
		};
		return function () {
			return styles[ zoneType ] || styles.turf;
		};
	}

	function assetToMarker( feature, latlng ) {
		var type = feature.properties.asset_type;
		var colors = {
			bollard_light: '#fde047',
			tree: '#14532d',
			ev_charger: '#2f4f4f',
			inverter: '#f59e0b',
		};
		return L.circleMarker( latlng, {
			radius: type === 'tree' ? 6 : 5,
			color: colors[ type ] || '#666',
			fillColor: colors[ type ] || '#666',
			fillOpacity: 0.9,
			weight: 1,
		} );
	}

	// ---------- Feature click handlers ----------

	function bindParcel( feature, layer ) {
		var p = feature.properties;
		layer.bindTooltip( p.parcel_code, { sticky: true } );
		layer.on( 'click', function () {
			openParcelDrawer( p );
		} );
	}

	function bindZone( feature, layer ) {
		var z = feature.properties;
		layer.bindTooltip( z.zone_name, { sticky: true } );
		layer.on( 'click', function () {
			openZoneDrawer( z );
		} );
	}

	function bindAsset( feature, layer ) {
		var a = feature.properties;
		layer.bindTooltip( a.asset_tag, { sticky: true } );
		layer.on( 'click', function () {
			openAssetDrawer( a );
		} );
	}

	// ---------- Drawer content ----------

	function openDrawer( title, subtitle ) {
		drawerTitle.textContent = title;
		drawerSubtitle.textContent = subtitle || '';
		drawer.classList.remove( 'hidden' );
		drawer.setAttribute( 'aria-hidden', 'false' );
	}

	function closeDrawer() {
		drawer.classList.add( 'hidden' );
		drawer.setAttribute( 'aria-hidden', 'true' );
	}

	function deleteButtonHtml() {
		return '<button class="hoa-btn hoa-btn-danger" id="hoa-delete-feature">Delete from map</button>';
	}

	function openParcelDrawer( parcel ) {
		openDrawer( parcel.parcel_code, parcel.address || '' );
		if ( cfg.isManager ) {
			drawerBody.innerHTML =
				'<div class="hoa-field"><label>Message to this member</label>' +
				'<textarea id="hoa-dm-message" rows="4" placeholder="Notice or message..."></textarea></div>' +
				'<button class="hoa-btn" id="hoa-dm-send">Send Notice</button>' +
				deleteButtonHtml();
			document.getElementById( 'hoa-dm-send' ).addEventListener( 'click', function () {
				var message = document.getElementById( 'hoa-dm-message' ).value.trim();
				if ( ! message ) { return; }
				apiPost( '/map/broadcast', { zone_code: parcel.parcel_code, message: message } )
					.then( function () { alert( 'Notice sent.' ); closeDrawer(); } )
					.catch( function () { alert( 'Could not send notice.' ); } );
			} );
			document.getElementById( 'hoa-delete-feature' ).addEventListener( 'click', function () {
				if ( ! confirm( 'Remove this parcel from the map?' ) ) { return; }
				apiPost( '/map/parcels/' + encodeURIComponent( parcel.parcel_code ), {}, 'DELETE' )
					.then( function () { closeDrawer(); loadParcels(); } )
					.catch( function () { alert( 'Could not delete.' ); } );
			} );
		} else {
			drawerBody.innerHTML = '<p>Contact the board about this lot using the community directory.</p>';
		}
	}

	function openZoneDrawer( zone ) {
		openDrawer( zone.zone_name + ' (' + zone.zone_type + ')', 'Area: ' + Number( zone.surface_sqft || 0 ).toLocaleString() + ' sq ft' );

		var html = '';

		if ( zone.zone_type === 'paved_parking' && cfg.isManager ) {
			html += '<div id="hoa-solar-panel">Loading solar estimate…</div>';
		} else if ( zone.zone_type === 'turf' ) {
			html += '<p>Irrigation scheduling & turf rebate tracking for this zone.</p>';
		} else if ( zone.zone_type === 'recreation' ) {
			html += '<p>Amenity hours, access control, and reservations for this zone.</p>';
		} else if ( zone.zone_type === 'hoa_boundary' ) {
			html += '<p>This is the community\u2019s outer property boundary.</p>';
		}

		if ( zone.zone_type !== 'hoa_boundary' ) {
			html += ticketFormHtml();
		}
		if ( cfg.isManager ) {
			html += deleteButtonHtml();
		}
		drawerBody.innerHTML = html;
		if ( zone.zone_type !== 'hoa_boundary' ) {
			wireTicketForm( zone.zone_code, null );
		}
		if ( cfg.isManager ) {
			document.getElementById( 'hoa-delete-feature' ).addEventListener( 'click', function () {
				if ( ! confirm( 'Remove "' + zone.zone_name + '" from the map?' ) ) { return; }
				apiPost( '/map/zones/' + encodeURIComponent( zone.zone_code ), {}, 'DELETE' )
					.then( function () { closeDrawer(); loadZones(); } )
					.catch( function () { alert( 'Could not delete.' ); } );
			} );
		}

		if ( zone.zone_type === 'paved_parking' && cfg.isManager ) {
			apiGet( '/map/solar-estimate/' + zone.zone_code ).then( function ( est ) {
				document.getElementById( 'hoa-solar-panel' ).innerHTML =
					'<div class="hoa-solar-metric">' + est.estimated_kw + ' kW</div>' +
					'<p>Estimated solar canopy capacity for this lot. ' + est.note + '</p>';
			} ).catch( function () {
				document.getElementById( 'hoa-solar-panel' ).textContent = 'Solar estimate unavailable.';
			} );
		}
	}

	function openAssetDrawer( asset ) {
		openDrawer( asset.asset_tag, asset.asset_type + ' — ' + asset.status );
		var html = '';
		if ( asset.asset_type === 'tree' && cfg.isManager ) {
			html += '<p>Arborist maintenance log & trim cycle history.</p>';
		}
		html += ticketFormHtml( asset.zone_code, asset.asset_tag );
		if ( cfg.isManager ) {
			html += deleteButtonHtml();
		}
		drawerBody.innerHTML = html;
		wireTicketForm( asset.zone_code, asset.asset_tag );
		if ( cfg.isManager ) {
			document.getElementById( 'hoa-delete-feature' ).addEventListener( 'click', function () {
				if ( ! confirm( 'Remove "' + asset.asset_tag + '" from the map?' ) ) { return; }
				apiPost( '/map/assets/' + encodeURIComponent( asset.asset_tag ), {}, 'DELETE' )
					.then( function () { closeDrawer(); loadAssets(); } )
					.catch( function () { alert( 'Could not delete.' ); } );
			} );
		}
	}

	// ---------- Ticket form (shared by zone + asset drawers) ----------

	function ticketFormHtml() {
		return (
			'<div class="hoa-field"><label>Issue</label>' +
			'<select id="hoa-ticket-type">' +
			'<option value="lighting_out">Lighting out</option>' +
			'<option value="irrigation_leak">Irrigation leak</option>' +
			'<option value="asphalt_crack">Asphalt / path crack</option>' +
			'<option value="landscaping">Landscaping</option>' +
			'<option value="other">Other</option>' +
			'</select></div>' +
			'<div class="hoa-field"><label>Details</label>' +
			'<textarea id="hoa-ticket-description" rows="3"></textarea></div>' +
			'<button class="hoa-btn" id="hoa-ticket-submit">Submit Ticket</button>' +
			'<p id="hoa-ticket-status"></p>'
		);
	}

	function wireTicketForm( zoneCode, assetTag ) {
		var btn = document.getElementById( 'hoa-ticket-submit' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var issue_type = document.getElementById( 'hoa-ticket-type' ).value;
			var description = document.getElementById( 'hoa-ticket-description' ).value.trim();
			apiPost( '/map/tickets', {
				zone_code: zoneCode,
				asset_tag: assetTag,
				issue_type: issue_type,
				description: description,
			} ).then( function () {
				document.getElementById( 'hoa-ticket-status' ).textContent = 'Ticket submitted — thank you.';
			} ).catch( function () {
				document.getElementById( 'hoa-ticket-status' ).textContent = 'Could not submit ticket, try again.';
			} );
		} );
	}
} )();
