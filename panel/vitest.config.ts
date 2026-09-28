import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Tests import the third-party modules the browser gets: the import map
// scripts/modules.mjs writes resolves every bare specifier to its copy in
// modules/, or to vendor/ for a module a Composer package ships.
type ImportMap = { imports: Record<string, string>; composer: Record<string, string> };

const importMap: ImportMap = JSON.parse(
	fs.readFileSync(new URL('modules/importmap.json', import.meta.url), 'utf8'),
);
const file = (path: string) => fileURLToPath(new URL(path, import.meta.url));
const escape = (text: string) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const targets = [
	...Object.entries(importMap.imports).map(([key, path]) => [key, file(`modules/${path}`)]),
	...Object.entries(importMap.composer).map(([key, path]) => [key, file(`../vendor/${path}`)]),
];

export default defineConfig({
	resolve: {
		conditions: ['browser'],
		// A key ending in a slash maps the directory below it, as in the browser.
		alias: targets.map(([key, target]) =>
			key.endsWith('/')
				? { find: new RegExp(`^${escape(key)}(.+)$`), replacement: `${target}$1` }
				: { find: new RegExp(`^${escape(key)}$`), replacement: target },
		),
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
		setupFiles: ['tests/setup.ts'],
	},
});
