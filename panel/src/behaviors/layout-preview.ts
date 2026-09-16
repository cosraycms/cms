import { openDialog } from '$lib/dialogs';
import { nest } from '$lib/form-json';

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

function chosen(dialog: HTMLElement): number {
	const pressed = presets(dialog).find((option) => option.getAttribute('aria-pressed') === 'true');

	return Number(pressed?.dataset.layoutPreviewWidth) || DEFAULT_WIDTH;
}

// The frame keeps the preset's width; the stage decides the scale.
function layout(dialog: HTMLElement): void {
	const stage = dialog.querySelector<HTMLElement>(STAGE);
	const frame = dialog.querySelector<HTMLElement>(FRAME);

	if (!stage || !frame) {
		return;
	}

	const width = chosen(dialog);
	const available = stage.clientWidth;
	const scale = available > 0 && available < width ? available / width : 1;
	const height = stage.clientHeight;

	frame.style.width = `${width}px`;
	frame.style.height = height > 0 ? `${Math.round(height / scale)}px` : '';
	frame.style.transform = scale < 1 ? `scale(${scale})` : '';
	frame.classList.toggle('is-scaled', scale < 1);
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

		frame.srcdoc = await response.text();

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
		choose(dialog, remembered() || chosen(dialog), false);
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
