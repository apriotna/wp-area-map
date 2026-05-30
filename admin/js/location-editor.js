/**
 * Location Editor v2.0
 * Fixes: zoom background disappear (fallback satellite tile)
 * Adds: OSM building import button
 */
document.addEventListener('DOMContentLoaded', function () {

  const mapEl = document.getElementById('map10-location-editor');
  if (!mapEl) return;

  const latInput      = document.getElementById('map10_lat');
  const lngInput      = document.getElementById('map10_lng');
  const polygonsInput = document.getElementById('map10_polygons');
  const addressInput  = document.getElementById('map10_address');
  const searchBtn     = document.getElementById('map10_search');
  const mapSelect     = document.getElementById('map10_map_select');

  const initialLat   = parseFloat(mapEl.dataset.lat)  || 52.3708;
  const initialLng   = parseFloat(mapEl.dataset.lng)  || 4.8950;
  const initialZoom  = parseInt(mapEl.dataset.zoom)   || 16;
  const categorySlug = mapEl.dataset.categorySlug     || '';
  const savedPolygons = mapEl.dataset.polygons        || '';

  // ========================================
  // INIT MAP with fallback tile layers
  // ========================================
  const map = L.map(mapEl).setView([initialLat, initialLng], initialZoom);

  // Primary: OpenStreetMap (good up to zoom 19)
  const osmLayer = L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    { maxZoom: 19, maxNativeZoom: 19, attribution: '© OpenStreetMap' }
  );

  // Fallback: Esri Satellite (works at very high zoom, great for building outlines)
  const esriSatellite = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 21, maxNativeZoom: 19, attribution: '© Esri' }
  );

  // Esri labels overlay (for street names on top of satellite)
  const esriLabels = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    { maxZoom: 21, maxNativeZoom: 19, attribution: '© Esri' }
  );

  osmLayer.addTo(map);

  // Layer control
  const baseMaps = {
    'Street (OpenStreetMap)': osmLayer,
    'Satellite (Esri)': L.layerGroup([esriSatellite, esriLabels])
  };
  L.control.layers(baseMaps, {}, { position: 'topright' }).addTo(map);

  // ========================================
  // POLYGON STYLING BY CATEGORY
  // ========================================
  function getPolygonStyle() {
    const base = { weight: 2, opacity: 1, fillOpacity: 0.5 };
    switch (categorySlug.toLowerCase()) {
      case 'uva':  return { ...base, color: '#e51836', fillColor: '#e51836', weight: 0, fillOpacity: 0.6 };
      case 'knaw': return { ...base, color: '#00529f', fillColor: '#00529f', weight: 0, fillOpacity: 0.6 };
      case 'ahh':  return { ...base, color: '#000', fillColor: 'transparent', fillOpacity: 0, weight: 3, dashArray: '8,6' };
      default:     return { ...base, color: '#E84C3D', fillColor: '#E84C3D', dashArray: '8,6' };
    }
  }

  // ========================================
  // MARKER
  // ========================================
  let marker = L.marker([initialLat, initialLng], {
    draggable: false,
    icon: L.icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-yellow.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
      iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    })
  }).addTo(map);

  // ========================================
  // DRAW FEATURE GROUP
  // ========================================
  const drawnItems = new L.FeatureGroup();
  map.addLayer(drawnItems);

  // Load saved polygons
  if (savedPolygons) {
    try {
      const geojson = JSON.parse(savedPolygons);
      L.geoJSON(geojson, { style: getPolygonStyle() }).eachLayer(layer => drawnItems.addLayer(layer));
      if (drawnItems.getLayers().length > 0) map.fitBounds(drawnItems.getBounds(), { padding: [50, 50] });
    } catch (e) { console.warn('[EDITOR] Invalid saved polygons:', e); }
  }

  // ========================================
  // DRAW CONTROLS
  // ========================================
  const drawControl = new L.Control.Draw({
    edit: { featureGroup: drawnItems },
    draw: {
      polygon:      { shapeOptions: getPolygonStyle(), showArea: true },
      polyline:     false,
      rectangle:    false,
      circle:       false,
      marker:       false,
      circlemarker: false
    }
  });
  map.addControl(drawControl);

  // ========================================
  // OSM BUILDING IMPORT
  // ========================================
  const importBtn = L.control({ position: 'topleft' });
  importBtn.onAdd = function() {
    const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
    div.innerHTML = '<a href="#" title="Import nearest building from OpenStreetMap" style="font-size:18px;line-height:30px;text-align:center;display:block;width:30px;height:30px;background:white;cursor:pointer;text-decoration:none;" id="map10-osm-import">🏛</a>';
    L.DomEvent.on(div, 'click', function(e) {
      L.DomEvent.preventDefault(e);
      importNearestBuilding();
    });
    return div;
  };
  importBtn.addTo(map);

  // Multiple Overpass API mirrors — tried in order until one succeeds
  const OVERPASS_ENDPOINTS = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
  ];

  function fetchOverpass(query, endpointIndex) {
    endpointIndex = endpointIndex || 0;
    if (endpointIndex >= OVERPASS_ENDPOINTS.length) {
      return Promise.reject(new Error('All Overpass endpoints failed'));
    }
    const url = OVERPASS_ENDPOINTS[endpointIndex] + '?data=' + encodeURIComponent(query);
    return fetch(url, { signal: AbortSignal.timeout ? AbortSignal.timeout(15000) : undefined })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .catch(function(err) {
        console.warn('[EDITOR] Overpass endpoint ' + endpointIndex + ' failed:', err);
        return fetchOverpass(query, endpointIndex + 1);
      });
  }

  function importNearestBuilding() {
    const center = map.getCenter();
    const delta  = 0.0015; // ~150m bounding box
    const bbox   = (center.lat - delta) + ',' + (center.lng - delta) + ',' + (center.lat + delta) + ',' + (center.lng + delta);
    const query  = '[out:json][timeout:20];(way["building"](' + bbox + '););out geom;';

    const a = document.getElementById('map10-osm-import');
    a.textContent = '⏳';

    fetchOverpass(query)
      .then(function(data) {
        a.textContent = '🏛';
        if (!data.elements || !data.elements.length) {
          alert('No buildings found near the map center.\nTip: zoom in closer to the building you want to trace, then try again.');
          return;
        }

        // Find nearest building to map center
        let nearest = null, nearestDist = Infinity;
        data.elements.forEach(function(el) {
          if (!el.geometry || !el.geometry.length) return;
          const avgLat = el.geometry.reduce(function(s,n){ return s+n.lat; }, 0) / el.geometry.length;
          const avgLng = el.geometry.reduce(function(s,n){ return s+n.lon; }, 0) / el.geometry.length;
          const d = Math.hypot(avgLat - center.lat, avgLng - center.lng);
          if (d < nearestDist) { nearestDist = d; nearest = el; }
        });

        if (!nearest || !nearest.geometry) return;

        const latlngs = nearest.geometry.map(function(n){ return [n.lat, n.lon]; });
        const poly    = L.polygon(latlngs, getPolygonStyle());
        drawnItems.addLayer(poly);
        map.fitBounds(poly.getBounds(), { padding: [30, 30] });
        updateAll();

        const name = nearest.tags && nearest.tags.name ? nearest.tags.name : 'unnamed building';
        console.log('[EDITOR] Imported OSM building:', name);
      })
      .catch(function(err) {
        a.textContent = '🏛';
        console.error('[EDITOR] All Overpass endpoints failed:', err);
        alert('Could not load buildings from OpenStreetMap.\n\nYou can draw the polygon manually using the polygon tool on the left side of the map.');
      });
  }

  // ========================================
  // UPDATE FUNCTIONS
  // ========================================
  function calculateCenter() {
    if (!drawnItems.getLayers().length) return { lat: initialLat, lng: initialLng };
    const c = drawnItems.getBounds().getCenter();
    return { lat: c.lat, lng: c.lng };
  }

  function updateMarkerPosition() {
    const c = calculateCenter();
    marker.setLatLng([c.lat, c.lng]);
    latInput.value = c.lat.toFixed(6);
    lngInput.value = c.lng.toFixed(6);
  }

  function updatePolygonsGeoJSON() {
    polygonsInput.value = JSON.stringify(drawnItems.toGeoJSON(), null, 2);
  }

  function updateAll() { updateMarkerPosition(); updatePolygonsGeoJSON(); }

  // ========================================
  // DRAW EVENTS
  // ========================================
  map.on(L.Draw.Event.CREATED, function(e) {
    e.layer.setStyle(getPolygonStyle());
    drawnItems.addLayer(e.layer);
    updateAll();
  });
  map.on(L.Draw.Event.EDITED,  updateAll);
  map.on(L.Draw.Event.DELETED, updateAll);

  // ========================================
  // ADDRESS SEARCH
  // ========================================
  searchBtn.addEventListener('click', function() {
    const q = addressInput.value.trim();
    if (!q) { alert('Please enter an address'); return; }
    searchBtn.textContent = '⏳ Searching...'; searchBtn.disabled = true;

    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(results => {
        searchBtn.textContent = 'Search'; searchBtn.disabled = false;
        if (!results.length) { alert('Address not found'); return; }
        map.setView([parseFloat(results[0].lat), parseFloat(results[0].lon)], 18);
        addressInput.value = '';
      })
      .catch(() => {
        searchBtn.textContent = 'Search'; searchBtn.disabled = false;
        alert('Search failed. Please try again.');
      });
  });

  addressInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchBtn.click(); }
  });

  // ========================================
  // MAP SELECTION CHANGE
  // ========================================
  mapSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    const lat = parseFloat(opt.dataset.lat), lng = parseFloat(opt.dataset.lng);
    const z   = parseInt(opt.dataset.zoom) || 16;
    if (lat && lng) map.setView([lat, lng], z);
  });

  updateAll();
  console.log('[EDITOR] v2.0 initialized');
});
