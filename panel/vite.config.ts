import { copyFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const root = fileURLToPath(new URL('.', import.meta.url));
const devPort = Number.parseInt(process.env.COSRAY_PANEL_DEV_PORT ?? '2001', 10);
const devHost = process.env.COSRAY_PANEL_DEV_HOST ?? 'localhost';
const htmxDir = path.resolve(root, 'node_modules/htmx.org');

// Keep htmx as a classic script so plugin scripts can use its global before
// the panel module runs.
const copyHtmx = {
	name: 'copy-htmx',
	async writeBundle() {
		await copyFile(path.resolve(htmxDir, 'dist/htmx.min.js'), path.resolve(root, 'static/htmx.js'));
	},
};

export default defineConfig({
	base: './',
	plugins: [copyHtmx],
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
		},
	},
	css: {
		devSourcemap: true,
	},
	server: {
		port: Number.isFinite(devPort) ? devPort : 2001,
		host: devHost,
		strictPort: true,
		allowedHosts: true,
		cors: true,
	},
	build: {
		outDir: 'static',
		emptyOutDir: true,
		license: {
			fileName: 'PANEL-THIRD-PARTY-NOTICES.md',
		},
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
