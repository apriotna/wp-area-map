document.addEventListener('DOMContentLoaded', function () {

  if (typeof window.map10Data === 'undefined') {
    console.warn('[MAP10] map10Data not found');
    return;
  }

  // ============================================================
  // BASEMAP COLOR CONFIGURATION
  // ============================================================
  var MAP_COLORS = {
    land:       '#e8e8e8',
    water:      '#5bc8e8',
    road:       '#ffffff',
    road_minor: '#f5f5f5',
    building:   '#c0c0c0',
    park:       '#a8c8a0',
    label:      '#555555',
  };

  var map10PluginUrl = (typeof map10_vars !== 'undefined' && map10_vars.plugin_url)
    ? map10_vars.plugin_url : '';

  Object.keys(window.map10Data).forEach(function (mapId) {

    var el = document.getElementById('map10-' + mapId);
    if (!el) return;

    var data = window.map10Data[mapId];
    var lat  = parseFloat(data.lat);
    var lng  = parseFloat(data.lng);
    var zoom = parseInt(data.zoom) + 1;
    if (isNaN(lat) || isNaN(lng)) { console.error('[MAP10] Invalid center', data); return; }

    // ========================================
    // CATEGORY LOOKUP
    // ========================================
    var categoryMap = {};
    (data.categories || []).forEach(function(cat) { categoryMap[cat.id] = cat; });

    // ========================================
    // COLOR HELPERS  (no more hardcoded UVA/KNAW — use term meta)
    // ========================================
    function getCategoryColor(catId) {
      var cat = categoryMap[catId];
      return cat ? (cat.color || '#E67E22') : '#E67E22';
    }

    function parseColor(colorStr) {
      if (!colorStr) return { color: '#E67E22', opacity: 1 };
      var rgbaMatch = colorStr.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+))?\s*\)/);
      if (rgbaMatch) {
        var r = parseInt(rgbaMatch[1]), g = parseInt(rgbaMatch[2]), b = parseInt(rgbaMatch[3]);
        var a = rgbaMatch[4] !== undefined ? parseFloat(rgbaMatch[4]) : 1;
        if (a === 0) a = 0.7;
        var hex = '#' + ('0'+r.toString(16)).slice(-2) + ('0'+g.toString(16)).slice(-2) + ('0'+b.toString(16)).slice(-2);
        return { color: hex, opacity: a };
      }
      var hex8 = colorStr.match(/^#([0-9a-fA-F]{8})$/);
      if (hex8) {
        var h6 = '#' + hex8[1].substring(0,6);
        var al = parseInt(hex8[1].substring(6,8), 16) / 255;
        if (al === 0) al = 0.7;
        return { color: h6, opacity: al };
      }
      if (/^#[0-9a-fA-F]{6}$/.test(colorStr)) return { color: colorStr, opacity: 1 };
      return { color: '#E67E22', opacity: 1 };
    }

    // Resolve fill color: location override > primary category color
    function getLocationFillColor(loc) {
      if (loc.area_color_override && loc.area_color_override.trim()) {
        return loc.area_color_override;
      }
      var primaryCatId = (loc.categories && loc.categories.length) ? loc.categories[0] : loc.category;
      return getCategoryColor(primaryCatId);
    }

    // ========================================
    // MAPLIBRE STYLE
    // ========================================
    function buildMapStyle() {
      return {
        version: 8,
        glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        sources: { 'ofm': { type: 'vector', url: 'https://tiles.openfreemap.org/planet' } },
        layers: [
          { id: 'background', type: 'background', paint: { 'background-color': MAP_COLORS.land } },
          { id: 'water', type: 'fill', source: 'ofm', 'source-layer': 'water', paint: { 'fill-color': MAP_COLORS.water } },
          { id: 'waterway', type: 'line', source: 'ofm', 'source-layer': 'waterway',
            paint: { 'line-color': MAP_COLORS.water, 'line-width': ['interpolate',['linear'],['zoom'],8,0.5,14,2] } },
          { id: 'landuse-park', type: 'fill', source: 'ofm', 'source-layer': 'landuse',
            filter: ['in','class','park','grass','garden'], paint: { 'fill-color': MAP_COLORS.park } },
          { id: 'landcover-green', type: 'fill', source: 'ofm', 'source-layer': 'landcover',
            filter: ['in','class','wood','grass','forest'], paint: { 'fill-color': MAP_COLORS.park } },
          { id: 'building', type: 'fill', source: 'ofm', 'source-layer': 'building',
            paint: { 'fill-color': MAP_COLORS.building, 'fill-opacity': 0.9 } },
          { id: 'road-minor', type: 'line', source: 'ofm', 'source-layer': 'transportation',
            filter: ['in','class','minor','service','track','path','footway','cycleway'],
            paint: { 'line-color': MAP_COLORS.road_minor, 'line-width': ['interpolate',['linear'],['zoom'],12,0.5,16,2] } },
          { id: 'road-main', type: 'line', source: 'ofm', 'source-layer': 'transportation',
            filter: ['in','class','primary','secondary','tertiary','residential','living_street','unclassified'],
            paint: { 'line-color': MAP_COLORS.road, 'line-width': ['interpolate',['linear'],['zoom'],10,1,16,4] } },
          { id: 'road-highway', type: 'line', source: 'ofm', 'source-layer': 'transportation',
            filter: ['in','class','motorway','trunk'],
            paint: { 'line-color': MAP_COLORS.road, 'line-width': ['interpolate',['linear'],['zoom'],8,1,16,6] } },
          { id: 'label-place', type: 'symbol', source: 'ofm', 'source-layer': 'place',
            filter: ['in','class','city','town','village','suburb','quarter','neighbourhood'],
            layout: { 'text-field': ['get','name'], 'text-font': ['Noto Sans Regular'],
              'text-size': ['interpolate',['linear'],['zoom'],10,10,16,13], 'text-max-width': 8 },
            paint: { 'text-color': MAP_COLORS.label, 'text-halo-color': 'rgba(255,255,255,0.85)', 'text-halo-width': 1.5 } },
          { id: 'label-road', type: 'symbol', source: 'ofm', 'source-layer': 'transportation_name',
            minzoom: 14,
            layout: { 'text-field': ['get','name'], 'text-font': ['Noto Sans Regular'],
              'text-size': 11, 'symbol-placement': 'line', 'text-max-angle': 30 },
            paint: { 'text-color': MAP_COLORS.label, 'text-halo-color': 'rgba(255,255,255,0.85)', 'text-halo-width': 1.5 } }
        ]
      };
    }

    // ========================================
    // INIT MAP
    // ========================================
    var map = new maplibregl.Map({
      container: el,
      style: buildMapStyle(),
      center: [lng, lat],
      zoom: zoom,
      attributionControl: false
    });
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    // ========================================
    // STATE
    // ========================================
    var polygonLayers  = {};  // locId -> { fillId, lineId, loc, primaryCatId, baseOpacity }
    var markerObjects  = {};  // locId -> { marker, markerEl, loc }
    var activeCategory = null;
    var activeLocId    = null;
    var mapReady       = false;
    var suppressMapClick = false;  // prevents map click-outside from firing after marker click

    // ========================================
    // MAP LOAD
    // ========================================
    map.on('load', function () {
      mapReady = true;

      var sorted = (data.locations || []).slice().sort(function(a, b) {
        var oA = categoryMap[(a.categories||[])[0]] || {}; var oB = categoryMap[(b.categories||[])[0]] || {};
        return ((oA.order !== undefined) ? oA.order : 10) - ((oB.order !== undefined) ? oB.order : 10);
      });

      sorted.forEach(function (loc) {
        var primaryCatId = (loc.categories && loc.categories.length) ? loc.categories[0] : loc.category;

        // --- POLYGON ---
        if (loc.polygons) {
          var geojson;
          try { geojson = JSON.parse(loc.polygons); } catch(e) { console.warn('[MAP10] Bad GeoJSON', loc.title); }
          if (geojson) {
            var rawColor = getLocationFillColor(loc);
            var parsed   = parseColor(rawColor);
            var srcId    = 'map10-src-'  + loc.id;
            var fillId   = 'map10-fill-' + loc.id;
            var lineId   = 'map10-line-' + loc.id;

            map.addSource(srcId, { type: 'geojson', data: geojson });

            // Fill layer
            map.addLayer({ id: fillId, type: 'fill', source: srcId,
              paint: { 'fill-color': parsed.color, 'fill-opacity': parsed.opacity } });

            // Border layer (style: none | solid | dotted)
            var hasBorder = loc.border_style && loc.border_style !== 'none';
            if (hasBorder) {
              var dashArray = loc.border_style === 'dotted' ? [2, 4] : [1];
              map.addLayer({ id: lineId, type: 'line', source: srcId,
                paint: {
                  'line-color':      loc.border_color || '#000000',
                  'line-width':      loc.border_width || 2,
                  'line-dasharray':  dashArray,
                  'line-opacity':    1
                }
              });
            }

            polygonLayers[loc.id] = {
              fillId, lineId: hasBorder ? lineId : null,
              loc, primaryCatId, baseOpacity: parsed.opacity
            };

            // Click on polygon → show info box (skip if not_clickable)
            if (!loc.not_clickable) {
              map.on('click', fillId, function() { showInfoBox(loc, mapId); });
              map.on('mouseenter', fillId, function() { map.getCanvas().style.cursor = 'pointer'; });
              map.on('mouseleave', fillId, function() { map.getCanvas().style.cursor = ''; });
            }
          }
        }

        // --- MARKER ---
        if (!loc.hide_pinpoint && loc.lat && loc.lng) {
          var markerEl = document.createElement('div');
          markerEl.className = 'map10-custom-marker';
          markerEl.setAttribute('data-loc-id', loc.id);
          var img = document.createElement('img');
          img.src = (loc.marker_icon && loc.marker_icon.trim())
            ? loc.marker_icon
            : map10PluginUrl + 'public/images/Default_pin.png';
          img.style.cssText = 'width:25px;height:50px;display:block;';
          markerEl.appendChild(img);

          var marker = new maplibregl.Marker({ element: markerEl, anchor: 'bottom' })
            .setLngLat([loc.lng, loc.lat]).addTo(map);

          markerEl.addEventListener('click', (function(l){ return function(e){
            suppressMapClick = true;
            showInfoBox(l, mapId);
          }; })(loc));

          markerObjects[loc.id] = { marker, markerEl, loc };
        }
      });

      initFilterButtonIcons();
      updateButtonStates(null);
      console.log('[MAP10] Rendered', (data.locations || []).length, 'locations');
    });

    // ========================================
    // FILTER BUTTONS
    // ========================================
    var filterButtons = document.querySelectorAll('.map10-category-btn[data-map-id="' + mapId + '"]');

    function initFilterButtonIcons() {
      filterButtons.forEach(function(btn) {
        var btnCatId = parseInt(btn.getAttribute('data-category-id'));
        var cat = categoryMap[btnCatId];
        if (cat && cat.icon && cat.icon.trim()) {
          var img = document.createElement('img');
          img.src = cat.icon; img.className = 'map10-btn-icon'; img.alt = cat.name || '';
          btn.insertBefore(img, btn.firstChild);
        }
      });
    }

    filterButtons.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var clickedId = parseInt(this.getAttribute('data-category-id'));
        activeCategory = (activeCategory === clickedId) ? null : clickedId;
        updateButtonStates(activeCategory);
        updateLayerVisibility(activeCategory);
        updateDropdownOptions(activeCategory);
      });
    });

    function updateButtonStates(activeCatId) {
      filterButtons.forEach(function(btn) {
        var btnCatId = parseInt(btn.getAttribute('data-category-id'));
        var cat      = categoryMap[btnCatId] || {};
        var parsed   = parseColor(cat.color || '#808080');
        var isActive = activeCatId === null || btnCatId === activeCatId;
        btn.classList.toggle('active',   isActive);
        btn.classList.toggle('inactive', !isActive);
        btn.style.backgroundColor = parsed.color;
        btn.style.opacity = isActive ? '1' : '0.45';
      });
    }

    function updateLayerVisibility(activeCatId) {
      if (!mapReady) return;

      Object.keys(polygonLayers).forEach(function(locId) {
        var pl  = polygonLayers[locId];
        var loc = pl.loc;
        var cats = loc.categories || [loc.category];
        var isActive = activeCatId === null || cats.indexOf(activeCatId) !== -1;
        map.setPaintProperty(pl.fillId, 'fill-opacity', isActive ? pl.baseOpacity : pl.baseOpacity * 0.15);
        if (pl.lineId) map.setPaintProperty(pl.lineId, 'line-opacity', isActive ? 1 : 0.1);
      });

      Object.keys(markerObjects).forEach(function(locId) {
        var mo   = markerObjects[locId];
        var loc  = mo.loc;
        var cats = loc.categories || [loc.category];
        var isActive = activeCatId === null || cats.indexOf(activeCatId) !== -1;
        mo.markerEl.style.opacity = isActive ? '1' : '0.2';

        // Resize: no filter = 25x50, active filter = 25x50, inactive filter = 25x50
        var img = mo.markerEl.querySelector('img');
        if (img) {
          var isFiltering = activeCatId !== null;
          img.style.width  = (isFiltering && isActive) ? '25px' : '25px';
          img.style.height = (isFiltering && isActive) ? '50px' : '50px';
        }
      });
    }

    // ========================================
    // DROPDOWN — filter options by active category
    // ========================================
    var dropdown = document.getElementById('map10-location-select-' + mapId);

    function updateDropdownOptions(activeCatId) {
      if (!dropdown) return;
      var opts = dropdown.querySelectorAll('option[value]');
      opts.forEach(function(opt) {
        if (!opt.value) return;
        var cats = (opt.getAttribute('data-categories') || '').split(',').map(Number).filter(Boolean);
        var isVisible = activeCatId === null || cats.indexOf(activeCatId) !== -1;
        opt.style.display = isVisible ? '' : 'none';
        opt.disabled = !isVisible;
      });
      // Reset to placeholder if current selection is hidden
      if (dropdown.value && !isOptionVisible(dropdown.value)) {
        dropdown.value = '';
        hideInfoBox(mapId);
      }
    }

    function isOptionVisible(val) {
      var opt = dropdown ? dropdown.querySelector('option[value="' + val + '"]') : null;
      return opt && !opt.disabled;
    }

    if (dropdown) {
      dropdown.addEventListener('change', function() {
        var locationId = parseInt(this.value);
        if (!locationId) { map.flyTo({ center: [lng, lat], zoom: zoom }); hideInfoBox(mapId); return; }
        var location = (data.locations || []).find(function(l) { return l.id === locationId; });
        if (location) {
          var zoomed = false;
          if (location.polygons) {
            var gj; try { gj = JSON.parse(location.polygons); } catch(e) {}
            if (gj) {
              var bounds = getGeoJSONBounds(gj);
              if (bounds) { map.fitBounds(bounds, { padding: 50, duration: 1000 }); zoomed = true; }
            }
          }
          if (!zoomed && location.lat && location.lng) {
            map.flyTo({ center: [location.lng, location.lat], zoom: 18, duration: 1000 });
          }
          setTimeout(function() { showInfoBox(location, mapId); }, 500);
        }
      });
    }

    function getGeoJSONBounds(gj) {
      var minLng=180,maxLng=-180,minLat=90,maxLat=-90,found=false;
      function walk(c) {
        if (!Array.isArray(c)) return;
        if (typeof c[0]==='number') { minLng=Math.min(minLng,c[0]);maxLng=Math.max(maxLng,c[0]);minLat=Math.min(minLat,c[1]);maxLat=Math.max(maxLat,c[1]);found=true; }
        else c.forEach(walk);
      }
      (gj.features || [gj]).forEach(function(f){ if(f.geometry&&f.geometry.coordinates) walk(f.geometry.coordinates); });
      return found ? [[minLng,minLat],[maxLng,maxLat]] : null;
    }

    // ========================================
    // INFO BOX
    // ========================================
    function showInfoBox(location, mid) {
      var infoBox = document.getElementById('map10-info-box-' + mid);
      if (!infoBox) return;

      // Determine header color: use primary category or area_color_override
      var headerColorStr = getLocationFillColor(location);
      var headerParsed   = parseColor(headerColorStr);

      var h  = infoBox.querySelector('.map10-info-header');
      var t  = infoBox.querySelector('.map10-info-title');
      var ci = infoBox.querySelector('.map10-header-category-icons');
      var c  = infoBox.querySelector('.map10-info-content');
      var i  = infoBox.querySelector('.map10-info-image');
      var l  = infoBox.querySelector('.map10-info-link');

      if (h) h.style.backgroundColor = headerParsed.color;
      if (t) t.textContent = location.title || '';

      // Multi-category icons
      if (ci) {
        ci.innerHTML = '';
        var cats = location.categories || (location.category ? [location.category] : []);
        cats.forEach(function(catId) {
          var cat = categoryMap[catId];
          if (cat && cat.icon && cat.icon.trim()) {
            var img = document.createElement('img');
            img.src = cat.icon; img.alt = cat.name || '';
            img.className = 'map10-cat-icon-badge';
            ci.appendChild(img);
          }
        });
      }

      if (c) c.innerHTML = location.desc || '';
      if (i) {
        var hasImg = location.image && location.image.trim();
        i.src     = hasImg ? location.image : '';
        i.alt     = location.title || '';
        i.style.display = hasImg ? 'block' : 'none';
      }
      if (l) {
        l.href        = location.url || '#';
        l.textContent = (location.link_button_text && location.link_button_text.trim())
          ? location.link_button_text
          : 'Boek een ruimte';
        l.style.display = location.url ? 'inline-block' : 'none';
      }

      // Active state on marker
      if (activeLocId && markerObjects[activeLocId]) {
        markerObjects[activeLocId].markerEl.classList.remove('map10-marker-active');
      }
      activeLocId = location.id;
      if (markerObjects[activeLocId]) {
        markerObjects[activeLocId].markerEl.classList.add('map10-marker-active');
      }

      infoBox.classList.add('active');
    }

    function hideInfoBox(mid) {
      var infoBox = document.getElementById('map10-info-box-' + mid);
      if (infoBox) infoBox.classList.remove('active');
      if (activeLocId && markerObjects[activeLocId]) {
        markerObjects[activeLocId].markerEl.classList.remove('map10-marker-active');
      }
      activeLocId = null;
    }

    var closeBtn = document.querySelector('#map10-info-box-' + mapId + ' .map10-info-close');
    if (closeBtn) closeBtn.addEventListener('click', function() { hideInfoBox(mapId); });

    // Close info box when clicking outside of it
    map.on('click', function(e) {
      if (suppressMapClick) { suppressMapClick = false; return; }
      var infoBox = document.getElementById('map10-info-box-' + mapId);
      if (infoBox && infoBox.classList.contains('active')) {
        var clickedOnPolygon = Object.keys(polygonLayers).some(function(locId) {
          return map.queryRenderedFeatures(e.point, { layers: [polygonLayers[locId].fillId] }).length > 0;
        });
        if (!clickedOnPolygon) {
          hideInfoBox(mapId);
        }
      }
    });

  }); // end forEach mapId

});