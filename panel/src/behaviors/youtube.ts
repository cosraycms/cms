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

function onInput(event: Event): void {
	const input = event.target;
	const box = input instanceof HTMLInputElement ? input.closest('[data-youtube]') : null;

	if (!(input instanceof HTMLInputElement) || !box) {
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
