<template>
  <div class="auth-wrapper">
    <div class="container">
      <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-md-10 col-lg-7 d-flex justify-content-center">
          <div class="auth-card card">
            <div class="card-body">
              <div v-if="submitting" class="loading-overlay">
                <div class="spinner-border" role="status"><span class="visually-hidden">Processing…</span></div>
              </div>
              <div class="text-center mb-3">
                <img :src="logoSrc" alt="Logo" class="auth-logo" />
              </div>
              <div class="auth-header text-center mb-4">
                <h4 class="mb-1">Login to your account</h4>
              </div>
              <form @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">Email/Phone Number</label>
                  <input v-model="form.email" type="text" class="form-control" placeholder="Email/Phone Number" required :disabled="submitting" />
                </div>
                <div class="mb-1">
                  <label class="form-label mb-2">Password</label>
                  <div class="position-relative">
                    <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="form-control pe-5" placeholder="Password" required :disabled="submitting" />
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showPassword = !showPassword" tabindex="-1">
                      <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                  </div>
                </div>
                <div class="auth-actions">
                  <div class="auth-remember">
                    <input v-model="form.remember" class="form-check-input" type="checkbox" id="remember" :disabled="submitting" />
                    <label class="form-check-label text-muted" for="remember">Remember me</label>
                  </div>
                  <RouterLink to="/forgot-password" class="small forgot-link">Forget Password</RouterLink>
                </div>
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary" :disabled="submitting">
                    <span v-if="submitting" class="d-inline-flex align-items-center">
                      <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                      Logging in…
                    </span>
                    <span v-else>Login</span>
                  </button>
                </div>
              </form>
              <div class="mt-3 text-center auth-footer">
                <span class="text-muted small">Don’t have any Account?</span>
                <RouterLink to="/register" class="small ms-1">Register Now</RouterLink>
              </div>
              <p v-if="error" class="text-danger mt-3 mb-0">{{ error }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { setAuthenticatedUser, refreshCsrf, clearAuthCache, getCurrentUser } from '../auth';

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
const logoSrc = assetBase + '/images/login-page-logo.png';

const router = useRouter();
const appName = document.title || 'Omayer Fleet System';
const error = ref('');
const submitting = ref(false);
const showPassword = ref(false);
const form = reactive({ email: '', password: '', remember: false });

async function submit() {
  error.value = '';
  submitting.value = true;
  try {
    const { data } = await axios.post('/web/auth/login', form);
    // Update auth state so guards and UI reflect logged-in status
    setAuthenticatedUser(data?.user);
    // Immediately refresh CSRF to avoid first POST mismatch after session regeneration
    await refreshCsrf();
    await clearAuthCache();
    await getCurrentUser();
    window.location.reload();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Login failed';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.forgot-link { color: #E74C3C; }
.auth-logo { display: block; margin: 0 auto; height: 32px; }
.auth-card { position: relative; }
.loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 1050; }
</style>
