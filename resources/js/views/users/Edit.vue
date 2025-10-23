<template>
  <div class="edit-user-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/users">User Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Edit User</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Edit User</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <form @submit.prevent="submit">
      <div v-if="loading" class="d-flex align-items-center mb-2">
        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading…</span></div>
        <span class="text-muted small">Loading user…</span>
      </div>

      <!-- Account Information -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Account Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label small">First Name</label>
              <input v-model="form.firstName" type="text" class="form-control" placeholder="First Name" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Last Name</label>
              <input v-model="form.lastName" type="text" class="form-control" placeholder="Last Name" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Email Address</label>
              <input v-model="form.email" type="email" class="form-control" placeholder="Email Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Phone Number</label>
              <input v-model="form.phone" type="tel" class="form-control" placeholder="Phone Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">New Password</label>
              <input v-model="form.password" type="password" class="form-control" placeholder="Leave blank to keep" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Confirm Password</label>
              <input v-model="form.password_confirmation" type="password" class="form-control" placeholder="Confirm Password" />
            </div>
          </div>
        </div>
      </div>

      <!-- Role selection removed on Edit; roles are not editable here -->

      <!-- Actions -->
      <div class="d-flex align-items-center justify-content-end gap-2">
        <RouterLink to="/users" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save Changes' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const route = useRoute();
const router = useRouter();
const userId = Number(route.params.userId);

const form = reactive({
  id: null,
  firstName: "",
  lastName: "",
  email: "",
  phone: "",
  password: "",
  password_confirmation: "",
});

const message = ref('');
const error = ref('');
const loading = ref(false);
const submitting = ref(false);

// Role/distributor options removed; roles are not editable here

async function fetchUser() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/web/users/${userId}`);
    const [firstName, ...rest] = (data?.name || '').split(' ');
    const lastName = rest.join(' ');
    form.id = data?.id ?? null;
    form.firstName = firstName || '';
    form.lastName = lastName || '';
    form.email = data?.email || '';
    form.phone = data?.phone || '';
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to load user';
    error.value = msg;
    setTimeout(() => router.replace({ path: '/users', query: { error: msg } }), 300);
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await fetchUser();
});

async function submit() {
  message.value = '';
  error.value = '';
  submitting.value = true;
  try {
    const payload = {
      name: `${form.firstName} ${form.lastName}`.trim(),
      email: form.email,
      phone: form.phone,
    };
    if (form.password) {
      payload.password = form.password;
      payload.password_confirmation = form.password_confirmation;
    }

    const { data } = await axios.put(`/web/users/${userId}`, payload);
    message.value = data?.message || 'User updated';
    setTimeout(() => router.push('/users'), 400);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save changes';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>