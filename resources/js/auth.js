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