import { describe, expect, it } from 'vitest';
import { Slice } from 'prosemirror-model';
import { flatten, isLine, lineText, toLine } from '../../../src/elements/richtext/line.js';
import { schema } from '../../../src/elements/richtext/schema.js';

const { doc, paragraph, heading, bulletList, listItem, hardBreak } = schema.nodes;
const bold = schema.marks.bold.create();

function document() {
	return doc.create(null, [
		heading.create({ level: 2 }, schema.text('Title')),
		bulletList.create(null, [
			listItem.create(null, paragraph.create(null, [schema.text('One', [bold])])),
			listItem.create(
				null,
				paragraph.create(null, [schema.text('Two'), hardBreak.create(), schema.text('lines')]),
			),
		]),
	]);
}

describe('one line of richtext', () => {
	it('puts a paste of several blocks on the line, keeping its marks', () => {
		const pasted = flatten(new Slice(document().content, 0, 0));

		expect(pasted.content.textBetween(0, pasted.content.size)).toBe('Title One Two lines');
		const runs: [string | undefined, boolean][] = [];
		pasted.content.forEach((node) => runs.push([node.text, bold.isInSet(node.marks)]));

		expect(runs).toEqual([
			['Title ', false],
			['One', true],
			[' Two lines', false],
		]);
	});

	it('reads a stored document of several paragraphs as one line', () => {
		const line = toLine(document());

		expect(isLine(line)).toBe(true);
		expect(line.textContent).toBe('Title One Two lines');
	});

	it('keeps a line as it is', () => {
		const line = doc.create(null, paragraph.create(null, schema.text('Kept')));

		expect(toLine(line)).toBe(line);
		expect(isLine(doc.create(null, [paragraph.create(), paragraph.create()]))).toBe(false);
	});

	it('takes pasted plain text as one line', () => {
		const pasted = lineText('First\n\nsecond  line').content;

		expect(pasted.textBetween(0, pasted.size)).toBe('First second line');
		expect(lineText('')).toBe(Slice.empty);
	});
});
