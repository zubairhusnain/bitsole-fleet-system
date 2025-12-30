<template>
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-2">
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#e83e8c;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Trip Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#0b0f28;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Idle Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#339af0;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Distance</span></div>
          </div>
          <svg viewBox="0 0 600 260" width="100%" height="220">
            <rect x="0" y="0" width="600" height="220" fill="#fff" rx="8"/>
            <!-- Grid lines -->
            <line x1="40" y1="40" x2="580" y2="40" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="80" x2="580" y2="80" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="120" x2="580" y2="120" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="160" x2="580" y2="160" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="200" x2="580" y2="200" stroke="#f1f3f5" stroke-dasharray="4"/>
            <g transform="translate(10,0)">
              <!-- Bars (Distance - Blue) -->
              <rect v-for="(bar,i) in [140, 100, 80, 160, 120, 70, 110]" :key="'b'+i" :x="40 + i*75" :y="200-bar" width="20" :height="bar" fill="#339af0" rx="2"/>
              <!-- Bars (Idle - Dark) - Mocking different values -->
              <rect v-for="(bar,i) in [40, 30, 20, 50, 40, 10, 30]" :key="'i'+i" :x="40 + i*75 + 24" :y="200-bar" width="20" :height="bar" fill="#0b0f28" rx="2"/>
              <!-- Line (Trip Duration - Pink) -->
              <path d="M50 120 L125 80 L200 140 L275 70 L350 110 L425 90 L500 130" stroke="#e83e8c" stroke-width="3" fill="none"/>
              <circle v-for="(cx,i) in [50, 125, 200, 275, 350, 425, 500]" :key="'p'+i" :cx="cx" :cy="[120, 80, 140, 70, 110, 90, 130][i]" r="4" fill="#e83e8c"/>
            </g>
          </svg>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="row g-3 h-100">
            <div class="col-12 col-md-5">
              <div class="h-100 d-flex flex-column justify-content-center">
                <div class="small text-muted mb-1">Total Duration</div>
                <div class="display-6 fw-bold mb-3">2h 8m 8s</div>

                <div class="progress mb-1" style="height: 8px;"><div class="progress-bar" style="width:90%; background-color: #e83e8c;"></div></div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                   <div class="small fw-semibold" style="color: #e83e8c;">Trip Duration</div>
                   <div class="fw-bold">1h 54m 27s</div>
                </div>

                <div class="progress mb-1" style="height: 8px;"><div class="progress-bar" style="width:10%; background-color: #0b0f28;"></div></div>
                <div class="d-flex justify-content-between align-items-center">
                   <div class="small fw-semibold" style="color: #0b0f28;">Idle Duration</div>
                   <div class="fw-bold">13m 41s</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-7 border-start">
              <div class="ps-md-3 h-100 d-flex flex-column justify-content-center">
                <h6 class="fw-bold mb-3">Summary</h6>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Distance (km)</span><span class="fw-semibold">126.53 KM</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Trip Duration (hr)</span><span class="fw-semibold">1h 54m 27s</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Idling (hr)</span><span class="fw-semibold">13m 41s</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Idling Percentage vs Trip Duration</span><span class="fw-semibold">11.23%</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Average Fuel Consumption (km/litre)</span><span class="fw-semibold">0 km/l</span></div>
                <div class="d-flex justify-content-between"><span class="small text-muted">Total Fuel Usage (litre)</span><span class="fw-semibold">0 Litre</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Props could be added here if we want to make it dynamic later
</script>
