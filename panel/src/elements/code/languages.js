import { __ } from '../../lib/locale.js';

export const DEFAULT_CODE_SYNTAX = 'plaintext';

// Each syntax key is also the editor's language name. A load registers the
// Prism grammar and the editing behavior (comment tokens, indentation, tag
// closing) globally; plaintext has neither and stays unhighlighted, and diff
// has no behavior. Markup-based syntaxes bring the grammars their style and
// script elements embed. Specifiers are literal so scripts/modules.mjs
// vendors exactly these modules, and carry .js because the import map maps
// the language directories as a whole (package.json#cosray.sources).
const syntaxes = {
	// Labeled in the panel language by codeSyntaxLabel().
	plaintext: { label: '', load: async () => {} },
	apacheconf: {
		label: 'Apache config',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/apacheconf.js'),
				import('prism-code-editor/languages/apacheconf.js'),
			]),
	},
	bash: {
		label: 'Bash',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/bash.js'),
				import('prism-code-editor/languages/bash.js'),
			]),
	},
	clojure: {
		label: 'Clojure',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/clojure.js'),
				import('prism-code-editor/languages/clojure.js'),
			]),
	},
	csharp: {
		label: 'C#',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/csharp.js'),
				import('prism-code-editor/languages/clike.js'),
			]),
	},
	css: {
		label: 'CSS',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/languages/css.js'),
			]),
	},
	diff: {
		label: 'Diff',
		load: () => import('prism-code-editor/prism/languages/diff.js'),
	},
	django: {
		label: 'Django / Jinja',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/django.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/django.js'),
			]),
	},
	docker: {
		label: 'Dockerfile',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/docker.js'),
				import('prism-code-editor/languages/docker.js'),
			]),
	},
	fsharp: {
		label: 'F#',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/fsharp.js'),
				import('prism-code-editor/languages/fsharp.js'),
			]),
	},
	go: {
		label: 'Go',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/go.js'),
				import('prism-code-editor/languages/clike.js'),
			]),
	},
	handlebars: {
		label: 'Handlebars',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/handlebars.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/handlebars.js'),
			]),
	},
	html: {
		label: 'HTML',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/markup.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/html.js'),
			]),
	},
	ini: {
		label: 'INI',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/ini.js'),
				import('prism-code-editor/languages/ini.js'),
			]),
	},
	java: {
		label: 'Java',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/java.js'),
				import('prism-code-editor/languages/clike.js'),
			]),
	},
	javascript: {
		label: 'JavaScript',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/clike.js'),
			]),
	},
	json: {
		label: 'JSON',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/json.js'),
				import('prism-code-editor/languages/json.js'),
			]),
	},
	jsx: {
		label: 'JSX',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/jsx.js'),
				import('prism-code-editor/languages/jsx.js'),
			]),
	},
	latte: {
		label: 'Latte',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/latte.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/latte.js'),
			]),
	},
	lisp: {
		label: 'Lisp',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/lisp.js'),
				import('prism-code-editor/languages/lisp.js'),
			]),
	},
	liquid: {
		label: 'Liquid',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/liquid.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/liquid.js'),
			]),
	},
	lua: {
		label: 'Lua',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/lua.js'),
				import('prism-code-editor/languages/lua.js'),
			]),
	},
	// The HTML behavior also registers markdown.
	markdown: {
		label: 'Markdown',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/markdown.js'),
				import('prism-code-editor/languages/html.js'),
			]),
	},
	nginx: {
		label: 'nginx',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/nginx.js'),
				import('prism-code-editor/languages/nginx.js'),
			]),
	},
	nim: {
		label: 'Nim',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/nim.js'),
				import('prism-code-editor/languages/nim.js'),
			]),
	},
	ocaml: {
		label: 'OCaml',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/ocaml.js'),
				import('prism-code-editor/languages/ocaml.js'),
			]),
	},
	odin: {
		label: 'Odin',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/odin.js'),
				import('prism-code-editor/languages/odin.js'),
			]),
	},
	php: {
		label: 'PHP',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/php.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/php.js'),
			]),
	},
	python: {
		label: 'Python',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/python.js'),
				import('prism-code-editor/languages/python.js'),
			]),
	},
	ruby: {
		label: 'Ruby',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/ruby.js'),
				import('prism-code-editor/languages/ruby.js'),
			]),
	},
	rust: {
		label: 'Rust',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/rust.js'),
				import('prism-code-editor/languages/rust.js'),
			]),
	},
	// The CSS behavior also registers scss.
	scss: {
		label: 'SCSS',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/scss.js'),
				import('prism-code-editor/languages/css.js'),
			]),
	},
	sql: {
		label: 'SQL',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/sql.js'),
				import('prism-code-editor/languages/sql.js'),
			]),
	},
	toml: {
		label: 'TOML',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/toml.js'),
				import('prism-code-editor/languages/toml.js'),
			]),
	},
	// The JSX behavior also registers tsx.
	tsx: {
		label: 'TSX',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/tsx.js'),
				import('prism-code-editor/languages/jsx.js'),
			]),
	},
	twig: {
		label: 'Twig',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/twig.js'),
				import('prism-code-editor/prism/languages/css.js'),
				import('prism-code-editor/prism/languages/javascript.js'),
				import('prism-code-editor/languages/twig.js'),
			]),
	},
	typescript: {
		label: 'TypeScript',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/typescript.js'),
				import('prism-code-editor/languages/clike.js'),
			]),
	},
	xml: {
		label: 'XML',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/xml.js'),
				import('prism-code-editor/languages/xml.js'),
			]),
	},
	yaml: {
		label: 'YAML',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/yaml.js'),
				import('prism-code-editor/languages/yaml.js'),
			]),
	},
	zig: {
		label: 'Zig',
		load: () =>
			Promise.all([
				import('prism-code-editor/prism/languages/zig.js'),
				import('prism-code-editor/languages/zig.js'),
			]),
	},
};

/** @typedef {keyof typeof syntaxes} SyntaxKey */

/** @type {Record<string, SyntaxKey>} */
const syntaxAliases = {
	js: 'javascript',
	ts: 'typescript',
	md: 'markdown',
	yml: 'yaml',
	sh: 'bash',
	hbs: 'handlebars',
	mustache: 'handlebars',
	jinja: 'django',
	jinja2: 'django',
	plain: 'plaintext',
	text: 'plaintext',
};

/**
 * The syntax a key or alias names, or null for one the panel lacks.
 *
 * @param {string} syntax
 * @returns {SyntaxKey | null}
 */
function knownSyntax(syntax) {
	const key = syntax.trim().toLowerCase();

	if (Object.hasOwn(syntaxAliases, key)) {
		return syntaxAliases[key];
	}

	return Object.hasOwn(syntaxes, key) ? /** @type {SyntaxKey} */ (key) : null;
}

/**
 * @param {string | null | undefined} syntax
 * @returns {SyntaxKey}
 */
export function normalizeCodeSyntax(syntax) {
	return knownSyntax(syntax ?? '') ?? DEFAULT_CODE_SYNTAX;
}

/**
 * The name the syntax picker shows. A syntax the panel lacks keeps the key
 * the field configures, so the choice stays recognizable.
 *
 * @param {string} syntax
 * @returns {string}
 */
export function codeSyntaxLabel(syntax) {
	const known = knownSyntax(syntax);

	if (known === null) {
		return syntax;
	}

	return known === DEFAULT_CODE_SYNTAX ? __('code:plaintext') : syntaxes[known].label;
}

/**
 * Loads a syntax once and returns the editor language name for it.
 *
 * @param {string | null | undefined} syntax
 * @returns {Promise<SyntaxKey>}
 */
export async function loadCodeLanguage(syntax) {
	const key = normalizeCodeSyntax(syntax);
	await syntaxes[key].load();

	return key;
}
