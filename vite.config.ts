import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

import type { Plugin } from 'vite';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));
const wayfinderLocked = path.join(projectRoot, 'scripts', 'wayfinder-generate-locked.php');

/**
 * Remove consecutive duplicate wayfinder import lines left by a race between
 * overlapping `wayfinder:generate` processes (fixes Babel duplicate declaration).
 */
function dedupeWayfinderBarrelImports(viteRoot: string): void {
    const targets = [
        path.join(viteRoot, 'resources/js/routes/index.ts'),
        path.join(viteRoot, 'resources/js/actions/index.ts'),
    ];

    for (const filePath of targets) {
        if (!existsSync(filePath)) {
            continue;
        }

        const src = readFileSync(filePath, 'utf8');
        const lines = src.split(/\r?\n/);
        const next: string[] = [];

        for (const line of lines) {
            if (
                next.length > 0 &&
                line === next[next.length - 1] &&
                line.trimStart().startsWith('import ') &&
                line.includes('wayfinder')
            ) {
                continue;
            }

            next.push(line);
        }

        const out = next.join('\n');

        if (out !== src) {
            writeFileSync(filePath, out);
        }
    }
}

function dedupeWayfinderImportsPlugin(): Plugin {
    let root = projectRoot;

    return {
        name: 'dedupe-wayfinder-imports',
        enforce: 'post',
        configResolved(config) {
            root = config.root;
        },
        async buildStart() {
            dedupeWayfinderBarrelImports(root);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            /** Must run locked: Vite may trigger generate twice concurrently and double-prepend imports. */
            command: `php "${wayfinderLocked.replaceAll('\\', '/')}"`,
        }),
        dedupeWayfinderImportsPlugin(),
    ],
});
