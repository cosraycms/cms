// Key bindings for the menu tree, on top of the focus model in `menu-tree`.
//
// Two axes, two key pairs: nothing that reorders can change the level, and
// nothing that changes the level can reorder. That separation is the whole
// point — a drag conflates both into one gesture, which is where accidental
// renesting comes from.
//
// Moves need no plumbing of their own. Every row's kebab already carries a
// form per direction, with the right action and the right disabled state, so
// a binding finds that form and submits it. The disabled attribute is the
// legality check, and without JavaScript the kebab is simply used by hand.

import {
	collapsed,
	currentRow,
	expandable,
	focusRow,
	release,
	toggle,
	tree,
	visibleRows,
} from './menu-tree';

type Direction = 'up' | 'down' | 'in' | 'out';

function typing(target: EventTarget | null): boolean {
	return (
		target instanceof HTMLInputElement ||
		target instanceof HTMLTextAreaElement ||
		target instanceof HTMLSelectElement ||
		(target instanceof HTMLElement && target.isContentEditable)
	);
}

function step(root: HTMLElement, row: HTMLElement, offset: number): void {
	const visible = visibleRows(root);
	const next = visible[visible.indexOf(row) + offset];

	if (next) {
		focusRow(root, next);
	}
}

function parentRow(row: HTMLElement): HTMLElement | null {
	return row.parentElement?.closest<HTMLElement>('[role="treeitem"]') ?? null;
}

function firstChild(row: HTMLElement): HTMLElement | null {
	return row.querySelector<HTMLElement>(':scope > ul > [role="treeitem"]');
}

/** Left: fold this branch, or leave it when there is nothing left to fold. */
function fold(root: HTMLElement, row: HTMLElement): void {
	if (expandable(row) && !collapsed(row)) {
		toggle(root, row, true);

		return;
	}

	const parent = parentRow(row);

	if (parent) {
		focusRow(root, parent);
	}
}

/** Right: unfold this branch, or descend into it when it is already open. */
function unfold(root: HTMLElement, row: HTMLElement): void {
	if (expandable(row) && collapsed(row)) {
		toggle(root, row, false);

		return;
	}

	const child = firstChild(row);

	if (child) {
		focusRow(root, child);
	}
}

/**
 * Submits the row's own move form, unless the kebab already knows the move
 * is undefined here — a first item cannot indent, a root item cannot outdent.
 */
function move(row: HTMLElement, direction: Direction): void {
	const input = row.querySelector<HTMLInputElement>(`.kebab form input[value="${direction}"]`);
	const form = input?.form;
	const button = form?.querySelector('button[type="submit"]');

	if (form && button instanceof HTMLButtonElement && !button.disabled) {
		form.requestSubmit();
	}
}

function activate(row: HTMLElement): void {
	row.querySelector<HTMLAnchorElement>('a.text')?.click();
}

function openKebab(row: HTMLElement): void {
	const kebab = row.querySelector<HTMLDetailsElement>(':scope > .menu-card > .kebab');

	if (kebab) {
		kebab.open = true;
		kebab.querySelector<HTMLElement>('a, button')?.focus();
	}
}

function onKeydown(event: KeyboardEvent): void {
	if (typing(event.target) || event.metaKey || event.ctrlKey) {
		return;
	}

	const root = tree();
	const target = event.target;

	if (!root || !(target instanceof Node) || !root.contains(target)) {
		return;
	}

	const row = currentRow(root);

	if (!row) {
		return;
	}

	const handled = event.altKey ? withAlt(row, event.key) : plain(root, row, event);

	if (handled) {
		event.preventDefault();
	}
}

/** Alt reorders among siblings and changes the level; never both at once. */
function withAlt(row: HTMLElement, key: string): boolean {
	const direction: Record<string, Direction> = {
		ArrowUp: 'up',
		k: 'up',
		ArrowDown: 'down',
		j: 'down',
		l: 'in',
		h: 'out',
	};

	if (!(key in direction)) {
		return false;
	}

	move(row, direction[key]);

	return true;
}

function plain(root: HTMLElement, row: HTMLElement, event: KeyboardEvent): boolean {
	const visible = visibleRows(root);

	switch (event.key) {
		case 'Tab':
			move(row, event.shiftKey ? 'out' : 'in');

			return true;
		case 'ArrowUp':
		case 'k':
			step(root, row, -1);

			return true;
		case 'ArrowDown':
		case 'j':
			step(root, row, 1);

			return true;
		case 'ArrowLeft':
		case 'h':
			fold(root, row);

			return true;
		case 'ArrowRight':
		case 'l':
			unfold(root, row);

			return true;
		case 'Home':
			focusRow(root, visible[0]);

			return true;
		case 'End':
			focusRow(root, visible[visible.length - 1]);

			return true;
		case 'Enter':
			activate(row);

			return true;
		case '.':
			openKebab(row);

			return true;
		case 'Escape':
			release(root);

			return true;
		default:
			return false;
	}
}

export function install(): () => void {
	document.addEventListener('keydown', onKeydown);

	return () => {
		document.removeEventListener('keydown', onKeydown);
	};
}
