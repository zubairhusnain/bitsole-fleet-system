export function formatDateTime(date) {
    if (!date) return '-';
    try {
        let d = new Date(date);
        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        const timeZone = window.AppConfig?.timezone || 'UTC';
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
        let d = new Date(date);
        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        const timeZone = window.AppConfig?.timezone || 'UTC';
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
        let d = new Date(date);
        if (isNaN(d.getTime())) {
            const n = Number(date);
            if (Number.isFinite(n)) d = new Date(n);
        }

        const timeZone = window.AppConfig?.timezone || 'UTC';
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
