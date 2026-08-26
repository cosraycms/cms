import type { Node as PmNode } from 'prosemirror-model';
import type { RichtextDoc } from '$types/data';

import { parser, schema } from './schema';

/**
 * The cosray richtext storage format (docs/richtext-format.md) and its
 * adapter to and from the live ProseMirror document. The stored format
 * never follows the editor: this module is the only place that knows
 * both shapes.
 */

export const FORMAT = 'cosray-richtext';
export const VERSION = 1;

export type { RichtextDoc, RichtextMark, RichtextNode } from '$types/data';

export type RichtextValue = Record<string, RichtextDoc | null>;

export type RichtextEnvelope = {
	format: typeof FORMAT;
	version: number;
	value: RichtextValue;
};

type Json = Record<string, unknown>;

/** Build the live ProseMirror document for a stored doc (or an empty one). */
export function docToPm(doc: RichtextDoc | null): PmNode {
	if (!doc || doc.type !== 'doc') {
		return emptyPm();
	}

	try {
		return schema.nodeFromJSON(toPmJson(doc as unknown as Json));
	} catch (error) {
		console.error('Could not read the stored richtext document.', error);

		return emptyPm();
	}
}

/** The stored form of a live ProseMirror document. */
export function pmToDoc(pm: PmNode): RichtextDoc {
	return fromPmJson(pm.toJSON() as Json) as unknown as RichtextDoc;
}

/** Parse legacy HTML through the editor schema into the stored format. */
export function htmlToDoc(html: string): RichtextDoc | null {
	if (html.trim() === '') {
		return null;
	}

	const container = document.createElement('div');
	container.innerHTML = html;

	return pmToDoc(parser.parse(container) as unknown as PmNode);
}

function emptyPm(): PmNode {
	return schema.nodes.doc.createAndFill() as PmNode;
}

/**
 * Stored -> ProseMirror JSON: rename `align` to the schema's
 * `textAlign`; everything else is shape-identical (nodeFromJSON fills
 * omitted attribute defaults from the schema).
 */
function toPmJson(node: Json): Json {
	const result: Json = { ...node };
	const attrs = node.attrs as Json | undefined;

	if (attrs && 'align' in attrs) {
		const { align, ...rest } = attrs;
		result.attrs = { ...rest, textAlign: align };
	}

	if (Array.isArray(node.content)) {
		result.content = node.content.map((child) => toPmJson(child as Json));
	}

	return result;
}

/**
 * ProseMirror JSON -> stored: rename `textAlign` back to `align`, drop
 * null attributes and empty attrs/marks. The server normalizes to full
 * canonical form on save; this keeps the payload within the writer-
 * strict vocabulary (no nulls on link target kinds, no empty objects).
 */
function fromPmJson(node: Json): Json {
	const result: Json = { type: node.type };
	const attrs = cleanAttrs(node.attrs as Json | undefined, true);

	if (attrs) {
		result.attrs = attrs;
	}

	if (typeof node.text === 'string') {
		result.text = node.text;
	}

	if (Array.isArray(node.marks) && node.marks.length > 0) {
		result.marks = node.marks.map((mark) => {
			const m = mark as Json;
			const entry: Json = { type: m.type };
			const markAttrs = cleanAttrs(m.attrs as Json | undefined, false);

			if (markAttrs) {
				entry.attrs = markAttrs;
			}

			return entry;
		});
	}

	if (Array.isArray(node.content) && node.content.length > 0) {
		result.content = node.content.map((child) => fromPmJson(child as Json));
	}

	return result;
}

function cleanAttrs(attrs: Json | undefined, renameAlign: boolean): Json | null {
	if (!attrs) {
		return null;
	}

	const result: Json = {};

	for (const [key, value] of Object.entries(attrs)) {
		if (value === null || value === undefined) {
			continue;
		}

		result[renameAlign && key === 'textAlign' ? 'align' : key] = value;
	}

	return Object.keys(result).length > 0 ? result : null;
}
