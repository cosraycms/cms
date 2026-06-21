import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
	plugins: [svelte({ compilerOptions: { customElement: true } })],
	build: {
		emptyOutDir: true,
		outDir: '../app/components',
		lib: {
			entry: {
				code: resolve(import.meta.dirname, 'src/code.ts'),
				richtext: resolve(import.meta.dirname, 'src/richtext.ts'),
			},
			formats: ['es'],
		},
		rollupOptions: {
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: 'chunks/[name]-[hash].js',
				assetFileNames: 'assets/[name][extname]',
			},
		},
	},
});
