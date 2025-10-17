import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Include CSRF token from Blade meta for session-based auth
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}
// Ensure cookies are sent on same-origin requests
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['Accept'] = 'application/json';
