import axios from 'axios';
import { reactive } from 'vue';

export const authState = reactive({ user: null, impersonator: null, permissions: {}, fetched: false });

export function roleToNumber(r) {
  if (typeof r === 'string') {
    const v = r.trim().toLowerCase();
    if (v === 'superadmin' || v === 'super admin' || v === 'super-admin') return 3;
    if (v === 'admin' || v === 'administrator') return 3;
    if (v === 'distributor') return 2;
    if (v === 'manager' || v === 'fleet manager') return 1;
    return 0;
  }
  const n = Number(r ?? 0);
  return Number.isNaN(n) ? 0 : n;
}

export async function getCurrentUser() {
  if (authState.fetched) return authState.user;
  try {
    const { data } = await axios.get('/web/auth/me');
    authState.user = data?.user ?? null;
    if (authState.user) authState.user.role = roleToNumber(authState.user.role);
    authState.permissions = data?.permissions ?? {};
    authState.impersonator = data?.impersonator ?? null;
  } catch (e) {
    authState.user = null;
    authState.impersonator = null;
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
  authState.impersonator = null;
  authState.fetched = false;
}

export function setAuthenticatedUser(user) {
  authState.user = user ?? null;
  if (authState.user) authState.user.role = roleToNumber(authState.user.role);
   authState.impersonator = null;
  authState.fetched = true;
}

export function hasPermission(moduleKey, action) {
  const perms = authState.permissions || {};
  const mod = perms[moduleKey] || {};
  const role = roleToNumber(authState?.user?.role ?? 0);
  if (role === 3 || role === 2) {
    return true;
  }
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
