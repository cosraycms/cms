// An editor framed by another application (`?from=embed`) reports to its
// frame: `cms-dirty` and `cms-pristine` as the unsaved-changes indicator
// flips, `cms-ok` after a save or a creation, `cms-cancel` when the user
// cancels or presses Escape. Messages carry the node uid and only go to the
// origins the site allows to frame the panel.

const ROOT = '[data-embed]';
const INDICATOR = 'editor-dirty';
const STATUS = 'editor-status';

/** @type {WeakSet<Element>} */
const reported = new WeakSet();

/** @returns {HTMLElement | null} */
function root() {
	const element = document.querySelector(ROOT);

	return element instanceof HTMLElement ? element : null;
}

/**
 * @param {string} type
 * @param {Record<string, unknown>} [extra]
 */
function post(type, extra = {}) {
	const element = root();

	if (element === null || window.parent === window) {
		return;
	}

	/** @type {unknown} */
	let origins = [];

	try {
		origins = JSON.parse(element.dataset.embedOrigins ?? '[]');
	} catch {
		origins = [];
	}

	const message = { type, uid: element.dataset.embedUid ?? null, ...extra };

	for (const origin of Array.isArray(origins) ? origins : []) {
		if (typeof origin === 'string') {
			window.parent.postMessage(message, origin);
		}
	}
}

/** @param {MutationRecord[]} records */
function watch(records) {
	for (const record of records) {
		if (record.target instanceof HTMLElement && record.target.id === INDICATOR) {
			post(record.target.hidden ? 'cms-pristine' : 'cms-dirty');
		}
	}
}

// A save swaps in a new status element; a successful one carries is-success.
function settle() {
	const status = document.getElementById(STATUS);

	if (status !== null && status.classList.contains('is-success') && !reported.has(status)) {
		reported.add(status);
		post('cms-ok');
	}

	announceCreation();
}

// Creating redirects to the new node's editor, flagged so the frame learns
// the node exists.
function announceCreation() {
	const element = root();

	if (element !== null && element.hasAttribute('data-embed-created') && !reported.has(element)) {
		reported.add(element);
		post('cms-ok', { created: true });
	}
}

/** @param {Event} event */
function cancel(event) {
	if (event.target instanceof Element && event.target.closest('[data-embed-cancel]')) {
		post('cms-cancel');
	}
}

// The frame never sees key presses inside the panel. Overlays that close on
// Escape claim it at the document or below; only an unclaimed one cancels.
/** @param {KeyboardEvent} event */
function escape(event) {
	if (
		event.key !== 'Escape' ||
		event.defaultPrevented ||
		event.isComposing ||
		document.querySelector('dialog[open]') !== null ||
		[...document.querySelectorAll('[popover]')].some((element) => element.matches(':popover-open'))
	) {
		return;
	}

	post('cms-cancel');
}

/** @returns {() => void} */
export function install() {
	const observer = new MutationObserver(watch);
	observer.observe(document.body, { attributes: true, attributeFilter: ['hidden'], subtree: true });
	document.addEventListener('htmx:after:swap', settle);
	document.addEventListener('click', cancel);
	window.addEventListener('keydown', escape);
	announceCreation();

	return () => {
		observer.disconnect();
		document.removeEventListener('htmx:after:swap', settle);
		document.removeEventListener('click', cancel);
		window.removeEventListener('keydown', escape);
	};
}
