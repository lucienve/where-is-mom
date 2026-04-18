/* eslint-disable no-unused-vars */
let map;
let pathPolyline;
let markers = [];

/**
 * Initializes the Google Map using the provided API key.
 * @param {string} apiKey - The Google Maps API key.
 */
function initMapWithKey(apiKey) {
  if (!apiKey) return;

  // Inject Google Maps script dynamically once we have the key
  const script = document.createElement('script');
  script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&v=weekly&libraries=marker&callback=initMap`;
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
}

/**
 * Callback function executed after Google Maps API loads.
 * Initializes the map and sets up the polyline and polling.
 */
window.initMap = function() {
  map = new google.maps.Map(document.getElementById('mapContainer'), {
    center: {lat: 20, lng: 0},
    zoom: 7,
    mapTypeId: 'roadmap',
    mapTypeControl: true,
    zoomControl: true,
    streetViewControl: false,
    fullscreenControl: false,
    mapId: 'DEMO_MAP_ID',
  });

  pathPolyline = new google.maps.Polyline({
    geodesic: true,
    strokeColor: '#3b82f6',
    strokeOpacity: 1.0,
    strokeWeight: 3,
    map: map,
  });

  // Fetch data initially and when map stops moving
  map.addListener('idle', fetchLocations);
  // Poll every 5 minutes if idle
  setInterval(fetchLocations, 300000);

  // Fetch latest location to center map and show current position marker
  fetch('api.php?action=latest')
      .then((res) => res.json())
      .then((res) => {
        if (res.success && res.data) {
          const lat = parseFloat(res.data.lat);
          const lng = parseFloat(res.data.lng);
          const pos = {lat: lat, lng: lng};

          map.setCenter(pos);
          map.setZoom(7); // Few hundred miles view

          const pin = new google.maps.marker.PinElement({
            background: '#ef4444',
            borderColor: '#ffffff',
            glyphColor: '#ffffff',
            scale: 0.8,
          });

          new google.maps.marker.AdvancedMarkerElement({
            position: pos,
            map: map,
            content: pin.element,
            title: `Last Position: ${res.data.timestamp}`,
            zIndex: 1000, // Keep above the polyline
          });
        }
      });
};

/**
 * Fetches the latest locations from the server within the current map bounds
 * and renders them on the map.
 */
function fetchLocations() {
  if (!map) return;
  const bounds = map.getBounds();
  if (!bounds) return;

  const ne = bounds.getNorthEast();
  const sw = bounds.getSouthWest();

  const url = `api.php?action=locations&n=${ne.lat()}&s=${sw.lat()}` +
      `&e=${ne.lng()}&w=${sw.lng()}`;

  fetch(url)
      .then((res) => res.json())
      .then((res) => {
        if (res.success) {
          renderMapData(res.data);
        }
      });
}

/**
 * Renders the fetched location data onto the map as markers and polylines.
 * @param {Array<Object>} data - An array of location objects.
 */
function renderMapData(data) {
  const path = [];

  // Clear old markers
  markers.forEach((m) => m.setMap(null));
  markers = [];

  data.forEach((loc) => {
    const latLng = new google.maps.LatLng(loc.lat, loc.lng);

    // Add to polyline track regardless of source
    path.push(latLng);

    // Render marker only for photos
    if (loc.source === 'photo') {
      const pin = new google.maps.marker.PinElement({
        background: '#3b82f6',
        borderColor: '#2563eb',
        glyphColor: '#ffffff',
      });

      const marker = new google.maps.marker.AdvancedMarkerElement({
        position: latLng,
        map: map,
        content: pin.element,
        title: loc.timestamp,
      });

      marker.addListener('click', () => showLightbox(loc));
      markers.push(marker);
    }
  });

  pathPolyline.setPath(path);
}

/**
 * Shows the lightbox with photo details when a marker is clicked.
 * @param {Object} loc - The location object.
 */
function showLightbox(loc) {
  const lightbox = document.getElementById('lightbox');
  const img = document.getElementById('lightboxImage');
  const latSpan = document.getElementById('lightboxLat');
  const lngSpan = document.getElementById('lightboxLng');
  const timeSpan = document.getElementById('lightboxTime');

  // Simple proxy or we could do `image.php?id=${loc.id}` based on plan
  img.src = `api.php?action=image&id=${loc.id}`;

  // As per plan, we implemented image.php
  img.src = `image.php?id=${loc.id}`;

  latSpan.textContent = `Lat: ${parseFloat(loc.lat).toFixed(4)}`;
  lngSpan.textContent = `Lng: ${parseFloat(loc.lng).toFixed(4)}`;
  timeSpan.textContent = loc.timestamp;

  lightbox.classList.add('active');
}
