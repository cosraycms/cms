// One line of text in the richtext schema: what a RichLine field edits.
// Enter does nothing, a paste lands on the line, and a guard refuses any
// change that would leave more than one paragraph of text.

/** @import { Node as PmNode } from 'prosemirror-model' */

import { Fragment, Slice } from 'prosemirror-model';
import { Plugin } from 'prosemirror-state';
import { keymap } from 'prosemirror-keymap';
import { schema } from './schema.js';

/**
 * @param {PmNode} doc
 * @returns {boolean}
 */
export function isLine(doc) {
	const paragraph = doc.firstChild;

	if (doc.childCount !== 1 || paragraph?.type !== schema.nodes.paragraph) return false;

	let text = true;
	paragraph.forEach((node) => {
		text &&= node.isText;
	});

	return text;
}

/**
 * The text of a paste with its marks, its blocks joined by spaces.
 *
 * @param {Slice} slice
 * @returns {Slice}
 */
export function flatten(slice) {
	/** @type {PmNode[]} */
	const nodes = [];
	/** @param {PmNode} node */
	const add = (node) => {
		if (node.isText) nodes.push(node);
		else if (node.type === schema.nodes.hardBreak) nodes.push(schema.text(' '));
	};

	slice.content.descendants((node) => {
		if (node.isTextblock) {
			if (nodes.length > 0) nodes.push(schema.text(' '));
			node.forEach(add);

			return false;
		}

		if (node.isInline) {
			add(node);

			return false;
		}

		return true;
	});

	return new Slice(Fragment.fromArray(nodes), 0, 0);
}

/**
 * A stored document as one line, for a field that held more before.
 *
 * @param {PmNode} doc
 * @returns {PmNode}
 */
export function toLine(doc) {
	if (isLine(doc)) return doc;

	const text = flatten(new Slice(doc.content, 0, 0)).content;

	return schema.nodes.doc.create(null, schema.nodes.paragraph.create(null, text));
}

/**
 * @param {string} text
 * @returns {Slice}
 */
export function lineText(text) {
	const line = text.replace(/\s+/g, ' ');

	return line === '' ? Slice.empty : new Slice(Fragment.from(schema.text(line)), 0, 0);
}

/** @returns {Plugin[]} */
export function linePlugins() {
	return [
		keymap({ Enter: () => true, 'Shift-Enter': () => true }),
		new Plugin({ filterTransaction: (tr) => !tr.docChanged || isLine(tr.doc) }),
	];
}
