import axios from 'axios';
import { reactive } from 'vue';

export const authState = reactive({ user: null, fetched: false });

export async function getCurrentUser() {
  if (authState.fetched) return authState.user;
  try {
    const { data } = await axios.get('/web/auth/me');
    authState.user = data?.user ?? null;
  } catch (e) {
    authState.user = null;
  } finally {
    authState.fetched = true;
  }
  return authState.user;
}

export async function ensureAuthenticated() {
  const user = await getCurrentUser();
  return !!user;
}

export function clearAuthCache() {
  authState.user = null;
  authState.fetched = false;
}

export function setAuthenticatedUser(user) {
  authState.user = user ?? null;
  authState.fetched = true;
}

// Refresh CSRF cookie and header after auth changes to prevent first POST mismatch
export async function refreshCsrf() {
  try {
    // Seed XSRF-TOKEN cookie (if Sanctum is available)
    await axios.get('/sanctum/csrf-cookie');
  } catch (_) {}
  try {
    // Set X-CSRF-TOKEN header from backend helper
    const { data } = await axios.get('/web/csrf-token');
    if (data?.csrfToken) {
      axios.defaults.headers.common['X-CSRF-TOKEN'] = data.csrfToken;
    }
  } catch (_) {}
}