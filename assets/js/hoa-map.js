/**
 * HOA Map — front-end controller.
 *
 * Requires Leaflet (enqueued as a dependency) and HOA_MAP_CONFIG,
 * localized from PHP (rest URL, nonce, role flags, map center/zoom).
 */
( function () {
	'use strict';

	if ( typeof L === 'undefined' || typeof HOA_MAP_CONFIG === 'undefined' ) {
		return;
	}

	var cfg = HOA_MAP_CONFIG;
	var map, drawer, drawerTitle, drawerSubtitle, drawerBody;

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

		var parcelLayer     = L.geoJSON( null, { style: styleFor( 'parcel' ), onEachFeature: bindParcel } );
		var parkingLayer    = L.geoJSON( null, { style: styleFor( 'paved_parking' ), onEachFeature: bindZone } );
		var turfLayer       = L.geoJSON( null, { style: styleFor( 'turf' ), onEachFeature: bindZone } );
		var poolCourtLayer  = L.geoJSON( null, { style: styleFor( 'recreation' ), onEachFeature: bindZone } );
		var assetPointLayer = L.geoJSON( null, { pointToLayer: assetToMarker, onEachFeature: bindAsset } );

		parkingLayer.addTo( map );
		turfLayer.addTo( map );
		poolCourtLayer.addTo( map );
		assetPointLayer.addTo( map );
		parcelLayer.addTo( map );

		L.control.layers(
			{ 'OpenStreetMap': osmBaseLayer },
			{
				'Private Lots': parcelLayer,
				'Parking & Solar Areas': parkingLayer,
				'Common Greenbelts': turfLayer,
				'Pool, Spa & Courts': poolCourtLayer,
				'Pathway Lights & Trees': assetPointLayer,
			},
			{ collapsed: false }
		).addTo( map );

		loadZones( parkingLayer, turfLayer, poolCourtLayer );
		loadAssets( assetPointLayer );
		loadParcels( parcelLayer );
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

	function loadZones( parkingLayer, turfLayer, poolCourtLayer ) {
		apiGet( '/map/zones' ).then( function ( zones ) {
			zones.forEach( function ( zone ) {
				var feature = {
					type: 'Feature',
					geometry: zone.geojson,
					properties: zone,
				};
				if ( zone.zone_type === 'paved_parking' ) {
					parkingLayer.addData( feature );
				} else if ( zone.zone_type === 'turf' || zone.zone_type === 'pathway' ) {
					turfLayer.addData( feature );
				} else if ( zone.zone_type === 'recreation' ) {
					poolCourtLayer.addData( feature );
				} else {
					turfLayer.addData( feature ); // fallback bucket
				}
			} );
		} ).catch( console.error );
	}

	function loadAssets( assetPointLayer ) {
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

	function loadParcels( parcelLayer ) {
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

	function openParcelDrawer( parcel ) {
		openDrawer( parcel.parcel_code, parcel.address || '' );
		if ( cfg.isManager ) {
			drawerBody.innerHTML =
				'<div class="hoa-field"><label>Message to this member</label>' +
				'<textarea id="hoa-dm-message" rows="4" placeholder="Notice or message..."></textarea></div>' +
				'<button class="hoa-btn" id="hoa-dm-send">Send Notice</button>';
			document.getElementById( 'hoa-dm-send' ).addEventListener( 'click', function () {
				var message = document.getElementById( 'hoa-dm-message' ).value.trim();
				if ( ! message ) { return; }
				// Wire this to your existing member messaging system —
				// this fires the same 'hoa_map_broadcast' style pattern
				// so you can hook it server-side without editing this file.
				apiPost( '/map/broadcast', { zone_code: parcel.parcel_code, message: message } )
					.then( function () { alert( 'Notice sent.' ); closeDrawer(); } )
					.catch( function () { alert( 'Could not send notice.' ); } );
			} );
		} else {
			drawerBody.innerHTML = '<p>Contact the board about this lot using the community directory.</p>';
		}
	}

	function openZoneDrawer( zone ) {
		openDrawer( zone.zone_name + ' (' + zone.zone_type + ')', 'Area: ' + Number( zone.surface_sqft ).toLocaleString() + ' sq ft' );

		var html = '';

		if ( zone.zone_type === 'paved_parking' && cfg.isManager ) {
			html += '<div id="hoa-solar-panel">Loading solar estimate…</div>';
		} else if ( zone.zone_type === 'turf' ) {
			html += '<p>Irrigation scheduling & turf rebate tracking for this zone.</p>';
		} else if ( zone.zone_type === 'recreation' ) {
			html += '<p>Amenity hours, access control, and reservations for this zone.</p>';
		}

		html += ticketFormHtml( zone.zone_code, null );
		drawerBody.innerHTML = html;
		wireTicketForm( zone.zone_code, null );

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
		drawerBody.innerHTML = html;
		wireTicketForm( asset.zone_code, asset.asset_tag );
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
