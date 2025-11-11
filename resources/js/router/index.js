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
import { authState } from '../auth';
// Feature screens
const MonitoringVehicles = () => import('../views/monitoring/Vehicles.vue');
const MonitoringZones = () => import('../views/monitoring/Zones.vue');
const LiveTracking = () => import('../views/live-tracking/LiveTracking.vue');
const Drivers = () => import('../views/drivers/Index.vue');
const Users = () => import('../views/users/Index.vue');
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

const routes = [
  { path: '/', name: 'home', component: LiveTracking, meta: { requiresAuth: true } },
  { path: '/about', name: 'about', component: AboutView },
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword, meta: { guestOnly: true, title: 'Forgot Password' } },
  { path: '/reset-password', name: 'reset-password', component: ResetPassword, meta: { guestOnly: true, title: 'Reset Password' } },
  { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'Overview' } },
  { path: '/tasks', name: 'tasks', component: TasksView, meta: { requiresAuth: true } },
  { path: '/settings', name: 'settings', component: SettingsView, meta: { requiresAuth: true } },
  // Feature screens
  { path: '/monitoring/vehicles', name: 'monitoring-vehicles', component: MonitoringVehicles, meta: { requiresAuth: true, title: 'Vehicles Monitoring' } },
  { path: '/monitoring/zones', name: 'monitoring-zones', component: MonitoringZones, meta: { requiresAuth: true, title: 'Zone Monitoring' } },
  { path: '/live-tracking', name: 'live-tracking', component: LiveTracking, meta: { requiresAuth: true, title: 'Live Tracking', roles: [1, 2, 3] } },
  { path: '/drivers', name: 'drivers', component: Drivers, meta: { requiresAuth: true, title: 'Driver Management' } },
  // Add Driver route
  { path: '/drivers/new', name: 'drivers-new', component: () => import('../views/drivers/AddDriver.vue'), meta: { requiresAuth: true, title: 'Add New Driver' } },
  // Edit Driver route
  { path: '/drivers/:driverId/edit', name: 'drivers-edit', component: () => import('../views/drivers/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Driver' } },
  // Users routes
  { path: '/users', name: 'users', component: Users, meta: { requiresAuth: true, title: 'User Management' } },
  { path: '/users/new', name: 'users-new', component: () => import('../views/users/AddUser.vue'), meta: { requiresAuth: true, title: 'Add New User' } },
  { path: '/users/:userId/edit', name: 'users-edit', component: () => import('../views/users/Edit.vue'), meta: { requiresAuth: true, title: 'Edit User' } },
  { path: '/vehicles', name: 'vehicles', component: Vehicles, meta: { requiresAuth: true, title: 'Vehicle Management' } },
  { path: '/vehicles/maintenance', name: 'vehicles-maintenance', component: VehiclesMaintenance, meta: { requiresAuth: true, title: 'Vehicle Maintenance' } },
  { path: '/vehicles/overview', name: 'vehicles-overview', component: VehiclesOverview, meta: { requiresAuth: true, title: 'Vehicle Overview' } },
  // Add Vehicle route
  { path: '/vehicles/new', name: 'vehicles-new', component: () => import('../views/vehicles/AddVehicle.vue'), meta: { requiresAuth: true, title: 'Add New Vehicle' } },
  // Vehicle Detail route
  { path: '/vehicles/:deviceId', name: 'vehicles-detail', component: VehicleDetail, meta: { requiresAuth: true, title: 'Vehicle Detail' } },
  // Edit Vehicle route
  { path: '/vehicles/:deviceId/edit', name: 'vehicles-edit', component: () => import('../views/vehicles/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Vehicle' } },
  { path: '/reports', name: 'reports', component: Reports, meta: { requiresAuth: true, title: 'Reports & Analytics' } },
  { path: '/alerts', name: 'alerts', component: Alerts, meta: { requiresAuth: true, title: 'Alerts & Notifications' } },
  { path: '/fuel', name: 'fuel', component: Fuel, meta: { requiresAuth: true, title: 'Fuel Management' } },
  { path: '/zones', name: 'zones', component: Zones, meta: { requiresAuth: true, title: 'Zone Management' } },
  { path: '/zones/new', name: 'zones-new', component: ZonesAdd, meta: { requiresAuth: true, title: 'Add New Zone' } },
  { path: '/zones/:zoneId/edit', name: 'zones-edit', component: ZonesEdit, meta: { requiresAuth: true, title: 'Edit Zone' } },
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

  // Block guest-only pages when authenticated
  if (to.meta?.guestOnly && isAuthed) {
    return next({ name: 'live-tracking' });
  }

  // Role-based gating if route defines allowed roles
  const role = authState?.user?.role ?? 3;
  const allowed = Array.isArray(to.meta?.roles) ? to.meta.roles : null;
  if (allowed && !allowed.includes(Number(role))) {
    return next({ name: 'dashboard' });
  }

  return next();
});
