import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import router from "./router";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(App);

app.use(router);
app.use(Toast, {
  transition: "Vue-Toastification__bounce",
  maxToasts: 20,
  newestOnTop: true,
});

// Theme support - listen for theme changes from parent FeatherPanel
function applyTheme(theme: 'light' | 'dark') {
  if (theme === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
}

// Listen for theme messages from parent
window.addEventListener('message', (event) => {
  if (event.data?.type === 'featherpanel-theme') {
    applyTheme(event.data.theme);
  }
});

// Signal readiness to parent to receive initial theme
if (window.parent !== window) {
  window.parent.postMessage({ type: 'featherpanel-ready' }, '*');
}

// Default to dark mode until we receive theme from parent
applyTheme('dark');

// Wait for router to be ready before mounting to ensure initial route is set
router.isReady().then(() => {
  app.mount("#app");
});
