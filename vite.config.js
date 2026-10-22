import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '0.0.0.0',          // listen on all interfaces
        port: 5173,
        strictPort: true,
        cors: true,               // allow cross-origin requests from :8000
        hmr: {
            host: '192.168.1.219', // your LAN IP
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
