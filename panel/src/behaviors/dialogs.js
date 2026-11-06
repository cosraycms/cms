// Asks the questions of hx-confirm in a panel dialog.

/** @import { Question } from '../lib/modal.js' */

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
	document.addEventListener('htmx:confirm', confirmRequest);

	return () => document.removeEventListener('htmx:confirm', confirmRequest);
}
