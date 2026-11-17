import { describe, expect, it } from 'vitest';
import { DOMParser as PmParser, Slice } from 'prosemirror-model';
import { parser, schema } from '../../../src/elements/richtext/schema.js';
import { clean, vocabulary } from '../../../src/elements/richtext/vocabulary.js';
import { pmToDoc } from '../../../src/elements/richtext/format.js';
import type { RichtextNode } from '../../../src/types/data';

/** What a paste of this HTML brings into a field with these tools, as stored. */
function paste(html: string, tools: string[], classes: string[] = [], styles: string[] = []) {
	const container = document.createElement('div');
	container.innerHTML = html;
	const slice = (parser as PmParser).parseSlice(container);
	const cleaned = clean(slice, vocabulary(tools, classes, styles));

	return withoutDefaults(pmToDoc(schema.nodes.doc.create(null, cleaned.content)).content ?? []);
}

// The editor writes the default paragraph class; storage drops it.
function withoutDefaults(nodes: RichtextNode[]): RichtextNode[] {
	return nodes.map(({ attrs, content, ...node }) => {
		const { class: cls, ...rest } = attrs ?? {};
		const own = cls === undefined || cls === 'default' ? rest : { class: cls, ...rest };

		return {
			...node,
			...(Object.keys(own).length > 0 ? { attrs: own } : {}),
			...(content ? { content: withoutDefaults(content) } : {}),
		};
	});
}

const DEFAULT = [
	'undo',
	'redo',
	'bold',
	'italic',
	'strike',
	'h2',
	'h3',
	'bullet-list',
	'ordered-list',
	'link',
];

describe('a paste keeps to what the field offers', () => {
	it('takes Word’s classes and spans for plain paragraphs', () => {
		expect(
			paste(
				'<p class="MsoNormal">One <span class="SpellE">word</span> <u>under</u> <code>x</code></p>',
				DEFAULT,
			),
		).toEqual([{ type: 'paragraph', content: [{ type: 'text', text: 'One word under x' }] }]);
	});

	it('keeps what the field offers', () => {
		expect(
			paste(
				'<h2>Title</h2><ul><li><p><strong>Bold</strong> <a href="https://example.com">link</a></p></li></ul>',
				DEFAULT,
			),
		).toEqual([
			{ type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Title' }] },
			{
				type: 'bulletList',
				content: [
					{
						type: 'listItem',
						content: [
							{
								type: 'paragraph',
								content: [
									{ type: 'text', text: 'Bold', marks: [{ type: 'bold' }] },
									{ type: 'text', text: ' ' },
									{
										type: 'text',
										text: 'link',
										marks: [{ type: 'link', attrs: { href: 'https://example.com' } }],
									},
								],
							},
						],
					},
				],
			},
		]);
	});

	it('moves a heading to the nearest level on offer', () => {
		const levels = paste('<h1>One</h1><h4>Four</h4>', DEFAULT).map((block) => block.attrs?.level);

		expect(levels).toEqual([2, 3]);
	});

	it('gives a field of marks only paragraphs', () => {
		const blocks = paste(
			'<h2>Title</h2><ul><li><p>Item</p></li></ul><blockquote><p>Quote</p></blockquote><pre>a\nb</pre><hr><p style="text-align: center"><sub>low</sub></p>',
			['bold', 'italic', 'link'],
		);

		// The rule is gone, the alignment and the subscript too.
		expect(blocks).toEqual(
			['Title', 'Item', 'Quote', 'a', 'b', 'low'].map((text) => ({
				type: 'paragraph',
				content: [{ type: 'text', text }],
			})),
		);
	});

	it('keeps declared classes and styles only', () => {
		expect(
			paste(
				'<p class="lead"><span class="small">A</span></p><p class="other"><span class="loud">B</span></p>',
				DEFAULT,
				['lead'],
				['small'],
			),
		).toEqual([
			{
				type: 'paragraph',
				attrs: { class: 'lead' },
				content: [
					{ type: 'text', text: 'A', marks: [{ type: 'style', attrs: { class: 'small' } }] },
				],
			},
			{ type: 'paragraph', content: [{ type: 'text', text: 'B' }] },
		]);
	});

	it('keeps line breaks', () => {
		expect(paste('<p>One<br>two</p>', ['bold'])[0].content?.map((node) => node.type)).toEqual([
			'text',
			'hardBreak',
			'text',
		]);
	});

	it('leaves a paste of inline content open', () => {
		const slice = new Slice(
			schema.nodes.paragraph.create(null, schema.text('x', [schema.marks.code.create()])).content,
			0,
			0,
		);

		expect(clean(slice, vocabulary(['bold'])).content.firstChild?.marks).toEqual([]);
	});
});
