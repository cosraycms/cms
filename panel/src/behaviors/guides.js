// The grid shown while a block's edge drags. Rows size to their content,
// so a block that takes another row often keeps its own height: the
// row line moves up through it instead, and the neighbour that matched
// its height shrinks. The guides show that: the block's cells with the
// lines between them, and the free cells around it, as the grid has
// them now. A label at the pointer names the value the edge sets.

/** @import { Box, Boxes } from './placement.js' */

import { gaps } from './placement.js';

/**
 * @param {HTMLElement} grid
 * @param {Box} box
 * @param {string} kind
 */
function cell(grid, box, kind) {
	const guide = document.createElement('div');

	guide.className = `guide is-${kind}`;
	guide.dataset.guide = '';
	guide.setAttribute('aria-hidden', 'true');
	guide.style.gridRow = `${box.row} / span ${box.rowspan}`;
	guide.style.gridColumn = `${box.col} / span ${box.colspan}`;
	grid.append(guide);
}

/**
 * @param {HTMLElement} grid
 */
export function clear(grid) {
	grid.querySelectorAll(':scope > [data-guide]').forEach((guide) => guide.remove());
}

/**
 * A run of cells cut into single rows or single columns.
 *
 * @param {Box} box
 * @param {'rows' | 'columns'} across
 * @returns {Box[]}
 */
function cut(box, across) {
	return across === 'rows'
		? Array.from({ length: box.rowspan }, (_, index) => ({
				...box,
				row: box.row + index,
				rowspan: 1,
			}))
		: Array.from({ length: box.colspan }, (_, index) => ({
				...box,
				col: box.col + index,
				colspan: 1,
			}));
}

/**
 * The block and every free run of cells, cut along the dimension its
 * edge changes — into rows for the bottom edge, into columns for a side —
 * so the lines it moves along run through the empty space as well.
 *
 * @param {HTMLElement} grid
 * @param {HTMLElement} row
 * @param {Boxes<HTMLElement>} boxes
 * @param {number} columns
 * @param {'rows' | 'columns'} across
 */
export function draw(grid, row, boxes, columns, across) {
	const own = boxes.get(row);

	clear(grid);

	if (!own) {
		return;
	}

	for (const part of cut(own, across)) {
		cell(grid, part, 'own');
	}

	for (const free of gaps(boxes.values(), columns, 1)) {
		for (const part of cut(free, across)) {
			cell(grid, part, 'free');
		}
	}
}

/**
 * The label next to the pointer, in the grid's box so it scrolls along.
 *
 * @param {HTMLElement} grid
 * @param {string} text
 * @param {number} x
 * @param {number} y
 */
export function label(grid, text, x, y) {
	let tag = /** @type {HTMLElement | null} */ (grid.querySelector(':scope > .guide-label'));

	if (!tag) {
		tag = document.createElement('div');
		tag.className = 'guide-label';
		tag.dataset.guide = '';
		tag.setAttribute('aria-hidden', 'true');
		grid.append(tag);
	}

	const rect = grid.getBoundingClientRect();

	tag.textContent = text;
	tag.style.left = `${x - rect.left - grid.clientLeft}px`;
	tag.style.top = `${y - rect.top - grid.clientTop}px`;
}
