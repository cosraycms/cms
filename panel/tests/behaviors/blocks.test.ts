// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/blocks.php renders: a container with the grid
// bounds, a row with its hidden layout inputs and the number inputs of
// its settings dialog.

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
	bounds,
	clamp,
	grid,
	install,
	MAX_ROWSPAN,
	parseDimension,
	parseKey,
	pitch,
	ratchet,
	read,
	resize,
	set,
	shift,
	write,
} from '../../src/behaviors/blocks';

const NAME = 'content[body][value][de]';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function number(dimension: string, value: number, low: number, high: number): string {
	return `<input
		type="number"
		data-layout-input="${dimension}"
		value="${value}"
		min="${low}"
		max="${high}">`;
}

function editor(
	layout = { colspan: 6, rowspan: 1, indent: 2 },
	bounds = { columns: 12, min: 2 },
): {
	row: HTMLElement;
	input: (dimension: string) => HTMLInputElement;
	control: (dimension: string) => HTMLInputElement;
} {
	document.body.innerHTML = `<div
		data-repeater
		data-name="${NAME}"
		data-id="field-body-de"
		data-columns="${bounds.columns}"
		data-min="${bounds.min}"
		style="--columns: ${bounds.columns}">
		<div data-repeater-list>
			<div
				class="block"
				data-repeater-row
				data-indent="${layout.indent}"
				style="--colspan: ${layout.colspan}; --rowspan: ${layout.rowspan}; --indent: ${layout.indent}">
				<input type="hidden" name="${NAME}[0][uid]" value="b1">
				<input type="hidden" name="${NAME}[0][layout][colspan]" value="${layout.colspan}" data-layout="colspan">
				<input type="hidden" name="${NAME}[0][layout][rowspan]" value="${layout.rowspan}" data-layout="rowspan">
				<input type="hidden" name="${NAME}[0][layout][indent]" value="${layout.indent}" data-layout="indent">
				<div class="chrome"><span class="grip" data-repeater-grip tabindex="0"></span></div>
				<dialog data-meta>
					<div class="layout">
						${number('colspan', layout.colspan, bounds.min, bounds.columns - layout.indent)}
						${number('rowspan', layout.rowspan, 1, MAX_ROWSPAN)}
						${number('indent', layout.indent, 0, bounds.columns - layout.colspan)}
					</div>
				</dialog>
			</div>
		</div>
	</div>`;

	const row = document.querySelector<HTMLElement>('[data-repeater-row]');

	if (!row) {
		throw new Error('row missing');
	}

	const one = (selector: string): HTMLInputElement => {
		const found = row.querySelector<HTMLInputElement>(selector);

		if (!found) {
			throw new Error(`missing ${selector}`);
		}

		return found;
	};

	return {
		row,
		input: (dimension) => one(`input[data-layout="${dimension}"]`),
		control: (dimension) => one(`input[data-layout-input="${dimension}"]`),
	};
}

/** Types into a number input; committing is what leaving it does. */
function type(control: HTMLInputElement, value: string, commit = false): void {
	control.value = value;
	control.dispatchEvent(new Event('input', { bubbles: true }));

	if (commit) {
		control.dispatchEvent(new Event('change', { bubbles: true }));
	}
}

describe('blocks layout numbers', () => {
	it('clamps colspan into [min, columns] and rowspan into [1, MAX_ROWSPAN]', () => {
		const twelve = grid(12, 2);

		expect(clamp({ colspan: 14, rowspan: 9, indent: 0 }, twelve)).toEqual({
			colspan: 12,
			rowspan: MAX_ROWSPAN,
			indent: 0,
		});
		expect(clamp({ colspan: 1, rowspan: 0, indent: 0 }, twelve)).toEqual({
			colspan: 2,
			rowspan: 1,
			indent: 0,
		});
		expect(clamp({ colspan: 4, rowspan: 2, indent: -1 }, twelve)).toEqual({
			colspan: 4,
			rowspan: 2,
			indent: 0,
		});
	});

	it('keeps the indent within the room the span leaves', () => {
		expect(clamp({ colspan: 8, rowspan: 1, indent: 6 }, grid(12, 2)).indent).toBe(4);
	});

	it('caps a dimension by the room the others leave instead of moving them', () => {
		const twelve = grid(12, 2);

		// Widening stops at the grid's edge; the indent never gives way.
		expect(set({ colspan: 8, rowspan: 1, indent: 4 }, 'colspan', 9, twelve)).toEqual({
			colspan: 8,
			rowspan: 1,
			indent: 4,
		});
		expect(set({ colspan: 8, rowspan: 1, indent: 4 }, 'colspan', 7, twelve)).toEqual({
			colspan: 7,
			rowspan: 1,
			indent: 4,
		});
		expect(set({ colspan: 8, rowspan: 1, indent: 4 }, 'indent', 9, twelve)).toEqual({
			colspan: 8,
			rowspan: 1,
			indent: 4,
		});
		expect(set({ colspan: 8, rowspan: 1, indent: 4 }, 'rowspan', 99, twelve).rowspan).toBe(
			MAX_ROWSPAN,
		);
	});

	it('bounds every dimension given the others', () => {
		expect(bounds({ colspan: 8, rowspan: 1, indent: 0 }, grid(12, 2))).toEqual({
			colspan: { low: 2, high: 12 },
			rowspan: { low: 1, high: MAX_ROWSPAN },
			indent: { low: 0, high: 4 },
		});
		expect(bounds({ colspan: 6, rowspan: 1, indent: 3 }, grid(12, 2)).colspan).toEqual({
			low: 2,
			high: 9,
		});
	});

	it('normalizes a degenerate grid', () => {
		expect(grid(0, 5)).toEqual({ columns: 1, min: 1 });
		expect(grid(6, 9)).toEqual({ columns: 6, min: 6 });
		expect(grid(6.7, 2.2)).toEqual({ columns: 6, min: 2 });
	});

	it('parses a dimension and rejects anything else', () => {
		expect(parseDimension('colspan')).toBe('colspan');
		expect(parseDimension('indent')).toBe('indent');
		expect(parseDimension('width')).toBeNull();
		expect(parseDimension(null)).toBeNull();
	});

	it('applies a typed value to the hidden input, the custom properties and data-indent', () => {
		const { row, input, control } = editor();

		type(control('colspan'), '7');

		expect(input('colspan').value).toBe('7');
		expect(row.style.getPropertyValue('--colspan')).toBe('7');
		expect(row.style.getPropertyValue('--reserved')).toBe('9');
		// The room the width leaves is the indent's new limit.
		expect(control('indent').max).toBe('5');

		type(control('rowspan'), '2');
		type(control('indent'), '1');

		expect(input('rowspan').value).toBe('2');
		expect(row.style.getPropertyValue('--rowspan')).toBe('2');
		expect(input('indent').value).toBe('1');
		expect(row.style.getPropertyValue('--indent')).toBe('1');
		expect(row.dataset.indent).toBe('1');
		expect(control('colspan').max).toBe('11');
		expect(read(row)).toEqual({ colspan: 7, rowspan: 2, indent: 1 });
	});

	it('caps the width at the grid edge instead of pulling the indent in', () => {
		const { input, control } = editor({ colspan: 8, rowspan: 1, indent: 4 });

		type(control('colspan'), '9', true);

		expect(input('colspan').value).toBe('8');
		expect(input('indent').value).toBe('4');
		expect(control('colspan').value).toBe('8');
	});

	it('waits for an out-of-range or half-typed value to commit', () => {
		const { input, control } = editor();

		// On the way to 10, the 1 is below the minimum of 2.
		type(control('colspan'), '1');

		expect(input('colspan').value).toBe('6');
		expect(control('colspan').value).toBe('1');

		type(control('colspan'), '10');

		expect(input('colspan').value).toBe('10');

		type(control('colspan'), '');

		expect(input('colspan').value).toBe('10');

		type(control('colspan'), '', true);

		expect(control('colspan').value).toBe('10');

		type(control('indent'), '7', true);

		expect(input('indent').value).toBe('2');
		expect(control('indent').value).toBe('2');
	});

	it('renders the layout a stamped row carries in its inputs', () => {
		const { row, input, control } = editor({ colspan: 6, rowspan: 1, indent: 2 });

		// A duplicate: the inputs were copied, the style and the dialog were not.
		input('colspan').value = '4';
		input('indent').value = '5';
		row.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(row.style.getPropertyValue('--colspan')).toBe('4');
		expect(row.style.getPropertyValue('--indent')).toBe('5');
		expect(row.style.getPropertyValue('--reserved')).toBe('9');
		expect(row.dataset.indent).toBe('5');
		expect(control('colspan').value).toBe('4');
		expect(control('indent').max).toBe('8');
	});

	it('writes a layout without touching absent parts', () => {
		const { row, input } = editor();
		row.querySelector('dialog')?.remove();

		write(row, { colspan: 3, rowspan: 4, indent: 5 }, grid(12, 2));

		expect(input('colspan').value).toBe('3');
		expect(input('rowspan').value).toBe('4');
		expect(input('indent').value).toBe('5');
		expect(row.style.getPropertyValue('--rowspan')).toBe('4');
	});

	it('ignores inputs outside a repeater row', () => {
		document.body.innerHTML = `<input type="number" data-layout-input="colspan" value="3">`;
		const stray = document.querySelector<HTMLInputElement>('input');

		expect(() => stray && type(stray, '4', true)).not.toThrow();
	});
});

describe('blocks keyboard resizing', () => {
	function press(
		target: Element,
		key: string,
		modifiers: Partial<Record<'alt' | 'shift' | 'ctrl' | 'meta', boolean>> = { alt: true },
	): KeyboardEvent {
		const event = new KeyboardEvent('keydown', {
			key,
			altKey: modifiers.alt ?? false,
			shiftKey: modifiers.shift ?? false,
			ctrlKey: modifiers.ctrl ?? false,
			metaKey: modifiers.meta ?? false,
			bubbles: true,
			cancelable: true,
		});

		target.dispatchEvent(event);

		return event;
	}

	function grip(row: HTMLElement): Element {
		const found = row.querySelector('[data-repeater-grip]');

		if (!found) {
			throw new Error('grip missing');
		}

		return found;
	}

	it('maps Alt with the arrows to the edges, Shift added for the start edge', () => {
		const key = (k: string, shift = false, alt = true): KeyboardEvent =>
			new KeyboardEvent('keydown', { key: k, altKey: alt, shiftKey: shift });

		expect(parseKey(key('ArrowRight'))).toEqual({ edge: 'end', steps: 1 });
		expect(parseKey(key('ArrowLeft'))).toEqual({ edge: 'end', steps: -1 });
		expect(parseKey(key('ArrowRight', true))).toEqual({ edge: 'start', steps: 1 });
		expect(parseKey(key('ArrowLeft', true))).toEqual({ edge: 'start', steps: -1 });
		expect(parseKey(key('ArrowDown'))).toEqual({ edge: 'bottom', steps: 1 });
		expect(parseKey(key('ArrowUp'))).toEqual({ edge: 'bottom', steps: -1 });
		expect(parseKey(key('ArrowUp', true))).toBeNull();
		expect(parseKey(key('ArrowRight', false, false))).toBeNull();
		expect(parseKey(key('Enter'))).toBeNull();
		expect(
			parseKey(new KeyboardEvent('keydown', { key: 'ArrowRight', altKey: true, ctrlKey: true })),
		).toBeNull();
	});

	it('moves the edges from the focused grip and consumes the key', () => {
		const { row, input } = editor({ colspan: 6, rowspan: 1, indent: 2 });
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);

		expect(press(grip(row), 'ArrowRight').defaultPrevented).toBe(true);
		expect(read(row)).toEqual({ colspan: 7, rowspan: 1, indent: 2 });
		expect(row.style.getPropertyValue('--colspan')).toBe('7');

		press(grip(row), 'ArrowLeft', { alt: true, shift: true });

		// The start edge moved left: the block grew into its indent.
		expect(read(row)).toEqual({ colspan: 8, rowspan: 1, indent: 1 });

		press(grip(row), 'ArrowDown');
		press(grip(row), 'ArrowDown');
		press(grip(row), 'ArrowUp');

		expect(read(row)).toEqual({ colspan: 8, rowspan: 2, indent: 1 });
		document.removeEventListener('change', count);

		expect(changes).toBe(5);
	});

	it('stops where the handles stop, without a change', () => {
		const { row } = editor({ colspan: 10, rowspan: 1, indent: 2 });
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);
		press(grip(row), 'ArrowRight');
		document.removeEventListener('change', count);

		expect(read(row)).toEqual({ colspan: 10, rowspan: 1, indent: 2 });
		expect(changes).toBe(0);
	});

	it('leaves other keys, other targets and one-column fields alone', () => {
		const { row } = editor({ colspan: 6, rowspan: 1, indent: 2 });

		expect(press(grip(row), 'ArrowRight', { alt: false }).defaultPrevented).toBe(false);
		expect(press(row, 'ArrowRight').defaultPrevented).toBe(false);
		expect(read(row)).toEqual({ colspan: 6, rowspan: 1, indent: 2 });

		const list = editor({ colspan: 1, rowspan: 1, indent: 0 }, { columns: 1, min: 1 });

		expect(press(grip(list.row), 'ArrowDown').defaultPrevented).toBe(false);
		expect(read(list.row)).toEqual({ colspan: 1, rowspan: 1, indent: 0 });
	});
});

describe('blocks resize geometry', () => {
	it('spreads the gaps over the tracks', () => {
		// 12 columns of 61px with an 11px gap fill 863px.
		expect(pitch(863, 12, 11)).toBeCloseTo(72.83, 2);
		expect(pitch(100, 0, 8)).toBe(0);
	});

	it('takes a full step before a row follows', () => {
		expect(ratchet(99, 100)).toBe(0);
		expect(ratchet(100, 100)).toBe(1);
		expect(ratchet(199, 100)).toBe(1);
		expect(ratchet(-99, 100)).toBe(0);
		expect(ratchet(-240, 100)).toBe(-2);
		expect(ratchet(500, 0)).toBe(0);
	});

	it('rounds the travelled distance to whole tracks', () => {
		expect(shift(0, 72)).toBe(0);
		expect(shift(35, 72)).toBe(0);
		expect(shift(37, 72)).toBe(1);
		expect(shift(-150, 72)).toBe(-2);
		expect(shift(100, 0)).toBe(0);
	});
});

describe('blocks edge resizing', () => {
	const grid = { columns: 12, min: 2 };
	const layout = { colspan: 6, rowspan: 1, indent: 3 };

	it('grows the end edge, leaving the indent alone', () => {
		expect(resize(layout, 'end', 2, grid)).toEqual({ colspan: 8, rowspan: 1, indent: 3 });
		// Nine columns are left beside the indent; it never gives way.
		expect(resize(layout, 'end', 9, grid)).toEqual({ colspan: 9, rowspan: 1, indent: 3 });
		expect(resize(layout, 'end', -9, grid)).toEqual({ colspan: 2, rowspan: 1, indent: 3 });
	});

	it('trades indent against span on the start edge', () => {
		expect(resize(layout, 'start', -2, grid)).toEqual({ colspan: 8, rowspan: 1, indent: 1 });
		expect(resize(layout, 'start', 3, grid)).toEqual({ colspan: 3, rowspan: 1, indent: 6 });
		// Both directions stop before the block moves: indent 0, span min.
		expect(resize(layout, 'start', -5, grid)).toEqual({ colspan: 9, rowspan: 1, indent: 0 });
		expect(resize(layout, 'start', 8, grid)).toEqual({ colspan: 2, rowspan: 1, indent: 7 });
	});

	it('keeps the reserved width while the start edge moves', () => {
		// Indent plus span is what the block takes out of the row, and the
		// start edge only redistributes it — the block cannot wrap.
		for (const steps of [-3, -1, 0, 2, 5]) {
			const next = resize(layout, 'start', steps, grid);

			expect(next.indent + next.colspan).toBe(layout.indent + layout.colspan);
		}
	});

	it('counts rows on the bottom edge', () => {
		expect(resize(layout, 'bottom', 2, grid)).toEqual({ colspan: 6, rowspan: 3, indent: 3 });
		expect(resize(layout, 'bottom', 99, grid)).toEqual({
			colspan: 6,
			rowspan: MAX_ROWSPAN,
			indent: 3,
		});
		expect(resize(layout, 'bottom', -4, grid)).toEqual({ colspan: 6, rowspan: 1, indent: 3 });
	});
});
