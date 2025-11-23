import { createRouter, createWebHistory } from 'vue-router';

// Views
import HomeView from '../views/HomeView.vue';
import AboutView from '../views/AboutView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
// Auth views
const ForgotPassword = () => import('../views/auth/ForgotPassword.vue');
const ResetPassword = () => import('../views/auth/ResetPassword.vue');
import DashboardView from '../views/DashboardView.vue';
import TasksView from '../views/TasksView.vue';
import SettingsView from '../views/SettingsView.vue';
import { ensureAuthenticated } from '../auth';
import { authState, hasPermission } from '../auth';
// Feature screens
const Profile = () => import('../views/ProfileView.vue');
const MonitoringVehicles = () => import('../views/monitoring/Vehicles.vue');
const MonitoringZones = () => import('../views/monitoring/Zones.vue');
const LiveTracking = () => import('../views/live-tracking/LiveTracking.vue');
const Drivers = () => import('../views/drivers/Index.vue');
const Users = () => import('../views/users/Index.vue');
const UsersPermissions = () => import('../views/users/Permissions.vue');
const Vehicles = () => import('../views/vehicles/Index.vue');
const VehiclesMaintenance = () => import('../views/vehicles/Maintenance.vue');
const VehiclesOverview = () => import('../views/vehicles/Overview.vue');
// Add Vehicle Detail route
const VehicleDetail = () => import('../views/vehicles/Detail.vue');
const Alerts = () => import('../views/alerts/Index.vue');
const Reports = () => import('../views/reports/Analytics.vue');
const Fuel = () => import('../views/fuel/Index.vue');
const Zones = () => import('../views/zones/Index.vue');
// Zones add/edit
const ZonesAdd = () => import('../views/zones/AddZone.vue');
const ZonesEdit = () => import('../views/zones/Edit.vue');
const NotFound = () => import('../views/NotFound.vue');

const routes = [
  { path: '/', name: 'home', component: LiveTracking, meta: { requiresAuth: true, moduleKey: 'live-tracking', action: 'read' } },
  { path: '/about', name: 'about', component: AboutView },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword, meta: { guestOnly: true, title: 'Forgot Password' } },
  { path: '/reset-password', name: 'reset-password', component: ResetPassword, meta: { guestOnly: true, title: 'Reset Password' } },
  { path: '/dashboard', name: 'dashboard', component: LiveTracking, meta: { requiresAuth: true, title: 'Overview', moduleKey: 'live-tracking', action: 'read' } },
  { path: '/tasks', name: 'tasks', component: TasksView, meta: { requiresAuth: true, title: 'Tasks', moduleKey: 'tasks', action: 'read' } },
  { path: '/settings', name: 'settings', component: SettingsView, meta: { requiresAuth: true, title: 'Settings', moduleKey: 'settings', action: 'read' } },
  { path: '/profile', name: 'profile', component: Profile, meta: { requiresAuth: true, title: 'Profile' } },
  // Feature screens
  { path: '/monitoring/vehicles', name: 'monitoring-vehicles', component: MonitoringVehicles, meta: { requiresAuth: true, title: 'Vehicles Monitoring', moduleKey: 'vehicles', action: 'read' } },
  { path: '/monitoring/zones', name: 'monitoring-zones', component: MonitoringZones, meta: { requiresAuth: true, title: 'Zone Monitoring', moduleKey: 'zones', action: 'read' } },
  { path: '/live-tracking', name: 'live-tracking', component: LiveTracking, meta: { requiresAuth: true, title: 'Live Tracking', roles: [1, 2, 3], moduleKey: 'live-tracking', action: 'read' } },
  { path: '/drivers', name: 'drivers', component: Drivers, meta: { requiresAuth: true, title: 'Driver Management', moduleKey: 'drivers', action: 'read' } },
  // Add Driver route
  { path: '/drivers/new', name: 'drivers-new', component: () => import('../views/drivers/AddDriver.vue'), meta: { requiresAuth: true, title: 'Add New Driver', moduleKey: 'drivers', action: 'create' } },
  // Edit Driver route
  { path: '/drivers/:driverId/edit', name: 'drivers-edit', component: () => import('../views/drivers/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Driver', moduleKey: 'drivers', action: 'update' } },
  // Users routes
  { path: '/users', name: 'users', component: Users, meta: { requiresAuth: true, title: 'User Management', moduleKey: 'users', action: 'read' } },
  { path: '/users/permissions', name: 'users-permissions', component: UsersPermissions, meta: { requiresAuth: true, title: 'User Permissions', roles: [1, 2, 3], moduleKey: 'users.permissions', action: 'update' } },
  { path: '/users/new', name: 'users-new', component: () => import('../views/users/AddUser.vue'), meta: { requiresAuth: true, title: 'Add New User', moduleKey: 'users', action: 'create' } },
  { path: '/users/:userId/edit', name: 'users-edit', component: () => import('../views/users/Edit.vue'), meta: { requiresAuth: true, title: 'Edit User', moduleKey: 'users', action: 'update' } },
  { path: '/vehicles', name: 'vehicles', component: Vehicles, meta: { requiresAuth: true, title: 'Vehicle Management', moduleKey: 'vehicles', action: 'read' } },
  { path: '/vehicles/maintenance', name: 'vehicles-maintenance', component: VehiclesMaintenance, meta: { requiresAuth: true, title: 'Vehicle Maintenance', moduleKey: 'vehicles.maintenance', action: 'read' } },
  { path: '/vehicles/overview', name: 'vehicles-overview', component: VehiclesOverview, meta: { requiresAuth: true, title: 'Vehicle Overview', moduleKey: 'vehicles.overview', action: 'read' } },
  // Add Vehicle route
  { path: '/vehicles/new', name: 'vehicles-new', component: () => import('../views/vehicles/AddVehicle.vue'), meta: { requiresAuth: true, title: 'Add New Vehicle', moduleKey: 'vehicles', action: 'create' } },
  // Vehicle Detail route
  { path: '/vehicles/:deviceId', name: 'vehicles-detail', component: VehicleDetail, meta: { requiresAuth: true, title: 'Vehicle Detail', moduleKey: 'vehicles.overview', action: 'read' } },
  // Edit Vehicle route
  { path: '/vehicles/:deviceId/edit', name: 'vehicles-edit', component: () => import('../views/vehicles/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Vehicle', moduleKey: 'vehicles', action: 'update' } },
  { path: '/reports', name: 'reports', component: Reports, meta: { requiresAuth: true, title: 'Reports & Analytics', moduleKey: 'reports', action: 'read' } },
  { path: '/alerts', name: 'alerts', component: Alerts, meta: { requiresAuth: true, title: 'Alerts & Notifications', moduleKey: 'alerts', action: 'read' } },
  { path: '/fuel', name: 'fuel', component: Fuel, meta: { requiresAuth: true, title: 'Fuel Management', moduleKey: 'fuel', action: 'read' } },
  { path: '/zones', name: 'zones', component: Zones, meta: { requiresAuth: true, title: 'Zone Management', moduleKey: 'zones', action: 'read' } },
  { path: '/zones/new', name: 'zones-new', component: ZonesAdd, meta: { requiresAuth: true, title: 'Add New Zone', moduleKey: 'zones', action: 'create' } },
  { path: '/zones/:zoneId/edit', name: 'zones-edit', component: ZonesEdit, meta: { requiresAuth: true, title: 'Edit Zone', moduleKey: 'zones', action: 'update' } },
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

  // Block guest-only pages when authenticated, except 404 page
  if (to.meta?.guestOnly && isAuthed && to.name !== 'not-found') {
    return next({ name: 'profile' });
  }

  // Role-based gating if route defines allowed roles
  const role = authState?.user?.role ?? 3;
  const allowed = Array.isArray(to.meta?.roles) ? to.meta.roles : null;
  if (allowed && !allowed.includes(Number(role))) {
    return next({ name: 'not-found', query: { error: 'forbidden', missing: to.fullPath } });
  }

  const moduleKey = to.meta?.moduleKey;
  const action = to.meta?.action || 'read';
  if (moduleKey && !hasPermission(moduleKey, action)) {
    return next({ name: 'not-found', query: { error: 'forbidden', missing: to.fullPath } });
  }

  return next();
});
