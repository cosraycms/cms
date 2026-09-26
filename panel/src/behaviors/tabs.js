// Tabs on a server-rendered screen. A [data-tabs] block holds a tablist of
// [role="tab"] buttons, each naming its panel through aria-controls. The
// markup arrives with one tab selected and the other panels hidden; the
// behavior only moves the selection, with one tab stop and the arrow keys.

const ROOT = '[data-tabs]';
const TAB = '[role="tab"]';

/**
 * @param {Element} root
 * @returns {HTMLElement[]}
 */
function tabs(root) {
	return Array.from(/** @type {NodeListOf<HTMLElement>} */ (root.querySelectorAll(TAB)));
}

/**
 * @param {HTMLElement} tab
 * @returns {HTMLElement | null}
 */
function panel(tab) {
	const id = tab.getAttribute('aria-controls') ?? '';

	return id === '' ? null : document.getElementById(id);
}

/**
 * @param {HTMLElement} tab
 */
export function selectTab(tab) {
	const root = tab.closest(ROOT);

	if (!root) {
		return;
	}

	for (const other of tabs(root)) {
		const active = other === tab;
		other.setAttribute('aria-selected', String(active));
		other.tabIndex = active ? 0 : -1;

		const target = panel(other);

		if (target) {
			target.hidden = !active;
		}
	}
}

// Brings the tab holding a control to the front, for a jump from the
// error summary.
/**
 * @param {Element} control
 */
export function revealTab(control) {
	const target = /** @type {HTMLElement | null} */ (control.closest('[role="tabpanel"]'));
	const root = target?.closest(ROOT);

	if (!target || !root) {
		return;
	}

	const tab = tabs(root).find((candidate) => candidate.getAttribute('aria-controls') === target.id);

	if (tab && tab.getAttribute('aria-selected') !== 'true') {
		selectTab(tab);
	}
}

/**
 * @param {Event} event
 */
function click(event) {
	const target = event.target;
	const tab =
		target instanceof Element ? /** @type {HTMLElement | null} */ (target.closest(TAB)) : null;

	if (tab?.closest(ROOT)) {
		selectTab(tab);
	}
}

/**
 * @param {KeyboardEvent} event
 */
function keydown(event) {
	const target = event.target;

	if (
		event.altKey ||
		event.ctrlKey ||
		event.metaKey ||
		event.shiftKey ||
		!(target instanceof HTMLElement) ||
		!target.matches(TAB)
	) {
		return;
	}

	const root = target.closest(ROOT);
	const all = root ? tabs(root) : [];
	const index = all.indexOf(target);
	let next = -1;

	if (event.key === 'Home') {
		next = 0;
	} else if (event.key === 'End') {
		next = all.length - 1;
	} else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
		next = (index - 1 + all.length) % all.length;
	} else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
		next = (index + 1) % all.length;
	}

	const tab = all[next];

	if (!tab) {
		return;
	}

	event.preventDefault();
	selectTab(tab);
	tab.focus();
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);
	document.addEventListener('keydown', keydown);

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('keydown', keydown);
	};
}
