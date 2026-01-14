import axios from 'axios';
window.axios = axios;

// Core defaults
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Use Laravel's standard XSRF cookie/header pairing
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Backend base URL is only used in production; in dev we rely on Vite proxy
const isDev = typeof import.meta !== 'undefined' && import.meta.env?.DEV;
const BACKEND_URL = !isDev && (typeof import.meta !== 'undefined' && import.meta.env?.VITE_BACKEND_URL) ? import.meta.env.VITE_BACKEND_URL : '';
const joinBackend = (path) => (BACKEND_URL ? new URL(path, BACKEND_URL).toString() : path);
if (BACKEND_URL) {
  window.axios.defaults.baseURL = BACKEND_URL;
}

// Seed CSRF header from meta tag or fallback endpoint
const setCsrfHeader = (token) => {
  if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
  }
};

const meta = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]') : null;
if (meta && meta.content) {
  setCsrfHeader(meta.content);
} else {
  window.axios.get(joinBackend('/web/csrf-token'))
    .then((res) => setCsrfHeader(res?.data?.csrfToken))
    .catch(() => {});
}

// Auto-refresh CSRF header on 419 (Page Expired) and retry once
window.axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status;
    const config = error?.config || {};
    if (status === 419 && !config.__retried) {
      try {
        const res = await window.axios.get(joinBackend('/web/csrf-token'));
        setCsrfHeader(res?.data?.csrfToken);
        config.__retried = true;
        return window.axios(config);
      } catch (e) {
        // fall through to original error
      }
    }
    return Promise.reject(error);
  }
);

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getActiveTimezone } from './utils/datetime';

const originalDateToLocaleString = Date.prototype.toLocaleString;
const originalDateToLocaleDateString = Date.prototype.toLocaleDateString;
const originalDateToLocaleTimeString = Date.prototype.toLocaleTimeString;

Date.prototype.toLocaleString = function (locale, options) {
  try {
    const opts = options || {};
    if (!opts.timeZone) {
      const tz = getActiveTimezone();
      if (tz) {
        return originalDateToLocaleString.call(this, locale || undefined, { ...opts, timeZone: tz });
      }
    }
    return originalDateToLocaleString.call(this, locale || undefined, opts);
  } catch {
    return originalDateToLocaleString.call(this, locale, options);
  }
};

Date.prototype.toLocaleDateString = function (locale, options) {
  try {
    const opts = options || {};
    if (!opts.timeZone) {
      const tz = getActiveTimezone();
      if (tz) {
        return originalDateToLocaleDateString.call(this, locale || undefined, { ...opts, timeZone: tz });
      }
    }
    return originalDateToLocaleDateString.call(this, locale || undefined, opts);
  } catch {
    return originalDateToLocaleDateString.call(this, locale, options);
  }
};

Date.prototype.toLocaleTimeString = function (locale, options) {
  try {
    const opts = options || {};
    if (!opts.timeZone) {
      const tz = getActiveTimezone();
      if (tz) {
        return originalDateToLocaleTimeString.call(this, locale || undefined, { ...opts, timeZone: tz });
      }
    }
    return originalDateToLocaleTimeString.call(this, locale || undefined, opts);
  } catch {
    return originalDateToLocaleTimeString.call(this, locale, options);
  }
};

window.Pusher = Pusher;

const reverbKey = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_APP_KEY) || 'local';

// Dynamic Host and Scheme Detection for VPS/Production compatibility
const isSecure = typeof window !== 'undefined' && window.location.protocol === 'https:';
const currentHostname = typeof window !== 'undefined' ? window.location.hostname : 'localhost';

let envHost = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_HOST);
// If host is not set or is localhost, use current hostname (fixes VPS domain issues)
if (!envHost || envHost === 'localhost') {
    envHost = currentHostname;
}
const reverbHost = String(envHost).replace(/^"|"$/g, '');

let envPort = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_PORT);
let envScheme = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_SCHEME);

// If on HTTPS, force secure scheme
if (isSecure) {
    envScheme = 'https';
    // If port is default 8080, assume standard 443 for WSS via proxy
    if (!envPort || envPort == '8080') {
        envPort = '443';
    }
}

const reverbScheme = envScheme || 'http';
const defaultWsPort = reverbScheme === 'https' ? 443 : 80;
const reverbPort = envPort ? Number(envPort) : defaultWsPort;

window.echo = new Echo({
  broadcaster: 'pusher',
  key: reverbKey,
  wsHost: reverbHost,
  wsPort: reverbPort,
  wssPort: reverbPort,
  forceTLS: reverbScheme === 'https',
  enabledTransports: ['ws', 'wss'],
  disableStats: true,
  cluster: 'mt1'
});
