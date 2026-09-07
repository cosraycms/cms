import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	plugins: [
		svelte({
			dynamicCompileOptions({ filename }) {
				if (filename.includes('/src/elements/')) {
					return { customElement: true };
				}
			},
		}),
	],
	resolve: {
		conditions: ['browser'],
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$components: path.resolve(root, 'src/components'),
		},
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
		setupFiles: ['tests/setup.ts'],
	},
});
