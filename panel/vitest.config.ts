import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Deliberately not merged with vite.config.ts: the behaviors and lib
// modules under test are plain TypeScript, so the Svelte plugin and the
// htmx copy step stay out of the test pipeline.
const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$components: path.resolve(root, 'src/components'),
		},
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
	},
});
