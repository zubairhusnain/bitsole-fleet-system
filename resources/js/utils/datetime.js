export function formatDateTime(date) {
    if (!date) return '-';
    try {
        let d;
        // Handle SQL timestamps (often UTC but missing Z)
        // Matches "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD HH:MM:SS.sss"
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(date)) {
            // Replace space with T to comply with ISO format and append Z for UTC
            d = new Date(date.replace(' ', 'T') + 'Z');
        } else {
            d = new Date(date);
        }

        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        // Default to browser timezone if not configured or set to generic UTC
        let timeZone = window.AppConfig?.timezone;
        if (!timeZone || timeZone === 'UTC') {
            timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }

        // Format: DD/MM/YYYY HH:MM:SS
        return d.toLocaleString('en-GB', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        }).replace(',', '');
    } catch (e) {
        return String(date);
    }
}

export function formatDate(date) {
    if (!date) return '-';
    try {
        let d;
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(date)) {
            d = new Date(date.replace(' ', 'T') + 'Z');
        } else {
            d = new Date(date);
        }

        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        let timeZone = window.AppConfig?.timezone;
        if (!timeZone || timeZone === 'UTC') {
            timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }

        // Format: DD/MM/YYYY
        return d.toLocaleDateString('en-GB', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch (e) {
        return String(date);
    }
}

export function formatTime(date) {
    if (!date) return '-';
    try {
        let d;
        if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(date)) {
            d = new Date(date.replace(' ', 'T') + 'Z');
        } else {
            d = new Date(date);
        }

        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        let timeZone = window.AppConfig?.timezone;
        if (!timeZone || timeZone === 'UTC') {
            timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }

        // Format: HH:MM:SS
        return d.toLocaleTimeString('en-GB', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    } catch (e) {
        return String(date);
    }
}
