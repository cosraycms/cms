// Ghost blocks: the empty cells of a multi-column canvas, shown as
// buttons that insert a block filling them exactly. The blocks there are
// placed by position (see the placement behavior), so the free cells
// follow from the stored positions alone; every free run becomes a
// button placed by explicit grid lines, absolutely positioned so it
// takes part in neither placement nor track sizing.
//
// A field with one block type stamps it on the click. Otherwise the
// click opens the field's own picker at the ghost, and the ghost stays
// armed until that menu closes: a choice or the catalog picked from it
// inserts at the ghost's spot instead of appending.

/** @import { Adder } from './pick.js' */
/** @import { Box } from './placement.js' */
/** @import { Insertion } from './repeater.js' */

import { icon } from '../lib/icons.js';
import { open as openCatalog } from './block-catalog.js';
import { adder, arm } from './pick.js';
import { columnsOf, gaps, placed, snapshot } from './placement.js';
import { insert, insertion } from './repeater.js';

const FILLS = /** @type {WeakMap<HTMLElement, Box>} */ (new WeakMap());

/**
 * @param {HTMLElement} grid
 * @returns {HTMLElement[]}
 */
function ghosts(grid) {
	return Array.from(
		/** @type {NodeListOf<HTMLElement>} */ (grid.querySelectorAll(':scope > [data-ghost]')),
	);
}

/**
 * @param {HTMLElement} grid
 * @returns {HTMLElement[]}
 */
function rowsOf(grid) {
	return Array.from(
		/** @type {NodeListOf<HTMLElement>} */ (grid.querySelectorAll(':scope > [data-repeater-row]')),
	);
}

/**
 * @param {Box[]} fills
 * @returns {string}
 */
function key(fills) {
	return fills.map((fill) => `${fill.row}/${fill.col}/${fill.rowspan}/${fill.colspan}`).join(';');
}

/**
 * The free runs of a grid whose blocks all have their position; none before.
 *
 * @param {HTMLElement} grid
 * @returns {Box[]}
 */
function measure(grid) {
	const rows = rowsOf(grid);

	if (!rows.every(placed)) {
		return [];
	}

	const { columns, min } = columnsOf(grid);

	return gaps(snapshot(grid).values(), columns, min);
}

/**
 * @param {HTMLElement} grid
 * @param {HTMLElement} container
 * @param {Adder} adder
 * @param {Box[]} fills
 */
function render(grid, container, adder, fills) {
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
		ghost.style.gridRow = `${fill.row} / span ${fill.rowspan}`;
		ghost.style.gridColumn = `${fill.col} / span ${fill.colspan}`;
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
		/** @type {HTMLElement | null} */ (
			container.querySelector(':scope > [data-repeater-footer] > button')
		)?.focus();
	}
}

/**
 * The insertion a ghost stands for: a block stamped at the gap, sized to it.
 *
 * @param {HTMLElement} ghost
 * @returns {Insertion | null}
 */
function context(ghost) {
	const fill = FILLS.get(ghost);
	const base = insertion(ghost);

	if (!fill || !base) {
		return null;
	}

	return {
		...base,
		at: null,
		prepare(clone) {
			/** @type {Array<[string, number]>} */
			const layout = [
				['colspan', fill.colspan],
				['rowspan', fill.rowspan],
				['col', fill.col],
				['row', fill.row],
			];

			for (const [dimension, value] of layout) {
				const input = /** @type {HTMLInputElement | null} */ (
					clone.querySelector(`input[data-layout="${dimension}"]`)
				);

				if (input) {
					input.value = String(value);
				}
			}
		},
	};
}

// Runs in the capture phase after the menu library's own handler, which
// has already opened the picker at a clicked ghost.
/**
 * @param {MouseEvent} event
 */
function onClick(event) {
	const ghost =
		event.target instanceof Element
			? /** @type {HTMLElement | null} */ (event.target.closest('[data-ghost]'))
			: null;
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

/**
 * Drawn over the grid, not placed on it: ghosts and resize guides.
 *
 * @param {HTMLElement} node
 * @returns {boolean}
 */
function overlay(node) {
	return node.hasAttribute('data-ghost') || node.hasAttribute('data-guide');
}

/**
 * @param {HTMLElement} grid
 * @param {HTMLElement} container
 * @param {Adder} adder
 * @returns {{ refresh(): void; dispose(): void }}
 */
function watch(grid, container, adder) {
	let frame = 0;
	let last = '';

	function schedule() {
		if (!frame) {
			frame = requestAnimationFrame(refresh);
		}
	}

	function refresh() {
		frame = 0;

		// A dragged or resized row is not where it will end up; the change
		// after the gesture looks again.
		if (grid.closest('.is-resizing, .is-moving')) {
			return;
		}

		const fills = measure(grid);
		const next = key(fills);

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
						(node) => !(node instanceof HTMLElement && overlay(node)),
					)
				: record.target instanceof HTMLElement &&
					record.target.parentElement === grid &&
					!overlay(record.target),
		);

		if (structural) {
			schedule();
		}
	});

	observer.observe(grid, {
		childList: true,
		attributes: true,
		attributeFilter: ['style', 'data-placed'],
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

/** @returns {() => void} */
export function install() {
	const grids = /** @type {Map<HTMLElement, ReturnType<typeof watch>>} */ (new Map());

	function scan() {
		for (const [grid, watcher] of grids) {
			if (!grid.isConnected) {
				watcher.dispose();
				grids.delete(grid);
			}
		}

		/** @type {NodeListOf<HTMLElement>} */ (
			document.querySelectorAll('.cms-blocks-editor.is-grid > .grid')
		).forEach((grid) => {
			const container = grid.parentElement;
			const how = container && adder(container);

			if (!grids.has(grid) && how && !grid.closest('[data-readonly="true"]')) {
				grids.set(grid, watch(grid, container, how));
			}
		});
	}

	/**
	 * @param {Event} event
	 */
	function changed(event) {
		const grid =
			event.target instanceof Element
				? /** @type {HTMLElement | null} */ (event.target.closest('.cms-blocks-editor > .grid'))
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
