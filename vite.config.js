import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const isDocker = process.env.DOCKER === 'true';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/accesspoint.css',
                'resources/css/login.css',
                'resources/css/dashboard.css',
                'resources/js/dashboard.js',
                'resources/css/dcs/dashboard.css',
                'resources/css/dcs/register.css',
                'resources/css/dcs/update.css',
                'resources/css/dcs/edit.css',
                'resources/css/dcs/history.css',
                'resources/css/dcs/reports.css',
                'resources/css/dcs/stamping.css',
                'resources/css/dcs/database.css',
                'resources/css/dcs/settings.css',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: isDocker ? '0.0.0.0' : 'localhost',
        port: 5173,
        strictPort: true,
        cors: true,

        ...(isDocker && {
            origin: process.env.VITE_DEV_SERVER_URL ?? 'http://localhost:5173',
            hmr: {
                host: 'localhost',
                port: 5173,
                protocol: 'ws',
            },
            watch: {
                usePolling: true,
                interval: 500,
                ignored: ['**/vendor/**', '**/storage/framework/views/**'],
            },
        }),
    },
});