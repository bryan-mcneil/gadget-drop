import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // app.js   → public site core (Alpine + Livewire-driven), every page
            // tools.js → free-tool Alpine components, loaded only on /tools/* pages
            // app.jsx  → admin (Inertia/React, retained during the hybrid)
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/tools.js', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // cropperjs is lazy-imported by the Alpine image-cropper tool → own chunk
                    if (id.includes('cropperjs')) {
                        return 'vendor-cropper';
                    }
                    // Core React runtime → shared chunk reused across all admin pages
                    if (id.includes('node_modules/react/') || id.includes('node_modules/react-dom/')) {
                        return 'vendor-react';
                    }
                },
            },
        },
    },
});
