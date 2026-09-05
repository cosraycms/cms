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
	MAX_ROWS,
	parseDimension,
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
	layout = { span: 6, rows: 1, indent: 2 },
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
				style="--span: ${layout.span}; --rows: ${layout.rows}; --indent: ${layout.indent}">
				<input type="hidden" name="${NAME}[0][uid]" value="b1">
				<input type="hidden" name="${NAME}[0][layout][span]" value="${layout.span}" data-layout="span">
				<input type="hidden" name="${NAME}[0][layout][rows]" value="${layout.rows}" data-layout="rows">
				<input type="hidden" name="${NAME}[0][layout][indent]" value="${layout.indent}" data-layout="indent">
				<dialog data-meta>
					<div class="layout">
						${number('span', layout.span, bounds.min, bounds.columns - layout.indent)}
						${number('rows', layout.rows, 1, MAX_ROWS)}
						${number('indent', layout.indent, 0, bounds.columns - layout.span)}
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
	it('clamps span into [min, columns] and rows into [1, MAX_ROWS]', () => {
		const twelve = grid(12, 2);

		expect(clamp({ span: 14, rows: 9, indent: 0 }, twelve)).toEqual({
			span: 12,
			rows: MAX_ROWS,
			indent: 0,
		});
		expect(clamp({ span: 1, rows: 0, indent: 0 }, twelve)).toEqual({ span: 2, rows: 1, indent: 0 });
		expect(clamp({ span: 4, rows: 2, indent: -1 }, twelve)).toEqual({
			span: 4,
			rows: 2,
			indent: 0,
		});
	});

	it('keeps the indent within the room the span leaves', () => {
		expect(clamp({ span: 8, rows: 1, indent: 6 }, grid(12, 2)).indent).toBe(4);
	});

	it('caps a dimension by the room the others leave instead of moving them', () => {
		const twelve = grid(12, 2);

		// Widening stops at the grid's edge; the indent never gives way.
		expect(set({ span: 8, rows: 1, indent: 4 }, 'span', 9, twelve)).toEqual({
			span: 8,
			rows: 1,
			indent: 4,
		});
		expect(set({ span: 8, rows: 1, indent: 4 }, 'span', 7, twelve)).toEqual({
			span: 7,
			rows: 1,
			indent: 4,
		});
		expect(set({ span: 8, rows: 1, indent: 4 }, 'indent', 9, twelve)).toEqual({
			span: 8,
			rows: 1,
			indent: 4,
		});
		expect(set({ span: 8, rows: 1, indent: 4 }, 'rows', 99, twelve).rows).toBe(MAX_ROWS);
	});

	it('bounds every dimension given the others', () => {
		expect(bounds({ span: 8, rows: 1, indent: 0 }, grid(12, 2))).toEqual({
			span: { low: 2, high: 12 },
			rows: { low: 1, high: MAX_ROWS },
			indent: { low: 0, high: 4 },
		});
		expect(bounds({ span: 6, rows: 1, indent: 3 }, grid(12, 2)).span).toEqual({ low: 2, high: 9 });
	});

	it('normalizes a degenerate grid', () => {
		expect(grid(0, 5)).toEqual({ columns: 1, min: 1 });
		expect(grid(6, 9)).toEqual({ columns: 6, min: 6 });
		expect(grid(6.7, 2.2)).toEqual({ columns: 6, min: 2 });
	});

	it('parses a dimension and rejects anything else', () => {
		expect(parseDimension('span')).toBe('span');
		expect(parseDimension('indent')).toBe('indent');
		expect(parseDimension('width')).toBeNull();
		expect(parseDimension(null)).toBeNull();
	});

	it('applies a typed value to the hidden input, the custom properties and data-indent', () => {
		const { row, input, control } = editor();

		type(control('span'), '7');

		expect(input('span').value).toBe('7');
		expect(row.style.getPropertyValue('--span')).toBe('7');
		expect(row.style.getPropertyValue('--reserved')).toBe('9');
		// The room the width leaves is the indent's new limit.
		expect(control('indent').max).toBe('5');

		type(control('rows'), '2');
		type(control('indent'), '1');

		expect(input('rows').value).toBe('2');
		expect(row.style.getPropertyValue('--rows')).toBe('2');
		expect(input('indent').value).toBe('1');
		expect(row.style.getPropertyValue('--indent')).toBe('1');
		expect(row.dataset.indent).toBe('1');
		expect(control('span').max).toBe('11');
		expect(read(row)).toEqual({ span: 7, rows: 2, indent: 1 });
	});

	it('caps the width at the grid edge instead of pulling the indent in', () => {
		const { input, control } = editor({ span: 8, rows: 1, indent: 4 });

		type(control('span'), '9', true);

		expect(input('span').value).toBe('8');
		expect(input('indent').value).toBe('4');
		expect(control('span').value).toBe('8');
	});

	it('waits for an out-of-range or half-typed value to commit', () => {
		const { input, control } = editor();

		// On the way to 10, the 1 is below the minimum of 2.
		type(control('span'), '1');

		expect(input('span').value).toBe('6');
		expect(control('span').value).toBe('1');

		type(control('span'), '10');

		expect(input('span').value).toBe('10');

		type(control('span'), '');

		expect(input('span').value).toBe('10');

		type(control('span'), '', true);

		expect(control('span').value).toBe('10');

		type(control('indent'), '7', true);

		expect(input('indent').value).toBe('2');
		expect(control('indent').value).toBe('2');
	});

	it('writes a layout without touching absent parts', () => {
		const { row, input } = editor();
		row.querySelector('dialog')?.remove();

		write(row, { span: 3, rows: 4, indent: 5 }, grid(12, 2));

		expect(input('span').value).toBe('3');
		expect(input('rows').value).toBe('4');
		expect(input('indent').value).toBe('5');
		expect(row.style.getPropertyValue('--rows')).toBe('4');
	});

	it('ignores inputs outside a repeater row', () => {
		document.body.innerHTML = `<input type="number" data-layout-input="span" value="3">`;
		const stray = document.querySelector<HTMLInputElement>('input');

		expect(() => stray && type(stray, '4', true)).not.toThrow();
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
	const layout = { span: 6, rows: 1, indent: 3 };

	it('grows the end edge, leaving the indent alone', () => {
		expect(resize(layout, 'end', 2, grid)).toEqual({ span: 8, rows: 1, indent: 3 });
		// Nine columns are left beside the indent; it never gives way.
		expect(resize(layout, 'end', 9, grid)).toEqual({ span: 9, rows: 1, indent: 3 });
		expect(resize(layout, 'end', -9, grid)).toEqual({ span: 2, rows: 1, indent: 3 });
	});

	it('trades indent against span on the start edge', () => {
		expect(resize(layout, 'start', -2, grid)).toEqual({ span: 8, rows: 1, indent: 1 });
		expect(resize(layout, 'start', 3, grid)).toEqual({ span: 3, rows: 1, indent: 6 });
		// Both directions stop before the block moves: indent 0, span min.
		expect(resize(layout, 'start', -5, grid)).toEqual({ span: 9, rows: 1, indent: 0 });
		expect(resize(layout, 'start', 8, grid)).toEqual({ span: 2, rows: 1, indent: 7 });
	});

	it('keeps the reserved width while the start edge moves', () => {
		// Indent plus span is what the block takes out of the row, and the
		// start edge only redistributes it — the block cannot wrap.
		for (const steps of [-3, -1, 0, 2, 5]) {
			const next = resize(layout, 'start', steps, grid);

			expect(next.indent + next.span).toBe(layout.indent + layout.span);
		}
	});

	it('counts rows on the bottom edge', () => {
		expect(resize(layout, 'bottom', 2, grid)).toEqual({ span: 6, rows: 3, indent: 3 });
		expect(resize(layout, 'bottom', 99, grid)).toEqual({ span: 6, rows: MAX_ROWS, indent: 3 });
		expect(resize(layout, 'bottom', -4, grid)).toEqual({ span: 6, rows: 1, indent: 3 });
	});
});
