import { afterEach, describe, expect, it, vi } from 'vitest';

import type { RichtextDoc } from '../../../src/types/data';
import {
	FORMAT,
	VERSION,
	docToPm,
	htmlToDoc,
	pmToDoc,
} from '../../../src/components/richtext/format';
import { schema } from '../../../src/components/richtext/schema';

afterEach(() => {
	vi.restoreAllMocks();
});

describe('richtext format', () => {
	it('exposes the current storage envelope identifiers', () => {
		expect(FORMAT).toBe('cosray-richtext');
		expect(VERSION).toBe(1);
	});

	it('maps stored alignment into the ProseMirror schema', () => {
		const stored: RichtextDoc = {
			type: 'doc',
			content: [
				{
					type: 'paragraph',
					attrs: { class: 'intro', align: 'center' },
					content: [{ type: 'text', text: 'Centered' }],
				},
			],
		};

		const paragraph = docToPm(stored).toJSON().content?.[0];

		expect(paragraph?.attrs).toEqual({ class: 'intro', textAlign: 'center' });
		expect(paragraph?.content?.[0]).toEqual({ type: 'text', text: 'Centered' });
	});

	it('writes schema alignment and strips null mark attributes', () => {
		const pm = schema.nodeFromJSON({
			type: 'doc',
			content: [
				{
					type: 'paragraph',
					attrs: { class: 'default', textAlign: 'right' },
					content: [
						{
							type: 'text',
							text: 'Linked',
							marks: [
								{
									type: 'link',
									attrs: {
										href: 'https://example.test',
										node: null,
										asset: null,
										target: null,
										class: null,
									},
								},
							],
						},
					],
				},
			],
		});

		expect(pmToDoc(pm)).toEqual({
			type: 'doc',
			content: [
				{
					type: 'paragraph',
					attrs: { class: 'default', align: 'right' },
					content: [
						{
							type: 'text',
							text: 'Linked',
							marks: [{ type: 'link', attrs: { href: 'https://example.test' } }],
						},
					],
				},
			],
		});
	});

	it('round-trips a stored document without leaking schema names', () => {
		const stored: RichtextDoc = {
			type: 'doc',
			content: [
				{
					type: 'heading',
					attrs: { level: 2, align: 'left' },
					content: [{ type: 'text', text: 'Heading', marks: [{ type: 'bold' }] }],
				},
			],
		};

		expect(pmToDoc(docToPm(stored))).toEqual(stored);
	});

	it('parses legacy HTML into the storage vocabulary', () => {
		expect(
			htmlToDoc(
				'<h2 style="text-align: center"><strong>Hello</strong> <a data-node="node-a" target="_blank">world</a></h2>',
			),
		).toEqual({
			type: 'doc',
			content: [
				{
					type: 'heading',
					attrs: { level: 2, align: 'center' },
					content: [
						{ type: 'text', text: 'Hello', marks: [{ type: 'bold' }] },
						{ type: 'text', text: ' ' },
						{
							type: 'text',
							text: 'world',
							marks: [{ type: 'link', attrs: { node: 'node-a', target: '_blank' } }],
						},
					],
				},
			],
		});
	});

	it('represents empty legacy HTML as no stored document', () => {
		expect(htmlToDoc('  \n ')).toBeNull();
	});

	it('falls back to an empty document when stored JSON is invalid', () => {
		const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
		const invalid = {
			type: 'doc',
			content: [{ type: 'unknown' }],
		} as unknown as RichtextDoc;

		const result = docToPm(invalid).toJSON();

		expect(error).toHaveBeenCalledWith(
			'Could not read the stored richtext document.',
			expect.any(RangeError),
		);
		expect(result.type).toBe('doc');
		expect(result.content?.[0]?.type).toBe('paragraph');
	});
});
