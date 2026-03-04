<template>
  <div class="zones-edit-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">
          <RouterLink to="/zones">Zone Management</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Edit Zone</li>
      </ol>
    </div>

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-12">
        <h4 class="mb-0 fw-semibold">Edit Zone</h4>
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
            <select v-model="form.type" class="form-select" @change="onTypeChange">
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
          <div class="btn-group" role="group" style="position: absolute; top: 10px; right: 100px; z-index: 1000;">
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
          <l-map v-if="mapProvider === 'leaflet'" :key="mapKey" id="zoneEditMap" :zoom="zoom" :center="center" :options="mapOptions" @ready="onMapReady">
            <l-tile-layer :url="tileUrl" :attribution="tileAttribution" />
            <!-- Default center marker -->
            <l-marker :lat-lng="center" />
            <l-polygon v-if="polygonPoints.length" :lat-lngs="polygonPoints" :color="'#1070e3'" :weight="2" :fillColor="'#1070e3'" :fillOpacity="0.25" />
            <l-circle v-if="form.type === 'circle'" :lat-lng="center" :radius="form.radius || 100" :color="'#3f8fd7'" :weight="1" :fillColor="'#3f8fd7'" :fillOpacity="0.25" />
            <!-- Draggable markers for vertices and search -->
            <l-marker v-for="(p,i) in polygonPoints" :key="'poly-'+i" :lat-lng="p" :draggable="true" @dragend="onDrawMarkerDragEnd('polygon', i, $event)" />
            <l-marker v-for="(p,i) in rectanglePoints" :key="'rect-'+i" :lat-lng="p" :draggable="true" @dragend="onDrawMarkerDragEnd('rectangle', i, $event)" />
            <l-marker v-if="searchMarkerLatLng" :lat-lng="searchMarkerLatLng" :draggable="true" @dragend="onSearchMarkerDragEnd" />
          </l-map>
          <!-- Map tools: Reset shape to original and center to current location -->
          <div class="map-tools" style="position: absolute; top: 10px; right: 10px; z-index: 1000;">
            <div class="btn-group btn-group-sm" role="group">
              <button type="button" class="btn btn-light shadow-sm" @click="resetWholeMap">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="card-footer d-flex align-items-center justify-content-end gap-2 py-2">
        <RouterLink to="/zones" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button class="btn btn-app-dark" :disabled="submitting" @click="submit">
          <span v-if="submitting" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
          Save Changes
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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

const route = useRoute();
const router = useRouter();
const zoneId = ref(route.params.zoneId);

const form = reactive({
  name: '',
  speed: undefined,
  status: 'active',
  description: '',
  type: 'polygon',
  coordinates: '',
  radius: undefined,
  polygon: ''
});

// Central geofenceInfo object, mirroring geofencing.vue
const geofenceInfo = reactive({
  lat: null,
  lng: null,
  radius: 200,
  name: '',
  address: '',
  user_id: '',
  type: 'polygon',
  coordinates: [],
  geofenceId: ''
});

const message = ref('');
const error = ref('');
const submitting = ref(false);

const zoom = ref(15);
const center = ref([38.627, -90.199]);
const basemap = ref('map');
const mapKey = ref(0);
const mapProvider = ref('leaflet');
const tileAttribution = '';
const mapOptions = { zoomControl: true, attributionControl: false };
const mapRef = ref(null);
const googleMapRef = ref(null);
const googleMapInternal = ref(null);
const suppressTypeWatch = ref(false);

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
  // Center marker (matches Leaflet's l-marker)
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
  if ((form.type === 'polygon' || form.type === 'rectangle') && polygonPoints.value.length >= 3) {
    return [{
      paths: polygonPoints.value.map(p => ({ lat: p[0], lng: p[1] })),
      options: { color: '#1070e3', fillColor: '#1070e3', fillOpacity: 0.25 }
    }];
  }
  return [];
});

const googleCircles = computed(() => {
  if (form.type === 'circle' && circleCenter.value) {
    return [{
      center: { lat: circleCenter.value[0], lng: circleCenter.value[1] },
      radius: Number(form.radius || 100),
      options: { color: '#3f8fd7', fillColor: '#3f8fd7', fillOpacity: 0.25 }
    }];
  }
  return [];
});

const circleCenter = ref(null);
const polygonPoints = ref([]);
const rectanglePoints = ref([]);
const drawing = ref(false);
const searchQuery = ref('');
const searchMarkerLatLng = ref(null);
const searchMarkerIcon = ref(null);
const googleSearchInput = ref(null);

watch(mapProvider, async (val) => {
  if (val === 'google') {
    // Attempt to fit shape as soon as provider switches, with retries
    const tryFit = () => {
      // Check if map is actually ready via internal ref or component ref
      const gmInternal = googleMapInternal.value;
      const gmComp = googleMapRef.value;
      const isMapReady = gmInternal || (gmComp && (gmComp.map || (gmComp.map && gmComp.map.value)));

      if (isMapReady) {
        fitMapToCurrentShape();
        setupGoogleDrawingManager();
      } else {
        setTimeout(tryFit, 100);
      }
    };
    tryFit();
    // Additional backups
    setTimeout(() => { fitMapToCurrentShape(); }, 500);
    setTimeout(() => { fitMapToCurrentShape(); }, 1200);

    try {
      await loadGooglePlacesScript();
      setTimeout(() => {
        if (googleSearchInput.value) {
          setupGoogleAutocomplete(googleSearchInput.value, (lat, lng, icon) => {
            const addr = googleSearchInput.value.value;
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
              if (addr) {
                geofenceInfo.address = addr;
              }
            } else {
              const d = 0.01;
              polygonPoints.value = [
                [lat - d, lng - d],
                [lat + d, lng - d],
                [lat + d, lng + d],
                [lat - d, lng + d]
              ];
              if (form.type === 'rectangle') {
                rectanglePoints.value = [[lat - d, lng - d], [lat + d, lng + d]];
                geofenceInfo.coordinates = rectanglePoints.value.map(p => [p[0], p[1]]);
              } else {
                geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
              }
              geofenceInfo.lat = lat;
              geofenceInfo.lng = lng;
              if (addr) {
                geofenceInfo.address = addr;
              }
            }
          });
        }
      }, 200);
    } catch (e) {
      console.error('Failed to load Google Places', e);
    }
  }
});

// Persist the originally loaded shape so type switching can re-render exact geometry
const loadedShape = ref({ type: null, coordinates: [], lat: null, lng: null, radius: null });

// Sync basic form fields into geofenceInfo
watch(() => form.name, (v) => { geofenceInfo.name = String(v || '').trim(); });
watch(() => form.description, (v) => { geofenceInfo.address = String(v || '').trim(); });
watch(() => form.radius, (v) => { geofenceInfo.radius = typeof v === 'number' ? v : geofenceInfo.radius; });
watch(() => form.type, (v) => {
  if (suppressTypeWatch.value) return;
  geofenceInfo.type = v;

  // Clear/Setup shapes based on type
  if (v === 'circle') {
    polygonPoints.value = [];
    rectanglePoints.value = [];
    // Default to center if no circle exists
    if (!circleCenter.value) {
      circleCenter.value = [...center.value];
      form.radius = form.radius || 1000;
    }
  } else {
    circleCenter.value = null;
    if (v === 'polygon' && polygonPoints.value.length === 0) {
       const c = center.value;
       const d = 0.01;
       polygonPoints.value = [
         [c[0]-d, c[1]-d],
         [c[0]+d, c[1]-d],
         [c[0]+d, c[1]+d],
         [c[0]-d, c[1]+d]
       ];
    }
  }
  updateDrawingMode();
});

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
      if (typeof onPlaceSelected === 'function') onPlaceSelected(lat, lon, place.icon || null);
    });
  } catch {}
}

// Helper: parse WKT area string from Traccar into a shape
function parseWKTArea(area) {
  try {
    const s = String(area || '').trim();
    if (!s) return null;
    // Helper: haversine distance (meters)
    const toRad = (deg) => deg * Math.PI / 180;
    const haversine = (lat1, lon1, lat2, lon2) => {
      const R = 6378137; // meters
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      return R * c;
    };
    const isApproxCircle = (ptsLatLng) => {
      if (!Array.isArray(ptsLatLng) || ptsLatLng.length < 16) return null; // need enough points to test roundness
      // compute centroid
      let sumLat = 0, sumLng = 0; ptsLatLng.forEach(p => { sumLat += p[0]; sumLng += p[1]; });
      const centLat = sumLat / ptsLatLng.length; const centLng = sumLng / ptsLatLng.length;
      // distances
      const dists = ptsLatLng.map(p => haversine(centLat, centLng, p[0], p[1]));
      const mean = dists.reduce((a,b)=>a+b,0) / dists.length;
      const varSum = dists.reduce((a,b)=> a + Math.pow(b-mean,2), 0) / dists.length;
      const std = Math.sqrt(varSum);
      const ratio = std / (mean || 1);
      if (ratio < 0.05) { // within 5% -> circle-like
        return { lat: centLat, lng: centLng, radius: Math.round(mean) };
      }
      return null;
    };
    if (s.startsWith('POLYGON')) {
      const m = s.match(/\(\((.*)\)\)/);
      const body = m ? m[1] : '';
      const parts = body.split(',').map(p => p.trim()).filter(Boolean);
      const pts = parts.map(pair => pair.split(/[\s]+/).map(Number)).filter(a => a.length === 2 && a.every(v => Number.isFinite(v))).map(([lon, lat]) => [lat, lon]);
      // Remove closing duplicate if present
      if (pts.length >= 2) {
        const first = pts[0];
        const last = pts[pts.length - 1];
        if (first[0] === last[0] && first[1] === last[1]) pts.pop();
      }
      // Detect circle-like polygon and return circle
      const circleLike = isApproxCircle(pts);
      if (circleLike) {
        return { type: 'circle', lat: circleLike.lat, lng: circleLike.lng, radius: circleLike.radius, coordinates: [[circleLike.lat, circleLike.lng]] };
      }
      return { type: 'polygon', coordinates: pts };
    }
    // Traccar may use LINESTRING for routes
    if (s.startsWith('LINESTRING')) {
      const m = s.match(/LINESTRING\s*\((.*)\)/);
      const body = m ? m[1] : '';
      const parts = body.split(',').map(p => p.trim()).filter(Boolean);
      const pts = parts.map(pair => pair.split(/[\s]+/).map(Number)).filter(a => a.length === 2 && a.every(v => Number.isFinite(v))).map(([lon, lat]) => [lat, lon]);
      return { type: 'route', coordinates: pts };
    }
    if (s.startsWith('CIRCLE')) {
      // Example: CIRCLE (lat lon, radius)
      const m = s.match(/CIRCLE\s*\(([-+]?\d+(?:\.\d+)?)\s+([-+]?\d+(?:\.\d+)?),\s*([-+]?\d+(?:\.\d+)?)\)/);
      if (m) {
        const lat = parseFloat(m[1]);
        const lon = parseFloat(m[2]);
        const radius = parseFloat(m[3]);
        if (Number.isFinite(lat) && Number.isFinite(lon) && Number.isFinite(radius)) {
          return { type: 'circle', lat, lng: lon, radius, coordinates: [[lat, lon]] };
        }
      }
    }
    if (s.startsWith('ROUTE')) {
      const m = s.match(/ROUTE\s*\((.*)\)/);
      const body = m ? m[1] : '';
      const parts = body.split(',').map(p => p.trim()).filter(Boolean);
      const pts = parts.map(pair => pair.split(/[\s]+/).map(Number)).filter(a => a.length === 2 && a.every(v => Number.isFinite(v))).map(([lon, lat]) => [lat, lon]);
      return { type: 'route', coordinates: pts };
    }
  } catch {}
  return null;
}

function fitMapToCurrentShape() {
  if (mapProvider.value === 'google') {
    let gm = googleMapInternal.value;
    if (!gm) {
      // Fallback to component ref if internal not set yet
      const comp = googleMapRef.value;
      if (comp && comp.map) {
         gm = comp.map.value || comp.map; // Handle ref or raw
      }
    }

    if (!gm || typeof gm.setCenter !== 'function') return;

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
    if (Array.isArray(center.value) && center.value.length === 2) {
       gm.setCenter({ lat: center.value[0], lng: center.value[1] });
       gm.setZoom(Math.max(13, gm.getZoom()));
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
    // Default: center marker
    if (Array.isArray(center.value)) {
      map.setView(L.latLng(center.value[0], center.value[1]), Math.max(13, map.getZoom()));
    }
  } catch {}
}

let drawingManager = null;

function setupGoogleDrawingManager() {
  let map = googleMapInternal.value;
  if (!map) {
      const gm = googleMapRef.value;
      if (gm && gm.map) {
          map = gm.map.value || gm.map;
      }
  }
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

  // Reduce delay to make it feel more responsive, but keep a backup
  setTimeout(() => {
    fitMapToCurrentShape();
    setupGoogleDrawingManager();
  }, 200);

  // Backup fit to ensure it catches if the first one fired too early
  setTimeout(() => {
    fitMapToCurrentShape();
  }, 800);
}

function renderLoadedShape() {
  // Re-render the saved geometry regardless of current form.type
  circleCenter.value = null; polygonPoints.value = []; rectanglePoints.value = []; form.coordinates = ''; form.polygon = '';
  const s = loadedShape.value || {};
  if (!s || !s.type) return;
  if (s.type === 'circle' && s.lat != null && s.lng != null && Number.isFinite(s.radius)) {
    form.type = 'circle'; geofenceInfo.type = 'circle';
    circleCenter.value = [s.lat, s.lng];
    geofenceInfo.lat = s.lat; geofenceInfo.lng = s.lng; geofenceInfo.radius = s.radius;
    geofenceInfo.coordinates = [[s.lat, s.lng]];
    form.coordinates = `${s.lat},${s.lng}`;
    form.radius = s.radius;
    center.value = [s.lat, s.lng];
  } else if (s.type === 'polygon' && Array.isArray(s.coordinates) && s.coordinates.length >= 3) {
    form.type = 'polygon'; geofenceInfo.type = 'polygon';
    polygonPoints.value = s.coordinates.map(p => [p[0], p[1]]);
    geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
    form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
    const c0 = polygonPoints.value[0]; center.value = [c0[0], c0[1]];
  } else if (s.type === 'rectangle' && Array.isArray(s.coordinates) && s.coordinates.length === 2) {
    form.type = 'rectangle'; geofenceInfo.type = 'rectangle';
    const p1 = s.coordinates[0]; const p2 = s.coordinates[1];
    rectanglePoints.value = [[p1[0], p1[1]], [p2[0], p2[1]]];
    geofenceInfo.coordinates = [[p1[0], p1[1]], [p2[0], p2[1]]];
    form.coordinates = `${p1[0]},${p1[1]}; ${p2[0]},${p2[1]}`;
    const minLat = Math.min(p1[0], p2[0]); const maxLat = Math.max(p1[0], p2[0]); const minLng = Math.min(p1[1], p2[1]); const maxLng = Math.max(p1[1], p2[1]);
    polygonPoints.value = [[minLat,minLng],[minLat,maxLng],[maxLat,maxLng],[maxLat,minLng]];
    center.value = [ (p1[0]+p2[0])/2, (p1[1]+p2[1])/2 ];
  }
  updateGeomanControls();
  setTimeout(() => { try { const m = mapRef.value; m && m.invalidateSize(true); fitMapToCurrentShape(); } catch {} }, 40);
}

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

async function resetWholeMap() {
  try {
    if (drawingManager) drawingManager.setDrawingMode(null);
    // Clear all shapes and geoman layers, then restore loaded shape
    renderLoadedShape();
  } catch {}
}


function onMapReady(map) {
  mapRef.value = map;
  attachGeocoderControl(map);
  setupGeoman(map);
  // Bind native Leaflet click for reliable capture
  try { map.on('click', onMapClick); } catch {}
  // Ensure map fits the loaded shape once ready
  setTimeout(() => { try { fitMapToCurrentShape(); } catch {} }, 100);
}

function drawFromInputs() {
  if (form.type === 'polygon' && form.polygon) {
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
  if (form.type === 'circle' && form.coordinates && form.radius) {
    const [lat, lng] = form.coordinates.split(',').map(Number);
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
      circleCenter.value = [lat, lng];
      center.value = [lat, lng]; // Sync marker/circle center
      polygonPoints.value = [];
      return;
    }
  }
  if (form.type === 'rectangle' && form.coordinates) {
    const parts = form.coordinates.split(';');
    if (parts.length === 2) {
      const p1 = parts[0].trim().split(',').map(Number);
      const p2 = parts[1].trim().split(',').map(Number);
      if (p1.length === 2 && p2.length === 2 && p1.every(v => !Number.isNaN(v)) && p2.every(v => !Number.isNaN(v))) {
        rectanglePoints.value = [[p1[0], p1[1]], [p2[0], p2[1]]];
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
        circleCenter.value = null;
        return;
      }
    }
  }
  // Fallback to demo polygon
  polygonPoints.value = [
    [38.69, -90.32],
    [38.70, -90.18],
    [38.60, -90.08],
    [38.58, -90.28]
  ];
  circleCenter.value = null;
}

onMounted(() => {
  loadZone();
});

// Reload when navigating between different edit URLs without component unmount
watch(() => route.params.zoneId, (v) => {
  if (!v || v === zoneId.value) return;
  zoneId.value = v;
  loadZone();
});

function toPolygonString(arr) {
  if (!Array.isArray(arr) || !arr.length) return '';
  return arr.map(p => Array.isArray(p) && p.length === 2 ? `${p[0]},${p[1]}` : '').filter(Boolean).join('; ');
}

async function loadZone() {
  error.value = '';
  try {
    suppressTypeWatch.value = true;
    const { data } = await axios.get(`/web/zones/${zoneId.value}`);
    const z = data?.zone;
    const remote = data?.geofence;
    if (!z || !remote) throw new Error('Zone or geofence not found');
    geofenceInfo.geofenceId = String(z.geofence_id || '');
    // Read primary fields from remote geofence
    form.name = String(remote?.name || '').trim();
    form.description = String(remote?.description || '').trim();
    // Attributes may be an object or a JSON string; normalize
    let attrs = remote?.attributes || {};
    if (typeof attrs === 'string') {
      try { const parsed = JSON.parse(attrs); if (parsed && typeof parsed === 'object') attrs = parsed; } catch {}
    }
    form.status = String(attrs?.status || 'active');
    form.speed = Number.isFinite(parseFloat(attrs?.speed)) ? parseFloat(attrs.speed) : undefined;
    geofenceInfo.name = form.name;
    // Prefer explicit address if present in description; otherwise fall back to name
    geofenceInfo.address = String(form.description || form.name || '').trim();
    // Prefill search query with whatever textual address-like value we have
    searchQuery.value = geofenceInfo.address || '';
    // Reset map state
    circleCenter.value = null; polygonPoints.value = []; rectanglePoints.value = []; form.coordinates = ''; form.polygon = '';
    // Parse WKT area only (ignore attributes.coordinates per request)
    const parsed = parseWKTArea(remote?.area || '');
    if (parsed && parsed.type === 'circle' && parsed.lat != null && parsed.lng != null) {
      form.type = 'circle'; geofenceInfo.type = 'circle';
      circleCenter.value = [parsed.lat, parsed.lng];
      geofenceInfo.lat = parsed.lat; geofenceInfo.lng = parsed.lng;
      geofenceInfo.radius = Number.isFinite(parsed.radius) ? parsed.radius : (typeof attrs?.radius === 'number' ? attrs.radius : (form.radius || 1000));
      geofenceInfo.coordinates = [[parsed.lat, parsed.lng]];
      form.coordinates = `${parsed.lat},${parsed.lng}`;
      form.radius = geofenceInfo.radius;
      center.value = [parsed.lat, parsed.lng];
      loadedShape.value = { type: 'circle', lat: parsed.lat, lng: parsed.lng, radius: geofenceInfo.radius };
    } else if (parsed && (parsed.type === 'polygon' || parsed.type === 'route') && Array.isArray(parsed.coordinates) && parsed.coordinates.length >= (parsed.type === 'route' ? 2 : 3)) {
      form.type = 'polygon'; geofenceInfo.type = 'polygon';
      polygonPoints.value = parsed.coordinates;
      geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
      form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
      const c0 = polygonPoints.value[0]; center.value = [c0[0], c0[1]];
      loadedShape.value = { type: 'polygon', coordinates: polygonPoints.value.map(p => [p[0], p[1]]) };
    } else {
      // Fallback: Check attributes for saved circle if WKT area is missing/invalid
      const lat = Number(attrs?.lat);
      const lng = Number(attrs?.long || attrs?.lng);
      const rad = Number(attrs?.radius);
      const type = attrs?.type;

      if (type === 'circle' && Number.isFinite(lat) && Number.isFinite(lng)) {
          form.type = 'circle'; geofenceInfo.type = 'circle';
          circleCenter.value = [lat, lng];
          geofenceInfo.lat = lat; geofenceInfo.lng = lng;
          geofenceInfo.radius = Number.isFinite(rad) ? rad : (form.radius || 1000);
          geofenceInfo.coordinates = [[lat, lng]];
          form.coordinates = `${lat},${lng}`;
          form.radius = geofenceInfo.radius;
          center.value = [lat, lng];
          loadedShape.value = { type: 'circle', lat: lat, lng: lng, radius: geofenceInfo.radius };
      }
      // If area is missing or unparsable, leave demo fallback
    }
    // console.log('zoneformdata ',form,loadedShape);
    // Ensure controls reflect type
    updateGeomanControls();

    // Force map re-render to ensure layers (especially l-circle) pick up the new state
    mapKey.value++;

    // If we don't have a real address yet (or it's just name/description), try reverse geocoding by center
    setTimeout(() => { try { reverseGeocodeForCenter(); } catch {} }, 500);

    // Ensure map fits the loaded shape
    setTimeout(() => { fitMapToCurrentShape(); }, 1000);

    suppressTypeWatch.value = false;
    // Debug: log what was parsed to help diagnose if shape still missing
    try { console.debug('[Edit] Geofence loaded', { remote, attrs, type: form.type, polygonPoints: polygonPoints.value, rectanglePoints: rectanglePoints.value, circleCenter: circleCenter.value, radius: form.radius }); } catch {}
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load zone';
  }
}

async function reverseGeocodeForCenter() {
  try {
    const c = Array.isArray(center.value) ? center.value : null;
    const lat = Number(c?.[0]); const lon = Number(c?.[1]);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`;
    const res = await fetch(url, { credentials: 'omit' });
    const data = await res.json();
    const display = String(data?.display_name || '').trim();
    if (display) {
      geofenceInfo.address = display;
      searchQuery.value = display;
      try { const el = document.getElementById('zone-edit-geocode-input'); if (el) el.value = display; } catch {}
    }
  } catch {}
}

async function submit() {
  message.value = '';
  error.value = '';
  submitting.value = true;
  try {
    // Front-end validation
    const errs = [];
    const t = form.type;
    if (!String(form.name || '').trim()) errs.push('Name is required');
    if (t === 'circle') {
      if (!(Array.isArray(geofenceInfo.coordinates) && geofenceInfo.coordinates.length===1)) errs.push('Select circle center');
      if (!(typeof geofenceInfo.radius === 'number' && geofenceInfo.radius > 0)) errs.push('Radius must be greater than 0');
    } else if (t === 'rectangle') {
      if (!(Array.isArray(geofenceInfo.coordinates) && geofenceInfo.coordinates.length===2)) errs.push('Complete rectangle with two corners');
    } else if (t === 'polygon') {
      if (!(Array.isArray(geofenceInfo.coordinates) && geofenceInfo.coordinates.length>=3)) errs.push('Polygon requires at least 3 points');
    }
    if (errs.length) { error.value = errs.join('\n'); submitting.value = false; return; }
    // derive payload based on type
    if (form.type === 'circle') {
      form.polygon = '';
    } else if (form.type === 'rectangle') {
      form.polygon = '';
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
    // Build payload from geofenceInfo, matching geofencing.vue request shape
    const coordsCircle = geofenceInfo.lat != null && geofenceInfo.lng != null
      ? `${geofenceInfo.lat},${geofenceInfo.lng}`
      : (String(form.coordinates || '').includes(',') ? String(form.coordinates).trim() : null);
    const coordsRect = rectanglePoints.value.length === 2
      ? `${rectanglePoints.value[0][0]},${rectanglePoints.value[0][1]}; ${rectanglePoints.value[1][0]},${rectanglePoints.value[1][1]}`
      : (String(form.coordinates || '').includes(';') ? String(form.coordinates).trim() : null);
    const polyStr = polygonPoints.value.length >= 3
      ? polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ')
      : (String(form.polygon || '').trim() || null);
    const payload = {
      name: geofenceInfo.name || String(form.name || '').trim(),
      description: geofenceInfo.address || String(form.description || '').trim() || null,
      status: form.status || 'active',
      speed: typeof form.speed === 'number' ? form.speed : null,
      coordinates: form.type === 'circle' ? coordsCircle : (form.type === 'rectangle' ? coordsRect : null),
      radius: typeof geofenceInfo.radius === 'number' ? geofenceInfo.radius : (typeof form.radius === 'number' ? form.radius : null),
      polygon: form.type === 'polygon' ? polyStr : null,
      type: geofenceInfo.type || form.type,
    };
    const { data } = await axios.put(`/web/zones/${zoneId.value}`, payload);
    message.value = data?.message || 'Zone updated';
    setTimeout(() => router.push('/zones'), 400);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update zone';
  } finally {
    submitting.value = false;
  }
}

function onTypeChange() {
  clearShapes();
  updateGeomanControls();
  drawing.value = form.type !== 'circle';
  // Refresh map view so controls and layers update immediately
  const map = mapRef.value; try { map && map.invalidateSize(true); } catch {}
}

function onMapClick(e) {
  const latlng = e.latlng ? [e.latlng.lat, e.latlng.lng] : null;
  if (!latlng) return;
  center.value = latlng; // move center marker
  if (form.type === 'circle') {
    circleCenter.value = latlng;
    form.coordinates = `${latlng[0]},${latlng[1]}`;
    searchMarkerLatLng.value = latlng;
    geofenceInfo.lat = latlng[0];
    geofenceInfo.lng = latlng[1];
    geofenceInfo.coordinates = [[latlng[0], latlng[1]]];
    geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
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
    geofenceInfo.lat = latlng[0];
    geofenceInfo.lng = latlng[1];
    geofenceInfo.coordinates = [[latlng[0], latlng[1]]];
    if (form.type === 'circle') {
      circleCenter.value = latlng;
      geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
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
    form.polygon = polygonPoints.value.map(p => `${p[0]},${p[1]}`).join('; ');
    drawing.value = false;
    geofenceInfo.coordinates = polygonPoints.value.map(p => [p[0], p[1]]);
  } else if (form.type === 'rectangle' && rectanglePoints.value.length === 2) {
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
          form.coordinates = `${lat},${lon}`; // always set coordinates
          geofenceInfo.lat = lat;
          geofenceInfo.lng = lon;
          geofenceInfo.coordinates = [[lat, lon]];
          geofenceInfo.address = String(e?.location?.label || '').trim();
          if (form.type === 'circle') {
            circleCenter.value = [lat, lon];
            if (!form.radius || !Number.isFinite(Number(form.radius))) {
              form.radius = 1000;
            }
          }
          map.setView([lat, lon], map.getZoom());
        }
      });
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
        // Provide a stable id so we can update this from elsewhere if needed
        input.id = 'zone-edit-geocode-input';
        // Prefill with existing address/description/name if available
        try {
          const initialAddress = String(geofenceInfo.address || form.description || form.name || '').trim();
          if (initialAddress) { input.value = initialAddress; searchQuery.value = initialAddress; }
        } catch {}

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

        function clearSuggestions() { suggestions.innerHTML = ''; suggestions.style.display = 'none'; }
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
            const icon = document.createElement('span'); icon.textContent = '📍'; icon.style.marginRight = '8px';
            const text = document.createElement('span'); text.textContent = String(r.display_name || '').slice(0, 140);
            row.appendChild(icon); row.appendChild(text);
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
                geofenceInfo.coordinates = [[lat, lon]];
                geofenceInfo.address = String(r.display_name || '').trim();
                if (form.type === 'circle') {
                  circleCenter.value = [lat, lon];
                  if (!form.radius || !Number.isFinite(Number(form.radius))) {
                    form.radius = 1000;
                  }
                }
              } else {
                searchQuery.value = input.value; searchAddress();
              }
            });
            suggestions.appendChild(row);
          });
          suggestions.style.display = 'block';
        }

        function debounce(fn, delay) { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), delay); }; }
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
            geofenceInfo.coordinates = [[lat, lon]];
            geofenceInfo.address = String(input.value || '').trim();
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
    // Remove existing controls before adding new to reflect type
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

watch(() => form.type, () => {
  if (suppressTypeWatch.value) return;
  // When switching type, clear shapes and update controls instead of reloading saved geometry
  clearShapes();
  updateGeomanControls();
  drawing.value = form.type !== 'circle';
  searchMarkerLatLng.value = null;
  mapKey.value++;
  const map = mapRef.value; try { map && map.invalidateSize(true); } catch {}
  geofenceInfo.type = form.type;
  geofenceInfo.coordinates = [];
  geofenceInfo.lat = null;
  geofenceInfo.lng = null;
});

async function searchAddress() {
  const q = String(searchQuery.value || '').trim();
  if (!q) return;
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1&addressdetails=1`;
    const res = await fetch(url, { credentials: 'omit' });
    const data = await res.json();
    if (Array.isArray(data) && data.length) {
      const r = data[0];
      const lat = parseFloat(r.lat); const lon = parseFloat(r.lon);
      if (Number.isFinite(lat) && Number.isFinite(lon)) {
        center.value = [lat, lon];
        form.coordinates = `${lat},${lon}`;
        geofenceInfo.lat = lat;
        geofenceInfo.lng = lon;
        geofenceInfo.coordinates = [[lat, lon]];
        geofenceInfo.address = String(r.display_name || '').trim();
        if (form.type === 'circle') {
          circleCenter.value = [lat, lon];
          form.radius = typeof form.radius === 'number' ? form.radius : 1000;
          geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
        }
      }
      return;
    }
  } catch {}
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
        geofenceInfo.coordinates = [[lat, lon]];
        geofenceInfo.address = String(f?.properties?.name || f?.properties?.label || '').trim();
        if (form.type === 'circle') {
          circleCenter.value = [lat, lon];
          form.radius = typeof form.radius === 'number' ? form.radius : 1000;
          geofenceInfo.radius = typeof form.radius === 'number' ? form.radius : (geofenceInfo.radius || 1000);
        }
      }
    }
  } catch {}
}

</script>

<style scoped>
.map-frame { position: relative; height: 380px; border-radius: 12px; overflow: hidden; }
#zoneEditMap { height: 100%; width: 100%; }
.map-tools { position: absolute; top: 8px; right: 8px; z-index: 1000; }
.map-controls { position: absolute; left: 8px; bottom: 8px; display: flex; align-items: center; z-index: 1000; }
.map-controls .btn-group .btn { background: #fff; border-color: #ddd; }
.map-controls .btn-group .btn.active { background: var(--brand-primary); color: #fff; }
.geocode-suggestions { font-size: 13px; }
.geocode-suggestion-row { border-bottom: 1px solid #eee; }
.geocode-suggestion-row:last-child { border-bottom: 0; }
</style>

<style>
/* Ensure Google Places dropdown renders above the map and controls */
.pac-container { z-index: 2000 !important; }
</style>
