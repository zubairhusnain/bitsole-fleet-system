<template>
  <div v-if="show" class="driver-modal-overlay" @click.self="emit('close')">
    <div class="driver-modal" role="dialog" aria-modal="true" aria-labelledby="driverModalTitle">
      <div class="modal-header">
        <h5 id="driverModalTitle" class="mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-search text-muted"></i>
          <span>{{ driver?.name || 'Driver Details' }}</span>
        </h5>
        <button type="button" class="btn btn-light btn-sm" @click="emit('close')" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="text-muted small">Loading driver…</div>
        <div v-else>
          <div v-if="error" class="alert alert-danger py-2 px-3 small mb-3">{{ error }}</div>
          <div class="row g-3 g-xl-4">
            <!-- Left: Driver Information -->
            <div class="col-12 col-xl-4">
              <div class="card info-card">
                <div class="card-header">
                  <h6 class="mb-0">Driver Information</h6>
                </div>
                <div class="card-body">
                  <div class="d-flex align-items-center gap-3 mb-3">
                    <img v-if="avatarUrl" :src="avatarUrl" alt="Avatar" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;" />
                    <div>
                      <div class="fw-semibold driver-name d-flex align-items-center gap-2">
                        <span>{{ driver?.name || '—' }}</span>
                        <i class="bi bi-search xsmall text-muted"></i>
                        <span class="badge rounded-pill" :class="badgeClass()">{{ (status || 'Unknown') }}</span>
                      </div>
                      <div class="text-muted xsmall d-flex gap-3 flex-wrap">
                        <span>ID: {{ driverCode || '—' }}</span>
                        <span v-if="memberSince">Member Since: {{ memberSince }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="section">
                    <div class="section-title">Contact Detail</div>
                    <dl class="section-list">
                      <div class="row g-2 align-items-center">
                        <dt class="col-5 text-muted xsmall text-nowrap">Phone Number</dt>
                        <dd class="col-7 small mb-0">{{ attrs.phone || '—' }}</dd>
                      </div>
                      <div class="row g-2 align-items-center">
                        <dt class="col-5 text-muted xsmall text-nowrap">Email Address</dt>
                        <dd class="col-7 small mb-0">{{ attrs.email || '—' }}</dd>
                      </div>
                      <div class="row g-2 align-items-center">
                        <dt class="col-5 text-muted xsmall text-nowrap">Address</dt>
                        <dd class="col-7 small mb-0">{{ attrs.address || '—' }}</dd>
                      </div>
                    </dl>
                  </div>

                  <div class="section">
                    <div class="section-title">Licence Information</div>
                    <dl class="section-list">
                      <div class="row g-2 align-items-start">
                        <dt class="col-5 text-muted xsmall text-nowrap">ID Card</dt>
                        <dd class="col-7 small mb-0">{{ attrs.idCard || '—' }}</dd>
                      </div>
                      <div class="row g-2 align-items-start">
                        <dt class="col-5 text-muted xsmall text-nowrap">License Number</dt>
                        <dd class="col-7 small mb-0">{{ attrs.licence || attrs.license || '—' }}</dd>
                      </div>
                      <div class="row g-2 align-items-start">
                        <dt class="col-5 text-muted xsmall text-nowrap">Expiry Date</dt>
                        <dd class="col-7 small mb-0">{{ attrs.licenseExpiry || '—' }}</dd>
                      </div>
                    </dl>
                  </div>

                  <div class="section">
                    <div class="section-title">Vehicle Information</div>
                    <dl class="section-list">
                      <div class="row g-2 align-items-start">
                        <dt class="col-5 text-muted xsmall text-nowrap">Assigned Vehicle</dt>
                        <dd class="col-7 small mb-0">{{ assignedVehicleName || '—' }}</dd>
                      </div>
                      <div class="row g-2 align-items-start">
                        <dt class="col-5 text-muted xsmall text-nowrap">Plate Number</dt>
                        <dd class="col-7 small mb-0">{{ vehiclePlate || '—' }}</dd>
                      </div>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <!-- Middle: Driver Rating -->
            <div class="col-12 col-xl-4">
              <div class="card info-card">
                <div class="card-header">
                  <h6 class="mb-0">Driver Rating</h6>
                </div>
                <div class="card-body">
                  <p class="text-muted xsmall">This rating reflects your driving style and how you act behind the wheel.</p>
                  <ul class="list-unstyled rating-list">
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-hourglass-split"></i></span>
                      <div class="rating-text">
                        <div class="small">Idling Duration</div>
                        <div class="text-muted xsmall">{{ metrics.idlingHours }} hours</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-exclamation-triangle"></i></span>
                      <div class="rating-text">
                        <div class="small">Harsh Driving Alert</div>
                        <div class="text-muted xsmall">{{ metrics.harshDriveEvents }} Times</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-speedometer2"></i></span>
                      <div class="rating-text">
                        <div class="small">Over Speeding Alert</div>
                        <div class="text-muted xsmall">{{ metrics.overspeedEvents }} Times</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-sign-turn-right"></i></span>
                      <div class="rating-text">
                        <div class="small">Signal Breaking Alert</div>
                        <div class="text-muted xsmall">{{ metrics.signalBreakEvents }} Times</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-fuel-pump"></i></span>
                      <div class="rating-text">
                        <div class="small">Fuel Usage</div>
                        <div class="text-muted xsmall">{{ fmtLitresUpper(metrics.fuelUsageLiters) || '—' }}</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-clock-history"></i></span>
                      <div class="rating-text">
                        <div class="small">Driving Duration</div>
                        <div class="text-muted xsmall">{{ metrics.drivingDurationText || (metrics.drivingDurationHours + ' hours') }}</div>
                      </div>
                    </li>
                    <li class="rating-item">
                      <span class="icon-wrap"><i class="bi bi-activity"></i></span>
                      <div class="rating-text">
                        <div class="small">Track Distance</div>
                        <div class="text-muted xsmall">{{ fmtKmUpper(metrics.trackDistanceKm) || '—' }}</div>
                      </div>
                    </li>
                  </ul>

                  <div class="overall mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <div class="small fw-semibold">Overall Rating</div>
                      <div class="small fw-semibold">{{ overallRatingPercent }}</div>
                    </div>
                    <div class="progress" style="height: 8px;">
                      <div class="progress-bar bg-primary" role="progressbar" :style="{ width: overallRatingPercent }" :aria-valuenow="metrics.overallRating" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="text-muted xsmall mt-1">Here’s your overall rating showing how you handled the ride. Check it out above.</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right: Vehicle and Tracking Information -->
            <div class="col-12 col-xl-4">
              <div class="card info-card mb-3">
                <div class="vehicle-photo" v-if="vehicleImage">
                  <img :src="vehicleImage" alt="Vehicle" />
                </div>
                <div class="card-body">
                  <div class="section-title mb-2">Vehicle Information</div>
                  <dl class="section-list">
                    <div class="row g-3">
                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">Vehicle Type</dt>
                          <dd class="col-7 small mb-0">{{ vehicleType || '—' }}</dd>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">Manufacturer</dt>
                          <dd class="col-7 small mb-0">{{ vehicleManufacturer || '—' }}</dd>
                        </div>
                      </div>

                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">Model</dt>
                          <dd class="col-7 small mb-0">{{ vehicleModel || '—' }}</dd>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">Color</dt>
                          <dd class="col-7 small mb-0">{{ vehicleColor || '—' }}</dd>
                        </div>
                      </div>

                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">VIN Number</dt>
                          <dd class="col-7 small mb-0">{{ vehicleVin || '—' }}</dd>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="row g-2 align-items-start">
                          <dt class="col-5 text-muted xsmall text-nowrap">Plate Number</dt>
                          <dd class="col-7 small mb-0">{{ vehiclePlate || '—' }}</dd>
                        </div>
                      </div>
                    </div>
                  </dl>
                </div>
                <div class="card-footer vehicle-chips d-flex align-items-center gap-2 flex-wrap">
                  <span class="chip">{{ deviceUniqueId || '—' }}</span>
                  <span class="chip">{{ vehicleChipDate || '—' }}</span>
                  <span class="chip">{{ assignedVehicleName || '—' }}</span>
                </div>
              </div>

              <div class="card info-card">
                <div class="card-body">
                  <div class="section-title mb-2">Tracking Information</div>
                  <dl class="section-list">
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Ignition</dt>
                      <dd class="col-8 small mb-0">{{ tracking.ignition ?? '—' }}</dd>
                    </div>
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Speed</dt>
                      <dd class="col-8 small mb-0">{{ tracking.speedKmh != null ? `${Math.round(tracking.speedKmh)}km/h` : '—' }}</dd>
                    </div>
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Odometer</dt>
                      <dd class="col-8 small mb-0">{{ fmtKm(tracking.odometerKm) || '—' }}</dd>
                    </div>
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Fuel</dt>
                      <dd class="col-8 small mb-0">{{ fmtLitresUpper(tracking.fuelLiters) || '—' }}</dd>
                    </div>
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Location</dt>
                      <dd class="col-8 small mb-0">{{ tracking.location || '—' }}</dd>
                    </div>
                    <div class="row g-2 align-items-center">
                      <dt class="col-4 text-muted xsmall">Last Report</dt>
                      <dd class="col-8 small mb-0">{{ tracking.lastReport || '—' }}</dd>
                    </div>
                  </dl>
                  <div class="d-flex align-items-center gap-3 mt-2 xsmall">
                    <RouterLink to="/live-tracking" class="text-primary text-decoration-underline">Map Link</RouterLink>
                    <RouterLink to="/live-tracking" class="text-secondary text-decoration-underline">Live Location</RouterLink>
                    <RouterLink to="/reports" class="text-dark text-decoration-underline">Track History</RouterLink>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { RouterLink } from 'vue-router';

const props = defineProps({
  show: { type: Boolean, default: false },
  driverId: { type: [Number, String], default: null },
  useStatic: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const driver = ref(null);
const vehicle = ref(null);

// Static Figma-like dataset
const staticDriver = {
  name: 'Oliver Thompson',
  uniqueId: 'DRV-101623',
  status: 'Active',
  avatarImageUrl: 'https://i.pravatar.cc/112?img=12',
  attributes: {
    email: 'oliverthompson@gmail.com',
    phone: '(727) 540-0492',
    address: '4545 118th Ave N, Clearwater, Florida USA',
    idCard: 'USA-ID-A84917-TR55',
    licence: 'D248-1982-6794',
    licenseExpiry: 'July 15, 2035',
    idlingHours: 56,
    harshDriveEvents: 120,
    overspeedEvents: 400,
    signalBreakEvents: 20,
    fuelUsageLiters: 1000,
    drivingDurationHours: 124.93,
    drivingDurationText: '124 hours 56 minutes',
    trackDistanceKm: 12000,
    overallRating: 82.4,
    memberSince: 'July 2020',
    assignedVehicle: 'Toyota Camry SE',
  },
  deviceName: 'Lightning Racer',
  deviceStatus: 'online',
  deviceUniqueId: 'W125-9873-1402',
  deviceId: 1,
};

const staticVehicle = {
  name: 'Lightning Racer',
  uniqueId: 'W125-9873-1402',
  cardChipDate: 'Mar 19, 2026',
  model: 'Camry SE',
  plate: 'TXR-9283d',
  vin: 'WAUYGAF6CN174200',
  tcDevice: {
    attributes: {
      type: 'Sedan Car',
      manufacturer: 'Toyota',
      color: 'Midnight Black',
      plate: 'D24 - 6794',
      photos: ['https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?auto=format&fit=crop&w=1200&q=60'],
    },
    position: {
      speed: 0,
      attributes: { ignition: 'off' },
      address: 'PLUS KM 426',
    },
    lastUpdate: '14/08/25-15:29',
  },
  tracking: {
    ignition: 'off',
    speedKmh: 0,
    odometerKm: 211644,
    fuelLiters: 60,
    location: 'PLUS KM 426',
    lastReport: '14/08/25-15:29',
  },
};

function loadStatic() {
  loading.value = false;
  error.value = '';
  driver.value = staticDriver;
  vehicle.value = staticVehicle;
}

function parseAttrsMaybe(attr) {
  if (!attr) return {};
  if (typeof attr === 'string') {
    try { return JSON.parse(attr); } catch { return {}; }
  }
  return { ...attr };
}

async function fetchDetails(id) {
  loading.value = true;
  error.value = '';
  driver.value = null;
  vehicle.value = null;
  try {
    const { data } = await axios.get(`/web/drivers/${id}`);
    driver.value = data;
    const deviceId = data?.deviceId || data?.device_id;
    if (deviceId) {
      try {
        const vr = await axios.get(`/web/vehicles/${deviceId}`);
        vehicle.value = vr?.data ?? null;
      } catch (e) {
        // swallow vehicle fetch error; still show driver details
      }
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load driver';
  } finally {
    loading.value = false;
  }
}

watch(() => props.show, (s) => {
  if (!s) return;
  if (props.useStatic) loadStatic();
  else if (props.driverId) fetchDetails(props.driverId);
});
watch(() => props.driverId, (id) => {
  if (props.show && !props.useStatic && id) fetchDetails(id);
});

const attrs = computed(() => parseAttrsMaybe(driver.value?.attributes));
const avatarUrl = computed(() => driver.value?.avatarImageUrl || (attrs.value?.avatarImage ? `/storage/${attrs.value.avatarImage}` : 'https://i.pravatar.cc/112?img=12'));
const driverCode = computed(() => driver.value?.uniqueId || '');
const status = computed(() => props.useStatic ? 'Active' : (driver.value?.deviceStatus || driver.value?.status || 'unknown'));

const vattrs = computed(() => parseAttrsMaybe((vehicle.value?.tcDevice || vehicle.value?.tc_device || {}).attributes));
const assignedVehicleName = computed(() => vehicle.value?.name || driver.value?.deviceName || vattrs.value?.name || attrs.value?.assignedVehicle || '');
const deviceUniqueId = computed(() => vehicle.value?.uniqueId || vehicle.value?.uniqueid || driver.value?.deviceUniqueId || '');
const vehicleType = computed(() => vattrs.value?.type || vehicle.value?.type || '');
const vehicleManufacturer = computed(() => vattrs.value?.manufacturer || '');
const vehicleModel = computed(() => vehicle.value?.model || '');
const vehicleColor = computed(() => vattrs.value?.color || '');
const vehicleVin = computed(() => vattrs.value?.vin || vehicle.value?.vin || '');
const vehiclePlate = computed(() => vattrs.value?.plate || vattrs.value?.plateNumber || vehicle.value?.plate || '');

function getVehicleImage() {
  const tc = vehicle.value?.tcDevice || vehicle.value?.tc_device || {};
  const attrs = parseAttrsMaybe(tc.attributes);
  const photos = attrs?.photos;
  let candidate = '';
  if (Array.isArray(photos) && photos.length > 0) {
    candidate = photos.find(p => typeof p === 'string' && p.trim()) || '';
  } else {
    const alt = attrs?.photo || attrs?.image || (Array.isArray(attrs?.images) ? attrs.images[0] : '');
    candidate = typeof alt === 'string' ? alt : '';
  }
  if (!candidate) return '';
  const urlish = candidate.trim();
  return urlish.startsWith('http') ? urlish : `/storage/${urlish.replace(/^\/*/, '')}`;
}
const vehicleImage = computed(() => getVehicleImage());

const metrics = computed(() => {
  const a = attrs.value || {};
  return {
    idlingHours: a.idlingHours ?? 56,
    harshDriveEvents: a.harshDriveEvents ?? 120,
    overspeedEvents: a.overspeedEvents ?? 400,
    signalBreakEvents: a.signalBreakEvents ?? 20,
    fuelUsageLiters: a.fuelUsageLiters ?? 1000,
    drivingDurationHours: a.drivingDurationHours ?? 124.93,
    drivingDurationText: a.drivingDurationText ?? '124 hours 56 minutes',
    trackDistanceKm: a.trackDistanceKm ?? 12000,
    overallRating: a.overallRating ?? 82.4,
  };
});
const overallRatingPercent = computed(() => `${Math.min(100, Math.max(0, Number(metrics.value.overallRating || 0)))}%`);

const tracking = computed(() => {
  if (vehicle.value?.tracking) return vehicle.value.tracking;
  const tc = vehicle.value?.tcDevice || vehicle.value?.tc_device || vehicle.value || {};
  const pos = tc?.position || tc?.lastPosition || null;
  const attrsDev = parseAttrsMaybe(tc?.attributes);
  return {
    ignition: attrsDev?.ignition ?? (pos?.attributes?.ignition ?? null),
    speedKmh: pos?.speed ?? null,
    odometerKm: attrsDev?.odometer ?? null,
    fuelLiters: attrsDev?.fuel ?? null,
    location: pos?.address ?? attrsDev?.address ?? null,
    lastReport: tc?.lastUpdate || tc?.deviceTime || null,
  };
});

function badgeClass() {
  const s = String(status.value || '').toLowerCase();
  if (s === 'online' || s === 'active') return 'bg-success';
  if (s === 'offline') return 'bg-secondary';
  return 'bg-warning';
}

const memberSince = computed(() => attrs.value?.memberSince || '');
const fmtNumber = (n) => (n == null ? null : Number(n).toLocaleString());
const fmtLitres = (L) => (L == null ? null : `${fmtNumber(L)} litres`);
const fmtKm = (km) => (km == null ? null : `${fmtNumber(km)} km`);
const fmtLitresUpper = (L) => (L == null ? null : `${fmtNumber(L)} Litres`);
const fmtKmUpper = (km) => (km == null ? null : `${fmtNumber(km)} KM`);
const fmtDateHuman = (val) => {
  if (!val) return null;
  const tryDate = new Date(val);
  if (!Number.isNaN(tryDate.getTime())) {
    return tryDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }
  const m = String(val).match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})/);
  if (m) {
    const dd = Number(m[1]);
    const mm = Number(m[2]);
    const yy = Number(m[3]);
    const year = yy < 100 ? 2000 + yy : yy;
    const d2 = new Date(year, mm - 1, dd);
    if (!Number.isNaN(d2.getTime())) {
      return d2.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
  }
  return String(val);
};
const vehicleChipDate = computed(() => vehicle.value?.cardChipDate || fmtDateHuman(tracking.value?.lastReport) || '');
</script>

<style scoped>
.driver-modal-overlay { position: fixed; inset: 0; background: rgba(9, 12, 28, 0.4); backdrop-filter: blur(2px); z-index: 1050; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 24px; }
.driver-modal { background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,.15); width: 100%; max-width: 1100px; font-family: var(--font-sans); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e9ecef; }
.modal-body { padding: 16px; }
.info-card { border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
.info-card .card-header { background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
.section { margin-top: 10px; }
.section-title { font-weight: 600; font-size: .9rem; }
.section-list dt { margin: 0; }
.section-list dd { margin: 0; }
.xsmall { font-size: 0.75rem; }
.vehicle-photo { width: 100%; height: 180px; overflow: hidden; border-top-left-radius: 12px; border-top-right-radius: 12px; }
.vehicle-photo img { width: 100%; height: 100%; object-fit: cover; }
.rating-list { margin: 0; padding: 0; }
.rating-item { display: flex; align-items: center; gap: .75rem; padding: .4rem 0; border-bottom: 1px dashed #e9ecef; }
.rating-item:last-child { border-bottom: 0; }
.icon-wrap { width: 28px; height: 28px; border-radius: 50%; background: #eef2ff; color: #3b5bdb; display: inline-flex; align-items: center; justify-content: center; }
.progress { background-color: #eef2ff; }
.badge.bg-success { background-color: #28a745 !important; }
.badge.bg-secondary { background-color: #6c757d !important; }
.badge.bg-warning { background-color: #ffc107 !important; }

@media (max-width: 576px) {
  .driver-modal { border-radius: 0; max-width: none; height: 100vh; }
  .modal-body { padding: 12px; }
}

.modal-header h5 { font-size: 1.25rem; }
.driver-name { font-size: .98rem; }
.section-list .row { padding: 4px 0; }
.small, .xsmall { line-height: 1.25; }
.vehicle-photo { width: 100%; height: 210px; overflow: hidden; border-top-left-radius: 12px; border-top-right-radius: 12px; }
.rating-item { padding: .6rem 0; }
.rating-item .rating-text .small { font-weight: 500; }
.rating-item .text-muted { margin-top: 2px; }
.driver-modal .badge.rounded-pill { padding: 6px 10px; font-weight: 600; border-radius: 999px; }
.driver-modal .btn.btn-outline-primary,
.driver-modal .btn.btn-outline-secondary,
.driver-modal .btn.btn-outline-dark { border-radius: 999px; padding: 4px 12px; }
.section-list dt, .section-list dd { padding-top: 6px; padding-bottom: 6px; }
.section-list dt { white-space: nowrap; hyphens: none; overflow-wrap: normal; }
.section-list dd { overflow-wrap: break-word; word-break: normal; }
.section-list .row { align-items: flex-start !important; }
/* Figma-like chips below vehicle photo */
.vehicle-chips { border-top: 1px solid #dfe5f1; background: #f4f7fb; }
.chip { background: #eef3ff; color: #3b4a6b; border-radius: 999px; padding: 4px 12px; font-size: .78rem; }
.rating-item { padding: .6rem 0; }
.rating-item .rating-text .small { font-weight: 500; }
.rating-item .text-muted { margin-top: 2px; }
.driver-modal .badge.rounded-pill { padding: 6px 10px; font-weight: 600; border-radius: 999px; }
.driver-modal .btn.btn-outline-primary,
.driver-modal .btn.btn-outline-secondary,
.driver-modal .btn.btn-outline-dark { border-radius: 999px; padding: 4px 12px; }
.section-list dt, .section-list dd { padding-top: 6px; padding-bottom: 6px; }
.section-list dd { overflow-wrap: break-word; word-break: normal; }
.section-list .row { align-items: flex-start !important; }
</style>