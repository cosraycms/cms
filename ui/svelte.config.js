import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

const panelPath = process.env.COSRAY_PANEL_PATH ? process.env.COSRAY_PANEL_PATH : 'cms';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),
	kit: {
		paths: { base: `/${panelPath}` },
		adapter: adapter({
			pages: 'build',
			assets: 'build',
			fallback: 'index.html',
		}),
		prerender: { entries: [] },
		alias: {
			$lib: './src/lib',
			$types: './src/types',
			$shell: './src/shell',
		},
	},
};

export default config;
