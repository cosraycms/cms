import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/embed';

let uninstall: (() => void) | null = null;
let posted: Array<[unknown, string]> = [];
const parent = {
	postMessage: (message: unknown, origin: string) => posted.push([message, origin]),
};

function render(created = false): void {
	document.body.innerHTML = `
		<div data-embed data-embed-uid="node-1"
			data-embed-origins='["https://admin.example", "https://other.example"]'
			${created ? 'data-embed-created' : ''}>
			<span id="editor-dirty" hidden></span>
			<output id="editor-status"></output>
			<button type="button" data-embed-cancel>Cancel</button>
		</div>
	`;
}

beforeEach(() => {
	posted = [];
	vi.spyOn(window, 'parent', 'get').mockReturnValue(parent as unknown as Window);
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	vi.restoreAllMocks();
});

function types(): unknown[] {
	return posted
		.filter(([, origin]) => origin === 'https://admin.example')
		.map(([message]) => (message as { type: string }).type);
}

describe('embedded editor', () => {
	it('reports the unsaved-changes state to every allowed origin', async () => {
		render();
		uninstall = install();
		const indicator = document.getElementById('editor-dirty')!;

		indicator.hidden = false;
		await Promise.resolve();
		indicator.hidden = true;
		await Promise.resolve();

		expect(types()).toEqual(['cms-dirty', 'cms-pristine']);
		expect(posted.map(([, origin]) => origin)).toContain('https://other.example');
		expect(posted[0][0]).toEqual({ type: 'cms-dirty', uid: 'node-1' });
	});

	it('reports a successful save once and a cancel', () => {
		render();
		uninstall = install();
		document.getElementById('editor-status')!.classList.add('is-success');

		document.dispatchEvent(new Event('htmx:after:swap'));
		document.dispatchEvent(new Event('htmx:after:swap'));
		document.querySelector<HTMLButtonElement>('[data-embed-cancel]')!.click();

		expect(types()).toEqual(['cms-ok', 'cms-cancel']);
	});

	it('announces a node the embedded editor created', () => {
		render(true);
		uninstall = install();

		expect(posted[0][0]).toEqual({ type: 'cms-ok', uid: 'node-1', created: true });
	});

	it('stays quiet outside an embedded editor', () => {
		document.body.innerHTML =
			'<span id="editor-dirty" hidden></span><output id="editor-status" class="is-success"></output>';
		uninstall = install();

		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(posted).toEqual([]);
	});
});
