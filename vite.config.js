import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// Builds the React app into assets/dist with a manifest.json that PHP reads
// to find the (hashed) JS/CSS file names.
export default defineConfig({
  plugins: [react()],
  base: "/assets/dist/",
  build: {
    outDir: "assets/dist",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: "src/main.jsx",
    },
  },
});
