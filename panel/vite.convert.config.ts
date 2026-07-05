import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Third build pass: the headless richtext converter for the HTML->JSON
// content migration. Bundles the panel's editor schema and format
// adapter into one node script; jsdom stays external and resolves
// from panel/node_modules at runtime.

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$shell: path.resolve(root, 'src/shell'),
		},
	},
	build: {
		ssr: true,
		target: 'node20',
		outDir: 'build/tools',
		emptyOutDir: false,
		rollupOptions: {
			input: {
				'richtext-convert': path.resolve(root, 'src/tools/richtext-convert.ts'),
			},
			output: {
				entryFileNames: '[name].mjs',
			},
			external: ['jsdom'],
		},
	},
});
