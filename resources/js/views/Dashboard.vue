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
      <div v-if="isAdmin" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Distributors</h6></div>
        <div class="card-body">
          <div v-if="distributors.length === 0" class="text-muted small">No distributors found.</div>
          <div class="accordion" id="distAccordion">
            <div class="accordion-item" v-for="d in distributors" :key="'dist-'+d.id">
              <h2 class="accordion-header">
                <button class="accordion-button" type="button" :class="{ collapsed: !expandedDist.has(d.id) }" @click="toggleDist(d.id)">
                  {{ d.name }} — <span class="text-muted">{{ d.email }}</span>
                </button>
              </h2>
              <Transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
                <div class="accordion-collapse" v-show="expandedDist.has(d.id)">
                  <div class="accordion-body">
                  <h6 class="mb-2">Fleet Managers</h6>
                  <div v-if="managersByDistributor(d.id).length === 0" class="text-muted small">No fleet managers.</div>
                  <ul class="list-group list-group-flush mb-0">
                    <li v-for="m in managersByDistributor(d.id)" :key="'mgr-'+m.id" class="list-group-item">
                      {{ m.name }} — <span class="text-muted">{{ m.email }}</span>
                    </li>
                  </ul>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isDistributor" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Fleet Managers</h6></div>
        <div class="card-body">
          <div v-if="myManagers.length === 0" class="text-muted small">No fleet managers found.</div>
          <div class="accordion" id="mgrAccordionSelf">
            <div class="accordion-item" v-for="m in myManagers" :key="'mgr-self-'+m.id">
              <h2 class="accordion-header">
                <button class="accordion-button" type="button" :class="{ collapsed: !expandedMgr.has(m.id) }" @click="toggleMgr(m.id)">
                  {{ m.name }} — <span class="text-muted">{{ m.email }}</span>
                </button>
              </h2>
              <Transition @before-enter="beforeEnter" @enter="enter" @after-enter="afterEnter" @before-leave="beforeLeave" @leave="leave" @after-leave="afterLeave">
                <div class="accordion-collapse" v-show="expandedMgr.has(m.id)">
                  <div class="accordion-body">
                    <h6 class="mb-2">Fleet Viewers</h6>
                    <ul class="list-group list-group-flush">
                      <li v-for="u in viewersByManager(m.id)" :key="'usr-self-'+u.id" class="list-group-item">
                        {{ u.name }} — <span class="text-muted">{{ u.email }}</span>
                      </li>
                      <li v-if="viewersByManager(m.id).length === 0" class="list-group-item text-muted small">No fleet viewers.</li>
                    </ul>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import axios from 'axios';
import UiAlert from '../components/UiAlert.vue';
import { authState, roleToNumber } from '../auth';

const loading = ref(false);
const error = ref('');
const rows = ref([]);

const me = computed(() => authState?.user || {});
const role = computed(() => roleToNumber(me.value?.role ?? 0));
const isAdmin = computed(() => role.value === 3);
const isDistributor = computed(() => role.value === 2);

const expandedDist = ref(new Set());
const expandedMgr = ref(new Set());
function toggleDist(id) {
  const s = new Set(expandedDist.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  expandedDist.value = s;
}
function toggleMgr(id) {
  const s = new Set(expandedMgr.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  expandedMgr.value = s;
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
function viewersByManager(managerId) { return rows.value.filter(u => Number(u.role) === 0 && Number(u.manager_id) === Number(managerId)); }

const myManagers = computed(() => rows.value.filter(u => Number(u.role) === 1 && Number(u.distributor_id) === Number(me.value?.id)));

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
.accordion-button { display: flex; align-items: baseline; gap: 8px; }
.accordion-collapse { overflow: hidden; }
</style>
