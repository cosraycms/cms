// The field's block picker, borrowed for an insertion of someone else's:
// a ghost fills its gap with the chosen type, a split puts it beside the
// block it splits. While a menu is armed, a choice or the catalog picked
// from it goes to the armed callbacks, and the repeater never sees the
// click that would have appended at the footer. Closing the menu disarms.

/** @typedef {{ picker: string } | { type: string }} Adder */

/**
 * How a blocks field adds a block: its footer picker, or its one type outright.
 *
 * @param {HTMLElement} field
 * @returns {Adder | null}
 */
export function adder(field) {
	const button = /** @type {HTMLElement | null} */ (
		field.querySelector(':scope > [data-repeater-footer] > button')
	);
	const picker = button?.getAttribute('popovertarget');
	const type = button?.getAttribute('data-repeater-add');

	return picker ? { picker } : type ? { type } : null;
}

/** @type {{ menu: HTMLElement; choose: (type: string | null) => void; catalog: () => void } | null} */
let armed = null;

/**
 * @param {HTMLElement} menu
 * @param {(type: string | null) => void} choose
 * @param {() => void} catalog
 */
export function arm(menu, choose, catalog) {
	armed = { menu, choose, catalog };
}

// Runs in the capture phase after the menu library's own handler, which
// has already closed the menu under the clicked choice.
/**
 * @param {MouseEvent} event
 */
function onClick(event) {
	const target = event.target instanceof Element ? event.target : null;

	if (!armed || !target || !armed.menu.contains(target)) {
		return;
	}

	const choice = target.closest('[data-repeater-add]');
	const catalog = target.closest('[data-block-catalog-open]');

	if (!choice && !catalog) {
		return;
	}

	event.stopPropagation();

	const { choose, catalog: open } = armed;

	armed = null;

	if (choice) {
		choose(choice.getAttribute('data-repeater-add') || null);
	} else {
		open();
	}
}

/**
 * @param {Event} event
 */
function onToggle(event) {
	if (
		armed &&
		event.target === armed.menu &&
		/** @type {ToggleEvent} */ (event).newState === 'closed'
	) {
		armed = null;
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', onClick, true);
	document.addEventListener('toggle', onToggle, true);

	return () => {
		document.removeEventListener('click', onClick, true);
		document.removeEventListener('toggle', onToggle, true);
		armed = null;
	};
}
