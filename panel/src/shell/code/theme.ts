import type { Extension } from '@codemirror/state';

import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { EditorView } from '@codemirror/view';
import { tags } from '@lezer/highlight';

const highlightStyle = HighlightStyle.define(
	[
		{ tag: tags.comment, color: '#b8b0ac', fontStyle: 'italic' },
		{ tag: [tags.keyword, tags.modifier, tags.self, tags.null], color: '#c6a0f6' },
		{ tag: [tags.literal, tags.atom, tags.bool, tags.number, tags.color], color: '#8bd5ca' },
		{ tag: [tags.string, tags.character, tags.attributeValue], color: '#f5a97f' },
		{ tag: [tags.regexp, tags.escape, tags.special(tags.string)], color: '#f5bde6' },
		{
			tag: [tags.definition(tags.variableName), tags.function(tags.variableName)],
			color: '#8aadf4',
		},
		{ tag: [tags.variableName, tags.labelName], color: '#f6f5f4' },
		{ tag: [tags.propertyName, tags.attributeName], color: '#b7bdf8' },
		{ tag: [tags.typeName, tags.namespace, tags.className, tags.tagName], color: '#91d7e3' },
		{ tag: [tags.operator, tags.operatorKeyword], color: '#f5bde6' },
		{ tag: [tags.url, tags.link], color: '#8aadf4', textDecoration: 'underline' },
		{ tag: tags.heading, color: '#eed49f', fontWeight: 'bold' },
		{ tag: tags.strong, fontWeight: 'bold' },
		{ tag: tags.emphasis, fontStyle: 'italic' },
		{ tag: tags.strikethrough, textDecoration: 'line-through' },
		{ tag: [tags.meta, tags.annotation, tags.macroName], color: '#eed49f' },
		{ tag: tags.punctuation, color: '#d7d3d1' },
		{ tag: tags.inserted, color: '#a6da95' },
		{ tag: [tags.deleted, tags.invalid], color: '#ed8796' },
	],
	{ themeType: 'dark' },
);

export const cosrayCodeTheme: Extension = [
	EditorView.theme({}, { dark: true }),
	syntaxHighlighting(highlightStyle, { fallback: true }),
];
