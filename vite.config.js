import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync } from 'fs';

export default defineConfig(() => {
    // Re-read package.json inside the config function to ensure fresh version on every build
    const packageJson = JSON.parse(readFileSync('./package.json'));
    const version = packageJson.version;

    return {
        define: {
            'import.meta.env.VITE_APP_VERSION': JSON.stringify(version),
            'import.meta.env.VITE_BUILD_TIMESTAMP': JSON.stringify(new Date().toISOString()),
        },
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
    };
});
 