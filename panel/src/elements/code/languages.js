export const DEFAULT_CODE_SYNTAX = 'plaintext';

export const CODE_SYNTAXES = /** @type {const} */ ([
	'plaintext',
	'php',
	'javascript',
	'typescript',
	'html',
	'css',
	'json',
	'markdown',
	'sql',
	'yaml',
	'xml',
	'bash',
]);

/** @typedef {(typeof CODE_SYNTAXES)[number]} SyntaxKey */

/** @type {Record<string, SyntaxKey>} */
const syntaxAliases = {
	js: 'javascript',
	ts: 'typescript',
	md: 'markdown',
	yml: 'yaml',
	sh: 'bash',
	plain: 'plaintext',
	text: 'plaintext',
};

// Each syntax key is also the editor's language name. A load registers the
// Prism grammar and the editing behavior (comment tokens, indentation, tag
// closing) globally; plaintext has neither and stays unhighlighted. HTML
// brings the grammars its style and script elements embed. Specifiers carry
// .js because the import map maps the language directories as a whole
// (package.json#cosray.modulePrefixes).
/** @type {Record<SyntaxKey, () => Promise<unknown>>} */
const languageLoaders = {
	plaintext: async () => {},
	php: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/php.js'),
			import('prism-code-editor/languages/php.js'),
		]),
	javascript: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/javascript.js'),
			import('prism-code-editor/languages/clike.js'),
		]),
	typescript: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/typescript.js'),
			import('prism-code-editor/languages/clike.js'),
		]),
	html: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/markup.js'),
			import('prism-code-editor/prism/languages/css.js'),
			import('prism-code-editor/prism/languages/javascript.js'),
			import('prism-code-editor/languages/html.js'),
		]),
	css: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/css.js'),
			import('prism-code-editor/languages/css.js'),
		]),
	json: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/json.js'),
			import('prism-code-editor/languages/json.js'),
		]),
	// The HTML behavior also registers markdown.
	markdown: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/markdown.js'),
			import('prism-code-editor/languages/html.js'),
		]),
	sql: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/sql.js'),
			import('prism-code-editor/languages/sql.js'),
		]),
	yaml: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/yaml.js'),
			import('prism-code-editor/languages/yaml.js'),
		]),
	xml: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/xml.js'),
			import('prism-code-editor/languages/xml.js'),
		]),
	bash: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/bash.js'),
			import('prism-code-editor/languages/bash.js'),
		]),
};

/**
 * @param {string | null | undefined} syntax
 * @returns {SyntaxKey}
 */
export function normalizeCodeSyntax(syntax) {
	const normalized = (syntax ?? DEFAULT_CODE_SYNTAX).trim().toLowerCase();

	if (normalized === '') {
		return DEFAULT_CODE_SYNTAX;
	}

	if (normalized in syntaxAliases) {
		return syntaxAliases[normalized];
	}

	const known = CODE_SYNTAXES.find((key) => key === normalized);

	return known ?? DEFAULT_CODE_SYNTAX;
}

/**
 * Loads a syntax once and returns the editor language name for it.
 *
 * @param {string | null | undefined} syntax
 * @returns {Promise<SyntaxKey>}
 */
export async function loadCodeLanguage(syntax) {
	const key = normalizeCodeSyntax(syntax);
	await languageLoaders[key]();

	return key;
}
