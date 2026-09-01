import { cpSync, existsSync, mkdirSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const publicBuildDir = join(root, 'public', 'build');
const packagesDir = join(root, 'packages');

if (!existsSync(publicBuildDir) || !existsSync(packagesDir)) {
    process.exit(0);
}

for (const name of readdirSync(publicBuildDir, { withFileTypes: true })) {
    if (!name.isDirectory() || !name.name.startsWith('frontend-')) {
        continue;
    }

    const packageDir = join(packagesDir, name.name);
    if (!existsSync(packageDir)) {
        continue;
    }

    const source = join(publicBuildDir, name.name);
    const target = join(packageDir, 'public', 'build', name.name);

    rmSync(target, { recursive: true, force: true });
    mkdirSync(join(packageDir, 'public', 'build'), { recursive: true });
    cpSync(source, target, { recursive: true });

    console.log(`Synced ${source} -> ${target}`);
}
