// What a richtext field may hold: what its tools offer, and the paragraph
// classes and text styles the project declares. Pastes, drops and typing
// keep to it; stored content keeps whatever it has, so taking a tool away
// loses nothing.

/** @import { Mark, Node as PmNode } from 'prosemirror-model' */

import { Fragment, Slice } from 'prosemirror-model';
import { schema } from './schema.js';

/**
 * @typedef {object} Vocabulary
 * @property {number[]} headings The heading levels on offer.
 * @property {Set<string>} nodes The blocks beyond paragraphs, and images, by node type.
 * @property {Set<string>} marks The marks, by mark type.
 * @property {boolean} align
 * @property {Set<string>} classes Declared paragraph classes.
 * @property {Set<string>} styles Declared text styles.
 */

/** @type {Record<string, string>} */
const MARKS = {
	bold: 'bold',
	italic: 'italic',
	strike: 'strike',
	sub: 'subscript',
	sup: 'superscript',
	link: 'link',
};

/** @type {Record<string, string>} */
const NODES = {
	'bullet-list': 'bulletList',
	'ordered-list': 'orderedList',
	blockquote: 'blockquote',
	hr: 'horizontalRule',
	image: 'image',
};

/**
 * @param {Iterable<string>} tools
 * @param {string[]} [classes]
 * @param {string[]} [styles]
 * @returns {Vocabulary}
 */
export function vocabulary(tools, classes = [], styles = []) {
	const offered = new Set(tools);
	const pick = (/** @type {Record<string, string>} */ map) =>
		new Set(Object.keys(map).flatMap((tool) => (offered.has(tool) ? [map[tool]] : [])));
	const marks = pick(MARKS);

	if (styles.length > 0) marks.add('style');

	return {
		headings: [1, 2, 3].filter((level) => offered.has(`h${level}`)),
		nodes: pick(NODES),
		marks,
		align: offered.has('align'),
		classes: new Set(classes),
		styles: new Set(styles),
	};
}

/**
 * @param {Mark} mark
 * @param {Vocabulary} v
 * @returns {boolean}
 */
function allows(mark, v) {
	return (
		v.marks.has(mark.type.name) &&
		(mark.type !== schema.marks.style || v.styles.has(mark.attrs.class))
	);
}

/**
 * The offered level nearest to a heading's, none without headings.
 *
 * @param {number} level
 * @param {number[]} levels
 * @returns {number | null}
 */
function nearest(level, levels) {
	let best = null;

	for (const candidate of levels) {
		if (best === null || Math.abs(candidate - level) <= Math.abs(best - level)) best = candidate;
	}

	return best;
}

/**
 * @param {Fragment} content
 * @param {Vocabulary} v
 * @returns {Fragment}
 */
function inline(content, v) {
	/** @type {PmNode[]} */
	const nodes = [];

	content.forEach((node) => {
		if (node.isText) nodes.push(node.mark(node.marks.filter((mark) => allows(mark, v))));
		else if (node.type !== schema.nodes.image || v.nodes.has('image')) nodes.push(node);
	});

	return Fragment.fromArray(nodes);
}

/**
 * @param {Fragment} content
 * @param {Vocabulary} v
 * @returns {PmNode[]}
 */
function blocks(content, v) {
	return content.content.flatMap((node) => block(node, v));
}

/**
 * @param {PmNode} item
 * @param {Vocabulary} v
 * @returns {PmNode}
 */
function listItem(item, v) {
	const content = blocks(item.content, v);

	if (content[0]?.type !== schema.nodes.paragraph) content.unshift(schema.nodes.paragraph.create());

	return schema.nodes.listItem.create(null, content);
}

/**
 * @param {PmNode} node
 * @param {Vocabulary} v
 * @returns {PmNode[]}
 */
function block(node, v) {
	const { nodes } = schema;
	const textAlign = v.align ? (node.attrs.textAlign ?? null) : null;

	switch (node.type) {
		case nodes.paragraph: {
			const cls = v.classes.has(node.attrs.class) ? node.attrs.class : 'default';

			return [nodes.paragraph.create({ class: cls, textAlign }, inline(node.content, v))];
		}
		case nodes.heading: {
			const level = nearest(node.attrs.level, v.headings);

			return [
				level === null
					? nodes.paragraph.create({ textAlign }, inline(node.content, v))
					: nodes.heading.create({ level, textAlign }, inline(node.content, v)),
			];
		}
		case nodes.bulletList:
		case nodes.orderedList:
			return v.nodes.has(node.type.name)
				? [
						node.type.create(
							node.attrs,
							node.content.content.map((item) => listItem(item, v)),
						),
					]
				: node.content.content.flatMap((item) => blocks(item.content, v));
		case nodes.listItem:
			return v.nodes.has('bulletList') || v.nodes.has('orderedList')
				? [listItem(node, v)]
				: blocks(node.content, v);
		case nodes.blockquote: {
			const content = blocks(node.content, v);

			if (!v.nodes.has('blockquote')) return content;

			return content.length > 0 ? [nodes.blockquote.create(null, content)] : [];
		}
		case nodes.codeBlock:
			return node.textContent
				.split('\n')
				.filter((line) => line.trim() !== '')
				.map((line) => nodes.paragraph.create(null, schema.text(line)));
		case nodes.horizontalRule:
			return v.nodes.has('horizontalRule') ? [node] : [];
		default:
			return [node];
	}
}

/**
 * A paste or drop with only what the field offers: a heading takes the
 * nearest level on offer or becomes a paragraph, lists and quotes give up
 * their blocks, a code block becomes paragraphs, rules and images go, and
 * so do marks, paragraph classes, text styles and alignment the field does
 * not offer. Line breaks stay.
 *
 * @param {Slice} slice
 * @param {Vocabulary} v
 * @returns {Slice}
 */
export function clean(slice, v) {
	const first = slice.content.firstChild;

	if (!first) return slice;

	if (first.isInline) return new Slice(inline(slice.content, v), slice.openStart, slice.openEnd);

	const content = Fragment.fromArray(blocks(slice.content, v));
	const open = Slice.maxOpen(content);

	return new Slice(
		content,
		Math.min(slice.openStart, open.openStart),
		Math.min(slice.openEnd, open.openEnd),
	);
}
