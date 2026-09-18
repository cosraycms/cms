import { selectTab } from './tabs';

// The node inspector collapses to a strip of quick controls. Beside the fields
// the choice lives in a cookie the editor reads, so a page arrives in its
// state and never slides on load. Narrower, the inspector starts collapsed
// and data-open lasts for the page; in the shell's middle band, whose query
// matches cms-inspector.css, it opens over the fields as a layer that Escape
// and a press outside close. The strip's published switch is an unnamed copy: the
// form submits the real one, which a save replaces out of band.

const ROOT = '[data-inspector]';
const PUBLISHED = 'editor-published-switch';
const BESIDE = '(width >= 75rem)';
const OVER = '(width >= 40rem) and (height >= 30rem) and (width < 75rem)';

let opener: HTMLElement | null = null;

function expand(inspector: HTMLElement, expanded: boolean, remember = true): void {
	if (!matchMedia(BESIDE).matches) {
		inspector.toggleAttribute('data-open', expanded);
		return;
	}

	inspector.toggleAttribute('data-collapsed', !expanded);

	if (remember) {
		document.cookie = expanded
			? 'cosray_inspector=; path=/; max-age=0; samesite=lax'
			: 'cosray_inspector=collapsed; path=/; max-age=31536000; samesite=lax';
	}
}

function close(inspector: HTMLElement): void {
	expand(inspector, false);

	const target = opener?.isConnected
		? opener
		: inspector.querySelector<HTMLElement>('[data-inspector-expand]');
	target?.focus();
}

function closeLayers(): void {
	document.querySelectorAll(`${ROOT}[data-open]`).forEach((inspector) => {
		inspector.removeAttribute('data-open');
	});
}

// Opens the inspector around a control an error jump targets, without
// taking that for the editor's choice.
export function revealInspector(control: Element): void {
	const inspector = control.closest<HTMLElement>(ROOT);

	if (inspector) {
		expand(inspector, true, false);
	}
}

function click(event: Event): void {
	const target = event.target;
	const inspector = target instanceof Element ? target.closest<HTMLElement>(ROOT) : null;

	if (!(target instanceof Element) || !inspector) {
		return;
	}

	if (target.closest('[data-inspector-collapse]')) {
		close(inspector);
		return;
	}

	const trigger = target.closest<HTMLElement>('[data-inspector-open], [data-inspector-expand]');

	if (!trigger) {
		return;
	}

	const tab = trigger.dataset.inspectorOpen
		? document.getElementById(trigger.dataset.inspectorOpen)
		: inspector.querySelector<HTMLElement>('[role="tab"][aria-selected="true"]');

	if (tab) {
		selectTab(tab);
	}

	opener = trigger;
	expand(inspector, true);
	tab?.focus();
}

function keydown(event: KeyboardEvent): void {
	const target = event.target;

	if (
		event.key !== 'Escape' ||
		event.defaultPrevented ||
		!(target instanceof Element) ||
		target.closest('dialog') ||
		!matchMedia(OVER).matches
	) {
		return;
	}

	const inspector = target.closest<HTMLElement>(`${ROOT}[data-open]`);

	if (inspector) {
		event.preventDefault();
		close(inspector);
	}
}

function pointerdown(event: Event): void {
	const target = event.target;

	if (
		matchMedia(OVER).matches &&
		!(target instanceof Element && target.closest(`${ROOT}, dialog, [popover]`))
	) {
		closeLayers();
	}
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
	const beside = matchMedia(BESIDE);

	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('keydown', keydown);
	document.addEventListener('pointerdown', pointerdown);
	document.addEventListener('htmx:after:swap', syncPublished);
	beside.addEventListener('change', closeLayers);
	syncPublished();

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('keydown', keydown);
		document.removeEventListener('pointerdown', pointerdown);
		document.removeEventListener('htmx:after:swap', syncPublished);
		beside.removeEventListener('change', closeLayers);
		opener = null;
	};
}
