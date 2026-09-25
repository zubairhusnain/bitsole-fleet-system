/**
 * Replace third-party product branding in user-visible message strings only.
 * Do not use on arbitrary data objects, attribute names, or protocol values.
 */
export function sanitizeUserFacingMessage(message) {
  if (message == null || message === '') return String(message ?? '');

  const trimmed = String(message).trim();
  if (/^forbidden$/i.test(trimmed)) {
    return 'You do not have permission to access this feature.';
  }

  return trimmed
    .replaceAll('Traccar error', 'Tracking server error')
    .replaceAll('traccar error', 'tracking server error')
    .replaceAll('Traccar version', 'tracking server version')
    .replaceAll('Traccar', 'tracking server')
    .replaceAll('traccar', 'tracking server')
    .replaceAll('TRACCAR', 'TRACKING SERVER');
}

/**
 * Extract a safe user-facing message from an axios-style error object.
 */
export function apiErrorMessage(error, fallback = 'Request failed') {
  const status = error?.response?.status;
  const raw = error?.response?.data?.message ?? error?.message ?? '';
  let text = String(raw || '').trim();
  if (!text && status === 403) {
    text = 'You do not have permission to access this feature.';
  }
  return text ? sanitizeUserFacingMessage(text) : fallback;
}
