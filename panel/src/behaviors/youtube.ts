const ID = /^[A-Za-z0-9_-]{11}$/;

export function urlId(text: string): string | null {
	try {
		const url = new URL(/^https?:\/\//i.test(text.trim()) ? text.trim() : `https://${text.trim()}`);
		const host = url.hostname;
		let id: string | null = null;

		if (host === 'youtu.be') {
			id = /^\/([^/]+)\/?$/.exec(url.pathname)?.[1] ?? null;
		} else if (
			[
				'youtube.com',
				'www.youtube.com',
				'm.youtube.com',
				'music.youtube.com',
				'youtube-nocookie.com',
				'www.youtube-nocookie.com',
			].includes(host)
		) {
			id =
				url.pathname === '/watch'
					? url.searchParams.get('v')
					: (/^\/(?:shorts|embed|live|v)\/([^/]+)\/?$/.exec(url.pathname)?.[1] ?? null);
		}

		return id !== null && ID.test(id) ? id : null;
	} catch {
		return null;
	}
}

export function thumbnail(id: string): string {
	return `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
}

function value(box: Element): HTMLInputElement {
	return box.querySelector<HTMLInputElement>('[data-youtube-value]')!;
}

function input(box: Element): HTMLInputElement {
	return box.querySelector<HTMLInputElement>('[data-youtube-input]')!;
}

function show(box: Element, selector: string, visible: boolean): void {
	const element = box.querySelector<HTMLElement>(selector);
	if (element) element.hidden = !visible;
}

function clearError(box: Element): void {
	const editor = input(box);
	const error = box.querySelector<HTMLElement>('[data-youtube-error]')!;

	if (editor.getAttribute('aria-describedby') === error.id) {
		editor.removeAttribute('aria-invalid');
		editor.removeAttribute('aria-describedby');
	}
	error.hidden = true;
}

function actions(box: Element): void {
	show(box, '[data-youtube-add]', input(box).value.trim() !== '');
}

function render(box: Element): void {
	const id = value(box).value;
	const filled = ID.test(id);
	const player = box.querySelector<HTMLIFrameElement>('[data-youtube-player]')!;

	if (filled) {
		const src = `https://www.youtube-nocookie.com/embed/${id}`;
		if (player.getAttribute('src') !== src) player.src = src;
	} else {
		player.removeAttribute('src');
	}

	input(box).value = id;
	clearError(box);
	show(box, '[data-youtube-player]', filled);
	show(box, '[data-youtube-entry]', !filled);
	show(box, '[data-youtube-replace]', filled);
	actions(box);
}

function commit(box: Element, id: string): void {
	const control = value(box);
	const changed = control.value !== id;
	control.value = id;
	render(box);
	box
		.querySelector<HTMLElement>(id === '' ? '[data-youtube-input]' : '[data-youtube-replace]')
		?.focus();

	if (changed) {
		control.dispatchEvent(new Event('input', { bubbles: true }));
		control.dispatchEvent(new Event('change', { bubbles: true }));
	}
}

function confirm(box: Element): void {
	const text = input(box).value.trim();
	const id = ID.test(text) ? text : urlId(text);

	if (id === null) {
		const error = box.querySelector<HTMLElement>('[data-youtube-error]')!;
		error.hidden = false;
		input(box).setAttribute('aria-invalid', 'true');
		input(box).setAttribute('aria-describedby', error.id);
		input(box).focus();
		return;
	}

	commit(box, id);
}

const RATIO_META = /^(.*)\[meta\]\[aspectRatio[XY]\]\[zxx\]$/;

function side(root: string, axis: 'X' | 'Y'): number | null {
	const control = document.getElementsByName(`${root}[meta][aspectRatio${axis}][zxx]`)[0];
	const number = control instanceof HTMLInputElement ? Number(control.value) : NaN;

	return Number.isInteger(number) && number > 0 ? number : null;
}

function followRatio(control: HTMLInputElement): void {
	const root = RATIO_META.exec(control.name)?.[1];
	if (root === undefined) return;

	const x = side(root, 'X');
	const y = side(root, 'Y');
	if (x === null || y === null) return;

	for (const box of document.querySelectorAll<HTMLElement>('[data-youtube]')) {
		if (value(box).name.startsWith(`${root}[value]`)) {
			box.style.setProperty('--ratio', `${x} / ${y}`);
		}
	}
}

function onInput(event: Event): void {
	const control = event.target;
	if (!(control instanceof HTMLInputElement)) return;

	followRatio(control);
	const box = control.closest('[data-youtube]');
	if (!box) return;

	if (control.matches('[data-youtube-input]')) {
		clearError(box);
		actions(box);
	} else if (control.matches('[data-youtube-value]')) {
		render(box);
	}
}

function onClick(event: MouseEvent): void {
	const button = event.target instanceof Element ? event.target.closest('button') : null;
	const box = button?.closest('[data-youtube]');
	if (!button || !box || input(box).readOnly) return;

	if (button.hasAttribute('data-youtube-replace')) commit(box, '');
	else if (button.hasAttribute('data-youtube-add')) confirm(box);
}

function onKeydown(event: KeyboardEvent): void {
	const control = event.target;
	if (!(control instanceof HTMLInputElement) || !control.matches('[data-youtube-input]')) return;
	if (event.isComposing || control.readOnly || event.key !== 'Enter') return;

	event.preventDefault();
	confirm(control.closest('[data-youtube]')!);
}

function stamp(event: Event): void {
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-youtube]').forEach(render);
		// Duplicated rows copy live meta inputs, not the template's preview ratio.
		event.target.querySelectorAll<HTMLInputElement>('input[type="number"]').forEach(followRatio);
	}
}

export function install(): () => void {
	document.addEventListener('input', onInput);
	document.addEventListener('change', onInput);
	document.addEventListener('click', onClick);
	document.addEventListener('keydown', onKeydown);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onInput);
		document.removeEventListener('click', onClick);
		document.removeEventListener('keydown', onKeydown);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
