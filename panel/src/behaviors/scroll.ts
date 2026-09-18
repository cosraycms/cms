// A tree toggle re-renders the whole collection, so the list scroller comes
// back as a new element at the top. Remembering its position across the swap
// keeps the row that was clicked where it was.

interface RequestContext {
	sourceElement?: unknown;
}

const positions = new WeakMap<object, { left: number; top: number }>();

function context(event: Event): RequestContext | undefined {
	const ctx = (event as CustomEvent<{ ctx?: RequestContext }>).detail?.ctx;
	return ctx && typeof ctx === 'object' ? ctx : undefined;
}

function remember(event: Event): void {
	const ctx = context(event);
	const source = ctx?.sourceElement;

	if (!ctx || !(source instanceof Element) || !source.closest('[data-collection-toggle]')) {
		return;
	}

	const list = source.closest('.cms-collection')?.querySelector<HTMLElement>('.scroll');

	if (list) {
		positions.set(ctx, { left: list.scrollLeft, top: list.scrollTop });
	}
}

// Boosted links apply their own show position after `htmx:after:swap`, so the
// position has to be restored at the end of the swap lifecycle.
function restore(event: Event): void {
	const ctx = context(event);
	const position = ctx ? positions.get(ctx) : undefined;

	if (!ctx || !position) {
		return;
	}

	positions.delete(ctx);

	const list = document.querySelector<HTMLElement>('.cms-collection .scroll');

	if (list) {
		list.scrollLeft = position.left;
		list.scrollTop = position.top;
	}
}

export function install(): () => void {
	document.addEventListener('htmx:before:request', remember);
	document.addEventListener('htmx:finally:swap', restore);

	return () => {
		document.removeEventListener('htmx:before:request', remember);
		document.removeEventListener('htmx:finally:swap', restore);
	};
}
