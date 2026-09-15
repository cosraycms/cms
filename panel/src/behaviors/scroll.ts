// The document scrolls, so a navigation swap starts the new page at the top
// itself. A partial swap (a form post answered out of band, a re-rendered
// paths box) leaves the position alone, and so does a history restore, which
// the browser positions. The page head sticks below the masthead and the
// rails stick below the head, whose height depends on how its actions wrap:
// it is measured into --cms-head-height on the page for the CSS offsets.

const NAVIGATION_TARGETS = new Set(['main', 'frame']);

let observer: ResizeObserver | undefined;

function isNavigation(event: Event): boolean {
	const target = (event as CustomEvent<{ ctx?: { target?: unknown } }>).detail?.ctx?.target;

	if (target instanceof Element) {
		return NAVIGATION_TARGETS.has(target.id);
	}

	return typeof target === 'string' && NAVIGATION_TARGETS.has(target.replace(/^#/, ''));
}

function measure(entries: ResizeObserverEntry[]): void {
	for (const entry of entries) {
		const height =
			entry.borderBoxSize?.[0]?.blockSize ?? entry.target.getBoundingClientRect().height;
		entry.target.parentElement?.style.setProperty('--cms-head-height', `${height}px`);
	}
}

function observe(): void {
	observer?.disconnect();

	const head = document.querySelector('#main > .page > .head');

	if (head && typeof ResizeObserver !== 'undefined') {
		observer ??= new ResizeObserver(measure);
		observer.observe(head);
	}
}

function onSwap(event: Event): void {
	if (isNavigation(event)) {
		window.scrollTo(0, 0);
	}

	observe();
}

export function install(): () => void {
	document.addEventListener('htmx:after:swap', onSwap);
	observe();

	return () => {
		document.removeEventListener('htmx:after:swap', onSwap);
		observer?.disconnect();
		observer = undefined;
	};
}
