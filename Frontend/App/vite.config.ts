import path from "node:path";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  base: "./",
  build: {
    outDir: "../Components/billingcore/dist/",
    emptyOutDir: true,
    rollupOptions: {
      input: {
        "billing-info": "./billing-info.html",
        invoices: "./invoices.html",
        admin: "./admin.html",
        "admin-invoices": "./admin-invoices.html",
      },
    },
  },
});
