// Ghost blocks: the empty cells of a multi-column canvas, shown as dashed
// buttons that insert a block filling them exactly. Nothing here
// reimplements the browser's auto-placement. After layout the grid's
// resolved tracks and every row's box are read and mapped to cells; the
// free runs become buttons placed by explicit grid lines, absolutely
// positioned so they take part in neither placement nor track sizing —
// a row's area may well cover the cells its indent leaves free, and a
// button sitting in them as a grid item would push the row away.
//
// A ghost knows the row it inserts before — the first row after the gap
// in reading order, which is DOM order under the sparse row flow the
// canvas uses — and the layout that fills the gap. Cells a row leaves
// free through its indent are that row's own gap: filling it takes the
// indent away, and the row stays where it is. A gap the browser would
// not fill first, because the same block fits an earlier free spot on
// the way from the flow cursor, gets no ghost; the block would land
// there instead.
//
// A field with one block type stamps it on the click. Otherwise the
// click opens the field's own picker at the ghost, and the ghost stays
// armed until that menu closes: a choice or the catalog picked from it
// inserts at the ghost's spot instead of appending.

import { icon } from '$lib/icons';
import { open as openCatalog } from './block-catalog';
import { gridOf, read, write } from './blocks';
import { adder, arm, type Adder } from './pick';
import { insert, insertion, type Insertion } from './repeater';

export type Edges = { starts: number[]; ends: number[] };
export type Cells = { row: number; col: number; rowspan: number; colspan: number };
export type Occupant = { row: HTMLElement; box: Cells; area: Cells };
export type Fill = Cells & { kind: 'flow' | 'indent'; target: HTMLElement | null };

/** Track edges from a resolved `grid-template-*` value, gaps added between. */
export function tracks(template: string, gap: number): Edges {
	const starts: number[] = [];
	const ends: number[] = [];
	let at = 0;

	for (const token of template.split(/\s+/)) {
		const size = parseFloat(token);

		if (Number.isNaN(size)) {
			continue;
		}

		starts.push(at);
		ends.push(at + size);
		at += size + gap;
	}

	return { starts, ends };
}

function nearest(edges: number[], offset: number): number {
	let best = 0;

	for (let index = 1; index < edges.length; index++) {
		if (Math.abs(edges[index] - offset) < Math.abs(edges[best] - offset)) {
			best = index;
		}
	}

	return best;
}

/** The cells a row's box covers, and its area once the indent is added back. */
function occupant(
	row: HTMLElement,
	origin: { left: number; top: number },
	cols: Edges,
	rows: Edges,
): Occupant {
	const rect = row.getBoundingClientRect();
	const col = nearest(cols.starts, rect.left - origin.left);
	const top = nearest(rows.starts, rect.top - origin.top);
	const box = {
		row: top,
		col,
		rowspan: nearest(rows.ends, rect.bottom - origin.top) - top + 1,
		colspan: nearest(cols.ends, rect.right - origin.left) - col + 1,
	};
	const indent = Math.min(col, Number(row.dataset.indent) || 0);

	return { row, box, area: { ...box, col: col - indent, colspan: box.colspan + indent } };
}

function matrix<T>(rows: number, columns: number, value: T): T[][] {
	return Array.from({ length: rows }, () => Array<T>(columns).fill(value));
}

function mark<T>(cells: T[][], area: Cells, value: T): void {
	for (let row = area.row; row < area.row + area.rowspan; row++) {
		for (let col = area.col; col < area.col + area.colspan; col++) {
			if (cells[row]) {
				cells[row][col] = value;
			}
		}
	}
}

function vacant(boxes: boolean[][], at: Cells): boolean {
	for (let row = at.row; row < at.row + at.rowspan; row++) {
		for (let col = at.col; col < at.col + at.colspan; col++) {
			if (boxes[row]?.[col]) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Where the sparse row flow puts a block of this size when its cursor
 * stands at `from`: the first vacant spot in reading order. Cells below
 * the last measured row are implicit and free.
 */
function firstFit(
	boxes: boolean[][],
	from: { row: number; col: number },
	rowspan: number,
	colspan: number,
	columns: number,
): { row: number; col: number } {
	for (let row = from.row, col = from.col; ; row++, col = 0) {
		for (; col + colspan <= columns; col++) {
			if (vacant(boxes, { row, col, rowspan, colspan })) {
				return { row, col };
			}
		}
	}
}

/** The fills of every free run, merged down rows where they fill the same way. */
export function fills(occupants: Occupant[], columns: number, rows: number, min: number): Fill[] {
	const boxes = matrix(rows, columns, false);
	const indents = matrix<Occupant | null>(rows, columns, null);

	for (const occupant of occupants) {
		mark(indents, occupant.area, occupant);
		mark(boxes, occupant.box, true);
	}

	const found: Fill[] = [];

	for (let row = 0; row < rows; row++) {
		for (let col = 0; col < columns;) {
			if (boxes[row][col]) {
				col++;
				continue;
			}

			const owner = indents[row][col];
			const start = col;

			while (col < columns && !boxes[row][col] && indents[row][col] === owner) {
				col++;
			}

			const colspan = col - start;

			if (colspan < min) {
				continue;
			}

			const target = owner
				? owner.row
				: (occupants.find(({ area }) => area.row > row || (area.row === row && area.col > start))
						?.row ?? null);
			const kind = owner ? 'indent' : 'flow';
			const above = found.find(
				(fill) =>
					fill.row + fill.rowspan === row &&
					fill.col === start &&
					fill.colspan === colspan &&
					fill.kind === kind &&
					fill.target === target,
			);

			if (above) {
				above.rowspan++;
			} else {
				found.push({ row, col: start, rowspan: 1, colspan, kind, target });
			}
		}
	}

	return found.filter((fill) => {
		const index = fill.target
			? occupants.findIndex((occupant) => occupant.row === fill.target)
			: occupants.length;
		const before = occupants[index - 1];
		const from = before
			? { row: before.box.row, col: before.area.col + before.area.colspan }
			: { row: 0, col: 0 };
		const spot = firstFit(boxes, from, fill.rowspan, fill.colspan, columns);

		return spot.row === fill.row && spot.col === fill.col;
	});
}

const FILLS = new WeakMap<HTMLElement, Fill>();

function ghosts(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-ghost]'));
}

function rowsOf(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]'));
}

function key(fills: Fill[], rows: HTMLElement[]): string {
	return fills
		.map(
			(fill) =>
				`${fill.row}/${fill.col}/${fill.rowspan}/${fill.colspan}/${fill.kind}/${fill.target ? rows.indexOf(fill.target) : ''}`,
		)
		.join(';');
}

function measure(
	grid: HTMLElement,
	container: HTMLElement,
): { fills: Fill[]; rows: HTMLElement[] } {
	const rows = rowsOf(grid);
	const style = getComputedStyle(grid);
	const cols = tracks(style.gridTemplateColumns, parseFloat(style.columnGap) || 0);
	const lines = tracks(style.gridTemplateRows, parseFloat(style.rowGap) || 0);
	const rect = grid.getBoundingClientRect();
	const origin = {
		left: rect.left + grid.clientLeft + (parseFloat(style.paddingLeft) || 0),
		top: rect.top + grid.clientTop + (parseFloat(style.paddingTop) || 0),
	};
	const occupants = rows.map((row) => occupant(row, origin, cols, lines));

	return {
		rows,
		fills: fills(
			occupants,
			cols.starts.length,
			lines.starts.length,
			Number(container.dataset.min) || 1,
		),
	};
}

function render(grid: HTMLElement, container: HTMLElement, adder: Adder, fills: Fill[]): void {
	const label = container.dataset.ghostLabel ?? '';
	const focused = document.activeElement;
	const focus =
		focused instanceof HTMLElement && FILLS.has(focused) && grid.contains(focused)
			? FILLS.get(focused)
			: null;

	for (const ghost of ghosts(grid)) {
		ghost.remove();
	}

	for (const fill of fills) {
		const ghost = document.createElement('button');
		const name = `${label}, ${fill.colspan} × ${fill.rowspan}`;

		ghost.type = 'button';
		ghost.className = 'ghost';
		ghost.dataset.ghost = '';
		ghost.dataset.colspan = String(fill.colspan);
		ghost.dataset.rowspan = String(fill.rowspan);
		ghost.style.gridRow = `${fill.row + 1} / span ${fill.rowspan}`;
		ghost.style.gridColumn = `${fill.col + 1} / span ${fill.colspan}`;
		ghost.setAttribute('aria-label', name);
		ghost.title = name;
		ghost.innerHTML = icon('plus');

		if ('picker' in adder) {
			ghost.setAttribute('popovertarget', adder.picker);
			ghost.dataset.menuInside = '';
		} else {
			ghost.dataset.ghostType = adder.type;
		}

		FILLS.set(ghost, fill);
		grid.append(ghost);

		if (focus && focus.row === fill.row && focus.col === fill.col) {
			ghost.focus();
		}
	}

	if (focus && document.activeElement !== focused && !grid.contains(document.activeElement)) {
		container.querySelector<HTMLElement>(':scope > [data-repeater-footer] > button')?.focus();
	}
}

/**
 * The insertion a ghost stands for: before its target row, with the
 * clone's layout inputs set to the gap before the row lands, and for an
 * indent gap the target's indent taken away in the same step.
 */
function context(ghost: HTMLElement): Insertion | null {
	const fill = FILLS.get(ghost);
	const base = insertion(ghost);

	if (!fill || !base) {
		return null;
	}

	const grid = gridOf(base.owner);

	return {
		...base,
		at: fill.target ? { row: fill.target, where: 'before' } : null,
		prepare(clone) {
			const layout: Array<[string, number]> = [
				['colspan', fill.colspan],
				['rowspan', fill.rowspan],
				['indent', 0],
			];

			for (const [dimension, value] of layout) {
				const input = clone.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);

				if (input) {
					input.value = String(value);
				}
			}

			if (fill.kind === 'indent' && fill.target) {
				write(fill.target, { ...read(fill.target), indent: 0 }, grid);
			}
		},
	};
}

// Runs in the capture phase after the menu library's own handler, which
// has already opened the picker at a clicked ghost.
function onClick(event: MouseEvent): void {
	const ghost =
		event.target instanceof Element ? event.target.closest<HTMLElement>('[data-ghost]') : null;
	const insertion = ghost && context(ghost);

	if (!ghost || !insertion) {
		return;
	}

	if (ghost.dataset.ghostType) {
		insert(insertion, ghost.dataset.ghostType);
		return;
	}

	const menu = document.getElementById(ghost.getAttribute('popovertarget') ?? '');

	if (menu) {
		arm(
			menu,
			(type) => insert(insertion, type),
			() => openCatalog(insertion, true),
		);
	}
}

function watch(
	grid: HTMLElement,
	container: HTMLElement,
	adder: Adder,
): { refresh(): void; dispose(): void } {
	let frame = 0;
	let last = '';

	function schedule(): void {
		if (!frame) {
			frame = requestAnimationFrame(refresh);
		}
	}

	function refresh(): void {
		frame = 0;

		// A dragged or resized row is not where it will end up; the change
		// after the gesture measures again.
		if (grid.querySelector(':scope > .sortable-ghost') || grid.closest('.is-resizing')) {
			return;
		}

		const { fills, rows } = measure(grid, container);
		const next = key(fills, rows);

		if (next !== last) {
			last = next;
			render(grid, container, adder, fills);
		}
	}

	const observer = new MutationObserver((records) => {
		const structural = records.some((record) =>
			record.type === 'childList'
				? record.target === grid &&
					[...record.addedNodes, ...record.removedNodes].some(
						(node) => !(node instanceof HTMLElement && node.hasAttribute('data-ghost')),
					)
				: record.target instanceof HTMLElement &&
					record.target.parentElement === grid &&
					!record.target.hasAttribute('data-ghost'),
		);

		if (structural) {
			schedule();
		}
	});

	observer.observe(grid, {
		childList: true,
		attributes: true,
		attributeFilter: ['style', 'data-indent'],
		subtree: true,
	});
	schedule();

	return {
		refresh: schedule,
		dispose() {
			cancelAnimationFrame(frame);
			observer.disconnect();
		},
	};
}

export function install(): () => void {
	const grids = new Map<HTMLElement, ReturnType<typeof watch>>();

	function scan(): void {
		for (const [grid, watcher] of grids) {
			if (!grid.isConnected) {
				watcher.dispose();
				grids.delete(grid);
			}
		}

		document.querySelectorAll<HTMLElement>('.cms-blocks-editor.is-grid > .grid').forEach((grid) => {
			const container = grid.parentElement;
			const how = container && adder(container);

			if (!grids.has(grid) && how && !grid.closest('[data-readonly="true"]')) {
				grids.set(grid, watch(grid, container, how));
			}
		});
	}

	function changed(event: Event): void {
		const grid =
			event.target instanceof Element
				? event.target.closest<HTMLElement>('.cms-blocks-editor > .grid')
				: null;

		if (grid) {
			grids.get(grid)?.refresh();
		}
	}

	document.addEventListener('htmx:after:swap', scan);
	document.addEventListener('change', changed);
	document.addEventListener('click', onClick, true);
	scan();

	return () => {
		document.removeEventListener('htmx:after:swap', scan);
		document.removeEventListener('change', changed);
		document.removeEventListener('click', onClick, true);

		for (const watcher of grids.values()) {
			watcher.dispose();
		}
	};
}
