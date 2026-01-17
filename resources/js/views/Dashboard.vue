<template>
  <div class="content-wrapper">
    <div class="page-header mb-3">
      <h1 class="h4 mb-1">Dashboard</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><RouterLink to="/">Home</RouterLink></li>
          <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        </ol>
      </nav>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <div v-if="loading" class="text-muted small mb-2">Loading…</div>

    <div v-else>
      <!-- Admin View -->
      <div v-if="isAdmin" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Distributors</h6></div>
        <div class="card-body">
          <div v-if="distributors.length === 0" class="text-muted small">No distributors found.</div>
          <div class="accordion" id="distAccordion">
            <div class="accordion-item" v-for="d in distributors" :key="'dist-'+d.id">
              <h2 class="accordion-header">
                <div class="accordion-button collapsed" type="button" :class="{ collapsed: !expandedDist.has(d.id) }">
                  <div class="d-flex align-items-center flex-grow-1" @click="toggleDist(d.id)">
                    <span class="fw-semibold me-2">{{ d.name }}</span>
                    <span class="text-muted small me-auto">{{ d.email }}</span>
                  </div>
                  <button class="btn btn-sm btn-outline-primary ms-2 me-3" @click.stop="loginAs(d)">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login as
                  </button>
                  <i class="bi bi-chevron-down" :style="{ transform: expandedDist.has(d.id) ? 'rotate(180deg)' : '' }"></i>
                </div>
              </h2>
              <Transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
                <div class="accordion-collapse" v-show="expandedDist.has(d.id)">
                  <div class="accordion-body">
                  <h6 class="mb-2">Fleet Managers</h6>
                  <div v-if="managersByDistributor(d.id).length === 0" class="text-muted small">No fleet managers.</div>
                  <ul class="list-group list-group-flush mb-0">
                    <li v-for="m in managersByDistributor(d.id)" :key="'mgr-'+m.id" class="list-group-item d-flex align-items-center justify-content-between">
                      <div>{{ m.name }} — <span class="text-muted">{{ m.email }}</span></div>
                    </li>
                  </ul>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>

      <!-- Distributor View -->
      <div v-if="isDistributor" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Fleet Managers</h6></div>
        <div class="card-body">
          <div v-if="myManagers.length === 0" class="text-muted small">No fleet managers found.</div>
          <ul class="list-group list-group-flush">
            <li v-for="m in myManagers" :key="'mgr-self-'+m.id" class="list-group-item d-flex align-items-center justify-content-between">
              <div>{{ m.name }} — <span class="text-muted">{{ m.email }}</span></div>
              <button class="btn btn-sm btn-outline-primary" @click="loginAs(m)">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login as
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../components/UiAlert.vue';
import Swal from 'sweetalert2';
import { authState, roleToNumber, clearAuthCache, refreshCsrf, getCurrentUser } from '../auth';

const router = useRouter();
const loading = ref(false);
const error = ref('');
const rows = ref([]);

const me = computed(() => authState?.user || {});
const role = computed(() => roleToNumber(me.value?.role ?? 0));
const isAdmin = computed(() => role.value === 3);
const isDistributor = computed(() => role.value === 2);

const expandedDist = ref(new Set());
function toggleDist(id) {
  const s = new Set(expandedDist.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  expandedDist.value = s;
}

function beforeEnter(el) {
  el.style.height = '0';
  el.style.overflow = 'hidden';
}
function enter(el) {
  el.style.transition = 'height 300ms ease';
  const h = el.scrollHeight;
  el.style.height = h + 'px';
}
function afterEnter(el) {
  el.style.transition = '';
  el.style.height = 'auto';
}
function beforeLeave(el) {
  el.style.height = el.scrollHeight + 'px';
  el.style.overflow = 'hidden';
}
function leave(el) {
  el.style.transition = 'height 300ms ease';
  el.style.height = '0';
}
function afterLeave(el) {
  el.style.transition = '';
}

const distributors = computed(() => rows.value.filter(u => Number(u.role) === 2));
function managersByDistributor(distId) { return rows.value.filter(u => Number(u.role) === 1 && Number(u.distributor_id) === Number(distId)); }

const myManagers = computed(() => rows.value.filter(u => Number(u.role) === 1 && Number(u.distributor_id) === Number(me.value?.id)));

async function loginAs(row) {
  if (!row?.id) return;
  const result = await Swal.fire({
    title: `Login as ${row.name || row.email}?`,
    text: 'Your session will switch to this account.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Login as',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0b5ed7',
  });
  if (!result.isConfirmed) return;
  
  try {
    await axios.post(`/web/auth/impersonate/${row.id}`);
    clearAuthCache();
    await refreshCsrf();
    await getCurrentUser();
    await Swal.fire({ title: 'Switched', text: `Now logged in as ${row.name || row.email}.`, icon: 'success', timer: 1200, showConfirmButton: false });
    router.push('/');
    // Force reload to ensure all state is clean
    window.location.reload();
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to login as user';
    await Swal.fire({ title: 'Impersonation failed', text: msg, icon: 'error' });
  }
}

onMounted(async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/web/users');
    rows.value = Array.isArray(data?.users) ? data.users : [];
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load dashboard data';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.accordion-button { padding: 0.75rem 1rem; cursor: pointer; }
.accordion-button:not(.collapsed) { background-color: #e7f1ff; color: #0c63e4; }
.accordion-collapse { overflow: hidden; }
</style>
