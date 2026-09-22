// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/blocks.php renders. jsdom lays nothing out, so the
// grid's resolved tracks and the rows' boxes are stubbed.

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fills, install, tracks, type Occupant } from '../../src/behaviors/ghosts';

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

function canvas(
	boxes: Box[],
	rows: number,
	options: { columns?: number; min?: number; list?: boolean; readonly?: boolean } = {},
): { grid: HTMLElement; rows: HTMLElement[]; adder: HTMLElement; size: { rows: number } } {
	const columns = options.columns ?? 12;
	// The implicit rows a real grid would have: a test shrinks it as rows move up.
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
