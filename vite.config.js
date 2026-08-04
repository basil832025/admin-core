import { existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const adminInputs = [
    'resources/css/filament/admin/theme.css',
];

const frontendThreePirogaInputs = [
    'packages/frontend-3piroga/resources/css/app.css',
    'packages/frontend-3piroga/resources/js/app.js',
    'packages/frontend-3piroga/resources/js/map-cart.js',
];

export default defineConfig(({ mode }) => {
    const isFrontendThreePirogaBuild = mode === 'frontend-3piroga' && existsSync('packages/frontend-3piroga');
    const input = isFrontendThreePirogaBuild
        ? frontendThreePirogaInputs
        : (mode === 'admin' ? adminInputs : [...frontendThreePirogaInputs, ...adminInputs]);
    const buildDirectory = isFrontendThreePirogaBuild ? 'build/frontend-3piroga' : 'build';

    return {
        plugins: [
            laravel({
                input,
                refresh: true,
                buildDirectory,
            }),
        ],
        base: `/${buildDirectory}/`,
    };
});