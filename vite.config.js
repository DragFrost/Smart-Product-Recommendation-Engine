import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  esbuild: {
    loader: 'jsx',
    include: /src\/.*\.js$/,
    exclude: [],
  },
  build: {
    outDir: 'assets',
    assetsDir: '',
    rollupOptions: {
      input: path.resolve(__dirname, 'src/index.js'),
      output: {
        entryFileNames: 'js/admin-dashboard.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/admin.css';
          }
          return '[name].[ext]';
        },
        chunkFileNames: 'js/[name].js',
      },
    },
    emptyOutDir: false,
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
