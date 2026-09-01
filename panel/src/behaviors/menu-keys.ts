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

/** Opens the create pane the row's kebab already links to. */
function add(row: HTMLElement, kind: 'before' | 'after'): void {
	row.querySelector<HTMLAnchorElement>(`.kebab a[data-menu-add="${kind}"]`)?.click();
}

function openKebab(row: HTMLElement): void {
	const kebab = row.querySelector<HTMLDetailsElement>(':scope > .menu-card > .kebab');

	if (kebab) {
		kebab.open = true;
		kebab.querySelector<HTMLElement>('a, button')?.focus();
	}
}

function onKeydown(event: KeyboardEvent): void {
	if (typing(event.target)) {
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

	const accel = event.metaKey || event.ctrlKey;
	let handled = false;

	if (accel && event.shiftKey) {
		handled = moveBy(row, event.code, ARROW_MOVES);
	} else if (event.altKey && !accel) {
		handled = moveBy(row, event.code, VIM_MOVES);
	} else if (!accel && !event.altKey) {
		handled = plain(root, row, event);
	}

	if (handled) {
		event.preventDefault();
	}
}

/**
 * Moving the focused item. Both spellings do the same four things — the
 * arrows for everyone, the vim letters for the fingers that expect them —
 * and each is modified so that it collides with nothing the browser owns.
 *
 * `Alt` deliberately does not carry the arrows: Alt+left and Alt+right are
 * back and forward on Windows and Linux, and an essential default is not
 * ours to reinterpret, however recoverable the accident would be. The
 * arrows take `Ctrl+Shift` / `Cmd+Shift` instead, which is what Notion,
 * Miro and Webflow reach for. Both are accepted rather than sniffing the
 * platform, since neither means anything else outside a text field.
 *
 * Matched on `code`, not `key`: macOS composes Option with a letter into
 * another character entirely — Option+h is `˙`, Option+l is `¬` — so the
 * letter never arrives. The physical key is the same one on QWERTY and
 * QWERTZ, which is what these bindings mean anyway.
 */
const ARROW_MOVES: Record<string, Direction> = {
	ArrowUp: 'up',
	ArrowDown: 'down',
	ArrowRight: 'in',
	ArrowLeft: 'out',
};

const VIM_MOVES: Record<string, Direction> = {
	KeyK: 'up',
	KeyJ: 'down',
	KeyL: 'in',
	KeyH: 'out',
};

function moveBy(row: HTMLElement, code: string, map: Record<string, Direction>): boolean {
	if (!(code in map)) {
		return false;
	}

	move(row, map[code]);

	return true;
}

function plain(root: HTMLElement, row: HTMLElement, event: KeyboardEvent): boolean {
	const visible = visibleRows(root);

	switch (event.key) {
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
		case 'e':
			activate(row);

			return true;
		// `o` and `O` open a line below and above, exactly as in a vim buffer.
		// A child is `o` followed by Alt+right, the way an outliner does it,
		// so no key has to mean "but nested".
		case 'o':
			add(row, 'after');

			return true;
		case 'O':
			add(row, 'before');

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
