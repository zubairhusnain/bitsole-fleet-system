import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { formatDateTime, formatDate, formatTime } from './utils/datetime';

const app = createApp(App);

// Make date formatters available globally in templates
app.config.globalProperties.$formatDateTime = formatDateTime;
app.config.globalProperties.$formatDate = formatDate;
app.config.globalProperties.$formatTime = formatTime;

app.use(router);
app.mount('#app');

// Helper to apply AdminLTE body classes based on route
function setBodyClasses(to) {
  const body = document.body;
  const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
  if (to.meta?.guestOnly) {
    const guestClass = to.name === 'register' ? 'register-page' : 'login-page';
    body.className = `${guestClass} bg-body-secondary`;
  } else {
    const keepOpen = !isMobile && body.classList.contains('sidebar-open');
    const sidebarStateClass = isMobile ? 'sidebar-collapse' : (keepOpen ? 'sidebar-open' : 'sidebar-collapse');
    body.className = `layout-fixed sidebar-expand-lg sidebar-mini ${sidebarStateClass} bg-body-tertiary`;
  }
}

// After Vue mounts, set body classes and load AdminLTE JS
router.isReady().then(() => {
  const to = router.currentRoute.value;
  setBodyClasses(to);
  // Load AdminLTE JS after body classes are set
  import('./plugins/adminlte.min.js');
  router.afterEach((to) => setBodyClasses(to));
});
