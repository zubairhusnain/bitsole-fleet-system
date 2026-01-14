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
                            <i class="bi caret-toggle sidebar-toggle" :class="sidebarOpen ? 'bi-caret-right-fill' : 'bi-caret-left-fill'"></i>
                        </a>
                    </li>
                </ul>
                <!--end::Start Navbar Links-->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center" v-if="isAuthed">
                        <select v-model="timezone" @change="handleTimezoneChange" class="form-select form-select-sm timezone-select">
                            <option v-for="opt in timezoneOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </li>
                    <li class="nav-item" :class="{ 'd-testingmode': !isTestingMode }" v-if="isAuthed">
                        <RouterLink to="/alerts" class="nav-link position-relative" style="padding-top: 0.5rem;">
                            <i class="bi bi-bell" style="font-size: 1.2rem;"></i>
                            <span v-if="unreadCount > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; transform: translate(-50%, 50%) !important;">
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                                <span class="visually-hidden">unread messages</span>
                            </span>
                        </RouterLink>
                    </li>
                    <li class="nav-item user-menu" v-if="isAuthed">
                        <div class="nav-link d-flex align-items-center user-toggle">
                            <img v-if="avatarSrc" :src="avatarSrc" alt="Avatar" class="avatar-img" />
                            <span v-else class="avatar">{{ initials }}</span>
                            <div class="d-flex align-items-center ms-2">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="fw-semibold name-text">{{ displayName }}</span>
                                    <span class="role-badge mt-1">{{ displayRoleLabel }}</span>
                                </div>
                                <i class="bi bi-chevron-down ms-2 chevron"></i>
                            </div>
                        </div>
                        <div class="user-dropdown">
                            <RouterLink to="/profile" class="dropdown-item">Profile</RouterLink>
                            <RouterLink v-if="roleToNumber(authState?.user?.role ?? 0) === 3" to="/settings" class="dropdown-item">Settings</RouterLink>
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
                        data-lte-toggle="treeview" data-accordion="true" id="navigation">
                        <li class="nav-item" v-if="showLiveTracking">
                            <RouterLink to="/live-tracking" class="nav-link"
                                :class="{ active: route.name === 'live-tracking' }">
                                <i class="nav-icon bi bi-broadcast"></i>
                                <p>Live Tracking</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" v-if="showDashboard">
                            <RouterLink to="/dashboard" class="nav-link"
                                :class="{ active: route.name === 'dashboard' }">
                                <i class="nav-icon bi bi-speedometer"></i>
                                <p>Dashboard</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('drivers','read')">
                            <RouterLink to="/drivers" class="nav-link"
                                :class="{ active: route.path.startsWith('/drivers') }">
                                <i class="nav-icon bi bi-people"></i>
                                <p>Driver Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" :class="{ 'menu-open': route.path.startsWith('/vehicles') }" v-if="!isAdminOrDistributor && (hasPerm('vehicles','read') || hasPerm('vehicles.overview','read') || hasPerm('vehicles.maintenance','read'))">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/vehicles') }">
                                <i class="nav-icon bi bi-car-front"></i>
                                <p>
                                    Vehicle Management
                                    <i class="bi bi-chevron-right right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('vehicles','read')">
                                    <RouterLink to="/vehicles" class="nav-link"
                                        :class="{ active: route.path === '/vehicles' }">
                                        <i class="nav-icon bi bi-list-ul"></i>
                                        <p>All Vehicles</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('vehicles.maintenance','read')">
                                    <RouterLink to="/vehicles/maintenance" class="nav-link"
                                        :class="{ active: route.path.startsWith('/vehicles/maintenance') }">
                                        <i class="nav-icon bi bi-tools"></i>
                                        <p>Vehicle Maintenance</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('vehicles.overview','read')">
                                    <RouterLink to="/vehicles/overview" class="nav-link"
                                        :class="{ active: route.path.startsWith('/vehicles/overview') || route.name === 'vehicles-detail' }">
                                        <i class="nav-icon bi bi-clipboard-data"></i>
                                        <p>Vehicle Overview</p>
                                    </RouterLink>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item" :class="{ 'd-testingmode': !isTestingMode }" v-if="!isAdminOrDistributor && hasPerm('fuel', 'read')">
                            <RouterLink to="/fuel" class="nav-link" :class="{ active: route.name === 'fuel' }">
                                <i class="nav-icon bi bi-fuel-pump"></i>
                                <p>Fuel Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" :class="{ 'menu-open': route.path.startsWith('/monitoring'), 'd-testingmode': !isTestingMode }" v-if="!isAdminOrDistributor && (hasPerm('monitoring.vehicles', 'read') || hasPerm('monitoring.zones', 'read'))">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/monitoring') }">
                                <i class="nav-icon bi bi-graph-up"></i>
                                <p>
                                    Monitoring
                                    <i class="bi bi-chevron-right right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('monitoring.vehicles', 'read')">
                                    <RouterLink to="/monitoring/vehicles" class="nav-link"
                                        :class="{ active: route.path.startsWith('/monitoring/vehicles') }">
                                        <i class="nav-icon bi bi-truck"></i>
                                        <p>Vehicle Monitoring</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="!isAdminOrDistributor && hasPerm('monitoring.zones', 'read')">
                                    <RouterLink to="/monitoring/zones" class="nav-link"
                                        :class="{ active: route.path.startsWith('/monitoring/zones') }">
                                        <i class="nav-icon bi bi-geo-alt"></i>
                                        <p>Zone Monitoring</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item" v-if="hasPerm('monitoring.vehicles', 'read')">
                                    <RouterLink to="/monitoring/dashboard" class="nav-link"
                                        :class="{ active: route.path.startsWith('/monitoring/dashboard') }">
                                        <i class="nav-icon bi bi-speedometer2"></i>
                                        <p>Vehicle Dashboard</p>
                                    </RouterLink>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item" :class="{ 'd-testingmode': !isTestingMode }" v-if="!isAdminOrDistributor && hasPerm('zones','read')">
                            <RouterLink to="/zones" class="nav-link" :class="{ active: route.name === 'zones' }">
                                <i class="nav-icon bi bi-grid-3x3"></i>
                                <p>Zone Management</p>
                            </RouterLink>
                        </li>

                        <li class="nav-item" :class="{ 'd-testingmode': !isTestingMode,'menu-open': route.path.startsWith('/reports') }" v-if="hasPerm('reports','read')">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/reports') }">
                                <i class="nav-icon bi bi-bar-chart"></i>
                                <p>
                                    Reports & Analytics
                                    <i class="bi bi-chevron-right right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <RouterLink to="/reports/trip-analysis" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/trip-analysis') }">
                                        <i class="nav-icon bi bi-table"></i>
                                        <p>Trip Analysis Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/asset-activity" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/asset-activity') }">
                                        <i class="nav-icon bi bi-graph-up"></i>
                                        <p>Asset Activity Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/vehicle-activity" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/vehicle-activity') }">
                                        <i class="nav-icon bi bi-list-check"></i>
                                        <p>Vehicle Activity Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/vehicle-status" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/vehicle-status') }">
                                        <i class="nav-icon bi bi-info-square"></i>
                                        <p>Vehicle Status Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/idling" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/idling') }">
                                        <i class="nav-icon bi bi-pause-circle"></i>
                                        <p>Idling Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/utilisation" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/utilisation') }">
                                        <i class="nav-icon bi bi-bar-chart-line"></i>
                                        <p>Utilisation Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/incident-analysis" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/incident-analysis') }">
                                        <i class="nav-icon bi bi-exclamation-circle"></i>
                                        <p>Incident Analysis Report</p>
                                    </RouterLink>
                                </li>
                                <li class="nav-item">
                                    <RouterLink to="/reports/vehicle-ranking" class="nav-link"
                                        :class="{ active: route.path.startsWith('/reports/vehicle-ranking') }">
                                        <i class="nav-icon bi bi-trophy"></i>
                                        <p>Vehicle Ranking Report</p>
                                    </RouterLink>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item" :class="{ 'd-testingmode': !isTestingMode }" v-if="isAuthed">
                            <RouterLink to="/alerts" class="nav-link" :class="{ active: route.name === 'alerts' }">
                                <i class="nav-icon bi bi-bell"></i>
                                <p>Alerts & Notifications</p>
                            </RouterLink>
                        </li>



                        <li class="nav-item" :class="{ 'menu-open': route.path.startsWith('/users') }" v-if="hasPerm('users','read')">
                            <a href="#" class="nav-link" :class="{ active: route.path.startsWith('/users') }">
                                <i class="nav-icon bi bi-people-fill"></i>
                                <p>
                                    User Management
                                    <i class="bi bi-chevron-right right"></i>
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
                                <li class="nav-item" v-if="(roleToNumber(authState?.user?.role ?? 0) === 1 || roleToNumber(authState?.user?.role ?? 0) === 2 || roleToNumber(authState?.user?.role ?? 0) === 3) && hasPerm('users.permissions','read')">
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
            <div class="float-end d-none d-sm-inline">Version {{ version }}</div>
            <strong>
                Copyright &copy; {{ year }} {{ appName }}.
            </strong>
            All rights reserved.
        </footer>
        <!--end::Footer-->
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick, provide } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { authState, clearAuthCache, hasPermission, roleToNumber } from '../auth';
import { getActiveTimezone, setTimezonePreference } from '../utils/datetime';

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
const logoSrc = assetBase + '/images/logo.png';

const router = useRouter();
const route = useRoute();
const sidebarOpen = ref(false);
const isProd = import.meta.env.PROD;
const appName = document.title || 'Omayer Fleet System';
const year = new Date().getFullYear();
const version = import.meta.env.VITE_APP_VERSION || '1.0.0';
const unreadCount = ref(0);
const myDeviceIds = ref([]);
let echoChannel = null;

function timezoneFlag(tz) {
    const map = {
        'UTC': '🌐',
        'Etc/UTC': '🌐',
        'Europe/London': '🇬🇧',
        'Europe/Berlin': '🇩🇪',
        'Europe/Paris': '🇫🇷',
        'Europe/Madrid': '🇪🇸',
        'Europe/Rome': '🇮🇹',
        'Europe/Amsterdam': '🇳🇱',
        'Europe/Brussels': '🇧🇪',
        'Europe/Zurich': '🇨🇭',
        'Europe/Vienna': '🇦🇹',
        'Europe/Prague': '🇨🇿',
        'Europe/Warsaw': '🇵🇱',
        'Europe/Stockholm': '🇸🇪',
        'Europe/Copenhagen': '🇩🇰',
        'Europe/Helsinki': '🇫🇮',
        'Europe/Dublin': '🇮🇪',
        'Europe/Oslo': '🇳🇴',
        'Europe/Athens': '🇬🇷',
        'Europe/Bucharest': '🇷🇴',
        'Europe/Sofia': '🇧🇬',
        'Europe/Budapest': '🇭🇺',
        'Europe/Lisbon': '🇵🇹',
        'Europe/Moscow': '🇷🇺',
        'Europe/Istanbul': '🇹🇷',
        'Asia/Dubai': '🇦🇪',
        'Asia/Riyadh': '🇸🇦',
        'Asia/Doha': '🇶🇦',
        'Asia/Kuwait': '🇰🇼',
        'Asia/Manama': '🇧🇭',
        'Asia/Kolkata': '🇮🇳',
        'Asia/Karachi': '🇵🇰',
        'Asia/Dhaka': '🇧🇩',
        'Asia/Bangkok': '🇹🇭',
        'Asia/Jakarta': '🇮🇩',
        'Asia/Singapore': '🇸🇬',
        'Asia/Kuala_Lumpur': '🇲🇾',
        'Asia/Hong_Kong': '🇭🇰',
        'Asia/Shanghai': '🇨🇳',
        'Asia/Chongqing': '🇨🇳',
        'Asia/Urumqi': '🇨🇳',
        'Asia/Tokyo': '🇯🇵',
        'Asia/Seoul': '🇰🇷',
        'Asia/Taipei': '🇹🇼',
        'Asia/Yangon': '🇲🇲',
        'Asia/Colombo': '🇱🇰',
        'Asia/Kathmandu': '🇳🇵',
        'Asia/Almaty': '🇰🇿',
        'Asia/Tashkent': '🇺🇿',
        'Asia/Bishkek': '🇰🇬',
        'Asia/Tehran': '🇮🇷',
        'Asia/Baghdad': '🇮🇶',
        'Asia/Beirut': '🇱🇧',
        'Asia/Amman': '🇯🇴',
        'Asia/Jerusalem': '🇮🇱',
        'Asia/Muscat': '🇴🇲',
        'Australia/Sydney': '🇦🇺',
        'Australia/Melbourne': '🇦🇺',
        'Australia/Brisbane': '🇦🇺',
        'Australia/Perth': '🇦🇺',
        'Pacific/Auckland': '🇳🇿',
        'Pacific/Fiji': '🇫🇯',
        'America/St_Johns': '🇨🇦',
        'America/Halifax': '🇨🇦',
        'America/Toronto': '🇨🇦',
        'America/Montreal': '🇨🇦',
        'America/Vancouver': '🇨🇦',
        'America/Edmonton': '🇨🇦',
        'America/Winnipeg': '🇨🇦',
        'America/New_York': '🇺🇸',
        'America/Detroit': '🇺🇸',
        'America/Chicago': '🇺🇸',
        'America/Denver': '🇺🇸',
        'America/Phoenix': '🇺🇸',
        'America/Los_Angeles': '🇺🇸',
        'America/Anchorage': '🇺🇸',
        'America/Juneau': '🇺🇸',
        'America/Honolulu': '🇺🇸',
        'America/Sao_Paulo': '🇧🇷',
        'America/Rio_Branco': '🇧🇷',
        'America/Argentina/Buenos_Aires': '🇦🇷',
        'America/Lima': '🇵🇪',
        'America/Bogota': '🇨🇴',
        'America/Caracas': '🇻🇪',
        'America/Mexico_City': '🇲🇽',
        'America/Monterrey': '🇲🇽',
        'America/Guatemala': '🇬🇹',
        'America/Panama': '🇵🇦',
        'America/Santiago': '🇨🇱',
        'America/La_Paz': '🇧🇴',
        'America/Asuncion': '🇵🇾',
        'America/Montevideo': '🇺🇾',
        'Africa/Cairo': '🇪🇬',
        'Africa/Casablanca': '🇲🇦',
        'Africa/Algiers': '🇩🇿',
        'Africa/Tunis': '🇹🇳',
        'Africa/Johannesburg': '🇿🇦',
        'Africa/Lagos': '🇳🇬',
        'Africa/Nairobi': '🇰🇪',
        'Africa/Khartoum': '🇸🇩',
        'Africa/Addis_Ababa': '🇪🇹',
        'Africa/Accra': '🇬🇭',
        'Africa/Dakar': '🇸🇳'
    };
    if (map[tz]) return map[tz];
    const region = tz.split('/')[0];
    if (region === 'Europe') return '🇪🇺';
    if (region === 'Asia') return '🌏';
    if (region === 'America') return '🌎';
    if (region === 'Africa') return '🌍';
    if (region === 'Australia' || region === 'Pacific') return '🌏';
    return '🌐';
}

const rawTimezones = typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function'
    ? Intl.supportedValuesOf('timeZone')
    : [
        'UTC',
        'Europe/London',
        'Europe/Berlin',
        'Asia/Dubai',
        'Asia/Kolkata',
        'Asia/Karachi',
        'Asia/Singapore',
        'America/New_York',
        'America/Chicago',
        'America/Los_Angeles'
    ];

const filteredTimezones = rawTimezones.filter((tz) => {
    if (tz === 'UTC' || tz === 'Etc/UTC') return true;
    if (tz.startsWith('Etc/')) return false;
    if (tz === 'GMT' || tz === 'GMT0' || tz === 'Greenwich' || tz === 'Universal' || tz === 'Zulu' || tz === 'UCT') {
        return false;
    }
    return true;
});

const timezoneOptions = filteredTimezones.map(v => ({
    value: v,
    label: `${timezoneFlag(v)} ${v}`
}));

const timezone = ref('');

const isTestingMode = ref(false);
provide('isTestingMode', isTestingMode);


const checkTestingMode = () => {
    // Check for environment variable configuration
    const envTestingMode = import.meta.env.VITE_TESTING_MODE;

    // Support both lowercase and camelCase query params
    const rawQ = route.query.testingmode ?? route.query.testingMode;
    const q = rawQ !== null && rawQ !== undefined ? String(rawQ) : null;

    if (envTestingMode === 'true') {
        isTestingMode.value = true;
    } else if (q === '1') {
        console.log('testingMode on hai');
        localStorage.setItem('testingMode', '1');
        isTestingMode.value = true;
    } else if (q === '0') {
        console.log('testingMode off hai');
        localStorage.removeItem('testingMode');
        isTestingMode.value = false;
    } else {
        isTestingMode.value = localStorage.getItem('testingMode') === '1';
    }
};

watch(() => route.query, checkTestingMode, { immediate: true });

const isAuthed = computed(() => !!authState.user);

const fetchMyDeviceIds = async () => {
    if (!isAuthed.value) return;
    try {
        const { data } = await axios.get('/web/notifications/my-device-ids');
        myDeviceIds.value = data;
    } catch (e) {
        console.error('Failed to fetch device IDs', e);
    }
};

const fetchUnreadCount = async () => {
    if (!isAuthed.value) return;

    // If user is already on the alerts page, the count should be 0 (Index.vue marks them read)
    if (route.path === '/alerts') {
        unreadCount.value = 0;
        return;
    }

    try {
        const { data } = await axios.get('/web/notifications/unread-count');
        // Double check route hasn't changed while request was in flight
        if (route.path === '/alerts') {
            unreadCount.value = 0;
        } else {
            unreadCount.value = data.count || 0;
        }
    } catch (e) {
        console.error('Failed to fetch unread count', e);
    }
};

const initTimezone = () => {
    timezone.value = getActiveTimezone();
};

const handleTimezoneChange = () => {
    setTimezonePreference(timezone.value);
    window.location.reload();
};

watch(() => route.path, (newPath) => {
    if (newPath === '/alerts') {
        unreadCount.value = 0;
    }
});

const listenForAlerts = () => {
    if (echoChannel) return;

    // Retry if Echo isn't ready yet
    if (!window.echo) {
        setTimeout(listenForAlerts, 500);
        return;
    }

    if (!authState.user || !authState.user.id) return;

    const userId = authState.user.id;
    console.log(`[App] Listening for alerts on channel: alerts.${userId}`);

    echoChannel = window.echo.private(`alerts.${userId}`)
        .listen('.alerts.updated', (payload) => {
            console.log('[App] Alerts Update Received', payload);

            // If user is on /alerts page, keep count at 0
            if (route.path === '/alerts') {
                unreadCount.value = 0;
            } else {
                // Otherwise refresh the unread count
                fetchUnreadCount();
            }
        });

    // Handle subscription errors
    if (echoChannel.subscription) {
        echoChannel.subscription.bind('pusher:subscription_error', (status) => {
            console.error('[App] Subscription error:', status);
        });
        echoChannel.subscription.bind('pusher:subscription_succeeded', () => {
            console.log('[App] Subscription succeeded');
        });
    }
};


// Dev-only broadcast ping to ensure updates flow (mirrors LiveTracking)
let broadcastPing = null;


watch(isAuthed, (val) => {
    if (val) {
        fetchMyDeviceIds().then(() => {
            fetchUnreadCount();
            listenForAlerts();
        });

        // Start ping in dev
        if (import.meta.env.DEV && !broadcastPing) {
            broadcastPing = setInterval(() => {
                axios.get('/web/notifications/broadcast').catch(() => {});
            }, 5000);
        }
    } else {
        if (echoChannel) {
            window.echo.leave(`alerts.${authState?.user?.id}`);
            echoChannel = null;
        }
        if (broadcastPing) {
            clearInterval(broadcastPing);
            broadcastPing = null;
        }
    }
});

onMounted(() => {
    if (isAuthed.value) {
        fetchMyDeviceIds().then(() => {
            fetchUnreadCount();
            listenForAlerts();
        });

        // Start ping in dev
        if (import.meta.env.DEV && !broadcastPing) {
            broadcastPing = setInterval(() => {
                axios.get('/web/notifications/broadcast').catch(() => {});
            }, 5000);
        }
    }
    initTimezone();
});

onUnmounted(() => {
    if (echoChannel && authState.user) {
        window.echo.leave(`alerts.${authState.user.id}`);
    }
    if (broadcastPing) {
        clearInterval(broadcastPing);
        broadcastPing = null;
    }
});
const isAdminOrDistributor = computed(() => {
  const rn = roleToNumber(authState?.user?.role ?? 0);
  return rn === 3 || rn === 2;
});
const isGuestPage = computed(() => route.meta?.guestOnly === true);
const pageTitle = computed(() => route.meta?.title || (route.name ? String(route.name).charAt(0).toUpperCase() + String(route.name).slice(1) : 'Dashboard'));
const role = computed(() => authState?.user?.role ?? 0);
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
const displayRoleLabel = computed(() => {
    const u = authState?.user || {};
    const lbl = u.role_label;
    return typeof lbl === 'string' && lbl.trim() ? lbl : roleLabel(role.value);
});
const displayName = computed(() => authState.user?.name || 'Profile');
const initials = computed(() => {
    const n = String(displayName.value || '').trim();
    const first = n ? n[0] : '?';
    return String(first).toUpperCase();
});
// Ensure sidebar items render only after auth has been fetched
const showLiveTracking = computed(() => authState.fetched && (roleToNumber(authState?.user?.role ?? 0) === 0 || roleToNumber(authState?.user?.role ?? 0) === 1));
const showDashboard = computed(() => authState.fetched && (roleToNumber(authState?.user?.role ?? 0) === 3 || roleToNumber(authState?.user?.role ?? 0) === 2));
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
    if (isAuthed.value) {
        await fetchMyDeviceIds();
        fetchUnreadCount();
        listenForAlerts();
    }
    if (!isGuestPage.value) {
        await nextTick();
        initTreeview();
    }
    const p = route.path || '';
});

watch(() => isGuestPage.value, async (isGuest) => {
    if (!isGuest) {
        await nextTick();
        initTreeview();
    }
});

// No manual menu-open manipulation; AdminLTE handles accordion via data attributes

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
    try {
        const body = document.body;
        body.classList.remove('sidebar-open');
        body.classList.add('sidebar-collapse');
        sidebarOpen.value = false;
    } catch {}
}

let lastToggleTs = 0;
function toggleSidebar(ev) {
    const now = Date.now();
    if (now - lastToggleTs < 300) return;
    lastToggleTs = now;
    try {
        const body = document.body;
        // Icon toggling is handled by Vue reactivity on sidebarOpen
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
    } catch {
        sidebarOpen.value = !sidebarOpen.value;
    }
}

onMounted(async () => {
    // Sync initial state with body class
    sidebarOpen.value = document.body.classList.contains('sidebar-open');
});
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
:global(body:not(.sidebar-collapse) .app-sidebar .nav-link .nav-icon) { margin-right: 0.5rem; }
:global(body:not(.sidebar-collapse) .app-sidebar .nav-link p) { display: flex; align-items: center; justify-content: space-between; }

 .timezone-select {
    min-width: 220px;
    font-size: 0.8rem;
    margin-left: 0.75rem;
    margin-right: 0.75rem;
    border-radius: 999px;
    padding: 0.25rem 1.75rem 0.25rem 0.75rem;
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    color: #111827;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    background-image: linear-gradient(45deg, transparent 50%, #6b7280 50%), linear-gradient(135deg, #6b7280 50%, transparent 50%);
    background-position: calc(100% - 14px) 50%, calc(100% - 9px) 50%;
    background-size: 5px 5px, 5px 5px;
    background-repeat: no-repeat;
}

.timezone-select:focus {
    outline: none;
    border-color: #0f766e;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
    background-color: #ffffff;
}

@media (max-width: 576px) {
    .timezone-select {
        min-width: 160px;
        font-size: 0.75rem;
        margin-left: 0.5rem;
        margin-right: 0.5rem;
    }
}

@media (min-width: 992px) {
    :global(body.sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .nav-link) { justify-content: center; }
    :global(body.sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .nav-link .nav-icon) { margin-right: 0 !important; }
}
</style>
