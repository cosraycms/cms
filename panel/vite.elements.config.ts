import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

// Second build pass: cosray's editor controls as custom elements,
// loaded lazily by the island through the element mechanism.

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	base: './',
	plugins: [
		svelte({
			preprocess: vitePreprocess({ script: true }),
			dynamicCompileOptions({ filename }) {
				if (filename.includes('/src/elements/')) {
					return { customElement: true };
				}
			},
		}),
	],
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$shell: path.resolve(root, 'src/shell'),
		},
	},
	build: {
		outDir: 'build',
		emptyOutDir: false,
		rollupOptions: {
			input: {
				richtext: path.resolve(root, 'src/elements/richtext.ts'),
				code: path.resolve(root, 'src/elements/code.ts'),
			},
			output: {
				assetFileNames: 'elements/[name][extname]',
				chunkFileNames: 'elements/chunks/[name]-[hash].js',
				entryFileNames: 'elements/[name].js',
			},
		},
	},
});
