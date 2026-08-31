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

export function tree(): HTMLElement | null {
	return document.querySelector<HTMLElement>('[data-menu-tree]');
}

export function rows(root: HTMLElement): HTMLElement[] {
	return [...root.querySelectorAll<HTMLElement>('[role="treeitem"]')];
}

/** Rows the user can reach: everything not sitting inside a collapsed branch. */
export function visibleRows(root: HTMLElement): HTMLElement[] {
	return rows(root).filter(
		// Starting at the parent, so a collapsed row stays visible itself.
		(row) => row.parentElement?.closest('.menu-node.is-collapsed') == null,
	);
}

export function currentRow(root: HTMLElement): HTMLElement | null {
	const active = document.activeElement;

	if (active instanceof HTMLElement && active.matches('[role="treeitem"]')) {
		return active;
	}

	return root.querySelector<HTMLElement>('[role="treeitem"][tabindex="0"]');
}

export function expandable(row: HTMLElement): boolean {
	return row.getAttribute('aria-expanded') !== null;
}

export function collapsed(row: HTMLElement): boolean {
	return row.classList.contains('is-collapsed');
}

function menuOf(root: HTMLElement): string {
	return root.dataset.menuTree ?? '';
}

function stored(menu: string): Set<string> {
	try {
		const raw = localStorage.getItem(STORE + menu);
		const ids: unknown = raw === null ? [] : JSON.parse(raw);

		return new Set(
			Array.isArray(ids) ? ids.filter((id): id is string => typeof id === 'string') : [],
		);
	} catch {
		// A private window, cleared site data, or blocked storage: the tree
		// simply opens fully.
		return new Set();
	}
}

function persist(root: HTMLElement): void {
	const ids = rows(root)
		.filter(collapsed)
		.map((row) => row.dataset.uid ?? '');

	try {
		localStorage.setItem(STORE + menuOf(root), JSON.stringify(ids));
	} catch {
		// Not being able to remember is not a reason to fail the toggle.
	}
}

export function setCollapsed(row: HTMLElement, value: boolean): void {
	if (!expandable(row)) {
		return;
	}

	row.classList.toggle('is-collapsed', value);
	row.setAttribute('aria-expanded', String(!value));
}

/** Moves the roving tabindex, and the focus with it when asked. */
export function focusRow(root: HTMLElement, row: HTMLElement, move = true): void {
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
export function restore(): void {
	const root = tree();

	if (!root) {
		held = false;

		return;
	}

	const ids = stored(menuOf(root));

	for (const row of rows(root)) {
		setCollapsed(row, ids.has(row.dataset.uid ?? ''));
	}

	const selected = root.querySelector<HTMLElement>('.menu-card.is-selected')?.closest('li');
	const target = selected instanceof HTMLElement ? selected : visibleRows(root)[0];

	if (target) {
		focusRow(root, target, held);
	}
}

function onToggle(event: Event): boolean {
	const target = event.target;

	if (!(target instanceof Element)) {
		return false;
	}

	const toggle = target.closest('[data-menu-collapse]');
	const root = tree();
	const row = toggle?.closest<HTMLElement>('[role="treeitem"]');

	if (!root || !row) {
		return false;
	}

	setCollapsed(row, !collapsed(row));
	persist(root);
	focusRow(root, row);

	return true;
}

export function toggle(root: HTMLElement, row: HTMLElement, value: boolean): void {
	setCollapsed(row, value);
	persist(root);
}

function onFocusIn(event: FocusEvent): void {
	const root = tree();
	const target = event.target;

	if (root && target instanceof Node && root.contains(target)) {
		held = true;
	}
}

function onFocusOut(event: FocusEvent): void {
	const root = tree();
	const next = event.relatedTarget;

	if (root && (!(next instanceof Node) || !root.contains(next))) {
		held = false;
	}
}

/** Lets a keyboard user out again, now that Tab means something else. */
export function release(root: HTMLElement): void {
	held = false;
	root.focus();
}

export function install(): () => void {
	// A fresh install is a fresh page as far as focus goes.
	held = false;

	const onClick = (event: Event): void => {
		onToggle(event);
	};

	document.addEventListener('click', onClick);
	document.addEventListener('focusin', onFocusIn);
	document.addEventListener('focusout', onFocusOut);
	document.addEventListener('htmx:after:swap', restore);
	restore();

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('focusin', onFocusIn);
		document.removeEventListener('focusout', onFocusOut);
		document.removeEventListener('htmx:after:swap', restore);
	};
}
