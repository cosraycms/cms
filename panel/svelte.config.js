import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

const config = {
	preprocess: vitePreprocess({ script: true }),
	onwarn(warning, handler) {
		// Element wrappers under src/elements/ declare <svelte:options
		// customElement>; the compile option is set per-file via
		// dynamicCompileOptions in the vite configs, which svelte-check
		// does not see.
		if (warning.code === 'options_missing_custom_element') {
			return;
		}

		handler(warning);
	},
};

export default config;
