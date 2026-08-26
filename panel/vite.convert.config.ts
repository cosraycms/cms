import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

// The legacy HTML-to-richtext migration helper. Built on demand, not as
// part of `pnpm run build`: its output is committed in the sibling
// cosray/legacy-richtext-converter repository and must run without the
// panel checkout or node_modules.

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
			$components: path.resolve(root, 'src/components'),
		},
	},
	ssr: {
		noExternal: true,
	},
	build: {
		ssr: true,
		target: 'node20',
		minify: 'esbuild',
		outDir: '../../legacy-richtext-converter/resources',
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
					'/*! Generated migration-only compatibility artifact. Rebuild with `pnpm run build:converter` in panel/; do not edit. */',
			},
		},
	},
});
