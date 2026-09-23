// The placement model on plain boxes, and its behaviors on the real
// blocks view. jsdom lays nothing out: the drag reads stubbed tracks.

import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { install as installBlocks } from '../../src/behaviors/blocks';
import {
	compact,
	flow,
	gaps,
	install,
	limits,
	move,
	resize,
	settle,
	sorted,
	tracks,
	type Box,
	type Boxes,
} from '../../src/behaviors/placement';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { install as installMenus } from '../../src/lib/action-menu';
import { installBridge } from '../../src/lib/bridge-standalone';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

let uninstall: (() => void) | undefined;

afterEach(() => {
	uninstall?.();
	uninstall = undefined;
	document.body.replaceChildren();
	delete window.Cosray;
	vi.unstubAllGlobals();
});

const box = (col: number, row: number, colspan: number, rowspan = 1): Box => ({
	col,
	row,
	colspan,
	rowspan,
});

function grid(entries: Record<string, Box>): Boxes<string> {
	return new Map(Object.entries(entries));
}

function plain(boxes: Boxes<string>): Record<string, Box> {
	return Object.fromEntries(boxes);
}

// A B C in the first row, D E in the second.
const two = (): Boxes<string> =>
	grid({ a: box(1, 1, 5), b: box(6, 1, 4), c: box(10, 1, 3), d: box(1, 2, 6), e: box(7, 2, 6) });

describe('placement flow', () => {
	it('places spans and indents as the sparse row flow does', () => {
		expect(
			flow(
				[
					{ colspan: 8, rowspan: 1, indent: 0 },
					{ colspan: 12, rowspan: 1, indent: 0 },
					{ colspan: 6, rowspan: 1, indent: 3 },
				],
				12,
			),
		).toEqual([box(1, 1, 8), box(1, 2, 12), box(4, 3, 6)]);
	});

	it('never goes back to a gap the cursor passed', () => {
		// The indented block would fit beside the first one, but the flow put
		// it a row further down, next to its indent.
		expect(
			flow(
				[
					{ colspan: 6, rowspan: 1, indent: 0 },
					{ colspan: 7, rowspan: 1, indent: 5 },
				],
				12,
			),
		).toEqual([box(1, 1, 6), box(6, 2, 7)]);
	});

	it('fits blocks beside a tall one', () => {
		expect(
			flow(
				[
					{ colspan: 4, rowspan: 2, indent: 0 },
					{ colspan: 8, rowspan: 1, indent: 0 },
					{ colspan: 8, rowspan: 1, indent: 0 },
				],
				12,
			),
		).toEqual([box(1, 1, 4, 2), box(5, 1, 8), box(5, 2, 8)]);
	});
});

describe('placement model', () => {
	it('pushes what a block lands on below it, and on down', () => {
		const boxes = grid({
			a: box(1, 1, 6),
			b: box(1, 2, 6),
			c: box(1, 3, 12),
			moved: box(1, 1, 6, 2),
		});

		expect(plain(settle(boxes, 'moved'))).toEqual({
			a: box(1, 3, 6),
			b: box(1, 4, 6),
			c: box(1, 5, 12),
			moved: box(1, 1, 6, 2),
		});
	});

	it('takes out rows no block covers, not those a tall block reaches into', () => {
		expect(plain(compact(grid({ a: box(1, 2, 6, 2), b: box(1, 5, 6) })))).toEqual({
			a: box(1, 1, 6, 2),
			b: box(1, 3, 6),
		});
	});

	it('reads row by row, left to right', () => {
		expect(
			sorted(grid({ e: box(7, 2, 6), a: box(1, 1, 5), d: box(1, 2, 6), c: box(10, 1, 3) })),
		).toEqual(['a', 'c', 'd', 'e']);
	});

	it('drops a block onto a row, pushing only what it overlaps', () => {
		expect(plain(move(two(), 'c', { col: 10, row: 2 }, false, 12))).toEqual({
			a: box(1, 1, 5),
			b: box(6, 1, 4),
			c: box(10, 2, 3),
			d: box(1, 2, 6),
			e: box(7, 3, 6),
		});
	});

	it('opens a new row on a line, the rows below moving down as a whole', () => {
		expect(plain(move(two(), 'c', { col: 10, row: 2 }, true, 12))).toEqual({
			a: box(1, 1, 5),
			b: box(6, 1, 4),
			c: box(10, 2, 3),
			d: box(1, 3, 6),
			e: box(7, 3, 6),
		});
	});

	it('drops into a full row by pushing the block in the way down', () => {
		const boxes = grid({ a: box(1, 1, 6), b: box(7, 1, 6), moved: box(1, 2, 4) });

		expect(plain(move(boxes, 'moved', { col: 5, row: 1 }, false, 12))).toEqual({
			a: box(1, 2, 6),
			b: box(7, 2, 6),
			moved: box(5, 1, 4),
		});
	});

	it('keeps a dropped block inside the columns and closes the row it left', () => {
		const boxes = grid({ a: box(1, 1, 12), moved: box(1, 2, 4) });

		expect(plain(move(boxes, 'moved', { col: 11, row: 1 }, true, 12))).toEqual({
			a: box(1, 2, 12),
			moved: box(9, 1, 4),
		});
	});

	it('grows sideways only into free cells, up to the grid edge', () => {
		expect(resize(two(), 'c', 'end', 2, 12, 1).get('c')).toEqual(box(10, 1, 3));
		expect(resize(two(), 'b', 'end', 2, 12, 1).get('b')).toEqual(box(6, 1, 4));

		const open = grid({ a: box(1, 1, 4), b: box(9, 1, 4) });

		expect(resize(open, 'a', 'end', 9, 12, 1).get('a')).toEqual(box(1, 1, 8));
		expect(resize(open, 'b', 'start', -9, 12, 1).get('b')).toEqual(box(5, 1, 8));
		expect(resize(open, 'b', 'start', 9, 12, 2).get('b')).toEqual(box(11, 1, 2));
		expect(limits(open, 'a', 12, 1).colspan).toEqual({ low: 1, high: 8 });
	});

	it('pushes the blocks below down as a block grows downwards', () => {
		expect(plain(resize(two(), 'a', 'bottom', 1, 12, 1))).toEqual({
			a: box(1, 1, 5, 2),
			b: box(6, 1, 4),
			c: box(10, 1, 3),
			d: box(1, 3, 6),
			e: box(7, 2, 6),
		});
	});

	it('offers every free run, merged down while the rows stay free the same way', () => {
		expect(gaps([box(1, 1, 8), box(1, 2, 8), box(1, 3, 6)], 12, 1)).toEqual([
			box(9, 1, 4, 2),
			box(7, 3, 6),
		]);
		expect(gaps([box(1, 1, 11)], 12, 2)).toEqual([]);
	});

	it('reads track edges from a resolved template with the gap between', () => {
		expect(tracks('100px 100px 100px', 10)).toEqual({
			starts: [0, 110, 220],
			ends: [100, 210, 320],
		});
		expect(tracks('none', 0)).toEqual({ starts: [], ends: [] });
	});
});

const TEXT = 'Cosray\\Block\\Text';
const TRACK = 100;

type Layout = { colspan: number; rowspan?: number; indent?: number; col?: number; row?: number };

function view(rows: Layout[]): string {
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
						blockTypes: [
							{
								type: TEXT,
								handle: 'text',
								label: 'Text',
								fields: [{ name: 'text', control: { name: 'text' } }],
							},
						],
						columns: 12,
						min: 1,
					},
				},
			},
			data: {
				value: {
					zxx: rows.map((layout, index) => ({
						uid: `row-${index}`,
						type: TEXT,
						layout: { rowspan: 1, indent: 0, ...layout },
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

function editor(rows: Layout[]): {
	form: HTMLFormElement;
	grid: HTMLElement;
	rows: () => HTMLElement[];
} {
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
	document.body.innerHTML = `<form id="node-editor-form" data-content-locale-scope data-content-locale="en">${view(rows)}</form>`;

	const form = document.querySelector('form')!;
	const grid = form.querySelector<HTMLElement>('.cms-blocks-editor.is-grid > .grid')!;
	const stops = [installMenus(), installRepeater(), installBlocks(), install()];

	uninstall = () => stops.reverse().forEach((stop) => stop());

	return {
		form,
		grid,
		rows: () => [...grid.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]')],
	};
}

function spot(row: HTMLElement): string {
	const value = (key: string) =>
		row.querySelector<HTMLInputElement>(`:scope > input[data-layout="${key}"]`)!.value;

	return `${value('col')}/${value('row')} ${value('colspan')}×${value('rowspan')}`;
}

function uid(row: HTMLElement): string {
	return row.querySelector<HTMLInputElement>('[data-repeater-uid]')!.value;
}

function menuItem(row: HTMLElement, selector: string): HTMLElement {
	return row.querySelector<HTMLElement>(`:scope > .chrome ${selector}`)!;
}

describe('placement on the canvas', () => {
	it('places a grid saved before positions where the flow put it', () => {
		const { rows } = editor([{ colspan: 6 }, { colspan: 7, indent: 5 }]);

		expect(rows().map(spot)).toEqual(['1/1 6×1', '6/2 7×1']);
		expect(rows()[1].querySelector<HTMLInputElement>('input[data-layout="indent"]')!.value).toBe(
			'0',
		);
		expect(rows().every((row) => row.hasAttribute('data-placed'))).toBe(true);
	});

	it('keeps stored positions and submits the rows in reading order', () => {
		const { form, rows } = editor([
			{ colspan: 6, col: 7, row: 1 },
			{ colspan: 6, col: 1, row: 1 },
		]);

		expect(rows().map(spot)).toEqual(['7/1 6×1', '1/1 6×1']);

		// Any structural change puts the DOM, and so the submitted order, in line.
		form
			.querySelector<HTMLElement>('.cms-blocks-editor')!
			.dispatchEvent(new Event('change', { bubbles: true }));

		expect(rows().map(uid)).toEqual(['row-1', 'row-0']);
		expect(rows()[0].querySelector<HTMLInputElement>('[data-repeater-uid]')!.name).toBe(
			'content[body][value][zxx][0][uid]',
		);
	});

	it('stamps a block after another in new rows below it, and appends below everything', () => {
		const { rows } = editor([
			{ colspan: 6, col: 1, row: 1 },
			{ colspan: 6, col: 7, row: 1 },
			{ colspan: 12, col: 1, row: 2 },
		]);

		menuItem(rows()[1], '[data-repeater-duplicate]').click();

		expect(rows().map(spot)).toEqual(['1/1 6×1', '7/1 6×1', '7/2 6×1', '1/3 12×1']);
		expect(uid(rows()[3])).toBe('row-2');
	});

	it('moves a block one row up or down, pushing what it meets', () => {
		const { rows } = editor([
			{ colspan: 12, col: 1, row: 1 },
			{ colspan: 6, col: 1, row: 2 },
		]);
		const lower = rows()[1];

		menuItem(lower, '[data-repeater-move="up"][data-places~="block"]').click();

		expect(rows()[0]).toBe(lower);
		expect(rows().map(spot)).toEqual(['1/1 6×1', '1/2 12×1']);
	});

	it('resizes a placed block into free cells only', () => {
		const { rows } = editor([
			{ colspan: 4, col: 1, row: 1 },
			{ colspan: 4, col: 9, row: 1 },
		]);
		const grip = menuItem(rows()[0], '[data-repeater-grip]');

		for (let step = 0; step < 6; step++) {
			grip.dispatchEvent(
				new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, bubbles: true }),
			);
		}

		expect(rows().map(spot)).toEqual(['1/1 8×1', '9/1 4×1']);
	});

	describe('dragging', () => {
		function stubTracks(grid: HTMLElement, rows: number): void {
			const original = getComputedStyle;

			grid.getBoundingClientRect = () => new DOMRect(0, 0, 12 * TRACK, rows * TRACK);
			vi.stubGlobal('getComputedStyle', (element: Element, pseudo?: string | null) =>
				element === grid
					? ({
							gridTemplateColumns: `${TRACK}px `.repeat(12).trim(),
							gridTemplateRows: `${TRACK}px `.repeat(rows).trim(),
							columnGap: '0px',
							rowGap: '0px',
							paddingLeft: '0px',
							paddingTop: '0px',
						} as CSSStyleDeclaration)
					: original(element, pseudo),
			);
		}

		function pointer(target: EventTarget, type: string, x: number, y: number): void {
			target.dispatchEvent(
				new MouseEvent(type, { bubbles: true, clientX: x, clientY: y, button: 0 }),
			);
		}

		// A B C, then D E: C's grip grabbed in its top left cell.
		function scene(): ReturnType<typeof editor> & { grip: HTMLElement } {
			const setup = editor([
				{ colspan: 5, col: 1, row: 1 },
				{ colspan: 4, col: 6, row: 1 },
				{ colspan: 3, col: 10, row: 1 },
				{ colspan: 6, col: 1, row: 2 },
				{ colspan: 6, col: 7, row: 2 },
			]);

			stubTracks(setup.grid, 2);

			return { ...setup, grip: menuItem(setup.rows()[2], '[data-repeater-grip]') };
		}

		it('opens a new row when dropped on the line between two rows', () => {
			const { rows, grip, form } = scene();
			const change = vi.fn();

			form.addEventListener('change', change);
			pointer(grip, 'pointerdown', 950, 50);
			pointer(document, 'pointermove', 950, 80);
			pointer(document, 'pointermove', 950, 102);
			pointer(document, 'pointerup', 950, 102);

			expect(rows().map(spot)).toEqual(['1/1 5×1', '6/1 4×1', '10/2 3×1', '1/3 6×1', '7/3 6×1']);
			expect(change).toHaveBeenCalledOnce();
		});

		it('drops on a cell, pushing only the block in the way', () => {
			const { rows, grid, grip } = scene();

			pointer(grip, 'pointerdown', 950, 50);
			pointer(document, 'pointermove', 950, 150);

			// Lifted out of the grid, with a slot where it would land.
			const slot = grid.querySelector<HTMLElement>(':scope > .landing')!;

			expect(rows()[2].classList.contains('is-lifted')).toBe(true);
			expect([slot.style.gridColumn, slot.style.gridRow]).toEqual(['10 / span 3', '2 / span 1']);

			pointer(document, 'pointerup', 950, 150);

			expect(grid.querySelector('.landing')).toBeNull();
			expect(grid.querySelector('.is-lifted')).toBeNull();
			expect(rows().map(spot)).toEqual(['1/1 5×1', '6/1 4×1', '1/2 6×1', '10/2 3×1', '7/3 6×1']);
		});

		it('puts everything back on Escape', () => {
			const { rows, grip } = scene();

			pointer(grip, 'pointerdown', 950, 50);
			pointer(document, 'pointermove', 950, 150);
			document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
			pointer(document, 'pointerup', 950, 150);

			expect(rows().map(spot)).toEqual(['1/1 5×1', '6/1 4×1', '10/1 3×1', '1/2 6×1', '7/2 6×1']);
		});
	});
});
