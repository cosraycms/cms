// Tabs on a server-rendered screen. A [data-tabs] block holds a tablist of
// [role="tab"] buttons, each naming its panel through aria-controls. The
// markup arrives with one tab selected and the other panels hidden; the
// behavior only moves the selection, with one tab stop and the arrow keys.

const ROOT = '[data-tabs]';
const TAB = '[role="tab"]';

function tabs(root: Element): HTMLElement[] {
	return Array.from(root.querySelectorAll<HTMLElement>(TAB));
}

function panel(tab: HTMLElement): HTMLElement | null {
	const id = tab.getAttribute('aria-controls') ?? '';

	return id === '' ? null : document.getElementById(id);
}

export function selectTab(tab: HTMLElement): void {
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
export function revealTab(control: Element): void {
	const target = control.closest<HTMLElement>('[role="tabpanel"]');
	const root = target?.closest(ROOT);

	if (!target || !root) {
		return;
	}

	const tab = tabs(root).find((candidate) => candidate.getAttribute('aria-controls') === target.id);

	if (tab && tab.getAttribute('aria-selected') !== 'true') {
		selectTab(tab);
	}
}

function click(event: Event): void {
	const target = event.target;
	const tab = target instanceof Element ? target.closest<HTMLElement>(TAB) : null;

	if (tab?.closest(ROOT)) {
		selectTab(tab);
	}
}

function keydown(event: KeyboardEvent): void {
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

export function install(): () => void {
	document.addEventListener('click', click);
	document.addEventListener('keydown', keydown);

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('keydown', keydown);
	};
}
