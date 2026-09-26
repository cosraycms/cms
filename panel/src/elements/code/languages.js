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
// brings the grammars its style and script elements embed.
/** @type {Record<SyntaxKey, () => Promise<unknown>>} */
const languageLoaders = {
	plaintext: async () => {},
	php: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/php'),
			import('prism-code-editor/languages/php'),
		]),
	javascript: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/javascript'),
			import('prism-code-editor/languages/clike'),
		]),
	typescript: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/typescript'),
			import('prism-code-editor/languages/clike'),
		]),
	html: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/markup'),
			import('prism-code-editor/prism/languages/css'),
			import('prism-code-editor/prism/languages/javascript'),
			import('prism-code-editor/languages/html'),
		]),
	css: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/css'),
			import('prism-code-editor/languages/css'),
		]),
	json: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/json'),
			import('prism-code-editor/languages/json'),
		]),
	// The HTML behavior also registers markdown.
	markdown: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/markdown'),
			import('prism-code-editor/languages/html'),
		]),
	sql: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/sql'),
			import('prism-code-editor/languages/sql'),
		]),
	yaml: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/yaml'),
			import('prism-code-editor/languages/yaml'),
		]),
	xml: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/xml'),
			import('prism-code-editor/languages/xml'),
		]),
	bash: () =>
		Promise.all([
			import('prism-code-editor/prism/languages/bash'),
			import('prism-code-editor/languages/bash'),
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
