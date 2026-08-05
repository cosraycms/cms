import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

// Third build pass: the legacy HTML-to-richtext migration helper.
// Its checked-in output ships with the Composer package and must run
// without the panel checkout or node_modules.

const root = fileURLToPath(new URL('.', import.meta.url));

function bundleJsdom(): Plugin {
	const worker =
		'const syncWorkerFile = require.resolve ? require.resolve("./xhr-sync-worker.js") : null;';

	return {
		name: 'bundle-jsdom-without-sync-xhr',
		enforce: 'pre',
		transform(code, id) {
			if (!id.endsWith('/jsdom/lib/jsdom/living/xhr/XMLHttpRequest-impl.js')) {
				return;
			}

			if (!code.includes(worker)) {
				throw new Error('Could not disable the jsdom synchronous XHR worker');
			}

			// The converter neither loads resources nor runs page scripts. Removing
			// this unreachable worker path lets jsdom ship in one ESM artifact.
			return code.replace(worker, 'const syncWorkerFile = null;');
		},
	};
}

export default defineConfig({
	plugins: [bundleJsdom()],
	esbuild: {
		legalComments: 'inline',
	},
	resolve: {
		alias: {
			$lib: path.resolve(root, 'src/lib'),
			$types: path.resolve(root, 'src/types'),
			$shell: path.resolve(root, 'src/shell'),
		},
	},
	ssr: {
		noExternal: true,
	},
	build: {
		ssr: true,
		target: 'node20',
		minify: 'esbuild',
		outDir: '../resources/migration',
		emptyOutDir: true,
		license: {
			fileName: 'THIRD-PARTY-NOTICES.md',
		},
		rollupOptions: {
			input: {
				'legacy-richtext-html-converter': path.resolve(
					root,
					'src/tools/legacy-richtext-html-converter.ts',
				),
			},
			output: {
				entryFileNames: 'legacy-richtext-html-converter.mjs',
				banner:
					'/*! Generated migration-only compatibility artifact. Rebuild with `pnpm run build` in panel/; do not edit. */',
			},
		},
	},
});
