import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'packages/frontend-3piroga/resources/css/app.css',
                'packages/frontend-3piroga/resources/js/app.js',
                'packages/frontend-3piroga/resources/js/map-cart.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            buildDirectory: 'build',
        }),
    ],
    base: '/build/',
});
