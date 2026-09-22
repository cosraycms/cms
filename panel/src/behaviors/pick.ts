// The field's block picker, borrowed for an insertion of someone else's:
// a ghost fills its gap with the chosen type, a split puts it beside the
// block it splits. While a menu is armed, a choice or the catalog picked
// from it goes to the armed callbacks, and the repeater never sees the
// click that would have appended at the footer. Closing the menu disarms.

export type Adder = { picker: string } | { type: string };

/** How a blocks field adds a block: its footer picker, or its one type outright. */
export function adder(field: HTMLElement): Adder | null {
	const button = field.querySelector<HTMLElement>(':scope > [data-repeater-footer] > button');
	const picker = button?.getAttribute('popovertarget');
	const type = button?.getAttribute('data-repeater-add');

	return picker ? { picker } : type ? { type } : null;
}

let armed: {
	menu: HTMLElement;
	choose: (type: string | null) => void;
	catalog: () => void;
} | null = null;

export function arm(
	menu: HTMLElement,
	choose: (type: string | null) => void,
	catalog: () => void,
): void {
	armed = { menu, choose, catalog };
}

// Runs in the capture phase after the menu library's own handler, which
// has already closed the menu under the clicked choice.
function onClick(event: MouseEvent): void {
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

function onToggle(event: Event): void {
	if (armed && event.target === armed.menu && (event as ToggleEvent).newState === 'closed') {
		armed = null;
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick, true);
	document.addEventListener('toggle', onToggle, true);

	return () => {
		document.removeEventListener('click', onClick, true);
		document.removeEventListener('toggle', onToggle, true);
		armed = null;
	};
}
