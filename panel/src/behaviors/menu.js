// Menus area behavior: drag-and-drop reordering, the item form's type
// sections, and the search pickers for nodes and assets. The tree's own
// focus and collapse model lives in `menu-tree`, because a roving
// tabindex has nothing to do with a search box.
//
// Everything event-driven is a document-level delegated
// listener, so htmx swaps cannot orphan it; the Sortable instances are
// re-scanned after every swap instead. The pickers talk to the JSON
// search endpoints the element carries in `data-menu-picker-url`;
// without JavaScript the hidden uid input simply keeps its value and
// the kebab's move buttons stay the reorder path.

/** @import { SortableEvent } from 'sortablejs' */

/**
 * @typedef {object} PickerItem
 * @property {string} uid
 * @property {string} label
 * @property {string} sub
 */

const timers = /** @type {WeakMap<HTMLInputElement, ReturnType<typeof setTimeout>>} */ (
	new WeakMap()
);
const aborters = /** @type {WeakMap<HTMLInputElement, AbortController>} */ (new WeakMap());
const enhanced = /** @type {WeakSet<Element>} */ (new WeakSet());

/** @returns {Element | null} */
function tree() {
	return document.querySelector('.cms-menu-tree .tree');
}

async function initDrag() {
	const lists = [
		.../** @type {NodeListOf<HTMLElement>} */ (document.querySelectorAll('[data-menu-list]')),
	].filter((list) => !enhanced.has(list));

	if (lists.length === 0) {
		return;
	}

	// Loaded on demand, so only menu screens pay for the library.
	const { default: Sortable } = await import('sortablejs');

	for (const list of lists) {
		if (enhanced.has(list)) {
			continue;
		}

		enhanced.add(list);
		new Sortable(list, {
			group: 'cms-menu',
			handle: '[data-menu-grip]',
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65,
			onStart: () => tree()?.classList.add('is-dragging'),
			onEnd: (/** @type {SortableEvent} */ event) => {
				tree()?.classList.remove('is-dragging');
				submitMove(event.item, event.to, event.from, event.newIndex ?? 0, event.oldIndex ?? 0);
			},
		});
	}
}

/**
 * Posts a drop through the server-rendered `#menu-drag` form, so the
 * move rides the same boosted pipeline as every other tree action and
 * the response re-renders the tree (or rejects the move with a
 * notice). Exported for the behavior tests; `onEnd` delegates here.
 *
 * @param {HTMLElement} item
 * @param {HTMLElement} to
 * @param {HTMLElement} from
 * @param {number} newIndex
 * @param {number} oldIndex
 */
export function submitMove(item, to, from, newIndex, oldIndex) {
	if (to === from && newIndex === oldIndex) {
		return;
	}

	const uid = item.dataset.uid ?? '';
	const form = /** @type {HTMLFormElement | null} */ (document.querySelector('#menu-drag'));
	const template = form?.dataset.menuDragAction ?? '';

	if (!form || uid === '' || template === '') {
		return;
	}

	form.action = template.replace('__item__', encodeURIComponent(uid));
	const parent = form.elements.namedItem('parent');
	const index = form.elements.namedItem('index');

	if (parent instanceof HTMLInputElement) {
		parent.value = to.dataset.parent ?? '';
	}

	if (index instanceof HTMLInputElement) {
		index.value = String(newIndex);
	}

	form.requestSubmit();
}

/**
 * @param {Element} el
 * @returns {HTMLElement | null}
 */
function picker(el) {
	return el.closest('[data-menu-picker]');
}

/**
 * @param {HTMLElement} box
 * @returns {HTMLElement | null}
 */
function resultsOf(box) {
	return /** @type {HTMLElement | null} */ (box.querySelector('[data-menu-picker-results]'));
}

/**
 * @param {HTMLElement} box
 * @param {unknown} payload
 * @returns {PickerItem[]}
 */
function parse(box, payload) {
	const key = box.dataset.menuPicker ?? '';
	const rows =
		payload && typeof payload === 'object'
			? /** @type {Record<string, unknown>} */ (payload)[key]
			: null;

	if (!Array.isArray(rows)) {
		return [];
	}

	return rows.flatMap((row) => {
		if (!row || typeof row !== 'object') {
			return [];
		}

		const item = /** @type {Record<string, unknown>} */ (row);
		const uid = typeof item.uid === 'string' ? item.uid : '';
		const label =
			typeof item.title === 'string'
				? item.title
				: typeof item.filename === 'string'
					? item.filename
					: '';
		const sub =
			typeof item.typeLabel === 'string'
				? item.typeLabel
				: typeof item.kind === 'string'
					? item.kind
					: '';

		return uid === '' ? [] : [{ uid, label, sub }];
	});
}

/**
 * @param {HTMLElement} box
 * @param {PickerItem[]} items
 */
function render(box, items) {
	const list = resultsOf(box);

	if (!list) {
		return;
	}

	list.textContent = '';

	for (const item of items) {
		const option = document.createElement('button');
		option.type = 'button';
		option.dataset.menuPickerOption = item.uid;
		option.dataset.menuPickerLabel = item.label;

		const label = document.createElement('strong');
		label.textContent = item.label;
		option.append(label);

		if (item.sub !== '') {
			const sub = document.createElement('small');
			sub.textContent = item.sub;
			option.append(sub);
		}

		list.append(option);
	}

	list.hidden = items.length === 0;
}

/**
 * @param {HTMLInputElement} input
 */
async function search(input) {
	const box = picker(input);
	const url = box?.dataset.menuPickerUrl ?? '';

	if (!box || url === '') {
		return;
	}

	aborters.get(input)?.abort();
	const aborter = new AbortController();
	aborters.set(input, aborter);

	try {
		const response = await fetch(`${url}&q=${encodeURIComponent(input.value.trim())}`, {
			signal: aborter.signal,
			headers: { Accept: 'application/json' },
		});

		if (!response.ok) {
			return;
		}

		render(box, parse(box, await response.json()));
	} catch {
		// Aborted or failed searches leave the results as they are.
	}
}

/**
 * @param {Event} event
 */
function onInput(event) {
	const input = event.target;

	if (!(input instanceof HTMLInputElement) || !input.matches('[data-menu-picker-search]')) {
		return;
	}

	// Typing invalidates the previous selection; picking writes it back.
	const value = /** @type {HTMLInputElement | null} */ (
		picker(input)?.querySelector('[data-menu-picker-value]')
	);

	if (value) {
		value.value = '';
	}

	const timer = timers.get(input);

	if (timer !== undefined) {
		clearTimeout(timer);
	}

	timers.set(
		input,
		setTimeout(() => void search(input), 250),
	);
}

/**
 * @param {Event} event
 */
function onChange(event) {
	const select = event.target;

	if (!(select instanceof HTMLSelectElement) || !select.matches('[data-menu-type]')) {
		return;
	}

	const form = select.closest('form');

	/** @type {NodeListOf<HTMLElement>} */ (
		form?.querySelectorAll('[data-menu-section], [data-menu-section-hide]')
	).forEach((section) => {
		const show = section.dataset.menuSection;

		if (typeof show === 'string' && show !== '') {
			section.hidden = !show.split(' ').includes(select.value);

			return;
		}

		const hide = section.dataset.menuSectionHide ?? '';
		section.hidden = hide.split(' ').includes(select.value);
	});
}

/**
 * @param {HTMLElement} option
 */
function pick(option) {
	const box = picker(option);

	if (!box) {
		return;
	}

	const value = /** @type {HTMLInputElement | null} */ (
		box.querySelector('[data-menu-picker-value]')
	);
	const input = /** @type {HTMLInputElement | null} */ (
		box.querySelector('[data-menu-picker-search]')
	);

	if (value) {
		value.value = option.dataset.menuPickerOption ?? '';
	}

	if (input) {
		input.value = option.dataset.menuPickerLabel ?? '';
	}

	const list = resultsOf(box);

	if (list) {
		list.hidden = true;
		list.textContent = '';
	}
}

/**
 * @param {Event} event
 */
function onClick(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const option = target.closest('[data-menu-picker-option]');

	if (option instanceof HTMLElement) {
		pick(option);

		return;
	}

	// A click outside any picker closes every open result list.
	if (!target.closest('[data-menu-picker]')) {
		/** @type {NodeListOf<HTMLElement>} */ (
			document.querySelectorAll('[data-menu-picker-results]')
		).forEach((list) => {
			list.hidden = true;
		});
	}
}

/**
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
	const target = event.target;

	if (
		event.key === 'Enter' &&
		target instanceof HTMLInputElement &&
		target.matches('[data-menu-picker-search]')
	) {
		// Enter must not submit the form with a half-typed search.
		event.preventDefault();
	}
}

/** @returns {() => void} */
export function install() {
	const rescan = () => {
		void initDrag();
	};

	document.addEventListener('click', onClick);
	document.addEventListener('input', onInput);
	document.addEventListener('change', onChange);
	document.addEventListener('keydown', onKeydown);
	document.addEventListener('htmx:after:swap', rescan);
	rescan();

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onChange);
		document.removeEventListener('keydown', onKeydown);
		document.removeEventListener('htmx:after:swap', rescan);
	};
}
