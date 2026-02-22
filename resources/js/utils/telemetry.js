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

  // 1. Strict Priority: odometerAttr_key
  const pref = attrs.odometerAttr_key || ctx?.odometerAttr_key;
  if (pref) {
    const val = getV(pref);
    if (val !== null && val > -1) {
      // Validation: Ignore 0 for IO keys
      const isIo = ['87', '389', '16', '50'].some(x => pref.includes(x));
      if (!((isIo && val === 0))) {
        const km = isKm(pref) ? val : val / 1000;
        return mkOdo(pref, val, km);
      }
    }
    // Explicitly configured but missing/invalid -> Return 0/Null (Skip defaults)
    return mkOdo(pref, 0, 0);
  }

  // 2. Defaults
  const defaults = [
    '87', '389', '16', '50', 'odometer', 'mileage', 'odometerKm', 'odometer_km',
    'totalDistance', 'distance', 'tripDistance'
  ];

  // Include legacy ctx.odometerAttr in fallback search if not caught above
  const keys = [ctx?.odometerAttr, ...defaults]
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

  const calcIo9Fuel = () => {
    const raw = getV('io9');
    if (raw === null || raw <= 0) return null;
    const min = getV('fuelanalogempty') ?? getV('fuel_empty') ?? getV('analog_empty') ?? getV('fuelMin') ?? getV('fuel_min');
    const max = getV('fuelanalogfull') ?? getV('fuel_full') ?? getV('analog_full') ?? getV('fuelMax') ?? getV('fuel_max');
    const scale = getV('fuelanalogscale') ?? getV('analog_scale') ?? 1;
    const off = getV('fuelanalogoffset') ?? getV('analog_offset') ?? 0;
    if (min == null || max == null || min === max) return null;
    const adj = raw * scale + off;
    let p;
    if (max > min) {
      p = ((adj - min) / (max - min)) * 100;
    } else {
      p = ((min - adj) / (min - max)) * 100;
    }
    p = Math.max(0, Math.min(100, Math.round(p)));
    let l = null;
    if (cap && cap > 0) {
      l = Math.round((cap * p / 100) * 10) / 10;
    }
    return { raw, p, l };
  };

  // 1. Preferred/Resolved
  const pref = attrs.fuelAttr_key || ctx?.fuelAttr_key;
  const fuelAttr = attrs.fuelAttr || ctx?.fuelAttr;

  if (pref && fuelAttr) {
    if (pref === 'io9') {
      const res = calcIo9Fuel();
      if (res) return mkFuel(pref, res.l, res.p, res.raw, 'io9');
      return mkFuel(pref, 0, 0, null, 'zero');
    }
    const val = getV(pref);
    if (val !== null && val !== -1) {
      let v = val;
      const isCan = String(pref).toLowerCase().includes('can') || ['io84', '84'].includes(String(pref).toLowerCase());
      const multiplier = isCan ? 0.1 : 1.0;
      v = v * multiplier;

      let l = null, p = null;
      if (cap && v >= 0 && v <= 100) { p = Math.round(v); l = Math.round((cap * p / 100) * 10) / 10; }
      else { l = Math.round(v * 10) / 10; }
      return mkFuel(pref, l, p);
    }
    // Explicitly configured but missing/invalid -> Return empty/zero (Skip defaults)
    return mkFuel(pref, 0, 0, null, 'zero');
  }

  // 2. Percent
  const pKeys = ['fuelPercent', 'fuelLevel', 'fuel_percent', 'fuelpercentage', 'io89', '89', 'io48', '48'];
  let pRes = null;
  for (const k of pKeys) {
    const v = getV(k);
    if (v !== null && v > -1) { pRes = { k, v: Math.max(0, Math.min(100, Math.round(v))) }; break; }
  }

  // 3. Liters
  const lKeys = ['canFuel', 'can_fuel', 'can_fuel_level', 'fuelLiter', 'fuelLiters', 'fuel', 'io84', '84'];
  let lRes = null;
  let wasMinusOne = false;
  for (const k of lKeys) {
    const v = getV(k);
    if (v !== null) {
      if (v <= -1) { if (v === -1) wasMinusOne = true; continue; }
      const isCan = String(k).toLowerCase().includes('can') || ['io84', '84'].includes(String(k).toLowerCase());
      const multiplier = isCan ? 0.1 : 1.0;
      lRes = { k, v: Math.round((v * multiplier) * 10) / 10 };
      break;
    }
  }

  // 4. Analog (For analog-only devices or as fallback)
  let raw = null, rawKey = null;
  if (!pRes && !lRes) {
    const rKeys = ['io9', 'io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243', 'fuelRaw', 'analog1', 'analog2', 'analog3'];
    let sum = 0, count = 0;

    for (const k of rKeys) {
      const v = getV(k);
      // Only include values that look like raw analog readings (usually > 0)
      if (v !== null && v > 0) {
        sum += v;
        count++;
        if (!rawKey) rawKey = k;
      }
    }

    if (count > 0) {
      raw = sum / count;
      if (count > 1) rawKey = 'analog_avg';
    }

    if (raw !== null) {
      const min = getV('fuelanalogempty') ?? getV('fuel_empty') ?? getV('analog_empty') ?? getV('fuelMin') ?? getV('fuel_min');
      const max = getV('fuelanalogfull') ?? getV('fuel_full') ?? getV('analog_full') ?? getV('fuelMax') ?? getV('fuel_max');
      const scale = getV('fuelanalogscale') ?? getV('analog_scale') ?? 1;
      const off = getV('fuelanalogoffset') ?? getV('analog_offset') ?? 0;

      const adj = raw * scale + off;

      if (min != null && max != null && min !== max) {
        let p;
        if (max > min) {
          // Sensor increases with fuel: standard interpolation
          p = ((adj - min) / (max - min)) * 100;
        } else {
          // Sensor decreases with fuel: EMPTY > FULL (client formula)
          // Percentage full = (EMPTY - value) / (EMPTY - FULL) * 100
          p = ((min - adj) / (min - max)) * 100;
        }
        p = Math.round(p);
        pRes = { k: rawKey, v: Math.max(0, Math.min(100, p)) };
      } else {
        lRes = { k: rawKey, v: Math.round(adj * 10) / 10 };
      }
    }
  }

  // 5. Ignition Heuristic
  if (pRes?.v === 0 && !lRes) {
    const ign = get(attrs, 'ignition');
    const isOff = ign === false || ign === 0 || String(ign).toLowerCase() === 'off' || String(ign) === '0';
    if (isOff) pRes = null;
  }

  // 6. Compute Liters/Percent Cross-Fill (If capacity is valid and not 0)
   if (cap && cap > 0) {
     // Scenario A: Value is in percentage -> Calculate liters
     if (pRes && !lRes) {
       lRes = { k: pRes.k, v: Math.round((cap * pRes.v / 100) * 10) / 10 };
     }
     // Scenario B: Value is in liters -> Calculate percentage
     else if (lRes && !pRes) {
       pRes = { k: lRes.k, v: Math.max(0, Math.min(100, Math.round((lRes.v / cap) * 100))) };
     }
     // Scenario C: Both present -> Ensure they are synced based on capacity
     else if (pRes && lRes && Math.abs((cap * pRes.v / 100) - lRes.v) > 1) {
       lRes = { k: pRes.k, v: Math.round((cap * pRes.v / 100) * 10) / 10 };
     }
   }

  if (lRes || pRes || raw !== null) {
    return mkFuel(lRes?.k || pRes?.k || rawKey, lRes?.v, pRes?.v, raw);
  }
  return null;
}

export function formatTelemetry(raw, ctx = {}) {
  return { odometer: formatOdometer(raw, ctx), fuel: formatFuel(raw, ctx) };
}

export function formatSpeed(deviceAttributes, position) {
  const attrs = parseAttrs(deviceAttributes);
  const pAttrs = position?.attributes || {};
  const defaultSpeed = position?.speed;

  // 1. Resolve Value
  let val = null;
  const speedAttrKey = attrs.speedAttr_key;

  if (speedAttrKey) {
    // Strict Priority: Use configured key exclusively.
    // If missing in attributes, default to 0 (do NOT fallback to GPS speed).
    const v = pAttrs[speedAttrKey];
    val = (v !== undefined && v !== null) ? v : 0;
  } else {
    const speedAttr = attrs.speedAttr;
    if (speedAttr) {
      const key = Object.keys(pAttrs).find(k => k.toLowerCase() === speedAttr.toLowerCase());
      if (key) val = pAttrs[key];
    }

    // Fallback to GPS Speed (only if no strict key configured)
    if (val === null || val === undefined) {
      val = defaultSpeed;
    }
  }

  // 2. Format Value
  if (val == null) return { value: null, display: '-', unit: '' };

  if (typeof val === 'string' && /[a-z]/i.test(val)) {
    return { value: val, display: val, unit: '' };
  }

  const n = parseFloat(val);
  if (!Number.isFinite(n)) return { value: val, display: String(val), unit: '' };

  // Conversion (Knots to km/h) - Standard logic for this project
  const kmh = n * 1.852;
  return {
    value: n,
    display: `${kmh.toFixed(1)} km/h`,
    unit: 'km/h'
  };
}
