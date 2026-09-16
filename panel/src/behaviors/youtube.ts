// The YouTube control: its input holds a video id, the image above it
// the video's thumbnail. A pasted YouTube URL — watch, shorts, embed,
// live or youtu.be — is reduced to its id as soon as it lands, and the
// preview follows whatever the input holds. Only a full eleven-character
// id asks YouTube for an image, so typing one does not request a
// thumbnail per keystroke; an id YouTube has no image for hides it.

const ID = /^[A-Za-z0-9_-]{11}$/;
const URL_ID =
	/(?:youtu\.be\/|\/(?:watch\?(?:[^#]*&)?v=|shorts\/|embed\/|live\/|v\/))([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])/;

/** The id a YouTube URL names, or null when the text is not one. */
export function urlId(text: string): string | null {
	return URL_ID.exec(text.trim())?.[1] ?? null;
}

export function thumbnail(id: string): string {
	return `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
}

function preview(box: Element, id: string | null): void {
	const image = box.querySelector<HTMLImageElement>('[data-youtube-preview]');

	if (!image) {
		return;
	}

	if (id === null) {
		image.hidden = true;
		image.removeAttribute('src');

		return;
	}

	const src = thumbnail(id);

	if (image.getAttribute('src') !== src) {
		image.setAttribute('src', src);
	}

	image.hidden = false;
}

// The aspect ratio lives in the field's meta — inside a block, in the
// settings dialog — as two inputs named `{root}[meta][aspectRatioX][zxx]`
// and `…Y…`; the control's own input is `{root}[value][{locale}]`.
const RATIO_META = /^(.*)\[meta\]\[aspectRatio[XY]\]\[zxx\]$/;

function side(root: string, axis: 'X' | 'Y'): number | null {
	const input = document.getElementsByName(`${root}[meta][aspectRatio${axis}][zxx]`)[0];
	const value = input instanceof HTMLInputElement ? Number(input.value) : NaN;

	return Number.isInteger(value) && value > 0 ? value : null;
}

/** Re-shapes the thumbnail whose ratio meta input changed. */
function followRatio(input: HTMLInputElement): boolean {
	const root = RATIO_META.exec(input.name)?.[1];

	if (root === undefined) {
		return false;
	}

	const box = Array.from(document.querySelectorAll<HTMLElement>('[data-youtube]')).find(
		(candidate) =>
			candidate
				.querySelector<HTMLInputElement>('input[type="text"]')
				?.name.startsWith(`${root}[value]`),
	);
	const x = side(root, 'X');
	const y = side(root, 'Y');

	if (box && x !== null && y !== null) {
		box.style.setProperty('--ratio', `${x} / ${y}`);
	}

	return true;
}

function onInput(event: Event): void {
	const input = event.target;

	if (!(input instanceof HTMLInputElement) || followRatio(input)) {
		return;
	}

	const box = input.closest('[data-youtube]');

	if (!box) {
		return;
	}

	const fromUrl = urlId(input.value);

	if (fromUrl !== null) {
		input.value = fromUrl;
	}

	const value = input.value.trim();

	preview(box, ID.test(value) ? value : null);
}

function onError(event: Event): void {
	const image = event.target;

	if (image instanceof HTMLImageElement && image.matches('[data-youtube-preview]')) {
		image.hidden = true;
	}
}

export function install(): () => void {
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('error', onError, true);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('error', onError, true);
	};
}
