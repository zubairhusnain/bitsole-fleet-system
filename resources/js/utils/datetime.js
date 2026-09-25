const TZ_STORAGE_KEY = 'app.timezone';

function getStoredTimezone() {
    if (typeof window === 'undefined') return null;
    try {
        const v = window.localStorage ? window.localStorage.getItem(TZ_STORAGE_KEY) : null;
        if (!v || v === 'null' || v === 'undefined') return null;
        return v;
    } catch {
        return null;
    }
}

export function setTimezonePreference(tz) {
    if (typeof window === 'undefined') return;
    try {
        if (!tz) {
            window.localStorage.removeItem(TZ_STORAGE_KEY);
        } else {
            window.localStorage.setItem(TZ_STORAGE_KEY, tz);
        }
    } catch {}
}

export function getActiveTimezone() {
    const stored = getStoredTimezone();
    if (stored) return stored;

    // Prioritize browser timezone if available
    try {
        const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (browserTz) return browserTz;
    } catch {}

    let timeZone = null;
    try {
        if (typeof window !== 'undefined' && window.AppConfig && window.AppConfig.timezone) {
            timeZone = window.AppConfig.timezone;
        }
    } catch {}

    if (!timeZone) {
        return 'UTC';
    }
    return timeZone;
}

function parseDateInput(date) {
    if (!date) return null;
    let d;
    if (typeof date === 'string') {
        if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/.test(date)) {
            if (/[Z\+\-]\d{2}:?\d{2}$/.test(date) || date.endsWith('Z')) {
                d = new Date(date);
            } else {
                d = new Date(date.replace(' ', 'T') + 'Z');
            }
        } else {
            d = new Date(date);
        }
    } else {
        d = new Date(date);
    }

    if (isNaN(d.getTime())) {
        const n = Number(date);
        if (Number.isFinite(n)) d = new Date(n);
    }

    return isNaN(d.getTime()) ? null : d;
}

/** YYYY-MM-DD calendar date in the user's active timezone (for date-range filters). */
export function calendarDateKeyInActiveTz(date) {
    const d = parseDateInput(date);
    if (!d) return '';
    return d.toLocaleDateString('en-CA', { timeZone: getActiveTimezone() });
}

export function formatDateTime(date, options = {}) {
    if (!date) return '-';
    try {
        const d = parseDateInput(date);
        if (!d) return '-';

        const timeZone = getActiveTimezone();
        const hour12 = options.hour12 ?? false;

        let formatted = d.toLocaleString('en-GB', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            ...(hour12 ? {} : { second: '2-digit' }),
            hour12,
        }).replace(',', '');
        if (hour12) {
            formatted = formatted.replace(/\b(am|pm)\b/gi, (m) => m.toUpperCase());
        }
        return formatted;
    } catch (e) {
        return '-';
    }
}

export function formatDate(date) {
    if (!date) return '-';
    try {
        let d;
        if (typeof date === 'string') {
            if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/.test(date)) {
                if (/[Z\+\-]\d{2}:?\d{2}$/.test(date) || date.endsWith('Z')) {
                    d = new Date(date);
                } else {
                    d = new Date(date.replace(' ', 'T') + 'Z');
                }
            } else {
                d = new Date(date);
            }
        } else {
            d = new Date(date);
        }

        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        if (isNaN(d.getTime())) {
            return '-';
        }

        const timeZone = getActiveTimezone();

        // Format: DD/MM/YYYY
        return d.toLocaleDateString('en-GB', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch (e) {
        return '-';
    }
}

export function formatTime(date) {
    if (!date) return '-';
    try {
        let d;
        if (typeof date === 'string') {
            if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/.test(date)) {
                if (/[Z\+\-]\d{2}:?\d{2}$/.test(date) || date.endsWith('Z')) {
                    d = new Date(date);
                } else {
                    d = new Date(date.replace(' ', 'T') + 'Z');
                }
            } else {
                d = new Date(date);
            }
        } else {
            d = new Date(date);
        }

        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        if (isNaN(d.getTime())) {
            return '-';
        }

        const timeZone = getActiveTimezone();

        // Format: HH:MM:SS
        return d.toLocaleTimeString('en-GB', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    } catch (e) {
        return '-';
    }
}
