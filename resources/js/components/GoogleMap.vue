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
  markers: {
    type: Array,
    default: () => [],
  },
  selectedId: {
    type: [String, Number, null],
    default: null,
  },
  polygons: {
    type: Array,
    default: () => [],
  },
  circles: {
    type: Array, 
    default: () => [],
  },
});

const emit = defineEmits(['ready', 'click', 'error', 'marker-dragend']);

const mapEl = ref(null);
const map = ref(null);
const marker = ref(null);
let zoneMarkers = [];
let routeLines = [];
let shapeOverlays = []; // Polygons and circles
let vehicleMarkers = new Map();
let vehicleInfoWindows = new Map();
let selectedInfoWindow = null;

let googleMapsPromise = null;

function loadGoogleMapsScript() {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

  // Helper to check if the loaded Google Maps is likely the real one and not a shim
  const isRealGoogleMaps = () => {
    return window.google &&
           window.google.maps &&
           window.google.maps.Map &&
           // Real Google Maps API usually exposes a version property
           window.google.maps.version;
  };

  if (isRealGoogleMaps()) return Promise.resolve();

  if (googleMapsPromise) return googleMapsPromise;

  googleMapsPromise = new Promise((resolve, reject) => {
    const id = 'google-maps-api-script';
    // If the script tag exists, wait for it to load
    if (document.getElementById(id)) {
      const check = () => {
        if (isRealGoogleMaps()) resolve();
        else setTimeout(check, 200);
      };
      check();
      return;
    }

    // If window.google exists but fails the "real" check, it might be a shim.
    // We proceed to load the real script.

    const s = document.createElement('script');
    s.id = id;
    const base = 'https://maps.googleapis.com/maps/api/js';
    s.src = apiKey ? `${base}?key=${apiKey}&libraries=places,drawing` : `${base}?libraries=places,drawing`;
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
  try {
    shapeOverlays.forEach((s) => s.setMap(null));
  } catch {}
  zoneMarkers = [];
  routeLines = [];
  shapeOverlays = [];
}

function clearVehicleMarkers() {
  try {
    vehicleMarkers.forEach((m) => m.setMap(null));
  } catch {}
  vehicleMarkers = new Map();
  try {
    vehicleInfoWindows.forEach((i) => i.close());
  } catch {}
  vehicleInfoWindows = new Map();
  if (selectedInfoWindow) {
    try { selectedInfoWindow.close(); } catch {}
    selectedInfoWindow = null;
  }
}

function syncVehicleMarkers() {
  if (!map.value || !(window.google && window.google.maps && window.google.maps.Marker)) return;
  const items = Array.isArray(props.markers) ? props.markers : [];
  const nextIds = new Set();

  items.forEach((m) => {
    const id = m && m.id;
    const lat = Number(m.lat);
    const lng = Number(m.lon ?? m.lng);
    if ((id === null || id === undefined) || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const key = String(id);
    nextIds.add(key);
    const pos = new window.google.maps.LatLng(lat, lng);
    let mk = vehicleMarkers.get(key);
    const iconUrl = m.iconUrl || null;
    const icon = iconUrl
      ? {
          url: iconUrl,
          scaledSize: new window.google.maps.Size(36, 48),
          anchor: new window.google.maps.Point(18, 44),
        }
      : null;
    const popupHtml = m.popup || null;
    let info = null;

    if (!mk) {
      const options = { position: pos, map: map.value };
      if (icon) options.icon = icon;
      if (m.draggable) options.draggable = true;
      mk = new window.google.maps.Marker(options);
      vehicleMarkers.set(key, mk);

      if (m.draggable) {
          mk.addListener('dragend', (e) => {
               emit('marker-dragend', { id: m.id, lat: e.latLng.lat(), lng: e.latLng.lng() });
          });
      }

      if (popupHtml) {
        info = vehicleInfoWindows.get(key) || new window.google.maps.InfoWindow({ content: popupHtml });
        vehicleInfoWindows.set(key, info);
        mk.addListener('click', () => {
          if (selectedInfoWindow && selectedInfoWindow !== info) {
            try { selectedInfoWindow.close(); } catch {}
          }
          selectedInfoWindow = info;
          try { info.open({ map: map.value, anchor: mk }); } catch {}
        });
      }
    } else {
      mk.setPosition(pos);
      if (icon) mk.setIcon(icon);
      if (m.draggable !== undefined) mk.setDraggable(!!m.draggable);
      if (popupHtml) {
        info = vehicleInfoWindows.get(key);
        if (!info) {
          info = new window.google.maps.InfoWindow({ content: popupHtml });
          vehicleInfoWindows.set(key, info);
        } else {
          // Only update content if changed to prevent flickering/re-rendering
          const currentContent = info.getContent();
          if (currentContent !== popupHtml) {
             info.setContent(popupHtml);
          }
        }
      }
    }
  });

  vehicleMarkers.forEach((mk, key) => {
    if (!nextIds.has(key)) {
      try { mk.setMap(null); } catch {}
      vehicleMarkers.delete(key);
      const info = vehicleInfoWindows.get(key);
      if (info) {
        try { info.close(); } catch {}
        vehicleInfoWindows.delete(key);
      }
    }
  });
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

    // Draw route using Directions Service
    const directionsService = new window.google.maps.DirectionsService();
    directionsService.route(
      {
        origin: deviceLatLng,
        destination: zoneLatLng,
        travelMode: window.google.maps.TravelMode.DRIVING,
      },
      (result, status) => {
        if (status === window.google.maps.DirectionsStatus.OK) {
          const polyline = new window.google.maps.Polyline({
            path: result.routes[0].overview_path,
            strokeColor: '#6610f2',
            strokeOpacity: 0.7,
            strokeWeight: 4,
          });
          polyline.setMap(map.value);
          routeLines.push(polyline);
        } else {
           // Fallback to straight line if routing fails
            const polyline = new window.google.maps.Polyline({
              path: [deviceLatLng, zoneLatLng],
              strokeColor: '#6610f2',
              strokeOpacity: 0.7,
              strokeWeight: 4,
            });
            polyline.setMap(map.value);
            routeLines.push(polyline);
        }
      }
    );
  });

  // Draw Polygons
  const polyArr = Array.isArray(props.polygons) ? props.polygons : [];
  polyArr.forEach((p) => {
    if (!p || !Array.isArray(p.paths) || !p.paths.length) return;

    // Normalize paths to {lat, lng}
    const paths = p.paths.map(pt => {
        if (pt && typeof pt.lat === 'number' && typeof pt.lng === 'number') return { lat: pt.lat, lng: pt.lng };
        if (Array.isArray(pt) && pt.length >= 2) return { lat: Number(pt[0]), lng: Number(pt[1]) };
        return null;
    }).filter(pt => pt && Number.isFinite(pt.lat) && Number.isFinite(pt.lng));

    if (!paths.length) return;

    const opts = p.options || {};
    const polygon = new window.google.maps.Polygon({
        paths,
        strokeColor: opts.color || '#1070e3',
        strokeOpacity: 0.8,
        strokeWeight: opts.weight || 2,
        fillColor: opts.fillColor || '#1070e3',
        fillOpacity: opts.fillOpacity || 0.25,
        map: map.value
    });
    shapeOverlays.push(polygon);
  });

  // Draw Circles
  const circArr = Array.isArray(props.circles) ? props.circles : [];
  circArr.forEach((c) => {
    if (!c || !c.center) return;

    let center = null;
    if (c.center && typeof c.center.lat === 'number' && typeof c.center.lng === 'number') {
        center = { lat: c.center.lat, lng: c.center.lng };
    } else if (Array.isArray(c.center) && c.center.length >= 2) {
        center = { lat: Number(c.center[0]), lng: Number(c.center[1]) };
    }

    if (!center || !Number.isFinite(center.lat) || !Number.isFinite(center.lng)) return;

    const radius = Number(c.radius);
    if (!Number.isFinite(radius)) return;

    const opts = c.options || {};
    const circle = new window.google.maps.Circle({
        center,
        radius,
        strokeColor: opts.color || '#3f8fd7',
        strokeOpacity: 0.8,
        strokeWeight: opts.weight || 1,
        fillColor: opts.fillColor || '#3f8fd7',
        fillOpacity: opts.fillOpacity || 0.25,
        map: map.value
    });
    shapeOverlays.push(circle);
  });
}

function initMap() {
  if (!mapEl.value || !(window.google && window.google.maps && window.google.maps.Map)) return;
  const centerArr = Array.isArray(props.center) ? props.center : [0, 0];
  const lat = Number(centerArr[0]) || 0;
  const lng = Number(centerArr[1]) || 0;
  const hasMarkers = Array.isArray(props.markers) && props.markers.length > 0;
  const options = {
    center: { lat, lng },
    zoom: Number(props.zoom) || 13,
    mapTypeId: props.mapTypeId || 'roadmap',
    disableDefaultUI: true,
    zoomControl: true,
    zoomControlOptions: {
      position: window.google.maps.ControlPosition.RIGHT_BOTTOM,
    },
  };
  map.value = new window.google.maps.Map(mapEl.value, options);
  if (!hasMarkers) {
    marker.value = new window.google.maps.Marker({
      position: options.center,
      map: map.value,
    });
  } else {
    syncVehicleMarkers();
  }
  if (props.clickable && map.value) {
    map.value.addListener('click', (e) => {
      try {
        emit('click', { lat: e.latLng.lat(), lng: e.latLng.lng() });
      } catch {}
    });
  }
  emit('ready', map.value);
  // Draw shapes immediately
  updateZonesAndRoutes();
  // Delay popup slightly to ensure UI is ready
  setTimeout(() => {
    openSelectedIfAny();
  }, 200);
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
  () => props.polygons,
  () => {
    updateZonesAndRoutes();
  },
  { deep: true }
);

watch(
  () => props.circles,
  () => {
    updateZonesAndRoutes();
  },
  { deep: true }
);

watch(
  () => props.markers,
  () => {
    syncVehicleMarkers();
    // Re-open selected popup if markers changed and selection exists
    openSelectedIfAny();
  },
  { deep: true }
);

watch(
  () => props.selectedId,
  (id) => {
    if (!map.value) return;
    if (id === null || id === undefined) {
      if (selectedInfoWindow) {
        try { selectedInfoWindow.close(); } catch {}
        selectedInfoWindow = null;
      }
      return;
    }
    const key = String(id);
    const mk = vehicleMarkers.get(key);
    if (!mk) return;
    const pos = mk.getPosition();
    if (pos) {
      try { map.value.setCenter(pos); } catch {}
    }
    const info = vehicleInfoWindows.get(key);
    if (info) {
      if (selectedInfoWindow && selectedInfoWindow !== info) {
        try { selectedInfoWindow.close(); } catch {}
      }
      selectedInfoWindow = info;
      // If already open on the same map, don't re-open to avoid flickering
      if (info.getMap()) return;
      try { info.open({ map: map.value, anchor: mk }); } catch {}
    }
  }
);

function openSelectedIfAny() {
  const id = props.selectedId;
  if (id === null || id === undefined || !map.value) return;
  const key = String(id);
  const mk = vehicleMarkers.get(key);
  if (!mk) return;

  // NOTE: We do NOT center the map here. Centering should only happen on explicit user interaction (click/select),
  // not on every position update. This prevents the "refreshing" jumpy behavior.

  const info = vehicleInfoWindows.get(key);
  if (info) {
    if (selectedInfoWindow && selectedInfoWindow !== info) {
      try { selectedInfoWindow.close(); } catch {}
    }
    selectedInfoWindow = info;
    // If already open on the same map, don't re-open to avoid flickering
    if (info.getMap()) return;
    try { info.open({ map: map.value, anchor: mk }); } catch {}
  }
}

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
  clearVehicleMarkers();
  map.value = null;
});

function fitBounds(bounds) {
  if (!map.value || !bounds) return;
  // Handle LatLngBoundsLiteral {north, south, east, west} or Google LatLngBounds object
  if (typeof bounds.extend === 'function' || (bounds.north !== undefined && bounds.south !== undefined)) {
    map.value.fitBounds(bounds);
    return;
  }
  // Handle array of points [[lat,lng],...] or [{lat,lng},...]
  if (Array.isArray(bounds) && bounds.length > 0) {
     const b = new window.google.maps.LatLngBounds();
     bounds.forEach(pt => {
         if (Array.isArray(pt) && pt.length >= 2) b.extend({ lat: Number(pt[0]), lng: Number(pt[1]) });
         else if (pt && typeof pt.lat === 'number' && typeof pt.lng === 'number') b.extend(pt);
     });
     if (!b.isEmpty()) map.value.fitBounds(b);
  }
}

defineExpose({ map, fitBounds });
</script>
