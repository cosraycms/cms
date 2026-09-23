// The rendering on hand-built DOM mirroring what
// panel/views/field/blocks.php renders, and the insert paths on the real
// view. The gaps come from the stored positions, so nothing is measured.

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install as installCatalog } from '../../src/behaviors/block-catalog';
import { install as installBlocks } from '../../src/behaviors/blocks';
import { install } from '../../src/behaviors/ghosts';
import { install as installPick } from '../../src/behaviors/pick';
import { install as installPlacement } from '../../src/behaviors/placement';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installMenus, openMenu } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

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

type Box = { col: number; row: number; colspan: number; rowspan?: number };

function position(row: HTMLElement, box: Box): void {
	const values = { col: box.col, row: box.row, colspan: box.colspan, rowspan: box.rowspan ?? 1 };

	row.innerHTML = Object.entries(values)
		.map(([key, value]) => `<input type="hidden" data-layout="${key}" value="${value}">`)
		.join('');
	row.setAttribute('data-placed', '');
	row.style.setProperty('--col', String(box.col));
}

function canvas(
	boxes: Box[],
	options: { columns?: number; min?: number; list?: boolean; readonly?: boolean } = {},
): { grid: HTMLElement; rows: HTMLElement[]; adder: HTMLElement } {
	const columns = options.columns ?? 12;
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
		position(row, box);
		grid.append(row);

		return row;
	});

	document.body.append(field);

	return { grid, rows: elements, adder: field.querySelector<HTMLElement>('.adder')! };
}

function ghosts(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-ghost]'));
}

describe('ghost rendering', () => {
	it('places a button on every gap with the size in its name', async () => {
		const { grid } = canvas([
			{ row: 1, col: 1, colspan: 4, rowspan: 2 },
			{ row: 1, col: 5, colspan: 8 },
			{ row: 3, col: 1, colspan: 6 },
		]);
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

	it('offers the gap left of a block that follows, which the flow could not reach', async () => {
		const { grid } = canvas([
			{ row: 1, col: 1, colspan: 6 },
			{ row: 2, col: 6, colspan: 7 },
		]);
		uninstall = install();
		await paint();

		expect(ghosts(grid).map((ghost) => [ghost.style.gridRow, ghost.style.gridColumn])).toEqual([
			['1 / span 1', '7 / span 6'],
			['2 / span 1', '1 / span 5'],
		]);
	});

	it('keeps its buttons while the gaps stay and replaces them when a row moves', async () => {
		const { grid, rows } = canvas([
			{ row: 1, col: 1, colspan: 8 },
			{ row: 2, col: 1, colspan: 8 },
		]);
		uninstall = install();
		await paint();

		const before = ghosts(grid);

		// The free cells beside both rows are one gap two rows tall.
		expect(before.map((ghost) => ghost.style.gridRow)).toEqual(['1 / span 2']);

		grid.dispatchEvent(new Event('change', { bubbles: true }));
		await paint();

		expect(ghosts(grid)).toEqual(before);

		position(rows[1], { row: 1, col: 9, colspan: 4 });
		await paint();

		expect(ghosts(grid)).toEqual([]);
	});

	it('shows nothing on a list, a read-only field, a grid mid-drag or one not placed yet', async () => {
		const list = canvas([{ row: 1, col: 1, colspan: 6 }], { list: true });
		const locked = canvas([{ row: 1, col: 1, colspan: 6 }], { readonly: true });
		const dragged = canvas([{ row: 1, col: 1, colspan: 6 }]);
		const flowing = canvas([{ row: 1, col: 1, colspan: 6 }]);

		dragged.grid.parentElement!.classList.add('is-moving');
		flowing.rows[0].removeAttribute('data-placed');
		uninstall = install();
		await paint();

		expect(ghosts(list.grid)).toEqual([]);
		expect(ghosts(locked.grid)).toEqual([]);
		expect(ghosts(dragged.grid)).toEqual([]);
		expect(ghosts(flowing.grid)).toEqual([]);

		dragged.grid.parentElement!.classList.remove('is-moving');
		dragged.grid.dispatchEvent(new Event('change', { bubbles: true }));
		await paint();

		expect(ghosts(dragged.grid)).toHaveLength(1);
	});

	it('hands focus on when the focused gap is redrawn or vanishes', async () => {
		const { grid, rows, adder } = canvas([
			{ row: 1, col: 1, colspan: 8 },
			{ row: 2, col: 1, colspan: 8 },
		]);
		uninstall = install();
		await paint();
		ghosts(grid)[0].focus();

		position(rows[1], { row: 2, col: 1, colspan: 6 });
		await paint();

		expect(ghosts(grid).map((ghost) => ghost.dataset.colspan)).toEqual(['4', '6']);
		expect(document.activeElement).toBe(ghosts(grid)[0]);

		position(rows[1], { row: 1, col: 9, colspan: 4 });
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
						layout: { ...box, rowspan: box.rowspan ?? 1, indent: 0 },
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

/** The real blocks view, every behavior installed. */
async function editor(
	boxes: Box[],
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
	const stops = [
		installMenus(),
		installRepeater(),
		installBlocks(),
		installPlacement(),
		installCatalog(),
		install(),
		installPick(),
	];

	uninstall = () => stops.reverse().forEach((stop) => stop());
	await paint();

	return { form, grid, rows: list };
}

function layout(row: HTMLElement): Record<string, string> {
	return Object.fromEntries(
		[...row.querySelectorAll<HTMLInputElement>(':scope > input[data-layout]')].map((input) => [
			input.dataset.layout,
			input.value,
		]),
	);
}

function uid(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>('[data-repeater-uid]')!.value;
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
	it('stamps the one type into the gap, sized to it, in reading order', async () => {
		const { form, grid, rows } = await editor([
			{ row: 1, col: 1, colspan: 8 },
			{ row: 2, col: 1, colspan: 12 },
		]);
		const change = vi.fn();

		form.addEventListener('change', change);
		ghosts(grid)[0].click();

		const [, added, below] = rows();

		expect(rows()).toHaveLength(3);
		expect(uid(below)).toBe('row-1');
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0', col: '9', row: '1' });
		expect(added.style.getPropertyValue('--col')).toBe('9');
		expect(added.hasAttribute('data-placed')).toBe(true);
		expect(added.contains(document.activeElement)).toBe(true);
		expect(change).toHaveBeenCalledOnce();
	});

	it('fills the gap left of a block without moving it', async () => {
		const { grid, rows } = await editor([
			{ row: 1, col: 1, colspan: 6 },
			{ row: 2, col: 6, colspan: 7 },
		]);

		ghosts(grid)[1].click();

		expect(rows().map(layout)).toEqual([
			{ colspan: '6', rowspan: '1', indent: '0', col: '1', row: '1' },
			{ colspan: '5', rowspan: '1', indent: '0', col: '1', row: '2' },
			{ colspan: '7', rowspan: '1', indent: '0', col: '6', row: '2' },
		]);
	});

	it('inserts the type picked from the menu it opens at the gap', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 1, col: 1, colspan: 8 },
				{ row: 2, col: 1, colspan: 12 },
			],
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
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0', col: '9', row: '1' });
		expect(menu.matches(':popover-open')).toBe(false);
	});

	it('inserts a catalog choice at the gap without offering before or after', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 1, col: 1, colspan: 8 },
				{ row: 2, col: 1, colspan: 12 },
			],
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
		expect(layout(added)).toEqual({ colspan: '4', rowspan: '1', indent: '0', col: '9', row: '1' });
	});

	it('forgets the gap once its menu closed, so the footer appends below everything', async () => {
		const { grid, rows } = await editor(
			[
				{ row: 1, col: 1, colspan: 8 },
				{ row: 2, col: 1, colspan: 12 },
			],
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
		expect(layout(rows()[2])).toEqual({
			colspan: '12',
			rowspan: '1',
			indent: '0',
			col: '1',
			row: '3',
		});
	});
});
