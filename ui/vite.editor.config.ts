/// <reference types="vitest/config" />
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	plugins: [
		svelte({
			preprocess: vitePreprocess(),
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
		outDir: '../panel/editor',
		emptyOutDir: true,
		lib: {
			entry: path.resolve(root, 'src/islands/node-editor.ts'),
			formats: ['es'],
			fileName: () => 'node-editor.js',
			cssFileName: 'node-editor',
		},
		rollupOptions: {
			output: {
				assetFileNames: asset =>
					asset.name === 'style.css' ? 'node-editor.css' : '[name][extname]',
				chunkFileNames: '[name].js',
				entryFileNames: 'node-editor.js',
			},
		},
	},
});
