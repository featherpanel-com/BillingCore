import { createApp } from "vue";
import "../style.css";
import Invoices from "../pages/Invoices.vue";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(Invoices);

app.use(Toast, {
  transition: "Vue-Toastification__bounce",
  maxToasts: 20,
  newestOnTop: true,
});

// Enable dark mode by default
document.documentElement.classList.add("dark");
// Remove all backgrounds
document.body.style.background = "transparent";
document.documentElement.style.background = "transparent";
if (document.body.parentElement) {
  document.body.parentElement.style.background = "transparent";
}

app.mount("#app");
