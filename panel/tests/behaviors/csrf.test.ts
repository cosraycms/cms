import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/csrf';
import { closeDialog } from '../../src/lib/dialogs';
import { noticeRefusal } from '../../src/lib/csrf';

vi.mock('../../src/lib/locale.js', () => ({ __: (id: string) => id }));

interface Request {
	method?: string;
	headers?: Record<string, string>;
}

function configure(request: Request): Request {
	document.dispatchEvent(new CustomEvent('htmx:config:request', { detail: { ctx: { request } } }));

	return request;
}

function dialog(): HTMLDialogElement {
	return document.querySelector<HTMLDialogElement>('dialog[data-csrf-expired]')!;
}

describe('csrf behavior', () => {
	let uninstall: () => void;

	beforeEach(() => {
		document.head.innerHTML = '<meta name="csrf-token" content="token-a">';
		document.body.innerHTML = `<dialog data-csrf-expired>
			<p>Out of date</p>
			<button type="button" data-dialog-close>Close</button>
			<button type="button" data-csrf-reload>Reload</button>
		</dialog>`;
		uninstall = install();
	});

	afterEach(() => {
		closeDialog(dialog());
		uninstall();
		vi.unstubAllGlobals();
		document.head.innerHTML = '';
		document.body.innerHTML = '';
	});

	it('sends the token with unsafe requests only', () => {
		const post = configure({ method: 'POST', headers: { 'HX-Request': 'true' } });
		const remove = configure({ method: 'delete', headers: {} });
		const get = configure({ method: 'GET', headers: { 'HX-Request': 'true' } });

		expect(post.headers).toEqual({ 'HX-Request': 'true', 'X-CSRF-Token': 'token-a' });
		expect(remove.headers).toEqual({ 'X-CSRF-Token': 'token-a' });
		expect(get.headers).toEqual({ 'HX-Request': 'true' });
	});

	it('explains a refusal and reloads only when asked', () => {
		const reload = vi.fn();
		vi.stubGlobal('location', { ...window.location, reload });

		document.body.dispatchEvent(new CustomEvent('cosray:csrf-expired', { bubbles: true }));

		expect(dialog().open).toBe(true);
		expect(reload).not.toHaveBeenCalled();

		dialog().querySelector<HTMLElement>('[data-csrf-reload]')!.click();

		expect(reload).toHaveBeenCalledOnce();
	});

	it('takes only the server refusal of a fetch() for one', () => {
		const refusal = new Response('', {
			status: 403,
			headers: { 'HX-Trigger': 'cosray:csrf-expired' },
		});

		noticeRefusal(new Response('', { status: 403 }));
		noticeRefusal(
			new Response('', { status: 500, headers: { 'HX-Trigger': 'cosray:csrf-expired' } }),
		);
		expect(dialog().open).toBe(false);

		noticeRefusal(refusal);
		expect(dialog().open).toBe(true);
	});
});
