// Splits on the real blocks view: the kebab's split entries, the picker
// they open, the parts' names and layouts, taking parts away and resizing
// a split. jsdom lays nothing out, and nothing here measures.

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { install as installCatalog } from '../../src/behaviors/block-catalog';
import { install as installBlocks } from '../../src/behaviors/blocks';
import { install as installPick } from '../../src/behaviors/pick';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install } from '../../src/behaviors/split';
import { install as installMenus } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const TEXT = 'Cosray\\Block\\Text';
const QUOTE = 'Acme\\Quote';
const BASE = 'content[body][value][zxx]';

type Layout = { colspan: number; rowspan: number };
type Row = Record<string, unknown>;

let uninstall: (() => void) | undefined;

afterEach(() => {
	uninstall?.();
	uninstall = undefined;
	document.body.replaceChildren();
	delete window.Cosray;
});

const area = (colspan: number, rowspan = 1): Layout => ({ colspan, rowspan });
const text = (uid: string, layout: Layout): Row => ({
	uid,
	type: TEXT,
	layout,
	fields: { text: { value: { zxx: uid } } },
});
const split = (uid: string, layout: Layout, ...blocks: Row[]): Row => ({ uid, layout, blocks });

function view(rows: Row[], types: string[], common?: string[]): string {
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
							fields: [{ name: 'text', control: { name: 'text' } }],
						})),
						...(common ? { commonTypes: common } : {}),
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

function editor(rows: Row[], types = [TEXT], common?: string[]): { rows: () => HTMLElement[] } {
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
	document.body.innerHTML = `<form id="node-editor-form" data-content-locale-scope data-content-locale="en">${view(rows, types, common)}</form>`;

	const stops = [
		installMenus(),
		installRepeater(),
		installBlocks(),
		installCatalog(),
		installPick(),
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

function layout(row: HTMLElement): Layout {
	const value = (dimension: string): number =>
		Number(
			row.querySelector<HTMLInputElement>(`:scope > input[data-layout="${dimension}"]`)!.value,
		);

	return { colspan: value('colspan'), rowspan: value('rowspan') };
}

function uid(row: HTMLElement): HTMLInputElement {
	return row.querySelector<HTMLInputElement>(':scope > input[data-repeater-uid]')!;
}

function type(row: HTMLElement): string | undefined {
	return row.querySelector<HTMLInputElement>(':scope > input[name$="[type]"]')?.value;
}

/** The row's kebab opened, then its entry for this place clicked. */
function act(row: HTMLElement, selector: string): HTMLButtonElement {
	row.querySelector<HTMLButtonElement>(':scope > .chrome .kebab')!.click();

	const entry = row.querySelector<HTMLButtonElement>(
		`:scope > .chrome [data-action-menu] > ${selector}`,
	)!;

	entry.click();

	return entry;
}

/** Clicks the choice for a type; a backslash in the name defeats an attribute selector. */
function pick(root: ParentNode, attribute: string, value: string): void {
	[...root.querySelectorAll<HTMLElement>(`[${attribute}]`)]
		.find((element) => element.getAttribute(attribute) === value)!
		.click();
}

function number(row: HTMLElement, dimension: string, value: string): void {
	const control = row.querySelector<HTMLInputElement>(
		`:scope > dialog input[data-layout-input="${dimension}"]`,
	)!;

	control.value = value;
	control.dispatchEvent(new Event('change', { bubbles: true }));
}

const COLUMNS = '[data-split-into="columns"][data-places="block"]';
const ROWS = '[data-split-into="rows"][data-places="block"]';

describe('splitting a block', () => {
	it('puts a split in its place holding it and the picked type, the larger half kept', () => {
		const { rows } = editor([text('a', area(5, 2)), text('b', area(12))], [TEXT, QUOTE]);
		const [block] = rows();

		act(block, COLUMNS);

		const picker = document.querySelector<HTMLElement>('.adders [data-action-menu]')!;

		expect(picker.matches(':popover-open')).toBe(true);

		pick(picker, 'data-repeater-add', QUOTE);

		const [container, after] = rows();
		const [kept, added] = parts(container);

		expect(rows()).toHaveLength(2);
		expect(uid(after).value).toBe('b');
		expect(uid(container).value).toMatch(/^[a-z0-9]{13}$/);
		expect(container.dataset.split).toBe('columns');
		expect(type(container)).toBeUndefined();
		expect(layout(container)).toEqual(area(5, 2));
		expect(kept).toBe(block);
		expect(layout(kept)).toEqual(area(3, 2));
		expect(layout(added)).toEqual(area(2, 2));
		expect(type(added)).toBe(QUOTE);
		expect(uid(added).value).not.toBe('');
		expect(container.querySelector<HTMLElement>(':scope > .parts')!.dataset).toMatchObject({
			name: `${BASE}[0][blocks]`,
			columns: '5',
		});
		expect(uid(container).name).toBe(`${BASE}[0][uid]`);
		expect(uid(kept).name).toBe(`${BASE}[0][blocks][0][uid]`);
		expect(uid(added).name).toBe(`${BASE}[0][blocks][1][uid]`);
		expect(
			added.querySelector('input[name$="[fields][text][value][zxx]"]')!.getAttribute('name'),
		).toBe(`${BASE}[0][blocks][1][fields][text][value][zxx]`);
		expect(uid(after).name).toBe(`${BASE}[1][uid]`);
		expect(added.contains(document.activeElement)).toBe(true);
	});

	it('stamps the field’s one type below the block in a split one row taller', () => {
		const { rows } = editor([text('a', area(6, 2))]);

		act(rows()[0], ROWS);

		const [container] = rows();
		const [kept, added] = parts(container);

		expect(container.dataset.split).toBe('rows');
		expect(layout(container)).toEqual(area(6, 3));
		expect(layout(kept)).toEqual(area(6, 2));
		expect(layout(added)).toEqual(area(6, 1));
		expect(type(added)).toBe(TEXT);
	});

	it('takes a catalog choice from the split’s picker', () => {
		const { rows } = editor([text('a', area(6))], [TEXT, QUOTE], [TEXT]);

		act(rows()[0], COLUMNS);
		document.querySelector<HTMLElement>('.adders [data-block-catalog-open]')!.click();

		const dialog = document.querySelector<HTMLDialogElement>('dialog[open]')!;

		pick(dialog, 'data-block-choice', QUOTE);

		const [, added] = parts(rows()[0]);

		expect(rows()).toHaveLength(1);
		expect(type(added)).toBe(QUOTE);
		expect(layout(added)).toEqual(area(3));
	});

	it('splits a part again within its split', () => {
		const { rows } = editor([
			split('s1', area(8), text('a', area(4)), text('b', area(4))),
			split('s2', area(6, 2), text('c', area(6)), text('d', area(6))),
		]);
		const [columns, stacked] = rows();

		act(parts(columns)[1], '[data-split-into="columns"][data-places="columns"]');
		act(parts(stacked)[1], '[data-split-into="rows"][data-places="rows"]');

		expect(parts(columns).map(layout)).toEqual([area(4), area(2), area(2)]);
		expect(parts(columns).map((part) => uid(part).name)).toEqual(
			[0, 1, 2].map((i) => `${BASE}[0][blocks][${i}][uid]`),
		);
		expect(layout(stacked)).toEqual(area(6, 3));
		expect(parts(stacked).map(layout)).toEqual([area(6), area(6), area(6)]);
		expect(uid(parts(stacked)[2]).name).toBe(`${BASE}[1][blocks][2][uid]`);
	});

	it('offers only the splits there is room for', () => {
		const { rows } = editor([
			text('a', area(3, 6)),
			split('s1', area(6), text('b', area(3)), text('c', area(3))),
		]);
		const [block, container] = rows();
		const narrow = parts(container)[0];

		expect(act(block, COLUMNS).disabled).toBe(true);
		expect(act(block, ROWS).disabled).toBe(true);
		expect(act(narrow, '[data-split-into="columns"][data-places="columns"]').disabled).toBe(true);
		expect(rows()).toHaveLength(2);
		expect(parts(container)).toHaveLength(2);
	});
});

describe('removing a part', () => {
	const REMOVE = '[data-split-remove]';

	it('hands its width to the previous part, the first part’s to the next', () => {
		const { rows } = editor([
			split('s1', area(12), text('a', area(4)), text('b', area(4)), text('c', area(4))),
			split('s2', area(12), text('d', area(4)), text('e', area(4)), text('f', area(4))),
		]);
		const [middle, first] = rows();

		act(parts(middle)[1], REMOVE);
		act(parts(first)[0], REMOVE);

		expect(parts(middle).map((part) => uid(part).value)).toEqual(['a', 'c']);
		expect(parts(middle).map(layout)).toEqual([area(8), area(4)]);
		expect(uid(parts(middle)[1]).name).toBe(`${BASE}[0][blocks][1][uid]`);
		expect(parts(first).map((part) => uid(part).value)).toEqual(['e', 'f']);
		expect(parts(first).map(layout)).toEqual([area(8), area(4)]);
	});

	it('turns a split left with one part back into that block, with the split’s layout', () => {
		const { rows } = editor([
			text('x', area(3)),
			split('s1', area(6, 2), text('a', area(3, 2)), text('b', area(3, 2))),
			split('s2', area(6, 3), text('c', area(6, 2)), text('d', area(6))),
		]);

		act(parts(rows()[1])[1], REMOVE);
		act(parts(rows()[2])[0], REMOVE);

		const [, left, lower] = rows();

		expect(rows().map((row) => uid(row).value)).toEqual(['x', 'a', 'd']);
		expect(layout(left)).toEqual(area(6, 2));
		expect(uid(left).name).toBe(`${BASE}[1][uid]`);
		expect(
			left.querySelector('input[name$="[fields][text][value][zxx]"]')!.getAttribute('name'),
		).toBe(`${BASE}[1][fields][text][value][zxx]`);
		// A stacked split shrinks by the removed part's rows first.
		expect(layout(lower)).toEqual(area(6));
		expect(document.querySelector('.is-split')).toBeNull();
	});
});

describe('resizing a split', () => {
	it('shares a new width among parts side by side and gives them its height', () => {
		const { rows } = editor([split('s1', area(6), text('a', area(2)), text('b', area(4)))]);
		const [container] = rows();

		number(container, 'colspan', '9');

		expect(parts(container).map(layout)).toEqual([area(3), area(6)]);
		expect(container.querySelector<HTMLElement>(':scope > .parts')!.dataset.columns).toBe('9');

		number(container, 'rowspan', '2');

		expect(parts(container).map(layout)).toEqual([area(3, 2), area(6, 2)]);

		// Never narrower than each part's minimum.
		number(container, 'colspan', '3');

		expect(layout(container).colspan).toBe(4);
		expect(parts(container).map((part) => layout(part).colspan)).toEqual([2, 2]);
	});

	it('keeps a stacked split as tall as its parts and as wide as they are', () => {
		const { rows } = editor([split('s1', area(6, 2), text('a', area(6)), text('b', area(6)))]);
		const [container] = rows();
		const grip = container.querySelector<HTMLElement>(':scope > .chrome [data-repeater-grip]')!;

		grip.dispatchEvent(
			new KeyboardEvent('keydown', {
				key: 'ArrowDown',
				altKey: true,
				bubbles: true,
				cancelable: true,
			}),
		);

		expect(layout(container)).toEqual(area(6, 2));

		number(parts(container)[0], 'rowspan', '3');

		expect(layout(container)).toEqual(area(6, 4));

		number(container, 'colspan', '8');

		expect(parts(container).map(layout)).toEqual([area(8, 3), area(8)]);
	});

	it('trades width between neighbouring parts, never below the minimum', () => {
		const { rows } = editor([split('s1', area(8), text('a', area(4)), text('b', area(4)))]);
		const [container] = rows();
		const [first, last] = parts(container);

		number(first, 'colspan', '5');

		expect(parts(container).map(layout)).toEqual([area(5), area(3)]);

		number(first, 'colspan', '7');

		expect(parts(container).map(layout)).toEqual([area(6), area(2)]);
		// The dialog shows what the part kept.
		expect(
			first.querySelector<HTMLInputElement>('dialog [data-layout-input="colspan"]')!.value,
		).toBe('6');

		// The last part trades with the one before it.
		last.querySelector<HTMLElement>(':scope > .chrome [data-repeater-grip]')!.dispatchEvent(
			new KeyboardEvent('keydown', {
				key: 'ArrowRight',
				altKey: true,
				bubbles: true,
				cancelable: true,
			}),
		);

		expect(parts(container).map(layout)).toEqual([area(5), area(3)]);
		expect(layout(container)).toEqual(area(8));
	});
});
