<template>
  <div class="auth-wrapper">
    <div class="container">
      <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-md-10 col-lg-7 d-flex justify-content-center">
          <div class="auth-card card w-100" style="max-width: 560px;">
            <div class="card-body">
              <div v-if="submitting" class="loading-overlay">
                <div class="spinner-border" role="status"><span class="visually-hidden">Processing…</span></div>
              </div>
              <div class="text-center mb-3">
                <img :src="logoSrc" alt="Logo" class="auth-logo" />
              </div>
              <div class="auth-header text-center mb-4">
                <h4 class="mb-1">Register New Account</h4>
              </div>
              <form @submit.prevent="submit">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <label class="form-label">First Name</label>
                    <input v-model="form.firstName" type="text" class="form-control" placeholder="First Name" required :disabled="submitting" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Last Name</label>
                    <input v-model="form.lastName" type="text" class="form-control" placeholder="Last Name" required :disabled="submitting" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Email Address</label>
                    <input v-model="form.email" type="email" class="form-control" placeholder="Email Address" required :disabled="submitting" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input v-model="form.phone" type="tel" class="form-control" placeholder="Phone Number" :disabled="submitting" />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Password</label>
                    <div class="position-relative">
                      <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="form-control pe-5" placeholder="Password" required :disabled="submitting" />
                      <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showPassword = !showPassword" tabindex="-1">
                        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Confirm Password</label>
                    <div class="position-relative">
                      <input v-model="form.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" class="form-control pe-5" placeholder="Confirm Password" required :disabled="submitting" />
                      <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1">
                        <i class="bi" :class="showConfirmPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                      </button>
                    </div>
                  </div>
                </div>
                <div class="form-check mt-3 mb-3">
                  <input class="form-check-input" type="checkbox" id="agree" required :disabled="submitting" />
                  <label class="form-check-label text-muted" for="agree">By clicking, you agree with Terms &amp; Conditions.</label>
                </div>
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary" :disabled="submitting">
                    <span v-if="submitting" class="d-inline-flex align-items-center">
                      <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                      Registering…
                    </span>
                    <span v-else>Register</span>
                  </button>
                </div>
              </form>
              <div class="mt-3 text-center auth-footer">
                <span class="text-muted small">Already Have an Account?</span>
                <RouterLink to="/login" class="small ms-1">Login Now</RouterLink>
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
const logoSrc = assetBase + '/images/login-page-logo.png';

const router = useRouter();
const appName = document.title || 'Omayer Fleet System';
const error = ref('');
const submitting = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const form = reactive({ firstName: '', lastName: '', email: '', phone: '', password: '', password_confirmation: '' });

async function submit() {
  error.value = '';
  submitting.value = true;
  const payload = { name: `${form.firstName} ${form.lastName}`.trim(), email: form.email, password: form.password, password_confirmation: form.password_confirmation };
  try {
    const { data } = await axios.post('/web/auth/register', payload);
    setAuthenticatedUser(data?.user);
    // Immediately refresh CSRF to avoid first POST mismatch after session regeneration
    await refreshCsrf();
    router.push('/');
  } catch (e) {
    error.value = e?.response?.data?.message || 'Registration failed';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.auth-card { max-width: 560px; }
.auth-logo { display: block; margin: 0 auto; height: 32px; }
.auth-card { position: relative; }
.loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 1050; }
</style>
