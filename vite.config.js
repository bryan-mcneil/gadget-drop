import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Heavy tool-only deps → own chunk, loaded only when a tool page renders
                    if (id.includes('cropperjs') || id.includes('react-cropper')) {
                        return 'vendor-cropper';
                    }
                    if (id.includes('react-markdown') || id.includes('remark') || id.includes('rehype') || id.includes('micromark') || id.includes('mdast') || id.includes('hast')) {
                        return 'vendor-markdown';
                    }
                    // Core React runtime → shared chunk reused across all pages
                    if (id.includes('node_modules/react/') || id.includes('node_modules/react-dom/')) {
                        return 'vendor-react';
                    }
                },
            },
        },
    },
});
