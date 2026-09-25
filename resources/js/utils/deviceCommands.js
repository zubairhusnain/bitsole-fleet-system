/** Command types shown in the send-command form (matches Traccar device UI). */
export const DEVICE_COMMAND_TYPES = [
    'custom',
    'deviceIdentification',
    'positionSingle',
    'positionPeriodic',
    'positionStop',
    'engineStop',
    'engineResume',
    'alarmArm',
    'alarmDisarm',
];

export const COMMAND_TYPE_LABELS = {
    custom: 'Custom command',
    deviceIdentification: 'Device Identification',
    positionSingle: 'Single Reporting',
    positionPeriodic: 'Periodic Reporting',
    positionStop: 'Stop Reporting',
    engineStop: 'Stop Engine',
    engineResume: 'Start Engine',
    alarmArm: 'Lock Doors',
    alarmDisarm: 'Unlock Doors',
    immobilize: 'Immobilize Vehicle',
    unimmobilize: 'Unimmobilize Vehicle',
};

/** Extra fields per command type (Traccar attributes). */
export const COMMAND_TYPE_FIELDS = {
    custom: [
        { key: 'data', label: 'Data', type: 'string', required: true, placeholder: 'Enter custom command string…' },
    ],
    positionPeriodic: [
        { key: 'frequency', label: 'Frequency', type: 'number', required: true, placeholder: 'Seconds between reports', min: 0 },
    ],
};

export function normalizeCommandTypes(list) {
    return (Array.isArray(list) ? list : [])
        .map((item) => (typeof item === 'string' ? item : item?.type))
        .filter(Boolean);
}

/** Prefer known labels/order, but only types the device protocol supports. */
export function resolveCommandTypes(apiTypes) {
    const supported = new Set(normalizeCommandTypes(apiTypes));
    const ordered = DEVICE_COMMAND_TYPES.filter((type) => supported.has(type));
    if (ordered.length) return ordered;
    return normalizeCommandTypes(apiTypes);
}

export function formatCommandTypeLabel(type) {
    if (!type) return '';
    if (COMMAND_TYPE_LABELS[type]) return COMMAND_TYPE_LABELS[type];
    return String(type).replace(/([A-Z])/g, ' $1').replace(/^./, (s) => s.toUpperCase()).trim();
}

export function getCommandTypeFields(type) {
    return COMMAND_TYPE_FIELDS[type] || [];
}

export function getDefaultCommandAttributes(type) {
    const defaults = {};
    getCommandTypeFields(type).forEach((field) => {
        switch (field.type) {
            case 'boolean':
                defaults[field.key] = false;
                break;
            case 'number':
                defaults[field.key] = '';
                break;
            default:
                defaults[field.key] = '';
                break;
        }
    });
    return defaults;
}

export function isCommandFormValid(mode, form) {
    if (mode === 'saved') return !!form.savedId;
    if (!form.type) return false;

    return getCommandTypeFields(form.type).every((field) => {
        if (!field.required) return true;
        const value = form.attributes?.[field.key];
        if (field.type === 'number') return value !== '' && value !== null && !Number.isNaN(Number(value));
        return String(value ?? '').trim() !== '';
    });
}

export function buildCommandSendPayload(mode, form) {
    const payload = {};

    if (mode === 'saved') {
        payload.id = Number(form.savedId);
    } else {
        payload.type = form.type;
        const attributes = {};
        getCommandTypeFields(form.type).forEach((field) => {
            const raw = form.attributes?.[field.key];
            if (raw === '' || raw === null || raw === undefined) return;
            attributes[field.key] = field.type === 'number' ? Number(raw) : raw;
        });
        if (Object.keys(attributes).length) {
            payload.attributes = attributes;
        }
    }

    if (form.noQueue) {
        payload.no_queue = true;
    }

    return payload;
}
