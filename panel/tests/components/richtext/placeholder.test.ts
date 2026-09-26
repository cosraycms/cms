import { mount, tick, unmount } from 'svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RichTextEditor from '../../../src/components/richtext/RichTextEditor.svelte';
import type { RichtextDoc } from '../../../src/elements/richtext/format.js';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

let app: ReturnType<typeof mount> | null = null;

afterEach(async () => {
	if (app) await unmount(app);
	app = null;
	document.body.replaceChildren();
});

async function editor(value: RichtextDoc | null, readonly = false): Promise<HTMLElement> {
	const target = document.createElement('div');

	document.body.append(target);
	app = mount(RichTextEditor, {
		target,
		props: { name: 'body', value, readonly, placeholder: 'Write…' },
	});
	await tick();

	return target.querySelector<HTMLElement>('.ProseMirror')!;
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
