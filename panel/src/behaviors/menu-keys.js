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

import { openMenu } from '../lib/action-menu.js';

import {
	collapsed,
	currentRow,
	expandable,
	focusRow,
	release,
	toggle,
	tree,
	visibleRows,
} from './menu-tree.js';

/** @typedef {'up' | 'down' | 'in' | 'out'} Direction */

const ACTIONS = ':scope > .menu-card > [data-action-menu]';

/**
 * The vim layer is off unless a browser opts into it. What it really gates
 * is who owns the unmodified letters: type-ahead, which the ARIA patterns
 * expect of a tree, needs every printable key, so a letter cannot belong to
 * both. Without the layer the tree keeps exactly the pattern's own keys.
 *
 * Per keystroke rather than cached: at typing speed the read costs nothing,
 * and a setting with no interface should take effect the moment it is set.
 */
const VIM_SETTING = 'cosray:vim-keys';

/** @returns {boolean} */
function vim() {
	try {
		return localStorage.getItem(VIM_SETTING) === 'on';
	} catch {
		return false;
	}
}

/**
 * The vim spellings fold onto the keys the base layer already handles.
 *
 * @type {Record<string, string>}
 */
const VIM_KEYS = {
	j: 'ArrowDown',
	k: 'ArrowUp',
	h: 'ArrowLeft',
	l: 'ArrowRight',
	e: 'Enter',
};

/**
 * @param {EventTarget | null} target
 * @returns {boolean}
 */
function typing(target) {
	return (
		target instanceof HTMLInputElement ||
		target instanceof HTMLTextAreaElement ||
		target instanceof HTMLSelectElement ||
		(target instanceof HTMLElement && target.isContentEditable)
	);
}

/**
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 * @param {number} offset
 */
function step(root, row, offset) {
	const visible = visibleRows(root);
	const next = visible[visible.indexOf(row) + offset];

	if (next) {
		focusRow(root, next);
	}
}

/**
 * @param {HTMLElement} row
 * @returns {HTMLElement | null}
 */
function parentRow(row) {
	return (
		/** @type {HTMLElement | null} */ (row.parentElement?.closest('[role="treeitem"]')) ?? null
	);
}

/**
 * @param {HTMLElement} row
 * @returns {HTMLElement | null}
 */
function firstChild(row) {
	return /** @type {HTMLElement | null} */ (row.querySelector(':scope > ul > [role="treeitem"]'));
}

/**
 * Left: fold this branch, or leave it when there is nothing left to fold.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 */
function fold(root, row) {
	if (expandable(row) && !collapsed(row)) {
		toggle(root, row, true);

		return;
	}

	const parent = parentRow(row);

	if (parent) {
		focusRow(root, parent);
	}
}

/**
 * Right: unfold this branch, or descend into it when it is already open.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 */
function unfold(root, row) {
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
 *
 * @param {HTMLElement} row
 * @param {Direction} direction
 */
function move(row, direction) {
	const input = /** @type {HTMLInputElement | null} */ (
		row.querySelector(`${ACTIONS} form input[value="${direction}"]`)
	);
	const form = input?.form;
	const button = form?.querySelector('button[type="submit"]');

	if (form && button instanceof HTMLButtonElement && !button.disabled) {
		form.requestSubmit();
	}
}

/**
 * @param {HTMLElement} row
 */
function activate(row) {
	/** @type {HTMLAnchorElement | null} */ (row.querySelector('a.text'))?.click();
}

/**
 * Opens the create pane the row's kebab already links to.
 *
 * @param {HTMLElement} row
 * @param {'before' | 'after'} kind
 */
function add(row, kind) {
	/** @type {HTMLAnchorElement | null} */ (
		row.querySelector(`${ACTIONS} a[data-menu-add="${kind}"]`)
	)?.click();
}

/**
 * @param {HTMLElement} row
 */
function openKebab(row) {
	const trigger = /** @type {HTMLButtonElement | null} */ (
		row.querySelector(':scope > .menu-card > button[popovertarget]')
	);
	if (trigger) openMenu(trigger, 'first', row);
}

/**
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
	if (
		typing(event.target) ||
		(event.target instanceof Element &&
			event.target.closest('[data-action-menu], button[popovertarget]'))
	) {
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
		handled = vim() && moveBy(row, event.code, VIM_MOVES);
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
 *
 * @type {Record<string, Direction>}
 */
const ARROW_MOVES = {
	ArrowUp: 'up',
	ArrowDown: 'down',
	ArrowRight: 'in',
	ArrowLeft: 'out',
};

/** @type {Record<string, Direction>} */
const VIM_MOVES = {
	KeyK: 'up',
	KeyJ: 'down',
	KeyL: 'in',
	KeyH: 'out',
};

/**
 * @param {HTMLElement} row
 * @param {string} code
 * @param {Record<string, Direction>} map
 * @returns {boolean}
 */
function moveBy(row, code, map) {
	if (!(code in map)) {
		return false;
	}

	move(row, map[code]);

	return true;
}

/**
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
function plain(root, row, event) {
	const letters = vim();
	const key = letters ? (VIM_KEYS[event.key] ?? event.key) : event.key;
	const visible = visibleRows(root);

	switch (key) {
		case 'ArrowUp':
			step(root, row, -1);

			return true;
		case 'ArrowDown':
			step(root, row, 1);

			return true;
		case 'ArrowLeft':
			fold(root, row);

			return true;
		case 'ArrowRight':
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
		// `o` and `O` open a line below and above, exactly as in a vim buffer,
		// and have no equivalent in the base layer to fold onto. A child is
		// `o` followed by an indent, the way an outliner does it, so no key
		// has to mean "but nested".
		case 'o':
		case 'O':
			if (!letters) {
				return false;
			}

			add(row, key === 'o' ? 'after' : 'before');

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

/** @returns {() => void} */
export function install() {
	document.addEventListener('keydown', onKeydown);

	return () => {
		document.removeEventListener('keydown', onKeydown);
	};
}
