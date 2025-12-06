import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    build: {
        emptyOutDir: true,
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        return id.toString().split('node_modules/')[1].split('/')[0].toString();
                    }
                }
            }
        }
    },
    // Dev proxy forwards app routes to Laravel backend. Target can be overridden via VITE_BACKEND_PROXY_TARGET
    server: {
        proxy: {
            '/web': { target: process.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001', changeOrigin: true },
            '/api': { target: process.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001', changeOrigin: true },
            '/storage': { target: process.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001', changeOrigin: true },
            '/sanctum': { target: process.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001', changeOrigin: true },
            // Private channel auth endpoint used by Echo
            '/broadcasting': { target: process.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001', changeOrigin: true },
        },
    },
});
