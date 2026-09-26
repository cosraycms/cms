// Positions on a multi-column blocks canvas. A block on the grid stores
// the column and row it starts at next to its spans (data-layout="col"
// and "row", 1-based) and sits exactly there; the reading order follows
// from the positions, row by row and left to right, and the rows are
// kept in that order in the DOM, so the form submits them that way.
// Blocks never overlap: a block moved, or grown downwards, onto others
// pushes them down below itself, and whatever they land on in turn.
// Rows no block covers any more are taken out. A block grows sideways
// only into free cells, up to the grid's edge. Every row arrives with its
// position from the server; a stamped one gets it here.
//
// The grip drags a block to another spot: the cell under the pointer is
// where its grabbed cell lands, and the pointer right on a line between
// two rows opens a new row there. The grid's tracks are read once when
// the drag starts, so a row changing height under a moved block does
// not move the target. The block is lifted out of the grid and follows
// the pointer; a slot of its size takes part in the grid where it would
// land, and the others show where they would go. Escape puts everything
// back. Near the top or bottom edge of whatever scrolls the canvas, the
// drag scrolls it, faster the closer the pointer gets, and the target
// follows the grid moving under the resting pointer.
//
// The grid places blocks without motion. Every change of positions
// animates the blocks from where they appeared to where the grid put
// them (FLIP), a lifted block settling into its slot as well.

import { changed, focusRow, renumber } from './repeater.js';

/**
 * @typedef {object} Box
 * @property {number} col
 * @property {number} row
 * @property {number} colspan
 * @property {number} rowspan
 */

/**
 * @template T
 * @typedef {Map<T, Box>} Boxes
 */

/** @typedef {'start' | 'end' | 'bottom'} Edge */

/**
 * @typedef {object} Edges
 * @property {number[]} starts
 * @property {number[]} ends
 */

export const MAX_ROWSPAN = 6;

const GRID = '.cms-blocks-editor.is-grid > .grid';

/**
 * @param {number} value
 * @param {number} low
 * @param {number} high
 * @returns {number}
 */
function between(value, low, high) {
	return Math.max(low, Math.min(high, Math.trunc(value) || 0));
}

/**
 * @param {Box} a
 * @param {Box} b
 * @returns {boolean}
 */
export function overlaps(a, b) {
	return (
		a.col < b.col + b.colspan &&
		b.col < a.col + a.colspan &&
		a.row < b.row + b.rowspan &&
		b.row < a.row + a.rowspan
	);
}

/**
 * @template T
 * @param {Boxes<T>} boxes
 * @returns {Boxes<T>}
 */
function copy(boxes) {
	return new Map([...boxes].map(([key, box]) => [key, { ...box }]));
}

/**
 * @param {Iterable<Box>} boxes
 * @returns {number}
 */
function bottom(boxes) {
	let last = 0;

	for (const box of boxes) {
		last = Math.max(last, box.row + box.rowspan - 1);
	}

	return last;
}

/**
 * The fixed block stays; the others, top to bottom, each move down below
 * whatever they hit, so blocks pushed together keep their order.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} fixed
 * @returns {Boxes<T>}
 */
export function settle(boxes, fixed) {
	const result = copy(boxes);
	const settled = [/** @type {Box} */ (result.get(fixed))];

	for (const key of sorted(boxes)) {
		if (key === fixed) {
			continue;
		}

		const box = /** @type {Box} */ (result.get(key));

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

/**
 * Rows no block covers are taken out; the blocks below move up.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @returns {Boxes<T>}
 */
export function compact(boxes) {
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

/**
 * Reading order: row by row, left to right.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @returns {T[]}
 */
export function sorted(boxes) {
	return [...boxes].sort(([, a], [, b]) => a.row - b.row || a.col - b.col).map(([key]) => key);
}

/**
 * The block moved to a spot, clamped into the columns; with `insert`, it
 * gets rows of its own there, the blocks from that row on moving down.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} key
 * @param {{ col: number; row: number }} to
 * @param {boolean} insert
 * @param {number} columns
 * @returns {Boxes<T>}
 */
export function move(boxes, key, to, insert, columns) {
	const result = copy(boxes);
	const box = /** @type {Box} */ (result.get(key));

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

/**
 * The widest the block can get from where it starts: free cells up to the grid's edge.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} key
 * @param {number} columns
 * @returns {{ left: number; right: number }}
 */
function reach(boxes, key, columns) {
	const box = /** @type {Box} */ (boxes.get(key));
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

/**
 * The range each span can take where the block sits.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} key
 * @param {number} columns
 * @param {number} min
 * @returns {{ colspan: { low: number; high: number }; rowspan: { low: number; high: number } }}
 */
export function limits(boxes, key, columns, min) {
	const box = /** @type {Box} */ (boxes.get(key));
	const { right } = reach(boxes, key, columns);

	return {
		colspan: { low: min, high: Math.max(box.colspan, right - box.col + 1) },
		rowspan: { low: 1, high: MAX_ROWSPAN },
	};
}

/**
 * One span set within its limits; a taller block pushes the ones below down.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} key
 * @param {'colspan' | 'rowspan'} dimension
 * @param {number} value
 * @param {number} columns
 * @param {number} min
 * @returns {Boxes<T>}
 */
export function span(boxes, key, dimension, value, columns, min) {
	const result = copy(boxes);
	const box = /** @type {Box} */ (result.get(key));
	const { low, high } = limits(boxes, key, columns, min)[dimension];

	box[dimension] = between(value, low, high);

	return dimension === 'rowspan' ? compact(settle(result, key)) : result;
}

/**
 * One edge moved by whole steps: the end edge grows into free cells up
 * to the grid's edge, the start edge moves the start column the same way
 * and keeps the end edge where it is, the bottom edge counts rows.
 *
 * @template T
 * @param {Boxes<T>} boxes
 * @param {T} key
 * @param {Edge} edge
 * @param {number} steps
 * @param {number} columns
 * @param {number} min
 * @returns {Boxes<T>}
 */
export function resize(boxes, key, edge, steps, columns, min) {
	const box = /** @type {Box} */ (boxes.get(key));

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
 *
 * @param {Iterable<Box>} boxes
 * @param {number} columns
 * @param {number} min
 * @returns {Box[]}
 */
export function gaps(boxes, columns, min) {
	const list = [...boxes];
	const rows = bottom(list);
	const taken = Array.from(
		{ length: rows + 1 },
		() => /** @type {boolean[]} */ (Array(columns + 1).fill(false)),
	);

	for (const box of list) {
		for (let row = box.row; row < box.row + box.rowspan; row++) {
			for (let col = box.col; col < box.col + box.colspan; col++) {
				taken[row][col] = true;
			}
		}
	}

	/** @type {Box[]} */
	const found = [];

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

/**
 * Track edges from a resolved `grid-template-*` value, gaps added between.
 *
 * @param {string} template
 * @param {number} gap
 * @returns {Edges}
 */
export function tracks(template, gap) {
	/** @type {number[]} */
	const starts = [];
	/** @type {number[]} */
	const ends = [];
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

/**
 * The grid a row is placed on; null for a part, a list or a read-only canvas row alike.
 *
 * @param {HTMLElement} row
 * @returns {HTMLElement | null}
 */
export function gridOf(row) {
	const grid = row.parentElement;

	return grid?.matches(GRID) ? grid : null;
}

/**
 * @param {HTMLElement} row
 * @returns {boolean}
 */
export function placed(row) {
	return row.hasAttribute('data-placed') && gridOf(row) !== null;
}

/**
 * @param {HTMLElement} grid
 * @returns {HTMLElement[]}
 */
function rowsOf(grid) {
	return [
		.../** @type {NodeListOf<HTMLElement>} */ (
			grid.querySelectorAll(':scope > [data-repeater-row]')
		),
	];
}

/**
 * @param {HTMLElement} row
 * @param {string} key
 * @returns {HTMLInputElement | null}
 */
function field(row, key) {
	return /** @type {HTMLInputElement | null} */ (
		row.querySelector(`:scope > input[data-layout="${key}"]`)
	);
}

/**
 * @param {HTMLElement} row
 * @param {string} key
 * @returns {number}
 */
function number(row, key) {
	return Number(field(row, key)?.value) || 0;
}

/**
 * @param {HTMLElement} row
 * @returns {Box}
 */
export function boxOf(row) {
	return {
		col: number(row, 'col'),
		row: number(row, 'row'),
		colspan: number(row, 'colspan') || 1,
		rowspan: number(row, 'rowspan') || 1,
	};
}

/**
 * @param {HTMLElement} grid
 * @returns {{ columns: number; min: number }}
 */
export function columnsOf(grid) {
	const container = /** @type {HTMLElement} */ (grid.parentElement);
	const columns = Math.max(1, Number(container.dataset.columns) || 1);

	return { columns, min: between(Number(container.dataset.min) || 1, 1, columns) };
}

/**
 * @param {HTMLElement} grid
 * @returns {Boxes<HTMLElement>}
 */
export function snapshot(grid) {
	return new Map(rowsOf(grid).map((row) => [row, boxOf(row)]));
}

/**
 * @param {HTMLElement} row
 * @param {string} key
 * @param {number} value
 */
function set(row, key, value) {
	const input = field(row, key);

	if (input) {
		input.value = String(value);
	}
}

/**
 * The positions written: the hidden inputs, the row's custom properties and the dialog's numbers.
 *
 * @param {HTMLElement} grid
 * @param {Boxes<HTMLElement>} boxes
 */
export function place(grid, boxes) {
	const { columns, min } = columnsOf(grid);

	for (const [row, box] of boxes) {
		set(row, 'col', box.col);
		set(row, 'row', box.row);
		row.style.setProperty('--col', String(box.col));
		row.style.setProperty('--row', String(box.row));
		row.toggleAttribute('data-placed', true);

		const dialog = /** @type {HTMLElement | null} */ (row.querySelector(':scope > dialog'));
		const colspan = limits(boxes, row, columns, min).colspan;

		for (const [key, value, low, high] of /** @type {const} */ ([
			['col', box.col, 1, columns - box.colspan + 1],
			['row', box.row, 1, 999],
			['colspan', box.colspan, colspan.low, colspan.high],
		])) {
			const control = /** @type {HTMLInputElement | null} */ (
				dialog?.querySelector(`input[data-layout-input="${key}"]`)
			);

			if (control && control !== document.activeElement) {
				control.min = String(low);
				control.max = String(high);
				control.value = String(value);
			}
		}
	}
}

/**
 * A position handed over, as from a block to the split that takes its place.
 *
 * @param {HTMLElement} from
 * @param {HTMLElement} to
 */
export function adopt(from, to) {
	const grid = gridOf(to);

	if (grid && number(from, 'col') > 0) {
		place(
			grid,
			new Map([[to, { ...boxOf(to), col: number(from, 'col'), row: number(from, 'row') }]]),
		);
	}
}

/**
 * A block leaving the grid for a split flows there again.
 *
 * @param {HTMLElement} row
 */
export function release(row) {
	set(row, 'col', 0);
	set(row, 'row', 0);
	row.style.removeProperty('--col');
	row.style.removeProperty('--row');
	row.removeAttribute('data-placed');
}

const MOTION = { duration: 180, easing: 'cubic-bezier(0.2, 0, 0, 1)', id: 'placement' };

/**
 * Runs a change of positions and slides every block from where it
 * appeared to where it is now. A block already sliding starts from where
 * it shows, so a change mid-animation does not jump. `still` is left out:
 * a block being resized, or one that has just been stamped. Returns
 * every block's box on screen after the change.
 *
 * @param {HTMLElement} grid
 * @param {() => void} change
 * @param {HTMLElement} [still]
 * @returns {Map<HTMLElement, DOMRect>}
 */
export function animate(grid, change, still) {
	const rows = rowsOf(grid).filter((row) => row !== still && 'animate' in row);
	const calm = rows.length === 0 || matchMedia('(prefers-reduced-motion: reduce)').matches;
	const first = new Map(calm ? [] : rows.map((row) => [row, row.getBoundingClientRect()]));

	for (const row of first.keys()) {
		row.getAnimations().forEach((running) => running.id === MOTION.id && running.cancel());
	}

	change();

	const last = new Map(rowsOf(grid).map((row) => [row, row.getBoundingClientRect()]));

	for (const [row, before] of first) {
		const after = /** @type {DOMRect} */ (last.get(row));
		const x = before.left - after.left;
		const y = before.top - after.top;

		if (row.isConnected && (Math.abs(x) >= 1 || Math.abs(y) >= 1)) {
			row.animate([{ transform: `translate(${x}px, ${y}px)` }, { transform: 'none' }], MOTION);
		}
	}

	return last;
}

/**
 * The rows in reading order in the DOM; true when any moved.
 *
 * @param {HTMLElement} grid
 * @returns {boolean}
 */
function reorder(grid) {
	const rows = rowsOf(grid);
	const order = sorted(snapshot(grid));

	if (order.every((row, index) => rows[index] === row)) {
		return false;
	}

	// An atomic move keeps focus and element state; append reconnects.
	/**
	 * @param {HTMLElement} row
	 */
	const move = (row) => {
		if ('moveBefore' in grid) {
			/** @type {HTMLElement & { moveBefore(node: Node, child: Node | null): void }} */ (
				grid
			).moveBefore(row, null);
		} else {
			grid.append(row);
		}
	};

	order.forEach(move);

	return true;
}

/**
 * Empty rows out, the DOM in reading order, and the change announced.
 *
 * @param {HTMLElement} grid
 */
export function commit(grid) {
	place(grid, compact(snapshot(grid)));
	reorder(grid);
	changed(/** @type {HTMLElement} */ (grid.parentElement));
}

/** @typedef {{ row: HTMLElement; where: 'before' | 'after' } | null} Anchor */

/**
 * A stamped block's spot: before a block, in new rows where that block
 * starts; after one, in new rows below it; appended, below everything.
 * It keeps the anchor's column where its span fits. One stamped with a
 * position of its own (a ghost fills its gap) stays there.
 *
 * @param {Event} event
 */
function onStamp(event) {
	const row = event.target;
	const grid = row instanceof HTMLElement ? gridOf(row) : null;

	if (!(row instanceof HTMLElement) || !grid) {
		return;
	}

	const at = /** @type {CustomEvent<{ at?: Anchor }>} */ (event).detail?.at ?? null;
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
	animate(grid, () => place(grid, boxes), row);
}

/**
 * Structural changes of a grid: rows left empty go, the DOM follows the positions.
 *
 * @param {Event} event
 */
function onChange(event) {
	const container = event.target;
	const grid =
		container instanceof HTMLElement && container.matches('.cms-blocks-editor.is-grid')
			? /** @type {HTMLElement | null} */ (container.querySelector(':scope > .grid'))
			: null;

	if (!grid || rowsOf(grid).some((row) => number(row, 'col') === 0)) {
		return;
	}

	animate(grid, () => {
		place(grid, compact(snapshot(grid)));

		if (reorder(grid)) {
			renumber(/** @type {HTMLElement} */ (container));
		}
	});
}

/**
 * @param {HTMLElement} grid
 * @returns {boolean}
 */
function locked(grid) {
	return grid.closest('[data-readonly="true"]') !== null;
}

/**
 * Move up and down go one row: the block lands there and pushes what it meets.
 *
 * @param {MouseEvent} event
 */
function onMove(event) {
	const mover =
		event.target instanceof Element ? event.target.closest('[data-repeater-move]') : null;
	const row = /** @type {HTMLElement | null} */ (mover?.closest('[data-repeater-row]'));
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

	animate(grid, () => {
		place(
			grid,
			move(snapshot(grid), row, { col: box.col, row: to }, false, columnsOf(grid).columns),
		);
		commit(grid);
	});
}

/**
 * The column and row numbers in a block's dialog move it like a drop.
 *
 * @param {Event} event
 */
function onInput(event) {
	const control = event.target;
	const key =
		control instanceof HTMLInputElement ? control.getAttribute('data-layout-input') : null;
	const row =
		control instanceof HTMLElement
			? /** @type {HTMLElement | null} */ (control.closest('[data-repeater-row]'))
			: null;
	const grid = row && placed(row) ? gridOf(row) : null;

	if (!(control instanceof HTMLInputElement) || (key !== 'col' && key !== 'row') || !row || !grid) {
		return;
	}

	const value = Number(control.value);

	if (control.value === '' || !(value >= 1)) {
		return;
	}

	const box = boxOf(row);

	animate(grid, () =>
		place(
			grid,
			move(snapshot(grid), row, { ...box, [key]: value }, false, columnsOf(grid).columns),
		),
	);

	if (event.type === 'change') {
		commit(grid);
	}
}

/**
 * @typedef {object} Drag
 * @property {number} pointer
 * @property {HTMLElement} grip
 * @property {HTMLElement} row
 * @property {HTMLElement} grid
 * @property {number} x
 * @property {number} y
 * @property {boolean} started
 * @property {Boxes<HTMLElement>} start
 * @property {Edges} cols
 * @property {Edges} rows
 * @property {{ left: number; top: number }} inset The grid's content box from its border box: the tracks start there.
 * @property {HTMLElement} scroller
 * @property {{ x: number; y: number }} at The last pointer position, for the target while the scroller moves.
 * @property {number} frame
 * @property {{ col: number; row: number }} grab
 * @property {{ x: number; y: number }} hold Where the pointer holds the lifted block, from its top left corner.
 * @property {HTMLElement | null} slot
 * @property {string} target
 */

const THRESHOLD = 4;
const BAND = 10;
// Rows below the grid have no height yet; the pointer there counts in this.
const PROBE = 96;
// How close to a scroller's edge the drag scrolls it, and its speed there per frame.
const EDGE = 56;
const SPEED = 18;

/** @type {Drag | null} */
let drag = null;

/**
 * @param {Edges} edges
 * @param {number} offset
 * @param {number} fallback
 * @returns {number}
 */
function cellOf(edges, offset, fallback) {
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

/**
 * The line between two rows the pointer sits on, as the row a new one would take.
 *
 * @param {Edges} edges
 * @param {number} offset
 * @returns {number | null}
 */
function lineOf(edges, offset) {
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

/**
 * @param {HTMLElement} grid
 * @returns {Pick<Drag, 'cols' | 'rows' | 'inset'>}
 */
export function geometry(grid) {
	const style = getComputedStyle(grid);

	return {
		cols: tracks(style.gridTemplateColumns, parseFloat(style.columnGap) || 0),
		rows: tracks(style.gridTemplateRows, parseFloat(style.rowGap) || 0),
		inset: {
			left: grid.clientLeft + (parseFloat(style.paddingLeft) || 0),
			top: grid.clientTop + (parseFloat(style.paddingTop) || 0),
		},
	};
}

/**
 * The pointer in the grid's content box, wherever the grid has scrolled to.
 *
 * @param {Drag} current
 * @param {number} x
 * @param {number} y
 * @returns {{ x: number; y: number }}
 */
function local(current, x, y) {
	const rect = current.grid.getBoundingClientRect();

	return { x: x - rect.left - current.inset.left, y: y - rect.top - current.inset.top };
}

/**
 * The nearest ancestor that scrolls vertically; the document otherwise.
 *
 * @param {HTMLElement} element
 * @returns {HTMLElement}
 */
function scrollerOf(element) {
	for (let node = element.parentElement; node; node = node.parentElement) {
		const { overflowY } = getComputedStyle(node);

		if ((overflowY === 'auto' || overflowY === 'scroll') && node.scrollHeight > node.clientHeight) {
			return node;
		}
	}

	return /** @type {HTMLElement | null} */ (document.scrollingElement) ?? document.documentElement;
}

/**
 * Pixels to scroll this frame: none away from the edges, full speed at or past them.
 *
 * @param {Drag} current
 * @returns {number}
 */
function pace(current) {
	const rect =
		current.scroller === document.scrollingElement
			? null
			: current.scroller.getBoundingClientRect();
	const top = Math.max(0, rect?.top ?? 0);
	const bottom = Math.min(innerHeight, rect?.bottom ?? innerHeight);
	const { y } = current.at;

	if (y < top + EDGE) {
		return -SPEED * Math.min(1, (top + EDGE - y) / EDGE);
	}

	if (y > bottom - EDGE) {
		return SPEED * Math.min(1, (y - bottom + EDGE) / EDGE);
	}

	return 0;
}

function scroll() {
	if (!drag?.started) {
		return;
	}

	const current = drag;
	const speed = pace(current);
	const before = current.scroller.scrollTop;

	current.frame = 0;

	if (speed === 0) {
		return;
	}

	current.scroller.scrollTop = before + speed;

	if (current.scroller.scrollTop !== before) {
		track(current);
	}

	current.frame = requestAnimationFrame(scroll);
}

/**
 * @param {PointerEvent} event
 */
function onPointerDown(event) {
	const grip =
		event.target instanceof Element
			? /** @type {HTMLElement | null} */ (event.target.closest('[data-repeater-grip]'))
			: null;
	const row = /** @type {HTMLElement | null} */ (grip?.closest('[data-repeater-row]'));
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
		scroller: scrollerOf(grid),
		at: { x: event.clientX, y: event.clientY },
		frame: 0,
		grab: { col: 0, row: 0 },
		hold: { x: 0, y: 0 },
		slot: null,
		target: '',
	};
	grip.setPointerCapture?.(event.pointerId);
	event.preventDefault();
}

/**
 * The slot stands in for the lifted block, as tall as it was.
 *
 * @param {HTMLElement} slot
 * @param {Box} box
 */
function fit(slot, box) {
	slot.style.gridColumn = `${box.col} / span ${box.colspan}`;
	slot.style.gridRow = `${box.row} / span ${box.rowspan}`;
}

/**
 * A split's parts sit on the grid's tracks through subgrid, which a
 * lifted block leaves: it takes the tracks it spans along, as measured
 * when the gesture began, rows included, which its neighbours may have
 * sized. A subgrid's border and padding narrow its edge tracks.
 *
 * @param {Drag} current
 * @param {Box} box
 */
function freeze(current, box) {
	const { row } = current;

	if (!row.matches('.is-split')) {
		return;
	}

	const style = getComputedStyle(row);
	/**
	 * @param {string} border
	 * @param {string} padding
	 */
	const inset = (border, padding) => parseFloat(border) + parseFloat(padding);
	/**
	 * @param {Edges} edges
	 * @param {number} from
	 * @param {number} count
	 * @param {number} start
	 * @param {number} end
	 */
	const span = (edges, from, count, start, end) => {
		const sizes = edges.starts
			.slice(from - 1, from - 1 + count)
			.map((at, index) => /** @type {number} */ (edges.ends[from - 1 + index]) - at);

		/** @type {number} */ (sizes[0]) -= start;
		/** @type {number} */ (sizes[sizes.length - 1]) -= end;

		return sizes.map((size) => `${size}px`).join(' ');
	};

	row.style.gridTemplateColumns = span(
		current.cols,
		box.col,
		box.colspan,
		inset(style.borderInlineStartWidth, style.paddingInlineStart),
		inset(style.borderInlineEndWidth, style.paddingInlineEnd),
	);
	row.style.gridTemplateRows = span(
		current.rows,
		box.row,
		box.rowspan,
		inset(style.borderBlockStartWidth, style.paddingBlockStart),
		inset(style.borderBlockEndWidth, style.paddingBlockEnd),
	);
}

/**
 * The lifted block, positioned in the grid's box, under the pointer.
 *
 * @param {Drag} current
 * @param {number} x
 * @param {number} y
 */
function follow(current, x, y) {
	const rect = current.grid.getBoundingClientRect();
	const left = x - rect.left - current.grid.clientLeft - current.hold.x;
	const top = y - rect.top - current.grid.clientTop - current.hold.y;

	current.row.style.transform = `translate(${left}px, ${top}px)`;
}

/**
 * @param {Drag} current
 */
function begin(current) {
	const box = boxOf(current.row);
	const { x, y } = local(current, current.x, current.y);
	const rect = current.row.getBoundingClientRect();
	const slot = document.createElement('div');

	current.started = true;
	current.start = snapshot(current.grid);
	current.grab = {
		col: cellOf(current.cols, x, PROBE) - box.col,
		row: cellOf(current.rows, y, PROBE) - box.row,
	};
	current.hold = { x: current.x - rect.left, y: current.y - rect.top };
	freeze(current, box);
	slot.className = 'landing';
	slot.setAttribute('aria-hidden', 'true');
	slot.style.minHeight = `${rect.height}px`;
	fit(slot, box);
	current.slot = slot;
	current.grid.append(slot);
	current.row.style.width = `${rect.width}px`;
	current.row.style.height = `${rect.height}px`;
	current.row.classList.add('is-lifted');
	/** @type {HTMLElement} */ (current.grid.parentElement).classList.add('is-moving');
	follow(current, current.x, current.y);
}

/**
 * The lifted block back in the grid, sliding from where it was held.
 *
 * @param {Drag} current
 * @param {Boxes<HTMLElement>} boxes
 * @param {() => void} done
 */
function land(current, boxes, done) {
	const { row, grid, slot } = current;

	animate(grid, () => {
		slot?.remove();
		row.classList.remove('is-lifted');
		row.style.removeProperty('transform');
		row.style.removeProperty('width');
		row.style.removeProperty('height');
		row.style.removeProperty('grid-template-columns');
		row.style.removeProperty('grid-template-rows');
		/** @type {HTMLElement} */ (grid.parentElement).classList.remove('is-moving');
		place(grid, boxes);
		done();
	});
}

/**
 * @param {PointerEvent} event
 */
function onPointerMove(event) {
	if (!drag || drag.pointer !== event.pointerId) {
		return;
	}

	if (!drag.started) {
		if (Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < THRESHOLD) {
			return;
		}

		begin(drag);
	}

	drag.at = { x: event.clientX, y: event.clientY };
	track(drag);

	if (!drag.frame && pace(drag) !== 0) {
		drag.frame = requestAnimationFrame(scroll);
	}
}

/**
 * The lifted block under the pointer, and the canvas as it would be with it dropped there.
 *
 * @param {Drag} current
 */
function track(current) {
	follow(current, current.at.x, current.at.y);

	const { x, y } = local(current, current.at.x, current.at.y);
	const line = lineOf(current.rows, y);
	const col = cellOf(current.cols, x, PROBE) - current.grab.col;
	const row = line ?? cellOf(current.rows, y, PROBE) - current.grab.row;
	const target = `${col}/${row}/${line !== null}`;

	if (target === current.target) {
		return;
	}

	const next = move(
		current.start,
		current.row,
		{ col, row },
		line !== null,
		columnsOf(current.grid).columns,
	);

	current.target = target;
	animate(
		current.grid,
		() => {
			place(current.grid, next);
			fit(/** @type {HTMLElement} */ (current.slot), /** @type {Box} */ (next.get(current.row)));
		},
		current.row,
	);
}

/**
 * @param {boolean} keep
 */
function finish(keep) {
	if (!drag) {
		return;
	}

	const current = drag;
	const { grip, row, grid, started, start } = current;

	drag = null;
	cancelAnimationFrame(current.frame);

	if (!started) {
		grip.focus();

		return;
	}

	if (!keep) {
		land(current, start, () => {});

		return;
	}

	land(current, snapshot(grid), () => commit(grid));
	focusRow(row);
}

/**
 * @param {PointerEvent} event
 */
function onPointerUp(event) {
	if (drag && drag.pointer === event.pointerId) {
		finish(true);
	}
}

/**
 * @param {Event} event
 */
function onLostCapture(event) {
	if (drag && event.target === drag.grip) {
		finish(true);
	}
}

/**
 * @param {KeyboardEvent} event
 */
function onKeyDown(event) {
	if (drag?.started && event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		finish(false);
	}
}

/** @returns {() => void} */
export function install() {
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

	return () => {
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

		if (drag) {
			cancelAnimationFrame(drag.frame);
		}

		drag = null;
	};
}
