import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install, urlId } from '../../src/behaviors/youtube';
import { install as installFallbacks } from '../../src/behaviors/fallbacks';
import { install as installErrors } from '../../src/behaviors/errors';
import { install as installDirty } from '../../src/behaviors/dirty';
import { install as installRepeater } from '../../src/behaviors/repeater';

vi.mock('sortablejs', () => ({ default: vi.fn() }));

const ID = 'dQw4w9WgXcQ';
const NEXT = 'abcdefghijk';
const NAME = 'content[video][value][zxx]';
const stops: Array<() => void> = [];

beforeEach(() => {
	stops.push(install());
});

afterEach(() => {
	stops
		.splice(0)
		.reverse()
		.forEach((stop) => stop());
	document.body.innerHTML = '';
});

function markup(
	value = '',
	field: Record<string, unknown> = {},
	extra: Record<string, unknown> = {},
): string {
	return execFileSync(
		'php',
		[resolve(dirname(fileURLToPath(import.meta.url)), '../../../tests/Fixtures/Panel/field.php')],
		{
			encoding: 'utf8',
			input: JSON.stringify({
				field: {
					name: 'video',
					label: 'YouTube video',
					required: true,
					control: { name: 'youtube' },
					...field,
				},
				data: { value: { zxx: value } },
				locales: [],
				defaultLocale: 'en',
				...extra,
			}),
		},
	);
}

function control(value = '', field: Record<string, unknown> = {}): HTMLElement {
	document.body.innerHTML = `<form id="node-editor-form">${markup(value, field)}</form>`;
	return document.querySelector<HTMLElement>('[data-youtube]')!;
}

function input(box: ParentNode = document): HTMLInputElement {
	return box.querySelector<HTMLInputElement>('[data-youtube-input]')!;
}

function player(box: ParentNode = document): HTMLIFrameElement {
	return box.querySelector<HTMLIFrameElement>('[data-youtube-player]')!;
}

function button(action: string, box: ParentNode = document): HTMLButtonElement {
	return box.querySelector<HTMLButtonElement>(`[data-youtube-${action}]`)!;
}

function type(value: string, target = input()): void {
	target.value = value;
	target.dispatchEvent(new Event('input', { bubbles: true }));
}

function key(key: string, target = input(), isComposing = false): KeyboardEvent {
	const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, isComposing });
	target.dispatchEvent(event);
	return event;
}

function submitted(name = NAME): FormDataEntryValue | null {
	return new FormData(document.querySelector('form')!).get(name);
}

function error(): HTMLElement {
	return document.querySelector('[data-youtube-error]')!;
}

describe('YouTube URLs', () => {
	it.each([
		`https://www.youtube.com/watch?v=${ID}`,
		`https://www.youtube.com/watch?feature=share&v=${ID}&t=42s`,
		`https://youtu.be/${ID}?t=3`,
		`https://youtube.com/shorts/${ID}`,
		`https://www.youtube-nocookie.com/embed/${ID}?rel=0`,
		`https://www.youtube.com/live/${ID}`,
		`https://m.youtube.com/watch?v=${ID}`,
		`youtube.com/watch?v=${ID}`,
		`youtu.be/${ID}`,
	])('extracts the ID from %s', (url) => {
		expect(urlId(url)).toBe(ID);
	});

	it.each([
		ID,
		'https://vimeo.com/12345',
		'https://www.youtube.com/watch?v=tooshort',
		`https://www.youtube.com/watch?v=${ID}extra`,
		`https://example.org/watch?v=${ID}`,
		`https://youtube.com.example.org/embed/${ID}`,
		`https://example.org/youtu.be/${ID}`,
		`https://youtu.be/${ID}/extra`,
		`<iframe src="https://www.youtube.com/embed/${ID}"></iframe>`,
		'not a URL',
	])('rejects %s', (url) => {
		expect(urlId(url)).toBeNull();
	});
});

describe('YouTube control', () => {
	it('keeps typing and blur separate from the submitted video', () => {
		control();
		expect(input().placeholder).toBe('Paste a YouTube URL or video ID…');
		expect(input().getAttribute('aria-label')).toBe('YouTube video');
		expect(button('add').hidden).toBe(true);
		expect(player().hidden).toBe(true);

		type(`https://youtu.be/${ID}`);
		input().dispatchEvent(new Event('change', { bubbles: true }));

		expect(input().value).toBe(`https://youtu.be/${ID}`);
		expect(submitted()).toBe('');
		expect(player().hasAttribute('src')).toBe(false);
		expect(button('add').hidden).toBe(false);
		type('   ');
		expect(button('add').hidden).toBe(true);
	});

	it.each(['click', 'Enter'])('adds through %s without submitting the enclosing form', (action) => {
		control();
		const submit = vi.fn((event: Event) => event.preventDefault());
		document.querySelector('form')!.addEventListener('submit', submit);
		type(` https://youtu.be/${ID} `);

		if (action === 'click') button('add').click();
		else expect(key('Enter').defaultPrevented).toBe(true);

		expect(submit).not.toHaveBeenCalled();
		expect(submitted()).toBe(ID);
		expect(player().getAttribute('src')).toBe(`https://www.youtube-nocookie.com/embed/${ID}`);
		expect(player().title).toBe('YouTube video');
		expect(player().hidden).toBe(false);
		expect(document.querySelector<HTMLElement>('[data-youtube-entry]')!.hidden).toBe(true);
		expect(document.activeElement).toBe(button('replace'));
	});

	it('renders a saved video without showing its ID input', () => {
		control(ID);
		expect(player().getAttribute('src')).toBe(`https://www.youtube-nocookie.com/embed/${ID}`);
		expect(document.querySelector<HTMLElement>('[data-youtube-entry]')!.hidden).toBe(true);
		expect(button('replace').hidden).toBe(false);
		expect(submitted()).toBe(ID);
	});

	it('shows an accessible validation error without accepting the invalid video', () => {
		control();
		type('https://example.org/watch?v=abcdefghijk');
		button('add').click();

		expect(submitted()).toBe('');
		expect(error().hidden).toBe(false);
		expect(input().getAttribute('aria-invalid')).toBe('true');
		expect(input().getAttribute('aria-describedby')).toBe(error().id);
		expect(document.activeElement).toBe(input());
		type(NEXT);
		expect(error().hidden).toBe(true);
		expect(input().hasAttribute('aria-invalid')).toBe(false);
	});

	it.each([true, false])(
		'returns a required=%s video to the fresh input state on Replace',
		(required) => {
			control(ID, { required });
			document.body.insertAdjacentHTML(
				'beforeend',
				'<span id="editor-dirty" hidden>Unsaved</span>',
			);
			stops.push(installDirty());
			button('replace').click();

			expect(submitted()).toBe('');
			expect(input().value).toBe('');
			expect(input().placeholder).toBe('Paste a YouTube URL or video ID…');
			expect(document.activeElement).toBe(input());
			expect(player().hidden).toBe(true);
			expect(player().hasAttribute('src')).toBe(false);
			expect(button('add').hidden).toBe(true);
			expect(document.getElementById('editor-dirty')!.hidden).toBe(false);
		},
	);

	it.each(['click', 'Enter'])(
		'adds a replacement through %s after reporting the cleared selection',
		(action) => {
			control(ID);
			const changes: string[] = [];
			document
				.querySelector('[data-youtube-value]')!
				.addEventListener('change', () => changes.push(String(submitted())));
			button('replace').click();
			type(NEXT);
			expect(changes).toEqual(['']);
			expect(submitted()).toBe('');
			expect(button('add').hidden).toBe(false);
			if (action === 'click') button('add').click();
			else key('Enter');
			expect(submitted()).toBe(NEXT);
			expect(changes).toEqual(['', NEXT]);
			expect(player().getAttribute('src')).toBe(`https://www.youtube-nocookie.com/embed/${NEXT}`);
		},
	);

	it('does not accept Enter during composition', () => {
		control();
		type(ID);
		expect(key('Enter', input(), true).defaultPrevented).toBe(false);
		expect(submitted()).toBe('');
	});

	it('keeps immutable videos readable without offering changes', () => {
		control(ID, { immutable: true });
		expect(player().hidden).toBe(false);
		expect(input().readOnly).toBe(true);
		expect(document.querySelectorAll('[data-youtube] button')).toHaveLength(0);
		key('Enter');
		expect(submitted()).toBe(ID);
	});

	it('preserves unrecognized stored IDs until explicitly corrected', () => {
		control('legacy');
		expect(submitted()).toBe('legacy');
		expect(input().value).toBe('legacy');
		expect(player().hasAttribute('src')).toBe(false);
		type(ID);
		button('add').click();
		expect(submitted()).toBe(ID);
	});

	it('reshapes only the players belonging to the edited ratio meta', () => {
		control(ID);
		document.querySelector('form')!.insertAdjacentHTML(
			'beforeend',
			`${markup(NEXT, { name: 'other' })}
			<input type="number" name="content[video][meta][aspectRatioX][zxx]" value="9" />
			<input type="number" name="content[video][meta][aspectRatioY][zxx]" value="16" />`,
		);
		const [own, other] = document.querySelectorAll<HTMLElement>('[data-youtube]');
		const y = document.querySelector<HTMLInputElement>('input[name$="[aspectRatioY][zxx]"]')!;
		type('16', y);
		expect(own.style.getPropertyValue('--ratio')).toBe('9 / 16');
		expect(other.style.getPropertyValue('--ratio')).toBe('16 / 9');
		expect(submitted()).toBe(ID);
		type('', y);
		expect(own.style.getPropertyValue('--ratio')).toBe('9 / 16');
	});

	it.each(['', ID])(
		'focuses the control without changing "%s" when following server validation errors',
		(value) => {
			control(value);
			stops.push(installErrors());
			document
				.querySelector('form')!
				.insertAdjacentHTML(
					'afterbegin',
					`<div id="editor-errors" tabindex="-1"><button type="button" data-error-path='["content","video","value","zxx"]'>Video is required</button></div>`,
				);
			document.dispatchEvent(new Event('htmx:after:swap'));
			document.querySelector<HTMLButtonElement>('[data-error-path]')!.click();
			expect(document.activeElement).toBe(value === '' ? input() : button('replace'));
			expect(input().getAttribute('aria-invalid')).toBe('true');
			expect(submitted()).toBe(value);
		},
	);

	it.each([false, true])(
		'duplicates the current selection with replacement started=%s',
		async (replacing) => {
			const html = markup(
				'',
				{
					name: 'blocks',
					required: false,
					control: {
						name: 'blocks',
						props: {
							blockTypes: [
								{
									type: 'Cosray\\Block\\Youtube',
									label: 'Video',
									fields: [
										{
											name: 'video',
											required: true,
											control: { name: 'youtube' },
											metaControl: {
												name: 'group',
												props: {
													fields: ['X', 'Y'].map((axis) => ({
														key: `aspectRatio${axis}`,
														control: { name: 'number' },
													})),
												},
											},
										},
									],
								},
							],
						},
					},
				},
				{
					data: {
						value: {
							zxx: [{ type: 'Cosray\\Block\\Youtube', fields: { video: { value: { zxx: ID } } } }],
						},
					},
				},
			);
			document.body.innerHTML = `<form>${html}</form>`;
			stops.push(installRepeater());
			type('9', document.querySelector<HTMLInputElement>('input[name$="[aspectRatioX][zxx]"]')!);
			type('16', document.querySelector<HTMLInputElement>('input[name$="[aspectRatioY][zxx]"]')!);
			if (replacing) {
				button('replace').click();
				type(NEXT);
			}
			document.querySelector<HTMLButtonElement>('[data-repeater-duplicate]')!.click();
			await Promise.resolve();
			const boxes = document.querySelectorAll<HTMLElement>('[data-youtube]');
			expect(boxes).toHaveLength(2);
			expect(player(boxes[1]).getAttribute('src')).toBe(
				replacing ? null : `https://www.youtube-nocookie.com/embed/${ID}`,
			);
			expect(submitted('content[blocks][value][zxx][1][fields][video][value][zxx]')).toBe(
				replacing ? '' : ID,
			);
			expect(boxes[1].style.getPropertyValue('--ratio')).toBe('9 / 16');
		},
	);

	it('previews locale fallbacks without submitting drafts or copying shared content', () => {
		const locales = [
			{ id: 'en', title: 'English' },
			{ id: 'de', title: 'Deutsch', fallback: 'en' },
		];
		document.body.innerHTML = `<form data-content-locale-scope data-content-locales='${JSON.stringify(locales)}'>${markup('', { translate: true }, { locales, defaultLocale: 'de', data: { value: { zxx: ID } } })}</form>`;
		stops.push(installFallbacks());
		const en = document.querySelector<HTMLElement>('.variant[data-locale="en"]')!;
		const de = document.querySelector<HTMLElement>('.variant[data-locale="de"]')!;
		expect(input(de).placeholder).toBe(ID);
		expect(submitted('content[video][value][de]')).toBe('');
		input(de).focus();
		expect(input(de).placeholder).toBe('Paste a YouTube URL or video ID…');
		input(de).blur();
		expect(input(de).placeholder).toBe(ID);
		type(NEXT, input(en));
		expect(input(de).placeholder).toBe(ID);
		button('add', en).click();
		expect(input(de).placeholder).toBe(NEXT);
		expect(submitted('content[video][value][en]')).toBe(NEXT);
		expect(submitted('content[video][value][de]')).toBe('');
	});
});
