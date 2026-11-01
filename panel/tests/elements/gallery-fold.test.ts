import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { galleryFold } from '../../src/elements/media/gallery-fold.js';

vi.mock('../../src/lib/locale.js', () => ({
	__: (id: string, values?: Record<string, unknown>) => (values ? `${id} ${values.count}` : id),
}));

let observed: ResizeObserverCallback;

beforeEach(() => {
	vi.stubGlobal(
		'ResizeObserver',
		class {
			constructor(callback: ResizeObserverCallback) {
				observed = callback;
			}
			observe() {}
			disconnect() {}
		},
	);
	vi.stubGlobal('matchMedia', () => ({ matches: true }));
});

afterEach(() => {
	vi.unstubAllGlobals();
	document.body.replaceChildren();
});

function gallery(count: number, columns: number, expanded = false) {
	const viewport = document.createElement('div');
	const tiles = document.createElement('div');
	const tileHeight = 100;

	for (let index = 0; index < count; index++) {
		const tile = document.createElement('div');
		const row = Math.floor(index / Math.max(columns, 1));
		tile.getBoundingClientRect = () => ({ bottom: (row + 1) * tileHeight }) as DOMRect;
		tiles.append(tile);
	}

	viewport.getBoundingClientRect = () => ({ top: 0, height: 0 }) as DOMRect;
	viewport.append(tiles);
	const changed = vi.fn();
	const fold = galleryFold({ viewport, tiles, expanded, changed, columns: () => columns });
	document.body.append(viewport, fold.element);
	observed([], {} as ResizeObserver);

	return { viewport, tiles, toggle: fold.element, changed, fold };
}

const inert = (tiles: HTMLElement) =>
	Array.from(tiles.children).map((tile) => (tile as HTMLElement).inert);

describe('gallery fold', () => {
	it('stays out of the way while the images fit into two rows', () => {
		const { viewport, tiles, toggle } = gallery(8, 4);

		expect(toggle.hidden).toBe(true);
		expect(viewport.style.height).toBe('');
		expect(inert(tiles)).not.toContain(true);
	});

	it('cuts after the second row and takes the rest out of the tab order', () => {
		const { viewport, tiles, toggle } = gallery(9, 4);

		expect(toggle.hidden).toBe(false);
		expect(toggle.textContent).toBe('image:show-all 9');
		expect(toggle.getAttribute('aria-expanded')).toBe('false');
		expect(viewport.style.height).toBe('200px');
		expect(viewport.classList.contains('is-clipped')).toBe(true);
		expect(inert(tiles)).toEqual([...Array(8).fill(false), true]);
	});

	it('follows the column count when the width changes', () => {
		let columns = 4;
		const viewport = document.createElement('div');
		const tiles = document.createElement('div');

		for (let index = 0; index < 6; index++) tiles.append(document.createElement('div'));

		viewport.append(tiles);
		const fold = galleryFold({
			viewport,
			tiles,
			expanded: false,
			changed: vi.fn(),
			columns: () => columns,
		});
		observed([], {} as ResizeObserver);
		expect(fold.element.hidden).toBe(true);

		columns = 2;
		observed([], {} as ResizeObserver);
		expect(fold.element.hidden).toBe(false);
		expect(inert(tiles)).toEqual([false, false, false, false, true, true]);
	});

	it('does nothing while the grid has no layout', () => {
		const { viewport, toggle } = gallery(30, 0);

		expect(toggle.hidden).toBe(true);
		expect(viewport.style.height).toBe('');
	});

	it('unfolds and folds again, reporting the state', () => {
		const { viewport, tiles, toggle, changed } = gallery(9, 4);

		toggle.click();

		expect(changed).toHaveBeenLastCalledWith(true);
		expect(toggle.textContent).toBe('image:show-fewer');
		expect(toggle.getAttribute('aria-expanded')).toBe('true');
		expect(viewport.style.height).toBe('');
		expect(viewport.classList.contains('is-clipped')).toBe(false);
		expect(inert(tiles)).not.toContain(true);

		toggle.click();

		expect(changed).toHaveBeenLastCalledWith(false);
		expect(viewport.style.height).toBe('200px');
	});

	it('starts unfolded when the owner says so', () => {
		const { viewport, toggle } = gallery(9, 4, true);

		expect(toggle.getAttribute('aria-expanded')).toBe('true');
		expect(viewport.style.height).toBe('');
	});

	it('applies the cut to the new order after a drag', () => {
		const { tiles, fold } = gallery(9, 4);
		const last = tiles.lastElementChild as HTMLElement;

		tiles.prepend(last);
		fold.refresh();

		expect(last.inert).toBe(false);
		expect((tiles.lastElementChild as HTMLElement).inert).toBe(true);
	});
});
