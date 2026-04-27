import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    emptyOutDir: false,
    outDir: '../view/frontend/web/js',
    sourcemap: true,
    lib: {
      entry: './src/main.jsx',
      name: 'PortfolioSearchAutocomplete',
      formats: ['iife'],
      fileName: () => 'search-autocomplete.bundle.js',
    },
  },
});
