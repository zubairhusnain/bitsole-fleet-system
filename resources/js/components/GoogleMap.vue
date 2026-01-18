<template>
  <div ref="mapEl" style="height: 100%; width: 100%;"></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  center: {
    type: Array,
    default: () => [0, 0],
  },
  zones: {
    type: Array,
    default: () => [],
  },
  zoom: {
    type: Number,
    default: 13,
  },
  mapTypeId: {
    type: String,
    default: 'roadmap',
  },
  disableDefaultUi: {
    type: Boolean,
    default: false,
  },
  clickable: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['ready', 'click', 'error']);

const mapEl = ref(null);
const map = ref(null);
const marker = ref(null);
let zoneMarkers = [];
let routeLines = [];

let googleMapsPromise = null;

function loadGoogleMapsScript() {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
  if (window.google && window.google.maps && window.google.maps.Map) return Promise.resolve();
  if (googleMapsPromise) return googleMapsPromise;
  googleMapsPromise = new Promise((resolve, reject) => {
    const id = 'google-maps-script';
    if (document.getElementById(id)) {
      const check = () => {
        if (window.google && window.google.maps && window.google.maps.Map) resolve();
        else setTimeout(check, 200);
      };
      check();
      return;
    }
    const s = document.createElement('script');
    s.id = id;
    const base = 'https://maps.googleapis.com/maps/api/js';
    s.src = apiKey ? `${base}?key=${apiKey}` : base;
    s.async = true;
    s.defer = true;
    s.onload = () => resolve();
    s.onerror = (e) => reject(e);
    document.head.appendChild(s);
  });
  return googleMapsPromise;
}

function clearZonesAndRoutes() {
  try {
    zoneMarkers.forEach((m) => m.setMap(null));
  } catch {}
  try {
    routeLines.forEach((l) => l.setMap(null));
  } catch {}
  zoneMarkers = [];
  routeLines = [];
}

function updateZonesAndRoutes() {
  if (!map.value || !(window.google && window.google.maps && window.google.maps.Map)) return;
  clearZonesAndRoutes();
  const centerArr = Array.isArray(props.center) ? props.center : [0, 0];
  const lat = Number(centerArr[0]);
  const lng = Number(centerArr[1]);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
  const deviceLatLng = new window.google.maps.LatLng(lat, lng);
  const zonesArr = Array.isArray(props.zones) ? props.zones : [];
  zonesArr.forEach((z) => {
    const c = z && z.center;
    if (!Array.isArray(c) || c.length !== 2) return;
    const zLat = Number(c[0]);
    const zLng = Number(c[1]);
    if (!Number.isFinite(zLat) || !Number.isFinite(zLng)) return;
    const zoneLatLng = new window.google.maps.LatLng(zLat, zLng);
    const markerOptions = {
      position: zoneLatLng,
      map: map.value,
      title: z.name || '',
      icon: {
        url: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        scaledSize: new window.google.maps.Size(25, 41),
        anchor: new window.google.maps.Point(12, 41),
      },
    };
    const zoneMarker = new window.google.maps.Marker(markerOptions);
    const hasName = z && typeof z.name === 'string' && z.name !== '';
    const hasDesc = z && typeof z.description === 'string' && z.description !== '';
    if (hasName || hasDesc) {
      const html = `<div class="fw-bold">${hasName ? z.name : ''}</div>${hasDesc ? `<div class="small text-muted">${z.description}</div>` : ''}`;
      const info = new window.google.maps.InfoWindow({ content: html });
      zoneMarker.addListener('click', () => {
        try {
          info.open({ map: map.value, anchor: zoneMarker });
        } catch {}
      });
    }
    zoneMarkers.push(zoneMarker);
    const polyline = new window.google.maps.Polyline({
      path: [deviceLatLng, zoneLatLng],
      strokeColor: '#007bff',
      strokeOpacity: 0.8,
      strokeWeight: 4,
    });
    polyline.setMap(map.value);
    routeLines.push(polyline);
  });
}

function initMap() {
  if (!mapEl.value || !(window.google && window.google.maps && window.google.maps.Map)) return;
  const centerArr = Array.isArray(props.center) ? props.center : [0, 0];
  const lat = Number(centerArr[0]) || 0;
  const lng = Number(centerArr[1]) || 0;
  const options = {
    center: { lat, lng },
    zoom: Number(props.zoom) || 13,
    mapTypeId: props.mapTypeId || 'roadmap',
    disableDefaultUI: props.disableDefaultUi,
  };
  map.value = new window.google.maps.Map(mapEl.value, options);
  marker.value = new window.google.maps.Marker({
    position: options.center,
    map: map.value,
  });
  if (props.clickable && map.value) {
    map.value.addListener('click', (e) => {
      try {
        emit('click', { lat: e.latLng.lat(), lng: e.latLng.lng() });
      } catch {}
    });
  }
  emit('ready', map.value);
  updateZonesAndRoutes();
}

onMounted(async () => {
  try {
    await loadGoogleMapsScript();
    initMap();
  } catch (e) {
    try {
      emit('error', e);
    } catch {}
  }
});

watch(
  () => props.center,
  (val) => {
    if (!map.value || !Array.isArray(val) || val.length !== 2) return;
    const lat = Number(val[0]);
    const lng = Number(val[1]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const pos = { lat, lng };
    map.value.setCenter(pos);
    if (marker.value) marker.value.setPosition(pos);
    updateZonesAndRoutes();
  },
  { deep: true }
);

watch(
  () => props.zones,
  () => {
    updateZonesAndRoutes();
  },
  { deep: true }
);

watch(
  () => props.zoom,
  (z) => {
    const zoomVal = Number(z);
    if (!map.value || !Number.isFinite(zoomVal)) return;
    map.value.setZoom(zoomVal);
  }
);

watch(
  () => props.mapTypeId,
  (t) => {
    if (!map.value || !t) return;
    map.value.setMapTypeId(t);
  }
);

onBeforeUnmount(() => {
  if (marker.value) {
    marker.value.setMap(null);
    marker.value = null;
  }
  clearZonesAndRoutes();
  map.value = null;
});
</script>
