// Manual order in collection listings. Rows of an arranged group carry
// `data-group` (their parent's uid, or empty for the collection's top level),
// a grip, and move up/down forms. Dragging a grip posts the drop through the
// server-rendered `[data-order-form]`; Ctrl/Cmd+Shift+Up/Down submits the
// focused row's own move form. Either way the move takes the boosted route
// and the listing comes back re-rendered, so nothing here keeps state beyond
// one swap. Without JavaScript the move forms still work.

/** @import { SortableEvent, MoveEvent } from 'sortablejs' */

/** @typedef {'up' | 'down' | 'link'} FocusKind */

const enhanced = /** @type {WeakSet<Element>} */ (new WeakSet());
/** The rows a dragged tree row folds away, restored when it lands. */
const folded = /** @type {WeakMap<HTMLElement, HTMLElement[]>} */ (new WeakMap());
/** The group's order when a drag starts, to tell a drop from a no-op. */
const started = /** @type {WeakMap<HTMLElement, string[]>} */ (new WeakMap());
/** What had focus when a move left, so the moved row can take it back. */
let returning = /** @type {FocusKind | null} */ (null);

/**
 * @param {Element} row
 * @returns {number}
 */
function depth(row) {
	return Number(/** @type {HTMLElement} */ (row).dataset.depth ?? 0);
}

/**
 * The uids of a group in document order.
 *
 * @param {HTMLElement} row
 * @returns {string[]}
 */
function members(row) {
	const body = row.parentElement;

	if (!body) {
		return [];
	}

	return [.../** @type {NodeListOf<HTMLElement>} */ (body.querySelectorAll('tr[data-group]'))]
		.filter((member) => member.dataset.group === row.dataset.group)
		.map((member) => member.dataset.uid ?? '');
}

/**
 * A tree row drags alone, so its expanded subtree folds away until it lands;
 * the re-rendered listing puts the subtree back under it.
 *
 * @param {HTMLElement} row
 */
export function fold(row) {
	const rows = [];
	const level = depth(row);

	for (
		let next = row.nextElementSibling;
		next instanceof HTMLElement;
		next = next.nextElementSibling
	) {
		if (depth(next) <= level) {
			break;
		}

		next.classList.add('is-drag-folded');
		rows.push(next);
	}

	folded.set(row, rows);
	started.set(row, members(row));
}

/**
 * @param {HTMLElement} row
 */
function unfold(row) {
	for (const hidden of folded.get(row) ?? []) {
		hidden.classList.remove('is-drag-folded');
	}

	folded.delete(row);
}

/**
 * Rows only change places within their own group.
 *
 * @param {HTMLElement} dragged
 * @param {HTMLElement} related
 * @returns {boolean}
 */
export function accepts(dragged, related) {
	return (
		related.dataset.group !== undefined &&
		related.dataset.group === dragged.dataset.group &&
		depth(related) === depth(dragged)
	);
}

/**
 * The group member a dropped row now follows, or precedes when it landed
 * first. Walking stops at the parent's row, and passes over the subtrees of
 * expanded siblings and anything folded away.
 *
 * @param {HTMLElement} row
 * @returns {{ after: string } | { before: string } | null}
 */
export function neighbour(row) {
	const level = depth(row);

	/**
	 * @param {'previousElementSibling' | 'nextElementSibling'} step
	 * @returns {string | null}
	 */
	const find = (step) => {
		for (let other = row[step]; other instanceof HTMLElement; other = other[step]) {
			if (other.classList.contains('is-drag-folded')) {
				continue;
			}

			if (depth(other) < level) {
				return null;
			}

			if (depth(other) === level && other.dataset.group === row.dataset.group) {
				return other.dataset.uid ?? null;
			}
		}

		return null;
	};

	const after = find('previousElementSibling');

	if (after !== null) {
		return { after };
	}

	const before = find('nextElementSibling');

	return before === null ? null : { before };
}

/**
 * Posts a drop through the listing's order form. Exported for the behavior
 * tests; Sortable's `onEnd` delegates here.
 *
 * @param {HTMLElement} row
 */
export function drop(row) {
	const before = started.get(row);
	started.delete(row);
	unfold(row);

	const order = members(row);

	if (before && before.join('\n') === order.join('\n')) {
		return;
	}

	const form = /** @type {HTMLFormElement | null} */ (document.querySelector('[data-order-form]'));
	const target = neighbour(row);

	if (!form || !target) {
		return;
	}

	/**
	 * @param {string} name
	 * @param {string} value
	 */
	const set = (name, value) => {
		const input = form.elements.namedItem(name);

		if (input instanceof HTMLInputElement) {
			input.value = value;
		}
	};

	set('node', row.dataset.uid ?? '');
	set('after', 'after' in target ? target.after : '');
	set('before', 'before' in target ? target.before : '');
	returning = null;
	form.requestSubmit();
}

async function initDrag() {
	const bodies = [
		.../** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll('.cms-collection tbody')),
	].filter((body) => !enhanced.has(body) && body.querySelector('[data-order-grip]'));

	if (bodies.length === 0) {
		return;
	}

	// Loaded on demand, so only arranged listings pay for the library.
	const { default: Sortable } = await import('sortablejs');

	for (const body of bodies) {
		if (enhanced.has(body)) {
			continue;
		}

		enhanced.add(body);
		new Sortable(body, {
			handle: '[data-order-grip]',
			draggable: 'tr[data-group]',
			animation: 150,
			fallbackOnBody: true,
			onStart: (/** @type {SortableEvent} */ event) => fold(event.item),
			onMove: (/** @type {MoveEvent} */ event) => accepts(event.dragged, event.related),
			onEnd: (/** @type {SortableEvent} */ event) => drop(event.item),
		});
	}
}

/**
 * @param {EventTarget | null} target
 * @returns {boolean}
 */
function typing(target) {
	return (
		target instanceof HTMLInputElement ||
		target instanceof HTMLTextAreaElement ||
		target instanceof HTMLSelectElement ||
		(target instanceof HTMLElement && target.isContentEditable)
	);
}

/** @type {Record<string, 'up' | 'down'>} */
const ARROW_MOVES = { ArrowUp: 'up', ArrowDown: 'down' };

/**
 * Ctrl/Cmd+Shift+Up/Down, the menu tree's keys for moving among siblings.
 * They submit the row's own form, whose disabled button already says
 * whether the move exists.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
	const direction = ARROW_MOVES[event.code];

	if (!direction || !event.shiftKey || !(event.metaKey || event.ctrlKey) || event.altKey) {
		return;
	}

	const target = event.target;

	if (typing(target) || !(target instanceof Element)) {
		return;
	}

	const row = target.closest('.cms-collection tr[data-group]');
	const button = row?.querySelector(`form[data-order-move="${direction}"] button`);

	if (!(button instanceof HTMLButtonElement)) {
		return;
	}

	event.preventDefault();

	if (!button.disabled) {
		remember(target);
		button.form?.requestSubmit();
	}
}

/**
 * A move button keeps focus on the same button of the moved row; any other
 * control in the row hands it to the row's title link.
 *
 * @param {Element} focused
 */
function remember(focused) {
	const move = focused.closest('form[data-order-move]');
	const direction = move instanceof HTMLElement ? move.dataset.orderMove : undefined;

	returning = direction === 'up' || direction === 'down' ? direction : 'link';
}

/**
 * @param {Event} event
 */
function onSubmit(event) {
	const form = event.target;

	const active = document.activeElement;

	// Only a focused button: Safari leaves focus where it was on a click,
	// and a key binding has already said where focus goes.
	if (
		form instanceof HTMLFormElement &&
		form.matches('form[data-order-move]') &&
		active instanceof Element &&
		form.contains(active)
	) {
		remember(active);
	}
}

function restoreFocus() {
	const kind = returning;
	returning = null;

	const row = document.querySelector('.cms-collection tr[data-moved]');

	if (!kind || !row) {
		return;
	}

	const button = row.querySelector(`form[data-order-move="${kind}"] button`);
	const target =
		button instanceof HTMLButtonElement && !button.disabled
			? button
			: row.querySelector('a.value.link');

	if (target instanceof HTMLElement) {
		target.focus({ preventScroll: true });
	}
}

// The moved row is marked once; the param leaves the address bar so a
// refresh or a copied link does not mark it again. htmx writes the URL
// after the swap event, so this runs behind it.
function stripMoved() {
	const url = new URL(window.location.href);

	if (!url.searchParams.has('moved')) {
		return;
	}

	url.searchParams.delete('moved');
	history.replaceState(history.state, '', url);
}

function onSwap() {
	void initDrag();
	restoreFocus();
	setTimeout(stripMoved, 0);
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('keydown', onKeydown);
	document.addEventListener('submit', onSubmit);
	document.addEventListener('htmx:after:swap', onSwap);
	void initDrag();
	stripMoved();

	return () => {
		document.removeEventListener('keydown', onKeydown);
		document.removeEventListener('submit', onSubmit);
		document.removeEventListener('htmx:after:swap', onSwap);
	};
}
