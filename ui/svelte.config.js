import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),
	kit: {
		paths: { base: '/panel' },
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
