import { afterEach, describe, expect, it, vi } from 'vitest';
import type { RichtextDoc } from '../../../src/elements/richtext/format.js';
import '../../../src/elements/richtext.js';

vi.mock('../../../src/lib/locale.js', () => ({ __: (id: string) => id }));

afterEach(() => {
	document.body.replaceChildren();
});

async function editor(value: RichtextDoc | null, readonly = false): Promise<HTMLElement> {
	const element = document.createElement('cosray-richtext');
	Object.assign(element, {
		value: { zxx: value },
		format: 'cosray-richtext',
		field: { name: 'body', immutable: readonly, placeholder: 'Write…' },
	});
	document.body.append(element);
	await Promise.resolve();

	return element.querySelector<HTMLElement>('.ProseMirror')!;
}

const words: RichtextDoc = {
	type: 'doc',
	content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Hello' }] }],
};

describe('rich text placeholder', () => {
	it('shows on the empty line of a blank document and names it for assistive technology', async () => {
		const content = await editor(null);
		const line = content.querySelector<HTMLElement>('.is-placeholder');

		expect(line?.tagName).toBe('P');
		expect(line?.dataset.placeholder).toBe('Write…');
		expect(content.getAttribute('aria-placeholder')).toBe('Write…');
	});

	it('does not show once there is text', async () => {
		const content = await editor(words);

		expect(content.querySelector('.is-placeholder')).toBeNull();
	});

	it('does not show on a read-only document', async () => {
		const content = await editor(null, true);

		expect(content.querySelector('.is-placeholder')).toBeNull();
		expect(content.hasAttribute('aria-placeholder')).toBe(false);
	});
});
