// The menu tree's focus model. The list is an ARIA `tree` with a roving
// tabindex: exactly one row is tabbable and the arrow keys move that, which
// is what makes the whole tree a single tab stop and frees Tab for
// indenting. Collapse state is kept in localStorage per menu, because every
// move re-renders the screen and would otherwise re-expand everything.
//
// Nothing here posts anything. Moves are the kebab's own forms, submitted by
// the key bindings, so the tree keeps working without JavaScript.

const STORE = 'cosray:menu-collapsed:';

/** Whether the tree held focus before the swap that is about to replace it. */
let held = false;

/** @returns {HTMLElement | null} */
export function tree() {
	return /** @type {HTMLElement | null} */ (document.querySelector('[data-menu-tree]'));
}

/**
 * @param {HTMLElement} root
 * @returns {HTMLElement[]}
 */
export function rows(root) {
	return [.../** @type {NodeListOf<HTMLElement>} */ (root.querySelectorAll('[role="treeitem"]'))];
}

/**
 * Rows the user can reach: everything not sitting inside a collapsed branch.
 *
 * @param {HTMLElement} root
 * @returns {HTMLElement[]}
 */
export function visibleRows(root) {
	return rows(root).filter(
		// Starting at the parent, so a collapsed row stays visible itself.
		(row) => row.parentElement?.closest('.menu-node.is-collapsed') == null,
	);
}

/**
 * @param {HTMLElement} root
 * @returns {HTMLElement | null}
 */
export function currentRow(root) {
	const active = document.activeElement;

	if (active instanceof HTMLElement && active.matches('[role="treeitem"]')) {
		return active;
	}

	return /** @type {HTMLElement | null} */ (root.querySelector('[role="treeitem"][tabindex="0"]'));
}

/**
 * @param {HTMLElement} row
 * @returns {boolean}
 */
export function expandable(row) {
	return row.getAttribute('aria-expanded') !== null;
}

/**
 * @param {HTMLElement} row
 * @returns {boolean}
 */
export function collapsed(row) {
	return row.classList.contains('is-collapsed');
}

/**
 * @param {HTMLElement} root
 * @returns {string}
 */
function menuOf(root) {
	return root.dataset.menuTree ?? '';
}

/**
 * @param {string} menu
 * @returns {Set<string>}
 */
function stored(menu) {
	try {
		const raw = localStorage.getItem(STORE + menu);
		/** @type {unknown} */
		const ids = raw === null ? [] : JSON.parse(raw);

		return new Set(
			Array.isArray(ids)
				? ids.filter(/** @returns {id is string} */ (id) => typeof id === 'string')
				: [],
		);
	} catch {
		// A private window, cleared site data, or blocked storage: the tree
		// simply opens fully.
		return new Set();
	}
}

/**
 * @param {HTMLElement} root
 */
function persist(root) {
	const ids = rows(root)
		.filter(collapsed)
		.map((row) => row.dataset.uid ?? '');

	try {
		localStorage.setItem(STORE + menuOf(root), JSON.stringify(ids));
	} catch {
		// Not being able to remember is not a reason to fail the toggle.
	}
}

/**
 * @param {HTMLElement} row
 * @param {boolean} value
 */
export function setCollapsed(row, value) {
	if (!expandable(row)) {
		return;
	}

	row.classList.toggle('is-collapsed', value);
	row.setAttribute('aria-expanded', String(!value));
}

/**
 * Moves the roving tabindex, and the focus with it when asked.
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 * @param {boolean} [move]
 */
export function focusRow(root, row, move = true) {
	for (const other of rows(root)) {
		other.tabIndex = -1;
	}

	row.tabIndex = 0;

	if (move) {
		row.focus();
		// Optional like the field-error scroll: jsdom has no implementation.
		row.scrollIntoView?.({ block: 'nearest' });
	}
}

/**
 * Re-applies the remembered collapse state and points the roving tabindex at
 * the selected row — the one a move redirect just named. Focus follows only
 * when the tree had it, so opening a menu does not steal it from elsewhere.
 */
export function restore() {
	const root = tree();

	if (!root) {
		held = false;

		return;
	}

	const ids = stored(menuOf(root));

	for (const row of rows(root)) {
		setCollapsed(row, ids.has(row.dataset.uid ?? ''));
	}

	const selected = /** @type {HTMLElement | null} */ (
		/** @type {HTMLElement | null} */ (root.querySelector('.menu-card.is-selected'))?.closest(
			'[role="treeitem"]',
		)
	);
	const target = selected ?? visibleRows(root)[0];

	if (target) {
		focusRow(root, target, held);
	}
}

/**
 * @param {Event} event
 * @returns {boolean}
 */
function onToggle(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return false;
	}

	const toggle = target.closest('[data-menu-collapse]');
	const root = tree();
	const row = /** @type {HTMLElement | null} */ (toggle?.closest('[role="treeitem"]'));

	if (!root || !row) {
		return false;
	}

	setCollapsed(row, !collapsed(row));
	persist(root);
	focusRow(root, row);

	return true;
}

/**
 * @param {HTMLElement} root
 * @param {HTMLElement} row
 * @param {boolean} value
 */
export function toggle(root, row, value) {
	setCollapsed(row, value);
	persist(root);
}

/**
 * Whether the tree still owns the focus. Deliberately not driven by
 * `focusout`: a move replaces the screen, and a focused row being removed
 * looks exactly like the user leaving — which would drop the focus every
 * time and make consecutive moves impossible.
 *
 * @param {Event} event
 */
function onLeaveOrEnter(event) {
	const root = tree();
	const target = event.target;

	if (root) {
		held = target instanceof Node && root.contains(target);
	}
}

/**
 * Lets a keyboard user out again, now that Tab means something else.
 *
 * @param {HTMLElement} root
 */
export function release(root) {
	held = false;
	root.focus();
}

/** @returns {() => void} */
export function install() {
	// A fresh install is a fresh page as far as focus goes.
	held = false;

	/**
	 * @param {Event} event
	 */
	const onClick = (event) => {
		onToggle(event);
	};

	document.addEventListener('click', onClick);
	// Both, because a click does not focus a link in every browser.
	document.addEventListener('focusin', onLeaveOrEnter);
	document.addEventListener('click', onLeaveOrEnter);
	document.addEventListener('htmx:after:swap', restore);
	restore();

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('focusin', onLeaveOrEnter);
		document.removeEventListener('click', onLeaveOrEnter);
		document.removeEventListener('htmx:after:swap', restore);
	};
}
