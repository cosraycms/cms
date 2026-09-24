// The grid shown while a block's edge drags. Rows size to their content,
// so a block that takes another row often keeps its own height: the
// row line moves up through it instead, and the neighbour that matched
// its height shrinks. The guides show that: the block's cells with the
// lines between them, and the free cells around it, as the grid has
// them now. A label at the pointer names the value the edge sets.

import { gaps, type Box, type Boxes } from './placement';

function cell(grid: HTMLElement, box: Box, kind: string): void {
	const guide = document.createElement('div');

	guide.className = `guide is-${kind}`;
	guide.dataset.guide = '';
	guide.setAttribute('aria-hidden', 'true');
	guide.style.gridRow = `${box.row} / span ${box.rowspan}`;
	guide.style.gridColumn = `${box.col} / span ${box.colspan}`;
	grid.append(guide);
}

export function clear(grid: HTMLElement): void {
	grid.querySelectorAll(':scope > [data-guide]').forEach((guide) => guide.remove());
}

/** A run of cells cut into single rows or single columns. */
function cut(box: Box, across: 'rows' | 'columns'): Box[] {
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
 */
export function draw(
	grid: HTMLElement,
	row: HTMLElement,
	boxes: Boxes<HTMLElement>,
	columns: number,
	across: 'rows' | 'columns',
): void {
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

/** The label next to the pointer, in the grid's box so it scrolls along. */
export function label(grid: HTMLElement, text: string, x: number, y: number): void {
	let tag = grid.querySelector<HTMLElement>(':scope > .guide-label');

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
