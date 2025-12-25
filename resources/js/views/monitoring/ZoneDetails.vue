<template>
  <div class="container-fluid py-3">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">Monitoring</li>
        <li class="breadcrumb-item">
          <RouterLink to="/monitoring/zones">Zone Monitoring</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">{{ zoneName || 'Zone Details' }}</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <!-- Map Section -->
    <div class="card border rounded-4 shadow-0 bg-white mb-4 overflow-hidden">
      <div class="card-body p-0 position-relative" style="height: 500px;">
        <!-- Map Type Controls -->
        <div class="position-absolute top-0 start-0 m-3 z-3 d-flex gap-2" style="z-index: 1000;">
          <div class="btn-group shadow-sm" role="group">
            <button type="button" class="btn btn-sm" :class="mapType === 'osm' ? 'btn-dark' : 'btn-light'" @click="mapType = 'osm'">Map</button>
            <button type="button" class="btn btn-sm" :class="mapType === 'satellite' ? 'btn-dark' : 'btn-light'" @click="mapType = 'satellite'">Satellite</button>
          </div>
        </div>

        <div class="position-absolute bottom-0 start-0 m-3 z-3" style="z-index: 1000;">
             <!-- Draw Zone button from image, assuming generic action or just visual for now since this is details -->
             <button class="btn btn-info text-white btn-sm shadow-sm" disabled>
                <i class="bi bi-pencil-square me-1"></i> Draw Zone
            </button>
        </div>

        <l-map :key="mapKey" ref="mapRef" v-if="mapReady" :zoom="zoom" :center="center" :options="{ zoomControl: false }" @ready="onMapReady">
          <l-tile-layer :url="tileUrl" :attribution="tileAttribution" />
          <l-control-zoom position="bottomright" />

          <!-- Zone Polygon -->
          <l-polygon v-if="zonePolygon.length" :lat-lngs="zonePolygon" :color="'#1070e3'" :weight="2" :fillColor="'#1070e3'" :fillOpacity="0.25" />

          <!-- Zone Circle -->
          <l-circle v-if="zoneCircle" :lat-lng="zoneCircle.center" :radius="zoneCircle.radius" :color="'#3f8fd7'" :weight="1" :fillColor="'#3f8fd7'" :fillOpacity="0.25" />

          <!-- Zone Center Marker -->
          <l-marker v-if="hasZoneLocation" :lat-lng="center" :icon="zoneMarkerIcon">
            <l-popup>
              <div class="text-center">
                <strong>{{ zoneName }}</strong>
                <div class="small text-muted">Zone Center</div>
              </div>
            </l-popup>
          </l-marker>

          <!-- Vehicles -->
          <l-marker v-for="v in vehicles" :key="v.id" :lat-lng="[v.latitude, v.longitude]" :icon="getIcon(v)">
            <l-popup>
              <div class="text-center">
                  <strong>{{ v.name }}</strong><br>
                  <span class="badge my-1" :class="isIgnitionOn(v) ? 'bg-success' : 'bg-danger'">
                      {{ isIgnitionOn(v) ? 'Ignition On' : 'Ignition Off' }}
                  </span><br>
                  {{ v.speed }} km/h
              </div>
            </l-popup>
          </l-marker>
        </l-map>
      </div>
    </div>

    <!-- Data Table Section -->
    <div class="card border rounded-3 shadow-0 bg-white">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="py-2 ps-4">vehicle ID</th>
                <th class="py-2">Vehicle Type/Model</th>
                <th class="py-2">Location</th>
                <th class="py-2">Last Report</th>
                <th class="py-2 text-center">Engine</th>
                <th class="py-2 text-center">Speed</th>
                <th class="py-2 text-center">Status</th>
                <th class="py-2 text-end pe-4">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="paginatedVehicles.length === 0">
                <td colspan="8" class="text-center py-5 text-muted">No vehicles found in this zone.</td>
              </tr>
              <tr v-else v-for="vehicle in paginatedVehicles" :key="vehicle.id">
                <td class="ps-4 fw-medium">{{ vehicle.uniqueid || vehicle.vehicle_no }} - {{vehicle.name || ''}}</td>
                <td>
                    <div class="fw-bold">{{ vehicle.model || '—' }}</div>
                    <div class="small text-muted">{{ vehicle.type || '—' }}</div>
                </td>
                <td class="text-muted small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                    {{ vehicle.address || '—' }}
                </td>
                <td>{{ vehicle.last_update || '—' }}</td>
                <td class="text-center">
                    <span class="badge rounded-pill px-3 py-2" :class="isIgnitionOn(vehicle) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                        {{ isIgnitionOn(vehicle) ? 'Ignition On' : 'Ignition Off' }}
                    </span>
                </td>
                <td class="text-center">{{ vehicle.speed }} km/h</td>
                <td class="text-center">
                    <span class="badge rounded-pill px-3" :class="isOnline(vehicle) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                        {{ vehicle.status || 'Offline' }}
                    </span>
                </td>
                <td class="text-end pe-4">
                  <div class="btn-group btn-group-sm">
                    <RouterLink :to="`/vehicles/${vehicle.id}`" class="btn btn-outline-primary rounded-circle me-1" title="View" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                      <i class="bi bi-eye"></i>
                    </RouterLink>
                    <RouterLink :to="`/vehicles/${vehicle.id}/edit`" class="btn btn-outline-secondary rounded-circle" title="Edit" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-pencil-square"></i>
                    </RouterLink>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <div class="card-footer d-flex align-items-center py-2" v-if="vehicles.length > 0">
        <div class="text-muted small me-auto">
            Showing {{ startIndex + 1 }} to {{ Math.min(endIndex, vehicles.length) }} of {{ vehicles.length }} results
        </div>
        <nav aria-label="Page navigation" class="ms-auto">
            <ul class="pagination pagination-sm mb-0 pagination-app">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link" @click="prevPage">‹</button>
                </li>
                <li class="page-item" v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
                    <button class="page-link" @click="goToPage(page)">
                        {{ page }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link" @click="nextPage">›</button>
                </li>
            </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import "leaflet/dist/leaflet.css";
import { LMap, LTileLayer, LPolygon, LMarker, LPopup, LControlZoom, LCircle } from "@vue-leaflet/vue-leaflet";
import * as L from 'leaflet';
import UiAlert from '../../components/UiAlert.vue';

const route = useRoute();
const zoneId = route.params.zoneId;

// State
const zoneName = ref('');
const zoneData = ref({});
const vehicles = ref([]);
const zonePolygon = ref([]);
const zoneCircle = ref(null);
const center = ref([0, 0]);
const zoom = ref(13);
const mapReady = ref(false);
const error = ref('');
const loading = ref(true);
const mapType = ref('osm');
const mapKey = ref(0);
const mapRef = ref(null);

// Pagination
const currentPage = ref(1);
const pageSize = 10;

// Map Config
const tileUrl = computed(() => {
    return mapType.value === 'satellite'
        ? 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
        : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
});
const tileAttribution = computed(() => {
    return mapType.value === 'satellite'
        ? 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        : '&copy; OpenStreetMap contributors';
});

// Helpers
const getIcon = (v) => {
  return L.icon({
    iconUrl: '/images/markers/focus-marker.svg',
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -32]
  });
};

const isIgnitionOn = (v) => {
    if (v.ignition !== undefined) return v.ignition;
    if (v.attributes && v.attributes.ignition !== undefined) return v.attributes.ignition;
    return false;
};

const isOnline = (v) => {
    return v.status && v.status.toLowerCase() === 'online';
};

const hasZoneLocation = computed(() => {
    return (zonePolygon.value.length > 0 || zoneCircle.value) && center.value && (center.value[0] !== 0 || center.value[1] !== 0);
});

// Pagination Computed
const totalPages = computed(() => Math.ceil(vehicles.value.length / pageSize));
const startIndex = computed(() => (currentPage.value - 1) * pageSize);
const endIndex = computed(() => startIndex.value + pageSize);
const paginatedVehicles = computed(() => vehicles.value.slice(startIndex.value, endIndex.value));

const goToPage = (p) => currentPage.value = p;
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

const onMapReady = (map) => {
    // Invalidate size to ensure correct rendering
    if (map) {
        setTimeout(() => { map.invalidateSize(); }, 100);
    }

    setTimeout(() => {
        // Use the map instance passed or from ref
        const mapInstance = map || (mapRef.value && mapRef.value.leafletObject);
        if (!mapInstance) return;

        // Calculate bounds
        const bounds = L.latLngBounds([]);

        if (zonePolygon.value && zonePolygon.value.length) {
            // Extend with polygon bounds
            bounds.extend(L.latLngBounds(zonePolygon.value));
        }

        if (zoneCircle.value) {
            // Fit bounds to circle using Leaflet helper
            try {
                const cb = L.circle(zoneCircle.value.center, { radius: zoneCircle.value.radius }).getBounds();
                bounds.extend(cb);
            } catch {
                bounds.extend(zoneCircle.value.center);
            }
        }

        if (hasZoneLocation.value) {
             bounds.extend(center.value);
        }

        vehicles.value.forEach(v => {
            const lat = Number(v.latitude);
            const lon = Number(v.longitude);
            if (Number.isFinite(lat) && Number.isFinite(lon)) {
                bounds.extend([lat, lon]);
            }
        });

        if (bounds.isValid()) {
            mapInstance.fitBounds(bounds, { padding: [50, 50] });
        } else if (zoneCircle.value) {
            mapInstance.setView(zoneCircle.value.center, 13);
        } else if (hasZoneLocation.value) {
            mapInstance.setView(center.value, 13);
        }
    }, 200);
};

const zoneMarkerIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
});

const parseWKTArea = (area) => {
    try {
        const s = String(area || '').trim();
        if (!s) return null;

        // Helper: haversine distance (meters)
        const toRad = (deg) => deg * Math.PI / 180;
        const haversine = (lat1, lon1, lat2, lon2) => {
            const R = 6378137; // meters
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        };

        const isApproxCircle = (ptsLatLng) => {
            if (!Array.isArray(ptsLatLng) || ptsLatLng.length < 16) return null; // need enough points to test roundness
            // compute centroid
            let sumLat = 0, sumLng = 0;
            ptsLatLng.forEach(p => { sumLat += p[0]; sumLng += p[1]; });
            const centLat = sumLat / ptsLatLng.length;
            const centLng = sumLng / ptsLatLng.length;
            // distances
            const dists = ptsLatLng.map(p => haversine(centLat, centLng, p[0], p[1]));
            const mean = dists.reduce((a, b) => a + b, 0) / dists.length;
            const varSum = dists.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / dists.length;
            const std = Math.sqrt(varSum);
            const ratio = std / (mean || 1);
            if (ratio < 0.05) { // within 5% -> circle-like
                return { lat: centLat, lng: centLng, radius: Math.round(mean) };
            }
            return null;
        };

        const wktUpper = s.toUpperCase();

        if (wktUpper.startsWith('POLYGON')) {
            const m = s.match(/POLYGON\s*\(\(([\s\S]*?)\)\)/i);
            const body = m ? m[1] : '';
            const parts = body.split(',').map(p => p.trim()).filter(Boolean);
            const pts = [];
            for (const pair of parts) {
                const nums = pair.match(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/g) || [];
                if (nums.length >= 2) {
                    const lon = parseFloat(nums[0]);
                    const lat = parseFloat(nums[1]);
                    if (Number.isFinite(lon) && Number.isFinite(lat)) {
                        pts.push([lat, lon]);
                    }
                }
            }

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

            if (pts.length >= 3) {
                return { type: 'polygon', coordinates: pts };
            }
            return null;
        }

        if (wktUpper.startsWith('MULTIPOLYGON')) {
            const m = s.match(/MULTIPOLYGON\s*\(\s*\(\(([\s\S]*?)\)\)/i);
            const body = m ? m[1] : '';
            const parts = body.split(',').map(p => p.trim()).filter(Boolean);
            const pts = [];
            for (const pair of parts) {
                const nums = pair.match(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/g) || [];
                if (nums.length >= 2) {
                    const lon = parseFloat(nums[0]);
                    const lat = parseFloat(nums[1]);
                    if (Number.isFinite(lon) && Number.isFinite(lat)) {
                        pts.push([lat, lon]);
                    }
                }
            }
            if (pts.length >= 3) {
                return { type: 'polygon', coordinates: pts };
            }
            return null;
        }

        if (wktUpper.startsWith('CIRCLE')) {
            const m = s.match(/CIRCLE\s*\(([-+]?\d+(?:\.\d+)?)\s+([-+]?\d+(?:\.\d+)?),\s*([-+]?\d+(?:\.\d+)?)\)/i);
            if (m) {
                const lat = parseFloat(m[1]);
                const lon = parseFloat(m[2]);
                const radius = parseFloat(m[3]);
                if (Number.isFinite(lat) && Number.isFinite(lon) && Number.isFinite(radius)) {
                    return { type: 'circle', lat, lng: lon, radius, coordinates: [[lat, lon]] };
                }
            }
        }

        // Traccar may use LINESTRING for routes
        if (wktUpper.startsWith('LINESTRING') || wktUpper.startsWith('ROUTE')) {
            const m = s.match(/(?:LINESTRING|ROUTE)\s*\(([\s\S]*?)\)/i);
            const body = m ? m[1] : '';
            const parts = body.split(',').map(p => p.trim()).filter(Boolean);
            const pts = [];
            for (const pair of parts) {
                const nums = pair.match(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/g) || [];
                if (nums.length >= 2) {
                    const lon = parseFloat(nums[0]);
                    const lat = parseFloat(nums[1]);
                    if (Number.isFinite(lon) && Number.isFinite(lat)) {
                        pts.push([lat, lon]);
                    }
                }
            }
            if (pts.length >= 2) {
                return { type: 'route', coordinates: pts };
            }
            return null;
        }

    } catch (e) {
    }
    return null;
};

onMounted(async () => {
  try {
    error.value = '';
    loading.value = true;
    const mon = await axios.get(`/web/monitoring/zones/${zoneId}`);
    const md = mon?.data || {};
    const gf = md?.geofence || md;

    // Normalize attributes
    let attrs = gf?.attributes || {};
    if (typeof attrs === 'string') {
      try {
        const parsed = JSON.parse(attrs);
        if (parsed && typeof parsed === 'object') attrs = parsed;
      } catch {}
    }

    zoneName.value = String(gf?.name || '').trim();
    zoneData.value = {
      id: gf?.id || zoneId,
      name: zoneName.value,
      description: gf?.description || null,
      area: gf?.area || null,
      attributes: attrs || null,
    };

    // Reset shapes
    zonePolygon.value = [];
    zoneCircle.value = null;

    // Parse WKT area first
    const parsed = parseWKTArea(gf?.area || '');
    if (parsed && parsed.type === 'circle' && parsed.lat != null && parsed.lng != null) {
      const radius = Number.isFinite(parsed.radius)
        ? parsed.radius
        : (typeof attrs?.radius === 'number' ? attrs.radius : 1000);
      zoneCircle.value = { center: [parsed.lat, parsed.lng], radius };
      center.value = [parsed.lat, parsed.lng];
    } else if (parsed && (parsed.type === 'polygon' || parsed.type === 'route') && Array.isArray(parsed.coordinates) && parsed.coordinates.length >= (parsed.type === 'route' ? 2 : 3)) {
      zonePolygon.value = parsed.coordinates;
      const lats = parsed.coordinates.map(p => p[0]);
      const lngs = parsed.coordinates.map(p => p[1]);
      center.value = [(Math.min(...lats) + Math.max(...lats)) / 2, (Math.min(...lngs) + Math.max(...lngs)) / 2];
    } else {
      // Fallback: attributes circle
      const lat = Number(attrs?.lat);
      const lng = Number(attrs?.long || attrs?.lng);
      const rad = Number(attrs?.radius);
      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        zoneCircle.value = {
          center: [lat, lng],
          radius: Number.isFinite(rad) ? rad : 1000
        };
        center.value = [lat, lng];
      }
    }

    const vlist = Array.isArray(md?.vehicles) ? md.vehicles : [];
    vehicles.value = vlist.map(v => {
      const lat = Number(v.lat ?? v.latitude);
      const lon = Number(v.lng ?? v.longitude);
      return {
        ...v,
        latitude: Number.isFinite(lat) ? lat : undefined,
        longitude: Number.isFinite(lon) ? lon : undefined,
      };
    });

    mapReady.value = true;
    await nextTick();
    try {
      const m = mapRef.value && mapRef.value.leafletObject;
      if (m) { onMapReady(m); }
    } catch {}

  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load zone details';
  } finally {
    loading.value = false;
  }
});
</script>
