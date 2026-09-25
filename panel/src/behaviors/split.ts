// Splitting blocks into parts, side by side or stacked, and taking parts
// away again. A split acts on the clicked block and halves it: the first
// split of a block on the grid puts a split in the block's place, holding
// the block and the new part; a part splits again inside its split. The
// new part's type comes from the field's picker, opened at the block's
// kebab, or is the field's one type. A removed part hands its space to
// its neighbour, and a split left with one part turns back into that
// block, with the split's layout. Blocks move between the grid and a
// split as they are, never cloned: an element host keeps its edits on the
// element.

import { openMenu } from '$lib/action-menu';
import { uid } from '$lib/content';
import { open as openCatalog } from './block-catalog';
import {
	MAX_ROWSPAN,
	directionOf,
	gridFor,
	gridOf,
	partsOf,
	read,
	splitOf,
	stretch,
	write,
	type Direction,
	type Layout,
} from './blocks';
import { adder, arm } from './pick';
import { adopt, release } from './placement';
import { changed, focusRow, initDrag, insert, insertion, rebase } from './repeater';

function parse(value: string | null): Direction | null {
	return value === 'columns' || value === 'rows' ? value : null;
}

/** Room for two halves side by side, or a row to spare below. */
function splittable(row: HTMLElement, direction: Direction): boolean {
	const split = splitOf(row);

	if (split && directionOf(split) !== direction) {
		return false;
	}

	return direction === 'columns'
		? read(row).colspan >= 2 * gridFor(row).min
		: read(split ?? row).rowspan < MAX_ROWSPAN;
}

/** The first split of a block on the grid: a split in its place, holding it. */
function wrap(row: HTMLElement, direction: Direction): HTMLElement | null {
	const field = row.closest<HTMLElement>('[data-repeater]');
	const template = field?.querySelector<HTMLTemplateElement>(
		':scope > template[data-repeater-container]',
	);
	const split = template?.content.firstElementChild?.cloneNode(true);

	if (!field || !(split instanceof HTMLElement)) {
		return null;
	}

	const parts = split.querySelector<HTMLElement>(':scope > .parts')!;
	const layout = read(row);

	split.querySelector<HTMLInputElement>('[data-repeater-uid]')!.value = uid();
	split.dataset.split = direction;
	row.before(split);
	rebase(row, field, parts);
	parts.append(row);
	write(split, layout, gridOf(field));
	adopt(row, split);
	release(row);
	parts.dataset.columns = String(layout.colspan);
	write(row, layout, gridOf(parts));
	changed(field);
	void initDrag();

	return split;
}

/** Halves the block, or stacks a part below it, and stamps the new part beside it. */
function split(row: HTMLElement, direction: Direction, type: string | null): void {
	if (!insertion(row) || !splittable(row, direction)) {
		return;
	}

	const container = splitOf(row) ?? wrap(row, direction);
	const parts = container?.querySelector<HTMLElement>(':scope > .parts');
	const context = parts && insertion(parts);

	if (!container || !parts || !context) {
		return;
	}

	const layout = read(row);
	let added: Layout;

	if (direction === 'columns') {
		const kept = Math.ceil(layout.colspan / 2);

		write(row, { ...layout, colspan: kept }, gridOf(parts));
		added = { colspan: layout.colspan - kept, rowspan: layout.rowspan };
	} else {
		const area = read(container);

		stretch(container, area.rowspan + 1);
		added = { colspan: area.colspan, rowspan: 1 };
	}

	insert(
		{
			...context,
			at: { row, where: 'after' },
			prepare(clone) {
				for (const [dimension, value] of Object.entries(added)) {
					const input = clone.querySelector<HTMLInputElement>(`input[data-layout="${dimension}"]`);

					if (input) {
						input.value = String(value);
					}
				}
			},
		},
		type,
	);
}

/** The picker at the block's kebab, or the field's one type right away. */
function choose(row: HTMLElement, direction: Direction, keyboard: boolean): void {
	const field = row.closest<HTMLElement>('.cms-blocks-editor');
	const how = field && adder(field);
	const base = field && insertion(field);

	if (!how || !base || !splittable(row, direction)) {
		return;
	}

	if ('type' in how) {
		split(row, direction, how.type);
		return;
	}

	const menu = document.getElementById(how.picker);
	const kebab = row.querySelector<HTMLButtonElement>(':scope > .chrome .kebab');

	if (!menu || !kebab) {
		return;
	}

	openMenu(kebab, keyboard ? 'first' : false, kebab, menu);
	arm(
		menu,
		(type) => split(row, direction, type),
		() =>
			openCatalog({ ...base, at: { row, where: 'after' } }, true, (type) =>
				split(row, direction, type),
			),
	);
}

/**
 * The part's space goes to its neighbour: the previous part's width (the
 * next one's, for the first), or the split's rows.
 */
function remove(part: HTMLElement): void {
	const container = splitOf(part);
	const parts = container ? partsOf(container) : [];
	const index = parts.indexOf(part);
	const heir = parts[index - 1] ?? parts[index + 1];
	const list = part.parentElement;

	if (!container || !heir || !list || !insertion(part)) {
		return;
	}

	if (directionOf(container) === 'columns') {
		const layout = read(heir);

		write(heir, { ...layout, colspan: layout.colspan + read(part).colspan }, gridOf(list));
	} else {
		stretch(container, read(container).rowspan - read(part).rowspan);
	}

	part.remove();

	if (parts.length > 2) {
		changed(list);
		focusRow(heir);

		return;
	}

	const field = container.closest<HTMLElement>('[data-repeater]')!;
	const area = read(container);

	rebase(heir, list, field);
	container.replaceWith(heir);
	adopt(container, heir);
	write(heir, area, gridOf(field));
	changed(field);
	focusRow(heir);
}

function onClick(event: MouseEvent): void {
	const target = event.target instanceof Element ? event.target : null;
	const entry = target?.closest<HTMLElement>('[data-split-into]');
	const direction = parse(entry?.getAttribute('data-split-into') ?? null);
	const row = entry?.closest<HTMLElement>('[data-repeater-row]');

	if (direction && row) {
		choose(row, direction, event.detail === 0);

		return;
	}

	const part = target?.closest('[data-split-remove]')?.closest<HTMLElement>('[data-repeater-row]');

	if (part) {
		remove(part);
	}
}

/** A block's kebab offers the splits it has room for. */
function onBeforeToggle(event: Event): void {
	const menu = event.target;

	if (!(menu instanceof HTMLElement) || (event as ToggleEvent).newState !== 'open') {
		return;
	}

	const row = menu.closest<HTMLElement>('[data-repeater-row]');

	for (const entry of menu.querySelectorAll<HTMLButtonElement>(':scope > [data-split-into]')) {
		const direction = parse(entry.getAttribute('data-split-into'));

		entry.disabled = !row || !direction || !splittable(row, direction);
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);
	document.addEventListener('beforetoggle', onBeforeToggle, true);

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('beforetoggle', onBeforeToggle, true);
	};
}
