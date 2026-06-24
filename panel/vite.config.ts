import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { svelte, vitePreprocess } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vite';

const root = fileURLToPath(new URL('.', import.meta.url));
const devPort = Number.parseInt(process.env.COSRAY_PANEL_DEV_PORT ?? '2001', 10);
const devHost = process.env.COSRAY_PANEL_DEV_HOST ?? 'localhost';

export default defineConfig({
	base: './',
	plugins: [
		svelte({
			preprocess: vitePreprocess({ script: true }),
		}),
	],
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$shell: path.resolve(root, 'src/shell'),
		},
	},
	server: {
		port: Number.isFinite(devPort) ? devPort : 2001,
		host: devHost,
		strictPort: true,
		allowedHosts: true,
		cors: true,
	},
	build: {
		outDir: 'build',
		emptyOutDir: true,
		rollupOptions: {
			input: {
				panel: path.resolve(root, 'src/panel.ts'),
			},
			output: {
				assetFileNames: '[name][extname]',
				chunkFileNames: '[name].js',
				entryFileNames: '[name].js',
			},
		},
	},
});
