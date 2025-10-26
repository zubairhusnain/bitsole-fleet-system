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
            <div class="col-auto text-end ms-auto" v-if="device">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge" :class="statusBadgeClass">{{ statusLabel }}</span>
                    <span class="text-muted">Last update: {{ lastUpdateDisplay }}</span>
                </div>
            </div>
        </div>

        <!-- Add error banner -->
        <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

        <!-- Static top Leaflet map -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body p-0">
                        <div ref="mapContainer" style="height: calc(60vh - 16px); min-height: 320px;">
                            <LMap v-if="mapReady" :zoom="zoom" :center="mapCenter" style="height: 100%; width: 100%;">
                                <LTileLayer :url="tileUrl" :attribution="tileAttribution" />
                                <LMarker :lat-lng="mapCenter"></LMarker>
                            </LMap>
                            <div v-else class="placeholder-glow" style="height: 100%">
                                <span class="placeholder col-12" style="height: 100%"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top widgets under map -->
        <div class="row g-3 mb-3">
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
                                <div class="fw-semibold">Sep 25 1:33 am</div>
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
                                <div class="fw-semibold">Off</div>
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
                                <div class="fw-semibold">0</div>
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
                                <div class="fw-semibold">8,896.73Km</div>
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
                                    <rect x="8" y="18" width="28" height="16" rx="3" fill="none" stroke="#2f9e44"
                                        stroke-width="3" />
                                    <rect x="36" y="22" width="4" height="8" rx="1" fill="#2f9e44" />
                                    <rect x="11" y="21" width="14" height="10" rx="2" fill="#69db7c" />
                                </svg>
                            </div>
                            <div>
                                <div class="small text-muted">Device Battery</div>
                                <div class="fw-semibold">%</div>
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
                            <img src="https://images.unsplash.com/photo-1549924231-f129b911e442?q=80&w=1600&auto=format&fit=crop"
                                alt="Camry" class="w-100" />
                        </div>
                        <h6 class="mb-3 panel-header">Vehicle Information</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Vehicle Type</div>
                                <div class="fw-semibold">Sedan Car</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Manufacturer</div>
                                <div class="fw-semibold">Toyota</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Model</div>
                                <div class="fw-semibold">Camry SE</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Color</div>
                                <div class="fw-semibold">Midnight Black</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">VIN Number</div>
                                <div class="fw-semibold">WAUYGAF6CCN174200</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted small">Plate Number</div>
                                <div class="fw-semibold">TXR-9283d</div>
                            </div>
                        </div>
                        <div class="mt-3 small d-flex flex-wrap gap-4 border-top pt-2">
                            <span class="fw-semibold text-primary">VHCL-1009</span>
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
                                <div class="text-muted small">Ignition</div>
                                <div class="fw-semibold">off</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Speed</div>
                                <div class="fw-semibold">0 km/h</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Odometer</div>
                                <div class="fw-semibold">211,644 km</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Fuel</div>
                                <div class="fw-semibold">60 Litres</div>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Location</div>
                                <div class="fw-semibold">PLUS KM 426</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Last Report</div>
                                <div class="fw-semibold">14/08/25-15:39</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Map Link</div>
                                <a href="#" class="fw-semibold text-primary text-decoration-underline">Live Location</a>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted small">Track History</div>
                                <a href="#" class="fw-semibold text-primary text-decoration-underline">Track History</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle column: Driver Information -->
            <div class="col-12 col-lg-3">
                <div class="card panel rounded-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="https://i.pravatar.cc/100?img=12" alt="Oliver" class="rounded-circle"
                                style="width:56px;height:56px;object-fit:cover" />
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Oliver Thompson</div>
                                <small class="text-muted">Member Since: July 2020</small>
                            </div>
                            <span class="badge badge-soft-success">Active</span>
                        </div>
                        <h6 class="mb-2">Contact Detail</h6>
                        <dl class="row mb-3">
                            <dt class="col-5 text-muted small">Phone Number</dt>
                            <dd class="col-7 small mb-2">(727) 540-0492</dd>
                            <dt class="col-5 text-muted small">Email Address</dt>
                            <dd class="col-7 small mb-2">oliverthompson@gmail.com</dd>
                            <dt class="col-5 text-muted small">Address</dt>
                            <dd class="col-7 small mb-2">4545 118th Ave N, Clearwater, Florida USA</dd>
                        </dl>
                        <h6 class="mb-2">Licence Information</h6>
                        <dl class="row mb-3">
                            <dt class="col-5 text-muted small">ID</dt>
                            <dd class="col-7 small mb-2">ID-84917-TR55</dd>
                            <dt class="col-5 text-muted small">License Number</dt>
                            <dd class="col-7 small mb-2">013-1982-6794</dd>
                            <dt class="col-5 text-muted small">Expiry Date</dt>
                            <dd class="col-7 small mb-2">05,15,2035</dd>
                        </dl>
                        <h6 class="mb-2">Vehicle Information</h6>
                        <div class="mb-1 small text-muted">Assigned Vehicle</div>
                        <div class="fw-semibold">Toyota Camry SE</div>
                        <div class="mb-1 small text-muted mt-2">Plate Number</div>
                        <div class="fw-semibold">TXR- 6794</div>
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
                                    <dt class="col-5 text-muted small">Odometer</dt>
                                    <dd class="col-7 small mb-2">{{ odometerDisplay }}</dd>
                                    <dt class="col-5 text-muted small">Fuel Average</dt>
                                    <dd class="col-7 small mb-2">{{ fuelAverage ? fuelAverage + ' L/100km' : '-' }}</dd>
                                    <dt class="col-5 text-muted small">Max Speed</dt>
                                    <dd class="col-7 small mb-2">{{ maxSpeed ? maxSpeed + ' km/h' : '-' }}</dd>
                                    <dt class="col-5 text-muted small">Speed Limit</dt>
                                    <dd class="col-7 small mb-2">{{ speedLimit ? speedLimit + ' km/h' : '-' }}</dd>
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { LMap, LTileLayer, LMarker, LPolyline } from '@vue-leaflet/vue-leaflet';
import 'leaflet/dist/leaflet.css';
import { getCurrentUser } from '../../auth';
import UiAlert from '../../components/UiAlert.vue';

const route = useRoute();
const deviceId = computed(() => parseInt(route.params.deviceId));

const device = ref(null);
const positions = ref([]);
const error = ref('');
const driver = ref(null);
const rating = ref(null);

const mapContainer = ref(null);
const mapReady = ref(false);
const zoom = ref(13);
const mapCenter = ref([3.139, 101.6869]); // default center (Kuala Lumpur)
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
    if (!positions.value.length) return null;
    return positions.value[positions.value.length - 1]?.address || null;
});

const statusLabel = computed(() => {
    const s = device.value?.tcDevice?.status || 'unknown';
    return String(s).toUpperCase();
});
const statusBadgeClass = computed(() => {
    const s = String(device.value?.tcDevice?.status || 'unknown').toLowerCase();
    return {
        'text-bg-success': s === 'online',
        'text-bg-danger': s === 'offline',
        'text-bg-secondary': s !== 'online' && s !== 'offline'
    };
});
const lastUpdateDisplay = computed(() => {
    const posTime = positions.value.length ? positions.value[positions.value.length - 1]?.serverTime : null;
    const t = posTime || device.value?.tcDevice?.lastUpdate || null;
    return t ? new Date(t).toLocaleString() : '-';
});
const uniqueId = computed(() => device.value?.tcDevice?.uniqueId || device.value?.tcDevice?.uniqueid || null);

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
        unsubEcho = () => { try { ch.stopListening('.positions.updated', handler); } catch (e) { } };
    } catch (e) {
        // no-op
    }
}

// Static view: skip dynamic fetches and websockets
onMounted(() => {
    mapReady.value = true;
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
    if (typeof unsubEcho === 'function') { try { unsubEcho(); } catch (e) { } }
});

// Vehicle + tracking detail panel computed fields
const model = computed(() => device.value?.tcDevice?.model || null);

function parseAttrsMaybe(attr) {
    if (!attr) return {};
    try { return typeof attr === 'string' ? JSON.parse(attr) : (attr || {}); } catch { return {}; }
}
const tcAttrs = computed(() => parseAttrsMaybe(device.value?.tcDevice?.attributes));
function pickAttr(keys) {
    const a = tcAttrs.value || {};
    for (const k of keys) {
        if (a[k] != null && a[k] !== '') return a[k];
    }
    return null;
}

const plate = computed(() => pickAttr(['plate', 'registration', 'regNumber']));
const vin = computed(() => pickAttr(['vin', 'VIN']));
const odometer = computed(() => pickAttr(['odometer', 'mileage', 'odometerKm', 'odometer_km']));
const odometerDisplay = computed(() => {
    const val = odometer.value;
    if (val == null || val === '') return '-';
    const n = Number(val);
    return Number.isFinite(n) ? `${Math.round(n)} km` : String(val);
});
const fuelAverage = computed(() => pickAttr(['fuelAverage']));
const maxSpeed = computed(() => pickAttr(['maxSpeed']));
const speedLimit = computed(() => pickAttr(['speedLimit']));
const type = computed(() => pickAttr(['type']));
const manufacturer = computed(() => pickAttr(['manufacturer']));
const color = computed(() => pickAttr(['color']));
const photos = computed(() => {
    const ph = pickAttr(['photos']);
    if (!ph) return [];
    return Array.isArray(ph) ? ph : [ph];
});
function photoUrl(p) {
    if (!p) return '';
    return String(p).startsWith('http') ? String(p) : `/storage/${p}`;
}

const deviceName = computed(() => device.value?.tcDevice?.name || null);
const coordsDisplay = computed(() => {
    if (!positions.value.length) return '-';
    const p = positions.value[positions.value.length - 1];
    const lat = Number(p.latitude);
    const lon = Number(p.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return '-';
    return `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
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
const driverLicenseNumber = computed(() => driverPickAttr(['license', 'licenseNumber']));
const driverLicenseExpiry = computed(() => driverPickAttr(['licenseExpiry', 'expiryDate']));
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
