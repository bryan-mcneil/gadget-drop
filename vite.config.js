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
                    // NOTE: do NOT add manualChunks entries for mammoth/jsPDF (the
                    // docx-to-pdf engine's deps). Rollup already isolates them behind
                    // the dynamic import of lib/docx-pdf.js; forcing named chunks made
                    // it park Vite's preload helper in the jsPDF chunk, which turned
                    // 400 kB into a STATIC import of the tools.js entry — i.e. every
                    // tool page paid for the PDF library. Verify with:
                    //   manifest.json → resources/js/tools.js → imports
                    // which must stay limited to the rolldown runtime.
                    // Core React runtime → shared chunk reused across all admin pages
                    if (id.includes('node_modules/react/') || id.includes('node_modules/react-dom/')) {
                        return 'vendor-react';
                    }
                },
            },
        },
    },
});
