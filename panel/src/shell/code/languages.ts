import type { Extension } from '@codemirror/state';
import { StreamLanguage } from '@codemirror/language';

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
type LanguageLoader = () => Promise<Extension>;

const syntaxAliases: Record<string, SyntaxKey> = {
	js: 'javascript',
	ts: 'typescript',
	md: 'markdown',
	yml: 'yaml',
	sh: 'bash',
	plain: 'plaintext',
	text: 'plaintext',
};

const languageLoaders: Record<SyntaxKey, LanguageLoader> = {
	plaintext: async () => [],
	php: async () => {
		const { php } = await import('@codemirror/lang-php');

		return php();
	},
	javascript: async () => {
		const { javascript } = await import('@codemirror/lang-javascript');

		return javascript();
	},
	typescript: async () => {
		const { javascript } = await import('@codemirror/lang-javascript');

		return javascript({ typescript: true });
	},
	html: async () => {
		const { html } = await import('@codemirror/lang-html');

		return html();
	},
	css: async () => {
		const { css } = await import('@codemirror/lang-css');

		return css();
	},
	json: async () => {
		const { json } = await import('@codemirror/lang-json');

		return json();
	},
	markdown: async () => {
		const { markdown } = await import('@codemirror/lang-markdown');

		return markdown();
	},
	sql: async () => {
		const { sql } = await import('@codemirror/lang-sql');

		return sql();
	},
	yaml: async () => {
		const { yaml } = await import('@codemirror/lang-yaml');

		return yaml();
	},
	xml: async () => {
		const { xml } = await import('@codemirror/lang-xml');

		return xml();
	},
	bash: async () => {
		const { shell } = await import('@codemirror/legacy-modes/mode/shell');

		return StreamLanguage.define(shell);
	},
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

export async function loadCodeLanguageExtension(
	syntax: string | null | undefined,
): Promise<Extension> {
	const normalized = normalizeCodeSyntax(syntax);

	return languageLoaders[normalized]();
}
