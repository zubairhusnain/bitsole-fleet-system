// Shared telemetry formatter for odometer and fuel (extensible)
// Accepts raw attributes (object or JSON string) and optional context (e.g., protocol)

// Model-specific mappings for telemetry keys
// Keep this small and explicit to avoid misdetections.
const MODEL_FUEL_MAPPINGS = {
  // BMW 530i (Teltonika): io48 observed as fuel level percent
  '530i': { key: 'io48', type: 'percent' },
};

export function parseAttrs(raw) {
  try {
    return typeof raw === 'string' ? JSON.parse(raw) : (raw || {});
  } catch {
    return {};
  }
}

function formatNumberKm(km) {
  const rounded = Math.round(km * 10) / 10;
  try {
    return `${rounded.toLocaleString()} km`;
  } catch {
    return `${rounded} km`;
  }
}

export function formatOdometer(rawAttrs, ctx = {}) {
  const attrs = parseAttrs(rawAttrs);
  const protocol = String(ctx?.protocol || '').toLowerCase();
  const distanceKeys = ['totalDistance', 'distance', 'tripDistance'];
  const primary = ['odometer', 'mileage', 'odometerKm', 'odometer_km'];
  // Teltonika: prefer io389, then named odometer/mileage, then distance fallbacks
  const teltonikaOrder = ['389', ...primary, ...distanceKeys];
  const genericOrder = [...primary, ...distanceKeys];
  const orderedKeys = protocol === 'teltonika' ? teltonikaOrder : genericOrder;
  let keyFound = null;
  for (const k of orderedKeys) {
    const exists = (k === '389') ? (attrs['389'] != null || attrs['io389'] != null) : (attrs[k] != null && attrs[k] !== '');
    if (exists) { keyFound = k; break; }
  }
  const parseNum = (val) => {
    const n = typeof val === 'string' ? parseFloat(val) : (typeof val === 'number' ? val : null);
    return Number.isFinite(n) ? n : null;
  };
  const getAttrVal = (k) => {
    if (Object.prototype.hasOwnProperty.call(attrs, k)) return attrs[k];
    const ioKey = 'io' + k;
    if (Object.prototype.hasOwnProperty.call(attrs, ioKey)) return attrs[ioKey];
    return undefined;
  };
  // Fallback scan
  if (!keyFound) {
    let specialKey = null;
    let specialRawVal = null;
    {
      const v16 = parseNum(getAttrVal('16'));
      if (Number.isFinite(v16) && v16 > 0) { specialKey = '16'; specialRawVal = v16; }
    }
    if (!specialKey) {
      const v389 = parseNum(getAttrVal('389'));
      if (Number.isFinite(v389) && v389 > 0) { specialKey = '389'; specialRawVal = v389; }
    }
    if (specialKey) { keyFound = specialKey; }
  }
  if (!keyFound) return null;
  const rawVal = (keyFound === '389') ? getAttrVal('389') : attrs[keyFound];
  const num = typeof rawVal === 'string' ? parseFloat(rawVal) : (typeof rawVal === 'number' ? rawVal : null);
  if (!Number.isFinite(num)) return null;
  const keyLower = String(keyFound).toLowerCase();
  let km = num;
  const looksMeters = (
    distanceKeys.map(k => k.toLowerCase()).includes(keyLower)
    || keyLower.endsWith('_m')
    || keyLower.includes('meter')
    || ((keyLower === 'odometer' || keyLower === 'mileage') && protocol === 'teltonika')
  );
  if (looksMeters) km = num / 1000;
  return {
    key: keyFound,
    raw: rawVal,
    km,
    display: formatNumberKm(km),
  };
}

export function formatFuel(rawAttrs, ctx = {}) {
  const attrs = parseAttrs(rawAttrs);
  const model = String(ctx?.model || '').trim();
  // Model override takes precedence when present and numeric
  if (model && MODEL_FUEL_MAPPINGS[model]) {
    const map = MODEL_FUEL_MAPPINGS[model];
    const val = attrs[map.key];
    const num = typeof val === 'string' ? parseFloat(val) : (typeof val === 'number' ? val : null);
    if (Number.isFinite(num)) {
      if (map.type === 'percent') {
        const pct = Math.max(0, Math.min(100, Math.round(num)));
        return { key: map.key, raw: val, isPercent: true, percent: pct, display: `${pct}%` };
      } else if (map.type === 'liters') {
        const liters = Math.round(num * 10) / 10;
        return { key: map.key, raw: val, isPercent: false, liters, display: `${liters} L` };
      }
    }
  }
  const namedCandidates = [
    'fuel', 'fuelLevel', 'fuel_level', 'fuellevel', 'fuelPercent', 'fuel_percent', 'fuelPercentage', 'fuelPct', 'fuel_ratio',
    'fuelLiters', 'fuelLitres', 'fuelVolume', 'fuel_l', 'fuelL', 'fuel1', 'fuel2'
  ];
  let keyFound = null;
  for (const k of namedCandidates) {
    if (attrs[k] != null && attrs[k] !== '') { keyFound = k; break; }
  }
  // Teltonika CAN/BLE fuel percent fallback: io113 (strict)
  if (!keyFound) {
    for (const k of ['io113','113']) { if (attrs[k] != null && attrs[k] !== '') { keyFound = k; break; } }
  }
  // As a safe fallback, only consider keys containing 'fuel' by name (avoid arbitrary io* keys)
  if (!keyFound) {
    for (const k in attrs) {
      const s = String(k).toLowerCase();
      if (s.includes('fuel')) { if (attrs[k] != null && attrs[k] !== '') { keyFound = k; break; } }
    }
  }
  if (!keyFound) return null;
  const val = attrs[keyFound];
  const num = typeof val === 'string' ? parseFloat(val) : (typeof val === 'number' ? val : null);
  if (!Number.isFinite(num)) return null;
  const keyLower = String(keyFound).toLowerCase();
  const isPercentName = keyLower.includes('percent') || keyLower.includes('pct') || keyLower.includes('ratio') || keyLower.includes('level');
  const isPercentIo = keyFound === 'io113' || keyFound === '113';
  const isPercent = isPercentName || isPercentIo;
  if (isPercent) {
    const pct = Math.max(0, Math.min(100, Math.round(num)));
    return { key: keyFound, raw: val, isPercent: true, percent: pct, display: `${pct}%` };
  }
  const liters = Math.round(num * 10) / 10;
  return { key: keyFound, raw: val, isPercent: false, liters, display: `${liters} L` };
}

export function formatTelemetry(rawAttrs, ctx = {}) {
  const odo = formatOdometer(rawAttrs, ctx);
  const fuel = formatFuel(rawAttrs, ctx);
  return { odometer: odo, fuel };
}