import { existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const inputs = [
    'resources/css/filament/admin/theme.css',
];

if (existsSync('packages/frontend-3piroga')) {
    inputs.unshift(
        'packages/frontend-3piroga/resources/css/app.css',
        'packages/frontend-3piroga/resources/js/app.js',
        'packages/frontend-3piroga/resources/js/map-cart.js',
    );
}

export default defineConfig({
    plugins: [
        laravel({
            input: inputs,
            refresh: true,
            buildDirectory: 'build',
        }),
    ],
    base: '/build/',
});