import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/portal.css',
                'resources/css/login.css',
                'resources/css/dashboard.css',
                'resources/js/calendar.js',
                'resources/js/dashboard.js',
                'resources/css/sidebar.css',
                'resources/js/sidebar.js',
                'resources/css/header.css',
                'resources/js/header.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: process.env.DOCKER ? '0.0.0.0' : 'localhost',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
            interval: 800,
            ignored: ['**/storage/framework/views/**'],
        },
    },
})