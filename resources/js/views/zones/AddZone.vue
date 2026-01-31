<template>
  <div class="zones-add-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">
          <RouterLink to="/zones">Zone Management</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Add New Zone</li>
      </ol>
    </div>

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-12">
        <h4 class="mb-0 fw-semibold">Add New Zone</h4>
      </div>
    </div>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <!-- Zone Information -->
    <div class="card mb-3 rounded-4 shadow-0">
      <div class="card-header"><h6 class="mb-0">Zone Information</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-12 col-md-6">
            <label class="form-label small">Zone Name</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Zone Name" />
          </div>
          <div class="col-sm-12 col-md-3">
            <label class="form-label small">Speed (km/h)</label>
            <input v-model.number="form.speed" type="number" class="form-control" placeholder="Speed (km/h)" />
          </div>
          <div class="col-sm-12 col-md-3">
            <label class="form-label small">Status</label>
            <select v-model="form.status" class="form-select">
              <option value="">-- Select Status --</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-sm-12 col-md-6">
            <label class="form-label small">Geofence Type</label>
            <select v-model="form.type" class="form-select">
              <option value="circle">Circle</option>
              <option value="rectangle">Rectangle</option>
              <option value="polygon">Polygon</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small">Description</label>
            <input v-model="form.description" type="text" class="form-control" placeholder="Description" />
          </div>
        </div>
      </div>
    </div>

    <!-- Zone Map -->
    <div class="card mb-3 rounded-4 shadow-0">
      <div class="card-header"><h6 class="mb-0">Zone Map</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-12 col-md-6" v-if="form.type === 'circle'">
            <label class="form-label small">Radius (meter)</label>
            <input v-model.number="form.radius" type="number" class="form-control" placeholder="Radius (meter)" />
            <div class="form-text">Click map to set center. You can also search an address.</div>
          </div>
          <!-- Hidden fields to hold derived values for submit -->
          <input v-model="form.coordinates" type="hidden" />
          <input v-model="form.polygon" type="hidden" />
        </div>

        <div class="map-frame mt-3" style="position: relative; height: 500px;">
          <!-- Map Provider Toggle -->
          <div class="btn-group" role="group" style="position: absolute; top: 10px; right: 100px; z-index: 1000;" v-if="isTestingMode">
             <button type="button" class="btn btn-sm shadow-sm" :class="mapProvider === 'leaflet' ? 'btn-primary' : 'btn-light'" @click="mapProvider = 'leaflet'">Leaflet</button>
             <button type="button" class="btn btn-sm shadow-sm" :class="mapProvider === 'google' ? 'btn-primary' : 'btn-light'" @click="mapProvider = 'google'">Google Maps</button>
          </div>

          <!-- Google Maps Search -->
          <div v-if="mapProvider === 'google'" class="input-group input-group-sm shadow-sm" style="position: absolute; top: 10px; left: 10px; z-index: 1000; max-width: 300px;">
             <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
             <input ref="googleSearchInput" type="text" class="form-control" placeholder="Search Google Places..." />
          </div>

          <GoogleMap
            v-if="mapProvider === 'google'"
            ref="googleMapRef"
            :center="center"
            :zoom="zoom"
            :markers="googleMarkers"
            :polygons="googlePolygons"
            :circles="googleCircles"
            :clickable="true"
            @click="onGoogleMapClick"
            @marker-dragend="onGoogleMarkerDragEnd"
            @ready="onGoogleMapReady"
          />
          <l-map v-if="mapProvider === 'leaflet'" :key="mapKey" ref="mapRef" id="zoneMap" :zoom="zoom" :center="center" :options="mapOptions" @ready="onMapReady" @click="onMapClick">
            <l-tile-layer :url="tileUrl" :attribution="tileAttribution" />
            <!-- Default center marker -->
            <l-circle-marker :lat-lng="center" :radius="6" :color="'#d9534f'" :weight="2" :fillColor="'#d9534f'" :fillOpacity="0.9" />
            <l-polygon v-if="polygonPoints.length" :lat-lngs="polygonPoints" :color="'#1070e3'" :weight="2" :fillColor="'#1070e3'" :fillOpacity="0.25" />
            <l-circle v-if="circleCenter && form.radius && form.type === 'circle'" :lat-lng="circleCenter" :radius="form.radius" :color="'#3f8fd7'" :weight="1" :fillColor="'#3f8fd7'" :fillOpacity="0.25" />
            <!-- Draggable markers for vertices and search -->
            <l-marker v-for="(p,i) in polygonPoints" :key="'poly-'+i" :lat-lng="p" :draggable="true" @dragend="onDrawMarkerDragEnd('polygon', i, $event)" />
            <l-marker v-for="(p,i) in rectanglePoints" :key="'rect-'+i" :lat-lng="p" :draggable="true" @dragend="onDrawMarkerDragEnd('rectangle', i, $event)" />
            <l-marker v-if="searchMarkerLatLng" :lat-lng="searchMarkerLatLng" :draggable="true" @dragend="onSearchMarkerDragEnd" />
          </l-map>
          <!-- Map tools: Reset shape and center to current location -->
          <div class="map-tools" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-light shadow-sm" @click="resetWholeMap">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
              </button>
            </div>
          </div>
          <!-- map-controls removed
            <div class="btn-group btn-group-sm" role="group" aria-label="Basemap">
              <button type="button" class="btn btn-light" :class="{active: basemap === 'map'}" @click="basemap = 'map'">Map</button>
              <button type="button" class="btn btn-light" :class="{active: basemap === 'sat'}" @click="basemap = 'sat'">Satellite</button>
            </div>
            <div class="input-group input-group-sm ms-2" style="max-width: 340px;">
              <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
              <input type="text" class="form-control" placeholder="Search address" v-model="searchQuery" @keyup.enter="searchAddress" />
              <button class="btn btn-light" @click="searchAddress">Search</button>
            </div>
            <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Drawing" v-if="form.type !== 'circle'">
              <button type="button" class="btn btn-light" :class="{active: drawing}" @click="toggleDrawing"><i class="bi bi-vector-pen me-1"></i> {{ drawing ? 'Drawing…' : 'Start Draw' }}</button>
              <button type="button" class="btn btn-light" @click="finishDrawing" :disabled="!polygonPoints.length && form.type==='polygon' && !rectanglePoints.length">Finish</button>
              <button type="button" class="btn btn-light" @click="clearShapes">Clear</button>
            </div>
          -->
        </div>
      </div>

      <!-- Actions -->
      <div class="card-footer d-flex align-items-center justify-content-end gap-2 py-2">
        <RouterLink to="/zones" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button class="btn btn-app-dark" :disabled="submitting" @click="submit">
          <span v-if="submitting" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
          Add Zone
        </button>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { LMap, LTileLayer, LPolygon, LCircle, LCircleMarker, LMarker } from '@vue-leaflet/vue-leaflet';
import GoogleMap from '../../components/GoogleMap.vue';
import { GeoSearchControl, OpenStreetMapProvider } from 'leaflet-geosearch';
import 'leaflet-geosearch/dist/geosearch.css';
import 'leaflet/dist/leaflet.css';
import '@geoman-io/leaflet-geoman-free';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import * as L from 'leaflet';
import { RouterLink } from 'vue-router';
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();

const form = reactive({
  name: '',
  speed: 60,
  status: '',
  description: '',
  type: 'circle',
  coordinates: '', // "lat,lng"
  radius: undefined,
  polygon: '' // "lat,lng; lat,lng; ..."
});

// Align with old-sample geofencing.vue: centralize geofenceInfo
const geofenceInfo = reactive({
  lat: null,
  lng: null,
  radius: null,
  name: '',
  address: '',
  user_id: '',
  type: 'circle',
  coordinates: [], // array of [lat, lng]
  geofenceId: ''
});

const message = ref('');
const error = ref('');
const submitting = ref(false);

const zoom = ref(15);
const center = ref([38.627, -90.199]); // St. Louis area (matches screenshot vibe)
const basemap = ref('map');
const mapKey = ref(0);
const mapProvider = ref('leaflet'); // 'leaflet' or 'google'
const tileAttribution = '© OpenStreetMap contributors';
const mapOptions = { zoomControl: true };
const mapRef = ref(null);
const googleMapRef = ref(null);
const googleMapInternal = ref(null);
const isTestingMode = ref(false);

const tileUrl = computed(() => {
  return basemap.value === 'sat'
    ? 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
    : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
});

const googleMarkers = computed(() => {
  const markers = [];
  // Polygon vertices
  if (form.type === 'polygon') {
    polygonPoints.value.forEach((p, i) => {
      markers.push({ id: 'poly-' + i, lat: p[0], lng: p[1], draggable: true, type: 'polygon', index: i });
    });
  }
  // Rectangle vertices
  else if (form.type === 'rectangle') {
    rectanglePoints.value.forEach((p, i) => {
      markers.push({ id: 'rect-' + i, lat: p[0], lng: p[1], draggable: true, type: 'rectangle', index: i });
    });
  }
  // Search marker
  if (searchMarkerLatLng.value) {
    markers.push({
      id: 'search-marker',
      lat: searchMarkerLatLng.value[0],
      lng: searchMarkerLatLng.value[1],
      draggable: true,
      type: 'search',
      iconUrl: searchMarkerIcon.value
    });
  }
  // Center marker (matches Leaflet's l-circle-marker which is always visible)
  if (center.value) {
    markers.push({
      id: 'center-marker',
      lat: center.value[0],
      lng: center.value[1],
      draggable: false
    });
  }
  return markers;
});

const googlePolygons = computed(() => {
  // Only show polygon if type matches
  if ((form.type === 'polygon' || form.type === 'rectangle') && polygonPoints.value.length >= 3) {
    return [{
      paths: polygonPoints.value.map(p => ({ lat: p[0], lng: p[1] })),
      options: { color: '#1070e3', fillColor: '#1070e3', fillOpacity: 0.25 }
    }];
  }
  return [];
});

const googleCircles = computed(() => {
  if (form.type === 'circle' && circleCenter.value && form.radius) {
    return [{
      center: { lat: circleCenter.value[0], lng: circleCenter.value[1] },
      radius: Number(form.radius),
      options: { color: '#3f8fd7', fillColor: '#3f8fd7', fillOpacity: 0.25 }
    }];
  }
  return [];
});

const circleCenter = ref(null);
const polygonPoints = ref([]);
const rectanglePoints = ref([]); // two opposite corners
const drawing = ref(false);
const searchQuery = ref('');
const searchMarkerLatLng = ref(null);
const searchMarkerIcon = ref(null);
const googleSearchInput = ref(null);

watch(mapProvider, async (val) => {
  if (val === 'google') {
    try {
      await loadGooglePlacesScript();
      setTimeout(() => {
        if (googleSearchInput.value) {
          setupGoogleAutocomplete(googleSearchInput.value, (lat, lng, icon) => {
            center.value = [lat, lng];
            searchMarkerLatLng.value = [lat, lng];
            searchMarkerIcon.value = icon;
            form.coordinates = `${lat},${lng}`;
            if (form.type === 'circle') {
                circleCenter.value = [lat, lng];
                if (!form.radius || !Number.isFinite(Number(form.radius))) {
                  form.radius = 1000;
                }
                geofenceInfo.lat = lat;
                geofenceInfo.lng = lng;
                geofenceInfo.coordinates = [[lat, lng]];
                geofenceInfo.radius = form.radius;
              } else {
                 // For Polygon/Rectangle, move the drawing area to the searched location
                 // Create a default box around the new center
                 const d = 0.01; // approx 1km
                 polygonPoints.value = [
                   [lat-d, lng-d],
                   [lat+d, lng-d],
                   [lat+d, lng+d],
                   [lat-d, lng+d]
                 ];
                 if (form.type === 'rectangle') {
                    rectanglePoints.value = [[lat-d, lng-d], [lat+d, lng+d]];
                 }
                 // Clear previous coordinates since we moved the shape
                 form.polygon = '';
                 form.coordinates = '';
              }
            });
          }
      }, 200);
    } catch (e) {
      console.error('Failed to load Google Places', e);
    }
  }
});

// Keep a snapshot of initial geometry to restore on reset
const initialShape = ref({ type: null, polygon: [], circle: null, radius: null });

// Sync form fields into geofenceInfo
watch(() => form.name, (v) => { geofenceInfo.name = String(v || '').trim(); });
watch(() => form.description, (v) => { geofenceInfo.address = String(v || '').trim(); });
watch(() => form.radius, (v) => { geofenceInfo.radius = typeof v === 'number' ? v : geofenceInfo.radius; });

// Google Places loader (consistent with GoogleMap.vue)
let googlePlacesPromise = null;
function loadGooglePlacesScript() {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) return Promise.reject(new Error('Missing Google API key'));

  // Helper to check if the loaded Google Maps is likely the real one and not a shim
  const isRealGoogleMaps = () => {
    return window.google &&
           window.google.maps &&
           window.google.maps.places &&
           window.google.maps.Map &&
           // Real Google Maps API usually exposes a version property
           window.google.maps.version;
  };

  if (isRealGoogleMaps()) return Promise.resolve();

  if (googlePlacesPromise) return googlePlacesPromise;
  googlePlacesPromise = new Promise((resolve, reject) => {
    let attempts = 0;
    const check = () => {
      if (isRealGoogleMaps()) {
        resolve();
      } else {
        attempts++;
        if (attempts > 300) {
          reject(new Error('Google Maps API failed to load within 30 seconds.'));
          return;
        }
        setTimeout(check, 100);
      }
    };
    check();
  });
  return googlePlacesPromise;
}

function setupGoogleAutocomplete(input, onPlaceSelected, clearSuggestionsCb) {
  try {
    if (!(window.google && window.google.maps && window.google.maps.places)) return;
    const autocomplete = new window.google.maps.places.Autocomplete(input, { types: ['geocode'] });
    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place || !place.geometry || !place.geometry.location) return;
      const lat = place.geometry.location.lat();
      const lon = place.geometry.location.lng();
      input.value = place.formatted_address || place.name || input.value;
      if (typeof clearSuggestionsCb === 'function') clearSuggestionsCb();
      if (typeof onPlaceSelected === 'function') onPlaceSelected(lat, lon);
    });
  } catch {}
}

let drawingManager = null;

function setupGoogleDrawingManager() {
  const gm = googleMapRef.value;
  const map = gm?.map && gm.map.setCenter ? gm.map : (gm?.map ? gm.map.value : null);
  if (!map || !window.google || !window.google.maps.drawing) return;

  if (!drawingManager) {
    drawingManager = new window.google.maps.drawing.DrawingManager({
      drawingMode: null,
      drawingControl: true,
      drawingControlOptions: {
        position: window.google.maps.ControlPosition.TOP_CENTER,
        drawingModes: []
      },
      polygonOptions: {
        fillColor: '#1070e3',
        fillOpacity: 0.25,
        strokeWeight: 2,
        clickable: true,
        editable: true,
        zIndex: 1
      },
      rectangleOptions: {
        fillColor: '#1070e3',
        fillOpacity: 0.25,
        strokeWeight: 2,
        clickable: true,
        editable: true,
        zIndex: 1
      }
    });

    window.google.maps.event.addListener(drawingManager, 'overlaycomplete', (e) => {
      const newShape = e.overlay;
      newShape.setMap(null);

      if (e.type === 'polygon') {
        const path = newShape.getPath();
        const pts = [];
        for (let i = 0; i < path.getLength(); i++) {
          const xy = path.getAt(i);
          pts.push([xy.lat(), xy.lng()]);
        }
        polygonPoints.value = pts;
        circleCenter.value = null;
        form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
        form.coordinates = '';
      } else if (e.type === 'rectangle') {
        const bounds = newShape.getBounds();
        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();
        rectanglePoints.value = [[sw.lat(), sw.lng()], [ne.lat(), ne.lng()]];
        polygonPoints.value = [
             [sw.lat(), sw.lng()],
             [sw.lat(), ne.lng()],
             [ne.lat(), ne.lng()],
             [ne.lat(), sw.lng()]
        ];
        circleCenter.value = null;
        form.coordinates = `${sw.lat()},${sw.lng()}; ${ne.lat()},${ne.lng()}`;
        form.polygon = '';
      }
      drawingManager.setDrawingMode(null);
    });
  }

  // ALWAYS bind to the current map instance, in case it changed (e.g. toggled providers)
  drawingManager.setMap(map);
  updateDrawingMode();
}

function updateDrawingMode() {
  if (!drawingManager || !window.google) return;
  const dm = drawingManager;
  // Clear any existing modes
  dm.setOptions({
    drawingControlOptions: {
      position: window.google.maps.ControlPosition.TOP_CENTER,
      drawingModes: []
    }
  });

  if (form.type === 'polygon') {
    dm.setOptions({
      drawingControl: true,
      drawingControlOptions: {
        position: window.google.maps.ControlPosition.TOP_CENTER,
        drawingModes: [window.google.maps.drawing.OverlayType.POLYGON]
      }
    });
  } else if (form.type === 'rectangle') {
    dm.setOptions({
      drawingControl: true,
      drawingControlOptions: {
        position: window.google.maps.ControlPosition.TOP_CENTER,
        drawingModes: [window.google.maps.drawing.OverlayType.RECTANGLE]
      }
    });
  } else {
    // Circle or other: disable drawing tools (handled by click/radius input)
    dm.setDrawingMode(null);
    dm.setOptions({ drawingControl: false });
  }
}

function onGoogleMapReady(mapInstance) {
  if (mapInstance) googleMapInternal.value = mapInstance;
  setTimeout(() => {
    fitMapToCurrentShape();
    setupGoogleDrawingManager();
  }, 200);
}

function onMapReady(map) {
  mapRef.value = map;
  attachGeocoderControl(map);
  setupGeoman(map);
  // Also bind native Leaflet click to ensure we capture clicks
  try { map.on('click', onMapClick); } catch {}
}

function drawDemo() {
  // If user provided inputs, try to render those; otherwise draw sample polygon
  if (form.polygon) {
    const pts = form.polygon.split(';').map(s => s.trim()).map(p => {
      const [lat, lng] = p.split(',').map(Number);
      return [lat, lng];
    }).filter(arr => Array.isArray(arr) && arr.length === 2 && arr.every(v => !Number.isNaN(v)));
    if (pts.length >= 3) {
      polygonPoints.value = pts;
      circleCenter.value = null;
      return;
    }
  }
  if (form.coordinates && form.radius) {
    const [lat, lng] = form.coordinates.split(',').map(Number);
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
      circleCenter.value = [lat, lng];
      polygonPoints.value = [];
      return;
    }
  }
  // Fallback: demo polygon near St. Louis
  polygonPoints.value = [
    [38.68, -90.33],
    [38.70, -90.10],
    [38.60, -90.05],
    [38.58, -90.30]
  ];
  circleCenter.value = null;
}

onMounted(() => {
  // Pre-render demo polygon so the map doesn’t look empty
  drawDemo();
  // Try to get user location
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      center.value = [lat, lng];

      // If form is clean (no user input yet), update the shape to user location
      if (!form.coordinates && !form.polygon && form.type === 'circle') {
          circleCenter.value = [lat, lng];
          form.coordinates = `${lat},${lng}`;
      } else if (!form.polygon && form.type !== 'circle' && polygonPoints.value.length === 4) {
          // Move demo polygon to user location
          const d = 0.01;
          polygonPoints.value = [
             [lat-d, lng-d],
             [lat+d, lng-d],
             [lat+d, lng+d],
             [lat-d, lng+d]
          ];
      }
    }, (err) => {
       console.warn('Geolocation failed', err);
    });
  }
  // Capture initial shape after demo draw
  try {
    if (polygonPoints.value.length >= 3) {
      initialShape.value = { type: 'polygon', polygon: [...polygonPoints.value] };
    } else if (Array.isArray(circleCenter.value) && circleCenter.value.length === 2) {
      initialShape.value = { type: 'circle', circle: [...circleCenter.value], radius: typeof form.radius === 'number' ? form.radius : 1000 };
    } else {
      initialShape.value = { type: form.type || 'polygon', polygon: [...polygonPoints.value] };
    }
  } catch {}
  try {
    const uid = localStorage.getItem('APP_USER_ID');
    if (uid) geofenceInfo.user_id = uid;
  } catch {}
});

function onGoogleMarkerDragEnd(e) {
  const marker = googleMarkers.value.find(m => m.id === e.id);
  if (!marker) return;

  const eventMock = { target: { getLatLng: () => ({ lat: e.lat, lng: e.lng }) } };

  if (marker.type === 'search') {
    onSearchMarkerDragEnd(eventMock);
  } else if (marker.type === 'polygon' || marker.type === 'rectangle') {
    onDrawMarkerDragEnd(marker.type, marker.index, eventMock);
  }
}

function onGoogleMapClick(e) {
  const eventMock = { latlng: { lat: e.lat, lng: e.lng } };
  onMapClick(eventMock);
}

function onMapClick(e) {
  const latlng = e.latlng ? [e.latlng.lat, e.latlng.lng] : null;
  if (!latlng) return;
  center.value = latlng; // move center marker
  if (form.type === 'circle') {
    circleCenter.value = latlng;
    form.coordinates = `${latlng[0]},${latlng[1]}`;
    geofenceInfo.lat = latlng[0];
    geofenceInfo.lng = latlng[1];
    geofenceInfo.coordinates = [[latlng[0], latlng[1]]];
    geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
    searchMarkerLatLng.value = latlng;
    return;
  }
  if (!drawing.value) { searchMarkerLatLng.value = latlng; return; }
  if (form.type === 'polygon') {
    polygonPoints.value = [...polygonPoints.value, latlng];
    geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
  } else if (form.type === 'rectangle') {
    if (rectanglePoints.value.length === 0) {
      rectanglePoints.value = [latlng];
    } else if (rectanglePoints.value.length === 1) {
      rectanglePoints.value = [rectanglePoints.value[0], latlng];
      // compute rectangle corners
      const [p1, p2] = rectanglePoints.value;
      const minLat = Math.min(p1[0], p2[0]);
      const maxLat = Math.max(p1[0], p2[0]);
      const minLng = Math.min(p1[1], p2[1]);
      const maxLng = Math.max(p1[1], p2[1]);
      polygonPoints.value = [
        [minLat, minLng],
        [minLat, maxLng],
        [maxLat, maxLng],
        [maxLat, minLng],
      ];
      form.coordinates = `${p1[0]},${p1[1]}; ${p2[0]},${p2[1]}`;
      geofenceInfo.coordinates = [[p1[0], p1[1]], [p2[0], p2[1]]];
    }
  }
}

function onSearchMarkerDragEnd(e) {
  try {
    const ll = e?.target?.getLatLng?.();
    if (!ll) return;
    const latlng = [ll.lat, ll.lng];
    searchMarkerLatLng.value = latlng;
    center.value = latlng;
    form.coordinates = `${latlng[0]},${latlng[1]}`;
    if (form.type === 'circle') {
      circleCenter.value = latlng;
      geofenceInfo.lat = latlng[0];
      geofenceInfo.lng = latlng[1];
      geofenceInfo.coordinates = [[latlng[0], latlng[1]]];
    }
  } catch {}
}

function onDrawMarkerDragEnd(kind, index, e) {
  try {
    const ll = e?.target?.getLatLng?.();
    if (!ll) return;
    const latlng = [ll.lat, ll.lng];
  if (kind === 'polygon') {
    polygonPoints.value[index] = latlng;
    form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
    geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
  } else if (kind === 'rectangle') {
    rectanglePoints.value[index] = latlng;
    if (rectanglePoints.value.length === 2) {
      const [p1, p2] = rectanglePoints.value;
      const minLat = Math.min(p1[0], p2[0]);
      const maxLat = Math.max(p1[0], p2[0]);
      const minLng = Math.min(p1[1], p2[1]);
      const maxLng = Math.max(p1[1], p2[1]);
      polygonPoints.value = [
        [minLat, minLng],
        [minLat, maxLng],
        [maxLat, maxLng],
        [maxLat, minLng],
      ];
      form.coordinates = `${p1[0]},${p1[1]}; ${p2[0]},${p2[1]}`;
      geofenceInfo.coordinates = [[p1[0], p1[1]], [p2[0], p2[1]]];
    }
  }
  } catch {}
}

function toggleDrawing() { drawing.value = !drawing.value; }
function clearShapes() {
  polygonPoints.value = [];
  rectanglePoints.value = [];
  circleCenter.value = null;
  form.coordinates = '';
  form.polygon = '';
  clearGeomanLayers();
  geofenceInfo.coordinates = [];
  geofenceInfo.lat = null;
  geofenceInfo.lng = null;
}
function finishDrawing() {
  if (form.type === 'polygon' && polygonPoints.value.length >= 3) {
    // close polygon visually (optional) and set form string
    form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
    drawing.value = false;
    geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
  } else if (form.type === 'rectangle' && rectanglePoints.value.length === 2) {
    // already set coordinates in onMapClick
    drawing.value = false;
    const [p1, p2] = rectanglePoints.value;
    geofenceInfo.coordinates = [[p1[0], p1[1]], [p2[0], p2[1]]];
  }
}

function attachGeocoderControl(map) {
  try {
    if (!map || !L || !L.Control) return;

    // Prefer the same process used in geofencing.vue: leaflet-geosearch
    try {
      const searchCtl = new GeoSearchControl({
        provider: new OpenStreetMapProvider(),
        style: 'bar',
      });
      map.addControl(searchCtl);
      map.on('geosearch/showlocation', (e) => {
        const lat = parseFloat(e?.location?.raw?.lat);
        const lon = parseFloat(e?.location?.raw?.lon);
        if (Number.isFinite(lat) && Number.isFinite(lon)) {
          center.value = [lat, lon];
          searchMarkerLatLng.value = [lat, lon];
          form.coordinates = `${lat},${lon}`;
          if (form.type === 'circle') {
            circleCenter.value = [lat, lon];
            if (!form.radius || !Number.isFinite(Number(form.radius))) {
              form.radius = 1000;
            }
          }
          map.setView([lat, lon], map.getZoom());
        }
      });
      // For circle, mimic geofencing.vue: set initial center via browser geolocation
      setTimeout(() => {
        if (form.type !== 'circle') return;
        if (navigator?.geolocation) {
          navigator.geolocation.getCurrentPosition(
            (position) => {
              const lat = position.coords.latitude;
              const lon = position.coords.longitude;
              center.value = [lat, lon];
              form.coordinates = `${lat},${lon}`;
              circleCenter.value = [lat, lon];
              form.radius = typeof form.radius === 'number' ? form.radius : 1000;
              map.setView([lat, lon], 15);
            },
            (error) => { /* ignore */ }
          );
        }
      }, 500);
      return; // skip custom control when plugin is available
    } catch {}
    const GeocodeCtl = L.Control.extend({
      onAdd: (m) => {
        const container = L.DomUtil.create('div', 'leaflet-bar geocode-control');
        container.style.background = '#fff';
        container.style.padding = '10px';
        container.style.borderRadius = '6px';
        container.style.boxShadow = '0 1px 4px rgba(0,0,0,.2)';
        container.style.position = 'relative';

        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Enter a location';
        input.style.width = '240px';
        input.style.padding = '4px 6px';
        input.style.border = '1px solid #ddd';
        input.style.borderRadius = '4px';
        input.style.marginRight = '8px';

        const btn = document.createElement('button');
        btn.textContent = 'Geocode';
        btn.style.padding = '4px 8px';
        btn.style.border = '1px solid #ddd';
        btn.style.background = '#f8f9fa';
        btn.style.borderRadius = '4px';

        const suggestions = document.createElement('div');
        suggestions.className = 'geocode-suggestions';
        suggestions.style.position = 'absolute';
        suggestions.style.left = '0';
        suggestions.style.right = '0';
        suggestions.style.top = '52px';
        suggestions.style.background = '#fff';
        suggestions.style.border = '1px solid #ddd';
        suggestions.style.borderRadius = '6px';
        suggestions.style.boxShadow = '0 1px 4px rgba(0,0,0,.2)';
        suggestions.style.maxHeight = '220px';
        suggestions.style.overflowY = 'auto';
        suggestions.style.zIndex = '1000';
        suggestions.style.display = 'none';

        function clearSuggestions() {
          suggestions.innerHTML = '';
          suggestions.style.display = 'none';
        }

        function showSuggestions(items) {
          suggestions.innerHTML = '';
          if (!Array.isArray(items) || items.length === 0) { clearSuggestions(); return; }
          items.forEach((r) => {
            const lat = parseFloat(r.lat); const lon = parseFloat(r.lon);
            const row = document.createElement('div');
            row.className = 'geocode-suggestion-row';
            row.style.padding = '8px 10px';
            row.style.cursor = 'pointer';
            row.style.display = 'flex';
            row.style.alignItems = 'center';
            const icon = document.createElement('span');
            icon.textContent = '📍';
            icon.style.marginRight = '8px';
            const text = document.createElement('span');
            text.textContent = String(r.display_name || '').slice(0, 140);
            row.appendChild(icon);
            row.appendChild(text);
            row.addEventListener('mouseenter', () => { row.style.background = '#f5f6f8'; });
            row.addEventListener('mouseleave', () => { row.style.background = '#fff'; });
            row.addEventListener('click', () => {
              input.value = r.display_name || '';
              clearSuggestions();
              if (Number.isFinite(lat) && Number.isFinite(lon)) {
                center.value = [lat, lon];
                searchMarkerLatLng.value = [lat, lon];
                form.coordinates = `${lat},${lon}`; // always set coordinates
                geofenceInfo.lat = lat;
                geofenceInfo.lng = lon;
                geofenceInfo.address = String(r.display_name || '').trim();
                geofenceInfo.coordinates = [[lat, lon]];
                if (form.type === 'circle') {
                  circleCenter.value = [lat, lon];
                  if (!form.radius || !Number.isFinite(Number(form.radius))) {
                    form.radius = 1000;
                  }
                }
              } else {
                // fallback: run normal geocode
                searchQuery.value = input.value;
                searchAddress();
              }
            });
            suggestions.appendChild(row);
          });
          suggestions.style.display = 'block';
        }

        function debounce(fn, delay) {
          let t;
          return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), delay); };
        }

        const fetchSuggestions = async (q) => {
          const query = String(q || '').trim();
          if (!query) { clearSuggestions(); return; }
          try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=6&addressdetails=1`;
            const res = await fetch(url, { credentials: 'omit' });
            const data = await res.json();
            showSuggestions(Array.isArray(data) ? data : []);
          } catch (e) {
            // Fallback to Photon (Komoot) if Nominatim fails
            try {
              const url2 = `https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=6`;
              const res2 = await fetch(url2, { credentials: 'omit' });
              const data2 = await res2.json();
              const items = Array.isArray(data2?.features) ? data2.features.map(f => ({
                lat: f?.geometry?.coordinates?.[1],
                lon: f?.geometry?.coordinates?.[0],
                display_name: f?.properties?.name || f?.properties?.label || ''
              })) : [];
              showSuggestions(items);
            } catch { clearSuggestions(); }
          }
        };
        const fetchSuggestionsDebounced = debounce(() => fetchSuggestions(input.value), 250);

        container.appendChild(input);
        container.appendChild(btn);
        container.appendChild(suggestions);
        L.DomEvent.disableClickPropagation(container);

        btn.addEventListener('click', () => { clearSuggestions(); searchQuery.value = input.value; searchAddress(); });
        input.addEventListener('keyup', (e) => { if (e.key === 'Enter') { clearSuggestions(); searchQuery.value = input.value; searchAddress(); } });
        const onInput = () => {
          if (!(window.google && window.google.maps && window.google.maps.places)) {
            fetchSuggestionsDebounced();
          }
        };
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', (e) => { if (e.key === 'Escape') clearSuggestions(); });
        input.addEventListener('blur', () => { setTimeout(clearSuggestions, 150); });
        // try to enable Google Places Autocomplete
        loadGooglePlacesScript().then(() => {
          setupGoogleAutocomplete(input, (lat, lon) => {
            center.value = [lat, lon];
            searchMarkerLatLng.value = [lat, lon];
            form.coordinates = `${lat},${lon}`;
            geofenceInfo.lat = lat;
            geofenceInfo.lng = lon;
            geofenceInfo.address = String(input.value || '').trim();
            geofenceInfo.coordinates = [[lat, lon]];
            if (form.type === 'circle') {
              circleCenter.value = [lat, lon];
              if (!form.radius || !Number.isFinite(Number(form.radius))) {
                form.radius = 1000;
              }
            }
            m.setView([lat, lon], m.getZoom());
          }, clearSuggestions);
        }).catch(() => {});
        return container;
      },
      onRemove: () => {}
    });
    const ctl = new GeocodeCtl({ position: 'topleft' });
    map.addControl(ctl);
  } catch (e) {
    // ignore control attach errors
  }
}

function setupGeoman(map) {
  try {
    if (!map || !map.pm) return;
    updateGeomanControls();
    // listen create/edit/remove to sync form values
    map.on('pm:create', (e) => {
      const layer = e?.layer;
      const shape = layer?.pm?.getShape?.();
      if (shape === 'Polygon') {
        const latlngs = layer.getLatLngs();
        const pts = Array.isArray(latlngs[0]) ? latlngs[0] : latlngs;
        polygonPoints.value = pts.map(ll => [ll.lat, ll.lng]);
        form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
        form.coordinates = '';
        circleCenter.value = null;
      } else if (shape === 'Rectangle') {
        const b = layer.getBounds();
        const sw = b.getSouthWest();
        const ne = b.getNorthEast();
        rectanglePoints.value = [[sw.lat, sw.lng], [ne.lat, ne.lng]];
        polygonPoints.value = [
          [sw.lat, sw.lng],
          [sw.lat, ne.lng],
          [ne.lat, ne.lng],
          [ne.lat, sw.lng],
        ];
        form.coordinates = `${sw.lat},${sw.lng}; ${ne.lat},${ne.lng}`;
        form.polygon = '';
        circleCenter.value = null;
      }
    });
    map.on('pm:edit', (e) => {
      const layer = e?.layer;
      const shape = layer?.pm?.getShape?.();
      if (shape === 'Polygon') {
        const latlngs = layer.getLatLngs();
        const pts = Array.isArray(latlngs[0]) ? latlngs[0] : latlngs;
        polygonPoints.value = pts.map(ll => [ll.lat, ll.lng]);
        form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
        form.coordinates = '';
      } else if (shape === 'Rectangle') {
        const b = layer.getBounds();
        const sw = b.getSouthWest();
        const ne = b.getNorthEast();
        rectanglePoints.value = [[sw.lat, sw.lng], [ne.lat, ne.lng]];
        polygonPoints.value = [
          [sw.lat, sw.lng],
          [sw.lat, ne.lng],
          [ne.lat, ne.lng],
          [ne.lat, sw.lng],
        ];
        form.coordinates = `${sw.lat},${sw.lng}; ${ne.lat},${ne.lng}`;
        form.polygon = '';
      }
    });
    map.on('pm:remove', () => {
      polygonPoints.value = [];
      rectanglePoints.value = [];
      form.coordinates = '';
      form.polygon = '';
    });
  } catch {}
}

function updateGeomanControls() {
  const map = mapRef.value;
  try {
    if (!map || !map.pm) return;
    // Remove existing controls before adding new ones to reflect type
    try { map.pm.removeControls(); } catch {}
    map.pm.addControls({
      position: 'topleft',
      drawMarker: false,
      drawCircleMarker: false,
      drawPolyline: false,
      drawCircle: false,
      drawRectangle: form.type !== 'circle' && form.type === 'rectangle',
      drawPolygon: form.type !== 'circle' && form.type === 'polygon',
      editMode: form.type !== 'circle',
      dragMode: form.type !== 'circle',
      removalMode: form.type !== 'circle',
    });
    map.pm.setGlobalOptions({
      layerGroup: undefined,
      pathOptions: { color: '#1070e3', weight: 2, fillColor: '#1070e3', fillOpacity: 0.25 },
    });
  } catch {}
}

function clearGeomanLayers() {
  const map = mapRef.value;
  try {
    if (!map || !map.pm) return;
    const layers = map.pm.getGeomanLayers();
    layers.forEach(l => { try { map.removeLayer(l); } catch {} });
  } catch {}
}

function fitMapToCurrentShape() {
  if (mapProvider.value === 'google') {
    let gm = googleMapInternal.value;
    if (!gm) {
        const comp = googleMapRef.value;
        if (comp && comp.map) {
            gm = comp.map.value || comp.map;
        }
    }
    if (!gm || typeof gm.setCenter !== 'function') return;

    // Prioritize centering on current location (center.value) for Add Zone
    if (Array.isArray(center.value) && center.value.length === 2) {
      gm.setCenter({ lat: center.value[0], lng: center.value[1] });
      gm.setZoom(16);
      return;
    }

    if (Array.isArray(polygonPoints.value) && polygonPoints.value.length) {
      const bounds = new window.google.maps.LatLngBounds();
      polygonPoints.value.forEach(p => bounds.extend({ lat: p[0], lng: p[1] }));
      gm.fitBounds(bounds);
      return;
    }
    if (Array.isArray(rectanglePoints.value) && rectanglePoints.value.length === 2) {
      const bounds = new window.google.maps.LatLngBounds();
      rectanglePoints.value.forEach(p => bounds.extend({ lat: p[0], lng: p[1] }));
      gm.fitBounds(bounds);
      return;
    }
    if (Array.isArray(circleCenter.value) && circleCenter.value.length === 2) {
       gm.setCenter({ lat: circleCenter.value[0], lng: circleCenter.value[1] });
       gm.setZoom(16);
       return;
    }
    return;
  }

  const map = mapRef.value;
  try {
    if (!map) return;
    if (Array.isArray(polygonPoints.value) && polygonPoints.value.length) {
      const bounds = L.latLngBounds(polygonPoints.value.map(p => L.latLng(p[0], p[1])));
      map.fitBounds(bounds, { padding: [24, 24] });
      return;
    }
    if (Array.isArray(rectanglePoints.value) && rectanglePoints.value.length === 2) {
      const [p1, p2] = rectanglePoints.value;
      const minLat = Math.min(p1[0], p2[0]);
      const maxLat = Math.max(p1[0], p2[0]);
      const minLng = Math.min(p1[1], p2[1]);
      const maxLng = Math.max(p1[1], p2[1]);
      const bounds = L.latLngBounds(L.latLng(minLat, minLng), L.latLng(maxLat, maxLng));
      map.fitBounds(bounds, { padding: [24, 24] });
      return;
    }
    if (Array.isArray(circleCenter.value) && circleCenter.value.length === 2) {
      map.setView(L.latLng(circleCenter.value[0], circleCenter.value[1]), Math.max(13, map.getZoom()));
      return;
    }
    if (Array.isArray(center.value)) {
      map.setView(L.latLng(center.value[0], center.value[1]), Math.max(13, map.getZoom()));
    }
  } catch {}
}

function centerToCurrentLocation() {
  const map = mapRef.value;
  return new Promise((resolve) => {
    try {
      if (navigator?.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            center.value = [lat, lon];
            searchMarkerLatLng.value = [lat, lon];
            if (form.type === 'circle') {
              circleCenter.value = [lat, lon];
              form.coordinates = `${lat},${lon}`;
              form.radius = typeof form.radius === 'number' ? form.radius : (initialShape.value.radius || 1000);
            } else {
              const d = 0.01;
              polygonPoints.value = [
                [lat - d, lon - d],
                [lat + d, lon - d],
                [lat + d, lon + d],
                [lat - d, lon + d]
              ];
              if (form.type === 'rectangle') {
                rectanglePoints.value = [[lat - d, lon - d], [lat + d, lon + d]];
              }
            }
            // Ensure zoom level is appropriate for viewing the location/shape
            zoom.value = 16;
            try { map && map.setView([lat, lon], Math.max(13, map.getZoom())); } catch {}
            // Fit map to the new shape (especially for Google Maps)
            setTimeout(() => { fitMapToCurrentShape(); }, 2000);
            resolve();
          },
          () => {
            // Fallback to current center if geolocation fails
            const [lat, lon] = center.value;
            if (form.type !== 'circle') {
              const d = 0.01;
              polygonPoints.value = [
                [lat - d, lon - d],
                [lat + d, lon - d],
                [lat + d, lon + d],
                [lat - d, lon + d]
              ];
              if (form.type === 'rectangle') {
                rectanglePoints.value = [[lat - d, lon - d], [lat + d, lon + d]];
              }
            }
            zoom.value = 16;
            setTimeout(() => { fitMapToCurrentShape(); }, 2000);
            resolve();
          }
        );
      } else {
        // Fallback if no geolocation support
        const [lat, lon] = center.value;
        if (form.type !== 'circle') {
          const d = 0.01;
          polygonPoints.value = [
            [lat - d, lon - d],
            [lat + d, lon - d],
            [lat + d, lon + d],
            [lat - d, lon + d]
          ];
          if (form.type === 'rectangle') {
            rectanglePoints.value = [[lat - d, lon - d], [lat + d, lon + d]];
          }
        }
        zoom.value = 16;
        setTimeout(() => { fitMapToCurrentShape(); }, 2000);
        resolve();
      }
    } catch { resolve(); }
  });
}

async function resetWholeMap() {
  try {
    if (drawingManager) drawingManager.setDrawingMode(null);
    // Clear all shapes and geoman layers, reset geofence info
    clearShapes();
    updateGeomanControls();
    drawing.value = form.type !== 'circle';
    searchMarkerLatLng.value = null;
    geofenceInfo.coordinates = [];
    geofenceInfo.lat = null;
    geofenceInfo.lng = null;
    // Refresh map rendering
    mapKey.value++;
    const m = mapRef.value; try { m && m.invalidateSize(true); } catch {}
    // Center to current device location if available
    await centerToCurrentLocation();
  } catch {}
}

watch(() => form.type, async () => {
  // When switching type, clear shapes and auto-enter drawing mode for polygon/rectangle
  clearShapes();
  searchMarkerLatLng.value = null;
  updateGeomanControls();
  drawing.value = form.type !== 'circle';

  mapKey.value++;
  const map = mapRef.value; try { map && map.invalidateSize(true); } catch {}
  // Also refresh geofenceInfo
  geofenceInfo.type = form.type;
  geofenceInfo.coordinates = [];
  geofenceInfo.lat = null;
  geofenceInfo.lng = null;
  if (form.type !== 'circle') {
     geofenceInfo.radius = null;
     form.radius = undefined;
  }

  await centerToCurrentLocation();
});

async function searchAddress() {
  const q = String(searchQuery.value || '').trim();
  if (!q) return;
  const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1&addressdetails=1`;
  // First try Nominatim
  try {
    const res = await fetch(nominatimUrl, { credentials: 'omit' });
    const data = await res.json();
    if (Array.isArray(data) && data.length) {
      const r = data[0];
      const lat = parseFloat(r.lat); const lon = parseFloat(r.lon);
      if (Number.isFinite(lat) && Number.isFinite(lon)) {
        center.value = [lat, lon];
        form.coordinates = `${lat},${lon}`;
        geofenceInfo.lat = lat;
        geofenceInfo.lng = lon;
        geofenceInfo.address = String(r.display_name || '').trim();
        geofenceInfo.coordinates = [[lat, lon]];
        if (form.type === 'circle') {
          circleCenter.value = [lat, lon];
          form.radius = typeof form.radius === 'number' ? form.radius : 1000;
          geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
        }
        return;
      }
    }
  } catch (e) { /* ignore */ }
  // Fallback to Photon
  try {
    const url2 = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&limit=1`;
    const res2 = await fetch(url2, { credentials: 'omit' });
    const data2 = await res2.json();
    const f = Array.isArray(data2?.features) ? data2.features[0] : null;
    if (f && Array.isArray(f.geometry?.coordinates)) {
      const lon = parseFloat(f.geometry.coordinates[0]);
      const lat = parseFloat(f.geometry.coordinates[1]);
      if (Number.isFinite(lat) && Number.isFinite(lon)) {
        center.value = [lat, lon];
        form.coordinates = `${lat},${lon}`;
        geofenceInfo.lat = lat;
        geofenceInfo.lng = lon;
        geofenceInfo.address = String(f?.properties?.name || f?.properties?.label || '').trim();
        geofenceInfo.coordinates = [[lat, lon]];
        if (form.type === 'circle') {
          circleCenter.value = [lat, lon];
          form.radius = typeof form.radius === 'number' ? form.radius : 1000;
          geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
        }
      }
    }
  } catch (e) { /* ignore */ }
}


async function submit() {
  message.value = '';
  error.value = '';
  submitting.value = true;
  try {
    // derive payload based on type
    if (form.type === 'circle') {
      // form.coordinates already set from center click/search
      form.polygon = '';
    } else if (form.type === 'rectangle') {
      form.polygon = '';
      // ensure coordinates are two points
      if (!form.coordinates && rectanglePoints.value.length === 2) {
        const [p1, p2] = rectanglePoints.value;
        form.coordinates = `${p1[0]},${p1[1]}; ${p2[0]},${p2[1]}`;
      }
    } else if (form.type === 'polygon') {
      form.coordinates = '';
      if (!form.polygon && polygonPoints.value.length >= 3) {
        form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
      }
    }
    // Build payload following geofenceInfo flow, mapping to backend expected strings
    const coordsStringCircle = geofenceInfo.lat != null && geofenceInfo.lng != null
      ? `${geofenceInfo.lat},${geofenceInfo.lng}`
      : String(form.coordinates || '').trim() || null;
    const coordsStringRect = rectanglePoints.value.length === 2
      ? `${rectanglePoints.value[0][0]},${rectanglePoints.value[0][1]}; ${rectanglePoints.value[1][0]},${rectanglePoints.value[1][1]}`
      : String(form.coordinates || '').trim() || null;
    const polygonString = polygonPoints.value.length >= 3
      ? polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ')
      : String(form.polygon || '').trim() || null;
    const payload = {
      name: geofenceInfo.name || String(form.name || '').trim(),
      description: geofenceInfo.address || String(form.description || '').trim() || null,
      status: form.status || 'active',
      speed: typeof form.speed === 'number' ? form.speed : null,
      coordinates: form.type === 'circle' ? coordsStringCircle : (form.type === 'rectangle' ? coordsStringRect : null),
      radius: form.type === 'circle' ? (typeof geofenceInfo.radius === 'number' ? geofenceInfo.radius : (typeof form.radius === 'number' ? form.radius : null)) : null,
      polygon: form.type === 'polygon' ? polygonString : null,
      type: geofenceInfo.type || form.type,
    };

    const { data } = await axios.post('/web/zones', payload);
    message.value = data?.message || 'Zone created';
    setTimeout(() => router.push('/zones'), 400);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to add zone';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.map-frame { position: relative; height: 380px; border-radius: 12px; overflow: hidden; }
#zoneAddMap { height: 100%; width: 100%; }
.map-tools { position: absolute; top: 8px; right: 8px; z-index: 1000; }
.map-controls { position: absolute; left: 8px; bottom: 8px; display: flex; align-items: center; }
.map-controls { z-index: 1000; }
.map-controls .btn-group .btn { background: #fff; border-color: #ddd; }
.map-controls .btn-group .btn.active { background: #0b0f28; color: #fff; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
.geocode-suggestions { font-size: 13px; }
.geocode-suggestion-row { border-bottom: 1px solid #eee; }
.geocode-suggestion-row:last-child { border-bottom: 0; }
</style>

<style>
/* Ensure Google Places dropdown renders above the map and controls */
.pac-container { z-index: 2000 !important; }
</style>
