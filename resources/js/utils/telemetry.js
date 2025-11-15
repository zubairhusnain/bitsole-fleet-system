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
  const preferNamed = !!ctx?.preferNamedOdometer;
  const distanceKeys = ['totalDistance', 'distance', 'tripDistance'];
  const primary = ['odometer', 'mileage', 'odometerKm', 'odometer_km'];
  // Teltonika: prefer io389, then named odometer/mileage, then distance fallbacks
  const teltonikaOrderIoFirst = ['16', '87', '50', '389', ...primary, ...distanceKeys];
  const teltonikaOrderNamedFirst = [...primary, ...distanceKeys, '16', '87', '50', '389'];
  const genericOrderIoFirst = ['16', '87', '50', ...primary, ...distanceKeys];
  const genericOrderNamedFirst = [...primary, ...distanceKeys, '16', '87', '50'];
  const orderedKeys = protocol === 'teltonika'
    ? teltonikaOrderIoFirst
    : genericOrderIoFirst;
  let keyFound = null;
  for (const k of orderedKeys) {
    // For numeric IO keys, check both raw and io-prefixed variants
    const val = (k === '389' || k === '87' || k === '50' || k === '16')
      ? (Object.prototype.hasOwnProperty.call(attrs, k) ? attrs[k]
         : (Object.prototype.hasOwnProperty.call(attrs, 'io' + k) ? attrs['io' + k] : undefined))
      : attrs[k];
    const exists = val != null && val !== '';
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
  const rawVal = (keyFound === '389' || keyFound === '87' || keyFound === '50' || keyFound === '16') ? getAttrVal(keyFound) : attrs[keyFound];
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
  // Heuristic for numeric IO keys (io87/io50): values typically in meters if very large
  if ((keyFound === '87' || keyFound === '50' || keyFound === '389' || keyFound === '16') && !looksMeters) {
    km = num >= 100000 ? (num / 1000) : num; // >=100,000 assumed meters → km
  }
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
  const lower = Object.fromEntries(Object.entries(attrs).map(([k,v]) => [String(k).toLowerCase(), v]));
  const capacity = ctx?.capacity ?? ctx?.fuelTankCapacity ?? (lower['fueltankcapacity'] ?? null);

  // Preferred named keys
  const percentNamed = lower['fuellevel'] ?? attrs['fuelLevel'] ?? attrs['FuelLevel'];
  const litersNamed = lower['fuel'];
  // IO fallbacks
  const io89 = attrs['io89'] ?? attrs['89'];
  const io48 = attrs['io48'] ?? attrs['48'];
  const io84 = attrs['io84'] ?? attrs['84'];

  // Resolve percent
  let percent = null;
  for (const v of [percentNamed, io89, io48]) {
    const n = typeof v === 'string' ? parseFloat(v) : (typeof v === 'number' ? v : null);
    if (Number.isFinite(n)) { percent = Math.max(0, Math.min(100, Math.round(n))); break; }
  }

  // Resolve liters
  let liters = null;
  for (const v of [litersNamed, io84]) {
    const n = typeof v === 'string' ? parseFloat(v) : (typeof v === 'number' ? v : null);
    if (Number.isFinite(n)) { liters = Math.round(n * 10) / 10; break; }
  }

  // If capacity provided and percent available, prefer computed liters
  let computedLiters = null;
  if (Number.isFinite(parseFloat(capacity)) && percent != null) {
    const cap = parseFloat(capacity);
    computedLiters = Math.round((cap * percent / 100) * 10) / 10;
  }

  const badgeVariant = percent == null ? null : (percent >= 60 ? 'success' : (percent >= 30 ? 'warning' : 'danger'));
  const finalLiters = computedLiters != null ? computedLiters : liters;
  const display = finalLiters != null ? `${finalLiters} L` : (percent != null ? `${percent}%` : null);
  if (display == null) return null;
  return {
    isPercent: percent != null && finalLiters == null,
    percent,
    liters: finalLiters,
    capacity: Number.isFinite(parseFloat(capacity)) ? parseFloat(capacity) : null,
    variant: badgeVariant,
    display,
  };
}

export function formatTelemetry(rawAttrs, ctx = {}) {
  const odo = formatOdometer(rawAttrs, ctx);
  const fuel = formatFuel(rawAttrs, ctx);
  return { odometer: odo, fuel };
}