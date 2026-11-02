// The media screen's browser-side parts. The server renders the listing,
// filters and detail; this adds what needs the pointer or the file system:
// uploads by button or drop, the focal point, the caption's editor, the delete
// confirmation, the selection for deleting several files, replacing a file, and
// the active tile once htmx swaps in another file's detail.

import { cosray } from '../lib/bridge.js';
import { csrfToken, HEADER, noticeRefusal } from '../lib/csrf.js';
import { openDialog } from '../lib/dialogs.js';
import { __, __n } from '../lib/locale.js';

const SCREEN = '[data-media]';

let uploading = false;

/**
 * Gives each language's caption its editor, which writes the document into the
 * hidden input the form submits. The editor loads only where there is a caption.
 */
async function mountCaptions() {
	const inputs = Array.from(
		document.querySelectorAll('input[data-media-caption]:not([data-mounted])'),
	).filter((input) => input instanceof HTMLInputElement);

	if (inputs.length === 0) return;

	for (const input of inputs) input.dataset.mounted = '';

	const { inlineEditor } = await import('../elements/richtext/inline-editor.js');

	for (const input of inputs) {
		const label = document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
		const editor = inlineEditor({
			value: storedCaption(input.value),
			label: label?.textContent?.trim() ?? '',
			change: (doc) => {
				input.value = doc ? JSON.stringify(doc) : '';
			},
		});
		label?.addEventListener('click', () => editor.field.focus());
		input.after(editor.element);
	}
}

/**
 * @param {string} value A document's JSON, or a plain text from before captions had marks.
 * @returns {import('../types/data').RichtextDoc | string}
 */
function storedCaption(value) {
	try {
		const doc = JSON.parse(value);

		return doc?.type === 'doc' ? doc : value;
	} catch {
		return value;
	}
}

/** Marks the tile whose detail the inspector shows. */
function mark() {
	const detail = document.querySelector('#media-detail');
	const uid = detail?.getAttribute('data-media-detail') ?? '';

	document.querySelectorAll('[data-media-tile]').forEach((tile) => {
		tile.classList.toggle('active', uid !== '' && tile.getAttribute('data-media-tile') === uid);
	});
}

/** @param {string} type */
function uploadKind(type) {
	if (type.startsWith('image/')) {
		return 'image';
	}

	if (type.startsWith('video/')) {
		return 'video';
	}

	return 'file';
}

/**
 * Uploads one file after the other, then reloads the listing with the
 * last one selected. Failures stay as toasts, which outlive the reload.
 *
 * @param {HTMLElement} screen
 * @param {File[]} files
 */
async function upload(screen, files) {
	if (files.length === 0 || uploading) {
		return;
	}

	const button = screen.querySelector('[data-media-upload]');
	const label = screen.querySelector('[data-media-upload-label]');
	const idle = label?.textContent ?? '';
	const bridge = cosray();
	/** @type {string | null} */
	let last = null;
	let done = 0;

	/** @param {number} count */
	function progress(count) {
		if (label) {
			label.textContent =
				files.length > 1
					? `${__('upload:in-progress')} ${count}/${files.length}`
					: __('upload:in-progress');
		}
	}

	uploading = true;
	button?.toggleAttribute('disabled', true);
	progress(0);

	try {
		for (const file of files) {
			const result = await bridge.upload(uploadKind(file.type), file);

			if (result.ok && result.uid) {
				last = result.uid;
			} else {
				bridge.toast.error(`${file.name}: ${result.error ?? __('upload:failed')}`);
			}

			progress(++done);
		}
	} finally {
		uploading = false;
		button?.toggleAttribute('disabled', false);

		if (label) {
			label.textContent = idle;
		}
	}

	if (last !== null) {
		const url = new URL(location.href);
		url.searchParams.set('file', last);
		url.searchParams.delete('page');
		await htmx.ajax('GET', url.pathname + url.search, { target: '#main', replace: 'true' });
	}
}

/**
 * @param {string} url
 * @param {BodyInit} body
 * @returns {Promise<Record<string, unknown>>} What the server said, `ok` false when it could not.
 */
async function send(url, body) {
	try {
		const response = await fetch(url, { method: 'POST', headers: { [HEADER]: csrfToken() }, body });
		noticeRefusal(response);

		return await response.json();
	} catch {
		return { ok: false };
	}
}

/** Shows the listing again, as the server has it now. */
async function reload() {
	await htmx.ajax('GET', location.pathname + location.search, { target: '#main', replace: 'true' });
}

/** @param {ParentNode} screen */
function selected(screen) {
	return /** @type {HTMLInputElement[]} */ (
		Array.from(screen.querySelectorAll('[data-media-select]:checked'))
	);
}

/** @param {ParentNode} screen */
function showSelection(screen) {
	const count = selected(screen).length;
	const bar = screen.querySelector('[data-media-selection]');

	if (bar instanceof HTMLElement) {
		bar.hidden = count === 0;
		const label = bar.querySelector('[data-media-selection-count]');

		if (label) {
			label.textContent = __n('media:selected', 'media:selected-plural', count, { count });
		}
	}
}

/** @param {HTMLElement} screen */
async function deleteSelected(screen) {
	const dialog = screen.querySelector('[data-media-delete-selected-dialog]');

	if (!(dialog instanceof HTMLDialogElement)) {
		return;
	}

	const body = new FormData();

	for (const input of selected(screen)) {
		body.append('uids[]', input.value);
	}

	const result = await send(dialog.dataset.url ?? '', body);
	dialog.close();

	if (!result.ok) {
		cosray().toast.error(__('media:delete-selected-failed'));

		return;
	}

	const deleted = Number(result.deleted);
	const kept = Number(result.kept);

	if (deleted > 0) {
		cosray().toast.success(
			__n('media:deleted-selected', 'media:deleted-selected-plural', deleted, { count: deleted }),
		);
	}

	if (kept > 0) {
		cosray().toast.error(
			__n('media:kept-selected', 'media:kept-selected-plural', kept, { count: kept }),
		);
	}

	await reload();
}

/** @param {HTMLInputElement} input */
async function replace(input) {
	const [file] = input.files ?? [];
	input.value = '';

	if (!file) {
		return;
	}

	const body = new FormData();
	body.append('file', file);
	const result = await send(input.dataset.url ?? '', body);

	if (!result.ok) {
		cosray().toast.error(
			typeof result.error === 'string' ? result.error : __('media:replace-failed'),
		);

		return;
	}

	await reload();
}

/** @param {DragEvent} event */
function hasFiles(event) {
	return event.dataTransfer?.types.includes('Files') ?? false;
}

// Children fire their own enter and leave pairs, so the zone counts depth
// instead of trusting the last event.
/** @type {WeakMap<Element, number>} */
const depths = new WeakMap();

/** @param {DragEvent} event */
function drag(event) {
	const zone = event.target instanceof Element ? event.target.closest('[data-media-drop]') : null;

	if (!zone || !hasFiles(event)) {
		return;
	}

	event.preventDefault();
	let depth = depths.get(zone) ?? 0;

	if (event.type === 'dragenter') {
		depth += 1;
	} else if (event.type === 'dragleave') {
		depth = Math.max(0, depth - 1);
	} else if (event.type === 'drop') {
		depth = 0;
		const screen = zone.closest(SCREEN);

		if (screen instanceof HTMLElement) {
			void upload(screen, [...(event.dataTransfer?.files ?? [])]);
		}
	}

	depths.set(zone, depth);
	zone.classList.toggle('is-dragging', depth > 0);
}

/**
 * @param {HTMLElement} form
 * @param {{ x: number, y: number } | null} point
 */
function setFocal(form, point) {
	const x = form.querySelector('[data-media-focal-x]');
	const y = form.querySelector('[data-media-focal-y]');
	const marker = form.querySelector('[data-media-focal-marker]');
	const text = form.querySelector('[data-media-focal-text]');
	const clear = form.querySelector('[data-media-focal-clear]');

	if (x instanceof HTMLInputElement && y instanceof HTMLInputElement) {
		x.value = point ? String(point.x) : '';
		y.value = point ? String(point.y) : '';
	}

	if (marker instanceof HTMLElement) {
		marker.hidden = point === null;

		if (point) {
			marker.style.left = `${point.x * 100}%`;
			marker.style.top = `${point.y * 100}%`;
		}
	}

	if (text instanceof HTMLElement) {
		text.textContent = point
			? `${text.dataset.label}: ${Math.round(point.x * 100)}% / ${Math.round(point.y * 100)}%`
			: (text.dataset.empty ?? '');
	}

	if (clear instanceof HTMLElement) {
		clear.hidden = point === null;
	}
}

/** @param {number} n */
function round(n) {
	return Math.round(Math.min(1, Math.max(0, n)) * 1000) / 1000;
}

/** @param {MouseEvent} event */
function click(event) {
	const target = event.target instanceof Element ? event.target : null;
	const screen = target?.closest(SCREEN);

	if (!target || !(screen instanceof HTMLElement)) {
		return;
	}

	if (target.closest('[data-media-upload]')) {
		const input = screen.querySelector('[data-media-upload-input]');

		if (input instanceof HTMLInputElement) {
			input.click();
		}

		return;
	}

	if (target.closest('[data-media-selection-clear]')) {
		for (const input of selected(screen)) {
			input.checked = false;
		}

		showSelection(screen);

		return;
	}

	const deleting = target.closest('[data-media-delete-selected]');

	if (deleting instanceof HTMLElement) {
		const dialog = screen.querySelector('[data-media-delete-selected-dialog]');
		const question = dialog?.querySelector('[data-media-delete-selected-question]');
		const count = selected(screen).length;

		if (dialog instanceof HTMLDialogElement && question) {
			question.textContent = __n(
				'media:confirm-delete-selected',
				'media:confirm-delete-selected-plural',
				count,
				{
					count,
				},
			);
			openDialog(dialog, { opener: deleting, owner: deleting });
		}

		return;
	}

	if (target.closest('[data-media-delete-selected-confirm]')) {
		void deleteSelected(screen);

		return;
	}

	if (target.closest('[data-media-replace]')) {
		const input = screen.querySelector('[data-media-replace-input]');

		if (input instanceof HTMLInputElement) {
			input.click();
		}

		return;
	}

	const preview = target.closest('[data-media-focal]');
	const form = target.closest('form');

	// Keyboard activation reports no pointer position; it must not snap the
	// focal point into the corner.
	if (preview instanceof HTMLElement && form && event.detail !== 0) {
		const box = preview.getBoundingClientRect();
		setFocal(form, {
			x: round((event.clientX - box.left) / box.width),
			y: round((event.clientY - box.top) / box.height),
		});
		return;
	}

	if (target.closest('[data-media-focal-clear]') && form) {
		setFocal(form, null);
		return;
	}

	const remove = target.closest('[data-media-delete]');

	if (remove instanceof HTMLElement) {
		const dialog = remove.closest('#media-detail')?.querySelector('[data-media-delete-dialog]');

		if (dialog instanceof HTMLDialogElement) {
			openDialog(dialog, { opener: remove, owner: remove });
		}
	}
}

/** @param {Event} event */
function change(event) {
	const input = event.target;

	if (input instanceof HTMLInputElement && input.matches('[data-media-select]')) {
		const parent = input.closest(SCREEN);

		if (parent) {
			showSelection(parent);
		}

		return;
	}

	if (input instanceof HTMLInputElement && input.matches('[data-media-replace-input]')) {
		void replace(input);

		return;
	}

	if (!(input instanceof HTMLInputElement) || !input.matches('[data-media-upload-input]')) {
		return;
	}

	const screen = input.closest(SCREEN);
	const files = [...(input.files ?? [])];
	input.value = '';

	if (screen instanceof HTMLElement) {
		void upload(screen, files);
	}
}

/**
 * A tile click swaps only the detail and replaces the URL, so the filters,
 * search and paging still carry the file that was selected when the page
 * rendered. Requests marked to keep the file take the selection from the
 * URL instead.
 *
 * @param {Event} event
 */
function keepFile(event) {
	const source = event.target;
	const detail =
		/** @type {CustomEvent<{ ctx?: { request?: { method?: string, action?: string, body?: unknown } } }>} */ (
			event
		).detail;
	const request = detail?.ctx?.request;

	if (
		!(source instanceof Element) ||
		!source.closest('[data-media-keep-file]') ||
		request?.method !== 'GET' ||
		!(request.body instanceof FormData) ||
		typeof request.action !== 'string'
	) {
		return;
	}

	const file = new URLSearchParams(location.search).get('file') ?? '';
	const url = new URL(request.action, document.baseURI);
	url.searchParams.delete('file');
	request.action = url.pathname + url.search;

	if (file === '') {
		request.body.delete('file');
	} else {
		request.body.set('file', file);
	}
}

/** @returns {() => void} */
export function install() {
	const events = new AbortController();
	const options = { signal: events.signal };

	document.addEventListener('click', click, options);
	document.addEventListener('change', change, options);

	for (const type of ['dragenter', 'dragover', 'dragleave', 'drop']) {
		document.addEventListener(type, /** @type {EventListener} */ (drag), options);
	}

	document.addEventListener('htmx:after:swap', mark, options);
	document.addEventListener('htmx:after:swap', () => void mountCaptions(), options);
	void mountCaptions();
	document.addEventListener('htmx:config:request', keepFile, options);

	return () => events.abort();
}
