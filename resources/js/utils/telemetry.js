export function parseAttrs(raw) {
  try { return typeof raw === 'string' ? JSON.parse(raw) : (raw || {}); } catch { return {}; }
}

/** Fuel gauge / alert thresholds (%). Below critical = red; below warning = yellow. */
export const FUEL_LEVEL_THRESHOLDS = { critical: 10, warning: 25 };

export function fuelLevelVariant(percent) {
  if (percent == null || !Number.isFinite(Number(percent))) return null;
  const p = Number(percent);
  if (p < FUEL_LEVEL_THRESHOLDS.critical) return 'danger';
  if (p < FUEL_LEVEL_THRESHOLDS.warning) return 'warning';
  return 'success';
}

export function fuelLevelColor(percent, variant = null) {
  const v = variant ?? fuelLevelVariant(percent);
  if (v === 'danger') return '#e03131';
  if (v === 'warning') return '#f59f00';
  if (v === 'success') return '#2f9e44';
  return '#adb5bd';
}

const num = (v) => {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return Number.isFinite(n) ? n : null;
};

/** Traccar device.totalDistance / position totalDistance — always metres. */
export function parseTraccarDistanceKm(raw) {
  const n = num(raw);
  if (n == null) return null;
  return n / 1000;
}

/**
 * Engine runtime counters from Traccar device.hours (ms) or position IO keys.
 * @param {string|null} key - attribute name when falling back to position attrs
 */
export function parseEngineHoursValue(key, raw) {
  const n = num(raw);
  if (n == null) return null;
  const k = String(key || '').toLowerCase();

  // Traccar device.hours and position "hours" are milliseconds
  if (k === 'hours' || k === '') {
    return n / 3_600_000;
  }

  if (['enginehours', 'totalhours', 'workinghours', 'runhours', 'operatinghours'].includes(k)) {
    if (n >= 1_000_000) return n / 3_600_000;
    if (n >= 10_000) return n / 3_600;
    return n;
  }

  if (n >= 1_000_000) return n / 3_600_000;
  if (n >= 10_000) return n / 3_600;
  return n;
}

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

const isFuelReverseFlag = (v) => v === true || v === 'true' || v === 1 || v === '1';

const isAnalogFuelAttr = (attrs, ctx) => String(attrs.fuelAttr ?? ctx?.fuelAttr ?? '').toLowerCase().includes('analog');

/** True when vehicle form explicitly disables fuel telemetry (electric / no fuel sensor). */
export function isFuelAttrNone(fuelAttr) {
  return String(fuelAttr ?? '').trim().toLowerCase() === 'none';
}

/** IO keys that report fuel level as 0–100 % (not mV / litres). */
const isPercentFuelIoKey = (key) => {
  const s = String(key || '').toLowerCase();
  return ['io89', '89', 'io48', '48'].includes(s) || s.includes('percent');
};

/**
 * Resolve EMPTY/FULL mV for (EMPTY − AI1) / (EMPTY − FULL).
 * Reverse swaps fuelMin/fuelMax first, then: if max > min → EMPTY=max, FULL=min; else EMPTY=min, FULL=max.
 */
export function resolveAnalogEmptyFull(fuelMin, fuelMax, isReverse) {
  if (fuelMin == null || fuelMax == null || fuelMin === fuelMax) return null;
  let minV = fuelMin;
  let maxV = fuelMax;
  if (isReverse) {
    minV = fuelMax;
    maxV = fuelMin;
  }
  if (maxV > minV) {
    return { empty: maxV, full: minV };
  }
  return { empty: minV, full: maxV };
}

/**
 * Client formula (one ratio, then apply capacity):
 *   ratio = (EMPTY − AI1) / (EMPTY − FULL)
 *   LITRES = CAPACITY × ratio
 *   PERCENT = ratio × 100
 * AI1 is clamped to [FULL, EMPTY] mV so readings below FULL = full tank, above EMPTY = empty.
 */
export function analogFuelFromMv(ai1, capacity, fuelMin, fuelMax, isReverse) {
  const cal = resolveAnalogEmptyFull(fuelMin, fuelMax, isReverse);
  if (cal == null || ai1 == null || capacity == null || capacity <= 0) return null;

  const EMPTY = cal.empty;
  const FULL = cal.full;
  const range = EMPTY - FULL;
  if (range === 0) return null;

  const lo = Math.min(EMPTY, FULL);
  const hi = Math.max(EMPTY, FULL);
  const AI1 = Math.max(lo, Math.min(hi, ai1));

  let ratio = (EMPTY - AI1) / range;
  ratio = Math.max(0, Math.min(1, ratio));

  const liters = parseFloat((capacity * ratio).toFixed(1));
  const percent = Math.round(ratio * 100);

  return { liters, percent, empty: EMPTY, full: FULL, ai1: AI1, ratio };
}

/** @deprecated Use analogFuelFromMv — kept for callers that only need percent. */
export function analogFuelPercent(adj, emptyCal, fullCal, isReverse) {
  const cap = 100;
  const r = analogFuelFromMv(adj, cap, emptyCal, fullCal, isReverse);
  return r ? r.percent : null;
}

export function formatFuel(rawAttrs, ctx = {}) {
  const attrs = parseAttrs(rawAttrs);
  const configuredFuelAttr = attrs.fuelAttr ?? ctx?.fuelAttr ?? null;
  if (isFuelAttrNone(configuredFuelAttr)) {
    const cap = num(ctx?.capacity ?? ctx?.fuelTankCapacity);
    return {
      key: null, liters: null, percent: null, raw: null, capacity: cap,
      display: '-', source: 'none', variant: null, isPercent: false,
    };
  }
  const getV = (k) => num(get(attrs, k));
  const cap = num(ctx?.capacity ?? ctx?.fuelTankCapacity);
  const emptyCal = getV('fuelanalogempty') ?? getV('fuelAnalogEmpty') ?? getV('fuel_empty') ?? getV('analog_empty') ?? getV('analogEmpty') ?? getV('fuelMin') ?? getV('fuel_min');
  const fullCal = getV('fuelanalogfull') ?? getV('fuelAnalogFull') ?? getV('fuel_full') ?? getV('analog_full') ?? getV('analogFull') ?? getV('fuelMax') ?? getV('fuel_max');
  const aScale = getV('fuelanalogscale') ?? getV('fuelAnalogScale') ?? getV('analog_scale') ?? getV('analogScale') ?? 1;
  const aOff = getV('fuelanalogoffset') ?? getV('fuelAnalogOffset') ?? getV('analog_offset') ?? getV('analogOffset') ?? 0;
  const fuelReverse = isFuelReverseFlag(attrs.fuelReverse ?? ctx?.fuelReverse);
  const hasAnalogCal = (emptyCal != null && fullCal != null && emptyCal !== fullCal) || aScale !== 1 || aOff !== 0;

  const analogCompute = (rawVal, rawKey) => {
    const rawNum = num(rawVal);
    if (rawNum == null) return null;
    const adj = rawNum * aScale + aOff;
    let pRes = null;
    let lRes = null;
    const fuel = cap && cap > 0 ? analogFuelFromMv(adj, cap, emptyCal, fullCal, fuelReverse) : null;
    if (fuel) {
      pRes = { k: rawKey, v: fuel.percent };
      lRes = { k: rawKey, v: fuel.liters };
    }
    return { raw: rawNum, rawKey, pRes, lRes };
  };

  const isIoKey = (k) => {
    const s = String(k || '').trim().toLowerCase();
    return /^io\d+$/.test(s) || /^\d+$/.test(s);
  };

  // Result helper
  const mkFuel = (key, l, p, raw = null, src = null) => {
    const d = (cap && cap > 0)
      ? (p != null ? `${p}%` : (l != null ? `${l} L` : null))
      : (l != null ? `${l} L` : (p != null ? `${p}%` : null));
    return {
      key, liters: l, percent: p, raw, capacity: cap, display: d, source: src,
      variant: fuelLevelVariant(p),
      isPercent: p != null && l == null
    };
  };

  // 1. Preferred/Resolved
  const pref = attrs.fuelAttr_key || ctx?.fuelAttr_key;

  if (pref) {
    let val = getV(pref);
    if (val !== null && val !== -1) {
      const fuelAttrName = String(attrs.fuelAttr ?? ctx?.fuelAttr ?? '').toLowerCase();
      const needsAnalogCal = isIoKey(pref) && isAnalogFuelAttr(attrs, ctx) && emptyCal != null && fullCal != null;
      if (needsAnalogCal) {
        const a = analogCompute(val, pref);
        if (a && (a.pRes || a.lRes)) return mkFuel(a.lRes?.k || a.pRes?.k || a.rawKey, a.lRes?.v, a.pRes?.v, a.raw);
        return mkFuel(pref, 0, 0, val, 'unconfigured');
      }
      // CAN / percent IO keys (io89, io48, io84) — not millivolt analog
      const prefLower = String(pref).toLowerCase();
      const isCanScaled = (prefLower.includes('can') || ['io84', '84'].includes(prefLower))
        && !fuelAttrName.includes('percent');
      const multiplier = isCanScaled ? 0.1 : 1.0;
      val = val * multiplier;

      let l = null, p = null;
      if (isIoKey(pref)) {
        if (cap && val >= 0) {
          if (isPercentFuelIoKey(pref) && val <= 100) {
            p = Math.round(val);
            l = Math.round((cap * val / 100) * 10) / 10;
          } else {
            l = Math.round(val * 10) / 10;
            p = Math.max(0, Math.min(100, Math.round((l / cap) * 100)));
          }
        }
      } else if (cap && val >= 0 && val <= 100) {
        p = Math.round(val);
        l = Math.round((cap * p / 100) * 10) / 10;
      } else {
        l = Math.round(val * 10) / 10;
      }
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
  let raw = null, rawKey = null;
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

  // 4. Remaining IO / analog fallback (only mV calibration for configured analog fuel)
  if (!pRes && !lRes) {
    const rKeys = ['io67', 'io68', 'io69', 'io240', 'io241', 'io242', 'io243', 'fuelRaw', 'analog1', 'analog2', 'analog3', 'adc1', 'adc2', 'adc3', 'adc'];
    let sum = 0, count = 0;

    for (const k of rKeys) {
      const v = getV(k);
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
      const useAnalogCal = isAnalogFuelAttr(attrs, ctx) && emptyCal != null && fullCal != null;
      if (useAnalogCal) {
        const a = analogCompute(raw, rawKey);
        if (a) {
          pRes = a.pRes;
          lRes = a.lRes;
        }
      } else if (isPercentFuelIoKey(rawKey) && cap && cap > 0 && raw <= 100) {
        pRes = { k: rawKey, v: Math.round(raw) };
      } else if (raw > 0) {
        lRes = { k: rawKey, v: Math.round(raw * 10) / 10 };
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
      val = defaultSpeed;
  }

  // 2. Format Value
  if (val == null) return { value: null, display: '-', unit: '' };

  if (typeof val === 'string' && /[a-z]/i.test(val)) {
    return { value: val, display: val, unit: '' };
  }

  const n = parseFloat(val);
  if (!Number.isFinite(n)) return { value: val, display: String(val), unit: '' };

  // Conversion (Knots to km/h) - Standard logic for this project
  const kmh = n;
  return {
    value: n,
    display: `${kmh.toFixed(1)} km/h`,
    unit: 'km/h'
  };
}
