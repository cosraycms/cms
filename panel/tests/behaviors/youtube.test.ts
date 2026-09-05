import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install, thumbnail, urlId } from '../../src/behaviors/youtube';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function control(value = ''): { input: HTMLInputElement; image: HTMLImageElement } {
	document.body.innerHTML = `<div class="cms-youtube" data-youtube>
		<img class="thumbnail" data-youtube-preview alt="" ${value === '' ? 'hidden' : `src="${thumbnail(value)}"`} />
		<input type="text" name="content[video][value][zxx]" value="${value}" />
	</div>`;

	return {
		input: document.querySelector<HTMLInputElement>('input')!,
		image: document.querySelector<HTMLImageElement>('img')!,
	};
}

function type(input: HTMLInputElement, value: string): void {
	input.value = value;
	input.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('youtube control', () => {
	it('reads the id off every YouTube URL shape and nothing else', () => {
		expect(urlId('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
		expect(urlId('https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s')).toBe(
			'dQw4w9WgXcQ',
		);
		expect(urlId('https://youtu.be/dQw4w9WgXcQ?t=3')).toBe('dQw4w9WgXcQ');
		expect(urlId('https://youtube.com/shorts/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
		expect(urlId('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0')).toBe('dQw4w9WgXcQ');
		expect(urlId('https://www.youtube.com/live/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ');
		expect(urlId('dQw4w9WgXcQ')).toBeNull();
		expect(urlId('https://vimeo.com/12345')).toBeNull();
		expect(urlId('https://www.youtube.com/watch?v=tooshort')).toBeNull();
	});

	it('reduces a pasted URL to its id and shows the thumbnail', () => {
		const { input, image } = control();

		type(input, 'https://youtu.be/dQw4w9WgXcQ');

		expect(input.value).toBe('dQw4w9WgXcQ');
		expect(image.hidden).toBe(false);
		expect(image.getAttribute('src')).toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
	});

	it('asks for an image only once the id is complete', () => {
		const { input, image } = control();

		type(input, 'dQw4w9');

		expect(image.hidden).toBe(true);
		expect(image.hasAttribute('src')).toBe(false);

		type(input, 'dQw4w9WgXcQ');

		expect(image.hidden).toBe(false);

		type(input, '');

		expect(image.hidden).toBe(true);
		expect(image.hasAttribute('src')).toBe(false);
	});

	it('hides a thumbnail YouTube has no image for', () => {
		const { image } = control('dQw4w9WgXcQ');

		image.dispatchEvent(new Event('error'));

		expect(image.hidden).toBe(true);
	});

	it('leaves other inputs alone', () => {
		document.body.innerHTML = `<input type="text" value="https://youtu.be/dQw4w9WgXcQ" />`;
		const stray = document.querySelector<HTMLInputElement>('input')!;

		type(stray, 'https://youtu.be/dQw4w9WgXcQ');

		expect(stray.value).toBe('https://youtu.be/dQw4w9WgXcQ');
	});
});
