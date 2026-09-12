import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
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

function syncFrontendPackageBuild(packageName, enabled) {
    return {
        name: `sync-${packageName}-package-build`,
        buildStart() {
            if (!enabled) {
                return;
            }

            rmSync(join(process.cwd(), 'public', 'build', packageName), { recursive: true, force: true });
        },
        closeBundle() {
            if (!enabled) {
                return;
            }

            const source = join(process.cwd(), 'public', 'build', packageName);
            const target = join(process.cwd(), 'packages', packageName, 'public', 'build', packageName);

            if (!existsSync(source)) {
                return;
            }

            rmSync(target, { recursive: true, force: true });
            mkdirSync(join(process.cwd(), 'packages', packageName, 'public', 'build'), { recursive: true });
            cpSync(source, target, { recursive: true });

            console.log(`Synced ${source} -> ${target}`);
        },
    };
}

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
    const isPackageFrontendBuild = isFrontendThreePirogaBuild || isFrontendSeviaBuild;

    return {
        plugins: [
            laravel({
                input,
                refresh: true,
                buildDirectory,
            }),
            syncFrontendPackageBuild('frontend-3piroga', isFrontendThreePirogaBuild),
            syncFrontendPackageBuild('frontend-sevia', isFrontendSeviaBuild),
        ],
        build: {
            emptyOutDir: !isPackageFrontendBuild,
        },
        base: `/${buildDirectory}/`,
    };
});
