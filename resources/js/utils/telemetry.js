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
  const userPriorityKeys = ['CAN_Total_Mileage_87', 'OBD_Total_Mileage_389', 'GNSS_Total_Odometer_16'];
  const distanceKeys = ['totalDistance', 'distance', 'tripDistance'];
  const primary = ['odometer', 'mileage', 'odometerKm', 'odometer_km'];
  // Unified order: include 389 in IO check list for everyone
  const orderIoFirst = [...userPriorityKeys, '16', '87', '50', '389', ...primary, ...distanceKeys];
  const orderNamedFirst = [...userPriorityKeys, ...primary, ...distanceKeys, '16', '87', '50', '389'];

  const orderedKeys = preferNamed ? orderNamedFirst : orderIoFirst;
  const getIoVal = (n) => {
    const forms = [String(n), 'io' + String(n), 'io_' + String(n), 'io-' + String(n)];
    for (const f of forms) {
      if (Object.prototype.hasOwnProperty.call(attrs, f)) return attrs[f];
    }
    return undefined;
  };

  // Create case-insensitive map for named keys
  const attrsLower = {};
  for (const key of Object.keys(attrs)) {
    attrsLower[key.toLowerCase()] = attrs[key];
  }

  let keyFound = null;
  // REMOVED pre-check for 16 to respect orderedKeys priority
  for (const k of orderedKeys) {
    // For numeric IO keys, check both raw and io-prefixed variants
    let val;
    if (k === '389' || k === '87' || k === '50' || k === '16') {
      val = getIoVal(k);
    } else {
      // Check exact match first, then case-insensitive
      val = attrs[k] ?? attrsLower[k.toLowerCase()];
    }

    const exists = val != null && val !== '';
    if (keyFound == null && exists) { keyFound = k; break; }
  }
  const parseNum = (val) => {
    const n = typeof val === 'string' ? parseFloat(val) : (typeof val === 'number' ? val : null);
    return Number.isFinite(n) ? n : null;
  };
  const getAttrVal = (k) => {
    if (k === '389' || k === '87' || k === '50' || k === '16') return getIoVal(k);
    return attrs[k] ?? attrsLower[k.toLowerCase()];
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
  const rawVal = getAttrVal(keyFound);
  const num = typeof rawVal === 'string' ? parseFloat(rawVal) : (typeof rawVal === 'number' ? rawVal : null);
  if (!Number.isFinite(num)) return null;
  const keyLower = String(keyFound).toLowerCase();
  let km = num;
    // console.log('odometer km value ',km,rawVal,keyFound,keyLower);

  const skipConversion = (keyFound === '389') || (keyLower === 'obd_total_mileage_389');

  if (!skipConversion) km = num / 1000;
  console.log('odometer in km or meter ',km,num,keyFound);
;  return {
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
  const capacity = ctx?.capacity ?? ctx?.fuelTankCapacity ?? null;

  const num = (v) => {
    const n = typeof v === 'string' ? parseFloat(v) : (typeof v === 'number' ? v : null);
    return Number.isFinite(n) ? n : null;
  };
  const resolveByName = null;

  const percentFuelPercent = lower['fuelpercent'];
  const percentFuelLevel = lower['fuellevel'] ?? attrs['fuelLevel'] ?? attrs['FuelLevel'];
  const percentFuelPercentage = lower['fuelpercentage'] ?? lower['fuel_percent'];
  const litersFuelLiter = lower['fuelliter'];
  const litersFuelLiters = lower['fuelliters'] ?? attrs['fuelLiters'] ?? attrs['FuelLiters'];
  const litersFuel = lower['fuel'];
  // IO fallbacks
  const io89 = attrs['io89'] ?? attrs['89'];
  const io48 = attrs['io48'] ?? attrs['48'];
  const io84 = attrs['io84'] ?? attrs['84'];
  const io67 = attrs['io67'] ?? attrs['67'];
  const io68 = attrs['io68'] ?? attrs['68'];
  const io69 = attrs['io69'] ?? attrs['69'];
  // Additional Teltonika analog channels sometimes mapped for fuel
  const io240 = attrs['io240'] ?? attrs['240'];
  const io241 = attrs['io241'] ?? attrs['241'];
  const io242 = attrs['io242'] ?? attrs['242'];
  const io243 = attrs['io243'] ?? attrs['243'];

  const percentKeyName = null;
  const litersKeyName = null;
  const analogKeyName = null;

  // Resolve percent
  let percent = null;
  let percentKeyUsed = null;
  {
    const candidates = [
      { key: 'fuelPercent', val: percentFuelPercent },
      { key: 'fuelLevel', val: percentFuelLevel },
      { key: 'fuel_percent', val: percentFuelPercentage },
      { key: 'io89', val: io89 },
      { key: 'io48', val: io48 },
    ];
    for (const c of candidates) {
      const n = num(c.val);
      if (n != null) { percent = Math.max(0, Math.min(100, Math.round(n))); percentKeyUsed = c.key; break; }
    }
  }
  if (percent == null) {
    // fallthrough remains null
  }

  // Resolve liters
  let liters = null;
  let litersKeyUsed = null;
  let litersWasMinusOne = false;
  {
    const candidates = [
      { key: 'fuelLiter', val: litersFuelLiter },
      { key: 'fuelLiters', val: litersFuelLiters },
      { key: 'fuel', val: litersFuel },
      { key: 'io84', val: io84 },
    ];
    for (const c of candidates) {
      const n = num(c.val);
      if (n != null) { liters = Math.round(n * 10) / 10; litersKeyUsed = c.key; break; }
    }
  }
  if (liters == null) {
    // fallthrough remains null
  }

  if (liters === -1) { litersWasMinusOne = true; liters = null; litersKeyUsed = null; }

  // Raw analog fallback
  let raw = null;
  let rawKeyUsed = null;
  // ignore analog configured key name
  if (raw == null) {
    const rawCandidates = [
      { key: 'io67', val: io67 },
      { key: 'io68', val: io68 },
      { key: 'io69', val: io69 },
      { key: 'io240', val: io240 },
      { key: 'io241', val: io241 },
      { key: 'io242', val: io242 },
      { key: 'io243', val: io243 },
      { key: 'fuelRaw', val: lower['fuelraw'] ?? attrs['fuelRaw'] },
      { key: 'analog1', val: lower['analog1'] },
      { key: 'analog2', val: lower['analog2'] },
      { key: 'analog3', val: lower['analog3'] },
      { key: 'adc1', val: lower['adc1'] },
      { key: 'adc2', val: lower['adc2'] },
      { key: 'adc3', val: lower['adc3'] },
    ];
    for (const c of rawCandidates) {
      const n = typeof c.val === 'string' ? parseFloat(c.val) : (typeof c.val === 'number' ? c.val : null);
      if (Number.isFinite(n)) { raw = n; rawKeyUsed = c.key; break; }
    }
  }

  // Optional calibration for analog: empty/full/scale/offset
  const emptyVal = num(lower['fuelanalogempty'] ?? lower['fuel_empty'] ?? lower['analog_empty']);
  const fullVal = num(lower['fuelanalogfull'] ?? lower['fuel_full'] ?? lower['analog_full']);
  const scale = num(lower['fuelanalogscale'] ?? lower['analog_scale']) ?? 1;
  const offset = num(lower['fuelanalogoffset'] ?? lower['analog_offset']) ?? 0;
  let percentFromAnalog = false;
  if (raw != null) {
    const adjusted = raw * scale + offset;
    if (percent == null && emptyVal != null && fullVal != null && fullVal > emptyVal) {
      const p = Math.round(((adjusted - emptyVal) / (fullVal - emptyVal)) * 100);
      percent = Math.max(0, Math.min(100, p));
      percentFromAnalog = true;
    } else if (percent == null && adjusted >= 0 && adjusted <= 100) {
      percent = Math.round(adjusted);
      percentFromAnalog = true;
    }
  }

  // Generic heuristic: scan any numeric attribute whose key includes 'fuel'
  if (raw == null) {
    for (const [k, v] of Object.entries(lower)) {
      if (!k.includes('fuel')) continue;
      const n = typeof v === 'string' ? parseFloat(v) : (typeof v === 'number' ? v : null);
      if (Number.isFinite(n)) { raw = n; rawKeyUsed = k; break; }
    }
  }

  // Normalize ignition value from attributes (affects percent=0 handling)
  let ignition = null;
  try {
    const ignRaw = lower['ignition'] ?? attrs['Ignition'];
    if (ignRaw !== undefined && ignRaw !== null) {
      const s = String(ignRaw).toLowerCase();
      if (ignRaw === true || ignRaw === 1 || s === 'on' || s === 'true' || s === '1') ignition = true;
      else if (ignRaw === false || ignRaw === 0 || s === 'off' || s === 'false' || s === '0') ignition = false;
    }
  } catch {}

  // Heuristic: treat percent=0 as unknown when ignition is OFF and no liters present
  if (percent === 0 && liters == null) {
    if (ignition === false) {
      percent = null;
    } else if (raw != null && raw > 0 && raw <= 100) {
      percent = Math.round(raw);
    }
  }

  // If capacity provided and percent available, prefer computed liters
  let computedLiters = null;
  if (Number.isFinite(parseFloat(capacity)) && percent != null) {
    const cap = parseFloat(capacity);
    if (!litersWasMinusOne || percent > 0) {
      computedLiters = Math.round((cap * percent / 100) * 10) / 10;
    }
  }

  const badgeVariant = percent == null ? null : (percent >= 60 ? 'success' : (percent >= 30 ? 'warning' : 'danger'));
  const finalLiters = computedLiters != null ? computedLiters : liters;
  let display = null;
  let source = null;
  let keyUsed = null;
  if (finalLiters != null) {
    display = `${finalLiters} L`;
    source = 'liters';
    keyUsed = litersKeyUsed ?? (percent != null ? (percentFromAnalog ? rawKeyUsed : percentKeyUsed) : rawKeyUsed);
  } else if (percent != null) {
    display = `${percent}%`;
    source = percentFromAnalog ? 'analog-percent' : 'percent';
    keyUsed = percentFromAnalog ? rawKeyUsed : percentKeyUsed;
  } else if (raw != null) {
    const looksPercent = raw >= 0 && raw <= 100;
    display = looksPercent ? `${Math.round(raw)}%` : String(raw);
    source = looksPercent ? 'analog-percent' : 'analog-raw';
    keyUsed = rawKeyUsed;
  }
  if (display == null) return null;
  return {
    isPercent: percent != null && finalLiters == null,
    percent,
    liters: finalLiters,
    capacity: Number.isFinite(parseFloat(capacity)) ? parseFloat(capacity) : null,
    variant: badgeVariant,
    raw,
    key: keyUsed,
    source,
    display,
  };
}

export function formatTelemetry(rawAttrs, ctx = {}) {
  const odo = formatOdometer(rawAttrs, ctx);
  const fuel = formatFuel(rawAttrs, ctx);
  return { odometer: odo, fuel };
}
