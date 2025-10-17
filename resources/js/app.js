import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';

const app = createApp(App);
app.use(router);
app.mount('#app');

// Helper to apply AdminLTE body classes based on route
function setBodyClasses(to) {
  const body = document.body;
  if (to.meta?.guestOnly) {
    const guestClass = to.name === 'register' ? 'register-page' : 'login-page';
    body.className = `${guestClass} bg-body-secondary`;
  } else {
    body.className = 'layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary';
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