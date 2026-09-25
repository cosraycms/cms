// Changing a block's type on the real blocks view: the kebab entry, the
// picker it opens, the fresh block in the old one's place and the
// question before content goes. jsdom lays nothing out.

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { install as installCatalog } from '../../src/behaviors/block-catalog';
import { install as installBlocks } from '../../src/behaviors/blocks';
import { install as installPick } from '../../src/behaviors/pick';
import { install as installPlacement } from '../../src/behaviors/placement';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install } from '../../src/behaviors/retype';
import { install as installSplit } from '../../src/behaviors/split';
import { install as installMenus } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const TEXT = 'Cosray\\Block\\Text';
const QUOTE = 'Acme\\Quote';
const RICH = 'Cosray\\Block\\RichText';
// Rich text renders as a custom control; the others as plain inputs.
const CONTROLS: Record<string, Row> = {
	[RICH]: { name: 'element', props: { tag: 'cosray-richtext', module: 'cosray:richtext' } },
};

type Layout = { colspan: number; rowspan: number; col?: number; row?: number };
type Row = Record<string, unknown>;

let uninstall: (() => void) | undefined;

afterEach(() => {
	uninstall?.();
	uninstall = undefined;
	vi.restoreAllMocks();
	document.body.replaceChildren();
	delete window.Cosray;
});

const at = (col: number, row: number, colspan: number, rowspan = 1): Layout => ({
	col,
	row,
	colspan,
	rowspan,
});
const text = (uid: string, layout: Layout, content = uid, meta?: Row): Row => ({
	uid,
	type: TEXT,
	layout,
	fields: { text: { value: { zxx: content } } },
	...(meta ? { meta } : {}),
});
const split = (uid: string, layout: Layout, ...blocks: Row[]): Row => ({ uid, layout, blocks });

const META = {
	name: 'group',
	props: {
		fields: [
			{ key: 'class', control: { name: 'text' } },
			{
				key: 'padding',
				control: {
					name: 'option',
					props: {
						options: [
							{ value: '', label: 'Default' },
							{ value: 'l', label: 'Large' },
						],
					},
				},
			},
		],
	},
};

function view(rows: Row[], types: string[]): string {
	return execFileSync('php', [resolve('../tests/Fixtures/Panel/field.php')], {
		encoding: 'utf8',
		input: JSON.stringify({
			field: {
				name: 'body',
				label: 'Body',
				translate: false,
				control: {
					name: 'blocks',
					props: {
						blockTypes: types.map((type) => ({
							type,
							handle: type.split('\\').pop()!.toLowerCase(),
							label: type.split('\\').pop(),
							fields: [{ name: 'text', control: CONTROLS[type] ?? { name: 'text' } }],
						})),
						meta: META,
						columns: 12,
						min: 2,
					},
				},
			},
			data: { value: { zxx: rows } },
			locales: [{ id: 'en', title: 'English' }],
			defaultLocale: 'en',
			globalLocales: true,
		}),
	});
}

function editor(rows: Row[], types = [TEXT, QUOTE]): { rows: () => HTMLElement[] } {
	installBridge({
		locale: 'en',
		defaultLocale: 'en',
		locales: [],
		customLocales: [],
		prefix: '/cp',
		assets: '',
		debug: false,
		allowedFiles: { file: [], image: [], video: [] },
	});
	document.body.innerHTML = `<form id="node-editor-form" data-content-locale-scope data-content-locale="en">${view(rows, types)}</form>`;

	const stops = [
		installMenus(),
		installRepeater(),
		installBlocks(),
		installPlacement(),
		installCatalog(),
		installPick(),
		installSplit(),
		install(),
	];

	uninstall = () => stops.reverse().forEach((stop) => stop());

	return {
		rows: () => [...document.querySelectorAll<HTMLElement>('.grid > [data-repeater-row]')],
	};
}

function parts(row: HTMLElement): HTMLElement[] {
	return [...row.querySelectorAll<HTMLElement>(':scope > .parts > [data-repeater-row]')];
}

/** A control of the row by the end of its name; none of these rows nest others. */
function own(row: HTMLElement, name: string): HTMLInputElement | HTMLSelectElement {
	return row.querySelector<HTMLInputElement | HTMLSelectElement>(`[name$="${name}"]`)!;
}

function layout(row: HTMLElement): Layout {
	const value = (key: string): number =>
		Number(row.querySelector<HTMLInputElement>(`:scope > input[data-layout="${key}"]`)!.value);

	return {
		col: value('col'),
		row: value('row'),
		colspan: value('colspan'),
		rowspan: value('rowspan'),
	};
}

function uid(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>(':scope > input[data-repeater-uid]')!.value;
}

function type(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>(':scope > input[name$="[type]"]')!.value;
}

function content(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>('input[name$="[fields][text][value][zxx]"]')!.value;
}

/** The block's kebab opened, its type entry clicked, then the picker's choice for `to`. */
function retype(row: HTMLElement, to: string): void {
	row.querySelector<HTMLButtonElement>(':scope > .chrome .kebab')!.click();
	row
		.querySelector<HTMLButtonElement>(':scope > .chrome [data-action-menu] > [data-retype]')!
		.click();

	const picker = document.querySelector<HTMLElement>('.adders [data-action-menu]')!;

	expect(picker.matches(':popover-open')).toBe(true);

	// A backslash in the type defeats an attribute selector.
	[...picker.querySelectorAll<HTMLElement>('[data-repeater-add]')]
		.find((choice) => choice.getAttribute('data-repeater-add') === to)!
		.click();
}

describe('changing a block’s type', () => {
	it('puts a fresh block of the picked type in an empty block’s cells, with its settings', () => {
		const confirm = vi.spyOn(window, 'confirm');
		const { rows } = editor([
			text('a', at(1, 1, 6), '', { class: { zxx: 'wide' }, padding: { zxx: 'l' } }),
			text('b', at(7, 1, 6)),
		]);

		retype(rows()[0], QUOTE);

		const [fresh, other] = rows();

		expect(confirm).not.toHaveBeenCalled();
		expect(rows()).toHaveLength(2);
		expect(type(fresh)).toBe(QUOTE);
		expect(uid(fresh)).toMatch(/^[a-z0-9]{13}$/);
		expect(layout(fresh)).toEqual(at(1, 1, 6));
		expect(own(fresh, '[meta][class][zxx]').value).toBe('wide');
		expect(own(fresh, '[meta][padding][zxx]').value).toBe('l');
		expect(fresh.dataset.padding).toBe('l');
		expect(content(fresh)).toBe('');
		expect(fresh.contains(document.activeElement)).toBe(true);
		expect(uid(other)).toBe('b');
		expect(layout(other)).toEqual(at(7, 1, 6));
		expect(own(fresh, '[uid]').name).toBe('content[body][value][zxx][0][uid]');
	});

	it('asks before a block’s content goes, and keeps the block when declined', () => {
		const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
		const { rows } = editor([text('a', at(1, 1, 12))]);

		retype(rows()[0], QUOTE);

		expect(confirm).toHaveBeenCalledWith('Change the block type? Its content will be removed.');
		expect(rows().map(uid)).toEqual(['a']);
		expect(content(rows()[0])).toBe('a');

		confirm.mockReturnValue(true);
		retype(rows()[0], QUOTE);

		expect(rows()).toHaveLength(1);
		expect(type(rows()[0])).toBe(QUOTE);
		expect(content(rows()[0])).toBe('');
		expect(layout(rows()[0])).toEqual(at(1, 1, 12));
	});

	it('asks for a rich text with words in it, not for an empty one', () => {
		const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
		const doc = (...words: string[]) => ({
			type: 'doc',
			content: [
				{
					type: 'paragraph',
					...(words.length ? { content: words.map((text) => ({ type: 'text', text })) } : {}),
				},
			],
		});
		const rich = (uid: string, layout: Layout, value: unknown): Row => ({
			uid,
			type: RICH,
			layout,
			fields: { text: { value: { zxx: value } } },
		});
		const { rows } = editor(
			[rich('empty', at(1, 1, 6), doc()), rich('words', at(7, 1, 6), doc('Hello'))],
			[RICH, TEXT],
		);

		retype(rows()[0], TEXT);

		expect(confirm).not.toHaveBeenCalled();

		retype(rows()[1], TEXT);

		expect(confirm).toHaveBeenCalledOnce();
		expect(rows().map(type)).toEqual([TEXT, TEXT]);
	});

	it('replaces a part within its split, which keeps its parts', () => {
		vi.spyOn(window, 'confirm').mockReturnValue(true);
		const { rows } = editor([
			split(
				's1',
				at(1, 1, 8),
				text('a', { colspan: 5, rowspan: 1 }),
				text('b', { colspan: 3, rowspan: 1 }),
			),
		]);
		const [container] = rows();

		retype(parts(container)[0], QUOTE);

		const [fresh, kept] = parts(container);

		expect(rows()).toEqual([container]);
		expect(parts(container)).toHaveLength(2);
		expect(type(fresh)).toBe(QUOTE);
		expect(layout(fresh)).toMatchObject({ colspan: 5, rowspan: 1 });
		expect(uid(kept)).toBe('b');
		expect(own(fresh, '[uid]').name).toBe('content[body][value][zxx][0][blocks][0][uid]');
	});

	it('leaves a block picked as its own type as it is', () => {
		const confirm = vi.spyOn(window, 'confirm');
		const { rows } = editor([text('a', at(1, 1, 6))]);

		retype(rows()[0], TEXT);

		expect(confirm).not.toHaveBeenCalled();
		expect(rows().map(uid)).toEqual(['a']);
	});

	it('offers no type to change to in a field of one type', () => {
		const { rows } = editor([text('a', at(1, 1, 6))], [TEXT]);

		expect(rows()[0].querySelector('[data-retype]')).toBeNull();
	});
});
