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

const frontendSeviaInputs = [
    'packages/frontend-sevia/resources/css/app.css',
    'packages/frontend-sevia/resources/js/app.js',
];

export default defineConfig(({ mode }) => {
    process.env.TAILWIND_BUILD_MODE = mode;

    const isFrontendThreePirogaBuild = mode === 'frontend-3piroga' && existsSync('packages/frontend-3piroga');
    const isFrontendSeviaBuild = mode === 'frontend-sevia' && existsSync('packages/frontend-sevia');
    const input = isFrontendThreePirogaBuild
        ? frontendThreePirogaInputs
        : (isFrontendSeviaBuild
            ? frontendSeviaInputs
            : (mode === 'admin' ? adminInputs : [...frontendThreePirogaInputs, ...frontendSeviaInputs, ...adminInputs]));
    const buildDirectory = isFrontendThreePirogaBuild
        ? 'build/frontend-3piroga'
        : (isFrontendSeviaBuild ? 'build/frontend-sevia' : 'build');

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
