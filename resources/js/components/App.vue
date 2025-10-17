<template>
  <!-- Guest pages render without admin layout -->
  <template v-if="isGuestPage">
    <RouterView />
  </template>

  <!-- Admin layout mirrors layout-sample.blade.php -->
  <div v-else class="app-wrapper">
    <!--begin::Header-->
    <nav class="app-header navbar navbar-expand bg-body">
      <!--begin::Container-->
      <div class="container-fluid">
        <!--begin::Start Navbar Links-->
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
              <i class="bi bi-list"></i>
            </a>
          </li>
        </ul>
        <!--end::Start Navbar Links-->
        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
          <li class="nav-item" v-if="isAuthed">
            <button @click="logout" class="btn btn-link nav-link text-danger">Logout</button>
          </li>
        </ul>
        <!--end::End Navbar Links-->
      </div>
      <!--end::Container-->
    </nav>
    <!--end::Header-->

    <!--begin::Sidebar-->
    <aside class="app-sidebar bg-white ">
      <!--begin::Sidebar Brand-->
      <div class="sidebar-brand">
        <RouterLink to="/" class="brand-link">
          <span class="brand-text fw-light">{{ appName }}</span>
        </RouterLink>
      </div>
      <!--end::Sidebar Brand-->
      <!--begin::Sidebar Wrapper-->
      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <!--begin::Sidebar Menu-->
          <ul class="nav sidebar-menu flex-column" role="navigation" aria-label="Main navigation" data-accordion="false" id="navigation">
            <li class="nav-item">
              <RouterLink to="/live-tracking" class="nav-link" :class="{ active: route.name === 'live-tracking' }">
                <i class="nav-icon bi bi-broadcast"></i>
                <p>Live Tracking</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <RouterLink to="/dashboard" class="nav-link" :class="{ active: route.name === 'dashboard' }">
                <i class="nav-icon bi bi-speedometer"></i>
                <p>Dashboard</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <RouterLink to="/drivers" class="nav-link" :class="{ active: route.path.startsWith('/drivers') }">
                <i class="nav-icon bi bi-people"></i>
                <p>Driver Management</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/vehicles') }">
                <i class="nav-icon bi bi-car-front"></i>
                <p>
                  Vehicle Management
                  <i class="bi bi-chevron-right right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <RouterLink to="/vehicles" class="nav-link" :class="{ active: route.path === '/vehicles' }">
                    <i class="nav-icon bi bi-list-ul"></i>
                    <p>All Vehicles</p>
                  </RouterLink>
                </li>
                <li class="nav-item">
                  <RouterLink to="/vehicles/maintenance" class="nav-link" :class="{ active: route.path.startsWith('/vehicles/maintenance') }">
                    <i class="nav-icon bi bi-tools"></i>
                    <p>Vehicle Maintenance</p>
                  </RouterLink>
                </li>
                <li class="nav-item">
                  <RouterLink to="/vehicles/overview" class="nav-link" :class="{ active: route.path.startsWith('/vehicles/overview') }">
                    <i class="nav-icon bi bi-clipboard-data"></i>
                    <p>Vehicle Overview</p>
                  </RouterLink>
                </li>
              </ul>
            </li>

            <li class="nav-item">
              <RouterLink to="/fuel" class="nav-link" :class="{ active: route.name === 'fuel' }">
                <i class="nav-icon bi bi-fuel-pump"></i>
                <p>Fuel Management</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-graph-up"></i>
                <p>
                  Monitoring
                  <i class="bi bi-chevron-right right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <RouterLink to="/monitoring/vehicles" class="nav-link" :class="{ active: route.name === 'monitoring-vehicles' }">
                    <i class="nav-icon bi bi-truck"></i>
                    <p>Vehicles</p>
                  </RouterLink>
                </li>
                <li class="nav-item">
                  <RouterLink to="/monitoring/zones" class="nav-link" :class="{ active: route.name === 'monitoring-zones' }">
                    <i class="nav-icon bi bi-geo-alt"></i>
                    <p>Zones</p>
                  </RouterLink>
                </li>
              </ul>
            </li>

            <li class="nav-item">
              <RouterLink to="/zones" class="nav-link" :class="{ active: route.name === 'zones' }">
                <i class="nav-icon bi bi-grid-3x3"></i>
                <p>Zone Management</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <RouterLink to="/reports" class="nav-link" :class="{ active: route.name === 'reports' }">
                <i class="nav-icon bi bi-bar-chart"></i>
                <p>Reports & Analytics</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <RouterLink to="/alerts" class="nav-link" :class="{ active: route.name === 'alerts' }">
                <i class="nav-icon bi bi-bell"></i>
                <p>Alerts & Notifications</p>
              </RouterLink>
            </li>

            <li class="nav-item">
              <RouterLink to="/settings" class="nav-link" :class="{ active: route.name === 'settings' }">
                <i class="nav-icon bi bi-gear"></i>
                <p>Settings</p>
              </RouterLink>
            </li>
          </ul>
          <!--end::Sidebar Menu-->
        </nav>
      </div>
      <!--end::Sidebar Wrapper-->
    </aside>
    <!--end::Sidebar-->

    <!--begin::App Main-->
    <main class="app-main">
      <!--begin::App Content Header-->
      <!--end::App Content Header-->
      <!--begin::App Content-->
      <div class="app-content">
        <div class="container-fluid">
          <RouterView />
        </div>
      </div>
      <!--end::App Content-->
    </main>
    <!--end::App Main-->

    <!--begin::Footer-->
    <footer class="app-footer">
      <div class="float-end d-none d-sm-inline">Anything you want</div>
      <strong>
        Copyright &copy; {{ year }} {{ appName }}.
      </strong>
      All rights reserved.
    </footer>
    <!--end::Footer-->
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { authState, clearAuthCache } from '../auth';

const router = useRouter();
const route = useRoute();
const appName = document.title || 'Omayer Fleet System';
const year = new Date().getFullYear();
const isAuthed = computed(() => !!authState.user);
const isGuestPage = computed(() => route.meta?.guestOnly === true);
const pageTitle = computed(() => route.meta?.title || (route.name ? String(route.name).charAt(0).toUpperCase() + String(route.name).slice(1) : 'Dashboard'));

async function logout() {
  try {
    await axios.post('/web/auth/logout');
  } catch (e) {
    // ignore logout errors
  }
  clearAuthCache();
  router.push('/login');
}
</script>

<style scoped>
nav a.router-link-exact-active { font-weight: 600; }
/* Active sidebar item: dark background, white text */
.app-sidebar .nav-link.active,
.app-sidebar .nav-link.router-link-active,
.app-sidebar .nav-link.router-link-exact-active {
  color: #fff !important;
  background-color: #0b0f28 !important;
}

.app-sidebar .nav-link.active .nav-icon,
.app-sidebar .nav-link.router-link-active .nav-icon,
.app-sidebar .nav-link.router-link-exact-active .nav-icon,
.app-sidebar .nav-link.active p,
.app-sidebar .nav-link.router-link-active p,
.app-sidebar .nav-link.router-link-exact-active p {
  color: #fff !important;
}
</style>
