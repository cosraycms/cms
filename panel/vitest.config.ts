import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
	resolve: {
		conditions: ['browser'],
		// Modules a Composer package ships (package.json#cosray.composerModules).
		alias: {
			'@celema/verba': fileURLToPath(
				new URL('../vendor/celema/verba/js/src/index.js', import.meta.url),
			),
		},
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
		setupFiles: ['tests/setup.ts'],
	},
});
