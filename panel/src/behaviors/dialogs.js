// Opens server-rendered dialogs and asks the questions of hx-confirm in a
// panel dialog. A `data-dialog-open` trigger opens the nearest dialog of
// that name, the first one an ancestor holds as a direct child, so a
// block's settings button never reaches the dialog of the field around it.

/** @import { Question } from '../lib/modal.js' */

import { nearestDialog, openDialog } from '../lib/dialogs.js';
import { confirm, question } from '../lib/modal.js';

/**
 * The part of an htmx request context this behavior reads and writes.
 *
 * @typedef {{ confirm?: string | null, sourceElement?: Element }} Context
 */

/**
 * @typedef {object} ConfirmDetail
 * @property {Context} ctx
 * @property {() => void} issueRequest
 * @property {() => void} dropRequest
 */

/** @type {WeakMap<Context, Question>} */
const questions = new WeakMap();

/**
 * Makes a request being configured ask a question before it is issued,
 * in place of the hx-confirm of its source.
 *
 * @param {Context} ctx
 * @param {Question} asked
 */
export function ask(ctx, asked) {
	ctx.confirm = asked.message;
	questions.set(ctx, asked);
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	const trigger =
		event.target instanceof Element ? event.target.closest('[data-dialog-open]') : null;

	if (!(trigger instanceof HTMLElement)) {
		return;
	}

	const dialog = nearestDialog(trigger, trigger.dataset.dialogOpen ?? '');

	if (dialog) {
		// A trigger inside a closed action menu cannot take focus back.
		openDialog(dialog, {
			opener: trigger.checkVisibility({ visibilityProperty: true }) ? trigger : undefined,
			owner: trigger,
		});
	}
}

/**
 * @param {Event} event
 */
function confirmRequest(event) {
	const detail = /** @type {CustomEvent<ConfirmDetail>} */ (event).detail;
	const message = detail?.ctx?.confirm;

	// A `js:` confirmation is code for htmx to run.
	if (!message || /^(js|javascript):/.test(message)) {
		return;
	}

	event.preventDefault();
	const source =
		detail.ctx.sourceElement ?? (event.target instanceof Element ? event.target : document.body);
	const asked =
		questions.get(detail.ctx) ?? question(source.closest('[hx-confirm]') ?? source, message);

	confirm(asked).then(
		(confirmed) => (confirmed ? detail.issueRequest() : detail.dropRequest()),
		() => detail.dropRequest(),
	);
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);
	document.addEventListener('htmx:confirm', confirmRequest);

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('htmx:confirm', confirmRequest);
	};
}
