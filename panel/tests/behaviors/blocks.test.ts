// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/blocks.php renders: a container with the grid
// bounds, a row with its hidden layout inputs, badges and step buttons.

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
	bounds,
	clamp,
	grid,
	install,
	MAX_ROWS,
	parseStep,
	read,
	step,
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

function stepper(dimension: string, value: number): string {
	return `<span class="stepper">
		<button type="button" data-layout-step="${dimension}:-1">−</button>
		<span data-layout-badge="${dimension}">${value}</span>
		<button type="button" data-layout-step="${dimension}:+1">+</button>
	</span>`;
}

function editor(
	layout = { span: 6, rows: 1, indent: 2 },
	bounds = { columns: 12, min: 2 },
): {
	row: HTMLElement;
	input: (dimension: string) => HTMLInputElement;
	button: (step: string) => HTMLButtonElement;
	badge: (dimension: string) => HTMLElement;
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
				<div class="toolbar">${stepper('span', layout.span)}</div>
				<div class="kebab-menu">
					${stepper('span', layout.span)}
					${stepper('rows', layout.rows)}
					${stepper('indent', layout.indent)}
				</div>
			</div>
		</div>
	</div>`;

	const row = document.querySelector<HTMLElement>('[data-repeater-row]');

	if (!row) {
		throw new Error('row missing');
	}

	const one = <T extends Element>(selector: string): T => {
		const found = row.querySelector<T>(selector);

		if (!found) {
			throw new Error(`missing ${selector}`);
		}

		return found;
	};

	return {
		row,
		input: (dimension) => one<HTMLInputElement>(`input[data-layout="${dimension}"]`),
		button: (step) => one<HTMLButtonElement>(`[data-layout-step="${step}"]`),
		badge: (dimension) => one<HTMLElement>(`[data-layout-badge="${dimension}"]`),
	};
}

describe('blocks layout stepping', () => {
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
		const twelve = grid(12, 2);

		expect(clamp({ span: 8, rows: 1, indent: 6 }, twelve).indent).toBe(4);
		// Widening re-clamps the indent; narrowing leaves it alone.
		expect(step({ span: 8, rows: 1, indent: 4 }, 'span', 1, twelve)).toEqual({
			span: 9,
			rows: 1,
			indent: 3,
		});
		expect(step({ span: 8, rows: 1, indent: 4 }, 'span', -1, twelve)).toEqual({
			span: 7,
			rows: 1,
			indent: 4,
		});
	});

	it('bounds every dimension given the others', () => {
		expect(bounds({ span: 8, rows: 1, indent: 0 }, grid(12, 2))).toEqual({
			span: { low: 2, high: 12 },
			rows: { low: 1, high: MAX_ROWS },
			indent: { low: 0, high: 4 },
		});
	});

	it('normalizes a degenerate grid', () => {
		expect(grid(0, 5)).toEqual({ columns: 1, min: 1 });
		expect(grid(6, 9)).toEqual({ columns: 6, min: 6 });
		expect(grid(6.7, 2.2)).toEqual({ columns: 6, min: 2 });
	});

	it('parses step attributes and rejects anything else', () => {
		expect(parseStep('span:+1')).toEqual({ dimension: 'span', delta: 1 });
		expect(parseStep('indent:-2')).toEqual({ dimension: 'indent', delta: -2 });
		expect(parseStep('width:+1')).toBeNull();
		expect(parseStep('span:1')).toBeNull();
		expect(parseStep(null)).toBeNull();
	});

	it('steps the hidden input, the custom properties, the badges and data-indent', () => {
		const { row, input, button, badge } = editor();

		button('span:+1').click();

		expect(input('span').value).toBe('7');
		expect(row.style.getPropertyValue('--span')).toBe('7');
		expect(badge('span').textContent).toBe('7');
		expect(
			Array.from(row.querySelectorAll('[data-layout-badge="span"]'), (el) => el.textContent),
		).toEqual(['7', '7']);

		button('rows:+1').click();
		button('indent:-1').click();

		expect(input('rows').value).toBe('2');
		expect(row.style.getPropertyValue('--rows')).toBe('2');
		expect(input('indent').value).toBe('1');
		expect(row.style.getPropertyValue('--indent')).toBe('1');
		expect(row.dataset.indent).toBe('1');
		expect(read(row)).toEqual({ span: 7, rows: 2, indent: 1 });
	});

	it('disables the buttons at a bound and re-enables them on the way back', () => {
		const { button } = editor({ span: 11, rows: 1, indent: 0 });

		button('span:+1').click();

		expect(button('span:+1').disabled).toBe(true);
		expect(button('span:-1').disabled).toBe(false);
		// Full width leaves no room for an indent.
		expect(button('indent:+1').disabled).toBe(true);
		expect(button('indent:-1').disabled).toBe(true);

		button('span:-1').click();

		expect(button('span:+1').disabled).toBe(false);
		expect(button('indent:+1').disabled).toBe(false);
	});

	it('re-clamps the indent when the span widens into it', () => {
		const { input, button } = editor({ span: 8, rows: 1, indent: 4 });

		button('span:+1').click();

		expect(input('span').value).toBe('9');
		expect(input('indent').value).toBe('3');
	});

	it('dispatches a bubbling change only when something moved', () => {
		const { button } = editor({ span: 12, rows: MAX_ROWS, indent: 0 });
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);
		button('span:+1').click();
		button('rows:+1').click();
		button('indent:+1').click();
		button('span:-1').click();
		document.removeEventListener('change', count);

		expect(changes).toBe(1);
	});

	it('writes a layout without touching absent parts', () => {
		const { row, input } = editor();
		row.querySelector('.kebab-menu')?.remove();

		write(row, { span: 3, rows: 4, indent: 5 }, grid(12, 2));

		expect(input('span').value).toBe('3');
		expect(input('rows').value).toBe('4');
		expect(input('indent').value).toBe('5');
		expect(row.style.getPropertyValue('--rows')).toBe('4');
	});

	it('ignores clicks outside a repeater row', () => {
		document.body.innerHTML = `<button type="button" data-layout-step="span:+1">+</button>`;

		expect(() => document.querySelector<HTMLElement>('button')?.click()).not.toThrow();
	});
});
