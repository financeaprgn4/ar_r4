import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  base: "/ar_r4/",
  plugins: [react()],

  server: {
    host: true,

    // Izinkan akses dari Cloudflare Tunnel
    allowedHosts: true,

    fs: {
      allow: [
        process.cwd(),
        process.cwd() + "/node_modules",
      ],
    },
  },
});