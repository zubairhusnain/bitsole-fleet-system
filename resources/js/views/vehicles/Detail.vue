<template>
    <div>
        <div class="app-content-header mb-2">
            <ol class="breadcrumb mb-0 small text-muted">
                <li class="breadcrumb-item">
                    <RouterLink to="/dashboard">Dashboard</RouterLink>
                </li>
                <li class="breadcrumb-item">
                    <RouterLink to="/vehicles">Vehicle Management</RouterLink>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Vehicle Detail</li>
            </ol>
        </div>

        <div class="row mb-3">
            <div class="col-auto text-end ms-auto" v-if="detailPayload">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge" :class="statusBadgeClass">{{ statusLabel }}</span>
                    <span class="fw-semibold">{{ deviceName || '-' }}</span>
                </div>
            </div>
        </div>

        <!-- Add error banner -->
        <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
        <!-- Top widgets under map -->
        <div class="row g-3 mb-3 mt-3">
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Stopwatch -->
                                    <circle cx="24" cy="26" r="12" fill="none" stroke="#fa5252" stroke-width="3" />
                                    <rect x="20" y="8" width="8" height="4" rx="2" fill="#fa5252" />
                                    <path d="M24 26 L30 20" stroke="#ae3ec9" stroke-width="3" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Time</div>
                                <div class="fw-semibold">{{ lastUpdateDisplay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Ignition: power symbol -->
                                    <circle cx="24" cy="24" r="14" fill="none" stroke="#fd7e14" stroke-width="4" />
                                    <path d="M24 10v10" stroke="#fd7e14" stroke-width="4" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Ignition</div>
                                <div class="fw-semibold">{{ ignitionLabel }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Speedometer: multi-color arc + needle -->
                                    <path d="M10 28a14 14 0 0 1 28 0" fill="none" stroke="#69dbff" stroke-width="4"
                                        stroke-linecap="round" />
                                    <path d="M10 28a14 14 0 0 1 12-12" fill="none" stroke="#ffa94d" stroke-width="4"
                                        stroke-linecap="round" />
                                    <path d="M22 16a14 14 0 0 1 16 12" fill="none" stroke="#74c0fc" stroke-width="4"
                                        stroke-linecap="round" />
                                    <circle cx="24" cy="28" r="3" fill="#343a40" />
                                    <path d="M24 28 L34 18" stroke="#e03131" stroke-width="3" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Speed</div>
                                <div class="fw-semibold">{{ speedDisplay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Odometer: two pins -->
                                    <path d="M16 22c0-3.314 2.686-6 6-6s6 2.686 6 6c0 4.5-6 12-6 12s-6-7.5-6-12z"
                                        fill="#1c7ed6" />
                                    <circle cx="22" cy="22" r="2" fill="#fff" />
                                    <path d="M32 16c0-2.761 2.239-5 5-5s5 2.239 5 5c0 3.75-5 10-5 10s-5-6.25-5-10z"
                                        fill="#f76707" />
                                    <circle cx="37" cy="16" r="1.8" fill="#fff" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Odometer</div>
                                <div class="fw-semibold">{{ odometerDisplay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Thermometer + sun -->
                                    <rect x="18" y="14" width="8" height="18" rx="4" fill="#dee2e6" stroke="#ff6b6b"
                                        stroke-width="2" />
                                    <circle cx="22" cy="34" r="6" fill="#ff6b6b" />
                                    <circle cx="36" cy="14" r="4" fill="#fab005" />
                                    <path d="M36 6v4M36 18v4M28 14h4M40 14h4M30 10l3 3M39 21l3 3M33 21l-3 3M39 11l3-3"
                                        stroke="#fab005" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Device Temperature</div>
                                <div class="fw-semibold">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <div class="card panel rounded-4 shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="widget-icon">
                            <div class="icon-bubble">
                                <svg viewBox="0 0 48 48" class="icon-svg" aria-hidden="true">
                                    <!-- Battery -->
                                    <rect x="8" y="18" width="28" height="16" rx="3" fill="none" :stroke="batteryIconStroke"
                                        stroke-width="3" />
                                    <rect x="36" y="22" width="4" height="8" rx="1" :fill="batteryIconStroke" />
                                    <rect x="11" y="21" :width="batteryFillWidth" height="10" rx="2" :fill="batteryIconFill" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Device Battery</div>
                                <div class="fw-semibold">{{ batteryDisplay }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Static top Leaflet map -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body p-0">
                        <div ref="mapContainer" style="height: calc(60vh - 16px); min-height: 320px;">
                            <LMap v-if="mapReady" :zoom="zoom" :center="mapCenter" style="height: 100%; width: 100%;">
                                <LTileLayer :url="tileUrl" :attribution="tileAttribution" />
                                <LMarker :lat-lng="currentLatLng || mapCenter" ref="markerRef" />

                            </LMap>
                            <div v-else class="placeholder-glow" style="height: 100%">
                                <span class="placeholder col-12" style="height: 100%"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="true" class="row g-4">
            <!-- Left column: Vehicle hero + info + tracking -->
            <div class="col-12 col-lg-7">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <div class="vehicle-hero mb-3 rounded-3 overflow-hidden">
                            <img v-if="photos && photos.length" :src="photoUrl(photos[0])" alt="Vehicle image" class="w-100" />
                            <img v-else src="https://images.unsplash.com/photo-1549924231-f129b911e442?q=80&w=1600&auto=format&fit=crop" alt="Vehicle image" class="w-100" />
                        </div>
                        <h6 class="mb-3 panel-header">Vehicle Information</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">Vehicle Type</div>
                                <div class="fw-semibold">{{ type || '-' }}</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">Manufacturer</div>
                                <div class="fw-semibold">{{ manufacturer || '-' }}</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">Model</div>
                                <div class="fw-semibold">{{ model || '-' }}</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">Color</div>
                                <div class="fw-semibold">{{ color || '-' }}</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">VIN Number</div>
                                <div class="fw-semibold">{{ vin || '-' }}</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-muted small">Plate Number</div>
                                <div class="fw-semibold">{{ plate || '-' }}</div>
                            </div>
                        </div>
                        <div class="mt-3 small d-flex flex-wrap gap-4 border-top pt-2">
                            <span class="fw-semibold text-primary">{{ uniqueId || '-' }}</span>
                            <span class="fw-semibold">Lightning Racer</span>
                            <span class="fw-semibold">3N1AB7AP4FY123456</span>
                        </div>
                    </div>
                </div>
                <div class="card panel rounded-4 shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-3 panel-header">Tracking Information</h6>
                        <div class="row g-3">

                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Total Distance</div>
                                <div class="fw-semibold">{{ totalDistanceDisplay }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Fuel</div>
                                <div class="fw-semibold">{{ fuelAverage ? (fuelAverage + ' L/100km') : '-' }}</div>
                            </div>
                             <div class="col-6 col-md-3">
                                <div class="text-muted small">Total Hours</div>
                                <div class="fw-semibold">{{ totalHoursDisplay }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Map Link</div>
                                <a v-if="liveLocationUrl" :href="liveLocationUrl" target="_blank" rel="noopener" class="fw-semibold text-primary text-decoration-underline">Live Location</a>
                                <span v-else class="fw-semibold text-muted">Live Location</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle column: Driver Information -->
            <div class="col-12 col-lg-3">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <template v-if="driver">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img v-if="driverAvatarUrl" :src="driverAvatarUrl" alt="Driver Avatar" class="rounded-circle"
                                    style="width:56px;height:56px;object-fit:cover" />
                                <div v-else class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                    style="width:56px;height:56px;">
                                    <i class="bi bi-person text-muted"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ driverName || '-' }}</div>
                                    <small class="text-muted" v-if="memberSinceDisplay">Member Since: {{ memberSinceDisplay }}</small>
                                </div>
                                <span class="badge" :class="driverStatusClass">{{ driverStatusLabel }}</span>
                            </div>
                            <h6 class="mb-2">Contact Detail</h6>
                            <dl class="row mb-3">
                                <dt class="col-5 text-muted small">Phone Number</dt>
                                <dd class="col-7 small mb-2">{{ driverPhone || '-' }}</dd>
                                <dt class="col-5 text-muted small">Email Address</dt>
                                <dd class="col-7 small mb-2">{{ driverEmail || '-' }}</dd>
                                <dt class="col-5 text-muted small">Address</dt>
                                <dd class="col-7 small mb-2">{{ driverAddress || '-' }}</dd>
                            </dl>
                            <h6 class="mb-2">Licence Information</h6>
                            <dl class="row mb-3">
                                <dt class="col-5 text-muted small">ID</dt>
                                <dd class="col-7 small mb-2">{{ driverIdCard || '-' }}</dd>
                                <dt class="col-5 text-muted small">License Number</dt>
                                <dd class="col-7 small mb-2">{{ driverLicenseNumber || '-' }}</dd>
                                <dt class="col-5 text-muted small">Expiry Date</dt>
                                <dd class="col-7 small mb-2">{{ driverLicenseExpiry || '-' }}</dd>
                            </dl>
                            <h6 class="mb-2">Vehicle Information</h6>
                            <div class="mb-1 small text-muted">Assigned Vehicle</div>
                            <div class="fw-semibold">{{ deviceName || '-' }}</div>
                            <div class="mb-1 small text-muted mt-2">Plate Number</div>
                            <div class="fw-semibold">{{ plate || '-' }}</div>
                        </template>
                        <template v-else>
                            <div class="text-muted small">No driver is currently assigned to this vehicle.</div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right column: Vehicle Rating -->
            <div class="col-12 col-lg-2">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-2">Vehicle Rating</h6>
                        <div class="small text-muted mb-3">This rating reflects your vehicle during driving feel.</div>
                        <div class="metric-item mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-fuel-pump me-2 metric-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Fuel Average</div>
                                    <div class="small text-muted">8 km/h</div>
                                </div>
                            </div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-primary" style="width: 74%"></div>
                            </div>
                        </div>
                        <div class="metric-item mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-thermometer-high me-2 metric-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Heat up Gage</div>
                                    <div class="small text-muted">74%</div>
                                </div>
                            </div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-warning" style="width: 74%"></div>
                            </div>
                        </div>
                        <div class="metric-item mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-tools me-2 metric-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Well Service</div>
                                    <div class="small text-muted">100%</div>
                                </div>
                            </div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="metric-item mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-shield-check me-2 metric-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Tyre Condition</div>
                                    <div class="small text-muted">97%</div>
                                </div>
                            </div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-info" style="width: 97%"></div>
                            </div>
                        </div>
                        <div class="metric-item mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle me-2 metric-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">Brake failure</div>
                                    <div class="small text-muted">20 Times</div>
                                </div>
                            </div>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-danger" style="width: 60%"></div>
                            </div>
                        </div>
                        <h6 class="mt-3 mb-1">Overall Rating</h6>
                        <div class="small text-muted mb-2">Here's your overall rating showing how vehicle responded.
                            Check it out above!</div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" style="width: 82.4%"></div>
                        </div>
                        <div class="text-end small fw-semibold mt-1 text-muted">82.4%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Original dynamic page (hidden) -->
        <div class="row g-3" v-else>
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Map & Waypoints</h6>
                        <small class="text-muted">Device ID: {{ deviceId }}</small>
                    </div>
                    <div class="card-body p-0">
                        <div ref="mapContainer" style="height: calc(60vh - 16px); min-height: 320px;">
                            <LMap v-if="mapReady" :zoom="zoom" :center="mapCenter" style="height: 100%; width: 100%;">
                                <LTileLayer :url="tileUrl" :attribution="tileAttribution" />
                                <!-- Waypoints polyline -->
                                <LPolyline v-if="polyline && polyline.length" :lat-lngs="polyline"
                                    :color="polylineColor" :weight="4" :opacity="0.8" />
                                <!-- Current device marker -->
                                <LMarker v-if="currentLatLng" :lat-lng="currentLatLng"></LMarker>
                            </LMap>
                            <div v-else class="placeholder-glow" style="height: 100%">
                                <span class="placeholder col-12" style="height: 100%"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widgets -->
            <div class="col-12">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted">Unique ID</div>
                                        <div class="fw-semibold">{{ uniqueId || '-' }}</div>
                                    </div>
                                    <i class="bi bi-hash fs-4 text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted">Ignition</div>
                                        <div class="fw-semibold">{{ ignitionLabel }}</div>
                                    </div>
                                    <i class="bi" :class="ignitionIconClass"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted">Speed</div>
                                        <div class="fw-semibold">{{ speedDisplay }}</div>
                                    </div>
                                    <i class="bi bi-speedometer2 fs-4 text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted">Address</div>
                                        <div class="fw-semibold text-truncate" style="max-width: 220px">{{
                                            currentAddress || '-' }}</div>
                                    </div>
                                    <i class="bi bi-geo-alt fs-4 text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="col-12 mt-1">
                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <!-- Vehicle Image (hero) -->
                        <div class="card mb-3 rounded-3">
                            <div class="card-body p-0">
                                <div class="ratio ratio-16x9">
                                    <img v-if="photos && photos.length" :src="photoUrl(photos[0])" alt="Vehicle image"
                                        style="object-fit: cover; width: 100%; height: 100%;">
                                    <div v-else
                                        class="d-flex align-items-center justify-content-center bg-light text-muted"
                                        style="width: 100%; height: 100%;">No vehicle image</div>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Information -->
                        <div class="card h-100 rounded-3">
                            <div class="card-header">
                                <h6 class="mb-0">Vehicle Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Vehicle Name</label>
                                        <div class="fw-semibold">{{ deviceName || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Unique ID</label>
                                        <div class="fw-semibold">{{ uniqueId || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Plate Number</label>
                                        <div class="fw-semibold">{{ plate || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">VIN Number</label>
                                        <div class="fw-semibold">{{ vin || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Model</label>
                                        <div class="fw-semibold">{{ model || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Type</label>
                                        <div class="fw-semibold">{{ type || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Manufacturer</label>
                                        <div class="fw-semibold">{{ manufacturer || '-' }}</div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small text-muted mb-0">Color</label>
                                        <div class="fw-semibold">{{ color || '-' }}</div>
                                    </div>
                                </div>
                                <div v-if="photos && photos.length"
                                    class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                                    <div v-for="(src, idx) in photos" :key="idx" class="rounded overflow-hidden border"
                                        style="width: 96px; height: 64px;">
                                        <img :src="photoUrl(src)" alt=""
                                            style="width: 100%; height: 100%; object-fit: cover;" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tracking Information -->
                        <div class="card mt-3 rounded-3">
                            <div class="card-header">
                                <h6 class="mb-0">Tracking Information</h6>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-5 text-muted small">Ignition</dt>
                                    <dd class="col-7 small mb-2">{{ ignitionLabel }}</dd>
                                    <dt class="col-5 text-muted small">Speed</dt>
                                    <dd class="col-7 small mb-2">{{ speedDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Device Temperature</dt>
                                    <dd class="col-7 small mb-2">{{ temperatureDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Device Battery</dt>
                                    <dd class="col-7 small mb-2">{{ batteryDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Odometer</dt>
                                    <dd class="col-7 small mb-2">{{ odometerDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Fuel Average</dt>
                                    <dd class="col-7 small mb-2">{{ fuelAverage ? fuelAverage + ' L/100km' : '-' }}</dd>
                                    <dt class="col-5 text-muted small">Max Speed</dt>
                                    <dd class="col-7 small mb-2">{{ maxSpeed ? maxSpeed + ' km/h' : '-' }}</dd>
                                    <dt class="col-5 text-muted small">Speed Limit</dt>
                                    <dd class="col-7 small mb-2">{{ speedLimit ? speedLimit + ' km/h' : '-' }}</dd>
                                    <dt class="col-5 text-muted small">Device Source</dt>
                                    <dd class="col-7 small mb-2">{{ deviceSourceLabel }}</dd>
                                    <dt class="col-5 text-muted small">Location</dt>
                                    <dd class="col-7 small mb-2">{{ currentAddress || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Coordinates</dt>
                                    <dd class="col-7 small mb-2">{{ coordsDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Last Report</dt>
                                    <dd class="col-7 small mb-2">{{ lastUpdateDisplay }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card mb-3 rounded-3">
                            <div class="card-header">
                                <h6 class="mb-0">Driver Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img v-if="driver?.avatarImageUrl" :src="driver.avatarImageUrl" alt=""
                                        class="rounded-circle" style="width:44px;height:44px;object-fit:cover" />
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ driverName || '-' }}</div>
                                        <small class="text-muted" v-if="memberSinceDisplay">Member since {{
                                            memberSinceDisplay }}</small>
                                    </div>
                                    <span class="badge" :class="driverStatusClass">{{ driverStatusLabel }}</span>
                                </div>
                                <dl class="row mb-0">
                                    <dt class="col-5 text-muted small">Phone Number</dt>
                                    <dd class="col-7 small mb-2">{{ driverPhone || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Email Address</dt>
                                    <dd class="col-7 small mb-2">{{ driverEmail || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Address</dt>
                                    <dd class="col-7 small mb-2">{{ driverAddress || '-' }}</dd>
                                    <dt class="col-5 text-muted small">ID Card</dt>
                                    <dd class="col-7 small mb-2">{{ driverIdCard || '-' }}</dd>
                                    <dt class="col-5 text-muted small">License Number</dt>
                                    <dd class="col-7 small mb-2">{{ driverLicenseNumber || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Expiry Date</dt>
                                    <dd class="col-7 small mb-2">{{ driverLicenseExpiry || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Assigned Vehicle</dt>
                                    <dd class="col-7 small mb-2">{{ deviceName || '-' }}</dd>
                                    <dt class="col-5 text-muted small">Plate Number</dt>
                                    <dd class="col-7 small mb-2">{{ plate || '-' }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="card rounded-3">
                            <div class="card-header">
                                <h6 class="mb-0">Vehicle Rating</h6>
                            </div>
                            <div class="card-body">
                                <div class="small text-muted mb-2">This rating reflects your vehicle during driving.
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>Fuel Average</div>
                                    <div class="fw-semibold">{{ (rating?.avgFuel_l_per_100km ?? 0) }} L/100km</div>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-info" role="progressbar"
                                        :style="{ width: fuelPercent + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>Avg Speed</div>
                                    <div class="fw-semibold">{{ (rating?.avgSpeed_kph ?? 0) }} km/h</div>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                        :style="{ width: speedPercent + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>Brake events</div>
                                    <div class="fw-semibold">{{ (rating?.harshBraking ?? 0) }}</div>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar"
                                        :style="{ width: brakePercent + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>Overspeed events</div>
                                    <div class="fw-semibold">{{ (rating?.overspeedEvents ?? 0) }}</div>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-danger" role="progressbar"
                                        :style="{ width: overspeedPercent + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>Overall Rating</div>
                                    <div class="fw-semibold">{{ overallScoreDisplay }}</div>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar"
                                        :style="{ width: (rating?.overallScore ?? 0) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison: Static vs Dynamic values -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3 panel-header">Static vs Dynamic (API)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Static</th>
                                        <th>Dynamic (API)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, idx) in comparisons" :key="idx">
                                        <td class="small text-muted">{{ row.label }}</td>
                                        <td class="small">{{ row.static }}</td>
                                        <td class="small fw-semibold">{{ row.dynamic ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Device Attributes vs Displayed (debug) -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3 panel-header">Device Attributes vs Displayed</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Attribute Key</th>
                                        <th>Attribute Value</th>
                                        <th>Displayed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="small text-muted">Vehicle Type</td>
                                        <td class="small">{{ typeInfo.key || '-' }}</td>
                                        <td class="small">{{ typeInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ type || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Manufacturer</td>
                                        <td class="small">{{ manufacturerInfo.key || '-' }}</td>
                                        <td class="small">{{ manufacturerInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ manufacturer || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Model</td>
                                        <td class="small">{{ modelInfo.key || '-' }}</td>
                                        <td class="small">{{ modelInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ model || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Color</td>
                                        <td class="small">{{ colorInfo.key || '-' }}</td>
                                        <td class="small">{{ colorInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ color || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">VIN Number</td>
                                        <td class="small">{{ vinInfo.key || '-' }}</td>
                                        <td class="small">{{ vinInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ vin || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Plate Number</td>
                                        <td class="small">{{ plateInfo.key || '-' }}</td>
                                        <td class="small">{{ plateInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ plate || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Odometer</td>
                                        <td class="small">{{ odometerInfo.key || '-' }}</td>
                                        <td class="small">{{ odometerInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ odometerDisplay || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Device Temperature</td>
                                        <td class="small">{{ temperatureInfo.key || '-' }}</td>
                                        <td class="small">{{ temperatureInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ temperatureDisplay || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="small text-muted">Device Battery</td>
                                        <td class="small">{{ batteryInfo.key || '-' }}</td>
                                        <td class="small">{{ batteryInfo.value ?? '-' }}</td>
                                        <td class="small fw-semibold">{{ batteryDisplay || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Trip History Table (Previous Week) -->
        <div class="row mt-3 mb-4">
            <div class="col-12">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3 panel-header">Trip History (Previous Week)</h6>
                        <div v-if="weeklyTrips.length === 0" class="text-muted small">No trips found for the previous week.</div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Distance</th>
                                        <th>Duration</th>
                                        <th>Avg Speed</th>
                                        <th>Start Address</th>
                                        <th>End Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(t, i) in weeklyTrips" :key="i">
                                        <td class="small">{{ formatDateTime(t.startTime || t.start_time) }}</td>
                                        <td class="small">{{ formatDateTime(t.endTime || t.end_time) }}</td>
                                        <td class="small">{{ formatDistanceKm(t.distance) }}</td>
                                        <td class="small">{{ formatDuration(t.duration) }}</td>
                                        <td class="small">{{ formatSpeedKmh(t.averageSpeed ?? t.average_speed) }}</td>
                                        <td class="small">{{ t.startAddress || t.start_address || '-' }}</td>
                                        <td class="small">{{ t.endAddress || t.end_address || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { LMap, LTileLayer, LMarker, LPolyline, LPopup } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { getCurrentUser } from '../../auth';
import UiAlert from '../../components/UiAlert.vue';

// Ensure Leaflet default marker icons load correctly under Vite bundling
try {
    // @ts-ignore
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).toString(),
        iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).toString(),
        shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).toString(),
    });
} catch {}

const route = useRoute();
const router = useRouter();
const deviceId = computed(() => parseInt(route.params.deviceId));

const device = ref(null);
const positions = ref([]);
const error = ref('');
const driver = ref(null);
const rating = ref(null);
const weeklyTrips = ref([]);
const detailPayload = ref(null);
const driversList = ref([]);

const mapContainer = ref(null);
const mapReady = ref(false);
const zoom = ref(13);
const mapCenter = ref([3.139, 101.6869]); // default center (Kuala Lumpur)
const markerRef = ref(null);
const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const tileAttribution = '&copy; OpenStreetMap contributors';
const polylineColor = '#007bff';

const polyline = computed(() => positions.value.map(p => [p.latitude, p.longitude]));
const currentLatLng = computed(() => {
    if (!positions.value.length) return null;
    const p = positions.value[positions.value.length - 1];
    return [p.latitude, p.longitude];
});
const currentAddress = computed(() => {
    // Prefer address from detail payload.position; fall back to last position
    const addr = detailPayload.value?.position?.address || null;
    if (addr) return addr;
    if (!positions.value.length) return null;
    return positions.value[positions.value.length - 1]?.address || null;
});

const statusLabel = computed(() => {
    const raw = detailPayload.value?.device?.status;
    if (raw) {
        const s = String(raw).toLowerCase();
        if (s === 'online' || s === 'offline') return s.toUpperCase();
    }
    const t = detailPayload.value?.position?.serverTime || detailPayload.value?.position?.servertime || null;
    if (t) {
        const dt = new Date(t);
        const online = (Date.now() - dt.getTime()) < 3600 * 1000; // within 1 hour
        return online ? 'ONLINE' : 'OFFLINE';
    }
    return 'UNKNOWN';
});
const statusBadgeClass = computed(() => {
    const s = statusLabel.value.toLowerCase();
    return {
        'text-bg-success': s === 'online',
        'text-bg-danger': s === 'offline',
        'text-bg-secondary': s !== 'online' && s !== 'offline'
    };
});
const lastUpdateDisplay = computed(() => {
    const t = detailPayload.value?.position?.serverTime
        || detailPayload.value?.position?.servertime
        || detailPayload.value?.device?.lastUpdate
        || null;
    return t ? new Date(t).toLocaleString() : '-';
});
const uniqueId = computed(() => detailPayload.value?.device?.uniqueId || detailPayload.value?.device?.uniqueid || null);

const ignitionLabel = computed(() => {
    const ign = getIgnition();
    if (ign === true) return 'On';
    if (ign === false) return 'Off';
    return '-';
});
const ignitionIconClass = computed(() => {
    const ign = getIgnition();
    return ign === true ? 'bi bi-toggle-on fs-4 text-success' : (ign === false ? 'bi bi-toggle-off fs-4 text-muted' : 'bi bi-question-circle fs-4 text-secondary');
});
const speedDisplay = computed(() => {
    const s = getSpeed();
    if (s == null) return '-';
    // Traccar speed is in knots; convert to km/h
    const kmh = Math.round(Number(s) * 1.852);
    return `${kmh} km/h`;
});

function getIgnition() {
    const p = positions.value.length ? positions.value[positions.value.length - 1] : null;
    const attrs = p?.attributes || {};
    const raw = attrs.ignition ?? p?.ignition ?? null;
    if (raw === null || raw === undefined) return null;
    const s = String(raw).toLowerCase();
    return s === 'on' || s === 'true' || s === '1' || raw === true || raw === 1 ? true
        : (s === 'off' || s === 'false' || s === '0' || raw === false || raw === 0 ? false : null);
}
function getSpeed() {
    const p = positions.value.length ? positions.value[positions.value.length - 1] : null;
    return p?.speed ?? null;
}

async function fetchDevice() {
    try {
        const res = await axios.get(`/web/vehicles/${deviceId.value}`);
        device.value = res.data;
        // Center map to last known location when available
        const tc = device.value?.tcDevice || {};
        const pos = tc?.position || {};
        if (typeof pos.latitude === 'number' && typeof pos.longitude === 'number') {
            mapCenter.value = [pos.latitude, pos.longitude];
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to load vehicle.';
    }
}

function getPreviousWeekRange() {
    const now = new Date();
    const day = now.getDay(); // 0=Sun..6=Sat
    const daysSinceMonday = (day + 6) % 7;
    const startOfThisWeek = new Date(now);
    startOfThisWeek.setHours(0, 0, 0, 0);
    startOfThisWeek.setDate(now.getDate() - daysSinceMonday);
    const startPrev = new Date(startOfThisWeek);
    startPrev.setDate(startOfThisWeek.getDate() - 7);
    const endPrev = new Date(startPrev);
    endPrev.setDate(startPrev.getDate() + 7);
    endPrev.setMilliseconds(-1);
    return { from: startPrev.toISOString(), to: endPrev.toISOString() };
}

async function fetchDetail() {
    try {
        const { from, to } = getPreviousWeekRange();
        const res = await axios.get(`/web/vehicles/${deviceId.value}/detail`, { params: { from, to } });
        const d = res.data?.detail || null;
        detailPayload.value = d;
        console.log('ddddd response ',d);
        if (d) {
            // Seed map center and positions directly from detail payload.position
            const pos = d.position || null;
            positions.value = (pos && typeof pos.latitude === 'number' && typeof pos.longitude === 'number')
                ? [{
                    latitude: pos.latitude,
                    longitude: pos.longitude,
                    speed: pos.speed ?? null,
                    address: pos.address ?? null,
                    attributes: parseAttrsMaybe(pos.attributes) ?? {},
                    serverTime: pos.serverTime ?? pos.servertime ?? null
                }]
                : [];
            if (positions.value.length) {
                const last = positions.value[positions.value.length - 1];
                mapCenter.value = [last.latitude, last.longitude];
            }
            weeklyTrips.value = Array.isArray(d.trips) ? d.trips : [];
            driversList.value = Array.isArray(d.drivers) ? d.drivers : [];
            driver.value = driversList.value.length ? driversList.value[0] : null;
        } else {
            // No detail payload available → redirect back to vehicles with a message
            router.push({ path: '/vehicles', query: { error: 'No data available for this vehicle.' } });
            return;
        }
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to load detail.';
        error.value = msg;
        // Redirect back with error message for visibility on list page
        router.push({ path: '/vehicles', query: { error: msg } });
    }
}

async function fetchPositions() {
    try {
        const res = await axios.get(`/web/vehicles/${deviceId.value}/position`);
        const point = res.data?.position || null;
        positions.value = (point && typeof point.latitude === 'number' && typeof point.longitude === 'number') ? [point] : [];
        if (positions.value.length) {
            const last = positions.value[positions.value.length - 1];
            mapCenter.value = [last.latitude, last.longitude];
            // Seed last update if device didn’t have one yet
            if (device.value?.tcDevice && !device.value.tcDevice.lastUpdate && last.serverTime) {
                device.value.tcDevice.lastUpdate = last.serverTime;
            }
        }
    } catch (e) {
        const status = e?.response?.status;
        if (status === 404) {
            positions.value = [];
            if (!error.value) error.value = 'No position found or access denied for this vehicle.';
        } else {
            error.value = e?.response?.data?.message || 'Failed to load position.';
        }
    }
}

async function fetchDriver() {
    try {
        const res = await axios.get(`/web/vehicles/${deviceId.value}/driver`);
        driver.value = res.data?.driver || null;
    } catch (e) {
        driver.value = null;
    }
}

async function fetchRating() {
    try {
        const res = await axios.get(`/web/vehicles/${deviceId.value}/rating`);
        rating.value = res.data?.rating || null;
    } catch (e) {
        rating.value = null;
    }
}

function handleResize() {
    // Trigger map re-render by toggling readiness to avoid layout artifacts
    const el = mapContainer.value;
    if (!el) return;
    mapReady.value = false;
    requestAnimationFrame(() => { mapReady.value = true; });
}

let unsubEcho = null;
async function initWebsocket() {
    try {
        const user = await getCurrentUser();
        const channelName = `positions.${user?.id}`;
        const ch = window.echo?.private ? window.echo.private(channelName) : null;
        if (!ch) return;
        const handler = (e) => {
            const list = Array.isArray(e?.positions) ? e.positions : [];
            const found = list.find(p => Number(p.id) === Number(deviceId.value));
            if (!found) return;
            // Append updated point if lat/lon present
            if (typeof found.latitude === 'number' && typeof found.longitude === 'number') {
                positions.value = [...positions.value, {
                    latitude: found.latitude,
                    longitude: found.longitude,
                    speed: found.speed ?? null,
                    address: found.address ?? null,
                    attributes: found.attributes ?? {},
                    serverTime: found.serverTime ?? null,
                }];
            }
            // Update device status and last update
            if (!device.value) device.value = {};
            device.value.tcDevice = device.value.tcDevice || {};
            if (found.status) device.value.tcDevice.status = String(found.status).toLowerCase();
            if (found.lastUpdate) device.value.tcDevice.lastUpdate = found.lastUpdate;
            if (found.uniqueId && !device.value.tcDevice.uniqueId) device.value.tcDevice.uniqueId = found.uniqueId;
        };
        ch.listen('.positions.updated', handler);
        // Additional listener to flip polling fallback off once any socket event is received
        const fallbackHandler = () => {
            socketsSeen = true;
            try { stopPositionsPolling(); } catch {}
            if (fallbackStartTimer) { try { clearTimeout(fallbackStartTimer); } catch {}; fallbackStartTimer = null; }
        };
        ch.listen('.positions.updated', fallbackHandler);
        unsubEcho = () => {
            try { ch.stopListening('.positions.updated', handler); } catch (e) { }
            try { ch.stopListening('.positions.updated', fallbackHandler); } catch (e) { }
        };
    } catch (e) {
        // no-op
    }
}

// Live polling fallback (single device) to mirror LiveTracking behavior
let pollTimer = null;
let socketsSeen = false;
let fallbackStartTimer = null;
const POLL_MS = 5000;
async function pollPositionsOnce() {
    try {
        const res = await axios.get(`/web/vehicles/${deviceId.value}/position`).catch(() => ({ data: {} }));
        const point = res?.data?.position;
        if (point && typeof point.latitude === 'number' && typeof point.longitude === 'number') {
            positions.value = [...positions.value, point];
        }
    } catch {}
}
function startPositionsPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(() => { pollPositionsOnce(); }, POLL_MS);
}
function stopPositionsPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}
function armPollingFallback() {
    if (fallbackStartTimer) clearTimeout(fallbackStartTimer);
    fallbackStartTimer = setTimeout(() => { if (!socketsSeen) startPositionsPolling(); }, 8000);
}

// Static view enhanced: fetch detail for dynamic content and weekly trips
onMounted(async () => {
    mapReady.value = true;
    window.addEventListener('resize', handleResize);
    // Load both the base device (for tcDevice.attributes) and detail payload
    try {
        await Promise.all([fetchDevice(), fetchDetail()]);
    } catch {
        // Fallback sequential if parallel fails for any reason
        try { await fetchDevice(); } catch {}
        try { await fetchDetail(); } catch {}
    }
    // Subscribe to websocket updates for live tracking
    try { await initWebsocket(); } catch {}
    // Arm polling fallback in case sockets are unavailable
    armPollingFallback();
    // If there is no position after data fetch, redirect back to list with message
    try {
        const hasPosition = Array.isArray(positions.value) && positions.value.length > 0
            && typeof positions.value[positions.value.length - 1].latitude === 'number'
            && typeof positions.value[positions.value.length - 1].longitude === 'number';
        if (!hasPosition) {
            router.push({ path: '/vehicles', query: { error: 'No position available for this vehicle.' } });
            return;
        }
    } catch {}
    // Open the marker popup by default once the map and marker are ready
    try { await nextTick(); } catch {}
    try {
        if (currentAddress.value) {
            const mk = markerRef.value?.leafletObject ?? markerRef.value;
            mk?.openPopup?.();
        }
    } catch {}
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    if (typeof unsubEcho === 'function') { try { unsubEcho(); } catch (e) { } }
});

// Keep map centered on latest position updates
watch(currentLatLng, (ll) => {
    if (Array.isArray(ll) && typeof ll[0] === 'number' && typeof ll[1] === 'number') {
        mapCenter.value = ll;
    }
});

// If address becomes available later, open the popup automatically
watch(currentAddress, async (addr) => {
    if (!addr) return;
    try { await nextTick(); } catch {}
    try {
        const mk = markerRef.value?.leafletObject ?? markerRef.value;
        mk?.openPopup?.();
    } catch {}
});

// Vehicle + tracking detail panel computed fields
// Prefer device.model from device object; fallback to attributes 'model'
const model = computed(() => {
    const d = detailPayload.value?.device;
    const fromDevice = d?.model;
    if (fromDevice !== undefined && fromDevice !== null && fromDevice !== '') return fromDevice;
    return pickAttr(['model']);
});

function parseAttrsMaybe(attr) {
    if (!attr) return {};
    try { return typeof attr === 'string' ? JSON.parse(attr) : (attr || {}); } catch { return {}; }
}
// Merge attributes from raw device and position into one view model
// Prefer attributes from the base device (tcDevice.attributes), fallback to detail payload
const deviceAttrsFromDetail = computed(() => parseAttrsMaybe(detailPayload.value?.device?.attributes));
const deviceAttrsFromDevice = computed(() => parseAttrsMaybe(device.value?.tcDevice?.attributes));
const deviceAttrs = computed(() => ({ ...deviceAttrsFromDevice.value, ...deviceAttrsFromDetail.value }));
const positionAttrs = computed(() => parseAttrsMaybe(detailPayload.value?.position?.attributes));
const tcAttrs = computed(() => ({ ...deviceAttrs.value, ...positionAttrs.value }));
function pickAttr(keys) {
    const a = tcAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return a[k];
    }
    return null;
}
// Helper to also return which attribute key was matched
function pickAttrWithKey(keys) {
    const a = tcAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return { key: k, value: a[k] };
    }
    return { key: null, value: null };
}

const plate = computed(() => pickAttr(['plate', 'registration', 'regNumber']));
const vin = computed(() => pickAttr(['vin', 'VIN']));
// Odometer from position attributes with unit handling (meters→km)
const odometerInfo = computed(() => positionPickAttrWithKey([
    'odometer', 'mileage', 'odometerKm', 'odometer_km',
    'totalDistance', 'distance', 'odometer_m', 'tripDistance'
]));
const odometerKm = computed(() => {
    const info = odometerInfo.value;
    const key = String(info.key || '').toLowerCase();
    const raw = info.value;
    if (raw == null || raw === '') return null;
    const n = extractNumber(raw);
    if (!Number.isFinite(n)) return null;
    const isMeters = key.includes('distance') || key.endsWith('_m') || key.includes('meter');
    const km = isMeters ? (n / 1000) : n;
    return km;
});
const odometerDisplay = computed(() => {
    const km = odometerKm.value;
    if (km == null) return '-';
    const rounded = km >= 100 ? Math.round(km) : Math.round(km * 10) / 10;
    return `${rounded} km`;
});
// Total Distance from position attributes, prioritize totalDistance and related keys
const totalDistanceInfo = computed(() => positionPickAttrWithKey([
    'totalDistance', 'distance', 'odometer_m', 'tripDistance'
]));
const totalDistanceKm = computed(() => {
    const info = totalDistanceInfo.value;
    const key = String(info.key || '').toLowerCase();
    const raw = info.value;
    if (raw == null || raw === '') return null;
    const n = extractNumber(raw);
    if (!Number.isFinite(n)) return null;
    const isMeters = key.includes('distance') || key.endsWith('_m') || key.includes('meter');
    const km = isMeters ? (n / 1000) : n;
    return km;
});
const totalDistanceDisplay = computed(() => {
    const km = totalDistanceKm.value;
    if (km == null) return '-';
    const rounded = km >= 100 ? Math.round(km) : Math.round(km * 10) / 10;
    return `${rounded} km`;
});
// Total Hours: prefer position attributes if available; otherwise sum durations from weekly trips
const totalHoursInfo = computed(() => positionPickAttrWithKey([
    'totalHours', 'engineHours', 'workingHours', 'runHours', 'operatingHours', 'hours'
]));
const totalHoursFromTrips = computed(() => {
    const list = Array.isArray(weeklyTrips.value) ? weeklyTrips.value : [];
    let msSum = 0;
    for (const t of list) {
        let d = Number(t?.duration);
        if (!Number.isFinite(d)) continue;
        // Heuristic: durations may be in seconds; convert to ms
        if (d < 100000) d = d * 1000;
        msSum += d;
    }
    if (msSum <= 0) return null;
    return msSum / 3600000; // hours
});
const totalHoursValue = computed(() => {
    const info = totalHoursInfo.value;
    const raw = info.value;
    // Only show hours if attribute exists; no fallback to trips
    if (raw == null || raw === '') return null;
    const n = extractNumber(raw);
    if (!Number.isFinite(n)) return null;
    // Treat large numeric values as milliseconds
    if (n >= 100000) return n / 3600000;
    // If it looks like seconds, convert to hours
    if (n >= 1000) return n / 3600;
    // Otherwise assume reported value is in hours
    return n;
});
const totalHoursDisplay = computed(() => {
    const hours = totalHoursValue.value;
    if (hours == null) return '-';
    const totalSeconds = Math.floor(Number(hours) * 3600);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    return `${h} h ${m} m`;
});
const fuelAverage = computed(() => pickAttr(['fuelAverage']));
const maxSpeed = computed(() => pickAttr(['maxSpeed']));
const speedLimit = computed(() => pickAttr(['speedLimit']));
const type = computed(() => pickAttr(['type']));
const manufacturer = computed(() => pickAttr(['manufacturer']));
const color = computed(() => pickAttr(['color']));
// Attribute key+value info for comparison
const typeInfo = computed(() => pickAttrWithKey(['type']));
const manufacturerInfo = computed(() => pickAttrWithKey(['manufacturer']));
const modelInfo = computed(() => {
    const d = detailPayload.value?.device;
    const fromDevice = d?.model;
    if (fromDevice !== undefined && fromDevice !== null && fromDevice !== '') {
        return { key: 'device.model', value: fromDevice };
    }
    return pickAttrWithKey(['model']);
});
const colorInfo = computed(() => pickAttrWithKey(['color']));
const vinInfo = computed(() => pickAttrWithKey(['vin', 'VIN']));
const plateInfo = computed(() => pickAttrWithKey(['plate', 'registration', 'regNumber']));

// Position-only attribute helpers for dynamic telemetry
function positionPickAttr(keys) {
    const a = positionAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return a[k];
    }
    return null;
}
function positionPickAttrWithKey(keys) {
    const a = positionAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return { key: k, value: a[k] };
    }
    return { key: null, value: null };
}

// Device Temperature from position attributes (common keys)
const temperatureInfo = computed(() => positionPickAttrWithKey([
    'temperature', 'temp', 'cabinTemp', 'engineTemp', 'temp1', 'temp2', 'externalTemp'
]));
const temperatureDisplay = computed(() => {
    const val = temperatureInfo.value?.value;
    if (val == null || val === '') return '-';
    const n = Number(val);
    if (Number.isFinite(n)) {
        const fixed = Math.abs(n) >= 10 ? n.toFixed(0) : n.toFixed(1);
        return `${fixed} °C`;
    }
    return String(val);
});

// Device Battery from position attributes (prefer tracker voltage keys, else mobile percent keys)
function extractNumber(raw) {
    if (raw == null) return NaN;
    const s = String(raw).replace(',', '.');
    const m = s.match(/[-+]?\d*\.?\d+/);
    return m ? Number(m[0]) : NaN;
}
function hasVoltageUnit(s) {
    const t = String(s || '').toLowerCase();
    return t.includes('v') || t.includes('volt');
}
function hasPercentUnit(s) {
    const t = String(s || '').toLowerCase();
    return t.includes('%') || t.includes('percent') || t.includes('pct');
}

const batteryInfo = computed(() => {
    const a = positionAttrs.value || {};
    const trackerKeys = ['batterylevel', 'batteryLevel', 'battery_level', 'voltage', 'batteryVoltage', 'power','battery'];
    const mobileKeys = ['battery', 'Battery', 'batteryPercent', 'deviceBattery'];
    const candidates = [];
    for (const k of trackerKeys) {
        const v = a[k];
        if (v != null && v !== '') {
            const s = String(v);
            const n = extractNumber(s);
            const style = 'voltage';
            const score = Number.isFinite(n) ? (n > 0 ? 3 : 0) : -1;
            candidates.push({ key: k, value: v, score, style });
        }
    }
    for (const k of mobileKeys) {
        const v = a[k];
        if (v != null && v !== '') {
            const s = String(v);
            const n = extractNumber(s);
            const style = hasVoltageUnit(s) ? 'voltage' : 'percent';
            const score = Number.isFinite(n) ? (n > 0 ? (style === 'voltage' ? 3 : 2) : 0) : -1;
            candidates.push({ key: k, value: v, score, style });
        }
    }
    candidates.sort((a, b) => b.score - a.score);
    if (candidates.length > 0 && candidates[0].score >= 0) {
        return { key: candidates[0].key, value: candidates[0].value };
    }
    for (const k of [...trackerKeys, ...mobileKeys]) {
        const v = a[k];
        if (v != null && v !== '') return { key: k, value: v };
    }
    return { key: null, value: null };
});

// Raw battery values by device type preference
const batteryMobileRaw = computed(() => positionPickAttr(['battery', 'Battery', 'batteryPercent', 'deviceBattery']));
const batteryTrackerRaw = computed(() => positionPickAttr(['batterylevel', 'batteryLevel', 'battery_level', 'voltage', 'batteryVoltage', 'power', 'battery', 'Battery']));
const batteryDisplay = computed(() => {
    const a = positionAttrs.value || {};
    const bl = a.batteryLevel;
    if (bl !== undefined && bl !== null && bl !== '') {
        const s = String(bl).trim();
        const n = extractNumber(s);
        if (Number.isFinite(n)) {
            return `${Math.round(n)} %`;
        }
        return hasPercentUnit(s) ? s : `${s} %`;
    }
    const bv = a.battery;
    if (bv !== undefined && bv !== null && bv !== '') {
        const s = String(bv).trim();
        const n = extractNumber(s);
        if (Number.isFinite(n)) {
            const fixed = Math.abs(n) >= 10 ? n.toFixed(1) : n.toFixed(2);
            return `${fixed} V`;
        }
        return hasVoltageUnit(s) ? s : `${s} V`;
    }
    return '-';
});

// Calibrated voltage→percent mapping (piecewise linear), tuned for 1S Li-ion
function voltageToPercent(v) {
    const points = [
        [3.40, 0],
        [3.70, 10],
        [3.80, 25],
        [3.90, 60],
        [3.96, 90],
        [4.00, 95],
        [4.20, 100],
    ];
    if (!Number.isFinite(v)) return null;
    if (v <= points[0][0]) return 0;
    if (v >= points[points.length - 1][0]) return 100;
    for (let i = 1; i < points.length; i++) {
        const [vx, px] = points[i];
        const [vPrev, pPrev] = points[i - 1];
        if (v <= vx) {
            const t = (v - vPrev) / (vx - vPrev);
            const p = pPrev + t * (px - pPrev);
            return Math.max(0, Math.min(100, Math.round(p)));
        }
    }
    return null;
}

// Battery percent for icon fill (percent for mobile, voltage→percent for trackers)
const batteryLevelPercent = computed(() => {
    const a = positionAttrs.value || {};
    const bl = a.batteryLevel;
    if (bl !== undefined && bl !== null && bl !== '') {
        const n = extractNumber(bl);
        if (!Number.isFinite(n)) return null;
        return Math.max(0, Math.min(100, Math.round(n)));
    }
    const bv = a.battery;
    if (bv !== undefined && bv !== null && bv !== '') {
        const v = extractNumber(bv);
        return voltageToPercent(v);
    }
    return null;
});
const batteryIconFill = computed(() => {
    const p = batteryLevelPercent.value;
    if (p == null) return '#adb5bd'; // gray for unknown
    if (p < 20) return '#e03131'; // red
    if (p < 50) return '#f59f00'; // amber
    return '#2f9e44'; // green
});
const batteryIconStroke = computed(() => {
    const p = batteryLevelPercent.value;
    if (p == null) return '#adb5bd';
    if (p < 20) return '#e03131';
    if (p < 50) return '#f59f00';
    return '#2f9e44';
});
const batteryFillWidth = computed(() => {
    const baseWidth = 20; // max inner fill width
    const p = batteryLevelPercent.value;
    if (p == null) return 0;
    return Math.round((Math.max(0, Math.min(100, p)) / 100) * baseWidth);
});

// Detect if the tracked device is a mobile phone (Traccar client) vs a dedicated GPS tracker
const isMobileDevice = computed(() => {
    const protocol = String(detailPayload.value?.position?.protocol || '').toLowerCase();
    if (['osmand', 'traccar', 'gpslogger', 'android', 'ios'].includes(protocol)) return true;
    const category = String(deviceAttrs.value?.category || '').toLowerCase();
    if (category === 'person' || category === 'phone') return true;
    const hasPercentBattery = ['batteryLevel','battery','deviceBattery','batteryPercent'].some(k => positionAttrs.value?.[k] != null);
    const hasVoltageBattery = ['power','voltage','batteryVoltage'].some(k => positionAttrs.value?.[k] != null);
    if (hasPercentBattery && !hasVoltageBattery) return true;
    const name = String(detailPayload.value?.device?.name || '').toLowerCase();
    if (name.includes('phone') || name.includes('mobile')) return true;
    return false;
});
const deviceSourceLabel = computed(() => isMobileDevice.value ? 'Mobile Device' : 'GPS Tracker');
const photos = computed(() => {
    const out = [];
    const toPath = (it) => {
        if (!it && it !== 0) return '';
        if (Array.isArray(it)) return it.map(toPath).filter(Boolean);
        if (typeof it === 'string') {
            const s = it.trim();
            if (!s) return '';
            // Attempt to parse JSON-encoded arrays or objects
            if ((s.startsWith('[') && s.endsWith(']')) || (s.startsWith('{') && s.endsWith('}'))) {
                try {
                    const parsed = JSON.parse(s);
                    return toPath(parsed);
                } catch { /* fall through */ }
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

    // Prefer array-style fields first (can be array, JSON string, or object)
    const arrLike = pickAttr(['photos', 'images']);
    const arrResolved = toPath(arrLike);
    if (Array.isArray(arrResolved)) out.push(...arrResolved);
    else if (typeof arrResolved === 'string' && arrResolved) out.push(arrResolved);

    // Fallback to single image keys
    const single = toPath(pickAttr(['photo', 'image', 'vehiclePhoto', 'vehicleImage']));
    if (Array.isArray(single)) out.push(...single);
    else if (typeof single === 'string' && single) out.push(single);

    // Normalize: remove empty values and dedupe
    const uniq = Array.from(new Set(out.filter(v => typeof v === 'string' && v.trim() !== '')));
    return uniq;
});
function photoUrl(p) {
    if (!p && p !== 0) return '';
    const raw = String(p).trim();
    if (!raw) return '';
    if (raw.startsWith('http') || raw.startsWith('data:')) return raw;
    if (raw.startsWith('/')) return raw; // already absolute from root
    if (raw.startsWith('storage/')) return `/${raw}`;
    if (raw.startsWith('public/')) return `/${raw.replace(/^public\//, 'storage/')}`;
    // default: treat as a public disk path under storage
    return `/storage/${raw.replace(/^\/*/, '')}`;
}

const deviceName = computed(() => detailPayload.value?.device?.name || null);
const coordsDisplay = computed(() => {
    if (!positions.value.length) return '-';
    const p = positions.value[positions.value.length - 1];
    const lat = Number(p.latitude);
    const lon = Number(p.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return '-';
    return `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
});

// Link to Google Maps for the latest coordinates
const liveLocationUrl = computed(() => {
    if (!positions.value.length) return null;
    const p = positions.value[positions.value.length - 1];
    const lat = Number(p.latitude);
    const lon = Number(p.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
    const query = encodeURIComponent(`${lat},${lon}`);
    return `https://www.google.com/maps/search/?api=1&query=${query}`;
});

// Driver helpers
const driverAttrs = computed(() => parseAttrsMaybe(driver.value?.attributes));
function driverPickAttr(keys) {
    const a = driverAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return a[k];
    }
    return null;
}
const driverName = computed(() => driver.value?.name || driverPickAttr(['name']));
const driverPhone = computed(() => driverPickAttr(['phone', 'phoneNumber']));
const driverEmail = computed(() => driverPickAttr(['email', 'emailAddress']));
const driverAddress = computed(() => driverPickAttr(['address']));
const driverIdCard = computed(() => driverPickAttr(['idCard', 'idCardNumber', 'nationalId']));
// Prefer direct fields on driver, then fall back to attribute keys (including British spelling)
const driverLicenseNumber = computed(() => (
    driver.value?.licenseNumber
    || driver.value?.licenceNumber
    || driverPickAttr([
        'license', 'licenseNumber',
        'licence', 'licenceNumber',
        'license_no', 'licenseNo',
        'licence_no', 'licenceNo',
        'driverLicenseNumber', 'driverLicenceNumber',
        'dlNumber', 'dlNo'
    ])
));
const driverLicenseExpiry = computed(() => (
    driver.value?.licenseExpiry
    || driver.value?.licenceExpiry
    || driver.value?.expiryDate
    || driver.value?.expirationDate
    || driver.value?.expireDate
    || driver.value?.validTill
    || driver.value?.validUntil
    || driverPickAttr([
        'licenseExpiry', 'expiryDate', 'expirationDate', 'expireDate',
        'licenceExpiry', 'licenceExpire', 'licenceExpiration',
        'validTill', 'validUntil'
    ])
));
const driverStatusLabel = computed(() => {
    const s = driverPickAttr(['status']);
    return (typeof s === 'string' && s.toLowerCase().includes('active')) ? 'Active' : 'Inactive';
});
const driverStatusClass = computed(() => driverStatusLabel.value === 'Active'
    ? 'bg-success-subtle text-success-emphasis'
    : 'bg-secondary-subtle text-secondary-emphasis');
const memberSinceDisplay = computed(() => {
    const since = driverPickAttr(['memberSince', 'joinedAt', 'createdAt']);
    return since ? new Date(since).toLocaleDateString() : '';
});

const driverAvatarUrl = computed(() => {
    if (driver.value?.avatarImageUrl) return driver.value.avatarImageUrl;
    const raw = driverPickAttr(['avatarImage', 'avatar_image', 'avatar']);
    if (!raw) return null;
    const s = String(raw);
    return s.startsWith('http') ? s : `/storage/${s}`;
});

// Rating helpers
const fuelPercent = computed(() => {
    const f = Number(rating.value?.avgFuel_l_per_100km ?? 0);
    const target = 15; // assumed reasonable threshold
    return Math.min(100, Math.max(0, Math.round((f / target) * 100)));
});
const speedPercent = computed(() => {
    const s = Number(rating.value?.avgSpeed_kph ?? 0);
    return Math.min(100, Math.max(0, Math.round((s / 120) * 100)));
});
const brakePercent = computed(() => {
    const b = Number(rating.value?.harshBraking ?? 0);
    return Math.min(100, Math.max(0, Math.round(b * 5)));
});
const overspeedPercent = computed(() => {
    const o = Number(rating.value?.overspeedEvents ?? 0);
    return Math.min(100, Math.max(0, Math.round(o * 4)));
});
const overallScoreDisplay = computed(() => `${Math.round(Number(rating.value?.overallScore ?? 0))}%`);

// Comparison rows: static vs dynamic
const comparisons = computed(() => ([
    { label: 'Vehicle Type', static: 'Sedan Car', dynamic: type.value || '-' },
    { label: 'Manufacturer', static: 'Toyota', dynamic: manufacturer.value || '-' },
    { label: 'Model', static: 'Camry SE', dynamic: model.value || '-' },
    { label: 'Color', static: 'Midnight Black', dynamic: color.value || '-' },
    { label: 'VIN Number', static: 'WAUYGAF6CCN174200', dynamic: vin.value || '-' },
    { label: 'Plate Number', static: 'TXR-9283d', dynamic: plate.value || '-' },
    { label: 'Ignition', static: 'off', dynamic: ignitionLabel.value || '-' },
    { label: 'Speed', static: '0 km/h', dynamic: speedDisplay.value || '-' },
    { label: 'Odometer', static: '211,644 km', dynamic: odometerDisplay.value || '-' },
    { label: 'Fuel', static: '60 Litres', dynamic: fuelAverage.value ? (fuelAverage.value + ' L/100km') : '-' },
    { label: 'Location', static: 'PLUS KM 426', dynamic: currentAddress.value || '-' },
    { label: 'Last Report', static: '14/08/25-15:39', dynamic: lastUpdateDisplay.value || '-' },
]));

// Trips helpers
function formatDateTime(s) {
    if (!s) return '-';
    try { return new Date(s).toLocaleString(); } catch { return String(s); }
}
function formatDistanceKm(d) {
    const n = Number(d);
    if (!Number.isFinite(n)) return '-';
    // Traccar distances are in meters
    return `${(n / 1000).toFixed(2)} km`;
}
function formatDuration(d) {
    if (d == null) return '-';
    let ms = Number(d);
    if (!Number.isFinite(ms)) return '-';
    // Heuristic: if value looks like seconds, convert to ms
    if (ms < 100000) ms = ms * 1000;
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const hh = String(hours).padStart(2, '0');
    const mm = String(minutes).padStart(2, '0');
    const ss = String(seconds).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
}
function formatSpeedKmh(s) {
    const n = Number(s);
    if (!Number.isFinite(n)) return '-';
    // Trips often report averageSpeed in knots; convert to km/h if reasonable
    const kmh = Math.round(n * 1.852);
    return `${kmh} km/h`;
}

</script>

<style scoped>
.panel {
    border: 1px solid #e9ecef;
    border-radius: 16px;
}

.panel-header {
    font-weight: 600;
}

.badge-soft-success {
    background-color: #eaf7ea;
    color: #198754;
}

.metric-icon {
    font-size: 1.2rem;
    color: #6c757d;
}

.progress-thin {
    height: 6px;
}

.vehicle-hero {
    aspect-ratio: 16/9;
}

.vehicle-hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

@supports not (aspect-ratio: 16/9) {
    .vehicle-hero {
        height: clamp(180px, 30vw, 280px);
    }
}

/* Colorful widget icons */
.widget-icon {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.icon-bubble {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-svg {
    width: 28px;
    height: 28px;
}

/* keep previous widget text layout tight */
.card .small {
    line-height: 1;
}

.card .fw-semibold {
    line-height: 1.1;
}
</style>
