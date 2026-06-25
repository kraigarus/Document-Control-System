import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/portal.css',
                'resources/css/dcs/login.css',
                
                'resources/js/dcs/calendar.js',
                
                'resources/js/dcs/dashboard.js',
                'resources/css/dcs/dashboard.css',

                'resources/css/dcs/sidebar.css',
                'resources/js/dcs/sidebar.js',

                'resources/css/dcs/header.css',
                'resources/js/dcs/header.js',

                'resources/css/dcs/register.css',
                'resources/js/dcs/register.js',

                'resources/css/dcs/update.css',
                'resources/js/dcs/update.js',

                'resources/css/dcs/edit.css',
                'resources/js/dcs/edit.js',
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