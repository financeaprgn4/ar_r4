import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  base: process.env.GITHUB_ACTIONS === "true" ? "/ar_r4/" : "/",

  plugins: [react()],

  server: {
    host: true,
    allowedHosts: true,

    fs: {
      allow: [
        process.cwd(),
        process.cwd() + "/node_modules",
      ],
    },
  },
});