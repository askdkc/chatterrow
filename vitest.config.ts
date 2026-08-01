import { fileURLToPath, URL } from 'node:url';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        conditions: ['browser'],
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
        exclude: ['node_modules', 'vendor'],
    },
});
