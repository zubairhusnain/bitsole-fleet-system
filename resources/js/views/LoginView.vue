<template>
  <div class="auth-wrapper">
    <div class="container">
      <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-md-10 col-lg-7 d-flex justify-content-center">
          <div class="auth-card card">
            <div class="card-body">
              <div class="text-center mb-3">
                <img :src="logoSrc" alt="Logo" class="auth-logo" />
              </div>
              <div class="auth-header text-center mb-4">
                <h4 class="mb-1">Login to your account</h4>
              </div>
              <form @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">Email/Phone Number</label>
                  <input v-model="form.email" type="text" class="form-control" placeholder="Email/Phone Number" required />
                </div>
                <div class="mb-1">
                  <label class="form-label mb-2">Password</label>
                  <input v-model="form.password" type="password" class="form-control" placeholder="Password" required />
                </div>
                <div class="auth-actions">
                  <div class="auth-remember">
                    <input v-model="form.remember" class="form-check-input" type="checkbox" id="remember" />
                    <label class="form-check-label text-muted" for="remember">Remember me</label>
                  </div>
                  <RouterLink to="/forgot-password" class="small forgot-link">Forget Password</RouterLink>
                </div>
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Login</button>
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
import { setAuthenticatedUser, refreshCsrf } from '../auth';

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
const logoSrc = assetBase + '/images/logo.png';

const router = useRouter();
const appName = document.title || 'Omayer Fleet System';
const error = ref('');
const form = reactive({ email: '', password: '', remember: false });

async function submit() {
  error.value = '';
  try {
    const { data } = await axios.post('/web/auth/login', form);
    // Update auth state so guards and UI reflect logged-in status
    setAuthenticatedUser(data?.user);
    // Immediately refresh CSRF to avoid first POST mismatch after session regeneration
    await refreshCsrf();
    // Redirect to intended route or Live Tracking by default
    const redirect = router.currentRoute.value.query?.redirect;
    router.push(redirect || '/live-tracking');
  } catch (e) {
    error.value = e?.response?.data?.message || 'Login failed';
  }
}
</script>

<style scoped>
.forgot-link { color: #E74C3C; }
.auth-logo { display: block; margin: 0 auto; height: 32px; }
</style>