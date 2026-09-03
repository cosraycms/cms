import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

// The legacy HTML-to-richtext migration helper. Built on demand, not as
// part of `pnpm run build`: its output is committed in the sibling
// cosray/legacy-richtext-converter repository and must run without the
// panel checkout or node_modules.

const root = fileURLToPath(new URL('.', import.meta.url));

// jsdom source fragments the standalone bundle must neutralize. Both are
// optional runtime paths the converter never takes: it neither loads
// resources nor runs page scripts, and it never decodes images.
const jsdomPatches = [
	{
		file: '/jsdom/lib/jsdom/living/xhr/XMLHttpRequest-impl.js',
		search:
			'const syncWorkerFile = require.resolve ? require.resolve("./xhr-sync-worker.js") : null;',
		replace: 'const syncWorkerFile = null;',
		subject: 'the jsdom synchronous XHR worker',
	},
	{
		// Bundling resolves the absent optional `canvas` module to an empty
		// object, which jsdom 25 no longer detects, so `new Canvas.Image()`
		// would throw on every `<img>` carrying a `src` or `width`.
		file: '/jsdom/lib/jsdom/utils.js',
		search: 'try {\n  exports.Canvas = require("canvas");\n} catch {\n  exports.Canvas = null;\n}',
		replace: 'exports.Canvas = null;',
		subject: 'the optional jsdom canvas dependency',
	},
];

function bundleJsdom(): Plugin {
	return {
		name: 'bundle-jsdom-standalone',
		enforce: 'pre',
		transform(code, id) {
			const patch = jsdomPatches.find((candidate) => id.endsWith(candidate.file));

			if (!patch) {
				return;
			}

			if (!code.includes(patch.search)) {
				throw new Error(`Could not disable ${patch.subject}`);
			}

			return code.replace(patch.search, patch.replace);
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
