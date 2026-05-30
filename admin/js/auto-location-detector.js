/**
 * Auto Location Type Detector
 * Deteksi otomatis jalan/sungai/taman saat map selesai load
 * Berdasarkan center koordinat map
 */

// ========================================
// MAIN DETECTION FUNCTION
// ========================================
async function autoDetectLocationType(lat, lng) {
  
  console.log('🔍 [AUTO-DETECT] Memulai deteksi di:', lat, lng);
  
  // Show loading
  showStatus('Mendeteksi lokasi...', 'loading');
  
  try {
    // Query Overpass API
    const result = await queryOverpass(lat, lng);
    
    if (result.detected) {
      console.log('✅ [AUTO-DETECT] Berhasil:', result.type);
      showStatus(`Terdeteksi: ${result.label}`, 'success');
      return result;
    } else {
      console.log('⚠️ [AUTO-DETECT] Tidak terdeteksi');
      showStatus('Tidak dapat mendeteksi tipe lokasi', 'warning');
      return result;
    }
    
  } catch (error) {
    console.error('❌ [AUTO-DETECT] Error:', error);
    showStatus('Gagal mendeteksi. Silakan coba lagi.', 'error');
    return {
      detected: false,
      type: null,
      error: error.message
    };
  }
}

// ========================================
// QUERY OVERPASS API
// ========================================
async function queryOverpass(lat, lng) {
  
  const radius = 100; // 100 meter radius untuk area deteksi
  
  // Query untuk cek highway, waterway, leisure
  const query = `
    [out:json][timeout:15];
    (
      way["highway"](around:${radius},${lat},${lng});
      way["waterway"](around:${radius},${lat},${lng});
      way["leisure"="park"](around:${radius},${lat},${lng});
      way["natural"="water"](around:${radius},${lat},${lng});
      way["landuse"="grass"](around:${radius},${lat},${lng});
    );
    out tags;
  `;

  console.log('[OVERPASS] Query:', query.trim());

  const url = 'https://overpass-api.de/api/interpreter';
  
  const response = await fetch(url, {
    method: 'POST',
    body: 'data=' + encodeURIComponent(query)
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const data = await response.json();
  const elements = data.elements || [];
  
  console.log('[OVERPASS] Response:', elements.length, 'elements');
  
  // Analisa hasil
  return analyzeElements(elements);
}

// ========================================
// ANALYZE ELEMENTS
// ========================================
function analyzeElements(elements) {
  
  if (elements.length === 0) {
    return {
      detected: false,
      type: null,
      label: null,
      message: 'Tidak ada data di area ini'
    };
  }

  // Hitung jumlah setiap tipe
  const scores = {
    road: 0,
    river: 0,
    park: 0
  };

  const found = [];

  elements.forEach(el => {
    const tags = el.tags || {};
    
    // Cek highway (jalan)
    if (tags.highway) {
      scores.road++;
      found.push({
        type: 'road',
        name: tags.name || tags.highway,
        detail: tags.highway
      });
    }
    
    // Cek waterway atau natural=water (sungai/air)
    if (tags.waterway || tags.natural === 'water') {
      scores.river++;
      found.push({
        type: 'river',
        name: tags.name || tags.waterway || 'water',
        detail: tags.waterway || tags.natural
      });
    }
    
    // Cek leisure=park atau landuse=grass (taman)
    if (tags.leisure === 'park' || tags.landuse === 'grass') {
      scores.park++;
      found.push({
        type: 'park',
        name: tags.name || tags.leisure || tags.landuse,
        detail: tags.leisure || tags.landuse
      });
    }
  });

  console.log('[ANALYZE] Scores:', scores);
  console.log('[ANALYZE] Found:', found);

  // Cari yang paling banyak
  let detectedType = null;
  let maxScore = 0;

  Object.keys(scores).forEach(type => {
    if (scores[type] > maxScore) {
      maxScore = scores[type];
      detectedType = type;
    }
  });

  if (!detectedType || maxScore === 0) {
    return {
      detected: false,
      type: null,
      label: null,
      message: 'Tidak terdeteksi sebagai jalan/sungai/taman',
      scores: scores,
      found: found
    };
  }

  // Labels
  const labels = {
    road: '🛣️ Jalan/Road',
    river: '🌊 Sungai/River',
    park: '🌳 Taman/Park'
  };

  return {
    detected: true,
    type: detectedType,
    label: labels[detectedType],
    confidence: Math.round((maxScore / elements.length) * 100),
    scores: scores,
    found: found,
    message: `Terdeteksi sebagai ${labels[detectedType]}`
  };
}

// ========================================
// SHOW STATUS
// ========================================
function showStatus(message, type) {
  const statusEl = document.getElementById('detect_status');
  if (!statusEl) return;

  const icons = {
    loading: '🔍',
    success: '✅',
    warning: '⚠️',
    error: '❌'
  };

  const colors = {
    loading: '#666',
    success: '#2ECC71',
    warning: '#F39C12',
    error: '#E74C3C'
  };

  statusEl.textContent = `${icons[type] || ''} ${message}`;
  statusEl.style.color = colors[type] || '#666';
}

// ========================================
// INIT - Run when map loads
// ========================================
function initAutoDetect() {
  
  const mapEl = document.getElementById('map10-location-editor');
  if (!mapEl) {
    console.log('[AUTO-DETECT] Map element not found');
    return;
  }

  // Ambil koordinat dari data attribute
  const lat = parseFloat(mapEl.dataset.lat);
  const lng = parseFloat(mapEl.dataset.lng);

  if (isNaN(lat) || isNaN(lng)) {
    console.log('[AUTO-DETECT] Invalid coordinates');
    return;
  }

  console.log('[AUTO-DETECT] Map loaded at:', lat, lng);

  // Tambah tombol detect
  const detectBtn = document.getElementById('map10_auto_detect');
  if (detectBtn) {
    detectBtn.addEventListener('click', async function() {
      this.disabled = true;
      this.textContent = '⏳ Mendeteksi...';
      
      const result = await autoDetectLocationType(lat, lng);
      
      // Show hasil
      console.log('[RESULT]', result);
      
      // Display hasil detail di console
      if (result.detected) {
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('HASIL DETEKSI:');
        console.log('Tipe:', result.type);
        console.log('Label:', result.label);
        console.log('Confidence:', result.confidence + '%');
        console.log('Scores:', result.scores);
        console.log('Detail:', result.found);
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━');
      }
      
      this.disabled = false;
      this.textContent = '🔍 Deteksi Ulang';
    });
  }

  // AUTO RUN saat page load (opsional, bisa dimatikan)
  // Uncomment baris di bawah jika mau auto-run:
  // setTimeout(() => autoDetectLocationType(lat, lng), 1000);
}

// Run saat DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAutoDetect);
} else {
  initAutoDetect();
}

// Export untuk akses manual
window.LocationDetector = {
  detect: autoDetectLocationType,
  init: initAutoDetect
};

console.log('✅ Location Detector loaded');
console.log('📌 Usage: LocationDetector.detect(lat, lng)');
