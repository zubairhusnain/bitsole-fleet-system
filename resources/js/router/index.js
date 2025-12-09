import { createRouter, createWebHistory } from 'vue-router';

// Views
import AboutView from '../views/AboutView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
// Auth views
const ForgotPassword = () => import('../views/auth/ForgotPassword.vue');
const ResetPassword = () => import('../views/auth/ResetPassword.vue');
// Dashboard now renders LiveTracking component
import TasksView from '../views/TasksView.vue';
import SettingsView from '../views/SettingsView.vue';
import { ensureAuthenticated } from '../auth';
import { authState, hasPermission, roleToNumber } from '../auth';
// Feature screens
const Profile = () => import('../views/ProfileView.vue');
const MonitoringVehicles = () => import('../views/monitoring/Vehicles.vue');
const MonitoringZones = () => import('../views/monitoring/Zones.vue');
const LiveTracking = () => import('../views/live-tracking/LiveTracking.vue');
const Dashboard = () => import('../views/Dashboard.vue');
const Drivers = () => import('../views/drivers/Index.vue');
const Users = () => import('../views/users/Index.vue');
const UsersPermissions = () => import('../views/users/Permissions.vue');
const Vehicles = () => import('../views/vehicles/Index.vue');
const VehiclesMaintenance = () => import('../views/vehicles/Maintenance.vue');
const VehiclesOverview = () => import('../views/vehicles/Overview.vue');
// Add Vehicle Detail route
const VehicleDetail = () => import('../views/vehicles/Detail.vue');
const VehicleSettings = () => import('../views/vehicles/Settings.vue');
const Alerts = () => import('../views/alerts/Index.vue');
const Reports = () => import('../views/reports/Analytics.vue');
const Fuel = () => import('../views/fuel/Index.vue');
const Zones = () => import('../views/zones/Index.vue');
// Zones add/edit
const ZonesAdd = () => import('../views/zones/AddZone.vue');
const ZonesEdit = () => import('../views/zones/Edit.vue');
const NotFound = () => import('../views/NotFound.vue');
const TelemetryCodec8 = () => import('../views/telemetry/Codec8Tool.vue');

const routes = [
  { path: '/', name: 'home', component: LiveTracking, meta: { requiresAuth: true, title: 'Home', roles: [0, 1] } },
  { path: '/about', name: 'about', component: AboutView },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword, meta: { guestOnly: true, title: 'Forgot Password' } },
  { path: '/reset-password', name: 'reset-password', component: ResetPassword, meta: { guestOnly: true, title: 'Reset Password' } },
  { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true, title: 'Dashboard', roles: [2, 3] } },
  { path: '/tasks', name: 'tasks', component: TasksView, meta: { requiresAuth: true, title: 'Tasks', moduleKey: 'tasks', action: 'read' } },
  { path: '/settings', name: 'settings', component: SettingsView, meta: { requiresAuth: true, title: 'Settings', roles: [3], moduleKey: 'settings', action: 'read' } },
  { path: '/profile', name: 'profile', component: Profile, meta: { requiresAuth: true, title: 'Profile' } },
  // Feature screens
  { path: '/monitoring/vehicles', name: 'monitoring-vehicles', component: MonitoringVehicles, meta: { requiresAuth: true, title: 'Vehicles Monitoring', moduleKey: 'vehicles', action: 'read' } },
  { path: '/monitoring/zones', name: 'monitoring-zones', component: MonitoringZones, meta: { requiresAuth: true, title: 'Zone Monitoring', moduleKey: 'zones', action: 'read' } },
  { path: '/live-tracking', name: 'live-tracking', component: LiveTracking, meta: { requiresAuth: true, title: 'Live Tracking', roles: [0, 1] } },
  { path: '/drivers', name: 'drivers', component: Drivers, meta: { requiresAuth: true, title: 'Driver Management', moduleKey: 'drivers', action: 'read' } },
  { path: '/vehicles/:deviceId(\\d+)/settings', name: 'vehicle-settings', component: VehicleSettings, meta: { requiresAuth: true, title: 'Vehicle Settings', moduleKey: 'vehicles', action: 'read' } },
  // Add Driver route
  { path: '/drivers/new', name: 'drivers-new', component: () => import('../views/drivers/AddDriver.vue'), meta: { requiresAuth: true, title: 'Add New Driver', moduleKey: 'drivers', action: 'create' } },
  // Edit Driver route (numeric id only)
  { path: '/drivers/:driverId(\\d+)/edit', name: 'drivers-edit', component: () => import('../views/drivers/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Driver', moduleKey: 'drivers', action: 'update' } },
  // Users routes
  { path: '/users', name: 'users', component: Users, meta: { requiresAuth: true, title: 'User Management', moduleKey: 'users', action: 'read' } },
  { path: '/users/permissions', name: 'users-permissions', component: UsersPermissions, meta: { requiresAuth: true, title: 'User Permissions', roles: [1, 2, 3], moduleKey: 'users.permissions', action: 'update' } },
  { path: '/users/new', name: 'users-new', component: () => import('../views/users/AddUser.vue'), meta: { requiresAuth: true, title: 'Add New User', moduleKey: 'users', action: 'create' } },
  // Edit User route (numeric id only)
  { path: '/users/:userId(\\d+)/edit', name: 'users-edit', component: () => import('../views/users/Edit.vue'), meta: { requiresAuth: true, title: 'Edit User', moduleKey: 'users', action: 'update' } },
  { path: '/vehicles', name: 'vehicles', component: Vehicles, meta: { requiresAuth: true, title: 'Vehicle Management', moduleKey: 'vehicles', action: 'read' } },
  { path: '/vehicles/maintenance', name: 'vehicles-maintenance', component: VehiclesMaintenance, meta: { requiresAuth: true, title: 'Vehicle Maintenance', moduleKey: 'vehicles.maintenance', action: 'read' } },
  { path: '/vehicles/overview', name: 'vehicles-overview', component: VehiclesOverview, meta: { requiresAuth: true, title: 'Vehicle Overview', moduleKey: 'vehicles.overview', action: 'read' } },
  // Add Vehicle route
  { path: '/vehicles/new', name: 'vehicles-new', component: () => import('../views/vehicles/AddVehicle.vue'), meta: { requiresAuth: true, title: 'Add New Vehicle', moduleKey: 'vehicles', action: 'create' } },
  // Vehicle Detail route (numeric id only)
  { path: '/vehicles/:deviceId(\\d+)', name: 'vehicles-detail', component: VehicleDetail, meta: { requiresAuth: true, title: 'Vehicle Detail', moduleKey: 'vehicles.overview', action: 'read' } },
  // Edit Vehicle route (numeric id only)
  { path: '/vehicles/:deviceId(\\d+)/edit', name: 'vehicles-edit', component: () => import('../views/vehicles/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Vehicle', moduleKey: 'vehicles', action: 'update' } },
  { path: '/reports', name: 'reports', component: Reports, meta: { requiresAuth: true, title: 'Reports & Analytics', moduleKey: 'reports', action: 'read' } },
  { path: '/alerts', name: 'alerts', component: Alerts, meta: { requiresAuth: true, title: 'Alerts & Notifications' } },
  { path: '/fuel', name: 'fuel', component: Fuel, meta: { requiresAuth: true, title: 'Fuel Management', moduleKey: 'fuel', action: 'read' } },
  { path: '/fuel/new', name: 'fuel-new', component: () => import('../views/fuel/AddFuel.vue'), meta: { requiresAuth: true, title: 'Add Fuel Entry', moduleKey: 'fuel', action: 'create' } },
  { path: '/fuel/:id(\\d+)/edit', name: 'fuel-edit', component: () => import('../views/fuel/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Fuel Entry', moduleKey: 'fuel', action: 'update' } },
  { path: '/zones', name: 'zones', component: Zones, meta: { requiresAuth: true, title: 'Zone Management', moduleKey: 'zones', action: 'read' } },
  { path: '/zones/new', name: 'zones-new', component: ZonesAdd, meta: { requiresAuth: true, title: 'Add New Zone', moduleKey: 'zones', action: 'create' } },
  // Edit Zone route (numeric id only)
  { path: '/zones/:zoneId(\\d+)/edit', name: 'zones-edit', component: ZonesEdit, meta: { requiresAuth: true, title: 'Edit Zone', moduleKey: 'zones', action: 'update' } },
  { path: '/telemetry/codec8', name: 'telemetry-codec8', component: TelemetryCodec8, meta: { requiresAuth: true, title: 'Codec 8E Decoder' } },
  { path: '/404', name: 'not-found', component: NotFound, meta: { title: 'Not Found', guestOnly: true } },
  { path: '/:pathMatch(.*)*', redirect: (to) => ({ path: '/404', query: { missing: to.fullPath || to.path || '' } }) },
];

const router = createRouter({
  history: createWebHistory('/'),
  routes,
});

export default router;

// Global route guard to protect routes with meta.requiresAuth
router.beforeEach(async (to, from, next) => {
  const isAuthed = await ensureAuthenticated();

  // Block protected pages when not authenticated
  if (to.meta?.requiresAuth && !isAuthed) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  // Block guest-only pages when authenticated (login/register) → go to home, not profile
  if (to.meta?.guestOnly && isAuthed) {
    if (to.name === 'not-found') {
      const missing = to.query?.missing || to.fullPath || to.path || '';
      return next({ name: 'profile', query: { error: 'route_not_exist', missing } });
    }
    return next({ path: '/' });
  }

  // Role-based gating and redirects
  const role = authState?.user?.role ?? 0;
  const roleNum = roleToNumber(role);
  if ((roleNum === 3 || roleNum === 2) && (to.name === 'home' || to.name === 'live-tracking')) {
    return next({ name: 'dashboard' });
  }
  // Redirect non-admin/distributor away from dashboard
  if ((to.name === 'dashboard') && (roleNum !== 3 && roleNum !== 2)) {
    return next({ name: 'live-tracking' });
  }
  // Role-based gating if route defines allowed roles
  const allowed = Array.isArray(to.meta?.roles) ? to.meta.roles : null;
  if (allowed && !allowed.includes(roleNum)) {
    return next({ name: 'not-found', query: { error: 'forbidden', missing: to.fullPath } });
  }

  const moduleKey = to.meta?.moduleKey;
  const action = to.meta?.action || 'read';
  if (moduleKey && !hasPermission(moduleKey, action)) {
    return next({ name: 'not-found', query: { error: 'forbidden', missing: to.fullPath } });
  }

  return next();
});

// Handle dynamic import (chunk) load failures globally
router.onError((err, to) => {
  const msg = String(err?.message || '');
  const isChunkError =
    msg.includes('Failed to fetch dynamically imported module') ||
    msg.includes('ChunkLoadError') ||
    /Loading chunk .* failed/i.test(msg);
  if (!isChunkError) return;
  try {
    const already = sessionStorage.getItem('chunkReloaded');
    if (already !== '1') {
      sessionStorage.setItem('chunkReloaded', '1');
      window.location.reload();
      return;
    }
  } catch {}
  const missing = to?.fullPath || to?.path || '';
  router.replace({ name: 'profile', query: { error: 'chunk_load_failed', missing } }).catch(() => {});
});
