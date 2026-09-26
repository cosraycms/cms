import { openDialog } from '../lib/dialogs.js';
import { nest } from '../lib/form-json.js';
import { thumbnail } from './youtube.js';

// The layout preview of a blocks field: the field rendered through the
// site's render path from the form as it stands, in the frame of the one
// dialog the editor page renders. The endpoint saves nothing, so the
// preview never touches the working copy or the dirty state. A width
// preset sets the frame's width so the reference sheet's container query
// shows the responsive stacking; a frame wider than its stage is scaled
// down from the top left corner and given the height the scale eats, so
// it still fills the stage. The last preset is remembered per browser.

const BUTTON = '[data-layout-preview]';
const DIALOG = '[data-layout-preview-dialog]';
const FORM = '#node-editor-form';
const WIDTH = '[data-layout-preview-width]';
const RELOAD = '[data-layout-preview-reload]';
const STAGE = '[data-layout-preview-stage]';
const FRAME = '[data-layout-preview-frame]';
const STORE = 'cosray:layout-preview-width';
const DEFAULT_WIDTH = 1200;

/** @type {ResizeObserver | null} */
let observer = null;

/** @returns {number} */
function remembered() {
	try {
		return Number(localStorage.getItem(STORE)) || 0;
	} catch {
		return 0;
	}
}

/**
 * @param {number} width
 */
function remember(width) {
	try {
		localStorage.setItem(STORE, String(width));
	} catch {
		// A blocked storage only forgets the choice.
	}
}

/**
 * @param {HTMLElement} dialog
 * @returns {HTMLElement[]}
 */
function presets(dialog) {
	return Array.from(/** @type {NodeListOf<HTMLElement>} */ (dialog.querySelectorAll(WIDTH)));
}

/**
 * @typedef {object} Preset
 * @property {number} width
 * @property {number | null} height
 */

/**
 * @param {HTMLElement} dialog
 * @returns {Preset}
 */
function chosen(dialog) {
	const pressed = presets(dialog).find((option) => option.getAttribute('aria-pressed') === 'true');
	const height = Number(pressed?.dataset.layoutPreviewHeight);

	return {
		width: Number(pressed?.dataset.layoutPreviewWidth) || DEFAULT_WIDTH,
		height: height > 0 ? height : null,
	};
}

// The frame is the preset's size — a device's screen, or the stage's
// height for the desktop — and the stage decides the scale: down until
// both sides fit, never up. It is centred by a translation, since the
// transform does not change what the layout thinks the frame's width is.
/**
 * @param {HTMLElement} dialog
 */
function layout(dialog) {
	const stage = /** @type {HTMLElement | null} */ (dialog.querySelector(STAGE));
	const frame = /** @type {HTMLElement | null} */ (dialog.querySelector(FRAME));

	if (!stage || !frame) {
		return;
	}

	const { width, height } = chosen(dialog);
	const availableWidth = stage.clientWidth;
	const availableHeight = stage.clientHeight;
	let scale = availableWidth > 0 && availableWidth < width ? availableWidth / width : 1;

	if (height !== null && availableHeight > 0 && availableHeight < height * scale) {
		scale = availableHeight / height;
	}

	const offset = availableWidth > 0 ? Math.max(0, (availableWidth - width * scale) / 2) : 0;

	frame.style.width = `${width}px`;
	frame.style.height =
		height !== null
			? `${height}px`
			: availableHeight > 0
				? `${Math.round(availableHeight / scale)}px`
				: '';
	frame.style.transform =
		scale < 1 || offset > 0 ? `translateX(${Math.round(offset)}px) scale(${scale})` : '';
}

/**
 * @param {HTMLElement} dialog
 * @param {number} width
 * @param {boolean} [persist]
 */
function choose(dialog, width, persist = true) {
	const options = presets(dialog);
	const match = options.find((option) => Number(option.dataset.layoutPreviewWidth) === width);

	if (!match) {
		return;
	}

	options.forEach((option) => {
		const pressed = option === match;
		option.setAttribute('aria-pressed', pressed ? 'true' : 'false');
		option.toggleAttribute('data-dialog-focus', pressed);
	});

	if (persist) {
		remember(width);
	}

	layout(dialog);
}

/**
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function body(form) {
	const entries = Array.from(new FormData(form)).filter(
		/** @returns {entry is [string, string]} */ (entry) => typeof entry[1] === 'string',
	);

	return JSON.stringify(nest(entries));
}

/**
 * @param {HTMLElement} dialog
 */
function fail(dialog) {
	const message = dialog.dataset.error ?? '';

	if (message !== '') {
		window.Cosray?.toast.error(message);
	}
}

/**
 * @param {string} html
 * @returns {string}
 */
function thumbnails(html) {
	const sheet = new DOMParser().parseFromString(html, 'text/html');

	// Nested players inherit the script-free sandbox, so replace them before loading the sheet.
	for (const embed of /** @type {NodeListOf<HTMLIFrameElement>} */ (
		sheet.querySelectorAll('iframe.youtube')
	)) {
		const id = /^https:\/\/www\.youtube\.com\/embed\/([A-Za-z0-9_-]{11})$/.exec(
			embed.getAttribute('src') ?? '',
		)?.[1];

		if (!id) {
			continue;
		}

		const image = sheet.createElement('img');
		image.src = thumbnail(id);
		image.alt = 'YouTube';
		image.className = embed.className;
		image.style.cssText = embed.style.cssText;
		image.style.objectFit = 'cover';
		embed.replaceWith(image);
	}

	return `<!doctype html>\n${sheet.documentElement.outerHTML}`;
}

/**
 * @param {HTMLElement} dialog
 * @param {string} field
 * @returns {Promise<boolean>}
 */
async function load(dialog, field) {
	const form = /** @type {HTMLFormElement | null} */ (document.querySelector(FORM));
	const stage = /** @type {HTMLElement | null} */ (dialog.querySelector(STAGE));
	const frame = /** @type {HTMLIFrameElement | null} */ (dialog.querySelector(FRAME));

	if (!form || !stage || !frame) {
		return false;
	}

	const locale = form.getAttribute('data-content-locale') ?? '';
	const url = new URL(dialog.dataset.url ?? '', location.href);
	url.pathname += `/${encodeURIComponent(field)}`;

	if (locale !== '') {
		url.searchParams.set('locale', locale);
	}

	dialog.dataset.field = field;
	stage.classList.add('is-loading');

	try {
		const response = await fetch(url.pathname + url.search, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'text/html' },
			body: body(form),
		});

		if (!response.ok) {
			fail(dialog);

			return false;
		}

		frame.srcdoc = thumbnails(await response.text());

		return true;
	} catch {
		fail(dialog);

		return false;
	} finally {
		stage.classList.remove('is-loading');
	}
}

/**
 * @param {HTMLElement} button
 */
async function open(button) {
	const dialog = /** @type {HTMLDialogElement | null} */ (document.querySelector(DIALOG));
	const field = button.dataset.layoutPreview ?? '';

	if (!dialog || field === '') {
		return;
	}

	if (!(await load(dialog, field))) {
		return;
	}

	if (!dialog.open) {
		choose(dialog, remembered() || chosen(dialog).width, false);
		openDialog(dialog, { opener: button });
	}

	const stage = /** @type {HTMLElement | null} */ (dialog.querySelector(STAGE));

	if (stage && typeof ResizeObserver !== 'undefined') {
		observer?.disconnect();
		observer = new ResizeObserver(() => layout(dialog));
		observer.observe(stage);
	}

	layout(dialog);
}

/**
 * @param {Event} event
 */
function onClick(event) {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const button = /** @type {HTMLElement | null} */ (target.closest(BUTTON));

	if (button) {
		void open(button);

		return;
	}

	const dialog = /** @type {HTMLElement | null} */ (target.closest(DIALOG));

	if (!dialog) {
		return;
	}

	const width = /** @type {HTMLElement | null} */ (target.closest(WIDTH));

	if (width) {
		choose(dialog, Number(width.dataset.layoutPreviewWidth));

		return;
	}

	if (target.closest(RELOAD) && dialog.dataset.field) {
		void load(dialog, dialog.dataset.field);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
		observer?.disconnect();
		observer = null;
	};
}
