import { createRouter, createWebHashHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    name: "BillingInfo",
    component: () => import("@/pages/BillingInfo.vue"),
  },
  {
    path: "/invoices",
    name: "Invoices",
    component: () => import("@/pages/Invoices.vue"),
  },
  {
    path: "/admin",
    name: "Admin",
    component: () => import("@/pages/Admin.vue"),
  },
  {
    path: "/admin/invoices",
    name: "AdminInvoices",
    component: () => import("@/pages/AdminInvoices.vue"),
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

// Function to get initial route from parent window or URL
function getInitialRoute(): string {
  // Check URL hash first (highest priority)
  const hash = window.location.hash.replace("#", "");
  if (hash && routes.some((r) => r.path === hash)) {
    return hash;
  }

  // Check query params
  const params = new URLSearchParams(window.location.search);
  const route = params.get("route");
  if (route && routes.some((r) => r.path === route)) {
    return route;
  }

  // Try to get route from parent window (if in iframe)
  // This is the most reliable method when loaded in FeatherPanel's iframe
  try {
    if (window.parent !== window) {
      const parentPath = window.parent.location.pathname;
      console.log("[BillingCore Router] Parent pathname:", parentPath);

      // Check for invoices routes first (more specific)
      if (parentPath.includes("/billingcore/invoices")) {
        if (parentPath.includes("/admin")) {
          console.log("[BillingCore Router] Detected admin invoices route");
          return "/admin/invoices";
        }
        console.log("[BillingCore Router] Detected client invoices route");
        return "/invoices";
      }

      // Check for admin routes
      if (
        parentPath.includes("/admin") &&
        parentPath.includes("/billingcore")
      ) {
        // If it's just /admin/billingcore (not invoices), show admin dashboard
        if (!parentPath.includes("/invoices")) {
          console.log("[BillingCore Router] Detected admin dashboard route");
          return "/admin";
        }
      }

      // Check for client billingcore (not invoices)
      if (
        parentPath.includes("/billingcore") &&
        !parentPath.includes("/invoices") &&
        !parentPath.includes("/admin")
      ) {
        console.log("[BillingCore Router] Detected client billing info route");
        return "/";
      }
    }
  } catch (e) {
    // Cross-origin iframe, can't access parent - will use postMessage listener
    console.log("[BillingCore Router] Cannot access parent window:", e);
  }

  // Check current window pathname as fallback
  const pathname = window.location.pathname;
  if (pathname.includes("/billingcore/invoices")) {
    if (pathname.includes("/admin")) {
      return "/admin/invoices";
    }
    return "/invoices";
  }
  if (pathname.includes("/billingcore") && pathname.includes("/admin")) {
    return "/admin";
  }

  console.log("[BillingCore Router] Defaulting to root route");
  return "/";
}

// Listen for postMessage from parent window
window.addEventListener("message", (event) => {
  // Verify origin if needed
  if (event.data && event.data.type === "billingcore-route") {
    const route = event.data.route;
    if (route && routes.some((r) => r.path === route)) {
      router.push(route);
    }
  }
});

// Set initial route - wait for router to be ready
router.isReady().then(() => {
  const initialRoute = getInitialRoute();
  const currentHash = window.location.hash.replace("#", "");

  // Only set route if hash is empty or doesn't match a valid route
  if (!currentHash || !routes.some((r) => r.path === currentHash)) {
    if (initialRoute !== "/" || currentHash === "") {
      console.log(
        "[BillingCore Router] Setting initial route to:",
        initialRoute
      );
      router.replace(initialRoute).catch((err) => {
        console.error("[BillingCore Router] Error setting route:", err);
      });
    }
  }
});

// Also listen for route changes from parent window
let lastParentPath = "";
const checkParentRoute = () => {
  try {
    if (window.parent !== window) {
      const parentPath = window.parent.location.pathname;
      if (parentPath !== lastParentPath) {
        lastParentPath = parentPath;
        const route = getInitialRoute();
        const currentRoute = router.currentRoute.value.path;
        if (route !== currentRoute) {
          console.log(
            "[BillingCore Router] Parent route changed, updating to:",
            route
          );
          router.replace(route);
        }
      }
    }
  } catch (e) {
    // Ignore cross-origin errors
  }
};

// Check parent route periodically (when in iframe)
if (window.parent !== window) {
  setInterval(checkParentRoute, 500);
}

export default router;
