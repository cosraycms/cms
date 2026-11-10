import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { EVENT, install, keeping } from '../../src/behaviors/autosave';
import { install as installDirty } from '../../src/behaviors/dirty';
import { catalog } from '../catalog';

let uninstall: Array<() => void> = [];
const post = vi.fn();
const ajax = vi.fn();

beforeAll(() => activate(catalog()));

function editor(state = ''): void {
	document.head.innerHTML = '<meta name="csrf-token" content="token-a">';
	document.body.innerHTML = `
		<span id="editor-dirty" hidden></span>
		<span id="editor-status"></span>
		<main id="main">
			<form id="node-editor-form">
				<input name="content[title][value][en]" value="Live">
				<input name="_complete" value="1" type="hidden">
			</form>
			<div id="editor-autosave" hidden data-url="/panel/node/page/autosave"
				data-base="2026-11-06 10:00:00+00" ${state}></div>
		</main>
		<a id="away" href="/elsewhere">Away</a>
	`;
}

function edit(value = 'Edited'): void {
	const input = document.querySelector<HTMLInputElement>('input[name^="content"]')!;
	input.value = value;
	input.dispatchEvent(new Event('input', { bubbles: true }));
}

function unloadPrevented(): boolean {
	const event = new Event('beforeunload', { cancelable: true });
	window.dispatchEvent(event);

	return event.defaultPrevented;
}

function sentBody(call = 0): Record<string, unknown> {
	return JSON.parse((post.mock.calls[call][1] as RequestInit).body as string);
}

beforeEach(() => {
	vi.useFakeTimers();
	post.mockResolvedValue(new Response('{"ok":true}', { status: 200 }));
	ajax.mockResolvedValue(undefined);
	vi.stubGlobal('fetch', post);
	vi.stubGlobal('htmx', { ajax });
});

afterEach(() => {
	uninstall.forEach((cleanup) => cleanup());
	uninstall = [];
	document.body.replaceChildren();
	vi.useRealTimers();
	vi.unstubAllGlobals();
	post.mockReset();
	ajax.mockReset();
});

function start(state = ''): void {
	editor(state);
	uninstall = [installDirty(), install()];
}

describe('autosave', () => {
	it('sends the form once editing pauses, with the state it started from', async () => {
		start();
		edit();

		await vi.advanceTimersByTimeAsync(1900);
		expect(post).not.toHaveBeenCalled();
		await vi.advanceTimersByTimeAsync(200);

		expect(post).toHaveBeenCalledOnce();
		const [url, init] = post.mock.calls[0] as [string, RequestInit];
		expect(url).toBe('/panel/node/page/autosave');
		expect(init.method).toBe('POST');
		expect((init.headers as Record<string, string>)['X-CSRF-Token']).toBe('token-a');
		expect(sentBody()).toEqual({
			content: { title: { value: { en: 'Edited' } } },
			_complete: '1',
			_base: '2026-11-06 10:00:00+00',
		});
	});

	it('sends at least every 15 seconds while editing goes on', async () => {
		start();

		for (let second = 0; second < 15; second++) {
			edit(`Edit ${second}`);
			await vi.advanceTimersByTimeAsync(1000);
		}

		expect(post).toHaveBeenCalledOnce();
	});

	it('keeps the browser prompt only until the server holds the edits', async () => {
		start();
		edit();

		expect(unloadPrevented()).toBe(true);
		await vi.advanceTimersByTimeAsync(2000);

		expect(unloadPrevented()).toBe(false);
	});

	it('keeps prompting while the server refuses the edits', async () => {
		post.mockResolvedValue(new Response('{"ok":false}', { status: 422 }));
		start();
		edit();
		await vi.advanceTimersByTimeAsync(2000);

		expect(post).toHaveBeenCalledOnce();
		expect(unloadPrevented()).toBe(true);
	});

	it('sends pending edits when the page goes', () => {
		start();
		edit();
		window.dispatchEvent(new Event('pagehide'));

		expect(post).toHaveBeenCalledOnce();
		expect((post.mock.calls[0][1] as RequestInit).keepalive).toBe(true);
	});

	it('sends pending edits when the tab is hidden', () => {
		start();
		edit();
		const visibility = vi.spyOn(document, 'visibilityState', 'get');

		visibility.mockReturnValue('visible');
		document.dispatchEvent(new Event('visibilitychange'));
		expect(post).not.toHaveBeenCalled();

		visibility.mockReturnValue('hidden');
		document.dispatchEvent(new Event('visibilitychange'));
		visibility.mockRestore();

		expect(post).toHaveBeenCalledOnce();
		expect((post.mock.calls[0][1] as RequestInit).keepalive).toBe(true);
	});

	it('sends edits too large to outlive the page as an ordinary request', () => {
		start();
		edit('x'.repeat(70000));
		window.dispatchEvent(new Event('pagehide'));

		expect(post).toHaveBeenCalledOnce();
		expect((post.mock.calls[0][1] as RequestInit).keepalive).toBe(false);
	});

	it('tries again when sending as the page goes fails', async () => {
		post.mockRejectedValueOnce(new TypeError('Failed to fetch'));
		start();
		edit();
		window.dispatchEvent(new Event('pagehide'));
		await vi.advanceTimersByTimeAsync(15000);

		expect(post).toHaveBeenCalledTimes(2);
		expect(unloadPrevented()).toBe(false);
	});

	it('keeps nothing for an editor without autosave state', async () => {
		editor();
		document.getElementById('editor-autosave')!.remove();
		uninstall = [installDirty(), install()];
		edit();
		await vi.advanceTimersByTimeAsync(20000);

		expect(post).not.toHaveBeenCalled();
		expect(unloadPrevented()).toBe(true);
	});

	it('counts a save as holding the edits it submitted', async () => {
		start();
		edit();
		document
			.getElementById('node-editor-form')!
			.dispatchEvent(new Event('submit', { cancelable: true }));
		document.getElementById('editor-autosave')!.setAttribute('data-saved', '');
		document.dispatchEvent(new Event('htmx:after:swap'));
		await vi.advanceTimersByTimeAsync(20000);

		expect(post).not.toHaveBeenCalled();
		expect(unloadPrevented()).toBe(false);
	});

	it('marks an editor restored from an autosave as unsaved', () => {
		start('data-restored');

		expect(document.getElementById('editor-dirty')!.hidden).toBe(false);
		expect(unloadPrevented()).toBe(false);
	});
});

describe('restored edits', () => {
	it('drops the pending edits for a form that discards the autosave', async () => {
		start('data-restored');
		document.body.insertAdjacentHTML('beforeend', '<form id="drop" data-autosave-drop></form>');
		edit();
		document
			.getElementById('drop')!
			.dispatchEvent(new Event('htmx:before:request', { bubbles: true }));
		await vi.advanceTimersByTimeAsync(20000);

		expect(post).not.toHaveBeenCalled();
	});

	it('removes the marks of changed fields once a save took the edits', () => {
		start('data-restored');
		document
			.getElementById('main')!
			.insertAdjacentHTML('afterbegin', '<span data-restored-mark></span>');
		document.getElementById('editor-autosave')!.setAttribute('data-saved', '');
		document.dispatchEvent(new Event('htmx:after:swap'));

		expect(document.querySelector('[data-restored-mark]')).toBeNull();
	});
});

describe('reports', () => {
	function reports(): string[] {
		const states: string[] = [];
		const listener = (event: Event) =>
			states.push((event as CustomEvent<{ state: string }>).detail.state);
		document.addEventListener(EVENT, listener);
		uninstall.push(() => document.removeEventListener(EVENT, listener));

		return states;
	}

	it('says when edits wait, go out and are kept or dropped', async () => {
		start();
		const states = reports();
		post.mockResolvedValueOnce(new Response('{"ok":true,"kept":true}', { status: 200 }));
		edit();
		await vi.advanceTimersByTimeAsync(2000);

		post.mockResolvedValueOnce(new Response('{"ok":true,"kept":false}', { status: 200 }));
		edit('Live');
		await vi.advanceTimersByTimeAsync(2000);

		expect(states).toEqual(['pending', 'saving', 'kept', 'pending', 'saving', 'dropped']);
	});

	it('says when sending failed, and leaving inside the panel asks then', async () => {
		start();
		const states = reports();
		post.mockResolvedValueOnce(new Response('{"ok":false}', { status: 500 }));
		edit();
		await vi.advanceTimersByTimeAsync(2000);

		expect(states).toEqual(['pending', 'saving', 'failed']);
		expect(keeping()).toBe(false);
	});
});
