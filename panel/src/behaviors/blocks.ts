// Layout editing for the blocks editor. A block row carries its spans
// as hidden inputs (data-layout="colspan|rowspan") and, in its settings
// dialog, one number input per dimension ([data-layout-input]). Typing
// into one applies the value as it is typed, within the bounds the
// field's shape and the save patch enforce, then writes the hidden
// input, the row's custom properties and the other inputs' limits. The
// bounds come from the container's data-columns/data-min.
//
// A block on a multi-column grid is placed by position (see the
// placement behavior). Its edges drag: a pointer gesture on a
// [data-layout-resize] handle maps the travelled distance to whole
// steps. The end edge grows into free cells only, up to the grid's edge,
// the start edge moves its start column and keeps the end edge put, and
// the bottom edge counts rows, a taller block pushing the ones below
// down; the positions of every block the gesture moved are written along
// with its spans. The keyboard reaches the same edges from the focused
// grip: Alt with the arrows, Shift added for the start edge.
//
// A split's parts follow it: side by side they share its width in
// proportion and take its height, stacked they take its width while its
// height is theirs added up. A part itself changes only along its split —
// side by side its width trades with its neighbour, the seam between two
// parts dragging that trade; stacked its rows change and the split's
// follow.

import {
	MAX_ROWSPAN,
	animate,
	commit,
	gridOf as canvasOf,
	limits,
	place,
	placed,
	resize as resizePlaced,
	snapshot,
	span,
	type Boxes,
} from './placement';
import { focusRow } from './repeater';

export { MAX_ROWSPAN };

export type Dimension = 'colspan' | 'rowspan';
export type Layout = Record<Dimension, number>;
export type Grid = { columns: number; min: number };
export type Bounds = Record<Dimension, { low: number; high: number }>;
export type Edge = 'start' | 'end' | 'bottom';
export type Direction = 'columns' | 'rows';

const DIMENSIONS: Dimension[] = ['colspan', 'rowspan'];

function between(value: number, low: number, high: number): number {
	return Math.max(low, Math.min(high, Math.trunc(value) || 0));
}

export function grid(columns: number, min: number): Grid {
	const cols = between(columns, 1, Number.MAX_SAFE_INTEGER);

	return { columns: cols, min: between(min, 1, cols) };
}

export function clamp(layout: Layout, grid: Grid): Layout {
	return {
		colspan: between(layout.colspan, grid.min, grid.columns),
		rowspan: between(layout.rowspan, 1, MAX_ROWSPAN),
	};
}

/** The range of each dimension on a grid. */
export function bounds(grid: Grid): Bounds {
	return {
		colspan: { low: grid.min, high: grid.columns },
		rowspan: { low: 1, high: MAX_ROWSPAN },
	};
}

export function parseDimension(value: string | null): Dimension | null {
	return value === 'colspan' || value === 'rowspan' ? value : null;
}

/** One track plus one gap — the distance a colspan of 1 travels. */
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

export function parseEdge(value: string | null): Edge | null {
	return value === 'start' || value === 'end' || value === 'bottom' ? value : null;
}

/** The edge and direction a key moves, or null for a key that is not ours. */
export function parseKey(event: KeyboardEvent): { edge: Edge; steps: number } | null {
	if (!event.altKey || event.ctrlKey || event.metaKey) {
		return null;
	}

	switch (event.key) {
		case 'ArrowLeft':
			return { edge: event.shiftKey ? 'start' : 'end', steps: -1 };
		case 'ArrowRight':
			return { edge: event.shiftKey ? 'start' : 'end', steps: 1 };
		case 'ArrowUp':
			return event.shiftKey ? null : { edge: 'bottom', steps: -1 };
		case 'ArrowDown':
			return event.shiftKey ? null : { edge: 'bottom', steps: 1 };
		default:
			return null;
	}
}

export function gridOf(container: HTMLElement): Grid {
	return grid(Number(container.dataset.columns) || 1, Number(container.dataset.min) || 1);
}

/** The split a part sits in; null for a block on the grid. */
export function splitOf(row: HTMLElement): HTMLElement | null {
	const list = row.parentElement;

	return list?.matches('.parts') ? list.closest<HTMLElement>('[data-repeater-row]') : null;
}

export function partsOf(split: HTMLElement): HTMLElement[] {
	return [...split.querySelectorAll<HTMLElement>(':scope > .parts > [data-repeater-row]')];
}

export function directionOf(split: HTMLElement): Direction {
	return split.dataset.split === 'rows' ? 'rows' : 'columns';
}

/**
 * The grid a row's layout moves in: its list's, and for a split into
 * columns one that leaves each part its minimum width.
 */
export function gridFor(row: HTMLElement): Grid {
	const list = row.parentElement?.closest<HTMLElement>('[data-repeater]');
	const base = list ? gridOf(list) : grid(1, 1);

	return row.matches('.is-split') && directionOf(row) === 'columns'
		? grid(base.columns, base.min * Math.max(1, partsOf(row).length))
		: base;
}

/**
 * Whole widths in proportion to `widths` adding up to `total`, none
 * below `min`; the last takes what rounding leaves.
 */
function shares(widths: number[], total: number, min: number): number[] {
	const sum = widths.reduce((all, width) => all + width, 0) || 1;
	let left = total;

	return widths.map((width, index) => {
		const rest = widths.length - index - 1;
		const share =
			rest === 0 ? left : between(Math.round((width * total) / sum), min, left - rest * min);

		left -= share;

		return share;
	});
}

function input(row: HTMLElement, dimension: Dimension): HTMLInputElement | null {
	return row.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);
}

export function read(row: HTMLElement): Layout {
	const layout = { colspan: 1, rowspan: 1 };

	for (const dimension of DIMENSIONS) {
		layout[dimension] = Number(input(row, dimension)?.value) || layout[dimension];
	}

	return layout;
}

export function write(row: HTMLElement, layout: Layout, grid: Grid): void {
	const limits = bounds(grid);

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
}

function widthsOf(split: HTMLElement): number[] {
	return partsOf(split).map((part) => read(part).colspan);
}

/**
 * A row's layout written, and a split's parts fitted to it: side by side
 * in proportion to `widths`, their widths when the gesture began, since
 * rounding step by step would drift.
 */
function apply(row: HTMLElement, layout: Layout, grid: Grid, widths = widthsOf(row)): void {
	const parts = row.querySelector<HTMLElement>(':scope > .parts');

	write(row, layout, grid);

	if (!row.matches('.is-split') || !parts) {
		return;
	}

	parts.dataset.columns = String(layout.colspan);

	const inner = gridOf(parts);
	const rows = partsOf(row);

	if (directionOf(row) === 'rows') {
		rows.forEach((part) => write(part, { ...read(part), colspan: layout.colspan }, inner));

		return;
	}

	const shared = shares(widths, layout.colspan, inner.min);

	rows.forEach((part, index) =>
		write(part, { colspan: shared[index], rowspan: layout.rowspan }, inner),
	);
}

/** The part a part's width trades with: the next one, or the previous for the last. */
function neighbour(part: HTMLElement, parts: HTMLElement[]): HTMLElement | undefined {
	const index = parts.indexOf(part);

	return parts[index + 1] ?? parts[index - 1];
}

/** The values a part can take for a dimension: only its split's direction moves. */
function reach(part: HTMLElement, dimension: Dimension): { low: number; high: number } {
	const split = splitOf(part);
	const own = read(part)[dimension];

	if (!split) {
		return { low: own, high: own };
	}

	const parts = partsOf(split);

	if (directionOf(split) === 'columns' && dimension === 'colspan') {
		const other = neighbour(part, parts);
		const { min } = gridFor(part);

		return other ? { low: min, high: own + read(other).colspan - min } : { low: own, high: own };
	}

	if (directionOf(split) === 'rows' && dimension === 'rowspan') {
		const others = parts.reduce((sum, row) => sum + (row === part ? 0 : read(row).rowspan), 0);

		return { low: 1, high: MAX_ROWSPAN - others };
	}

	return { low: own, high: own };
}

/** A part's dimension set within its reach; false when nothing changed. */
function resizePart(part: HTMLElement, dimension: Dimension, value: number): boolean {
	const split = splitOf(part);
	const before = read(part);
	const { low, high } = reach(part, dimension);
	const next = between(value, low, high);

	if (!split || next === before[dimension]) {
		return false;
	}

	const grid = gridFor(part);
	const change = next - before[dimension];

	if (dimension === 'colspan') {
		const other = neighbour(part, partsOf(split))!;
		const layout = read(other);

		write(other, { ...layout, colspan: layout.colspan - change }, grid);
	} else {
		const area = read(split);

		write(split, { ...area, rowspan: area.rowspan + change }, gridFor(split));
	}

	write(part, { ...before, [dimension]: next }, grid);

	return true;
}

/** A placed block's spans written from its box, and every block's position. */
function applyPlaced(
	row: HTMLElement,
	boxes: Boxes<HTMLElement>,
	grid: Grid,
	widths?: number[],
): void {
	const box = boxes.get(row)!;

	const canvas = canvasOf(row)!;

	animate(
		canvas,
		() => {
			apply(row, { colspan: box.colspan, rowspan: box.rowspan }, grid, widths);
			place(canvas, boxes);
		},
		row,
	);
}

function same(a: Boxes<HTMLElement>, b: Boxes<HTMLElement>): boolean {
	return [...a].every(([row, box]) => {
		const other = b.get(row);

		return (
			other !== undefined &&
			other.col === box.col &&
			other.row === box.row &&
			other.colspan === box.colspan &&
			other.rowspan === box.rowspan
		);
	});
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
	widths: number[];
	origin: number;
	moved: boolean;
	/** The canvas as the gesture found it, for a placed block. */
	boxes: Boxes<HTMLElement> | null;
};

let drag: Drag | null = null;

function position(event: PointerEvent, edge: Edge): number {
	return edge === 'bottom' ? event.clientY : event.clientX;
}

function listOf(container: HTMLElement): HTMLElement {
	return container.querySelector<HTMLElement>(':scope > [data-repeater-list]') ?? container;
}

/**
 * A read-only field is ignored whole on save, so a layout gesture inside
 * one would only lose the work. Its handles are not rendered either; this
 * closes the keyboard and pointer paths that do not need them.
 */
function locked(container: HTMLElement): boolean {
	return container.closest('[data-readonly="true"]') !== null;
}

/**
 * The travel of one column: a track plus its gap, off the list's own box.
 * A split's parts are a subgrid, whose gap computes to `normal`: the gap
 * is the field grid's.
 */
function pitchOf(container: HTMLElement, columns: number): number {
	const list = listOf(container);
	const style = getComputedStyle(list);
	const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
	const gap = getComputedStyle(list.closest('.grid') ?? list).columnGap;

	return pitch(list.clientWidth - padding, columns, parseFloat(gap) || 0);
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

	// A second finger does not join a gesture in progress, and a read-only
	// field has no layout to drag.
	if (drag || !handle || !edge || !row || !container || locked(container)) {
		return;
	}

	const grid = gridFor(row);

	drag = {
		pointer: event.pointerId,
		handle,
		row,
		container,
		edge,
		grid,
		pitch: pitchOf(container, grid.columns),
		start: read(row),
		widths: widthsOf(row),
		origin: position(event, edge),
		moved: false,
		boxes: placed(row) ? snapshot(canvasOf(row)!) : null,
	};
	handle.setPointerCapture(event.pointerId);
	handle.classList.add('is-active');
	container.classList.add('is-resizing');
	event.preventDefault();
}

function onPointerMove(event: PointerEvent): void {
	if (!drag || drag.pointer !== event.pointerId) {
		return;
	}

	const travelled = position(event, drag.edge) - drag.origin;
	const steps =
		drag.edge === 'bottom' ? ratchet(travelled, ROW_STEP) : shift(travelled, drag.pitch);

	// A part's only handle is the seam to the next part; the pair's width
	// stays the same, so the start plus the steps is the part's new width.
	if (splitOf(drag.row)) {
		drag.moved = resizePart(drag.row, 'colspan', drag.start.colspan + steps) || drag.moved;

		return;
	}

	if (drag.boxes) {
		const canvas = canvasOf(drag.row)!;
		const next = resizePlaced(
			drag.boxes,
			drag.row,
			drag.edge,
			steps,
			drag.grid.columns,
			drag.grid.min,
		);

		if (!same(next, snapshot(canvas))) {
			applyPlaced(drag.row, next, drag.grid, drag.widths);
			drag.moved = true;
		}
	}
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

	const { handle, row, container, edge, moved, boxes } = drag;

	drag = null;
	handle.classList.remove('is-active');
	container.classList.remove('is-resizing');

	if (moved && boxes) {
		commit(canvasOf(row)!);
	} else if (moved) {
		const dimension: Dimension = edge === 'bottom' ? 'rowspan' : 'colspan';

		(input(row, dimension) ?? row).dispatchEvent(new Event('change', { bubbles: true }));
	}
}

/**
 * Alt with an arrow is the browser's history on some platforms, so a
 * handled key is consumed. A one-column field has no layout to reach.
 */
function onKeyDown(event: KeyboardEvent): void {
	const grip = event.target;
	const row = grip instanceof Element ? grip.closest<HTMLElement>('[data-repeater-row]') : null;
	const container = row?.closest<HTMLElement>('[data-repeater]');
	const key = parseKey(event);

	if (
		!(grip instanceof Element) ||
		!grip.matches('[data-repeater-grip]') ||
		!row ||
		!container ||
		!key ||
		locked(container)
	) {
		return;
	}

	const grid = gridFor(row);

	if (gridOf(container).columns < 2) {
		return;
	}

	event.preventDefault();

	const dimension: Dimension = key.edge === 'bottom' ? 'rowspan' : 'colspan';

	if (splitOf(row)) {
		if (key.edge !== 'start' && resizePart(row, dimension, read(row)[dimension] + key.steps)) {
			(input(row, dimension) ?? row).dispatchEvent(new Event('change', { bubbles: true }));
		}

		return;
	}

	// A split into rows is as tall as its parts.
	if (row.matches('.is-split') && directionOf(row) === 'rows' && key.edge === 'bottom') {
		return;
	}

	const canvas = placed(row) ? canvasOf(row) : null;

	if (!canvas) {
		return;
	}

	const boxes = snapshot(canvas);
	const next = resizePlaced(boxes, row, key.edge, key.steps, grid.columns, grid.min);

	if (!same(next, boxes)) {
		applyPlaced(row, next, grid);
		commit(canvas);
	}
}

function onPointerUp(event: PointerEvent): void {
	if (drag && drag.pointer === event.pointerId) {
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

	if (!dimension || !row || !container || locked(container)) {
		return;
	}

	const grid = gridFor(row);
	const before = read(row);
	const typed = control.value === '' ? NaN : Number(control.value);
	const part = splitOf(row) !== null;

	if (placed(row)) {
		const canvas = canvasOf(row)!;
		const boxes = snapshot(canvas);
		const { low, high } = limits(boxes, row, grid.columns, grid.min)[dimension];

		if (event.type !== 'change' && !(typed >= low && typed <= high)) {
			return;
		}

		const value = Number.isNaN(typed) ? before[dimension] : typed;

		applyPlaced(row, span(boxes, row, dimension, value, grid.columns, grid.min), grid);

		if (event.type === 'change') {
			commit(canvas);
		}

		return;
	}

	const { low, high } = reach(row, dimension);

	if (!part || (event.type !== 'change' && !(typed >= low && typed <= high))) {
		return;
	}

	const value = Number.isNaN(typed) ? before[dimension] : typed;

	// Out of reach: the number shows what the part kept.
	if (!resizePart(row, dimension, value)) {
		write(row, before, grid);
	}
}

const SPACING = ['none', 's', 'm', 'l', 'xl'];
const CONTAINER_SPACING: Record<string, string> = {
	gap: 'gap',
	rowGap: 'rowGap',
	columnGap: 'columnGap',
};

function spacingKey(control: HTMLSelectElement): string | null {
	return /\[meta\]\[(gap|rowGap|columnGap|padding)\]\[zxx\]$/.exec(control.name)?.[1] ?? null;
}

function applySpacing(target: HTMLElement, key: string, value: string): void {
	if (SPACING.includes(value)) {
		target.dataset[key] = value;
	} else {
		delete target.dataset[key];
	}
}

/**
 * The canvas follows the spacing dialogs live: a block's padding select
 * writes the row's attribute, the field's gap selects write every
 * container of the field, so the grid and the resize math read the gap
 * the site will render with.
 */
export function mirrorSpacing(control: HTMLSelectElement): void {
	const key = spacingKey(control);

	if (key === 'padding') {
		const row = control.closest<HTMLElement>('[data-repeater-row]');

		if (row) {
			applySpacing(row, key, control.value);
		}

		return;
	}

	if (!key) {
		return;
	}

	control
		.closest('[data-meta-owner]')
		?.querySelectorAll<HTMLElement>('.cms-blocks-editor')
		.forEach((container) => applySpacing(container, CONTAINER_SPACING[key], control.value));
}

function onSpacing(event: Event): void {
	if (event.target instanceof HTMLSelectElement) {
		mirrorSpacing(event.target);
	}
}

/**
 * The field's gap dialog: splitting copies the gap into both axes and
 * clears it, joining copies the row gap back. The selects then report a
 * change of their own, so the form turns dirty as for a typed choice.
 */
function onSplit(event: Event): void {
	const toggle = event.target;

	if (!(toggle instanceof HTMLInputElement) || !toggle.matches('[data-gap-split]')) {
		return;
	}

	const scope = toggle.closest<HTMLElement>('[data-gap-scope]');
	const select = (key: string): HTMLSelectElement | null =>
		scope?.querySelector<HTMLSelectElement>(`select[name$="[meta][${key}][zxx]"]`) ?? null;
	const gap = select('gap');
	const row = select('rowGap');
	const column = select('columnGap');

	if (!scope || !gap || !row || !column) {
		return;
	}

	if (toggle.checked) {
		row.value = gap.value;
		column.value = gap.value;
		gap.value = '';
	} else {
		gap.value = row.value || column.value;
		row.value = '';
		column.value = '';
	}

	scope.querySelectorAll<HTMLElement>('[data-gap-single]').forEach((part) => {
		part.hidden = toggle.checked;
	});
	scope.querySelectorAll<HTMLElement>('[data-gap-separate]').forEach((part) => {
		part.hidden = !toggle.checked;
	});

	for (const changed of [gap, row, column]) {
		changed.dispatchEvent(new Event('change', { bubbles: true }));
	}
}

/**
 * A stamped row shows the layout its inputs carry: a duplicate copies
 * the inputs of its source, while its style and dialog still say what
 * the template did.
 */
const INTERACTIVE =
	'a, button, input, select, textarea, label, summary, dialog, [popover], [contenteditable], [tabindex]';

// A click on a block's own ground makes it the active one — the border
// and the chrome follow focus — while a click on anything interactive
// keeps its meaning.
function onClick(event: MouseEvent): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const row = target.closest<HTMLElement>('.cms-blocks-editor [data-repeater-row]');

	if (!row || row.contains(document.activeElement)) {
		return;
	}

	const interactive = target.closest(INTERACTIVE);

	if (interactive && row.contains(interactive)) {
		return;
	}

	focusRow(row);
}

function onStamp(event: Event): void {
	const row = event.target;
	const container = row instanceof HTMLElement ? row.closest<HTMLElement>('[data-repeater]') : null;

	if (!(row instanceof HTMLElement) || !container) {
		return;
	}

	if (input(row, 'colspan')) {
		write(row, read(row), gridOf(container));
	}

	// A duplicate copies its source's padding select; the row shows it.
	const padding = row.querySelector<HTMLSelectElement>('select[name$="[meta][padding][zxx]"]');

	if (padding) {
		mirrorSpacing(padding);
	}
}

export function install(): () => void {
	document.addEventListener('repeater:stamp', onStamp);
	document.addEventListener('click', onClick);
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('change', onSplit);
	document.addEventListener('change', onSpacing);
	document.addEventListener('keydown', onKeyDown);
	document.addEventListener('pointerdown', onPointerDown);
	document.addEventListener('pointermove', onPointerMove);
	document.addEventListener('pointerup', onPointerUp);
	document.addEventListener('pointercancel', onPointerUp);
	document.addEventListener('lostpointercapture', onLostCapture);

	return () => {
		document.removeEventListener('repeater:stamp', onStamp);
		document.removeEventListener('click', onClick);
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('change', onSplit);
		document.removeEventListener('change', onSpacing);
		document.removeEventListener('keydown', onKeyDown);
		document.removeEventListener('pointerdown', onPointerDown);
		document.removeEventListener('pointermove', onPointerMove);
		document.removeEventListener('pointerup', onPointerUp);
		document.removeEventListener('pointercancel', onPointerUp);
		document.removeEventListener('lostpointercapture', onLostCapture);
	};
}
