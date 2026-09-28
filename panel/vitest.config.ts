import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
	resolve: {
		conditions: ['browser'],
		alias: [
			// Modules a Composer package ships (package.json#cosray.composerModules).
			{
				find: '@celema/verba',
				replacement: fileURLToPath(
					new URL('../vendor/celema/verba/js/src/index.js', import.meta.url),
				),
			},
			// Modules under package.json#cosray.modulePrefixes, named with .js.
			{
				find: /^prism-code-editor\/((?:prism\/)?languages\/.+\.js)$/,
				replacement: fileURLToPath(
					new URL('node_modules/prism-code-editor/dist/$1', import.meta.url),
				),
			},
		],
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
		setupFiles: ['tests/setup.ts'],
	},
});
