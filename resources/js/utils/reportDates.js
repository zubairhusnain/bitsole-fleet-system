/**
 * Default report date helpers — current day only (avoids wide-range timeouts).
 */

function pad(n) {
    return n.toString().padStart(2, '0');
}

/** YYYY-MM-DD for <input type="date"> */
export function todayDateString(date = new Date()) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** YYYY-MM-DDTHH:mm for <input type="datetime-local"> */
export function toDatetimeLocalValue(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function todayStartDatetimeLocal() {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    return toDatetimeLocalValue(start);
}

export function todayEndDatetimeLocal() {
    const end = new Date();
    end.setHours(23, 59, 0, 0);
    return toDatetimeLocalValue(end);
}

export function initTodayDateRange() {
    const today = todayDateString();
    return { fromDate: today, toDate: today };
}

export function initTodayDatetimeRange() {
    return {
        startDate: todayStartDatetimeLocal(),
        endDate: todayEndDatetimeLocal(),
    };
}

/** Last 24 hours rolling window for datetime-local inputs */
export function defaultLast24HoursDatetimeRange() {
    const to = new Date();
    const from = new Date(to.getTime() - 24 * 60 * 60 * 1000);
    return {
        dateFrom: toDatetimeLocalValue(from),
        dateTo: toDatetimeLocalValue(to),
    };
}

/** Last 2 calendar days inclusive: yesterday → today */
export function defaultTwoDayDateRange() {
    const to = todayDateString();
    const fromDate = new Date();
    fromDate.setDate(fromDate.getDate() - 1);
    return {
        dateFrom: todayDateString(fromDate),
        dateTo: to,
    };
}
