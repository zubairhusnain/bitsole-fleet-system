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

// Bind Pusher global for Echo
window.Pusher = Pusher;

// Configure Echo to connect to Reverb (Pusher protocol)
const reverbKey = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_APP_KEY) || 'local';
const envHost = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_HOST) || (typeof window !== 'undefined' ? window.location.hostname : 'localhost');
const reverbHost = String(envHost).replace(/^"|"$/g, '');
const reverbPort = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_PORT) ? Number(import.meta.env.VITE_REVERB_PORT) : 8080;
const reverbScheme = (typeof import.meta !== 'undefined' && import.meta.env?.VITE_REVERB_SCHEME) || 'http';

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
