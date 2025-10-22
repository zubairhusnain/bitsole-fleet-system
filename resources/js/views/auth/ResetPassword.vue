<template>
  <div class="auth-wrapper">
    <div class="container">
      <div class="row align-items-center min-vh-100">
        <div class="col-12 d-flex justify-content-center">
          <div class="auth-card card">
            <div class="card-body">
              <div class="text-center mb-3">
                <img :src="logoSrc" alt="Logo" class="auth-logo" />
              </div>
               <div class="auth-header text-center mb-4">
                <h4 class="mb-1">Reset Password</h4>
                <p class="text-muted">Choose a new password for your account</p>
              </div>
              <form @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">New Password</label>
                  <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">Confirm Password</label>
                  <input v-model="form.password_confirmation" type="password" class="form-control" placeholder="••••••••" required />
                </div>
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
              </form>
              <div class="mt-3 d-flex justify-content-between">
                <RouterLink to="/login" class="small">Back to login</RouterLink>
                <RouterLink to="/register" class="small">Create account</RouterLink>
              </div>
              <p v-if="message" class="text-success mt-3 mb-0">{{ message }}</p>
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
import axios from 'axios';

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
const logoSrc = assetBase + '/images/logo.png';

const form = reactive({ email: '', password: '', password_confirmation: '' });
const message = ref('');
const error = ref('');

async function submit() {
  message.value = '';
  error.value = '';
  try {
    await axios.post('/web/auth/password/reset', form);
    message.value = 'Your password has been updated.';
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to reset password';
  }
}
</script>

<style scoped>
.auth-card { max-width: 480px; width: 100%; border-radius: var(--radius-card); box-shadow: var(--shadow-card); }
.auth-logo { display: block; margin: 0 auto; height: 32px; }
</style>