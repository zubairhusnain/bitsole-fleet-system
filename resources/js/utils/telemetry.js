export function parseAttrs(raw) {
  try { return typeof raw === 'string' ? JSON.parse(raw) : (raw || {}); } catch { return {}; }
}

const num = (v) => {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n : null;
};

const fmtKm = (k) => {
  const n = Math.round(k * 10) / 10;
  try { return `${n.toLocaleString()} km`; } catch { return `${n} km`; }
};

const mkOdo = (key, raw, km) => ({ key, raw, km, display: fmtKm(km) });

// Helper to get case-insensitive value
const get = (attrs, k) => attrs[k] ?? Object.entries(attrs).find(([x]) => x.toLowerCase() === String(k).toLowerCase())?.[1];

export function formatOdometer(raw, ctx = {}) {
  const attrs = parseAttrs(raw);
  const getV = (k) => num(get(attrs, k));

  const isKm = (k) => {
    const s = String(k || '').toLowerCase();
    return ['389', 'io389', 'obd_total_mileage_389'].some(x => s.includes(x)) || s.endsWith('km') || s.includes('-km');
  };

  const defaults = [
    'CAN_Total_Mileage_87', 'OBD_Total_Mileage_389', 'GNSS_Total_Odometer_16',
    '87', '389', '16', '50', 'odometer', 'mileage', 'odometerKm', 'odometer_km',
    'totalDistance', 'distance', 'tripDistance'
  ];

  // Build priority list: Context Pref -> Attr Key -> Defaults
  const keys = [ctx?.odometerAttr, attrs.odometerKey, ctx?.odometerKey, ...defaults]
    .filter(k => k && typeof k === 'string')
    .flatMap(k => {
      // Expand IO keys if applicable
      if (['87', '389', '16', '50'].includes(k)) return [k, `io${k}`, `io_${k}`, `io-${k}`];
      return [k];
    });

  for (const k of keys) {
    const val = getV(k);
    if (val === null) continue;

    // Validation: Ignore 0 for IO keys, ignore -1
    const isIo = ['87', '389', '16', '50'].some(x => k.includes(x));
    if ((isIo && val === 0) || val <= -1) continue;

    const km = isKm(k) ? val : val / 1000;
    return mkOdo(k, val, km);
  }

  // Fallback for explicit pref that failed (return 0)
  if (ctx?.odometerAttr) return mkOdo(ctx.odometerAttr, 0, 0);
  return null;
}

export function formatFuel(rawAttrs, ctx = {}) {
  const attrs = parseAttrs(rawAttrs);
  const getV = (k) => num(get(attrs, k));
  const cap = num(ctx?.capacity ?? ctx?.fuelTankCapacity);

  // Result helper
  const mkFuel = (key, l, p, raw = null, src = null) => {
    const d = l != null ? `${l} L${p != null ? ` (${p}%)` : ''}` : (p != null ? `${p}%` : null);
    return {
      key, liters: l, percent: p, raw, capacity: cap, display: d, source: src,
      variant: p == null ? null : (p >= 60 ? 'success' : p >= 30 ? 'warning' : 'danger'),
      isPercent: p != null && l == null
    };
  };

  // 1. Preferred/Resolved
  const pref = attrs.fuelKey || ctx?.fuelKey;
  if (pref) {
    let val = getV(pref);
    if (val !== null && val !== -1) {
      // Calculate L/P
      let l = null, p = null;
      if (cap && val >= 0 && val <= 100) { p = Math.round(val); l = Math.round((cap * p / 100) * 10) / 10; }
      else { l = Math.round(val * 10) / 10; }
      return mkFuel(pref, l, p);
    }
    if (ctx?.fuelKey) return mkFuel(pref, 0, 0, null, 'zero');
  }

  // 2. Percent
  const pKeys = ['CAN_FuelPercentage_89', 'fuelPercent', 'fuelLevel', 'fuel_percent', 'fuelpercentage', 'io89', '89', 'io48', '48'];
  let pRes = null;
  for (const k of pKeys) {
    const v = getV(k);
    if (v !== null && v > -1) { pRes = { k, v: Math.max(0, Math.min(100, Math.round(v))) }; break; }
  }

  // 3. Liters
  const lKeys = ['CAN_FuelLeter_84', 'OBD_FuelLeter_48', 'fuelLiter', 'fuelLiters', 'fuel', 'io84', '84'];
  let lRes = null;
  let wasMinusOne = false;
  for (const k of lKeys) {
    const v = getV(k);
    if (v !== null) {
      if (v <= -1) { if (v === -1) wasMinusOne = true; continue; }
      lRes = { k, v: Math.round(v * 10) / 10 };
      break;
    }
  }

  // 4. Analog
  let raw = null, rawKey = null;
  if (!pRes && !lRes) {
    const rKeys = ['io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243', 'fuelRaw', 'analog1', 'analog2', 'analog3'];
    for (const k of rKeys) {
      const v = getV(k);
      if (v !== null && v > -1) { raw = v; rawKey = k; break; }
    }

    // Calculate from analog
    if (raw !== null) {
      const [min, max] = [getV('fuelanalogempty') ?? getV('fuel_empty') ?? getV('analog_empty'), getV('fuelanalogfull') ?? getV('fuel_full') ?? getV('analog_full')];
      const scale = getV('fuelanalogscale') ?? getV('analog_scale') ?? 1;
      const off = getV('fuelanalogoffset') ?? getV('analog_offset') ?? 0;

      const adj = raw * scale + off;
      let p = null;
      if (min != null && max != null && max > min) p = Math.round(((adj - min) / (max - min)) * 100);
      else if (adj >= 0 && adj <= 100) p = Math.round(adj);

      pRes = { k: rawKey, v: Math.max(0, Math.min(100, p || 0)) };
    }
  }

  // 5. Ignition Heuristic
  if (pRes?.v === 0 && !lRes) {
    const ign = get(attrs, 'ignition');
    const isOff = ign === false || ign === 0 || String(ign).toLowerCase() === 'off' || String(ign) === '0';
    if (isOff) pRes = null;
  }

  // 6. Compute Liters from Percent
  if (cap && pRes && (!lRes || (lRes && pRes.v > 0 && Math.abs((cap * pRes.v / 100) - lRes.v) > 1))) {
    // If we have capacity and percent, prioritize calculation over fallback liters
    // especially if there's a discrepancy or no liters found.
    const calcL = Math.round((cap * pRes.v / 100) * 10) / 10;

    // Only overwrite if we didn't have liters OR we have capacity and percent (user config priority)
    // Actually, always overwrite if capacity is present and we have percent, as capacity is a user override.
    // Except if percent is 0? No, even then.
    // However, keeping !lRes check was the old behavior. We want to CHANGE it.

    lRes = { k: pRes.k, v: calcL };
  }

  if (lRes || pRes || raw !== null) {
    return mkFuel(lRes?.k || pRes?.k || rawKey, lRes?.v, pRes?.v, raw);
  }
  return null;
}

export function formatTelemetry(raw, ctx = {}) {
  return { odometer: formatOdometer(raw, ctx), fuel: formatFuel(raw, ctx) };
}
