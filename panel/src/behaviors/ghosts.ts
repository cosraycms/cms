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

import { icon } from '$lib/icons';
import { open as openCatalog } from './block-catalog';
import { adder, arm, type Adder } from './pick';
import { columnsOf, gaps, placed, snapshot, type Box } from './placement';
import { insert, insertion, type Insertion } from './repeater';

const FILLS = new WeakMap<HTMLElement, Box>();

function ghosts(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-ghost]'));
}

function rowsOf(grid: HTMLElement): HTMLElement[] {
	return Array.from(grid.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]'));
}

function key(fills: Box[]): string {
	return fills.map((fill) => `${fill.row}/${fill.col}/${fill.rowspan}/${fill.colspan}`).join(';');
}

/** The free runs of a grid whose blocks all have their position; none before. */
function measure(grid: HTMLElement): Box[] {
	const rows = rowsOf(grid);

	if (!rows.every(placed)) {
		return [];
	}

	const { columns, min } = columnsOf(grid);

	return gaps(snapshot(grid).values(), columns, min);
}

function render(grid: HTMLElement, container: HTMLElement, adder: Adder, fills: Box[]): void {
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
		container.querySelector<HTMLElement>(':scope > [data-repeater-footer] > button')?.focus();
	}
}

/** The insertion a ghost stands for: a block stamped at the gap, sized to it. */
function context(ghost: HTMLElement): Insertion | null {
	const fill = FILLS.get(ghost);
	const base = insertion(ghost);

	if (!fill || !base) {
		return null;
	}

	return {
		...base,
		at: null,
		prepare(clone) {
			const layout: Array<[string, number]> = [
				['colspan', fill.colspan],
				['rowspan', fill.rowspan],
				['col', fill.col],
				['row', fill.row],
			];

			for (const [dimension, value] of layout) {
				const input = clone.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);

				if (input) {
					input.value = String(value);
				}
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
