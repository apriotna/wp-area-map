/**
 * Map Editor
 * For Map CPT - Search address and auto center/zoom
 */
document.addEventListener('DOMContentLoaded', function () {

  const mapEl = document.getElementById('map10-map-editor');
  if (!mapEl) {
    console.log('[MAP EDITOR] Container not found');
    return;
  }

  const addressInput = document.getElementById('map10_address');
  const searchBtn = document.getElementById('map10_search_btn');
  const latInput = document.getElementById('map10_center_lat');
  const lngInput = document.getElementById('map10_center_lng');
  const zoomInput = document.getElementById('map10_zoom');

  const lat = parseFloat(mapEl.dataset.lat) || 52.370216;
  const lng = parseFloat(mapEl.dataset.lng) || 4.895168;
  const zoom = parseInt(mapEl.dataset.zoom) || 13;

  console.log('[MAP EDITOR] Initializing with:', { lat, lng, zoom });

  // Initialize map
  const map = L.map(mapEl).setView([lat, lng], zoom);

  L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    { maxZoom: 20 }
  ).addTo(map);

  // Add center marker
  let centerMarker = L.marker([lat, lng], {
    draggable: true,
    icon: L.divIcon({
      className: 'map10-center-marker',
      html: '<div style="background: #e51836; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
      iconSize: [20, 20],
      iconAnchor: [10, 10]
    })
  }).addTo(map);

  // Update hidden inputs when map changes
  function updateInputs() {
    const center = map.getCenter();
    const currentZoom = map.getZoom();
    
    latInput.value = center.lat.toFixed(6);
    lngInput.value = center.lng.toFixed(6);
    zoomInput.value = currentZoom;
    
    console.log('[MAP EDITOR] Updated:', {
      lat: center.lat.toFixed(6),
      lng: center.lng.toFixed(6),
      zoom: currentZoom
    });
  }

  // Update marker position when dragged
  centerMarker.on('dragend', function () {
    const pos = centerMarker.getLatLng();
    map.setView(pos, map.getZoom());
    updateInputs();
  });

  // Update inputs when map moves
  map.on('moveend', function () {
    const center = map.getCenter();
    centerMarker.setLatLng(center);
    updateInputs();
  });

  map.on('zoomend', function () {
    updateInputs();
  });

  // Search address functionality
  searchBtn.addEventListener('click', function () {
    const query = addressInput.value.trim();
    
    if (!query) {
      alert('Please enter an address');
      return;
    }

    console.log('[MAP EDITOR] Searching:', query);
    searchBtn.textContent = '⏳ Searching...';
    searchBtn.disabled = true;

    fetch(
      'https://nominatim.openstreetmap.org/search?format=json&q=' +
      encodeURIComponent(query)
    )
      .then(res => res.json())
      .then(results => {
        searchBtn.textContent = '🔍 Search';
        searchBtn.disabled = false;

        if (!results.length) {
          alert('Address not found. Please try a different search term.');
          return;
        }

        const result = results[0];
        const newLat = parseFloat(result.lat);
        const newLng = parseFloat(result.lon);

        console.log('[MAP EDITOR] Found:', result.display_name);

        // Zoom to location
        map.setView([newLat, newLng], 16);
        centerMarker.setLatLng([newLat, newLng]);
        updateInputs();

        // Clear search input
        addressInput.value = '';
      })
      .catch(error => {
        console.error('[MAP EDITOR] Search error:', error);
        searchBtn.textContent = '🔍 Search';
        searchBtn.disabled = false;
        alert('Search failed. Please try again.');
      });
  });

  // Allow Enter key to search
  addressInput.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      searchBtn.click();
    }
  });

  // Initial update
  updateInputs();

  console.log('[MAP EDITOR] Initialized successfully');
});
