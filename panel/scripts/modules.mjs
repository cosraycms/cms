// Copies the third-party files the panel runs in the browser from
// node_modules into modules/ and writes the import map data PHP serves.
// The panel ships without a build, so these committed copies are what a
// Composer install delivers. Files keep their path inside the package and
// carry no version, so an upgrade shows up as an ordinary diff.
//
//   pnpm run modules          regenerate modules/
//   pnpm run modules:check    fail when modules/ is out of date
//
// Entry points are the bare imports in src/ (Svelte and Node-only tools
// excepted) plus the classic scripts listed in package.json#cosray. Every
// entry's package has to be a runtime dependency; transitive packages come
// along through the import graph. Plain .js files in src/ are served as they
// are, so their imports also have to be ones a browser resolves: mapped
// packages and relative paths to existing files, no aliases.

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { init, parse } from 'es-module-lexer';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const target = path.join(root, 'modules');
const manifest = readJson(path.join(root, 'package.json'));
const config = manifest.cosray ?? {};
const conditions = ['browser', 'import', 'module', 'default'];

// Our own code: prettier-formatted TypeScript, JavaScript and Svelte, where
// a lexer for plain JavaScript would trip over type syntax.
const sourceImport =
	/(?:^|[\s;{}])(?:import|export)\s+(?!type\b)(?:[^'"`;]*?\sfrom\s*)?['"]([^'"\n]+)['"]|\bimport\(\s*['"]([^'"\n]+)['"]\s*\)/g;

class Failure extends Error {}

await init;

try {
	const result = collect();

	if (process.argv.includes('--check')) {
		check(result);
	} else {
		write(result, target);
		console.log(`modules/: ${result.files.size} files from ${result.packages.size} packages`);
	}
} catch (error) {
	if (!(error instanceof Failure)) {
		throw error;
	}

	console.error(`modules: ${error.message}`);
	process.exit(1);
}

function collect() {
	const packages = new Map();
	const files = new Map();
	const imports = new Map();
	const queue = [];
	const anchor = path.join(root, 'package.json');

	function add(file, follow = true) {
		const owner = packageOf(file, packages);

		if (!files.has(file)) {
			files.set(file, owner);

			if (follow) {
				queue.push(file);
			}
		}

		return owner;
	}

	function resolveBare(specifier, from) {
		const file = resolve(specifier, from);
		const owner = add(file);
		const mapped = path.posix.join(owner.name, toPosix(path.relative(owner.dir, file)));
		const known = imports.get(specifier);

		if (known !== undefined && known !== mapped) {
			throw new Failure(`${specifier} resolves to both ${known} and ${mapped}`);
		}

		imports.set(specifier, mapped);
	}

	for (const specifier of entries()) {
		const name = packageName(specifier);

		if (!Object.hasOwn(manifest.dependencies ?? {}, name)) {
			throw new Failure(`src/ imports ${specifier}, but ${name} is not in dependencies`);
		}

		resolveBare(specifier, anchor);
	}

	const scripts = [];

	for (const script of config.classicScripts ?? []) {
		const name = packageName(script);
		const file = path.join(packageDir(name, anchor), script.slice(name.length + 1));

		if (!fs.existsSync(file)) {
			throw new Failure(`classic script ${script} does not exist`);
		}

		// A classic script has no module imports to follow.
		const owner = add(file, false);
		scripts.push(path.posix.join(owner.name, toPosix(path.relative(owner.dir, file))));
	}

	while (queue.length > 0) {
		const file = queue.shift();

		for (const specifier of moduleImports(file)) {
			if (isRelative(specifier)) {
				const next = path.resolve(path.dirname(file), specifier);

				if (!isFile(next)) {
					throw new Failure(`${display(file)} imports missing ${specifier}`);
				}

				add(next);
			} else {
				resolveBare(specifier, file);
			}
		}
	}

	return { packages, files, imports, scripts };
}

/** Bare specifiers imported by the panel's own source. */
function entries() {
	const found = new Set();

	for (const file of walk(path.join(root, 'src'))) {
		if (!/\.(js|ts|svelte)$/.test(file) || file.endsWith('.d.ts')) {
			continue;
		}

		if (file.startsWith(path.join(root, 'src', 'tools') + path.sep)) {
			continue;
		}

		const specifiers = file.endsWith('.js')
			? servedImports(file)
			: Array.from(
					fs.readFileSync(file, 'utf8').matchAll(sourceImport),
					(match) => match[1] ?? match[2],
				);

		for (const specifier of specifiers) {
			if (isBare(specifier) && specifier !== 'svelte' && !specifier.startsWith('svelte/')) {
				found.add(specifier);
			}
		}
	}

	return [...found].sort();
}

/** Imports of a file the browser loads unbundled, checked for resolvability. */
function servedImports(file) {
	// Our own computed imports load plugin modules by URL at runtime.
	const specifiers = moduleImports(file, false);

	for (const specifier of specifiers) {
		if (isRelative(specifier)) {
			if (!specifier.endsWith('.js') || !isFile(path.resolve(path.dirname(file), specifier))) {
				throw new Failure(`${display(file)} imports ${specifier}, which a browser cannot load`);
			}
		} else if (!isBare(specifier)) {
			throw new Failure(`${display(file)} imports ${specifier}, which a browser cannot resolve`);
		}
	}

	return specifiers;
}

function moduleImports(file, warnComputed = true) {
	const [imports] = parse(fs.readFileSync(file, 'utf8'), file);
	const specifiers = [];

	for (const entry of imports) {
		// import.meta
		if (entry.d === -2) {
			continue;
		}

		if (entry.n === undefined) {
			if (warnComputed) {
				console.warn(`modules: skipping a computed import in ${display(file)}`);
			}

			continue;
		}

		specifiers.push(entry.n);
	}

	return specifiers;
}

function resolve(specifier, from) {
	const name = packageName(specifier);
	const dir = packageDir(name, from);
	const pkg = readJson(path.join(dir, 'package.json'));
	const subpath = '.' + specifier.slice(name.length);
	// Like Node, a package with an exports map exposes nothing beyond it.
	const relative =
		pkg.exports === undefined || pkg.exports === null
			? fallbackTarget(pkg, subpath)
			: exportTarget(pkg.exports, subpath);

	if (relative === null) {
		throw new Failure(`cannot resolve ${specifier}`);
	}

	const file = path.join(dir, relative);

	if (!isFile(file)) {
		throw new Failure(`${specifier} resolves to missing ${display(file)}`);
	}

	return fs.realpathSync(file);
}

function exportTarget(exports, subpath) {
	const map =
		typeof exports === 'string' ||
		Array.isArray(exports) ||
		!Object.keys(exports).some((key) => key.startsWith('.'))
			? { '.': exports }
			: exports;

	if (Object.hasOwn(map, subpath)) {
		return condition(map[subpath]);
	}

	for (const [key, value] of Object.entries(map)) {
		const star = key.indexOf('*');

		if (star === -1) {
			continue;
		}

		const prefix = key.slice(0, star);
		const suffix = key.slice(star + 1);

		if (subpath.startsWith(prefix) && subpath.endsWith(suffix)) {
			const match = subpath.slice(prefix.length, subpath.length - suffix.length);

			return condition(value)?.replaceAll('*', match) ?? null;
		}
	}

	return null;
}

function condition(value) {
	if (typeof value === 'string') {
		return value;
	}

	if (Array.isArray(value)) {
		return value.length > 0 ? condition(value[0]) : null;
	}

	if (value !== null && typeof value === 'object') {
		for (const name of conditions) {
			if (Object.hasOwn(value, name)) {
				const found = condition(value[name]);

				if (found !== null) {
					return found;
				}
			}
		}
	}

	return null;
}

function fallbackTarget(pkg, subpath) {
	if (subpath === '.') {
		return pkg.module ?? pkg.main ?? 'index.js';
	}

	return subpath.endsWith('.js') ? subpath : `${subpath}.js`;
}

/** Finds a package the way Node does, from the importing file upwards. */
function packageDir(name, from) {
	let dir = path.dirname(fs.realpathSync(from));

	while (true) {
		const candidate = path.join(dir, 'node_modules', name);

		if (isFile(path.join(candidate, 'package.json'))) {
			return fs.realpathSync(candidate);
		}

		const parent = path.dirname(dir);

		if (parent === dir) {
			throw new Failure(`cannot find package ${name} from ${display(from)}`);
		}

		dir = parent;
	}
}

/** The package that owns a resolved file; one version per package. */
function packageOf(file, packages) {
	let dir = path.dirname(file);

	while (
		!isFile(path.join(dir, 'package.json')) ||
		!readJson(path.join(dir, 'package.json')).name
	) {
		const parent = path.dirname(dir);

		if (parent === dir) {
			throw new Failure(`no package owns ${display(file)}`);
		}

		dir = parent;
	}

	const pkg = readJson(path.join(dir, 'package.json'));
	const known = packages.get(pkg.name);

	if (known !== undefined && known.dir !== dir) {
		throw new Failure(
			`${pkg.name} is needed in two versions (${known.version}, ${pkg.version}); dedupe it`,
		);
	}

	if (known !== undefined) {
		return known;
	}

	const owner = { name: pkg.name, version: pkg.version, dir, ...licensing(pkg, dir) };
	packages.set(pkg.name, owner);

	return owner;
}

function licensing(pkg, dir) {
	const license = config.licenses?.[pkg.name] ?? pkg.license;

	if (typeof license !== 'string' || license === '') {
		throw new Failure(`${pkg.name} declares no license; add one to package.json#cosray.licenses`);
	}

	const files = fs
		.readdirSync(dir)
		.filter((name) => /^(licen[cs]e|copying|notice)(\.|$)/i.test(name))
		.sort();
	let copyright = null;

	for (const name of files) {
		const line = fs
			.readFileSync(path.join(dir, name), 'utf8')
			.split('\n')
			.map((text) => text.trim())
			.find((text) => /^copyright\b/i.test(text));

		if (line !== undefined) {
			copyright = line.replace(/^copyright\s*(?:\(c\)|©)?\s*/i, '');
			break;
		}
	}

	copyright ??= typeof pkg.author === 'string' ? pkg.author : (pkg.author?.name ?? null);

	if (copyright === null) {
		throw new Failure(`${pkg.name} names no copyright holder`);
	}

	return { license, copyright, licenseFiles: files };
}

function write(result, dir) {
	fs.rmSync(dir, { recursive: true, force: true });

	for (const [file, owner] of result.files) {
		copy(file, path.join(dir, owner.name, path.relative(owner.dir, file)));
	}

	const packages = [...result.packages.values()].sort((a, b) => a.name.localeCompare(b.name));

	for (const owner of packages) {
		for (const name of owner.licenseFiles) {
			copy(path.join(owner.dir, name), path.join(dir, owner.name, name));
		}
	}

	const importmap = {
		imports: Object.fromEntries([...result.imports].sort(([a], [b]) => a.localeCompare(b))),
		scripts: [...result.scripts].sort(),
		packages: Object.fromEntries(packages.map((owner) => [owner.name, owner.version])),
	};

	fs.writeFileSync(path.join(dir, 'importmap.json'), JSON.stringify(importmap, null, '\t') + '\n');
	fs.writeFileSync(path.join(dir, 'REUSE.toml'), reuse(packages));
}

function reuse(packages) {
	const blocks = packages.map((owner) =>
		[
			'[[annotations]]',
			`path = [${toml(`${owner.name}/**`)}]`,
			'precedence = "closest"',
			`SPDX-FileCopyrightText = ${toml(owner.copyright)}`,
			`SPDX-License-Identifier = ${toml(owner.license)}`,
		].join('\n'),
	);

	return (
		[
			'# Generated by scripts/modules.mjs. Third-party files keep their licenses.',
			'version = 1',
			...blocks,
		].join('\n\n') + '\n'
	);
}

function check(result) {
	const scratch = fs.mkdtempSync(path.join(os.tmpdir(), 'cosray-modules-'));

	try {
		write(result, scratch);
		const expected = listing(scratch);
		const actual = fs.existsSync(target) ? listing(target) : new Map();
		const drift = [];

		for (const [name, content] of expected) {
			if (!actual.has(name)) {
				drift.push(`missing ${name}`);
			} else if (!actual.get(name).equals(content)) {
				drift.push(`changed ${name}`);
			}
		}

		for (const name of actual.keys()) {
			if (!expected.has(name)) {
				drift.push(`extra ${name}`);
			}
		}

		if (drift.length > 0) {
			throw new Failure(`modules/ is out of date; run pnpm run modules\n  ${drift.join('\n  ')}`);
		}

		console.log('modules/ is up to date');
	} finally {
		fs.rmSync(scratch, { recursive: true, force: true });
	}
}

function listing(dir) {
	return new Map(
		[...walk(dir)].map((file) => [toPosix(path.relative(dir, file)), fs.readFileSync(file)]),
	);
}

function* walk(dir) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		const file = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			yield* walk(file);
		} else if (entry.isFile()) {
			yield file;
		}
	}
}

function copy(from, to) {
	fs.mkdirSync(path.dirname(to), { recursive: true });
	fs.copyFileSync(from, to);
}

function packageName(specifier) {
	const parts = specifier.split('/');

	return specifier.startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];
}

function isBare(specifier) {
	return !isRelative(specifier) && !/^(\$|node:|[a-z]+:\/\/)/.test(specifier);
}

function isRelative(specifier) {
	return specifier.startsWith('./') || specifier.startsWith('../') || specifier.startsWith('/');
}

function isFile(file) {
	return fs.existsSync(file) && fs.statSync(file).isFile();
}

function readJson(file) {
	return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function toml(value) {
	return JSON.stringify(value);
}

function toPosix(file) {
	return file.split(path.sep).join('/');
}

function display(file) {
	return toPosix(path.relative(root, file));
}
