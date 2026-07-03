import '../styles/panel.css';

const mainSelector = '#main';
const editorSelector = '[data-cosray-node-editor]';
const cleanups: Array<() => void> = [];

let editor: Promise<typeof import('./editor/main')> | null = null;

const currentPath = () => window.location.pathname.replace(/\/$/, '') || '/';

const linkPath = (link: HTMLAnchorElement) => {
	try {
		return new URL(link.href, window.location.href).pathname.replace(/\/$/, '') || '/';
	} catch {
		return '';
	}
};

const isElement = (value: unknown): value is Element => value instanceof Element;

function listen<K extends keyof DocumentEventMap>(
	type: K,
	listener: (event: DocumentEventMap[K]) => void,
): void {
	document.addEventListener(type, listener);
	cleanups.push(() => document.removeEventListener(type, listener));
}

function updateNavigation(): void {
	const path = currentPath();

	document.querySelectorAll('.nav-link[aria-current]').forEach((link) => {
		link.removeAttribute('aria-current');
	});

	document.querySelectorAll<HTMLAnchorElement>('.nav-link[href]').forEach((link) => {
		if (linkPath(link) === path) {
			link.setAttribute('aria-current', 'page');
		}
	});
}

function focusSearch(event: KeyboardEvent): void {
	if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
		return;
	}

	const target = event.target;

	if (
		target instanceof HTMLInputElement ||
		target instanceof HTMLTextAreaElement ||
		target instanceof HTMLSelectElement ||
		(target instanceof HTMLElement && target.isContentEditable)
	) {
		return;
	}

	const search = document.querySelector('.search input[type="search"]');

	if (search instanceof HTMLInputElement) {
		event.preventDefault();
		search.focus();
		search.select();
	}
}

function hasEditor(root: ParentNode): boolean {
	return (
		(isElement(root) && root.matches(editorSelector)) || root.querySelector(editorSelector) !== null
	);
}

async function mountEditor(root: ParentNode = document): Promise<void> {
	if (!hasEditor(root)) {
		return;
	}

	editor ??= import('./editor/main');
	(await editor).mountEditor(root);
}

// A boosted navigation swaps a fragment whose root is itself #main into the
// existing #main, so htmx's innerHTML swap nests the fresh #main inside the
// previous one, leaving the outer wrapper carrying the last full page's class.
// Unwrap it so a single #main (with the current page's class) sits directly
// under .main — the arrangement the layout's scroll rules assume.
function denestMain(): void {
	const outer = document.querySelector(mainSelector);
	const inner = outer?.querySelector(`:scope > ${mainSelector}`);

	if (outer && inner) {
		outer.replaceWith(inner);
	}
}

function updateNavigationAfterSwap(): void {
	denestMain();
	updateNavigation();
	void mountEditor();
}

listen('keydown', focusSearch);
listen('htmx:afterSwap' as keyof DocumentEventMap, updateNavigationAfterSwap);
listen('htmx:after:swap' as keyof DocumentEventMap, updateNavigationAfterSwap);
listen('htmx:pushedIntoHistory' as keyof DocumentEventMap, updateNavigation);
listen('htmx:after:history:update' as keyof DocumentEventMap, updateNavigation);

updateNavigation();
void mountEditor();

if (import.meta.hot) {
	import.meta.hot.dispose(() => {
		while (cleanups.length > 0) {
			cleanups.pop()?.();
		}
	});
}
