// The fills are tested on plain numbers, the rendering on hand-built DOM
// mirroring what panel/views/field/blocks.php renders, and the insert
// paths on the real view. jsdom lays nothing out, so the grid's resolved
// tracks and the rows' boxes are stubbed throughout.

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install as installCatalog } from '../../src/behaviors/block-catalog';
import { install as installBlocks } from '../../src/behaviors/blocks';
import { fills, install, tracks, type Occupant } from '../../src/behaviors/ghosts';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installMenus, openMenu } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const TRACK = 100;

let frames: Map<number, FrameRequestCallback>;
let nextFrame: number;
let uninstall: (() => void) | undefined;

beforeEach(() => {
	frames = new Map();
	nextFrame = 0;
	vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
		frames.set(++nextFrame, callback);
		return nextFrame;
	});
	vi.stubGlobal('cancelAnimationFrame', (id: number) => frames.delete(id));
});

afterEach(() => {
	uninstall?.();
	uninstall = undefined;
	document.body.replaceChildren();
	delete window.Cosray;
	vi.unstubAllGlobals();
});

async function paint(): Promise<void> {
	await Promise.resolve();
	const callbacks = [...frames.values()];
	frames.clear();
	callbacks.forEach((callback) => callback(0));
}

function block(row: number, col: number, colspan: number, rowspan = 1, indent = 0): Occupant {
	return {
		row: document.createElement('div'),
		box: { row, col, rowspan, colspan },
		area: { row, col: col - indent, rowspan, colspan: colspan + indent },
	};
}

describe('ghost fills', () => {
	it('reads track edges from a resolved template with the gap between', () => {
		expect(tracks('100px 100px 100px', 10)).toEqual({
			starts: [0, 110, 220],
			ends: [100, 210, 320],
		});
		expect(tracks('none', 0)).toEqual({ starts: [], ends: [] });
	});

	it('fills the rest of a row before the block that wrapped', () => {
		const first = block(0, 0, 8);
		const wrapped = block(1, 0, 12);

		expect(fills([first, wrapped], 12, 2, 1)).toEqual([
			{ row: 0, col: 8, rowspan: 1, colspan: 4, kind: 'flow', target: wrapped.row },
		]);
	});

	it('merges the rows beside a tall block into one fill before the block below', () => {
		const tall = block(0, 0, 4, 3);
		const beside = block(0, 4, 8);
		const below = block(3, 0, 12);

		expect(fills([tall, beside, below], 12, 4, 1)).toEqual([
			{ row: 1, col: 4, rowspan: 2, colspan: 8, kind: 'flow', target: below.row },
		]);
	});

	it('treats the cells an indent leaves free as that block’s own gap', () => {
		const indented = block(0, 3, 6, 1, 3);
		const next = block(1, 0, 12);

		expect(fills([indented, next], 12, 2, 1)).toEqual([
			{ row: 0, col: 0, rowspan: 1, colspan: 3, kind: 'indent', target: indented.row },
			{ row: 0, col: 9, rowspan: 1, colspan: 3, kind: 'flow', target: next.row },
		]);
	});

	it('appends into the tail of the last row', () => {
		expect(fills([block(0, 0, 6)], 12, 1, 1)).toEqual([
			{ row: 0, col: 6, rowspan: 1, colspan: 6, kind: 'flow', target: null },
		]);
	});

	it('offers no run narrower than the field minimum', () => {
		expect(fills([block(0, 0, 11)], 12, 1, 2)).toEqual([]);
	});

	it('skips a gap the flow would pass over for an earlier spot the block fits', () => {
		const first = block(0, 0, 4);
		const tall = block(0, 4, 2, 2);
		const wide = block(2, 0, 12);

		expect(fills([first, tall, wide], 12, 3, 1)).toEqual([
			{ row: 0, col: 6, rowspan: 2, colspan: 6, kind: 'flow', target: wide.row },
		]);
	});
});

type Box = { row: number; col: number; colspan: number; rowspan?: number; indent?: number };

function place(row: HTMLElement, box: Box): void {
	row.dataset.indent = String(box.indent ?? 0);
	row.getBoundingClientRect = () =>
		new DOMRect(box.col * TRACK, box.row * TRACK, box.colspan * TRACK, (box.rowspan ?? 1) * TRACK);
}

/** Resolved tracks for a grid whose implicit rows a test shrinks as rows move up. */
function stubTracks(grid: HTMLElement, columns: number, size: { rows: number }): void {
	const original = getComputedStyle;

	grid.getBoundingClientRect = () => new DOMRect(0, 0, columns * TRACK, size.rows * TRACK);
	vi.stubGlobal('getComputedStyle', (element: Element, pseudo?: string | null) =>
		element === grid
			? ({
					display: 'grid',
					gridTemplateColumns: `${TRACK}px `.repeat(columns).trim(),
					gridTemplateRows: `${TRACK}px `.repeat(size.rows).trim(),
					columnGap: '0px',
					rowGap: '0px',
					paddingLeft: '0px',
					paddingTop: '0px',
				} as CSSStyleDeclaration)
			: original(element, pseudo),
	);
}

function canvas(
	boxes: Box[],
	rows: number,
	options: { columns?: number; min?: number; list?: boolean; readonly?: boolean } = {},
): { grid: HTMLElement; rows: HTMLElement[]; adder: HTMLElement; size: { rows: number } } {
	const columns = options.columns ?? 12;
	const size = { rows };
	const field = document.createElement('div');

	field.className = 'cms-field';

	if (options.readonly) {
		field.dataset.readonly = 'true';
	}

	field.innerHTML = `<div
		class="cms-blocks-editor ${options.list ? 'is-list' : 'is-grid'}"
		data-repeater
		data-name="content[body][value][zxx]"
		data-id="field-body"
		data-columns="${columns}"
		data-min="${options.min ?? 1}"
		data-ghost-label="Add block here"
		style="--columns: ${columns}">
		<div class="grid" data-repeater-list></div>
		<div class="adders" data-repeater-footer>
			<button type="button" class="adder" data-repeater-add="Acme\\Text" data-repeater-insert="append">Add block</button>
		</div>
	</div>`;

	const grid = field.querySelector<HTMLElement>('.grid')!;
	const elements = boxes.map((box) => {
		const row = document.createElement('div');

		row.className = 'block';
		row.setAttribute('data-repeater-row', '');
		place(row, box);
		grid.append(row);

		return row;
	});

	stubTracks(grid, columns, size);
	document.body.append(field);

	return { grid, rows: elements, adder: field.querySelector<HTMLElement>('.adder')!, size };
}

function ghosts(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-ghost]'));
}

describe('ghost rendering', () => {
	it('places a button on every gap with the size in its name', async () => {
		const { grid } = canvas(
			[
				{ row: 0, col: 0, colspan: 4, rowspan: 2 },
				{ row: 0, col: 4, colspan: 8 },
				{ row: 2, col: 0, colspan: 6 },
			],
			3,
		);
		uninstall = install();
		await paint();

		const found = ghosts(grid);

		expect(found.map((ghost) => ghost.style.gridRow)).toEqual(['2 / span 1', '3 / span 1']);
		expect(found.map((ghost) => ghost.style.gridColumn)).toEqual(['5 / span 8', '7 / span 6']);
		expect(found.map((ghost) => ghost.getAttribute('aria-label'))).toEqual([
			'Add block here, 8 × 1',
			'Add block here, 6 × 1',
		]);
		expect(found.every((ghost) => ghost instanceof HTMLButtonElement)).toBe(true);
	});

	it('keeps its buttons while the gaps stay and replaces them when a row moves', async () => {
		const { grid, rows, size } = canvas(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 8 },
			],
			2,
		);
		uninstall = install();
		await paint();

		const before = ghosts(grid);

		expect(before).toHaveLength(2);

		grid.dispatchEvent(new Event('change', { bubbles: true }));
		await paint();

		expect(ghosts(grid)).toEqual(before);

		place(rows[1], { row: 0, col: 8, colspan: 4 });
		rows[1].style.setProperty('--colspan', '4');
		size.rows = 1;
		await paint();

		expect(ghosts(grid)).toEqual([]);
	});

	it('shows nothing on a list, a read-only field or a grid mid-drag', async () => {
		const list = canvas([{ row: 0, col: 0, colspan: 6 }], 1, { list: true });
		const locked = canvas([{ row: 0, col: 0, colspan: 6 }], 1, { readonly: true });
		const dragged = canvas([{ row: 0, col: 0, colspan: 6 }], 1);

		dragged.rows[0].classList.add('sortable-ghost');
		uninstall = install();
		await paint();

		expect(ghosts(list.grid)).toEqual([]);
		expect(ghosts(locked.grid)).toEqual([]);
		expect(ghosts(dragged.grid)).toEqual([]);

		dragged.rows[0].classList.remove('sortable-ghost');
		dragged.grid.dispatchEvent(new Event('change', { bubbles: true }));
		await paint();

		expect(ghosts(dragged.grid)).toHaveLength(1);
	});

	it('hands focus on when the focused gap is redrawn or vanishes', async () => {
		const { grid, rows, adder, size } = canvas(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 8 },
			],
			2,
		);
		uninstall = install();
		await paint();
		ghosts(grid)[0].focus();

		place(rows[1], { row: 1, col: 0, colspan: 6 });
		rows[1].style.setProperty('--colspan', '6');
		await paint();

		expect(ghosts(grid).map((ghost) => ghost.dataset.colspan)).toEqual(['4', '6']);
		expect(document.activeElement).toBe(ghosts(grid)[0]);

		place(rows[1], { row: 0, col: 8, colspan: 4 });
		rows[1].style.setProperty('--colspan', '4');
		size.rows = 1;
		await paint();

		expect(ghosts(grid)).toEqual([]);
		expect(document.activeElement).toBe(adder);
	});
});

const TEXT = 'Cosray\\Block\\Text';
const QUOTE = 'Acme\\Quote';

function view(rows: Box[], options: { types?: string[]; common?: string[] } = {}): string {
	const types = (options.types ?? [TEXT]).map((type) => ({
		type,
		handle: type.split('\\').pop()!.toLowerCase(),
		label: type.split('\\').pop(),
		fields: [{ name: 'text', control: { name: 'text' } }],
	}));

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
						blockTypes: types,
						...(options.common ? { commonTypes: options.common } : {}),
						columns: 12,
						min: 1,
					},
				},
			},
			data: {
				value: {
					zxx: rows.map((box, index) => ({
						uid: `row-${index}`,
						type: TEXT,
						layout: { colspan: box.colspan, rowspan: box.rowspan ?? 1, indent: box.indent ?? 0 },
						fields: { text: { value: { zxx: `Row ${index}` } } },
					})),
				},
			},
			locales: [{ id: 'en', title: 'English' }],
			defaultLocale: 'en',
			globalLocales: true,
		}),
	});
}

/** The real blocks view with the rows' boxes stubbed, every behavior installed. */
async function editor(
	boxes: Box[],
	rows: number,
	options: { types?: string[]; common?: string[] } = {},
): Promise<{ form: HTMLFormElement; grid: HTMLElement; rows: () => HTMLElement[] }> {
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
	document.body.innerHTML = `<form id="node-editor-form" data-content-locale-scope data-content-locale="en">${view(boxes, options)}</form>`;

	const form = document.querySelector('form')!;
	const grid = form.querySelector<HTMLElement>('.cms-blocks-editor.is-grid > .grid')!;
	const list = () => [...grid.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]')];

	list().forEach((row, index) => place(row, boxes[index]));
	stubTracks(grid, 12, { rows });

	const stops = [installMenus(), installRepeater(), installBlocks(), installCatalog(), install()];

	uninstall = () => stops.reverse().forEach((stop) => stop());
	await paint();

	return { form, grid, rows: list };
}

function layout(row: HTMLElement): Record<string, string> {
	return Object.fromEntries(
		[...row.querySelectorAll<HTMLInputElement>('input[data-layout]')].map((input) => [
			input.dataset.layout,
			input.value,
		]),
	);
}

/** Clicks the choice for a type; a backslash in the name defeats an attribute selector. */
function pick(root: ParentNode, attribute: string, type: string): void {
	[...root.querySelectorAll<HTMLElement>(`[${attribute}]`)]
		.find((element) => element.getAttribute(attribute) === type)!
		.click();
}

function open(ghost: HTMLElement): HTMLElement {
	vi.spyOn(ghost, 'getBoundingClientRect').mockReturnValue(new DOMRect(150, 120, 80, 30));
	ghost.click();

	return document.getElementById(ghost.getAttribute('popovertarget')!)!;
}

describe('ghost insert', () => {
	it('stamps the one type before the row after the gap, sized to the gap', async () => {
		const { form, rows } = await editor(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 12 },
			],
			2,
		);
		const change = vi.fn();

		form.addEventListener('change', change);
		ghosts(rows()[0].parentElement!)[0].click();

		const [, added, wrapped] = rows();

		expect(rows()).toHaveLength(3);
		expect(wrapped.querySelector<HTMLInputElement>('[data-repeater-uid]')!.value).toBe('row-1');
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0' });
		expect(added.style.getPropertyValue('--colspan')).toBe('4');
		expect(added.contains(document.activeElement)).toBe(true);
		expect(change).toHaveBeenCalledOnce();
	});

	it('appends into the trailing gap of the last row', async () => {
		const { grid, rows } = await editor([{ row: 0, col: 0, colspan: 6 }], 1);

		ghosts(grid)[0].click();

		expect(rows().map(layout)).toEqual([
			{ colspan: '6', rowspan: '1', indent: '0' },
			{ colspan: '6', rowspan: '1', indent: '0' },
		]);
	});

	it('takes the indent away from the row whose gap it fills', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 0, col: 3, colspan: 6, indent: 3 },
				{ row: 1, col: 0, colspan: 12 },
			],
			2,
		);
		const [indented] = rows();

		ghosts(grid)[0].click();

		expect(rows()[1]).toBe(indented);
		expect(layout(rows()[0])).toEqual({ colspan: '3', rowspan: '1', indent: '0' });
		expect(layout(indented)).toEqual({ colspan: '6', rowspan: '1', indent: '0' });
		expect(indented.dataset.indent).toBe('0');
		expect(indented.style.getPropertyValue('--indent')).toBe('0');
	});

	it('inserts the type picked from the menu it opens at the gap', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 12 },
			],
			2,
			{ types: [TEXT, QUOTE] },
		);
		const ghost = ghosts(grid)[0];
		const menu = open(ghost);

		await paint();

		expect(menu.matches(':popover-open')).toBe(true);
		expect(ghost.getAttribute('aria-expanded')).toBe('true');
		// Inside the ghost's box, top edge at its middle.
		expect(menu.style.top).toBe('135px');
		expect(menu.style.left).toBe('190px');

		pick(menu, 'data-repeater-add', QUOTE);

		const [, added] = rows();

		expect(rows()).toHaveLength(3);
		expect(added.querySelector<HTMLInputElement>('input[name$="[type]"]')!.value).toBe(QUOTE);
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0' });
		expect(menu.matches(':popover-open')).toBe(false);
	});

	it('inserts a catalog choice at the gap without offering before or after', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 12 },
			],
			2,
			{ types: [TEXT, QUOTE], common: [TEXT] },
		);
		const menu = open(ghosts(grid)[0]);

		await paint();
		menu.querySelector<HTMLButtonElement>('[data-block-catalog-open]')!.click();

		const dialog = document.querySelector<HTMLDialogElement>('dialog[open]')!;

		expect(
			[...dialog.querySelectorAll<HTMLElement>('.cms-block-actions')].every(
				(group) => group.hidden,
			),
		).toBe(true);

		pick(dialog, 'data-block-choice', QUOTE);

		const [, added] = rows();

		expect(rows()).toHaveLength(3);
		expect(added.querySelector<HTMLInputElement>('input[name$="[type]"]')!.value).toBe(QUOTE);
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0' });
	});

	it('forgets the gap once its menu closed, so the footer appends as before', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 0, col: 0, colspan: 8 },
				{ row: 1, col: 0, colspan: 12 },
			],
			2,
			{ types: [TEXT, QUOTE] },
		);
		const menu = open(ghosts(grid)[0]);

		await paint();
		menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
		await paint();

		expect(menu.matches(':popover-open')).toBe(false);

		const trigger = grid.parentElement!.querySelector<HTMLButtonElement>(
			'[data-repeater-footer] button[popovertarget]',
		)!;

		vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(150, 400, 80, 30));
		openMenu(trigger, 'first');
		await paint();
		pick(menu, 'data-repeater-add', QUOTE);

		expect(rows()).toHaveLength(3);
		expect(layout(rows()[2])).toEqual({ colspan: '12', rowspan: '1', indent: '0' });
	});
});
