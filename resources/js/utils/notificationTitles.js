export const NOTIFICATION_TITLE_MAP = {
    geofenceEnter: 'Geofence Entered',
    geofenceExit: 'Geofence Exited',
    deviceOverspeed: 'Speed Limit Exceeded',
    alarm: 'Alarm Triggered',
    ignitionOn: 'Engine Started',
    ignitionOff: 'Engine Stopped',
    sos: 'SOS Alert',
    powerCut: 'Power Disconnected',
    lowBattery: 'Low Battery Warning',
    motion: 'Motion Detected',
    deviceOnline: 'Device Online',
    deviceOffline: 'Device Offline',
    deviceUnknown: 'Device Status Unknown',
    deviceMoving: 'Vehicle Moving',
    deviceStopped: 'Vehicle Stopped',
    deviceInactive: 'Vehicle Inactive',
    maintenance: 'Maintenance Required',
    textMessage: 'Text Message Received',
    driverChanged: 'Driver Changed',
    frequentIgnition: 'Frequent Ignition',
    lowFuelWarning: 'Low Fuel Warning',
    lowFuelCritical: 'Critical Fuel Level',
    commandResult: 'Command Result',
    queuedCommandSent: 'Queued Command Sent',
};

const HUMAN_STATUS_ALIASES = {
    'device online': 'Device Online',
    'device offline': 'Device Offline',
    'device unknown': 'Device Status Unknown',
    'device status unknown': 'Device Status Unknown',
    'vehicle online': 'Device Online',
    'vehicle offline': 'Device Offline',
    'vehicle unknown': 'Device Status Unknown',
    'vehicle status unknown': 'Device Status Unknown',
    'device moving': 'Vehicle Moving',
    'device stopped': 'Vehicle Stopped',
    'device inactive': 'Vehicle Inactive',
};

function normalizeTypeKey(type) {
    return String(type ?? '').trim().toLowerCase().replace(/[\s_-]+/g, '');
}

export function formatNotificationTitle(type) {
    if (!type) return '';

    const raw = String(type).trim();
    if (NOTIFICATION_TITLE_MAP[raw]) return NOTIFICATION_TITLE_MAP[raw];

    const lower = raw.toLowerCase();
    if (HUMAN_STATUS_ALIASES[lower]) return HUMAN_STATUS_ALIASES[lower];

    const normalized = normalizeTypeKey(raw);
    const camelKey = Object.keys(NOTIFICATION_TITLE_MAP).find(
        (key) => normalizeTypeKey(key) === normalized
    );
    if (camelKey) return NOTIFICATION_TITLE_MAP[camelKey];

    return raw
        .replace(/([A-Z])/g, ' $1')
        .replace(/_/g, ' ')
        .replace(/^./, (str) => str.toUpperCase())
        .trim()
        .replace(/\bDevice\b/gi, 'Vehicle');
}

export function parseEventAttributes(event) {
    if (!event?.attributes) return {};
    try {
        const attrs = typeof event.attributes === 'string'
            ? JSON.parse(event.attributes)
            : event.attributes;
        return attrs && typeof attrs === 'object' ? attrs : {};
    } catch {
        return {};
    }
}

/** Alert text: formatted type title, optionally with alarm and/or attributes.message appended. */
export function formatAlertMessage(event) {
    const type = event?.type ?? event?.notification_type ?? event?.notificationType ?? '';
    const attrs = parseEventAttributes(event);
    let msg = formatNotificationTitle(type);

    if (attrs.alarm) {
        msg += ` — ${formatNotificationTitle(attrs.alarm)}`;
    }
    if (attrs.message) {
        msg += ` — ${String(attrs.message).trim()}`;
    }
    if (attrs.result) {
        msg += ` — ${String(attrs.result).trim()}`;
    }

    return msg;
}
