// A rich text's toolbar or bubble as one tab stop: the arrow keys move
// between its controls, Home and End go to the ends, and Escape returns to
// the text where it was. Alt+F10 in the text jumps to it. The entries of
// its menus are left to the menus.

import { __ } from '../../lib/locale.js';

/**
 * The controls shown now, in order, without those inside its menus.
 *
 * @param {HTMLElement} bar
 * @returns {HTMLButtonElement[]}
 */
function controls(bar) {
	return [...bar.querySelectorAll('button')].filter(
		(button) =>
			!button.closest('[popover]') &&
			(typeof button.checkVisibility !== 'function' || button.checkVisibility()),
	);
}

/**
 * @param {HTMLElement} bar
 * @param {() => void} back Returns the focus to the text.
 * @returns {{ enter: () => boolean }} `enter` focuses the bar's stop, false when nothing shows.
 */
export function toolbarKeys(bar, back) {
	/** @type {HTMLButtonElement | null} */
	let current = null;

	bar.setAttribute('role', 'toolbar');
	bar.setAttribute('aria-label', __('richtext:formatting-tools'));

	// Controls come and go with the selection, the source view and the width.
	const sync = () => {
		const shown = controls(bar);

		if (!current || !shown.includes(current)) current = shown[0] ?? null;

		for (const button of bar.querySelectorAll('button')) {
			if (!button.closest('[popover]')) button.tabIndex = button === current ? 0 : -1;
		}
	};

	new MutationObserver(sync).observe(bar, { childList: true, subtree: true });

	if (typeof ResizeObserver !== 'undefined') new ResizeObserver(sync).observe(bar);

	bar.addEventListener('focusin', (event) => {
		if (event.target instanceof HTMLButtonElement && controls(bar).includes(event.target)) {
			current = event.target;
			sync();
		}
	});

	bar.addEventListener('keydown', (event) => {
		const target = event.target;

		if (!(target instanceof HTMLButtonElement) || target.closest('[popover]')) return;

		if (event.key === 'Escape') {
			event.preventDefault();
			back();

			return;
		}

		const shown = controls(bar);
		const at = shown.indexOf(target);
		const next = {
			ArrowRight: shown[(at + 1) % shown.length],
			ArrowLeft: shown[(at - 1 + shown.length) % shown.length],
			Home: shown[0],
			End: shown[shown.length - 1],
		}[event.key];

		if (at === -1 || !next) return;

		event.preventDefault();
		next.focus();
	});

	sync();

	return {
		enter() {
			sync();
			current?.focus();

			return current !== null;
		},
	};
}

/**
 * Alt+F10 in the text: to the first bar of these that shows.
 *
 * @param {HTMLElement} text
 * @param {(() => boolean)[]} bars Each focuses its bar and says whether it could.
 */
export function enterToolbar(text, bars) {
	text.addEventListener('keydown', (event) => {
		if (!event.altKey || event.key !== 'F10') return;

		event.preventDefault();
		bars.some((enter) => enter());
	});
}
