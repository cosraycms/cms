import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

// The class name rule of docs/panel-styles.md, checked where the panel
// writes class names down: selectors in its stylesheets, class attributes
// in its views and PHP, and class strings and selectors in its scripts.
// Blind spots: classes held in variables set away from the attribute or
// call (`$active`, a helper's parameter), and library defaults the panel
// leaves unconfigured (Sortable's `sortable-*`).

const panel = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/** Marks where interpolated code sits inside a literal class string. */
const HOLE = '\u0000';

const reserved = new Set([
	'cms-blocks',
	'cms-block',
	'cms-blocks-images',
	'cms-figure',
	'cms-image-gallery',
	'cms-image-gallery-item',
]);

type Foreign = { name: RegExp; files: string[]; within?: string };

// Classes that third-party code, content or site output sets, and where the panel meets them.
const foreign: Foreign[] = [
	// ProseMirror's editor classes, which the read-only preview borrows too.
	{ name: /^ProseMirror(-[a-z]+)?$/, files: ['styles/', 'src/elements/richtext.js'] },
	// prism-code-editor's own classes, and Prism's token kinds below `.token`.
	{
		name: /^(prism-code-editor|show-line-numbers|active-line|active-bracket|token)$/,
		files: ['styles/cms-code.css'],
	},
	{ name: /./, files: ['styles/cms-code.css'], within: '.token' },
	// A rich text paragraph's content style `default`, which renders no class.
	{ name: /^default$/, files: ['src/elements/richtext/commands.js'] },
	// The site's menu, rendered into the menu tree's preview.
	{
		name: /^nav-(label|image)$/,
		files: ['styles/cms-menus.css'],
		within: '.cms-menu-tree-preview-body',
	},
	// The site's YouTube embed, swapped for a thumbnail in the layout preview's page.
	{ name: /^youtube$/, files: ['src/behaviors/layout-preview.js'] },
];

type Found = { file: string; name: string; chain?: string };

/** Why a class breaks the rule; of a name with a hole, only its literal head counts. */
function offence(name: string): string | null {
	const partial = name.includes(HOLE);
	const head = partial ? name.slice(0, name.indexOf(HOLE)) : name;

	if ((partial && head === '') || /^(is|has)-/.test(head)) {
		return null;
	}
	if (!head.startsWith('cms-')) {
		return 'lacks the cms- prefix';
	}
	if (head.startsWith('cms-block-') || (!partial && reserved.has(head))) {
		return "is reserved for the site's block rendering";
	}

	return null;
}

function offences(found: Found[]): string[] {
	return found
		.filter(
			({ file, name, chain = '' }) =>
				!foreign.some(
					(entry) =>
						entry.files.some((prefix) => file.startsWith(prefix)) &&
						entry.name.test(name) &&
						(entry.within === undefined || chain.includes(entry.within)),
				),
		)
		.flatMap(({ file, name }) => {
			const why = offence(name);

			return why ? [`${file}: ${name.replaceAll(HOLE, '…')} ${why}`] : [];
		});
}

function sources(dirs: string[], extension: string): [string, string][] {
	return dirs
		.flatMap((dir) =>
			fs
				.readdirSync(path.join(panel, dir), { withFileTypes: true, recursive: true })
				.filter((entry) => entry.isFile() && entry.name.endsWith(extension))
				.map((entry) => path.join(entry.parentPath, entry.name)),
		)
		.sort()
		.map((file) => [path.relative(panel, file), fs.readFileSync(file, 'utf8')]);
}

/** The class names of a class list; what cannot be a class name is code, not a class. */
function tokens(list: string): string[] {
	return list.split(/\s+/).filter((token) => /^-?[_a-zA-Z\u0000][\w\u0000-]*$/.test(token));
}

/** The string literals of code that may end up as classes: not compared, not keys. */
function values(code: string, literal: string): string[] {
	const found: string[] = [];

	for (const match of code.matchAll(new RegExp(literal, 'g'))) {
		const before = code.slice(0, match.index).trimEnd();
		const after = code.slice(match.index + match[0].length).trimStart();

		if (!/(==|!=|\[)$/.test(before) && !/^(==|!=|=>|\])/.test(after)) {
			found.push(match[0].slice(1, -1).replace(/\$\{[^}]*\}/g, HOLE));
		}
	}

	return found;
}

/** The classes of a selector, without those in attribute values. */
function selectorClasses(selector: string): string[] {
	const bare = selector.replace(/"[^"]*"|'[^']*'/g, '""');

	return Array.from(bare.matchAll(/\.(-?[_a-zA-Z][\w\u0000-]*)/g), (match) => match[1]);
}

/** Class selectors of every rule, with the selectors of the rules it nests in. */
function cssClasses(file: string, source: string): Found[] {
	const text = source
		.replace(/\/\*[\s\S]*?\*\//g, ' ')
		.replace(/"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g, '""')
		.replace(/url\([^)]*\)/g, 'url()');
	const found: Found[] = [];
	const preludes: string[] = [];
	let start = 0;

	for (let i = 0; i < text.length; i++) {
		if (text[i] === '{') {
			const prelude = text.slice(start, i);
			preludes.push(prelude);
			const chain = preludes.join(' { ');
			found.push(...selectorClasses(prelude).map((name) => ({ file, name, chain })));
		} else if (text[i] === '}') {
			preludes.pop();
		}
		if ('{};'.includes(text[i])) {
			start = i + 1;
		}
	}

	return found;
}

const PHP_LITERAL = String.raw`'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"`;

function phpClasses(file: string, source: string): Found[] {
	// Heredocs hold sample content, such as the styleguide's code field.
	const code = source.replace(/<<<\s*(['"]?)(\w+)\1\n[\s\S]*?\n\s*\2\b/g, '');
	const names: string[] = [];

	for (const match of code.matchAll(
		/\bclass=(?:"((?:<\?[\s\S]*?\?>|[^"])*)"|'((?:<\?[\s\S]*?\?>|[^'])*)')/g,
	)) {
		const value = match[1] ?? match[2];
		// Inside a PHP string, what is concatenated in is a hole too.
		const literal = value
			.replace(/<\?[\s\S]*?\?>/g, HOLE)
			.replace(/(['"])\s*\.[\s\S]*?\.\s*\1/g, HOLE);
		names.push(...tokens(literal));

		for (const embedded of value.matchAll(/<\?[\s\S]*?\?>/g)) {
			names.push(...values(embedded[0], PHP_LITERAL).flatMap(tokens));
		}
	}
	for (const match of code.matchAll(
		/Icon::render\((?:[^()]|\([^()]*\))*?,\s*'([^']*)'\s*\)|'class'\s*=>\s*'([^']*)'|\$class(?:es)?(?:\[\])?\s*\.?=\s*'([^']*)'/g,
	)) {
		names.push(...tokens(match[1] ?? match[2] ?? match[3]));
	}

	return names.map((name) => ({ file, name }));
}

const JS_LITERAL = String.raw`'(?:\\.|[^'\\\n])*'|"(?:\\.|[^"\\\n])*"|\x60(?:\\.|[^\x60\\])*\x60`;

function jsClasses(file: string, source: string): Found[] {
	const code = source.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^\s*\/\/.*$/gm, '');
	const names: string[] = [];
	// Class lists: classList.add/remove/replace/toggle/contains, className,
	// `class:` and Sortable's `…Class:` options, a `create(tag, className)` helper.
	const lists = [
		String.raw`\.classList\.(?:add|remove|replace)\(([^()]*)\)`,
		String.raw`\.classList\.(?:toggle|contains)\(\s*(${JS_LITERAL})`,
		String.raw`\bclassName\s*\+?=\s*([^;]*)`,
		String.raw`\b(?:class|\w+Class):\s*(${JS_LITERAL})`,
		String.raw`\bcreate\(\s*(?:${JS_LITERAL})\s*,\s*([^,)]*)`,
	];

	for (const list of lists) {
		for (const match of code.matchAll(new RegExp(list, 'g'))) {
			names.push(...values(match[1], JS_LITERAL).flatMap(tokens));
		}
	}

	const strings = values(code, JS_LITERAL);

	for (const string of strings) {
		for (const match of string.matchAll(/\bclass="([^"]*)"/g)) {
			names.push(...tokens(match[1]));
		}
	}

	// Selectors: what a lookup receives, and any string naming a panel class as a selector.
	const selectors = Array.from(
		code.matchAll(
			new RegExp(String.raw`\.(?:querySelector(?:All)?|closest|matches)\(\s*(${JS_LITERAL})`, 'g'),
		),
		(match) => match[1].slice(1, -1).replace(/\$\{[^}]*\}/g, HOLE),
	);
	selectors.push(...strings.filter((string) => /\.(cms|is|has)-/.test(string)));

	for (const selector of new Set(selectors)) {
		names.push(...selectorClasses(selector));
	}

	return names.map((name) => ({ file, name }));
}

describe('panel class names', () => {
	it('stylesheets select only cms-, state and foreign classes', () => {
		const found = sources(['styles'], '.css').flatMap(([file, source]) => cssClasses(file, source));

		expect(found.length).toBeGreaterThan(0);
		expect(offences(found)).toEqual([]);
	});

	it('views and panel PHP emit only cms- and state classes', () => {
		const found = sources(['views', '../src/Panel', '../src/Controller/Panel'], '.php').flatMap(
			([file, source]) => phpClasses(file, source),
		);

		expect(found.length).toBeGreaterThan(0);
		expect(offences(found)).toEqual([]);
	});

	it('scripts set and look up only cms-, state and foreign classes', () => {
		const found = sources(['src'], '.js').flatMap(([file, source]) => jsClasses(file, source));

		expect(found.length).toBeGreaterThan(0);
		expect(offences(found)).toEqual([]);
	});
});
