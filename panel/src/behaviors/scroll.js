// A tree toggle re-renders the whole collection, so the list scroller comes
// back as a new element at the top. Remembering its position across the swap
// keeps the row that was clicked where it was.

/**
 * @typedef {object} RequestContext
 * @property {unknown} [sourceElement]
 */

const positions = /** @type {WeakMap<object, { left: number; top: number }>} */ (new WeakMap());

/**
 * @param {Event} event
 * @returns {RequestContext | undefined}
 */
function context(event) {
	const ctx = /** @type {CustomEvent<{ ctx?: RequestContext }>} */ (event).detail?.ctx;
	return ctx && typeof ctx === 'object' ? ctx : undefined;
}

/**
 * @param {Event} event
 */
function remember(event) {
	const ctx = context(event);
	const source = ctx?.sourceElement;

	if (!ctx || !(source instanceof Element) || !source.closest('[data-collection-toggle]')) {
		return;
	}

	const list = /** @type {HTMLElement | null} */ (
		source.closest('.cms-collection')?.querySelector('.scroll')
	);

	if (list) {
		positions.set(ctx, { left: list.scrollLeft, top: list.scrollTop });
	}
}

// Boosted links apply their own show position after `htmx:after:swap`, so the
// position has to be restored at the end of the swap lifecycle.
/**
 * @param {Event} event
 */
function restore(event) {
	const ctx = context(event);
	const position = ctx ? positions.get(ctx) : undefined;

	if (!ctx || !position) {
		return;
	}

	positions.delete(ctx);

	const list = /** @type {HTMLElement | null} */ (
		document.querySelector('.cms-collection .scroll')
	);

	if (list) {
		list.scrollLeft = position.left;
		list.scrollTop = position.top;
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('htmx:before:request', remember);
	document.addEventListener('htmx:finally:swap', restore);

	return () => {
		document.removeEventListener('htmx:before:request', remember);
		document.removeEventListener('htmx:finally:swap', restore);
	};
}
