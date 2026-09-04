// Layout stepping for the blocks editor. A block row carries its
// layout as hidden inputs (data-layout="span|rows|indent"); a click on
// a [data-layout-step="span:+1"] button steps one of them within the
// bounds the field's shape and the save patch enforce — span in
// [min, columns], rows in [1, MAX_ROWS], indent in [0, columns − span],
// a span change re-clamping the indent — then writes the input, the
// row's custom properties and data-indent (the grid preview follows),
// the value badges, disables the buttons that reached a bound and
// dispatches change so the dirty guard sees the edit. The bounds come
// from the container's data-columns/data-min.

export const MAX_ROWS = 6;

export type Dimension = 'span' | 'rows' | 'indent';
export type Layout = Record<Dimension, number>;
export type Grid = { columns: number; min: number };
export type Bounds = Record<Dimension, { low: number; high: number }>;

const DIMENSIONS: Dimension[] = ['span', 'rows', 'indent'];

function between(value: number, low: number, high: number): number {
	return Math.max(low, Math.min(high, Math.trunc(value) || 0));
}

export function grid(columns: number, min: number): Grid {
	const cols = between(columns, 1, Number.MAX_SAFE_INTEGER);

	return { columns: cols, min: between(min, 1, cols) };
}

export function clamp(layout: Layout, grid: Grid): Layout {
	const span = between(layout.span, grid.min, grid.columns);

	return {
		span,
		rows: between(layout.rows, 1, MAX_ROWS),
		indent: between(layout.indent, 0, grid.columns - span),
	};
}

export function step(layout: Layout, dimension: Dimension, delta: number, grid: Grid): Layout {
	return clamp({ ...layout, [dimension]: layout[dimension] + delta }, grid);
}

/** The reachable range of each dimension given the others. */
export function bounds(layout: Layout, grid: Grid): Bounds {
	const { span } = clamp(layout, grid);

	return {
		span: { low: grid.min, high: grid.columns },
		rows: { low: 1, high: MAX_ROWS },
		indent: { low: 0, high: grid.columns - span },
	};
}

export function parseStep(value: string | null): { dimension: Dimension; delta: number } | null {
	const match = /^(span|rows|indent):([+-]\d+)$/.exec(value ?? '');

	return match ? { dimension: match[1] as Dimension, delta: Number(match[2]) } : null;
}

function gridOf(container: HTMLElement): Grid {
	return grid(Number(container.dataset.columns) || 1, Number(container.dataset.min) || 1);
}

function input(row: HTMLElement, dimension: Dimension): HTMLInputElement | null {
	return row.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);
}

export function read(row: HTMLElement): Layout {
	const layout = { span: 1, rows: 1, indent: 0 };

	for (const dimension of DIMENSIONS) {
		layout[dimension] = Number(input(row, dimension)?.value) || layout[dimension];
	}

	return layout;
}

export function write(row: HTMLElement, layout: Layout, grid: Grid): void {
	const limits = bounds(layout, grid);

	for (const dimension of DIMENSIONS) {
		const value = String(layout[dimension]);
		const field = input(row, dimension);

		if (field) {
			field.value = value;
		}

		row.style.setProperty(`--${dimension}`, value);
		row.querySelectorAll<HTMLElement>(`[data-layout-badge="${dimension}"]`).forEach((badge) => {
			badge.textContent = value;
		});
		row
			.querySelectorAll<HTMLButtonElement>(`[data-layout-step="${dimension}:-1"]`)
			.forEach((button) => {
				button.disabled = layout[dimension] <= limits[dimension].low;
			});
		row
			.querySelectorAll<HTMLButtonElement>(`[data-layout-step="${dimension}:+1"]`)
			.forEach((button) => {
				button.disabled = layout[dimension] >= limits[dimension].high;
			});
	}

	row.style.setProperty('--reserved', String(layout.indent + layout.span));
	row.dataset.indent = String(layout.indent);
}

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const button = target.closest('[data-layout-step]');
	const parsed = parseStep(button?.getAttribute('data-layout-step') ?? null);
	const row = button?.closest<HTMLElement>('[data-repeater-row]');
	const container = row?.closest<HTMLElement>('[data-repeater]');

	if (!parsed || !row || !container) {
		return;
	}

	const before = read(row);
	const after = step(before, parsed.dimension, parsed.delta, gridOf(container));

	if (DIMENSIONS.every((dimension) => before[dimension] === after[dimension])) {
		return;
	}

	write(row, after, gridOf(container));
	(input(row, parsed.dimension) ?? row).dispatchEvent(new Event('change', { bubbles: true }));
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
