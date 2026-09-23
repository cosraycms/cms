// Contract-level tests against hand-built DOM mirroring what
// panel/views/field/blocks.php renders: a multi-column canvas with the
// grid bounds, a placed row with its hidden layout inputs and the number
// inputs of its settings dialog, and a neighbour it grows towards.

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

type Placed = { colspan: number; rowspan: number; col: number; row: number };

function hidden(index: number, layout: Placed): string {
	return Object.entries(layout)
		.map(
			([key, value]) =>
				`<input type="hidden" name="${NAME}[${index}][layout][${key}]" value="${value}" data-layout="${key}">`,
		)
		.join('');
}

/** A placed row beside a neighbour starting at column `wall`, on a grid of its own. */
function editor(
	layout: Placed = { colspan: 6, rowspan: 1, col: 3, row: 1 },
	bounds = { columns: 12, min: 2 },
	wall = 11,
): {
	row: HTMLElement;
	input: (dimension: string) => HTMLInputElement;
	control: (dimension: string) => HTMLInputElement;
} {
	const neighbour = { colspan: bounds.columns - wall + 1, rowspan: 1, col: wall, row: 1 };

	document.body.innerHTML = `<div
		class="cms-blocks-editor ${bounds.columns > 1 ? 'is-grid' : 'is-list'}"
		data-repeater
		data-name="${NAME}"
		data-id="field-body-de"
		data-columns="${bounds.columns}"
		data-min="${bounds.min}"
		style="--columns: ${bounds.columns}">
		<div class="grid" data-repeater-list>
			<div
				class="block"
				data-repeater-row
				data-placed
				style="--colspan: ${layout.colspan}; --rowspan: ${layout.rowspan}; --col: ${layout.col}; --row: ${layout.row}">
				<input type="hidden" name="${NAME}[0][uid]" value="b1">
				${hidden(0, layout)}
				<div class="chrome"><span class="grip" data-repeater-grip tabindex="0"></span></div>
				<dialog data-meta>
					<div class="layout">
						${number('colspan', layout.colspan, bounds.min, wall - layout.col)}
						${number('rowspan', layout.rowspan, 1, MAX_ROWSPAN)}
					</div>
				</dialog>
			</div>
			${
				wall <= bounds.columns
					? `<div class="block" data-repeater-row data-placed>
						<input type="hidden" name="${NAME}[1][uid]" value="b2">
						${hidden(1, neighbour)}
					</div>`
					: ''
			}
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
		input: (dimension) => one(`:scope > input[data-layout="${dimension}"]`),
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

describe('blocks selection', () => {
	function canvas(): { row: HTMLElement; ground: HTMLElement; button: HTMLButtonElement } {
		document.body.innerHTML = `
			<div class="cms-blocks-editor is-list" data-repeater data-name="${NAME}" data-id="field-body" data-columns="1" data-min="1">
				<div class="grid" data-repeater-list>
					<div class="block" data-repeater-row>
						<div class="body"><p class="ground">Content</p><button type="button">Act</button></div>
					</div>
				</div>
			</div>`;

		return {
			row: document.querySelector<HTMLElement>('.block')!,
			ground: document.querySelector<HTMLElement>('.ground')!,
			button: document.querySelector<HTMLButtonElement>('button')!,
		};
	}

	it('focuses a block clicked on its own ground, for as long as it keeps focus', () => {
		const { row, ground, button } = canvas();

		ground.click();

		expect(document.activeElement).toBe(row);
		expect(row.getAttribute('tabindex')).toBe('-1');

		button.focus();

		expect(row.hasAttribute('tabindex')).toBe(false);
	});

	it('leaves a click on a control to the control', () => {
		const { row, button } = canvas();

		button.click();

		expect(document.activeElement).not.toBe(row);
		expect(row.hasAttribute('tabindex')).toBe(false);
	});
});

describe('blocks layout numbers', () => {
	it('clamps colspan into [min, columns] and rowspan into [1, MAX_ROWSPAN]', () => {
		const twelve = grid(12, 2);

		expect(clamp({ colspan: 14, rowspan: 9 }, twelve)).toEqual({
			colspan: 12,
			rowspan: MAX_ROWSPAN,
		});
		expect(clamp({ colspan: 1, rowspan: 0 }, twelve)).toEqual({ colspan: 2, rowspan: 1 });
		expect(bounds(twelve)).toEqual({
			colspan: { low: 2, high: 12 },
			rowspan: { low: 1, high: MAX_ROWSPAN },
		});
	});

	it('normalizes a degenerate grid', () => {
		expect(grid(0, 5)).toEqual({ columns: 1, min: 1 });
		expect(grid(6, 9)).toEqual({ columns: 6, min: 6 });
		expect(grid(6.7, 2.2)).toEqual({ columns: 6, min: 2 });
	});

	it('parses a dimension and rejects anything else', () => {
		expect(parseDimension('colspan')).toBe('colspan');
		expect(parseDimension('rowspan')).toBe('rowspan');
		expect(parseDimension('indent')).toBeNull();
		expect(parseDimension(null)).toBeNull();
	});

	it('applies a typed value to the hidden input and the custom properties', () => {
		const { row, input, control } = editor();

		type(control('colspan'), '7');

		expect(input('colspan').value).toBe('7');
		expect(row.style.getPropertyValue('--colspan')).toBe('7');

		type(control('rowspan'), '2');

		expect(input('rowspan').value).toBe('2');
		expect(row.style.getPropertyValue('--rowspan')).toBe('2');
		expect(read(row)).toEqual({ colspan: 7, rowspan: 2 });
	});

	it('caps the width at the neighbour instead of moving it', () => {
		const { input, control } = editor();

		type(control('colspan'), '9', true);

		// Columns 3 to 10 are free; the neighbour starts at 11.
		expect(input('colspan').value).toBe('8');
		expect(control('colspan').value).toBe('8');
		expect(control('colspan').max).toBe('8');
	});

	it('waits for an out-of-range or half-typed value to commit', () => {
		const { input, control } = editor({ colspan: 6, rowspan: 1, col: 1, row: 1 }, undefined, 13);

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
	});

	it('renders the layout a stamped row carries in its inputs', () => {
		const { row, input, control } = editor();

		// A duplicate: the inputs were copied, the style and the dialog were not.
		input('colspan').value = '4';
		row.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(row.style.getPropertyValue('--colspan')).toBe('4');
		expect(control('colspan').value).toBe('4');
	});

	it('writes a layout without touching absent parts', () => {
		const { row, input } = editor();
		row.querySelector('dialog')?.remove();

		write(row, { colspan: 3, rowspan: 4 }, grid(12, 2));

		expect(input('colspan').value).toBe('3');
		expect(input('rowspan').value).toBe('4');
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
		const { row, input } = editor();
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);

		expect(press(grip(row), 'ArrowRight').defaultPrevented).toBe(true);
		expect(read(row)).toEqual({ colspan: 7, rowspan: 1 });
		expect(row.style.getPropertyValue('--colspan')).toBe('7');

		press(grip(row), 'ArrowLeft', { alt: true, shift: true });

		// The start edge moved left: the block starts a column earlier, its end stays.
		expect(read(row)).toEqual({ colspan: 8, rowspan: 1 });
		expect(input('col').value).toBe('2');

		press(grip(row), 'ArrowDown');
		press(grip(row), 'ArrowDown');
		press(grip(row), 'ArrowUp');

		expect(read(row)).toEqual({ colspan: 8, rowspan: 2 });
		document.removeEventListener('change', count);

		expect(changes).toBe(5);
	});

	it('stops where the handles stop, without a change', () => {
		const { row } = editor({ colspan: 8, rowspan: 1, col: 3, row: 1 });
		let changes = 0;
		const count = (): void => {
			changes += 1;
		};

		document.addEventListener('change', count);
		press(grip(row), 'ArrowRight');
		document.removeEventListener('change', count);

		expect(read(row)).toEqual({ colspan: 8, rowspan: 1 });
		expect(changes).toBe(0);
	});

	it('leaves other keys, other targets and one-column fields alone', () => {
		const { row } = editor();

		expect(press(grip(row), 'ArrowRight', { alt: false }).defaultPrevented).toBe(false);
		expect(press(row, 'ArrowRight').defaultPrevented).toBe(false);
		expect(read(row)).toEqual({ colspan: 6, rowspan: 1 });

		const list = editor({ colspan: 1, rowspan: 1, col: 1, row: 1 }, { columns: 1, min: 1 }, 2);

		expect(press(grip(list.row), 'ArrowDown').defaultPrevented).toBe(false);
		expect(read(list.row)).toEqual({ colspan: 1, rowspan: 1 });
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

describe('blocks gap settings', () => {
	const TOKENS = ['', 'none', 's', 'm', 'l', 'xl'];

	function gapDialog(gap: string, rowGap: string, columnGap: string): HTMLElement {
		const split = rowGap !== '' || columnGap !== '';
		const select = (key: string, value: string): string => `<div
			class="field"
			data-gap-${key === 'gap' ? 'single' : 'separate'}
			${(key === 'gap') === split ? 'hidden' : ''}>
			<select name="content[body][meta][${key}][zxx]">
				${TOKENS.map((token) => `<option value="${token}"${token === value ? ' selected' : ''}>${token}</option>`).join('')}
			</select>
		</div>`;

		document.body.innerHTML = `<div class="cms-field" data-meta-owner data-field="body">
			<dialog data-meta>
				<div class="fields" data-gap-scope>
					${select('gap', gap)}
					<label class="field split">
						<input type="checkbox" data-gap-split ${split ? 'checked' : ''}>
					</label>
					${select('rowGap', rowGap)}
					${select('columnGap', columnGap)}
				</div>
			</dialog>
		</div>`;

		return document.querySelector<HTMLElement>('[data-gap-scope]')!;
	}

	function gapSelect(scope: HTMLElement, key: string): HTMLSelectElement {
		return scope.querySelector<HTMLSelectElement>(`select[name$="[meta][${key}][zxx]"]`)!;
	}

	it('splits the gap into row and column gap and joins them again', () => {
		const scope = gapDialog('m', '', '');
		const toggle = scope.querySelector<HTMLInputElement>('[data-gap-split]')!;
		const changed: string[] = [];
		document.addEventListener('change', (event) => {
			if (event.target instanceof HTMLSelectElement) changed.push(event.target.name);
		});

		toggle.click();

		expect(gapSelect(scope, 'gap').value).toBe('');
		expect(gapSelect(scope, 'rowGap').value).toBe('m');
		expect(gapSelect(scope, 'columnGap').value).toBe('m');
		expect(scope.querySelector<HTMLElement>('[data-gap-single]')!.hidden).toBe(true);
		expect(
			[...scope.querySelectorAll<HTMLElement>('[data-gap-separate]')].map((part) => part.hidden),
		).toEqual([false, false]);
		expect(changed).toHaveLength(3);

		gapSelect(scope, 'rowGap').value = 'l';
		gapSelect(scope, 'columnGap').value = 's';
		toggle.click();

		expect(gapSelect(scope, 'gap').value).toBe('l');
		expect(gapSelect(scope, 'rowGap').value).toBe('');
		expect(gapSelect(scope, 'columnGap').value).toBe('');
		expect(scope.querySelector<HTMLElement>('[data-gap-single]')!.hidden).toBe(false);
		expect(
			[...scope.querySelectorAll<HTMLElement>('[data-gap-separate]')].map((part) => part.hidden),
		).toEqual([true, true]);
	});

	it('joins onto the column gap when no row gap is set', () => {
		const scope = gapDialog('', '', 'xl');

		scope.querySelector<HTMLInputElement>('[data-gap-split]')!.click();

		expect(gapSelect(scope, 'gap').value).toBe('xl');
		expect(gapSelect(scope, 'columnGap').value).toBe('');
	});
});

describe('blocks spacing mirror', () => {
	const TOKENS = ['', 'none', 's', 'm', 'l', 'xl'];

	function select(name: string, value: string): string {
		return `<select name="${name}">${TOKENS.map(
			(token) => `<option value="${token}"${token === value ? ' selected' : ''}>${token}</option>`,
		).join('')}</select>`;
	}

	function field(): { container: HTMLElement; row: HTMLElement } {
		document.body.innerHTML = `<div class="cms-field" data-meta-owner data-field="body">
			<div class="cms-blocks-editor is-grid" data-repeater data-name="${NAME}" data-columns="12" data-min="2">
				<div data-repeater-list>
					<div class="block" data-repeater-row data-meta-owner>
						<input type="hidden" name="${NAME}[0][layout][colspan]" value="6" data-layout="colspan">
						<dialog data-meta>${select(`${NAME}[0][meta][padding][zxx]`, '')}</dialog>
					</div>
				</div>
			</div>
			<dialog data-meta>
				${select('content[body][meta][gap][zxx]', '')}
				${select('content[body][meta][rowGap][zxx]', '')}
				${select('content[body][meta][columnGap][zxx]', '')}
			</dialog>
		</div>`;

		return {
			container: document.querySelector<HTMLElement>('.cms-blocks-editor')!,
			row: document.querySelector<HTMLElement>('[data-repeater-row]')!,
		};
	}

	function choose(name: string, value: string): void {
		const control = document.querySelector<HTMLSelectElement>(`select[name="${name}"]`)!;

		control.value = value;
		control.dispatchEvent(new Event('change', { bubbles: true }));
	}

	it('writes the gap selects onto the container and the padding onto the row', () => {
		const { container, row } = field();

		choose('content[body][meta][gap][zxx]', 's');
		choose('content[body][meta][columnGap][zxx]', 'xl');
		choose(`${NAME}[0][meta][padding][zxx]`, 'm');

		expect(container.dataset.gap).toBe('s');
		expect(container.dataset.columnGap).toBe('xl');
		expect(container.hasAttribute('data-row-gap')).toBe(false);
		expect(row.dataset.padding).toBe('m');

		choose('content[body][meta][gap][zxx]', '');
		choose(`${NAME}[0][meta][padding][zxx]`, '');

		expect(container.hasAttribute('data-gap')).toBe(false);
		expect(row.hasAttribute('data-padding')).toBe(false);
	});

	it('shows the padding a stamped row carries in its select', () => {
		const { row } = field();

		row.querySelector<HTMLSelectElement>('select')!.value = 'l';
		row.dispatchEvent(new Event('repeater:stamp', { bubbles: true }));

		expect(row.dataset.padding).toBe('l');
	});
});
