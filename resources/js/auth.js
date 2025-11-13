import axios from 'axios';
import { reactive } from 'vue';

export const authState = reactive({ user: null, permissions: {}, fetched: false });

export async function getCurrentUser() {
  if (authState.fetched) return authState.user;
  try {
    const { data } = await axios.get('/web/auth/me');
    authState.user = data?.user ?? null;
    authState.permissions = data?.permissions ?? {};
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

export function hasPermission(moduleKey, action) {
  const perms = authState.permissions || {};
  const mod = perms[moduleKey] || {};
  const role = Number(authState?.user?.role ?? 0);
  if (role === 3 || role === 2) return true;
  if (!action) return !!mod.read;
  switch (action) {
    case 'read': return !!mod.read;
    case 'create': return !!mod.create;
    case 'update': return !!mod.update;
    case 'delete': return !!mod.delete;
    default: return false;
  }
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
