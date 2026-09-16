import { openDialog } from '$lib/dialogs';
import { nest } from '$lib/form-json';
import { thumbnail } from './youtube';

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

let observer: ResizeObserver | null = null;

function remembered(): number {
	try {
		return Number(localStorage.getItem(STORE)) || 0;
	} catch {
		return 0;
	}
}

function remember(width: number): void {
	try {
		localStorage.setItem(STORE, String(width));
	} catch {
		// A blocked storage only forgets the choice.
	}
}

function presets(dialog: HTMLElement): HTMLElement[] {
	return Array.from(dialog.querySelectorAll<HTMLElement>(WIDTH));
}

type Preset = { width: number; height: number | null };

function chosen(dialog: HTMLElement): Preset {
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
function layout(dialog: HTMLElement): void {
	const stage = dialog.querySelector<HTMLElement>(STAGE);
	const frame = dialog.querySelector<HTMLElement>(FRAME);

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

function choose(dialog: HTMLElement, width: number, persist = true): void {
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

function body(form: HTMLFormElement): string {
	const entries = Array.from(new FormData(form)).filter(
		(entry): entry is [string, string] => typeof entry[1] === 'string',
	);

	return JSON.stringify(nest(entries));
}

function fail(dialog: HTMLElement): void {
	const message = dialog.dataset.error ?? '';

	if (message !== '') {
		window.Cosray?.toast.error(message);
	}
}

function thumbnails(html: string): string {
	const sheet = new DOMParser().parseFromString(html, 'text/html');

	// Nested players inherit the script-free sandbox, so replace them before loading the sheet.
	for (const embed of sheet.querySelectorAll<HTMLIFrameElement>('iframe.youtube')) {
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

async function load(dialog: HTMLElement, field: string): Promise<boolean> {
	const form = document.querySelector<HTMLFormElement>(FORM);
	const stage = dialog.querySelector<HTMLElement>(STAGE);
	const frame = dialog.querySelector<HTMLIFrameElement>(FRAME);

	if (!form || !stage || !frame) {
		return false;
	}

	const locale = form.getAttribute('data-content-locale') ?? '';
	const url =
		`${dialog.dataset.url ?? ''}/${encodeURIComponent(field)}` +
		(locale !== '' ? `?locale=${encodeURIComponent(locale)}` : '');

	dialog.dataset.field = field;
	stage.classList.add('is-loading');

	try {
		const response = await fetch(url, {
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

async function open(button: HTMLElement): Promise<void> {
	const dialog = document.querySelector<HTMLDialogElement>(DIALOG);
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

	const stage = dialog.querySelector<HTMLElement>(STAGE);

	if (stage && typeof ResizeObserver !== 'undefined') {
		observer?.disconnect();
		observer = new ResizeObserver(() => layout(dialog));
		observer.observe(stage);
	}

	layout(dialog);
}

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const button = target.closest<HTMLElement>(BUTTON);

	if (button) {
		void open(button);

		return;
	}

	const dialog = target.closest<HTMLElement>(DIALOG);

	if (!dialog) {
		return;
	}

	const width = target.closest<HTMLElement>(WIDTH);

	if (width) {
		choose(dialog, Number(width.dataset.layoutPreviewWidth));

		return;
	}

	if (target.closest(RELOAD) && dialog.dataset.field) {
		void load(dialog, dialog.dataset.field);
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
		observer?.disconnect();
		observer = null;
	};
}
