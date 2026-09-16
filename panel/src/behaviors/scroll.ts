// The document scrolls, so a navigation swap starts the new page at the top
// itself. A partial swap (a form post answered out of band, a re-rendered
// paths box) leaves the position alone, and so does a history restore, which
// the browser positions. The page head sticks below the masthead and the
// rails stick below the head, whose height depends on how its actions wrap:
// it is measured into --cms-head-height on the page for the CSS offsets.

const NAVIGATION_TARGETS = new Set(['main', 'frame']);

interface RequestContext {
	sourceElement?: unknown;
	target?: unknown;
}

interface ScrollPosition {
	documentLeft: number;
	documentTop: number;
	list: { left: number; top: number } | null;
}

let observer: ResizeObserver | undefined;
const positions = new WeakMap<object, ScrollPosition>();

function context(event: Event): RequestContext | undefined {
	const ctx = (event as CustomEvent<{ ctx?: RequestContext }>).detail?.ctx;
	return ctx && typeof ctx === 'object' ? ctx : undefined;
}

function isNavigation(event: Event): boolean {
	const target = context(event)?.target;

	if (target instanceof Element) {
		return NAVIGATION_TARGETS.has(target.id);
	}

	return typeof target === 'string' && NAVIGATION_TARGETS.has(target.replace(/^#/, ''));
}

function remember(event: Event): void {
	const ctx = context(event);
	const source = ctx?.sourceElement;

	if (!ctx || !(source instanceof Element) || !source.closest('[data-collection-toggle]')) {
		return;
	}

	const list = source.closest('.cms-collection')?.querySelector<HTMLElement>('.scroll');
	positions.set(ctx, {
		documentLeft: window.scrollX,
		documentTop: window.scrollY,
		list: list ? { left: list.scrollLeft, top: list.scrollTop } : null,
	});
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
	const ctx = context(event);

	if ((!ctx || !positions.has(ctx)) && isNavigation(event)) {
		window.scrollTo(0, 0);
	}

	observe();
}

// Boosted links apply their own show position after `htmx:after:swap`, so the
// collection position has to be restored at the end of the swap lifecycle.
function restore(event: Event): void {
	const ctx = context(event);
	const position = ctx ? positions.get(ctx) : undefined;

	if (!position || !ctx) {
		return;
	}

	positions.delete(ctx);

	const list = document.querySelector<HTMLElement>('.cms-collection .scroll');

	if (list && position.list) {
		list.scrollLeft = position.list.left;
		list.scrollTop = position.list.top;
	}

	window.scrollTo(position.documentLeft, position.documentTop);
}

export function install(): () => void {
	document.addEventListener('htmx:before:request', remember);
	document.addEventListener('htmx:after:swap', onSwap);
	document.addEventListener('htmx:finally:swap', restore);
	observe();

	return () => {
		document.removeEventListener('htmx:before:request', remember);
		document.removeEventListener('htmx:after:swap', onSwap);
		document.removeEventListener('htmx:finally:swap', restore);
		observer?.disconnect();
		observer = undefined;
	};
}
