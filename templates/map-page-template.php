<?php
/**
 * Shared markup for the HOA Map — included by both the wp-admin page
 * render and the [hoa_map] shortcode. Keep this template free of
 * business logic; all data comes in over REST via hoa-map.js.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="hoa-map-root" class="hoa-map-root">

	<h1 class="hoa-map-title"><?php esc_html_e( 'Community Map', 'hoa-dashboard' ); ?></h1>

	<div class="hoa-map-legend">
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--parcel"></i><?php esc_html_e( 'Private Parcels', 'hoa-dashboard' ); ?></span>
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--parking"></i><?php esc_html_e( 'Parking & Carports', 'hoa-dashboard' ); ?></span>
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--turf"></i><?php esc_html_e( 'Turf & Greenbelts', 'hoa-dashboard' ); ?></span>
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--pool"></i><?php esc_html_e( 'Pool, Spa & Courts', 'hoa-dashboard' ); ?></span>
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--path"></i><?php esc_html_e( 'Pathways & Lighting', 'hoa-dashboard' ); ?></span>
		<span class="hoa-map-legend-item"><i class="hoa-swatch hoa-swatch--tree"></i><?php esc_html_e( 'Landscape Trees', 'hoa-dashboard' ); ?></span>
	</div>

	<div id="hoa-map" class="hoa-map-canvas" aria-label="<?php esc_attr_e( 'HOA community map', 'hoa-dashboard' ); ?>"></div>

	<!-- Contextual drawer: content is swapped in by hoa-map.js depending on
	     what kind of feature was clicked (zone vs asset vs parcel). -->
	<aside id="hoa-map-drawer" class="hoa-map-drawer hidden" aria-hidden="true">
		<button type="button" class="hoa-map-drawer-close" id="hoa-map-drawer-close" aria-label="<?php esc_attr_e( 'Close', 'hoa-dashboard' ); ?>">&times;</button>

		<h2 id="hoa-map-drawer-title"></h2>
		<p id="hoa-map-drawer-subtitle" class="hoa-map-drawer-subtitle"></p>

		<div id="hoa-map-drawer-body" class="hoa-map-drawer-body">
			<!-- Populated per feature type: solar estimate, ticket form,
			     amenity info, broadcast form, etc. -->
		</div>
	</aside>

</div>
