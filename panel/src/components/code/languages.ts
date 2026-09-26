export const DEFAULT_CODE_SYNTAX = 'plaintext';

export const CODE_SYNTAXES = [
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
] as const;

type SyntaxKey = (typeof CODE_SYNTAXES)[number];

const syntaxAliases: Record<string, SyntaxKey> = {
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
const languageLoaders: Record<SyntaxKey, () => Promise<unknown>> = {
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

export function normalizeCodeSyntax(syntax: string | null | undefined): SyntaxKey {
	const normalized = (syntax ?? DEFAULT_CODE_SYNTAX).trim().toLowerCase();

	if (normalized === '') {
		return DEFAULT_CODE_SYNTAX;
	}

	if (normalized in syntaxAliases) {
		return syntaxAliases[normalized];
	}

	if ((CODE_SYNTAXES as readonly string[]).includes(normalized)) {
		return normalized as SyntaxKey;
	}

	return DEFAULT_CODE_SYNTAX;
}

/** Loads a syntax once and returns the editor language name for it. */
export async function loadCodeLanguage(syntax: string | null | undefined): Promise<SyntaxKey> {
	const key = normalizeCodeSyntax(syntax);
	await languageLoaders[key]();

	return key;
}
