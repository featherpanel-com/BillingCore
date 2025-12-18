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

// Enable dark mode by default
document.documentElement.classList.add("dark");

app.mount("#app");

