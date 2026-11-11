// Questions asked at the button that asks them: a popover marked
// data-confirm opens below or above the button whose popovertarget names
// it, aligned to its start or, with data-align="end", its end. It follows
// the button while open and closes once the button scrolls away. Cancel
// closes it, and so does its submit button on the way to the form it
// names; focus goes back to the button either way.

import { placement } from '../lib/action-menu.js';

const POPOVER = '[data-confirm]';
// The popover's distance from its button, and from the window's edges.
const GAP = 6;
const EDGE = 8;

/** @type {HTMLElement | null} */
let invoker = null;
/** @type {{ popover: HTMLElement, button: HTMLElement, frame: number } | null} */
let active = null;

/**
 * @param {Element | null} element
 * @returns {HTMLElement | null}
 */
function questionOf(element) {
	const button = element?.closest('button[popovertarget]');
	const popover = document.getElementById(button?.getAttribute('popovertarget') ?? '');

	return popover?.matches(POPOVER) ? popover : null;
}

/**
 * @param {HTMLElement} popover
 */
function close(popover) {
	if (popover.matches(':popover-open')) {
		popover.hidePopover();
	}
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	const target = event.target instanceof Element ? event.target : null;

	if (questionOf(target)) {
		invoker = /** @type {HTMLElement} */ (target?.closest('button[popovertarget]'));
		return;
	}

	const popover = target?.closest(POPOVER);

	if (popover instanceof HTMLElement && target?.closest('[data-confirm-cancel], [type="submit"]')) {
		close(popover);
	}
}

/**
 * @param {Event} event
 */
function toggle(event) {
	const popover = event.target;

	if (!(popover instanceof HTMLElement) || !popover.matches(POPOVER)) {
		return;
	}

	if (/** @type {ToggleEvent} */ (event).newState === 'closed') {
		finish(popover);
		return;
	}

	const button =
		invoker?.getAttribute('popovertarget') === popover.id
			? invoker
			: document.querySelector(`button[popovertarget="${CSS.escape(popover.id)}"]`);
	invoker = null;

	if (!(button instanceof HTMLElement)) {
		return;
	}

	if (active) {
		close(active.popover);
	}

	const state = { popover, button, frame: 0 };
	active = state;

	const position = () => {
		if (active !== state) {
			return;
		}

		if (
			!button.isConnected ||
			!popover.isConnected ||
			!button.checkVisibility({ visibilityProperty: true, opacityProperty: true })
		) {
			close(popover);
			return;
		}

		const point = placement(button, popover, popover.dataset.align === 'end' ? 'end' : 'start');

		if (!point.visible) {
			close(popover);
			return;
		}

		const right = document.documentElement.clientWidth - popover.offsetWidth - EDGE;
		popover.style.left = `${Math.max(EDGE, Math.min(point.left, right))}px`;
		popover.style.top = `${point.up ? point.top - GAP : point.top + GAP}px`;
		popover.style.maxWidth = `${point.maxWidth}px`;
		popover.style.maxHeight = `${Math.max(0, point.maxHeight - GAP)}px`;
		state.frame = requestAnimationFrame(position);
	};

	// beforetoggle runs while the popover still has no layout box; the
	// frame comes after it is shown and before it is painted.
	state.frame = requestAnimationFrame(() => {
		position();
		/** @type {HTMLElement | null} */ (popover.querySelector('[data-confirm-focus]'))?.focus();
	});
}

/**
 * @param {HTMLElement} popover
 */
function finish(popover) {
	if (active?.popover !== popover) {
		return;
	}

	const { button, frame } = active;
	cancelAnimationFrame(frame);
	active = null;
	const focused = document.activeElement;

	if (
		(focused === null || focused === document.body || popover.contains(focused)) &&
		button.isConnected &&
		button.checkVisibility({ visibilityProperty: true })
	) {
		button.focus({ preventScroll: true });
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click, true);
	document.addEventListener('beforetoggle', toggle, true);

	return () => {
		document.removeEventListener('click', click, true);
		document.removeEventListener('beforetoggle', toggle, true);

		if (active) {
			close(active.popover);
		}

		invoker = null;
	};
}
