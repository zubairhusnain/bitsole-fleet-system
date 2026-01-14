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
                    <button @click="showDetails(vehicle)" class="btn btn-outline-primary rounded-circle me-1" title="View" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                      <i class="bi bi-eye"></i>
                    </button>
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
    <!-- Vehicle Details Modal (Custom Implementation) -->
    <div v-if="showDetailsModal" class="driver-modal-overlay" @click.self="closeModal">
      <div class="driver-modal overflow-hidden position-relative" role="dialog" aria-modal="true" style="max-width: 800px;">
        <div class="modal-body p-0" v-if="selectedVehicle">

            <!-- Close Button -->
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3 z-3" @click="closeModal" aria-label="Close"></button>

            <!-- Header Image -->
            <div class="p-3">
                <div class="position-relative bg-light rounded-4 overflow-hidden" style="height: 250px;">
                    <img v-if="selectedVehicle.photos && selectedVehicle.photos.length"
                         :src="photoUrl(selectedVehicle.photos[0])"
                         class="w-100 h-100 object-fit-cover"
                         alt="Vehicle Image"
                         onerror="this.src='https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image'">
                    <img v-else src="https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image"
                         class="w-100 h-100 object-fit-cover"
                         alt="Vehicle Placeholder"
                         onerror="this.style.display='none'">
                </div>
            </div>

            <div class="px-4 pb-4">
                <!-- Title -->
                <h5 class="fw-bold mb-4 text-dark">Vehicle Status Information</h5>

                <!-- Grid -->
                <div class="row g-4">
                    <!-- Row 1 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Vehicle ID</div>
                        <div class="text-muted">{{ selectedVehicle.vehicle_no || selectedVehicle.name }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Device ID</div>
                        <div class="text-muted">{{ selectedVehicle.uniqueid }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Vehicle Type</div>
                        <div class="text-muted">{{ selectedVehicle.type }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Model</div>
                        <div class="text-muted">{{ selectedVehicle.model }}</div>
                    </div>

                    <!-- Row 2 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Ignition</div>
                        <div class="text-muted">{{ selectedVehicle.ignition ? 'On' : 'Off' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Ignition On</div>
                        <div class="text-muted">{{ selectedVehicle.last_ignition_on || 'N/A' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Ignition Off</div>
                        <div class="text-muted">{{ selectedVehicle.last_ignition_off || 'N/A' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Speed</div>
                        <div class="text-muted">{{ selectedVehicle.speed }} km/h</div>
                    </div>

                    <!-- Row 3 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Zone Name</div>
                        <div class="text-muted">{{ zoneName }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Report</div>
                        <div class="text-muted">{{ selectedVehicle.last_update }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Status</div>
                        <div :class="{
                            'text-success fw-bold': selectedVehicle.status === 'Online',
                            'text-danger fw-bold': selectedVehicle.status === 'Offline',
                            'text-muted': !['Online', 'Offline'].includes(selectedVehicle.status)
                        }">
                            {{ selectedVehicle.status || 'N/A' }}
                        </div>
                    </div>

                    <!-- Row 4 -->
                    <div class="col-12">
                        <div class="fw-bold mb-1 text-dark">Location</div>
                        <a :href="`https://maps.google.com/?q=${selectedVehicle.lat},${selectedVehicle.lng}`" target="_blank" class="text-decoration-none text-info">
                            {{ selectedVehicle.address || `${selectedVehicle.lat}, ${selectedVehicle.lng}` }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-body p-5 text-center" v-else>
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
      </div>
    </div>

    <!-- Status Popup -->
    <div v-if="showAlertStatusPopup" class="driver-modal-overlay" @click.self="closeAlertStatusPopup">
      <div class="driver-modal overflow-hidden" role="dialog" aria-modal="true" style="max-width: 400px;">
        <div class="modal-header">
          <h5 class="fw-bold mb-0">Change Status</h5>
          <button type="button" class="btn-close" @click="closeAlertStatusPopup"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Alerts</label>
            <select v-model="selectedAlertStatus" class="form-select">
              <option value="" disabled>--Select Alerts Status--</option>
              <option value="enabled">Enable Alerts</option>
              <option value="disabled">Disable Alerts</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Maintenance</label>
            <select v-model="selectedMaintenanceStatus" class="form-select">
              <option value="" disabled>--Select Maintenance Status--</option>
              <option value="enabled">Enable Maintenance</option>
              <option value="disabled">Disable Maintenance</option>
            </select>
          </div>
          <button class="btn btn-primary w-100" @click="updateAlertStatus" :disabled="submittingStatus">
            {{ submittingStatus ? 'Updating...' : 'Update Status' }}
          </button>
        </div>
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
import { formatDateTime } from '../../utils/datetime';

const route = useRoute();
const zoneId = route.params.zoneId;

// State
const zoneName = ref('');
const zoneData = ref({});
const vehicles = ref([]);
const zonePolygon = ref([]);
const zoneCircle = ref(null);
const center = ref([0, 0]);
const zoom = ref(5);
const mapReady = ref(false);
const error = ref('');
const loading = ref(true);
const mapType = ref('osm');
const mapKey = ref(0);
const mapRef = ref(null);

// Modal State
const selectedVehicle = ref(null);
const showDetailsModal = ref(false);
const showAlertStatusPopup = ref(false);
const selectedAlertStatus = ref('');
const selectedMaintenanceStatus = ref('');
const alertStatusTargetId = ref(null);
const submittingStatus = ref(false);

// Helper for date formatting (timezone-aware)
const formatDate = (dateStr) => {
    if (!dateStr || dateStr === 'N/A') return 'N/A';
    return formatDateTime(dateStr);
};

const parseAttrs = (a) => {
    if (!a) return {};
    if (typeof a === 'object') return a;
    try { return JSON.parse(a); } catch { return {}; }
};

const photoUrl = (p) => {
    if (!p && p !== 0) return '';
    const raw = String(p).trim();
    if (!raw) return '';
    if (raw.startsWith('http') || raw.startsWith('data:')) return raw;
    if (raw.startsWith('/')) return raw; // already absolute from root
    if (raw.startsWith('storage/')) return `/${raw}`;
    if (raw.startsWith('public/')) return `/${raw.replace(/^public\//, 'storage/')}`;
    // default: treat as a public disk path under storage
    return `/storage/${raw.replace(/^\/*/, '')}`;
};

const showDetails = async (vehicle) => {
    if (!vehicle || !vehicle.id) return;

    // Set initial state
    selectedVehicle.value = null;
    showDetailsModal.value = true;

    try {
        const { data } = await axios.get(`/web/monitoring/vehicles/${vehicle.id}`);

        const tc = data.tc_device || data.tcDevice || {};
        const pos = tc.position || {};
        const attrs = parseAttrs(pos.attributes);
        const deviceAttrs = parseAttrs(tc.attributes);
        const allAttrs = { ...deviceAttrs, ...attrs };

        // Helper to extract photos (logic from Detail.vue)
        const getPhotos = () => {
            const out = [];
            const toPath = (it) => {
                if (!it && it !== 0) return '';
                if (Array.isArray(it)) return it.map(toPath).filter(Boolean);
                if (typeof it === 'string') {
                    const s = it.trim();
                    if (!s) return '';
                    if ((s.startsWith('[') && s.endsWith(']')) || (s.startsWith('{') && s.endsWith('}'))) {
                        try {
                            const parsed = JSON.parse(s);
                            return toPath(parsed);
                        } catch { }
                    }
                    return s;
                }
                if (typeof it === 'number') return String(it);
                if (typeof it === 'object') {
                    const cand = it.url ?? it.path ?? it.src ?? it.image ?? it.photo;
                    return typeof cand === 'string' ? cand.trim() : '';
                }
                return '';
            };

            const pick = (keys) => {
                for (const k of keys) {
                    if (allAttrs[k] != null && allAttrs[k] !== '') return allAttrs[k];
                }
                return null;
            };

            const arrLike = pick(['photos', 'images']);
            const arrResolved = toPath(arrLike);
            if (Array.isArray(arrResolved)) out.push(...arrResolved);
            else if (typeof arrResolved === 'string' && arrResolved) out.push(arrResolved);

            const single = toPath(pick(['photo', 'image', 'vehiclePhoto', 'vehicleImage']));
            if (Array.isArray(single)) out.push(...single);
            else if (typeof single === 'string' && single) out.push(single);

            return Array.from(new Set(out.filter(v => typeof v === 'string' && v.trim() !== '')));
        };

        selectedVehicle.value = {
            id: data.device_id || data.id,
            // Use vehicle_id_attr if available, otherwise fallback to name, then 'Unknown'
            vehicle_no: data.vehicle_no || data.vehicle_id_attr || data.name,
            name: data.vehicle_id_attr || tc.name || data.name || 'Unknown',
            uniqueid: tc.uniqueid || data.uniqueid || 'N/A',
            plate: data.plate || tc.plate || 'N/A',
            lat: parseFloat(pos.latitude) || 0,
            lng: parseFloat(pos.longitude) || 0,
            speed: pos.speed != null ? Number((parseFloat(pos.speed) * 1.852).toFixed(1)) : 0,
            driver_name: data.driver_name || tc.driverUniqueId || 'N/A',
            last_update: formatDate(pos.servertime || pos.fixtime),
            fuel: attrs.fuel || 0,
            odometer: attrs.odometer || 0,
            ignition: attrs.ignition || false,
            group: data.group || 'Default Group',
            model: tc.model || data.model || 'Unknown',
            type: deviceAttrs.type || 'Unknown',
            owner: data.manager ? data.manager.name : (data.group || 'N/A'),
            maintenance_count: data.maintenance_count || 0,
            alert_count: data.alert_count || 0,
            blocked: data.blocked,
            last_ignition_on: formatDate(data.last_ignition_on),
            last_ignition_off: formatDate(data.last_ignition_off),
            address: pos.address,
            // Format maintenance string
            maintenance: data.maintenance_count > 0 ? `${data.maintenance_count} Due` : 'N/A',
            alert_status: deviceAttrs.alert_status || '',
            maintenance_status: deviceAttrs.maintenance_status || '',
            status: (data.status && data.status.toLowerCase() === 'online') ||
                   (tc.status && tc.status.toLowerCase() === 'online') ||
                   (vehicle.status && vehicle.status.toLowerCase() === 'online') ? 'Online' : 'Offline',
            photos: getPhotos()
        };
    } catch (e) {
        console.error("Failed to fetch vehicle details", e);
        // Fallback to passed vehicle object if API fails
        selectedVehicle.value = {
            ...vehicle,
            last_update: formatDate(vehicle.last_update),
            last_ignition_on: 'N/A',
            last_ignition_off: 'N/A',
            status: vehicle.status && vehicle.status.toLowerCase() === 'online' ? 'Online' : 'Offline'
        };
    }
};

const closeModal = () => {
    showDetailsModal.value = false;
};

const openAlertStatusPopup = (vehicle) => {
    if (!vehicle) return;
    alertStatusTargetId.value = vehicle.id;
    selectedAlertStatus.value = vehicle.alert_status || '';
    selectedMaintenanceStatus.value = vehicle.maintenance_status || '';
    showAlertStatusPopup.value = true;
};

const closeAlertStatusPopup = () => {
    showAlertStatusPopup.value = false;
};

const updateAlertStatus = async () => {
    if (!alertStatusTargetId.value) return;
    try {
        submittingStatus.value = true;
        await axios.post(`/web/monitoring/vehicles/${alertStatusTargetId.value}/alert-status`, {
            alert_status: selectedAlertStatus.value || null,
            maintenance_status: selectedMaintenanceStatus.value || null
        });
        closeAlertStatusPopup();
    } catch (e) {
        console.error("Failed to update alert status", e);
        alert("Failed to update alert status. Please try again.");
    } finally {
        submittingStatus.value = false;
    }
};

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
            // Manual bounds calculation to avoid L.circle dependency issues
            try {
                const { center, radius } = zoneCircle.value;
                const [lat, lng] = center;
                // Meters to degrees approximation
                const latDeg = radius / 111320;
                const lngDeg = radius / (40075000 * Math.cos(lat * Math.PI / 180) / 360);

                bounds.extend([lat - latDeg, lng - lngDeg]);
                bounds.extend([lat + latDeg, lng + lngDeg]);
            } catch {
                bounds.extend(zoneCircle.value.center);
            }
        }

        if (hasZoneLocation.value && !bounds.isValid()) {
             bounds.extend(center.value);
        }

        // Do not include vehicles in initial bounds to avoid jump away from zone

        if (bounds.isValid()) {
            const sw = bounds.getSouthWest();
            const ne = bounds.getNorthEast();
            // If bounds are effectively a point
            if (Math.abs(sw.lat - ne.lat) < 0.0001 && Math.abs(sw.lng - ne.lng) < 0.0001) {
                mapInstance.setView(sw, 15);
            } else {
                mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            }
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
      const rVal = parsed.radius ?? attrs?.radius;
      const rNum = Number(rVal);
      const radius = Number.isFinite(rNum) && rNum > 0 ? rNum : 1000;

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
      const rNum = Number(attrs?.radius);
      const rad = Number.isFinite(rNum) && rNum > 0 ? rNum : 1000;

      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        zoneCircle.value = {
          center: [lat, lng],
          radius: rad
        };
        center.value = [lat, lng];
      }
    }

    const vlist = Array.isArray(md?.vehicles) ? md.vehicles : [];
    vehicles.value = vlist.map(v => {
      const lat = Number(v.lat ?? v.latitude);
      const lon = Number(v.lng ?? v.longitude);
      const valid =
        Number.isFinite(lat) &&
        Number.isFinite(lon) &&
        Math.abs(lat) <= 90 &&
        Math.abs(lon) <= 180 &&
        !(lat === 0 && lon === 0);
      return {
        ...v,
        latitude: valid ? lat : undefined,
        longitude: valid ? lon : undefined,
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

<style scoped>
/* Custom Modal Styles from DriverDetailModal.vue */
.driver-modal-overlay { position: fixed; inset: 0; background: rgba(9, 12, 28, 0.4); backdrop-filter: blur(2px); z-index: 1050; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 24px; }
.driver-modal { background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,.15); width: 100%; max-width: 600px; font-family: var(--font-sans); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e9ecef; }
.modal-body { padding: 16px; }

@media (max-width: 576px) {
  .driver-modal { border-radius: 0; max-width: none; height: 100vh; }
  .modal-body { padding: 12px; }
}

.modal-header h5 { font-size: 1.25rem; }
</style>
