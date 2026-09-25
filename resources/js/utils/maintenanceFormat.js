import { formatDate } from './datetime';

function num(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

function formatCount(n, singular, plural = `${singular}s`) {
    const abs = Math.abs(n);
    const label = abs === 1 ? singular : plural;
    return `${n.toLocaleString('en-US', { maximumFractionDigits: 0 })} ${label}`;
}

/** Traccar time maintenance stores start as days since Unix epoch. */
export function daysToDate(days) {
    const n = num(days);
    if (n == null || n <= 0) return null;
    const d = new Date(n * 86400000);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function formatMaintenanceStart(type, value) {
    if (value == null || value === '') return '—';

    switch (String(type || '').trim()) {
        case 'time': {
            const d = daysToDate(value);
            return d ? formatDate(d) : String(value);
        }
        case 'odometer':
        case 'totalDistance':
        case 'serviceOdometer':
        case 'obdOdometer':
            return formatOdometerValue(value);
        case 'hours':
            return formatHoursValue(value);
        case 'drivingTime':
            return formatDrivingTimeMinutes(value);
        default:
            return String(value);
    }
}

export function formatMaintenancePeriod(type, value) {
    if (value == null || value === '') return '—';

    switch (String(type || '').trim()) {
        case 'time':
            return formatTimePeriodDays(value);
        case 'odometer':
        case 'totalDistance':
        case 'serviceOdometer':
        case 'obdOdometer':
            return formatOdometerInterval(value);
        case 'hours':
            return formatHoursInterval(value);
        case 'drivingTime':
            return formatDrivingTimeInterval(value);
        default:
            return String(value);
    }
}

function formatOdometerValue(value) {
    const n = num(value);
    if (n == null) return String(value);
    return `${n.toLocaleString('en-US', { maximumFractionDigits: 0 })} km`;
}

function formatOdometerInterval(value) {
    const n = num(value);
    if (n == null) return String(value);
    return `Every ${n.toLocaleString('en-US', { maximumFractionDigits: 0 })} km`;
}

function formatHoursValue(value) {
    const n = num(value);
    if (n == null) return String(value);
    return formatCount(n, 'hour');
}

function formatHoursInterval(value) {
    const n = num(value);
    if (n == null) return String(value);
    return `Every ${formatCount(n, 'hour')}`;
}

function formatDrivingTimeMinutes(value) {
    const n = num(value);
    if (n == null) return String(value);
    if (n >= 60) {
        const h = Math.floor(n / 60);
        const m = Math.round(n % 60);
        if (m === 0) return formatCount(h, 'hour');
        return `${formatCount(h, 'hour')} ${formatCount(m, 'minute')}`;
    }
    return formatCount(n, 'minute');
}

function formatDrivingTimeInterval(value) {
    const n = num(value);
    if (n == null) return String(value);
    return `Every ${formatDrivingTimeMinutes(n)}`;
}

function formatTimePeriodDays(value) {
    const days = num(value);
    if (days == null) return String(value);

    if (days >= 365 && days % 365 === 0) {
        const years = days / 365;
        return years === 1 ? '1 year' : `${years} years`;
    }
    if (days >= 30 && days % 30 === 0) {
        const months = days / 30;
        return months === 1 ? '1 month' : `${months} months`;
    }

    return formatCount(days, 'day');
}
