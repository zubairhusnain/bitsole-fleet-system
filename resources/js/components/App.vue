<template>
    <!-- Guest pages render without admin layout -->
    <template v-if="isGuestPage">
        <RouterView />
    </template>

    <!-- Admin layout mirrors layout-sample.blade.php -->
    <div v-else class="app-wrapper" :class="{ 'live-tracking-route': route.name === 'live-tracking' }">
        <!--begin::Header-->
        <nav class="app-header navbar navbar-expand bg-body">
            <!--begin::Container-->
            <div class="container-fluid">
                <!--begin::Start Navbar Links-->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link toggle-btn" href="#" role="button" @click.stop.prevent="toggleSidebar($event)" @touchend.stop.prevent="toggleSidebar($event)" aria-label="Toggle sidebar">
                            <i class="bi caret-toggle" :class="sidebarOpen ? 'bi-caret-left-fill' : 'bi-caret-right-fill'"></i>
                        </a>
                    </li>
                </ul>
                <!--end::Start Navbar Links-->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item user-menu" v-if="isAuthed">
                        <div class="nav-link d-flex align-items-center user-toggle">
                            <img v-if="avatarSrc" :src="avatarSrc" alt="Avatar" class="avatar-img" />
                            <span v-else class="avatar">{{ initials }}</span>
                            <div class="d-flex align-items-center ms-2">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="fw-semibold name-text">{{ displayName }}</span>
                                    <span class="role-badge mt-1">{{ roleLabel(role) }}</span>
                                </div>
                                <i class="bi bi-chevron-down ms-2 chevron"></i>
                            </div>
                        </div>
                        <div class="user-dropdown">
                            <RouterLink to="/profile" class="dropdown-item">Profile</RouterLink>
                            <button class="dropdown-item text-danger" @click="logout">Logout</button>
                        </div>
                    </li>
                </ul>
            </div>
            <!--end::Container-->
        </nav>
        <!--end::Header-->

        <!--begin::Sidebar-->
        <aside class="app-sidebar bg-white ">
            <!--begin::Sidebar Brand-->
            <div class="sidebar-brand">
                <RouterLink to="/" class="brand-link d-flex align-items-center">
                    <img :src="logoSrc" class="mt-3" alt="App Logo" style="height:35px" />
                </RouterLink>
            </div>
            <!-- Mobile close button -->
            <button class="sidebar-close d-lg-none" type="button" aria-label="Close sidebar"
                @click.prevent="closeSidebar" @touchend.stop.prevent="closeSidebar">
                <svg class="close-icon" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="1.8"/>
                    <line x1="8" y1="8" x2="16" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="16" y1="8" x2="8" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <!--end::Sidebar Brand-->
            <!--begin::Sidebar Wrapper-->
            <div class="sidebar-wrapper mt-4">
                <nav class="mt-2">
                    <!--begin::Sidebar Menu-->
                    <ul class="nav sidebar-menu flex-column" role="navigation" aria-label="Main navigation"
                        data-lte-toggle="treeview" data-accordion="false" id="navigation">
                        <li class="nav-item" v-if="hasPerm('live-tracking','read')">
                            <RouterLink to="/live-tracking" class="nav-link"
                                :class="{ active: route.name === 'live-tracking' }">
                                <i class="nav-icon bi bi-broadcast"></i>
                                <p>Live Tracking</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item d-none">
                            <RouterLink to="/dashboard" class="nav-link"
                                :class="{ active: route.name === 'dashboard' }">
                                <i class="nav-icon bi bi-speedometer"></i>
                                <p>Dashboard</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" v-if="hasPerm('drivers','read')">
                            <RouterLink to="/drivers" class="nav-link"
                                :class="{ active: route.path.startsWith('/drivers') }">
                                <i class="nav-icon bi bi-people"></i>
                                <p>Driver Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" ref="vehiclesNav" v-if="hasPerm('vehicles','read') || hasPerm('vehicles.overview','read') || hasPerm('vehicles.maintenance','read')">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/vehicles') }">
                                <i class="nav-icon bi bi-car-front"></i>
                                <p>
                                    Vehicle Management
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item" v-if="hasPerm('vehicles','read')">
                                    <RouterLink to="/vehicles" class="nav-link"
                                        :class="{ active: route.path === '/vehicles' }">
                                        <i class="nav-icon bi bi-list-ul"></i>
                                        <p>All Vehicles</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="hasPerm('vehicles.maintenance','read')">
                                    <RouterLink to="/vehicles/maintenance" class="nav-link"
                                        :class="{ active: route.path.startsWith('/vehicles/maintenance') }">
                                        <i class="nav-icon bi bi-tools"></i>
                                        <p>Vehicle Maintenance</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="hasPerm('vehicles.overview','read')">
                                    <RouterLink to="/vehicles/overview" class="nav-link"
                                        :class="{ active: route.path.startsWith('/vehicles/overview') }">
                                        <i class="nav-icon bi bi-clipboard-data"></i>
                                        <p>Vehicle Overview</p>
                                    </RouterLink>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item  d-none">
                            <RouterLink to="/fuel" class="nav-link" :class="{ active: route.name === 'fuel' }">
                                <i class="nav-icon bi bi-fuel-pump"></i>
                                <p>Fuel Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item  d-none">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-graph-up"></i>
                                <p>
                                    Monitoring
                                    <i class="bi bi-chevron-right right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <RouterLink to="/monitoring/vehicles" class="nav-link"
                                        :class="{ active: route.name === 'monitoring-vehicles' }">
                                        <i class="nav-icon bi bi-truck"></i>
                                        <p>Vehicles</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/monitoring/zones" class="nav-link"
                                        :class="{ active: route.name === 'monitoring-zones' }">
                                        <i class="nav-icon bi bi-geo-alt"></i>
                                        <p>Zones</p>
                                    </RouterLink>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item" v-if="hasPerm('zones','read')">
                            <RouterLink to="/zones" class="nav-link" :class="{ active: route.name === 'zones' }">
                                <i class="nav-icon bi bi-grid-3x3"></i>
                                <p>Zone Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item  d-none">
                            <RouterLink to="/reports" class="nav-link" :class="{ active: route.name === 'reports' }">
                                <i class="nav-icon bi bi-bar-chart"></i>
                                <p>Reports & Analytics</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item  d-none">
                            <RouterLink to="/alerts" class="nav-link" :class="{ active: route.name === 'alerts' }">
                                <i class="nav-icon bi bi-bell"></i>
                                <p>Alerts & Notifications</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item  d-none">
                            <RouterLink to="/settings" class="nav-link" :class="{ active: route.name === 'settings' }">
                                <i class="nav-icon bi bi-gear"></i>
                                <p>Settings</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" :class="{ 'menu-open': route.path.startsWith('/users') }" v-if="hasPerm('users','read')">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/users') }">
                                <i class="nav-icon bi bi-people-fill"></i>
                                <p>
                                    User Management
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
    <li class="nav-item" v-if="hasPerm('users','read')">
        <RouterLink to="/users" class="nav-link"
            :class="{ active: route.path === '/users' }">
            <i class="nav-icon bi bi-list-ul"></i>
            <p>User List</p>
        </RouterLink>
    </li>
    <li class="nav-item" v-if="(role === 1 || role === 2 || role === 3) && hasPerm('users.permissions','read')">
        <RouterLink to="/users/permissions" class="nav-link"
            :class="{ active: route.path.startsWith('/users/permissions') }">
            <i class="nav-icon bi bi-shield-lock"></i>
            <p>User Permission</p>
        </RouterLink>
    </li>
</ul>
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
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { authState, clearAuthCache, hasPermission } from '../auth';

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
const logoSrc = assetBase + '/images/logo.png';

const router = useRouter();
const route = useRoute();
const sidebarOpen = ref(false);
const vehiclesNav = ref(null);
const isProd = import.meta.env.PROD;
const appName = document.title || 'Omayer Fleet System';
const year = new Date().getFullYear();
const isAuthed = computed(() => !!authState.user);
const isGuestPage = computed(() => route.meta?.guestOnly === true);
const pageTitle = computed(() => route.meta?.title || (route.name ? String(route.name).charAt(0).toUpperCase() + String(route.name).slice(1) : 'Dashboard'));
const role = computed(() => authState?.user?.role ?? 3);
const hasPerm = (key, action) => hasPermission(key, action);
function roleLabel(r) {
    if (typeof r === 'string') {
        const v = r.trim().toLowerCase();
        if (v === 'admin' || v === 'administrator') return 'admin';
        if (v === 'distributor') return 'distributor';
        if (v === 'manager' || v === 'fleet manager') return 'fleet manager';
        return 'user';
    }
    switch (Number(r)) {
        case 3: return 'admin';
        case 2: return 'distributor';
        case 1: return 'fleet manager';
        default: return 'user';
    }
}
const displayName = computed(() => authState.user?.name || 'Profile');
const initials = computed(() => {
    const n = String(displayName.value || '').trim();
    const first = n ? n[0] : '?';
    return String(first).toUpperCase();
});
const avatarSrc = computed(() => {
    const u = authState.user || {};
    const src = u.avatar_url || u.avatar || '';
    if (src && /^https?:\/\//i.test(src)) return src;
    if (src) return src;
    return '';
});

// Ensure AdminLTE Treeview binds after Vue renders the sidebar (post-login)
function initTreeview() {
    const toggles = document.querySelectorAll('[data-lte-toggle="treeview"]');
    const Treeview = window?.adminlte?.Treeview;
    if (!toggles.length || !Treeview) return;
    toggles.forEach((toggle) => {
        // Avoid duplicate bindings across route changes
        if (toggle.dataset.treeviewBound === '1') return;
        toggle.addEventListener('click', (event) => {
            const target = event.target;
            const targetItem = target.closest('.nav-item');
            const targetLink = target.closest('.nav-link');
            if (target?.getAttribute('href') === '#' || targetLink?.getAttribute('href') === '#') {
                event.preventDefault();
            }
            if (targetItem) {
                const accordionAttr = toggle.dataset.accordion;
                const animationSpeedAttr = toggle.dataset.animationSpeed;
                const config = {
                    accordion: accordionAttr === undefined ? true : accordionAttr === 'true',
                    animationSpeed: animationSpeedAttr === undefined ? 300 : Number(animationSpeedAttr),
                };
                const tv = new Treeview(targetItem, config);
                tv.toggle();
            }
        });
        toggle.dataset.treeviewBound = '1';
    });
}

onMounted(async () => {
    if (!isGuestPage.value) {
        await nextTick();
        initTreeview();
    }
    document.body.classList.add('sidebar-open');
    document.body.classList.remove('sidebar-collapse');
    sidebarOpen.value = true;
    const p = route.path || '';
    document.querySelectorAll('.app-sidebar .nav-item.menu-open').forEach(el => el.classList.remove('menu-open'));
    if (p.startsWith('/vehicles') && vehiclesNav.value) vehiclesNav.value.classList.add('menu-open');
});

watch(() => isGuestPage.value, async (isGuest) => {
    if (!isGuest) {
        await nextTick();
        initTreeview();
    }
});

watch(() => route.path, (p) => {
    document.querySelectorAll('.app-sidebar .nav-item.menu-open').forEach(el => el.classList.remove('menu-open'));
    if ((p || '').startsWith('/vehicles') && vehiclesNav.value) {
        vehiclesNav.value.classList.add('menu-open');
    }
});

async function logout() {
    try {
        await axios.post('/web/auth/logout');
    } catch (e) {
        // ignore logout errors
    }
    clearAuthCache();
    router.push('/login');
}

function closeSidebar() {
    const body = document.body;
    if (body.classList.contains('sidebar-open')) {
        body.classList.remove('sidebar-open');
        body.classList.add('sidebar-collapse');
    }
}

let lastToggleTs = 0;
function toggleSidebar(ev) {
    const now = Date.now();
    // Dedup rapid click+touch sequences on mobile
    if (now - lastToggleTs < 300) return;
    lastToggleTs = now;

    const body = document.body;
    const isOpen = body.classList.contains('sidebar-open');
    if (isOpen) {
        body.classList.remove('sidebar-open');
        body.classList.add('sidebar-collapse');
        sidebarOpen.value = false;
    } else {
        body.classList.add('sidebar-open');
        body.classList.remove('sidebar-collapse');
        sidebarOpen.value = true;
    }
}
</script>

<style scoped>
nav a.router-link-exact-active {
    font-weight: 600;
}

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

/* Mobile-only close button (visible when sidebar is open) */
.app-sidebar {
    position: relative;
}

.sidebar-close {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 1051;
    display: none;
    background: none;
    border: none;
    padding: 6px;
    border-radius: 10px;
}

@media (max-width: 992px) {

    /* Ensure sidebar sits above the overlay so the close button is tappable */
    .app-sidebar {
        z-index: 1040;
    }

    body.sidebar-open .app-sidebar .sidebar-close {
        display: inline-block;
    }
}
.app-sidebar .sidebar-close .close-icon {
    display: inline-block;
    color: #4a4a4a; /* dark grey close to screenshot */
}

/* Slightly larger tap target on small screens */
@media (max-width: 576px) {
    .app-sidebar .sidebar-close {
        padding: 8px;
    }
    .app-sidebar .sidebar-close .close-icon {
        width: 28px;
        height: 28px;
    }
}
.app-header .nav-link .caret-toggle { display: inline-block; color: #4a4a4a; font-size: 22px; }
.app-header { position: relative; padding-left: 0 !important; }
.app-header .container-fluid { padding-left: 0 !important; }
.app-header .toggle-btn { position: absolute; left: 0; top: 50%; transform: translateY(-50%); padding: 0; z-index: 1051; display: flex; align-items: center; }

/* Improve tap target spacing on mobile */
@media (max-width: 576px) {
    .app-header .nav-link {
        padding: 8px 12px;
    }
    .app-header .nav-link .menu-icon {
        width: 28px;
        height: 28px;
    }
}
.user-menu { position: relative; }
.user-dropdown { position: absolute; right: 0; top: 100%; min-width: 160px; background: #fff; border: 1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: none; z-index: 1050; border-radius: 6px; }
.user-menu:hover .user-dropdown { display: block; }
.user-dropdown .dropdown-item { display: block; padding: 8px 12px; color: #333; text-decoration: none; background: transparent; width: 100%; text-align: left; border: none; }
.user-dropdown .dropdown-item:hover { background: #f7f7f7; }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; color: #343a40; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
.avatar-img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.name-text { color: #0b0f28; }
.chevron { color: #0b0f28; font-size: 18px; }
.role-badge { font-size: 10px; line-height: 1; color: #6b7280; border: 1px solid #e5e7eb; border-radius: 999px; padding: 1px 6px; text-transform: capitalize; }
</style>
import { ref } from 'vue';
const sidebarOpen = ref(false);
