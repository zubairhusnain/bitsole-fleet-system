<template>
  <div class="add-user-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/users">User Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Add {{ createTargetLabel }}</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Add {{ createTargetLabel }}</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <form @submit.prevent="submit">
      <!-- Account Information -->
      <div class="card mb-3">
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
                <label class="form-label small">Password</label>
                <input v-model="form.password" type="password" class="form-control" placeholder="Minimum 8 characters" />
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label small">Confirm Password</label>
                <input v-model="form.password_confirmation" type="password" class="form-control" placeholder="Confirm Password" />
              </div>
            </div>
        </div>
      </div>

      <!-- Role selection removed; role is auto-assigned on server based on creator -->

      <!-- Actions -->
      <div class="d-flex align-items-center justify-content-end gap-2">
        <RouterLink to="/users" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark" :disabled="submitting">{{ submitting ? 'Adding…' : ('Add ' + createTargetLabel) }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import { authState } from '../../auth';

const router = useRouter();

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
});

const message = ref('');
const error = ref('');
const submitting = ref(false);

// Role selection removed; options no longer needed.

const currentRole = computed(() => Number(authState?.user?.role ?? 0));
const createTargetLabel = computed(() => {
  switch (currentRole.value) {
    case 3: return 'Distributor';
    case 2: return 'Fleet Manager';
    case 1: return 'Fleet Viewer';
    default: return 'User';
  }
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
      password: form.password,
      password_confirmation: form.password_confirmation,
    };

    const { data } = await axios.post('/web/users', payload);
    message.value = data?.message || 'User created';
    setTimeout(() => router.push('/users'), 300);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to add user';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
