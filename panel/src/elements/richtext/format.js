/** @import { Node as PmNode } from 'prosemirror-model' */
/** @import { RichtextNode } from '../../types/data' */

import { parser, schema } from './schema.js';

/**
 * The cosray richtext storage format (docs/richtext-format.md) and its
 * adapter to and from the live ProseMirror document. The stored format
 * never follows the editor: this module is the only place that knows
 * both shapes.
 */

export const FORMAT = 'cosray-richtext';
export const VERSION = 1;

/** @typedef {import('../../types/data').RichtextDoc} RichtextDoc */
/** @typedef {import('../../types/data').RichtextMark} RichtextMark */
/** @typedef {Record<string, RichtextDoc | null>} RichtextValue */
/** @typedef {{ format: typeof FORMAT, version: number, value: RichtextValue }} RichtextEnvelope */
/** @typedef {Record<string, unknown>} Json */

/**
 * @param {RichtextDoc | null} doc
 * @returns {PmNode}
 */
export function docToPm(doc) {
	if (!doc || doc.type !== 'doc') {
		return emptyPm();
	}

	try {
		return schema.nodeFromJSON(toPmJson(/** @type {Json} */ (/** @type {unknown} */ (doc))));
	} catch (error) {
		console.error('Could not read the stored richtext document.', error);

		return emptyPm();
	}
}

/**
 * @param {PmNode} pm
 * @returns {RichtextDoc}
 */
export function pmToDoc(pm) {
	return /** @type {RichtextDoc} */ (/** @type {unknown} */ (fromPmJson(pm.toJSON())));
}

/**
 * @param {RichtextDoc | null | undefined} doc
 * @returns {boolean}
 */
export function isFilledDoc(doc) {
	if (!doc || doc.type !== 'doc') {
		return false;
	}

	/**
	 * @param {RichtextNode} node
	 * @returns {boolean}
	 */
	function filled(node) {
		if (typeof node.text === 'string' && node.text.trim() !== '') {
			return true;
		}

		if (node.type === 'image' || node.type === 'horizontalRule') {
			return true;
		}

		return node.content?.some(filled) ?? false;
	}

	return doc.content.some(filled);
}

/**
 * @param {string} html
 * @returns {RichtextDoc | null}
 */
export function htmlToDoc(html) {
	if (html.trim() === '') {
		return null;
	}

	const container = document.createElement('div');
	container.innerHTML = html;

	return pmToDoc(parser.parse(container));
}

/** @returns {PmNode} */
function emptyPm() {
	return /** @type {PmNode} */ (schema.nodes.doc.createAndFill());
}

/**
 * Stored -> ProseMirror JSON: rename `align` to the schema's
 * `textAlign`; everything else is shape-identical (nodeFromJSON fills
 * omitted attribute defaults from the schema).
 *
 * @param {Json} node
 * @returns {Json}
 */
function toPmJson(node) {
	/** @type {Json} */
	const result = { ...node };
	const attrs = /** @type {Json | undefined} */ (node.attrs);

	if (attrs && 'align' in attrs) {
		const { align, ...rest } = attrs;
		result.attrs = { ...rest, textAlign: align };
	}

	if (Array.isArray(node.content)) {
		result.content = node.content.map((child) => toPmJson(child));
	}

	return result;
}

/**
 * ProseMirror JSON -> stored: rename `textAlign` back to `align`, drop
 * null attributes and empty attrs/marks. The server normalizes to full
 * canonical form on save; this keeps the payload within the writer-
 * strict vocabulary (no nulls on link target kinds, no empty objects).
 *
 * @param {Json} node
 * @returns {Json}
 */
function fromPmJson(node) {
	/** @type {Json} */
	const result = { type: node.type };
	const attrs = cleanAttrs(/** @type {Json | undefined} */ (node.attrs), true);

	if (attrs) {
		result.attrs = attrs;
	}

	if (typeof node.text === 'string') {
		result.text = node.text;
	}

	if (Array.isArray(node.marks) && node.marks.length > 0) {
		result.marks = node.marks.map((mark) => {
			/** @type {Json} */
			const entry = { type: mark.type };
			const markAttrs = cleanAttrs(mark.attrs, false);

			if (markAttrs) {
				entry.attrs = markAttrs;
			}

			return entry;
		});
	}

	if (Array.isArray(node.content) && node.content.length > 0) {
		result.content = node.content.map((child) => fromPmJson(child));
	}

	return result;
}

/**
 * @param {Json | undefined} attrs
 * @param {boolean} renameAlign
 * @returns {Json | null}
 */
function cleanAttrs(attrs, renameAlign) {
	if (!attrs) {
		return null;
	}

	/** @type {Json} */
	const result = {};

	for (const [key, value] of Object.entries(attrs)) {
		if (value === null || value === undefined) {
			continue;
		}

		result[renameAlign && key === 'textAlign' ? 'align' : key] = value;
	}

	return Object.keys(result).length > 0 ? result : null;
}
