import { selectTab } from './tabs';

// The node inspector collapses to a strip of quick controls. The choice lives
// in a cookie the editor reads, so a page arrives in its state and never
// slides on load. The strip's published switch is an unnamed copy: the form
// submits the real one, which a save replaces out of band.

const ROOT = '[data-inspector]';
const PUBLISHED = 'editor-published-switch';

function collapse(inspector: HTMLElement, collapsed: boolean, remember = true): void {
	inspector.toggleAttribute('data-collapsed', collapsed);

	if (remember) {
		document.cookie = collapsed
			? 'cosray_inspector=collapsed; path=/; max-age=31536000; samesite=lax'
			: 'cosray_inspector=; path=/; max-age=0; samesite=lax';
	}
}

// Opens the inspector around a control an error jump targets, without
// taking that for the editor's choice.
export function revealInspector(control: Element): void {
	const inspector = control.closest<HTMLElement>(ROOT);

	if (inspector?.hasAttribute('data-collapsed')) {
		collapse(inspector, false, false);
	}
}

function click(event: Event): void {
	const target = event.target;
	const inspector = target instanceof Element ? target.closest<HTMLElement>(ROOT) : null;

	if (!(target instanceof Element) || !inspector) {
		return;
	}

	if (target.closest('[data-inspector-collapse]')) {
		collapse(inspector, true);
		inspector.querySelector<HTMLElement>('[data-inspector-expand]')?.focus();
		return;
	}

	const shortcut = target.closest<HTMLElement>('[data-inspector-open]');

	if (!shortcut && !target.closest('[data-inspector-expand]')) {
		return;
	}

	const tab = shortcut
		? document.getElementById(shortcut.dataset.inspectorOpen ?? '')
		: inspector.querySelector<HTMLElement>('[role="tab"][aria-selected="true"]');

	if (tab) {
		selectTab(tab);
	}

	collapse(inspector, false);
	tab?.focus();
}

function syncPublished(): void {
	const source = document.getElementById(PUBLISHED);

	if (!(source instanceof HTMLInputElement)) {
		return;
	}

	document.querySelectorAll<HTMLInputElement>('[data-inspector-published]').forEach((copy) => {
		copy.checked = source.checked;
	});
}

function change(event: Event): void {
	const target = event.target;

	if (!(target instanceof HTMLInputElement)) {
		return;
	}

	if (target.id === PUBLISHED) {
		syncPublished();
		return;
	}

	const source = document.getElementById(PUBLISHED);

	if (target.matches('[data-inspector-published]') && source instanceof HTMLInputElement) {
		source.checked = target.checked;
		source.dispatchEvent(new Event('change', { bubbles: true }));
	}
}

export function install(): () => void {
	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('htmx:after:swap', syncPublished);
	syncPublished();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('htmx:after:swap', syncPublished);
	};
}
