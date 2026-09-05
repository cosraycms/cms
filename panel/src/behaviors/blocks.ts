// Layout editing for the blocks editor. A block row carries its layout
// as hidden inputs (data-layout="span|rows|indent") and, in its settings
// dialog, one number input per dimension ([data-layout-input]). Typing
// into one applies the value as it is typed, within the bounds the
// field's shape and the save patch enforce — span in
// [min, columns − indent], rows in [1, MAX_ROWS], indent in
// [0, columns − span]; a dimension is capped by the room the others
// leave and never moves them — then writes the hidden input, the row's
// custom properties and data-indent (the grid preview follows) and the
// other inputs' limits. The bounds come from the container's
// data-columns/data-min. A row's edges also drag: a pointer gesture on a
// [data-layout-resize] handle maps the travelled distance to whole steps
// and writes the layout through the same path. Each edge moves only
// itself — the end edge grows the span up to the grid's edge, the start
// edge trades indent against span so the end edge stays put, and the
// bottom edge counts rows.

export const MAX_ROWS = 6;

export type Dimension = 'span' | 'rows' | 'indent';
export type Layout = Record<Dimension, number>;
export type Grid = { columns: number; min: number };
export type Bounds = Record<Dimension, { low: number; high: number }>;
export type Edge = 'start' | 'end' | 'bottom';

const DIMENSIONS: Dimension[] = ['span', 'rows', 'indent'];

function between(value: number, low: number, high: number): number {
	return Math.max(low, Math.min(high, Math.trunc(value) || 0));
}

export function grid(columns: number, min: number): Grid {
	const cols = between(columns, 1, Number.MAX_SAFE_INTEGER);

	return { columns: cols, min: between(min, 1, cols) };
}

export function clamp(layout: Layout, grid: Grid): Layout {
	const span = between(layout.span, grid.min, grid.columns);

	return {
		span,
		rows: between(layout.rows, 1, MAX_ROWS),
		indent: between(layout.indent, 0, grid.columns - span),
	};
}

/** The reachable range of each dimension given the others. */
export function bounds(layout: Layout, grid: Grid): Bounds {
	const { span, indent } = clamp(layout, grid);

	return {
		span: { low: grid.min, high: grid.columns - indent },
		rows: { low: 1, high: MAX_ROWS },
		indent: { low: 0, high: grid.columns - span },
	};
}

/** One dimension set, capped by the room the others leave; they stay put. */
export function set(layout: Layout, dimension: Dimension, value: number, grid: Grid): Layout {
	const { low, high } = bounds(layout, grid)[dimension];

	return clamp({ ...layout, [dimension]: between(value, low, high) }, grid);
}

export function parseDimension(value: string | null): Dimension | null {
	return value === 'span' || value === 'rows' || value === 'indent' ? value : null;
}

/** One track plus one gap — the distance a span of 1 travels. */
export function pitch(extent: number, tracks: number, gap: number): number {
	return tracks > 0 ? (extent + gap) / tracks : 0;
}

export function shift(distance: number, pitch: number): number {
	return pitch > 0 ? Math.round(distance / pitch) : 0;
}

/**
 * Rows ratchet instead of following the pointer: grid tracks size to their
 * content, so a row is worth whatever the tallest block in it happens to
 * be — nothing the bottom edge could track. One row per full step of
 * travel, so the first one takes a deliberate drag.
 */
export const ROW_STEP = 100;

export function ratchet(distance: number, step: number): number {
	return step > 0 ? Math.trunc(distance / step) || 0 : 0;
}

/**
 * One edge, moved by whole steps. The indent is relative to the flow, so
 * the stored layout says where every edge is: the start edge trades indent
 * against span and leaves the block where it sits, while the end edge only
 * grows the span — past the columns still free in its row, the grid wraps
 * the block onto the next line by itself.
 */
export function resize(start: Layout, edge: Edge, steps: number, grid: Grid): Layout {
	if (edge === 'bottom') {
		return set(start, 'rows', start.rows + steps, grid);
	}

	if (edge === 'end') {
		return set(start, 'span', start.span + steps, grid);
	}

	const moved = between(steps, -start.indent, start.span - grid.min);

	return clamp({ ...start, indent: start.indent + moved, span: start.span - moved }, grid);
}

export function parseEdge(value: string | null): Edge | null {
	return value === 'start' || value === 'end' || value === 'bottom' ? value : null;
}

function gridOf(container: HTMLElement): Grid {
	return grid(Number(container.dataset.columns) || 1, Number(container.dataset.min) || 1);
}

function input(row: HTMLElement, dimension: Dimension): HTMLInputElement | null {
	return row.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);
}

export function read(row: HTMLElement): Layout {
	const layout = { span: 1, rows: 1, indent: 0 };

	for (const dimension of DIMENSIONS) {
		layout[dimension] = Number(input(row, dimension)?.value) || layout[dimension];
	}

	return layout;
}

export function write(row: HTMLElement, layout: Layout, grid: Grid): void {
	const limits = bounds(layout, grid);

	for (const dimension of DIMENSIONS) {
		const value = String(layout[dimension]);
		const field = input(row, dimension);

		if (field) {
			field.value = value;
		}

		row.style.setProperty(`--${dimension}`, value);
		row
			.querySelectorAll<HTMLInputElement>(`input[data-layout-input="${dimension}"]`)
			.forEach((control) => {
				control.min = String(limits[dimension].low);
				control.max = String(limits[dimension].high);
				control.value = value;
			});
	}

	row.style.setProperty('--reserved', String(layout.indent + layout.span));
	row.dataset.indent = String(layout.indent);
}

type Drag = {
	pointer: number;
	handle: HTMLElement;
	row: HTMLElement;
	container: HTMLElement;
	edge: Edge;
	grid: Grid;
	pitch: number;
	start: Layout;
	origin: number;
	moved: boolean;
};

let drag: Drag | null = null;

function position(event: PointerEvent, edge: Edge): number {
	return edge === 'bottom' ? event.clientY : event.clientX;
}

function listOf(container: HTMLElement): HTMLElement {
	return container.querySelector<HTMLElement>(':scope > [data-repeater-list]') ?? container;
}

/** The travel of one column: a track plus its gap, off the list's own box. */
function pitchOf(container: HTMLElement, columns: number): number {
	const list = listOf(container);
	const style = getComputedStyle(list);
	const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);

	return pitch(list.clientWidth - padding, columns, parseFloat(style.columnGap) || 0);
}

function onPointerDown(event: PointerEvent): void {
	const target = event.target;

	if (!(target instanceof Element) || event.button !== 0) {
		return;
	}

	const handle = target.closest<HTMLElement>('[data-layout-resize]');
	const edge = parseEdge(handle?.getAttribute('data-layout-resize') ?? null);
	const row = handle?.closest<HTMLElement>('[data-repeater-row]');
	const container = row?.closest<HTMLElement>('[data-repeater]');

	// A second finger does not join a gesture in progress.
	if (drag || !handle || !edge || !row || !container) {
		return;
	}

	const grid = gridOf(container);

	drag = {
		pointer: event.pointerId,
		handle,
		row,
		container,
		edge,
		grid,
		pitch: pitchOf(container, grid.columns),
		start: read(row),
		origin: position(event, edge),
		moved: false,
	};
	handle.setPointerCapture(event.pointerId);
	handle.classList.add('is-active');
	container.classList.add('is-resizing');
	event.preventDefault();
}

function onPointerMove(event: PointerEvent): void {
	if (drag?.pointer !== event.pointerId) {
		return;
	}

	const travelled = position(event, drag.edge) - drag.origin;
	const steps =
		drag.edge === 'bottom' ? ratchet(travelled, ROW_STEP) : shift(travelled, drag.pitch);
	const next = resize(drag.start, drag.edge, steps, drag.grid);
	const current = read(drag.row);

	if (DIMENSIONS.every((dimension) => current[dimension] === next[dimension])) {
		return;
	}

	write(drag.row, next, drag.grid);
	drag.moved = true;
}

/**
 * The browser releases the capture itself once the gesture is over, so the
 * end is the same whether the pointer was lifted or the capture was lost —
 * the latter would otherwise leave a drag standing that refuses every
 * later one.
 */
function end(): void {
	if (!drag) {
		return;
	}

	const { handle, row, container, edge, moved } = drag;

	drag = null;
	handle.classList.remove('is-active');
	container.classList.remove('is-resizing');

	if (moved) {
		const dimension: Dimension = edge === 'bottom' ? 'rows' : 'span';

		(input(row, dimension) ?? row).dispatchEvent(new Event('change', { bubbles: true }));
	}
}

function onPointerUp(event: PointerEvent): void {
	if (drag?.pointer === event.pointerId) {
		end();
	}
}

function onLostCapture(event: Event): void {
	if (drag && event.target === drag.handle) {
		end();
	}
}

/**
 * A typed value is applied as soon as the block can take it; one that is
 * out of range or half typed waits until the input commits, or the write
 * back would fight the typing.
 */
function onInput(event: Event): void {
	const control = event.target;

	if (!(control instanceof HTMLInputElement)) {
		return;
	}

	const dimension = parseDimension(control.getAttribute('data-layout-input'));
	const row = control.closest<HTMLElement>('[data-repeater-row]');
	const container = row?.closest<HTMLElement>('[data-repeater]');

	if (!dimension || !row || !container) {
		return;
	}

	const grid = gridOf(container);
	const before = read(row);
	const typed = control.value === '' ? NaN : Number(control.value);
	const { low, high } = bounds(before, grid)[dimension];

	if (event.type !== 'change' && !(typed >= low && typed <= high)) {
		return;
	}

	write(row, set(before, dimension, Number.isNaN(typed) ? before[dimension] : typed, grid), grid);
}

export function install(): () => void {
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('pointerdown', onPointerDown);
	document.addEventListener('pointermove', onPointerMove);
	document.addEventListener('pointerup', onPointerUp);
	document.addEventListener('pointercancel', onPointerUp);
	document.addEventListener('lostpointercapture', onLostCapture);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('pointerdown', onPointerDown);
		document.removeEventListener('pointermove', onPointerMove);
		document.removeEventListener('pointerup', onPointerUp);
		document.removeEventListener('pointercancel', onPointerUp);
		document.removeEventListener('lostpointercapture', onLostCapture);
	};
}
