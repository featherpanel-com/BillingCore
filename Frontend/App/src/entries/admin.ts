import { createApp } from "vue";
import "../style.css";
import Admin from "../pages/Admin.vue";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(Admin);

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

// Remove all backgrounds
document.body.style.background = "transparent";
document.documentElement.style.background = "transparent";
if (document.body.parentElement) {
  document.body.parentElement.style.background = "transparent";
}

app.mount("#app");

