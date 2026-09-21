import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/block-corners';

type Box = { x: number; y: number; width: number; height: number };
const observers: Observer[] = [];
let frames: Map<number, FrameRequestCallback>;
let nextFrame: number;
let uninstall: (() => void) | undefined;

class Observer {
	observe = vi.fn();
	unobserve = vi.fn();
	disconnect = vi.fn();

	constructor(private callback: ResizeObserverCallback) {
		observers.push(this);
	}

	notify(): void {
		this.callback([], this as unknown as ResizeObserver);
	}
}

beforeEach(() => {
	frames = new Map();
	nextFrame = 0;
	vi.stubGlobal('ResizeObserver', Observer);
	vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
		frames.set(++nextFrame, callback);
		return nextFrame;
	});
	vi.stubGlobal('cancelAnimationFrame', (id: number) => frames.delete(id));
});

afterEach(() => {
	uninstall?.();
	uninstall = undefined;
	document.body.replaceChildren();
	observers.length = 0;
	vi.unstubAllGlobals();
});

function measure(element: HTMLElement, box: Box): void {
	Object.defineProperties(element, {
		offsetLeft: { configurable: true, get: () => box.x },
		offsetTop: { configurable: true, get: () => box.y },
		offsetWidth: { configurable: true, get: () => box.width },
		offsetHeight: { configurable: true, get: () => box.height },
	});
}

function fixture(boxes: Box[], width = 400, height = 204) {
	const editor = document.createElement('div');
	editor.className = 'cms-blocks-editor';
	const grid = document.createElement('div');
	grid.className = 'grid';
	const size = { width, height };
	Object.defineProperties(grid, {
		clientWidth: { get: () => size.width },
		clientHeight: { get: () => size.height },
	});
	const rows = boxes.map((box) => {
		const row = document.createElement('div');
		row.className = 'block';
		measure(row, box);
		grid.append(row);
		return row;
	});
	editor.append(grid);
	document.body.append(editor);
	return { editor, grid, rows, size };
}

async function paint(): Promise<void> {
	await Promise.resolve();
	const callbacks = [...frames.values()];
	frames.clear();
	callbacks.forEach((callback) => callback(0));
}

describe('block grid corners', () => {
	it('uses occupied corners rather than document order for spanning and indented blocks', () => {
		const { rows } = fixture([
			{ x: 0, y: 0, width: 198, height: 204 },
			{ x: 202, y: 0, width: 198, height: 100 },
			{ x: 202, y: 104, width: 198, height: 100 },
		]);
		const indented = fixture([{ x: 202, y: 0, width: 198, height: 204 }]);
		uninstall = install();

		expect(rows.map((row) => row.dataset.corners)).toEqual([
			'top-left bottom-left',
			'top-right',
			'bottom-right',
		]);
		expect(indented.rows[0].dataset.corners).toBe('top-right bottom-right');
	});

	it('rounds a single full-width block and tolerates integer layout rounding', () => {
		const { rows } = fixture([{ x: 0, y: 0, width: 399, height: 204 }]);
		uninstall = install();

		expect(rows[0].dataset.corners).toBe('top-left top-right bottom-left bottom-right');
	});

	it('updates all affected blocks when content growth changes the grid height', async () => {
		const boxes = [
			{ x: 0, y: 0, width: 198, height: 204 },
			{ x: 202, y: 0, width: 198, height: 204 },
		];
		const { rows, size } = fixture(boxes);
		uninstall = install();
		boxes[0].height = 308;
		size.height = 308;
		observers[0].notify();
		await paint();

		expect(rows[0].dataset.corners).toBe('top-left bottom-left');
		expect(rows[1].dataset.corners).toBe('top-right');
	});

	it('follows layout edits and reordering even when the grid size stays unchanged', async () => {
		const boxes = [
			{ x: 0, y: 0, width: 198, height: 204 },
			{ x: 202, y: 0, width: 198, height: 204 },
		];
		const { grid, rows } = fixture(boxes);
		uninstall = install();
		boxes[0].x = 202;
		boxes[1].x = 0;
		grid.prepend(rows[1]);
		await paint();

		expect(rows[0].dataset.corners).toBe('top-right bottom-right');
		expect(rows[1].dataset.corners).toBe('top-left bottom-left');

		boxes[0].x = 250;
		boxes[0].width = 150;
		rows[0].style.setProperty('--indent', '1');
		await paint();

		expect(rows[0].dataset.corners).toBe('top-right bottom-right');
		boxes[1].x = 50;
		rows[1].style.setProperty('--indent', '1');
		await paint();
		expect(rows[1].dataset.corners).toBe('');
	});

	it('tracks added blocks and releases removed blocks', async () => {
		const { grid, rows } = fixture([{ x: 0, y: 0, width: 400, height: 204 }]);
		uninstall = install();
		const replacement = document.createElement('div');
		measure(replacement, { x: 0, y: 0, width: 400, height: 204 });
		grid.replaceChildren(replacement);
		await paint();

		expect(replacement.dataset.corners).toBe('top-left top-right bottom-left bottom-right');
		expect(observers[0].unobserve).toHaveBeenCalledWith(rows[0]);
		expect(observers[0].observe).toHaveBeenCalledWith(replacement);
	});

	it('updates hidden grids when they become visible', async () => {
		const { rows, size } = fixture([{ x: 0, y: 0, width: 400, height: 204 }], 0, 0);
		uninstall = install();
		expect(rows[0].dataset.corners).toBe('');

		size.width = 400;
		size.height = 204;
		observers[0].notify();
		await paint();
		expect(rows[0].dataset.corners).toBe('top-left top-right bottom-left bottom-right');
	});

	it('treats nested grids independently and discovers them when a row is stamped', () => {
		const outer = fixture([{ x: 0, y: 0, width: 400, height: 204 }]);
		uninstall = install();
		const inner = fixture([{ x: 202, y: 0, width: 198, height: 204 }]);
		outer.rows[0].append(inner.editor);
		outer.rows[0].dispatchEvent(new Event('repeater:stamp', { bubbles: true }));

		expect(outer.rows[0].dataset.corners).toBe('top-left top-right bottom-left bottom-right');
		expect(inner.rows[0].dataset.corners).toBe('top-right bottom-right');
		expect(observers).toHaveLength(2);
	});

	it('releases swapped-out grids and cancels pending work on uninstall', async () => {
		const previous = fixture([{ x: 0, y: 0, width: 400, height: 204 }]);
		uninstall = install();
		previous.editor.remove();
		const next = fixture([{ x: 0, y: 0, width: 400, height: 204 }]);
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(observers[0].disconnect).toHaveBeenCalledOnce();
		expect(next.rows[0].dataset.corners).toBe('top-left top-right bottom-left bottom-right');
		observers[1].notify();
		uninstall();
		uninstall = undefined;
		expect(observers[1].disconnect).toHaveBeenCalledOnce();
		expect(frames.size).toBe(0);

		next.rows[0].remove();
		await paint();
		expect(frames.size).toBe(0);
	});
});
