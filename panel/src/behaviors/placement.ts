// Positions on a multi-column blocks canvas. A block on the grid stores
// the column and row it starts at next to its spans (data-layout="col"
// and "row", 1-based) and sits exactly there; the reading order follows
// from the positions, row by row and left to right, and the rows are
// kept in that order in the DOM, so the form submits them that way.
// Blocks never overlap: a block moved, or grown downwards, onto others
// pushes them down below itself, and whatever they land on in turn.
// Rows no block covers any more are taken out. A block grows sideways
// only into free cells, up to the grid's edge.
//
// A grid saved before positions existed flows: its blocks are placed
// where the browser's sparse row flow put them, indent included, and
// keep that position from then on.
//
// The grip drags a block to another spot: the cell under the pointer is
// where its grabbed cell lands, and the pointer right on a line between
// two rows opens a new row there. The grid's tracks are read once when
// the drag starts, so a row changing height under a moved block does
// not move the target. The canvas shows the result while dragging;
// Escape puts everything back.

import { changed, focusRow, renumber } from './repeater';

export type Box = { col: number; row: number; colspan: number; rowspan: number };
export type Boxes<T> = Map<T, Box>;
export type Edge = 'start' | 'end' | 'bottom';
export type Flowing = { colspan: number; rowspan: number; indent: number };
export type Edges = { starts: number[]; ends: number[] };

export const MAX_ROWSPAN = 6;

const GRID = '.cms-blocks-editor.is-grid > .grid';

function between(value: number, low: number, high: number): number {
	return Math.max(low, Math.min(high, Math.trunc(value) || 0));
}

export function overlaps(a: Box, b: Box): boolean {
	return (
		a.col < b.col + b.colspan &&
		b.col < a.col + a.colspan &&
		a.row < b.row + b.rowspan &&
		b.row < a.row + a.rowspan
	);
}

function copy<T>(boxes: Boxes<T>): Boxes<T> {
	return new Map([...boxes].map(([key, box]) => [key, { ...box }]));
}

function bottom(boxes: Iterable<Box>): number {
	let last = 0;

	for (const box of boxes) {
		last = Math.max(last, box.row + box.rowspan - 1);
	}

	return last;
}

/**
 * Where the sparse row flow puts each block: at the first spot from the
 * cursor where its indent and span fit, the box past the indent.
 */
export function flow(layouts: Flowing[], columns: number): Box[] {
	const taken = new Set<string>();
	const free = (row: number, col: number, width: number, height: number): boolean => {
		for (let r = row; r < row + height; r++) {
			for (let c = col; c < col + width; c++) {
				if (taken.has(`${r}:${c}`)) {
					return false;
				}
			}
		}

		return true;
	};
	let cursor = { row: 0, col: 0 };

	return layouts.map(({ colspan, rowspan, indent }) => {
		const width = Math.min(columns, indent + colspan);

		for (let row = cursor.row, col = cursor.col; ; row++, col = 0) {
			for (; col + width <= columns; col++) {
				if (!free(row, col, width, rowspan)) {
					continue;
				}

				for (let r = row; r < row + rowspan; r++) {
					for (let c = col; c < col + width; c++) {
						taken.add(`${r}:${c}`);
					}
				}

				cursor = { row, col: col + width };

				return { col: col + width - colspan + 1, row: row + 1, colspan, rowspan };
			}
		}
	});
}

/**
 * The fixed block stays; the others, top to bottom, each move down below
 * whatever they hit, so blocks pushed together keep their order.
 */
export function settle<T>(boxes: Boxes<T>, fixed: T): Boxes<T> {
	const result = copy(boxes);
	const settled = [result.get(fixed)!];

	for (const key of sorted(boxes)) {
		if (key === fixed) {
			continue;
		}

		const box = result.get(key)!;

		for (
			let hit = settled.find((other) => overlaps(box, other));
			hit;
			hit = settled.find((other) => overlaps(box, other))
		) {
			box.row = hit.row + hit.rowspan;
		}

		settled.push(box);
	}

	return result;
}

/** Rows no block covers are taken out; the blocks below move up. */
export function compact<T>(boxes: Boxes<T>): Boxes<T> {
	const result = copy(boxes);

	for (let line = bottom(result.values()); line >= 1; line--) {
		const used = [...result.values()].some(
			(box) => box.row <= line && line < box.row + box.rowspan,
		);

		if (!used) {
			for (const box of result.values()) {
				if (box.row > line) {
					box.row--;
				}
			}
		}
	}

	return result;
}

/** Reading order: row by row, left to right. */
export function sorted<T>(boxes: Boxes<T>): T[] {
	return [...boxes].sort(([, a], [, b]) => a.row - b.row || a.col - b.col).map(([key]) => key);
}

/**
 * The block moved to a spot, clamped into the columns; with `insert`, it
 * gets rows of its own there, the blocks from that row on moving down.
 */
export function move<T>(
	boxes: Boxes<T>,
	key: T,
	to: { col: number; row: number },
	insert: boolean,
	columns: number,
): Boxes<T> {
	const result = copy(boxes);
	const box = result.get(key)!;

	box.col = between(to.col, 1, columns - box.colspan + 1);
	box.row = Math.max(1, Math.trunc(to.row) || 1);

	if (insert) {
		for (const [other, placed] of result) {
			if (other !== key && placed.row >= box.row) {
				placed.row += box.rowspan;
			}
		}
	}

	return compact(settle(result, key));
}

/** The widest the block can get from where it starts: free cells up to the grid's edge. */
function reach<T>(boxes: Boxes<T>, key: T, columns: number): { left: number; right: number } {
	const box = boxes.get(key)!;
	let left = 1;
	let right = columns;

	for (const [other, placed] of boxes) {
		if (
			other === key ||
			placed.row >= box.row + box.rowspan ||
			box.row >= placed.row + placed.rowspan
		) {
			continue;
		}

		if (placed.col >= box.col + box.colspan) {
			right = Math.min(right, placed.col - 1);
		} else if (placed.col + placed.colspan <= box.col) {
			left = Math.max(left, placed.col + placed.colspan);
		}
	}

	return { left, right };
}

/** The range each span can take where the block sits. */
export function limits<T>(
	boxes: Boxes<T>,
	key: T,
	columns: number,
	min: number,
): { colspan: { low: number; high: number }; rowspan: { low: number; high: number } } {
	const box = boxes.get(key)!;
	const { right } = reach(boxes, key, columns);

	return {
		colspan: { low: min, high: Math.max(box.colspan, right - box.col + 1) },
		rowspan: { low: 1, high: MAX_ROWSPAN },
	};
}

/** One span set within its limits; a taller block pushes the ones below down. */
export function span<T>(
	boxes: Boxes<T>,
	key: T,
	dimension: 'colspan' | 'rowspan',
	value: number,
	columns: number,
	min: number,
): Boxes<T> {
	const result = copy(boxes);
	const box = result.get(key)!;
	const { low, high } = limits(boxes, key, columns, min)[dimension];

	box[dimension] = between(value, low, high);

	return dimension === 'rowspan' ? compact(settle(result, key)) : result;
}

/**
 * One edge moved by whole steps: the end edge grows into free cells up
 * to the grid's edge, the start edge moves the start column the same way
 * and keeps the end edge where it is, the bottom edge counts rows.
 */
export function resize<T>(
	boxes: Boxes<T>,
	key: T,
	edge: Edge,
	steps: number,
	columns: number,
	min: number,
): Boxes<T> {
	const box = boxes.get(key)!;

	if (edge === 'bottom') {
		return span(boxes, key, 'rowspan', box.rowspan + steps, columns, min);
	}

	if (edge === 'end') {
		return span(boxes, key, 'colspan', box.colspan + steps, columns, min);
	}

	const result = copy(boxes);
	const end = box.col + box.colspan;
	const col = between(box.col + steps, reach(boxes, key, columns).left, end - min);

	result.set(key, { ...box, col, colspan: end - col });

	return result;
}

/**
 * The free runs of every row, merged down while the rows below are free
 * the same way, up to the tallest block there is.
 */
export function gaps(boxes: Iterable<Box>, columns: number, min: number): Box[] {
	const list = [...boxes];
	const rows = bottom(list);
	const taken = Array.from({ length: rows + 1 }, () => Array<boolean>(columns + 1).fill(false));

	for (const box of list) {
		for (let row = box.row; row < box.row + box.rowspan; row++) {
			for (let col = box.col; col < box.col + box.colspan; col++) {
				taken[row][col] = true;
			}
		}
	}

	const found: Box[] = [];

	for (let row = 1; row <= rows; row++) {
		for (let col = 1; col <= columns;) {
			if (taken[row][col]) {
				col++;
				continue;
			}

			const start = col;

			while (col <= columns && !taken[row][col]) {
				col++;
			}

			const colspan = col - start;

			if (colspan < min) {
				continue;
			}

			const above = found.find(
				(gap) =>
					gap.row + gap.rowspan === row &&
					gap.col === start &&
					gap.colspan === colspan &&
					gap.rowspan < MAX_ROWSPAN,
			);

			if (above) {
				above.rowspan++;
			} else {
				found.push({ row, col: start, rowspan: 1, colspan });
			}
		}
	}

	return found;
}

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

// The DOM side.

/** The grid a row is placed on; null for a part, a list or a read-only canvas row alike. */
export function gridOf(row: HTMLElement): HTMLElement | null {
	const grid = row.parentElement;

	return grid?.matches(GRID) ? grid : null;
}

export function placed(row: HTMLElement): boolean {
	return row.hasAttribute('data-placed') && gridOf(row) !== null;
}

function rowsOf(grid: HTMLElement): HTMLElement[] {
	return [...grid.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]')];
}

function field(row: HTMLElement, key: string): HTMLInputElement | null {
	return row.querySelector<HTMLInputElement>(`:scope > input[data-layout="${key}"]`);
}

function number(row: HTMLElement, key: string): number {
	return Number(field(row, key)?.value) || 0;
}

export function boxOf(row: HTMLElement): Box {
	return {
		col: number(row, 'col'),
		row: number(row, 'row'),
		colspan: number(row, 'colspan') || 1,
		rowspan: number(row, 'rowspan') || 1,
	};
}

export function columnsOf(grid: HTMLElement): { columns: number; min: number } {
	const container = grid.parentElement!;
	const columns = Math.max(1, Number(container.dataset.columns) || 1);

	return { columns, min: between(Number(container.dataset.min) || 1, 1, columns) };
}

export function snapshot(grid: HTMLElement): Boxes<HTMLElement> {
	return new Map(rowsOf(grid).map((row) => [row, boxOf(row)]));
}

function set(row: HTMLElement, key: string, value: number): void {
	const input = field(row, key);

	if (input) {
		input.value = String(value);
	}
}

/**
 * The positions written: the hidden inputs, the row's custom properties
 * and the dialog's numbers. A placed row has no indent.
 */
export function place(grid: HTMLElement, boxes: Boxes<HTMLElement>): void {
	const { columns, min } = columnsOf(grid);

	for (const [row, box] of boxes) {
		set(row, 'col', box.col);
		set(row, 'row', box.row);
		row.style.setProperty('--col', String(box.col));
		row.style.setProperty('--row', String(box.row));
		row.toggleAttribute('data-placed', true);

		if (number(row, 'indent') !== 0) {
			set(row, 'indent', 0);
			row.style.setProperty('--indent', '0');
			row.style.setProperty('--reserved', String(box.colspan));
			row.dataset.indent = '0';
		}

		const dialog = row.querySelector<HTMLElement>(':scope > dialog');
		const colspan = limits(boxes, row, columns, min).colspan;

		for (const [key, value, low, high] of [
			['col', box.col, 1, columns - box.colspan + 1],
			['row', box.row, 1, 999],
			['colspan', box.colspan, colspan.low, colspan.high],
		] as const) {
			const control = dialog?.querySelector<HTMLInputElement>(`input[data-layout-input="${key}"]`);

			if (control && control !== document.activeElement) {
				control.min = String(low);
				control.max = String(high);
				control.value = String(value);
			}
		}
	}
}

/** A position handed over, as from a block to the split that takes its place. */
export function adopt(from: HTMLElement, to: HTMLElement): void {
	const grid = gridOf(to);

	if (grid && number(from, 'col') > 0) {
		place(
			grid,
			new Map([[to, { ...boxOf(to), col: number(from, 'col'), row: number(from, 'row') }]]),
		);
	}
}

/** A block leaving the grid for a split flows there again. */
export function release(row: HTMLElement): void {
	set(row, 'col', 0);
	set(row, 'row', 0);
	row.style.removeProperty('--col');
	row.style.removeProperty('--row');
	row.removeAttribute('data-placed');
}

/** The rows in reading order in the DOM; true when any moved. */
function reorder(grid: HTMLElement): boolean {
	const rows = rowsOf(grid);
	const order = sorted(snapshot(grid));

	if (order.every((row, index) => rows[index] === row)) {
		return false;
	}

	// An atomic move keeps focus and element state; append reconnects.
	const move = (row: HTMLElement): void => {
		if ('moveBefore' in grid) {
			(grid as HTMLElement & { moveBefore(node: Node, child: Node | null): void }).moveBefore(
				row,
				null,
			);
		} else {
			grid.append(row);
		}
	};

	order.forEach(move);

	return true;
}

/** Empty rows out, the DOM in reading order, and the change announced. */
export function commit(grid: HTMLElement): void {
	place(grid, compact(snapshot(grid)));
	reorder(grid);
	changed(grid.parentElement!);
}

/** A grid whose blocks do not all have a position yet gets them. */
function derive(grid: HTMLElement): void {
	const rows = rowsOf(grid);
	const { columns } = columnsOf(grid);
	const missing = rows.filter((row) => number(row, 'col') === 0);

	if (missing.length === 0) {
		if (rows.some((row) => !row.hasAttribute('data-placed'))) {
			place(grid, snapshot(grid));
		}

		return;
	}

	if (missing.length === rows.length) {
		const boxes = flow(
			rows.map((row) => ({
				colspan: number(row, 'colspan') || 1,
				rowspan: number(row, 'rowspan') || 1,
				indent: number(row, 'indent'),
			})),
			columns,
		);

		place(grid, new Map(rows.map((row, index) => [row, boxes[index]])));

		return;
	}

	let last = bottom(rows.filter((row) => !missing.includes(row)).map(boxOf));

	for (const row of missing) {
		const box = { ...boxOf(row), col: 1, row: last + 1 };

		place(grid, new Map([[row, box]]));
		last += box.rowspan;
	}
}

function scan(): void {
	document.querySelectorAll<HTMLElement>(GRID).forEach(derive);
}

type Anchor = { row: HTMLElement; where: 'before' | 'after' } | null;

/**
 * A stamped block's spot: before a block, in new rows where that block
 * starts; after one, in new rows below it; appended, below everything.
 * It keeps the anchor's column where its span fits. One stamped with a
 * position of its own (a ghost fills its gap) stays there.
 */
function onStamp(event: Event): void {
	const row = event.target;
	const grid = row instanceof HTMLElement ? gridOf(row) : null;

	if (!(row instanceof HTMLElement) || !grid) {
		return;
	}

	const at = (event as CustomEvent<{ at?: Anchor }>).detail?.at ?? null;
	const own = boxOf(row);

	if (!at && own.col > 0) {
		place(grid, new Map([[row, own]]));

		return;
	}

	const { columns } = columnsOf(grid);
	const boxes = snapshot(grid);

	boxes.delete(row);

	const anchor = at ? boxes.get(at.row) : undefined;
	const line = !anchor
		? bottom(boxes.values()) + 1
		: at?.where === 'before'
			? anchor.row
			: anchor.row + anchor.rowspan;
	const col = between(anchor?.col ?? 1, 1, columns - own.colspan + 1);

	for (const box of boxes.values()) {
		if (box.row >= line) {
			box.row += own.rowspan;
		}
	}

	boxes.set(row, { ...own, col, row: line });
	place(grid, boxes);
}

/** Structural changes of a grid: rows left empty go, the DOM follows the positions. */
function onChange(event: Event): void {
	const container = event.target;
	const grid =
		container instanceof HTMLElement && container.matches('.cms-blocks-editor.is-grid')
			? container.querySelector<HTMLElement>(':scope > .grid')
			: null;

	if (!grid || rowsOf(grid).some((row) => number(row, 'col') === 0)) {
		return;
	}

	place(grid, compact(snapshot(grid)));

	if (reorder(grid)) {
		renumber(container as HTMLElement);
	}
}

function locked(grid: HTMLElement): boolean {
	return grid.closest('[data-readonly="true"]') !== null;
}

/** Move up and down go one row: the block lands there and pushes what it meets. */
function onMove(event: MouseEvent): void {
	const mover =
		event.target instanceof Element ? event.target.closest('[data-repeater-move]') : null;
	const row = mover?.closest<HTMLElement>('[data-repeater-row]');
	const grid = row && placed(row) ? gridOf(row) : null;
	const direction = mover?.getAttribute('data-repeater-move');

	if (!grid || !row || locked(grid) || (direction !== 'up' && direction !== 'down')) {
		return;
	}

	event.preventDefault();

	const box = boxOf(row);
	const to = direction === 'up' ? box.row - 1 : box.row + 1;

	if (to < 1) {
		return;
	}

	place(grid, move(snapshot(grid), row, { col: box.col, row: to }, false, columnsOf(grid).columns));
	commit(grid);
}

/** The column and row numbers in a block's dialog move it like a drop. */
function onInput(event: Event): void {
	const control = event.target;
	const key =
		control instanceof HTMLInputElement ? control.getAttribute('data-layout-input') : null;
	const row =
		control instanceof HTMLElement ? control.closest<HTMLElement>('[data-repeater-row]') : null;
	const grid = row && placed(row) ? gridOf(row) : null;

	if (!(control instanceof HTMLInputElement) || (key !== 'col' && key !== 'row') || !row || !grid) {
		return;
	}

	const value = Number(control.value);

	if (control.value === '' || !(value >= 1)) {
		return;
	}

	const box = boxOf(row);

	place(grid, move(snapshot(grid), row, { ...box, [key]: value }, false, columnsOf(grid).columns));

	if (event.type === 'change') {
		commit(grid);
	}
}

type Drag = {
	pointer: number;
	grip: HTMLElement;
	row: HTMLElement;
	grid: HTMLElement;
	x: number;
	y: number;
	started: boolean;
	start: Boxes<HTMLElement>;
	cols: Edges;
	rows: Edges;
	origin: { left: number; top: number };
	grab: { col: number; row: number };
	target: string;
};

const THRESHOLD = 4;
const BAND = 10;
// Rows below the grid have no height yet; the pointer there counts in this.
const PROBE = 96;

let drag: Drag | null = null;

function cellOf(edges: Edges, offset: number, fallback: number): number {
	const { starts, ends } = edges;

	if (starts.length === 0) {
		return 1;
	}

	for (let index = 0; index < starts.length; index++) {
		if (
			offset <
			ends[index] + (starts[index + 1] !== undefined ? (starts[index + 1] - ends[index]) / 2 : 0)
		) {
			return index + 1;
		}
	}

	return starts.length + 1 + Math.floor((offset - ends[ends.length - 1]) / fallback);
}

/** The line between two rows the pointer sits on, as the row a new one would take. */
function lineOf(edges: Edges, offset: number): number | null {
	const { starts, ends } = edges;

	for (let index = 0; index <= starts.length; index++) {
		const at =
			index === 0
				? starts[0]
				: index === starts.length
					? ends[index - 1]
					: (ends[index - 1] + starts[index]) / 2;

		if (at !== undefined && Math.abs(offset - at) <= BAND) {
			return index + 1;
		}
	}

	return null;
}

function geometry(grid: HTMLElement): Pick<Drag, 'cols' | 'rows' | 'origin'> {
	const style = getComputedStyle(grid);
	const rect = grid.getBoundingClientRect();

	return {
		cols: tracks(style.gridTemplateColumns, parseFloat(style.columnGap) || 0),
		rows: tracks(style.gridTemplateRows, parseFloat(style.rowGap) || 0),
		origin: {
			left: rect.left + grid.clientLeft + (parseFloat(style.paddingLeft) || 0),
			top: rect.top + grid.clientTop + (parseFloat(style.paddingTop) || 0),
		},
	};
}

function onPointerDown(event: PointerEvent): void {
	const grip =
		event.target instanceof Element
			? event.target.closest<HTMLElement>('[data-repeater-grip]')
			: null;
	const row = grip?.closest<HTMLElement>('[data-repeater-row]');
	const grid = row && placed(row) ? gridOf(row) : null;

	if (drag || event.button !== 0 || !grip || !row || !grid || locked(grid)) {
		return;
	}

	drag = {
		pointer: event.pointerId,
		grip,
		row,
		grid,
		x: event.clientX,
		y: event.clientY,
		started: false,
		start: new Map(),
		...geometry(grid),
		grab: { col: 0, row: 0 },
		target: '',
	};
	grip.setPointerCapture?.(event.pointerId);
	event.preventDefault();
}

function begin(current: Drag): void {
	const box = boxOf(current.row);
	const x = current.x - current.origin.left;
	const y = current.y - current.origin.top;

	current.started = true;
	current.start = snapshot(current.grid);
	current.grab = {
		col: cellOf(current.cols, x, PROBE) - box.col,
		row: cellOf(current.rows, y, PROBE) - box.row,
	};
	current.row.classList.add('is-dragging');
	current.grid.parentElement!.classList.add('is-moving');
}

function onPointerMove(event: PointerEvent): void {
	if (drag?.pointer !== event.pointerId) {
		return;
	}

	if (!drag.started) {
		if (Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < THRESHOLD) {
			return;
		}

		begin(drag);
	}

	const x = event.clientX - drag.origin.left;
	const y = event.clientY - drag.origin.top;
	const line = lineOf(drag.rows, y);
	const col = cellOf(drag.cols, x, PROBE) - drag.grab.col;
	const row = line ?? cellOf(drag.rows, y, PROBE) - drag.grab.row;
	const target = `${col}/${row}/${line !== null}`;

	if (target === drag.target) {
		return;
	}

	drag.target = target;
	drag.grid.parentElement!.classList.toggle('is-opening-row', line !== null);
	place(
		drag.grid,
		move(drag.start, drag.row, { col, row }, line !== null, columnsOf(drag.grid).columns),
	);
}

function finish(keep: boolean): void {
	if (!drag) {
		return;
	}

	const { grip, row, grid, started, start } = drag;

	drag = null;

	if (!started) {
		grip.focus();

		return;
	}

	row.classList.remove('is-dragging');
	grid.parentElement!.classList.remove('is-moving', 'is-opening-row');

	if (!keep) {
		place(grid, start);

		return;
	}

	commit(grid);
	focusRow(row);
}

function onPointerUp(event: PointerEvent): void {
	if (drag?.pointer === event.pointerId) {
		finish(true);
	}
}

function onLostCapture(event: Event): void {
	if (drag && event.target === drag.grip) {
		finish(true);
	}
}

function onKeyDown(event: KeyboardEvent): void {
	if (drag?.started && event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		finish(false);
	}
}

export function install(): () => void {
	document.addEventListener('htmx:after:swap', scan);
	document.addEventListener('repeater:stamp', onStamp);
	document.addEventListener('change', onChange);
	document.addEventListener('click', onMove, true);
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('pointerdown', onPointerDown);
	document.addEventListener('pointermove', onPointerMove);
	document.addEventListener('pointerup', onPointerUp);
	document.addEventListener('pointercancel', onPointerUp);
	document.addEventListener('lostpointercapture', onLostCapture);
	document.addEventListener('keydown', onKeyDown, true);
	scan();

	return () => {
		document.removeEventListener('htmx:after:swap', scan);
		document.removeEventListener('repeater:stamp', onStamp);
		document.removeEventListener('change', onChange);
		document.removeEventListener('click', onMove, true);
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('pointerdown', onPointerDown);
		document.removeEventListener('pointermove', onPointerMove);
		document.removeEventListener('pointerup', onPointerUp);
		document.removeEventListener('pointercancel', onPointerUp);
		document.removeEventListener('lostpointercapture', onLostCapture);
		document.removeEventListener('keydown', onKeyDown, true);
		drag = null;
	};
}
