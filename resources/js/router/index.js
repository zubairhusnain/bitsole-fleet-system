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
// Feature screens
const MonitoringVehicles = () => import('../views/monitoring/Vehicles.vue');
const MonitoringZones = () => import('../views/monitoring/Zones.vue');
const LiveTracking = () => import('../views/live-tracking/LiveTracking.vue');
const Drivers = () => import('../views/drivers/Index.vue');
const Vehicles = () => import('../views/vehicles/Index.vue');
const VehiclesMaintenance = () => import('../views/vehicles/Maintenance.vue');
const VehiclesOverview = () => import('../views/vehicles/Overview.vue');
const Alerts = () => import('../views/alerts/Index.vue');
const Reports = () => import('../views/reports/Analytics.vue');
const Fuel = () => import('../views/fuel/Index.vue');
const Zones = () => import('../views/zones/Index.vue');

const routes = [
  { path: '/', name: 'home', component: HomeView },
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
  { path: '/live-tracking', name: 'live-tracking', component: LiveTracking, meta: { requiresAuth: true, title: 'Live Tracking' } },
  { path: '/drivers', name: 'drivers', component: Drivers, meta: { requiresAuth: true, title: 'Driver Management' } },
  // Add Driver route
  { path: '/drivers/new', name: 'drivers-new', component: () => import('../views/drivers/AddDriver.vue'), meta: { requiresAuth: true, title: 'Add New Driver' } },
  { path: '/vehicles', name: 'vehicles', component: Vehicles, meta: { requiresAuth: true, title: 'Vehicle Management' } },
  { path: '/vehicles/maintenance', name: 'vehicles-maintenance', component: VehiclesMaintenance, meta: { requiresAuth: true, title: 'Vehicle Maintenance' } },
  { path: '/vehicles/overview', name: 'vehicles-overview', component: VehiclesOverview, meta: { requiresAuth: true, title: 'Vehicle Overview' } },
  // Add Vehicle route
  { path: '/vehicles/new', name: 'vehicles-new', component: () => import('../views/vehicles/AddVehicle.vue'), meta: { requiresAuth: true, title: 'Add New Vehicle' } },
  // Edit Vehicle route
  { path: '/vehicles/:deviceId/edit', name: 'vehicles-edit', component: () => import('../views/vehicles/Edit.vue'), meta: { requiresAuth: true, title: 'Edit Vehicle' } },
  { path: '/reports', name: 'reports', component: Reports, meta: { requiresAuth: true, title: 'Reports & Analytics' } },
  { path: '/alerts', name: 'alerts', component: Alerts, meta: { requiresAuth: true, title: 'Alerts & Notifications' } },
  { path: '/fuel', name: 'fuel', component: Fuel, meta: { requiresAuth: true, title: 'Fuel Management' } },
  { path: '/zones', name: 'zones', component: Zones, meta: { requiresAuth: true, title: 'Zone Management' } },
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
    return next({ name: 'dashboard' });
  }

  return next();
});